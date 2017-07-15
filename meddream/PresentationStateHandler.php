<?php
/*
	Original name: PresentationStateHandler.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		convert PR to DICOM, delete temp DICOM and get PR list
 */

namespace Softneta\MedDream\Core;

require_once __DIR__ . '/autoload.php';

use Softneta\MedDream\Core\DicomTags;

if (!strlen(session_id()))
	@session_start();


/** @brief Business logic of annotation support */
class PresentationStateHandler
{
	protected $log;
	protected $backend = null;


	function __construct(Backend $backend = null, Logging $log = null)
	{
		if (!is_null($backend))
			$this->backend = $backend;
		if (!is_null($log))
			$this->log = $log;
		else
			$this->log = new Logging();
	}


	/** @brief Return new or existing instance of Backend.

		@param array $parts  Names of PACS parts that will be initialized
		@param boolean $withConnection  Is a DB connection required?
		@return Backend

		If the underlying AuthDB must be connected to the DB, then will request the
		connection once more.
	 */
	private function getBackend($parts = array(), $withConnection = true)
	{
		if (is_null($this->backend))
		{
			$this->backend = new Backend($parts, $withConnection, $this->log);

			$err = $this->backend->pacsAnnotation->setProductVersion($this->backend->productVersion);
			if ($err)
				$this->log->asErr('internal: PacsAnnotation::setVersion(): ' . $err);
		}
		else
			$this->backend->loadParts($parts);

		if (!$this->backend->authDB->isConnected() && $withConnection)
			$this->backend->authDB->reconnect();

		return $this->backend;
	}


	/** @brief Check for configuration errors.

		@return string  Error message (empty if no error)
	 */
	private function getConfigError()
	{
		$backend = $this->getBackend(array(), false);
		$cdata = $backend->pacsConfig->exportCommonData();
		foreach ($cdata['forward_aets'] as $value)
		{
			if (!empty($value['label']))
				if (substr($value['label'], -7, 7) == '- local')
					return '';
		}
		return '$forward_aets (config.php) is missing a device with description "- local"';
	}


	/** @brief Convert annotation structure to DICOM Presentation State object

		@param string $instanceuid  Image UID
		@param array  $annotation   See below for format

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message (empty if success)
			<li><tt>'filename'</tt> - full path to a new DICOM file
		</ul>

		Example of @p $annotation:

@verbatim
		array(
			'description'=>"description",
			'title'=>'title' ,
			'annotations'=>array(
				array(
					'type'=> 'TEXT',
					'points'=>array(
						//multiple graphic lines
						array('0','0','0','0')
					),
	 *				'graphicType'=> array('POLYLINE','POINT', 'CIRCLE',,..),
					'text'=>array(
						array(
							 //multiple text labels or boxes
							'text'=>'description',
							'textpos'=> array('0','0', '10', '12','12','12'),
							'textstyle'=>array('align'=>'LEFT')
						)
					)
				)
			)
		);
@endverbatim
	 */
	public function anotationToDicom($instanceuid, $annotation)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceuid, ', ', $annotation, ')');
		$audit = new Audit('ANNOTATION LAYERS TO DICOM');

		$backend = $this->getBackend(array('Annotation'));
		if (!$backend->authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$return['error'] = $err;
			$this->log->asErr($err);
			$audit->log(false, $instanceuid);
			return $return;
		}

		$return = array('error' => '');
		$return['error'] = $backend->pacsAnnotation->isSupported(true);
		if ($return['error'] != '')
		{
			$this->log->asErr($return['error']);
			$audit->log(false, $instanceuid);
			return $return;
		}
		$return['error'] = $this->getConfigError();
		if ($return['error'] != '')
		{
			$this->log->asErr($return['error']);
			$audit->log(false, $instanceuid);
			return $return;
		}

		/* downgrade to amfPHP < 2.0 */
		if (is_object($annotation))
			$annotation = get_object_vars($annotation);
		$this->log->asDump('$annotation = ', $annotation);

		$return = $backend->pacsAnnotation->collectStudyInfoForImage($instanceuid);
		if ($return['error'] != '')
		{
			if (isset($return['data']))
				unset($return['data']);
			$audit->log(false, $instanceuid);
			return $return;
		}
		$return1 = $this->getfileCharset($instanceuid);
		if ($return1['error'] != '')
		{
			$audit->log(false, $instanceuid);
			return $return1;
		}
		$charset = '';
		if (!empty($return1['charset']))
			$charset = $return1['charset'];
		else
		{
			$return['error'] = 'Character encoding is not valid, see the log';
			$this->log->asErr('DICOM file charset or $default_annotation_character_set (config.php) is not valid');
			$audit->log(false, $instanceuid);
			return $return;
		}
		unset($return1);
		$data = array(
			'patientname' => '',
			'patientid' => '',
			'birthdate' => '',
			'sex' => '',
			'studyuuid' => '',
			'studydate' => '',
			'studytime' => '',
			'referringphysician' => '',
			'studyid' => '',
			'accessionnum' => '',
			'seriesuuid' => '',
			'seriesnumber' => '',
			'presentationcreationdate' => $return['data']['date'],
			'presentationcreationtime' => $return['data']['time'],
			'contentlabel' => 'presentation',
			'contentlabelAsDescription' => 'presentation',
			'presentationdescription' => '',
			'presentationcreator' => $backend->authDB->getAuthUser(),
			'numcolumns' => 0,
			'numrows' => 0,
			'aspectratio' => '1\1',
			'layers' => array(),
			'instancenumber' => 0,
			//'transformation' => array('rotation' => 0,90,180,270, 'flip' => Y/N);
			'sopinstance' => '' // need generate new
		);
		$data['referencedseriessequence'][0] = array();
		$data['referencedseriessequence'][0]['seriesuid'] = $return['data']['seriesuid'];
		$data['referencedseriessequence'][0]['imagesequence'] = array(array(
				'sopud' => $return['data']['sopud'],
				'instanceuid' => $return['data']['instanceuid'],
		));

		/* swf output */
		if (isset($annotation[0]["graphic"]))
		{
			$data['layers'] = $annotation;
		}
		else
		if (isset($annotation['annotations']))
		{
			$data['layers'] = $this->layerToDicom($annotation['annotations']);
		}
		if(empty($data['layers']))
		{
			$return['error'] = 'no annotation to save';
			$this->log->asErr($return['error']);
			$audit->log(false, $instanceuid);
			return $return;
		}
		if (isset($annotation['description']))
			$data['presentationdescription'] = $annotation['description'];
		if (isset($annotation['title']))
			$data['contentlabelAsDescription'] = $annotation['title'];
		$data = array_merge($data, $return['data']);
		$this->log->asDump("prepared data: ", $data);
		$sep = DIRECTORY_SEPARATOR;
		$converter = new SOP\PresentationState();
		$converter->xml2dcm = __DIR__ . $sep . 'dcm4che' . $sep . 'bin' . $sep . 'xml2dcm';
		if (PHP_OS == 'WINNT')
			$converter->xml2dcm .= '.bat';
		$converter->cfgfilename = __DIR__ . $sep . 'temp' . $sep . date("Y-m-dHis") . '.xml';
		$converter->dcmfilename = __DIR__ . $sep . 'temp' . $sep . $data['sopinstance'] . '.dcm';
		$converter->rootuid = Constants::ROOT_UID;
		$converter->encoding = $charset;
		$converter->buildDicom($data);

		if ($converter->error != '')
		{
			$return['error'] = $converter->error;
			$this->log->asErr('conversion error: ' . $return['error']);
			$audit->log(false, $instanceuid);
		}
		else
		{
			$return = array('error' => '');
			$audit->log("SUCCESS, PR instance '{$converter->dcmfilename}'", $instanceuid);
			$this->log->asDump('Annotation forward: ' . $converter->dcmfilename);
			$status = $this->forward($converter->dcmfilename);
			if ($status == 'success')
				$return['msg'] = 'Annotation saved successfully';
			else
				$return['error'] = 'Failed to save Annotation: status ' . $status;
		}

		$this->deleteFile($converter->dcmfilename);
		//$return['error'] = 'ok';
		$converter->clear();
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Get character set from a DICOM image file.

		A derived PR file that references this image file must contain the same value
		of Specific Character Set tag. It's a typical use for this function.

		@param string $instanceuid - image uid
		@return array
	 */
	public function getfileCharset($instanceuid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceuid, ')');
		$return = array('error' => '', 'charset'=>'');
		$backend = $this->getBackend(array('Annotation', 'Structure'));
		$st = $backend->pacsStructure->instanceGetMetadata($instanceuid);
		if (strlen($st['error']))
		{
			$return['error'] = $st['error'];
			return $return;
		}
		$tagClass = new DicomTags($backend);
		$prdata = $tagClass->getTagsListByPath($st['path'], 0);
		unset($st);
		if (strlen($prdata['error']))
		{
			/* the error message from getTagsListByPath is almost good, let's make it
			   more specific
			 */
			$return['error'] = "Failed to read/parse image file\n(error code " .
				$prdata['error'] . ', see logs for more details)';
			return $return;
		}

		$tag = $tagClass->getTag($prdata['tags'], 8, 5);
		$charSet = '';
		if (!empty($tag['data']))
			$charSet = $tag['data'];
		unset($tag);
		unset($prdata);
		unset($tagClass);
		$this->log->asDump('found $charSet=', $charSet);
		$return = $backend->cs->validateCharSet($charSet, $backend->cs->defaultAnnotationCharSet);
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Transform input layers to array required by SOP\PresentationState

		@param array $layers
		@return array
	 */
	private function layerToDicom($layers)
	{
		$this->log->asDump('$layers = ', $layers);

		/*
			html send format:
			  [{
			  type: 'LINE| ANGLE | POLYLINE | TEXT | POINTS',
			  points: [['x1','y1','x2','y2']],
			  graphicType:['POINT', 'POLYLINE', 'CIRCLE',..](optionl)
			  text:[
			  		{
			  		text:'description',],
			  		textpos: ['x1','y1']
			  		textstyle:{ align: 'LEFT | CENTER | RIGHT'}
			  		},

			  }]

			convert to:

			  var returnarr:Array = [];
			  returnarr["name"] = "POINTS";
			  returnarr["order"] = measnumber;
			  returnarr["text"] = [];
			  returnarr["graphic"] = [];

			  returnarr["text"][i] = [];
			  returnarr["text"][i]["text"] = textslist[pointindex].text;
			  returnarr["text"][i]["x"] = textslist[pointindex].x;
			  returnarr["text"][i]["y"] = textslist[pointindex].y;

			  var pp:Point = getpoint..;

			  returnarr["graphic"][i] = [];
			  returnarr["graphic"][i]["type"] = "POINT";
			  returnarr["graphic"][i]["points"] = [pp.x, pp.y];
		 */
		$pointTypes = array('POINT','POLYLINE','INTERPOLATED','CIRCLE','ELLIPSE');
		$layerstmp = array();
		for ($i = 0; $i < count($layers); $i++)
		{
			if (empty($layers[$i]['type']))
				continue;

			$item = array(
				'name' => $layers[$i]['type'],
				'order' => $i,
				'text' => array(),
				'graphic' => array()
			);


			$pass = false;
			//if have multiple texts
			if (count($layers[$i]['text']) > 0)
			{
				foreach ($layers[$i]['text'] as $text)
				{
					if ($text['text'] == '')
						continue;

					$textitem = array('text' => $text['text']);

					$pass = true;
					if (isset($text['textstyle']))
					{
						if (isset($text['textstyle']['align']))
						{
							if (!isset($textitem['style']))
								$textitem['style'] = array();

							$align = array('LEFT', 'CENTER', 'RIGHT');
							if (in_array($text['textstyle']['align'], $align))
								$textitem['style']['horizontalalign'] = $text['textstyle']['align'];
						}
					}

					$count = count($text['textpos']);
					//for test type, if have 4 points for box
					if (($count == 2) || ($count == 6))
					{
						$textitem['x'] = $text['textpos'][0];
						$textitem['y'] = $text['textpos'][1];
					}
					if (isset($text['textanchor']))
					{
						$textitem['x'] = $text['textanchor'][0];
						$textitem['y'] = $text['textanchor'][1];
					}
					//if 4 or 6 points for box or for anchotr and box
					if ($count >= 4)
					{
						$u = $count - 4;
						$textitem['box'] = array(
							array('x' => $text['textpos'][$u],
								'y' => $text['textpos'][$u + 1]),
							array('x' => $text['textpos'][$u + 2],
								'y' => $text['textpos'][$u + 3]),
						);
					}

					$item['text'][] = $textitem;
				}
			}
			//if have points
			if (isset($layers[$i]['points']))
			{
				$count = count($layers[$i]['points']);
				for($j = 0; $j < $count; $j++)
				{
					$points = $layers[$i]['points'][$j];
					if (count($points) > 0)
					{
						if (count($points) > 2)
							$type = 'POLYLINE';
						else
							$type = 'POINT';
						if(!empty($layers[$i]['graphicType'][$j]) &&
							in_array($layers[$i]['graphicType'][$j], $pointTypes))
							$type = $layers[$i]['graphicType'][$j];

						$item['graphic'][] = array(
							'type' => $type,
							'points' => $points
						);
						$pass = true;
					}
				}
			}

			if ($pass)
				$layerstmp[] = $item;
		}

		return $layerstmp;
	}


	/** @brief Collect PR objects that refer to the given image.

		@param string $instanceuidpk - uid or pk
		@return string

		@todo There is a quite annoying message in the logs if the PACS doesn't support
		      annotations. The viewer should call Pacs\AnnotationIface::isSupported() first,
		      in order to decide that any other annotation methods are not to be called.
		      Even better would be to obey a new privilege from Pacs\AuthIface, "annotation".
	 */
	public function getImagePrlist($instanceuidpk)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceuidpk, ')');

		$return = array('prlist' => array());

		/* can we continue? */
		$backend = $this->getBackend(array('Annotation', 'Structure'));
		if (!$backend->authDB->isAuthenticated())
		{
			$return['error'] = 'reconnect';
			$this->log->asErr('not authenticated');
			return $return;
		}
		$err = $backend->pacsAnnotation->isSupported();
		if ($err != '')
		{
			$this->log->asErr($err);
				/* isSupported() is very simple and doesn't do any logging */
			$return['error'] = '';
				/* otherwise we'll get a very annoying popup when opening every image
				   if the PACS doesn't support annotations. The popup is adequate
				   *only* when the user chooses some annotations-related function.
				 */
			return $return;
		}

		/* obtain a link to the study */
		$return = $backend->pacsStructure->instanceGetStudy($instanceuidpk);
		if ($return['error'] != '')
			return $return;
		if (is_null($return['studyuid']))
			return $return;
		$studyuid = $return['studyuid'];

		$return = $backend->pacsAnnotation->collectPrSeriesImages($studyuid);
		if ($return['error'] != '')
			return $return;
		$this->log->asDump('seriesimagelist = ', $return['seriesimagelist']);
		$prlist = array();

		/* we'll need a true SOP Instance UID to compare with a corresponding attribute
		   in the PR file. However in some PACSes $instanceuidpk is not an UID.
		 */
		$result = $backend->pacsStructure->instanceKeyToUid($instanceuidpk);
		if (!empty($result['error']))
		{
			$return['error'] = $result['error'];
			return $return;
		}
		else
			$instanceuid = $result['imageuid'];

		if (!empty($instanceuid) && isset($return['seriesimagelist']))
		{
			foreach ($return['seriesimagelist'] as $seriesuid => $imagelist)
				foreach ($imagelist as $imageuid => $path)
				{
					$pritem = $this->parsePR($path, $instanceuid, $instanceuidpk);
					if (!strlen($pritem['error']))
					{
						unset($pritem['error']);

						if (isset($pritem['type']))
							$prlist[] = $pritem;
					}
					else
					{
						$return['error'] = $pritem['error'];
						$this->log->asErr($return['error']);
					}
				}
		}

		$return['prlist'] = $prlist;
		unset($return['seriesimagelist']);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Convert a string to UTF-8.

		@param string $value
		@param string $charSet
		@return string
	 */
	function fixValue($charSet, $value)
	{
		$backend = $this->getBackend();
		$value = $backend->cs->encodeWithCharset($charSet, $value);
		return trim($value);
	}


	/** @brief Parse DICOM data and see if this is the required Presentation State object

		@param string $path - file path
		@param string $belongstouid - real uid
		@param string $belongstouidpk - pk or uid
		@return array
	 */
	public function parsePR($path, $belongstouid, $belongstouidpk)
	{
		$return = array('error' => '');
		$backend = $this->getBackend(array('Structure'));
		$tagClass = new DicomTags($backend);
		$prdata = $tagClass->getTagsListByPath($path, 6);
		if (strlen($prdata['error']))
		{
			/* the error message from getTagsListByPath is almost good, let's make it
			   more specific
			 */
			$return['error'] = "Failed to read/parse annotation file(s)\n(error code " .
				$prdata['error'] . ', see logs for more details)';
			return $return;
		}

		$return['annotations'] = array();
		$layer = array();
		$uids = array();
		$numlayers = -1;
		$textpos = -1;
		$instanceid = '';
		$charSet = '';
		$tag = $tagClass->getTag($prdata['tags'], 8, 5);
		$charSet = '';
		if (isset($tag['data']) && !is_null($tag['data']))
			$charSet = $tag['data'];
		unset($tag);
		$getTitle = false;
		foreach ($prdata['tags'] as $value)
		{
			if (array_key_exists('data', $value) || ($value['vr'] == 'SQ'))
			{
				if (!array_key_exists('data', $value) || is_null($value['data']))
					$value['data'] = '';
				switch ((65536 * $value['group'] + $value['element']))
				{
					case 0x00020003:
						$backend = $this->getBackend(array('Structure'));
						$result = $backend->pacsStructure->instanceUidToKey($value['data']);
						if (!empty($result['error']))
						{
							$return['error'] = $result['error'];
							return $return;
						}
						else
							$instanceid = $result['imagepk'];

						if (empty($instanceid))
							$this->log->asWarn(__METHOD__ . 'warning: no image(0x00020003) pk/uid found for ' . trim($value['data']));
						break;
					case 0x0008103e:
						if (trim($value['data']) == Constants::PR_SERIES_DESC)
						{
							unset($return['annotations']);
							$return['instanceid'] = $instanceid;
							$return['parentid'] = $belongstouidpk;
							$return['path'] = $path;
							$return['type'] = 'jpg';
						}
						break;
					case 0x00080060:
						if (trim($value['data']) == 'PR')
						{
							$return['instanceid'] = $instanceid;
							$return['parentid'] = $belongstouidpk;
							$return['type'] = 'dicom';
						}
						break;
					case 0x00081155:
						$this->log->asDump('$value = ', $value['data']);
						$uids[] = $this->fixValue($charSet,$value['data']);
						break;
					case 0x00700081:
						if($getTitle)
						{
							$temp = $this->fixValue($charSet,$value['data']);
							if($temp != '')
								$return['title'] = $temp;
							else
								if(!isset($return['title']))
									$return['title'] = '';
						}
						else
							$return['description'] = $this->fixValue($charSet,$value['data']);
						break;
					case 0x00700087:
						$getTitle = true;
						break;
					case 0x00700080:
						$return['title'] = $value['data'];
						break;
					case 0x00700002:
						if (!isset($return['annotations'][$numlayers]))
							$numlayers++;
						else
						if (isset($return['annotations'][$numlayers]['text']) ||
							isset($return['annotations'][$numlayers]['points']))
							$numlayers++;
						else
							unset($return['annotations'][$numlayers]);
						$textpos = -1;
						$return['annotations'][$numlayers]['type'] = $this->fixValue($charSet,$value['data']);
						break;
					case 0x00700006:
						$textpos++;
						$numlayers = $this->getIndex($numlayers);
						$this->setPosDefault($return['annotations'][$numlayers]['text'], $textpos);

						$return['annotations'][$numlayers]['text'][$textpos]['text'] = $this->fixValue($charSet,$value['data']);
						break;
					case 0x00700014:
						$textpos = $this->getIndex($textpos);
						$numlayers = $this->getIndex($numlayers);
						$this->setPosDefault($return['annotations'][$numlayers]['text'], $textpos);

						if (count($return['annotations'][$numlayers]['text'][$textpos]['textpos']) == 0)
							$return['annotations'][$numlayers]['text'][$textpos]['textpos'] = $value['data'];
						else
							$return['annotations'][$numlayers]['text'][$textpos]['textanchor'] = $value['data'];
						break;
					case 0x00700010:
						$textpos = $this->getIndex($textpos);
						$numlayers = $this->getIndex($numlayers);
						$this->setPosDefault($return['annotations'][$numlayers]['text'], $textpos);

						for ($i = 0; $i < count($value['data']); $i++)
							$return['annotations'][$numlayers]['text'][$textpos]['textpos'][] = $value['data'][$i];
						break;

					case 0x00700011:
						$textpos = $this->getIndex($textpos);
						$numlayers = $this->getIndex($numlayers);
						$this->setPosDefault($return['annotations'][$numlayers]['text'], $textpos);

						for ($i = 0; $i < count($value['data']); $i++)
							$return['annotations'][$numlayers]['text'][$textpos]['textpos'][] = $value['data'][$i];
						break;
					case 0x00700242:
						$textpos = $this->getIndex($textpos);
						$numlayers = $this->getIndex($numlayers);
						$this->setPosDefault($return['annotations'][$numlayers]['text'], $textpos);

						$return['annotations'][$numlayers]['text'][$textpos]['textstyle'] = $this->fixValue($charSet, $value['data']);
						break;
					case 0x00700022:
						$numlayers = $this->getIndex($numlayers);
						$this->setitemArray($return['annotations'][$numlayers], 'points');

						$return['annotations'][$numlayers]['points'][] = $value['data'];
						break;
					case 0x00700023:
						$numlayers = $this->getIndex($numlayers);
						$this->setitemArray($return['annotations'][$numlayers], 'graphicType');
						$return['annotations'][$numlayers]['graphicType'][] = $value['data'];
						break;
				}
			}
		}

		if (!(isset($return['annotations'][$numlayers]['text']) ||
			isset($return['annotations'][$numlayers]['points'])))
			unset($return['annotations'][$numlayers]);

		$this->log->asDump($belongstouid . ' in? ', $uids);

		if (!in_array($belongstouid, $uids))
		{
			unset($return['annotations']);
			unset($return['type']);
		}

		return $return;
	}


	private function getIndex($i)
	{
		return max($i, 0);
	}


	/** @brief Create object arrays.
	 *
		@param array $item
		@param string|int $textpos
	 */
	private function setPosDefault(&$item, $textpos)
	{
		$this->setitemArray($item, $textpos);
		$this->setitemArray($item[$textpos], 'textpos');
	}


	/** @brief Create array for a missing key.
	 *
		@param array $item
		@param string|int $key
	 */
	private function setitemArray(&$item, $key)
	{
		if (!isset($item[$key]))
			$item[$key] = array();
	}


	/** @brief Return UIDs of images that have referencing PR objects

		@param string $studyuid
		@return array

		sample: array('seriesid_that_have_pr'=>array('imageid1_that_have_pr',..))
	 */
	public function getStudyPRList($studyuid)
	{
		$this->log->asDump('begin ' . __METHOD__);

		/* is it safe to continue? */
		$backend = $this->getBackend(array('Annotation', 'Structure'));
		if (!$backend->authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$this->log->asErr($err);
			return array('error' => $err);
		}
		$err = $backend->pacsAnnotation->isSupported();
		if ($err != '')
		{
			$this->log->asWarn(__METHOD__ . ": $err");

			/* NOTE: there is no need for interactive messages when opening every
			   study, as they will severely annoy the user; failure shall be
			   silent at this point
			 */
			return array('error' => '');
		}

		$return = $backend->pacsAnnotation->collectPrSeriesImages($studyuid);
		if ($return['error'] != '')
			return $return;

		if (isset($return['seriesimagelist']))
		{
			foreach ($return['seriesimagelist'] as $seriesuid => $imagelist)
			{
				foreach ($imagelist as $imageuid => $path)
				{
					$pritem = $this->itemPrUids($path);
					if ($pritem['error'] == '')
					{
						unset($pritem['error']);
						$referedseresuid = $pritem['seriesuid'];
						if (empty($referedseresuid))
						{
							continue;
						}

						if (!isset($return['series'][$referedseresuid]))
							$return['series'][$referedseresuid] = array();

						if (!empty($pritem['uidlist']))
							$return['series'][$referedseresuid] = array_unique(
								array_merge($return['series'][$referedseresuid], $pritem['uidlist']));
					}
					else
					{
						$return['error'] = $pritem['error'];
						unset($return['seriesimagelist']);
						$this->log->asErr('itemPrUids failed');
						return $return;
					}
				}
			}
		}
		unset($return['seriesimagelist']);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Parse DICOM PR file and return referenced Series UID and Image UIDs

		@param string $path
		@return array
	 */
	public function itemPrUids($path)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $path, ')');

		$backend = $this->getBackend(array('Structure'));

		$return = array('error' => '');
		$this->log->asDump('meddream_get_tags(', dirname(__FILE__), ', ', $path, ', 6)');
		$prdata = meddream_get_tags(dirname(__FILE__), $path, 6);
		if ($prdata['error'] != 0)
		{
			$this->log->asErr('meddream_get_tags error ' . $prdata['error'] . ' on ' . $path);
			$return['error'] = "Failed to read/parse annotation file(s)\n(error code " .
				$prdata['error'] . ', see logs for more details)';
			return $return;
		}

		$uids = array();
		$takeseriesuid = false;
		$seriesuid = '';
		$seriesfounded = false;
		foreach ($prdata['tags'] as $value)
		{
			if (isset($value['data']))
			{
				switch (65536 * $value['group'] + $value['element'])
				{
					case 0x00081155:
						$takeseriesuid = true;
						$result = $backend->pacsStructure->instanceUidToKey($value['data']);
						if (!empty($result['error']))
						{
							$return['error'] = $result['error'];
							return $return;
						}
						else
							$imageuid = $result['imagepk'];

						if (!empty($imageuid))
							$uids[] = $imageuid;
						else
						if (empty($seriesuid))
							$this->log->asWarn('warning: no image pk/uid found for ' . trim($value['data']));
						break;
					case 0x0020000e:
						if (!$seriesfounded)
						{
							$result = $backend->pacsStructure->seriesUidToKey($value['data']);
							if (!empty($result['error']))
							{
								$return['error'] = $result['error'];
								return $return;
							}
							else
								$seriesuid = $result['seriespk'];

							if (empty($seriesuid))
								$this->log->asWarn('warning: no series pk/uid found for ' . trim($value['data']));
							$seriesfounded = true;
						}
						break;
				}
			}
		}

		$return['uidlist'] = $uids;
		$return['seriesuid'] = $seriesuid;

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);

		return $return;
	}


	/** @brief Delete a temporary DICOM file.

		@param string $file - file path
		return string
	 */
	function deleteFile($file)
	{
		$this->log->asDump('begin ' . __METHOD__);
		$error = '';

		if (file_exists($file))
			if (is_file($file))
			{
				@unlink($file);
			}

		if (file_exists($file))
		{
			$error .= "Failed to delete file $file";
			$this->log->asErr($error);
		}
		else
			$this->log->asDump('Deleted: ', $file);

		$this->log->asDump('end ' . __METHOD__);

		return $error;
	}


	/** @brief Test if the study contains a Presentation State object.

		@param string $studyuid
		@return boolean
	 */
	public function hasAnnotations($studyuid)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $studyuid, ')');

		$backend = $this->getBackend(array('Annotation'));
		$r = $backend->pacsAnnotation->isPresentForStudy($studyuid);

		$this->log->asDump('end ' . __METHOD__);
		return $r;
	}


	/** @brief Convert JPG file to a DICOM Secondary Capture object

		@param string $instanceuid - image uid
		@param array $annotation - layer - array of points
		return array
	 */
	public function jpgToDicom($instanceuid, $annotation)
	{
		$this->log->asDump('begin ' . __METHOD__ . '(', $instanceuid, ', ', $annotation, ')');
		$audit = new Audit('ANNOTATION JPG TO DICOM');

		$backend = $this->getBackend(array('Annotation'));
		if (!$backend->authDB->isAuthenticated())
		{
			$err = 'not authenticated';
			$return['error'] = $err;
			$this->log->asErr($err);
			$audit->log(false, $instanceuid);
			return $return;
		}

		$return = array('error' => '');
		$return['error'] = $backend->pacsAnnotation->isSupported();
		if ($return['error'] != '')
		{
			$this->log->asErr($return['error']);
			$audit->log(false, $instanceuid);
			return $return;
		}
		$return['error'] = $this->getConfigError();
		if ($return['error'] != '')
		{
			$this->log->asErr($return['error']);
			$audit->log(false, $instanceuid);
			return $return;
		}

		/* downgrade to amfPHP < 2.0 */
		if (is_object($annotation))
			$annotation = get_object_vars($annotation);

		$this->log->asDump('$annotation = ', $annotation);

		$return = $backend->pacsAnnotation->collectStudyInfoForImage($instanceuid, 'jpg');
		if ($return['error'] != '')
		{
			if (isset($return['data']))
				unset($return['data']);

			$audit->log(false, $instanceuid);
			return $return;
		}

		if (!file_exists($annotation['file']))
		{
			$return['error'] = 'no image file to save';
			$this->log->asErr($return['error']);
			$audit->log(false, $instanceuid);
			return $return;
		}

		$data = array(
			'patientname' => '',
			'patientid' => '',
			'birthdate' => '',
			'sex' => '',
			'studyuuid' => '',
			'studydate' => '',
			'studytime' => '',
			'referringphysician' => '',
			'studyid' => '',
			'accessionnum' => '',
			'description' => '',
			'modality' => $return['data']['currentmodality'],
			'seriesuuid' => '',
			'seriesnumber' => '',
			'presentationcreationdate' => $return['data']['date'],
			'presentationcreationtime' => $return['data']['time'],
			'contentlabel' => 'presentation',
			'contentlabelAsDescription' => 'presentation',
			'presentationdescription' => '',
			'presentationcreator' => $backend->authDB->getAuthUser(),
			'numcolumns' => 0,
			'numrows' => 0,
			'instancenumber' => 0,
			'sopinstance' => '', // need generate new
			'laterality' => '',
			'seriesdescription' => Constants::PR_SERIES_DESC
		);
		$data['referencedseriessequence'][0] = array();
		$data['referencedseriessequence'][0]['seriesuid'] = $return['data']['seriesuid'];
		$data['referencedseriessequence'][0]['imagesequence'] = array(array(
				'sopud' => $return['data']['sopud'],
				'instanceuid' => $return['data']['instanceuid'],
		));

		if (isset($annotation['description']))
			$data['presentationdescription'] = $annotation['description'];

		if (isset($annotation['title']))
			$data['contentlabelAsDescription'] = $annotation['title'];

		$data = array_merge($data, $return['data']);

		$this->log->asDump("prepared data: ", $data);

		$dimensions = $this->getDimensions($annotation['file'], 'jpeg');
		$this->log->asDump('$dimensions: ', $dimensions);

		if ($dimensions['error'] != '')
		{
			$return['error'] = $dimensions['error'];
			$audit->log(false, $instanceuid);
			return $return;
		}
		$return1 = $this->getfileCharset($instanceuid);
		if ($return1['error'] != '')
		{
			$audit->log(false, $instanceuid);
			return $return1;
		}
		$charset = '';
		if (!empty($return1['charset']))
			$charset = $return1['charset'];
		else
		{
			$return['error'] = 'Character encoding is not valid, see the log';
			$this->log->asErr('DICOM file charset or config.php($default_annotation_character_set) is not valid');
			$audit->log(false, $instanceuid);
			return $return;
		}
		unset($return1);
		if (isset($dimensions['height']))
		{
			$data['numrows'] = $dimensions['height'];
			$data['numcolumns'] = $dimensions['width'];
		}
		$sep = DIRECTORY_SEPARATOR;
		$converter = new SOP\SecondaryCapture();
		$converter->xml2dcm = __DIR__ . $sep . 'dcm4che' . $sep . 'bin' . $sep . 'xml2dcm';
		if (PHP_OS == 'WINNT')
			$converter->xml2dcm .= '.bat';
		$converter->cfgfilename = __DIR__ . $sep . 'temp' . $sep . date("Y-m-dHis") . '.xml';
		$converter->dcmfilename = __DIR__ . $sep . 'temp' . $sep . $data['sopinstance'] . '.dcm';
		$converter->rootuid = Constants::ROOT_UID;
		$converter->filename = $annotation['file'];
		$converter->encoding = $charset;
		$converter->buildDicom($data);

		if ($converter->error != '')
		{
			$return['error'] = $converter->error;
			$this->log->asErr('conversion error: ' . $return['error']);
			$audit->log(false, $instanceuid);
		}
		else
		{
			$return = array('error' => '');
			$audit->log("SUCCESS, file '{$converter->dcmfilename}'", $instanceuid);
			$this->log->asDump('Annotation forward: ' . $converter->dcmfilename);
			$status = $this->forward($converter->dcmfilename);
			if ($status == 'success')
				$return['msg'] = 'Annotation saved successfully';
			else
				$return['error'] = 'Failed to save Annotation: status ' . $status;
		}
		
			
		$this->deleteFile($converter->dcmfilename);
		$converter->clear();

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Get image size.

		@param string $path
		@param string $type
		@return array
	 */
	private function getDimensions($path, $type)
	{
		$return = array();
		$return['error'] = '';

		if (file_exists($path))
		{
			if ($type == 'jpeg')
			{
				/* avoid calling a missing function with @ -- a failure will be impossible to diagnose
				   without modifying the code
				 */
				if (!function_exists('imagecreatefromjpeg'))
				{
					$return['error'] = 'GD2 extension is missing';
					return $return;
				}

				$img1 = @imagecreatefromjpeg($path);
				if ($img1 === false)
					return $return;

				$return["width"] = imagesx($img1);
				$return["height"] = imagesy($img1);
				imagedestroy($img1);
			}
		}
		return $return;
	}


	/** @brief Send a DICOM file to the local PACS.

		@param path $file
		@return string

		Simply a wrapper over Study::forward() and related functions that performs
		everything in a single call.
	 */
	public function forward($file)
	{
		chdir(__DIR__);
		$study = new Study();
		$jobid = $study->forward('', '', $file);
		$this->log->asDump('$jobid = ' . $jobid);
		$waitcount = 0;
		$status = 'submitted';

		/* Study::forwardStatus(), when used with nonzero 3rd parameter, returns a
		   progress indicator (some number with percent sign), a string 'success',
		   or an empty string (alternatively, a string 'failed') that indicates an
		   error. Only the first case is worth repeating.
		 */
		while ((strlen($status) > 0) && ($status != 'success') && ($status != 'failed'))
		{
			$waitcount++;
			sleep(1);
			$status = $study->forwardStatus($jobid, 0);
			$this->log->asDump('$status = ' . $status);
			if ($waitcount > 20)
				break;
		}
		return $status;
	}
}
?>
