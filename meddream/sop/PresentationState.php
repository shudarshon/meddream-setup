<?php
/*
	Original name: PresentationState.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Server-side part of DICOM PR creation
 */

namespace Softneta\MedDream\Core\SOP;

use Softneta\MedDream\Core\SOP\DicomCommon;

class PresentationState extends DicomCommon
{
	public $xml2dcm = '';
	public $cfgdata = array();
	public $cfgfilename = '';
	public $dcmfilename = '';
	public $transfersyntax = '1.2.840.10008.1.2.1';
	public $sopuid = '1.2.840.10008.5.1.4.1.1.11.2';
	public $rootuid = '1.3.6.1.4.1.44316';
	public $swversion = '';
	public $implementatinversion = 'PR2DCM';
	public $manufacturer = 'Softneta';
	public $icclen = 0;

	/**
     * convert parset data to dicom
	 *
	 *@param array $data - parsed array
     *return boolean true/false
     */
	function buildDicom($data)
	{
		if (count($data) == 0)
			return false;

		$this->data = $data;
		//validate
		$this->validateData();
		//if error in building dicom config
		if ($this->error != '')
			return false;
		//set extra data
		$this->data['instancedate'] = date("Ymd");
		$this->data['instancetime'] = date("His");
		$this->data['contentdate'] = $this->data['studydate'];
		$this->data['contenttime'] = $this->data['studytime'];

		if ($this->data['contentdate'] == '')
			$this->data['contentdate'] = $this->data['instancedate'];
		if ($this->data['contenttime'] == '')
			$this->data['contenttime'] = $this->data['instancetime'];

		if ($this->data['presentationcreationdate'] == '')
			$this->data['presentationcreationdate'] = $this->data['instancedate'];
		if ($this->data['presentationcreationtime'] == '')
			$this->data['presentationcreationtime'] = $this->data['instancetime'];

		if ($this->data['studyid'] == '')
			$this->data['studyid'] = $this->data['studydate'] . $this->data['studytime'];
		//set modules
		$this->setPatientModule();
		$this->setGeneralStudyModule();
		$this->setGeneralSeriesModule();
		$this->setGeneralEquipmentModule();
		$this->setPresentationStateIdentificationModule();
		$this->setPresentationStateRelationshipModule();
		$this->setDisplayedAreaModule();
		$this->setGraphicAnnotationModule();
		$this->setSpatialTransformationModule();
		$this->setGraphicLayerModule();
		$this->setICCProfileModule();
		$this->setSOPCommonModule();
		$this->setOtherMetadata();

		//if error in building dicom config
		if ($this->error != '')
			return false;

		if (count($this->cfgdata) >0)
		{
			$this->cfgfilename = str_replace("\\", '/', $this->cfgfilename);
			$this->dcmfilename = str_replace("\\", '/', $this->dcmfilename);
			$this->convertDicom();
		}
		return false;
	}
	/**
     * validate parsed data
	 *
     */
	function validateData()
	{
		$this->validateName('patientname');
		$this->validateName('referringphysician');
		$this->validateName('presentationcreator');
		$this->validateStringSize('presentationdescription', 64);
		$this->validateStringSize('contentlabel', 16);
		$this->validateStringSize('contentlabelAsDescription', 64);
		$this->validateStringSize('patientid');
		$this->validateStringSize('studyid', 16);
		$this->validateStringSize('accessionnum', 16);


		$this->data['sex'] = $this->validateSex('sex');
		
		if (!isset($this->data['seriesnumber']))
			$this->data['seriesnumber'] = 1;

		if ($this->data['birthdate'] != '')
		{
			$date = $this->validateDate($this->data['birthdate']);
			$this->data['birthdate'] = $date;
		}
		if ($this->data['studydate'] != '')
		{
			$date = $this->validateDate($this->data['studydate']);
			$this->data['studydate'] = $date;
		}
		if ($this->data['studytime'] != '')
		{
			$time = $this->validateTime($this->data['studytime']);
			$this->data['studytime'] = $time;
		}
		if ($this->data['presentationcreationdate'] != '')
		{
			$date = $this->validateDate($this->data['presentationcreationdate']);
			$this->data['presentationcreationdate'] = $date;
		}
		if ($this->data['presentationcreationtime'] != '')
		{
			$time = $this->validateTime($this->data['presentationcreationtime']);
			$this->data['presentationcreationtime'] = $time;
		}

		if (isset($this->data['layers']))
			for ($i = 0; $i < count($this->data['layers']); $i++)
			{
				if (isset($this->data['layers'][$i]['name']))
				{
					$this->data['layers'][$i]['name'] = $this->data['layers'][$i]['name'];
					$this->data['layers'][$i]['name'] = $this->fixString($this->data['layers'][$i]['name'], 16);
				}
				if (isset($this->data['layers'][$i]['text']))
					for ($j = 0; $j < count($this->data['layers'][$i]['text']); $j++)
						if (isset($this->data['layers'][$i]['text'][$j]['text']))
						{
							$this->data['layers'][$i]['text'][$j]['text'] = $this->data['layers'][$i]['text'][$j]['text'];
							$this->data['layers'][$i]['text'][$j]['text'] = $this->fixString($this->data['layers'][$i]['text'][$j]['text'], 1024);
						}
			}
	}
	/**
     * convert to dicom
	 *
     */
	function convertDicom()
	{
		$data = '';
		if (count($this->cfgdata) > 0)
		{
			ksort($this->cfgdata);
			$data = '<?xml version="1.0" encoding="UTF-8"?>';
			$data .= '<dicom>';
			$data .= $this->formXML($this->cfgdata);
			$data .= '</dicom>';
		}

		@file_put_contents($this->cfgfilename, $data);
		if (file_exists($this->cfgfilename))
		{
			$comand = '"' . $this->xml2dcm . '" -x "' . $this->cfgfilename . '" -o "' . $this->dcmfilename . '" 2>&1';

			$this->error = self::tryExec($comand, $out);
			if (!file_exists($this->dcmfilename))
				$this->setError('failed to create dicom');

			if ($this->error != '')
				$this->setError(implode("\n", $out));
			else
			{
				@unlink($this->cfgfilename);
			}
		}
		else
			$this->setError('Failed to create configuration file');
	}
	/**
     * form xml from tags
	 *
	 *$param array $taglist - tag list
	 *
	 *return string - xml
     */
	function formXML($taglist)
	{
		$str = '';
		if (count($taglist) > 0)
		{
			foreach ($taglist as $key => $value)
			{
				$prefix = 'attr';
				if (strlen($key) != 8)
				{
					$prefix = 'item';
					$str .= '<' . $prefix;
				}
				else
				{
					$str .= '<' . $prefix . ' tag="' . $key . '"';
					if ($key == '00282000')
						$str .= ' vr="OB" len="' . $this->icclen . '"';
				}

				if (is_array($value))
					$data = $this->formXML($value);
				else
				{

					$data = $value;
				}
				if ($data == '')
					$str .= '/>';
				else
					$str .= '>' . $data . '</' . $prefix . '>';
			}
		}
		return $str;
	}
	/**
     * form xml from tags
	 *
	 *$param string $tag - tag name
	 *$param string $value - tag list
     */
	function addTag($tag, $value)
	{
		//$this->cfgdata .= $tag.':'.$value."\r\n";
		//handle multiple tags
		$tags = explode('/', $tag);
		$this->cfgdata = $this->addTags($this->cfgdata, $tags, (string)$value);
	}
	/**
     * recursion to form multiple tags into multi-mention array
	 *
	 *$param string $data - tag array
	 *$param string $tag - tag name
	 *$param string $value - tag list
     */
	function addTags($data, $tags, $value)
	{
		$count = count($tags);
		$tag = array_shift($tags);
		if ( $count > 1)
		{
			if (!isset($data[$tag]))
				$data[$tag] = array();

			$data[$tag] = $this->addTags($data[$tag], $tags, $value);
		}
		else
			$data[$tag] = $value;

		return $data;
	}
	/**
     * set module tags
	 *
     */
	function setPatientModule()
	{
		//Patient's Name 64 chars maximum per component group - 2
		//need to limit len and from wl
		$this->addTag('00100010', $this->data['patientname']);
		//Patient ID 64 chars - 2 and from wl
		$this->addTag('00100020', $this->data['patientid']);
		//Patient's Birth Date - 2 and from wl
		$this->addTag('00100030', $this->data['birthdate']);
		//Patient's Sex Enumerated Values: M = male F =female O = other - 2
		$this->addTag('00100040', $this->data['sex']);
	}
	/**
     * set module tags
	 *
     */
	function setGeneralStudyModule()
	{
		//Study Instance UID -1 and from wl
		$this->addTag('0020000D', $this->data['studyuuid']);
		//Study Date - 2 and from wl
		$this->addTag('00080020', $this->data['studydate']);
		//Study Time - 2 and from wl
		$this->addTag('00080030', $this->data['studytime']);
		//Referring Physician's Name - can be empty - 2 from wl or empty
		$this->addTag('00080090', $this->data['referringphysician']);
		//Study ID - 2
		$this->addTag('00200010', $this->data['studyid']);
		//Accession Number - 2
		$this->addTag('00080050', $this->data['accessionnum']);
	}
	/**
     * set module tags
	 *
     */
	function setGeneralSeriesModule()
	{
		//Modality -1
		$this->addTag('00080060', 'PR');
		//Series Instance UID -1
		$this->addTag('0020000E', $this->data['seriesuuid']);
		//Transfer Syntax UID -1 and from wl
		$this->addTag('00020010', $this->transfersyntax);
		//Series Number -2
		$this->addTag('00200011', $this->data['seriesnumber']);
		//Laterality -2c
		$this->addTag('00200060', 'L');
	}
	/**
     * set module tags
	 *
     */
	function setGeneralEquipmentModule()
	{
		//Manufacturer -2
		$this->addTag('00080070', $this->manufacturer);
		//Software Versions -1
		if ($this->swversion != '')
			$this->addTag('00181020', $this->swversion);
	}
	/**
     * set module tags
	 *
     */
	function setPresentationStateIdentificationModule()
	{
		//Presentation Creation Date -1
		$this->addTag('00700082', $this->data['presentationcreationdate']);
		//Presentation Creation time -1
		$this->addTag('00700083', $this->data['presentationcreationtime']);
		//Instance Number - 2
		$this->addTag('00200013', $this->data['instancenumber']);
		//Content Label - 1
		$this->addTag('00700080', strtoupper($this->data['contentlabel']));
		//Content Description - 2 len=64
		$this->addTag('00700081', $this->data['presentationdescription']);
		//Alternate Content DescriptionSequence - 1
		//0070,0087
		//Content Description - 1
		//0070,0081
		$this->addTag('00700087/0/00700081', $this->data['contentlabelAsDescription']);
		//Language Code Sequence - 1
		//0008,0006
		//Defined CID 5000 “Languages”.
		//Code Value 0008,0100 - 1
		$this->addTag('00700087/0/00080006/0/00080100', 'en');
		//Coding Scheme Designator 0008,0102 - 1
		$this->addTag('00700087/0/00080006/0/00080102', 'RFC3066');
		//Code Meaning 0008,0104 - 1
		$this->addTag('00700087/0/00080006/0/00080104', 'English');
		//Content Creator’s Name - 2 len 64
		$this->addTag('00700084', $this->data['presentationcreator']);
	}
	/**
     * set module tags
	 *
     */
	function setPresentationStateRelationshipModule()
	{
		if (isset($this->data['referencedseriessequence']))
			for ($i = 0; $i < count($this->data['referencedseriessequence']); $i++)
			{
				//Referenced Series Sequence - 1
				//0008,1115
				//Series Instance UID -1
				$this->addTag('00081115/' . $i . '/0020000E', $this->data['referencedseriessequence'][$i]['seriesuid']);
				//Referenced Image Sequence -1
				//0008,1140
				if (isset($this->data['referencedseriessequence'][$i]['imagesequence']))
					for ($j = 0; $j < count($this->data['referencedseriessequence'][$i]['imagesequence']); $j++)
					{
						//Referenced SOP Class UID -1
						$this->addTag('00081115/' . $i . '/00081140/' . $j . '/00081150',
							$this->data['referencedseriessequence'][$i]['imagesequence'][$j]['sopud']);
						//Referenced SOP Instance UID - 1
						$this->addTag('00081115/' . $i . '/00081140/' . $j . '/00081155',
							$this->data['referencedseriessequence'][$i]['imagesequence'][$j]['instanceuid']);
					}
			}
	}
	/**
     * set module tags
	 *
     */
	function setDisplayedAreaModule()
	{
		//Displayed Area Selection Sequence 1
		//0070,005A
		//Displayed Area Top Left Hand Corner 1
		$this->addTag('0070005A/0/00700052', '1\1');
		//Displayed Area Bottom Right Hand Corner 1
		$this->addTag('0070005A/0/00700053', $this->data['numcolumns'] . "\\" . $this->data['numrows']);
		//Presentation Size Mode 1
		$this->addTag('0070005A/0/00700100', 'MAGNIFY');
		//Presentation Pixel Aspect Ratio 1c from image of 1\1
		$this->addTag('0070005A/0/00700102', $this->data['aspectratio']);
		//Presentation Pixel Magnification Ratio 1c for MAGNIFY
		$this->addTag('0070005A/0/00700103', '1');
	}
	/**
     * set module tags
	 *
     */
	function setGraphicAnnotationModule()
	{
		//Graphic Annotation Sequence 1
		//0070,0001
		if (isset($this->data['layers']))
		for ($i = 0; $i<count($this->data['layers']); $i++)
		{
			$layer = $this->data['layers'][$i];
			//Graphic Layer 1 16
			$this->addTag('00700001/' . $i . '/00700002', $layer['name']);

			//Text Object Sequence - 1c
			//0070,0008
			if (isset($layer['text']))
				for ($j = 0; $j<count($layer['text']); $j++)
				{
					$text = $layer['text'][$j];
					
					if (isset($text['box']))
					{
						//Bounding Box Annotation Units 1c
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700003', 'PIXEL');
					}
					if (isset($text['x']))
					{
						//Anchor Point Annotation Units 1c
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700004', 'PIXEL');
					}
					//Unformatted Text Value 1 len=1024

					$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700006', $text['text']);
					//Text Style Sequence 3
					//0070,0231 - neapsimoka
					if (isset($text['style']))
					{
						//CSS Font Name - 1
						$this->addTag('00700001/' . $i .'/00700008/' . $j . '/00700231/0/00700229', 'fontname');
						//Text Color CIELab Value - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700241', "67\\21\\71");
						if (isset($text['style']['horizontalalign']))
							//Horizontal Alignment - 3
							$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700242', $text['style']['horizontalalign']);
						//Shadow Style - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700244', "OFF");
						//Shadow Offset X - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700245', "0.0");
						//Shadow Offset Y - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700246', "0.0");
						//Shadow Color CIELab Value - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700247', "0\\0\\0");
						//Shadow Opacity - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700258', "0.0");
						//Underlined - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700248', "N");
						//Bold - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700249', "N");
						//Italic - 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700231/0/00700250', "N");
					}

					if (isset($text['box']))
					{
						//Bounding Box Top Left Hand Corner 1c
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700010', $text['box'][0]['x'] . "\\" . $text['box'][0]['y']);
						//Bounding Box Bottom Right Hand Corner 1c
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700011', $text['box'][1]['x'] . "\\" . $text['box'][1]['y']);
						//Bounding Box Text Horizontal Justification 1c
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700012', 'LEFT');
					}
					if (isset($text['x']))
					{
						//Anchor Point 1c
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700014', $text['x'] . "\\" . $text['y']);
						//Anchor Point Visibility 1
						$this->addTag('00700001/' . $i . '/00700008/' . $j . '/00700015', 'Y');
					}
					
					
					
				}
			//Graphic Object Sequence 1c
			//0070,0009
			if (isset($layer['graphic']))
				for ($j = 0; $j < count($layer['graphic']); $j++)
				{
					$graphic = $layer['graphic'][$j];
					//Graphic Annotation Units -1
					$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700005', 'PIXEL');
					//Graphic Dimensions -1
					$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700020', '2');
					//Number of Graphic Points -1
					$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700021', (int) (count($graphic['points']) / 2));
					//Graphic Data -1
					$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700022', implode("\\", $graphic['points']));
					//Graphic Type -1 POINT POLYLINE INTERPOLATED CIRCLE ELLIPSE
					$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700023', $graphic['type']);

					if (isset($graphic['style']))
					{
						//Line Style Sequence 3
						//0070,0232
						//Pattern On Color CIELab Value 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700251', $graphic['color']);
						//Pattern On Opacity 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700284', '1.0');
						//Line Thickness 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700253', '1.0');
						//Line Dashing Style 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700254', $graphic['linestyle']);
						if ($graphic['linestyle'] == 'DASHED')
							//Line Dashing Style 1
							$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700255', $graphic['linestyle']);
						//Shadow Style 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700244', 'OFF');
						//Shadow Offset X 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700245', '0.0');
						//Shadow Offset y 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700246', '0.0');
						//Shadow Color CIELab Value 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700247', '0.0');
						//Shadow Opacity 1
						$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700232/0/00700258', '0.0');
					}
					//Graphic Filled -1
					$this->addTag('00700001/' . $i . '/00700009/' . $j . '/00700024', 'N');
				}
		}
	}
	/**
     * set module tags
	 *
     */
	function setSpatialTransformationModule()
	{
		//1c
		if (isset($this->data['transformation']))
		{
			//Image Rotation - 1 0, 90,180,270
			$this->addTag('00700042', $this->data['transformation']['rotation']);
			//Image Rotation - 1 Y/N
			$this->addTag('00700041', $this->data['transformation']['flip']);
		}
	}
	/**
     * set module tags
	 *
     */
	function setGraphicLayerModule()
	{
		//Graphic Layer Sequence 1c
		//0070,0060
		if (isset($this->data['layers']))
		for ($i = 0; $i<count($this->data['layers']); $i++)
		{
			$layer = $this->data['layers'][$i];
			//Graphic Layer 1
			$this->addTag('00700060/' . $i . '/00700002', $layer['name']);
			//Graphic Layer Order 1
			$this->addTag('00700060/' . $i . '/00700062', $layer['order']);
		}
	}
	/**
     * set module tags
	 *
     */
	function setICCProfileModule()
	{
		//ICC Profile 1 - no idea
		$iccfiledata = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'sRGB_IEC61966-2-1_black_scaled.icc');
		$this->icclen = strlen($iccfiledata);
		$data = strtoupper(chunk_split(bin2hex($iccfiledata), 2, "\\"));
		if ($iccfiledata != '')
			$this->addTag('00282000', substr($data, 0, (strlen($data) - 1)));
		else
			$this->addTag('00282000', '');
	}
	/**
     * set module tags
	 *
     */
	function setSOPCommonModule()
	{
		//SOP Class UID - 1
		$this->addTag('00080016', $this->sopuid);
		//SOP Instance UID 1
		$this->addTag('00080018', $this->data['sopinstance']);
		//Specific Character Set 1c
		$this->addTag('00080005', $this->encoding);
		//Instance Creation Date -3 dicom was created
		$this->addTag('00080012', $this->data['instancedate']);
		//Instance Creation Time -3
		$this->addTag('00080013', $this->data['instancetime']);
	}
	/**
     * set module tags
	 *
     */
	function setOtherMetadata()
	{
		//Table 7.1-1  PS 3.10-2011 Page 20
		//File Meta Information Group Length - 1
		//0002,0000 - will be added by program
		//File Meta Information Version -1
		//0002,0001 - will be added by program
		$this->addTag('00020001', '00\01');
		//SOP Class UID - 1
		$this->addTag('00020002', $this->sopuid);
		//SOP Instance UID -1
		$this->addTag('00020003', $this->data['sopinstance']);
		//0002,0010 - see setGeneralSeriesModule()
		//Implementation Class UID -1 1.2.40.0.13.1.1+prefix
		$this->addTag('00020012', $this->rootuid);
		//Implementation Version Name -3
		//0002,0013 - will be added by program or not
		$this->addTag('00020013', $this->implementatinversion);
		//Source Application Entity Title -3
		//0002,0016 - will be added by program or not
	}
	/**
     * restore default data
	 *
     */
	function clear()
	{
		$this->cfgdata = array();
		$this->cfgfilename = '';
		$this->dcmfilename = '';
		$this->error = '';
		$this->data = array();
		$this->transfersyntax = '1.2.840.10008.1.2.1';
		$this->sopuid = '1.2.840.10008.5.1.4.1.1.11.2';
		$this->swversion = '';
		$this->encoding = 'ISO_IR 6';
	}
}
?>
