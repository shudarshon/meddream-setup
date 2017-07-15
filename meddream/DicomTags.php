<?php
/*
	Original name: DicomTags.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Get a listing of DICOM header tags similar to Dcm2Txt from DCM4CHEE2 Toolkit
 */

namespace Softneta\MedDream\Core;

if (!strlen(session_id()))
	@session_start();

require_once(__DIR__ . '/autoload.php');
use Softneta\MedDream\Core\SOP\DicomCommon;


/** @brief Formatter for the "DICOM Tags" function. Primitives for "Info Labels". */
class DicomTags
{
	protected $log;
	protected $backend = null;


	public function __construct(Backend $backend = null, Logging $log = null)
	{
		if (is_null($log))
			$this->log = new Logging();
		else
			$this->log = $log;

		$this->backend = $backend;
	}


	/** @brief Return a new or existing instance of Backend.

		@param array   $parts           Names of PACS parts that will be initialized
		@param boolean $withConnection  Is a DB connection required?
		@return Backend

		If the underlying AuthDB must be connected to the DB, then will request the
		connection once more.
	 */
	private function getBackend($parts = array(), $withConnection = true)
	{
		if (is_null($this->backend))
			$this->backend = new Backend($parts, $withConnection, $this->log);
		else
			$this->backend->loadParts($parts);

		if (!$this->backend->authDB->isConnected() && $withConnection)
			$this->backend->authDB->reconnect();

		return $this->backend;
	}


	/** @brief Get tags of a DICOM file given its UID.

		@param string $uid    Value of primary key in the images table (__not necessarily a SOP Instance UID__)
		@param int    $level  Maximum depth of container hierarchy, inclusive. Zero is the top level.

		@return array  See getTagsListByPath() for the format
	 */
	public function getTagsList($uid, $level = 6)
	{
		$er = error_reporting(0);
		$this->log->asDump('begin ' . __METHOD__ . '(', $uid, ', ', $level, ')');

		$backend = $this->getBackend(array('Structure', 'Preload'));
		if (!$backend->authDB->isAuthenticated())
		{
			$this->log->asErr('not authenticated');
			return array('error' => 'reconnect', 'tags' => '');
		}

		$return = $backend->pacsStructure->instanceGetMetadata($uid);
		if (strlen($return['error']))
			return $return;
		$path = $return['path'];
		unset($return);

		$return = $this->getTagsListByPath($path, $level);

		/* also remove the source file that might be created by instanceGetMetadata() */
		$backend->pacsPreload->removeFetchedFile($path);

		error_reporting($er);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Get tags of a DICOM file given its path.

		@param string $path   Path to the file
		@param int    $level  Maximum depth of container hierarchy, inclusive. Zero is the top level.

		@return array

		Elements of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message, empty if success
			<li><tt>'tags'</tt> - <tt>SINGLE_TAG</tt> subarray

			Elements of the <tt>SINGLE_TAG</tt> subarray:

			<ul>
				<li><tt>'group'</tt> - (int) Group Number
				<li><tt>'element'</tt> - (int) Element Number
				<li><tt>'name'</tt> - (string) official name of the tag. <b><tt>'?'</tt> for tags
				    not in the dictionary</b> (for example, private groups).
				<li><tt>'vl'</tt> - (int) Value Length
				<li><tt>'vr'</tt> - (string) Value Representation
				<li><tt>'offset'</tt> - (int) offset into the file where this tag begins
				<li><tt>'level'</tt> - (int) actual depth of the tag, zero-based
				<li><tt>'data'</tt> - (mixed) value. __Absent for containers.__

				Type of <tt>'data'</tt> depends on VR:

				<ul>
					<li>SS, US, FL -- a single integer/double or, in case of multiple elements, an
					    array of them;
					<li>OB, OW, OF, UN -- a binary string;
					<li>otherwise an ordinary string with trailing 0x20/0x00 removed. __Value
					    Multiplicity as per DICOM Data Dictionary is not taken into account and
					    will not yield an array of strings.__
				</ul>

				The value will be @c null if:

				<ul>
					<li>tag is empty (zero <tt>'vl'</tt>);
					<li>data was not loaded due to large amount of it (this would unnecessarily waste
					    resources). In this case you can use <tt>'offset'</tt> and <tt>'vl'</tt> to
					    read important values from the file later.
				</ul>
			</ul>
		</ul>
	 */
	public function getTagsListByPath($path, $level = 6)
	{
		$er = error_reporting(0);
		$this->log->asDump('begin ' . __METHOD__ . '(', $path, ', ', $level, ')');

		clearstatcache(false, $path);
		if (!file_exists($path))
			return array('error' => 'File does not exist', 'tags' => '');

		$return = meddream_get_tags(__DIR__, $path, $level);
		if ($return['error'] != 0)
		{
			$this->log->asErr('meddream_get_tags error ' . $return['error'] . ' on ' . $path);
			$return['error'] = "Failed to read/parse file(s)\n(error code " . $return['error'] . ', see logs for more details)';
			return $return;
		}
		else
			$return['error'] = '';

		error_reporting($er);
		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Return a single matching tag from the list.

		@param array $tags     List of tags from getTagsList() or getTagsListByPath()
		@param int   $group    Group Number of the tag being searched for
		@param int   $element  Element Number of the tag being searched for

		@return array Copy of a matching element from @p $tags. __Empty array if not found.__

		@note @p Returns the first subarray with matching $group and @p $element. Not
		      possible to search for identical elements at deeper levels.
	 */
	public function getTag($tags, $group, $element)
	{
		if (count($tags) == 0)
			return array();
		foreach ($tags as $item)
		{
			if ((((int) $item['group']) === (int) $group) &&
					(((int) $item['element']) === (int) $element))
				return $item;
		}
		return array();
	}


	/** @brief Return multiple matching tags at the same depth from the list.

		@param array $tags     List of tags from getTagsList() or getTagsListByPath().
		                       <tt>'level'</tt> of the first element defines the depth
		                       at which the tags are considered.
		@param int   $group    Group Number of the tag being searched for
		@param int   $element  Element Number of the tag being searched for

		@return array Copies of matching elements from @p $tags. __Empty array if nothing
		              was found.__

		@note Will not stop searching if some element is above the first element of
		      @p $tags.
	 */
	public function getTagItems($tags, $group, $element)
	{
		if (count($tags) == 0)
			return array();

		$items = array();
		$level = (int) $tags[0]['level'] + 1;
		foreach ($tags as $item)
		{
			if (((int) $item['level'] == $level) &&
				((int) $item['group'] === (int) $group) &&
				((int) $item['element'] === (int) $element))
				$items[] = $item;
		}
		return $items;
	}


	/** @brief Return contents of a matching container.

		@param array $tags     List of tags from getTagsList() or getTagsListByPath()
		@param int   $group    Group Number of the tag being searched for
		@param int   $element  Element Number of the tag being searched for

		@return array Copies of contained elements from @p $tags. __Empty array if nothing
		              was found or the sequence itself is empty.__

		The first matching tag defines the depth below which elements are extracted.
		Extraction stops after encountering a tag of the same depth as the container's.

		@note Does not verify whether the matching tag is a container.
	 */
	public function getSequence($tags, $group, $element)
	{
		$sequence = array();
		$level = -1;
		foreach ($tags as $item)
		{
			if ($level == -1)
			{
				if (((int) $item['group'] === (int) $group) &&
						((int) $item['element'] === (int) $element))
					$level = (int) $item['level'] + 1;
			}
			else
			{
				if ((int) $item['level'] >= $level)
					$sequence[] = $item;
				else
					break; // end of sq
			}
		}
		return $sequence;
	}


	/** @brief Format a textual dump of the tags.

		@param string $uid    Value of primary key in the images table (__not necessarily a SOP Instance UID__)

		@return array

		Format of the returned array:

		<ul>
			<li><tt>'error'</tt> - error message (empty if success)
			<li><tt>'tags'</tt> - contents of the dump
		</ul>
	 */
	public function getTags($uid)
	{
		$return = array('error' => '', 'tags' => '');

		$this->log->asDump('begin ' . __METHOD__);

		$backend = $this->getBackend(array('Structure', 'Preload'));	/* both parts needed in getTagsList() */
		if (!$backend->authDB->isAuthenticated())
		{
			$return['error'] = 'reconnect';
			$this->log->asErr('not authenticated');
			return $return;
		}

		$data = $this->getTagsList($uid);
		if ($data['error'] != '')
		{
			$return = $data;
			return $return;
		}
		if (!empty($data['tags']))
		{
			$return['uid'] = $uid;
			$tag = $this->getTag($data['tags'], 8, 5);
			$charSet = '';
			if (isset($tag['data']) && !is_null($tag['data']))
				$charSet = $tag['data'];
			unset($tag);

			foreach ($data['tags'] as $tag)
			{
				$return['tags'] .= $tag['offset'] . ': ' .
					str_repeat('>', $tag['level']) . '' .
					'(' . sprintf('%04x', $tag['group']) . ',' . sprintf('%04x', $tag['element']) . ') ' .
					$tag['vr'] . ' ' .
					'#' . $tag['vl'];

				$noContent = ($tag['vr'] == 'SQ') ||
					(($tag['group'] == 0xFFFE) && ($tag['element'] == 0xE000)) ||
					(($tag['group'] == 0xFFFE) && ($tag['element'] == 0xE00D)) ||
					(($tag['group'] == 0xFFFE) && ($tag['element'] == 0xE0DD));

				if (!$noContent)
				{
					$return['tags'] .=  ' [';
					if (isset($tag['data']))
					{
						if (is_array($tag['data']))
							$value = implode("\\",$tag['data']);
						else
							$value = $tag['data'];

						$value = addcslashes($value, "\0..\37");
						$value = str_replace('\\\\', "\\", $value);
						$return['tags'] .= $backend->cs->encodeWithCharset($charSet, $value);
						unset($value);
					}
					$return['tags'] .= ']';
				}

				if (isset($tag['name']))
					$return['tags'] .= ' ' . $tag['name'];

				$return['tags'] .= "\n";

				unset($tag);
			}
		}
		unset($data);

		$this->log->asDump('$return = ', $return);
		$this->log->asDump('end ' . __METHOD__);
		return $return;
	}


	/** @brief Basic validation of tag values with some VRs.

		@param string $vr     Value Representation. Only DA and TM are currently validated.
		@param string $value  Raw value

		@return string Empty if the format was not correct (wrong length, invalid characters, etc)
	 */
	public function formatTagValue($vr, $value)
	{
		switch ($vr)
		{
			case 'DA':
					$dc = new DicomCommon();
					$date = $dc->parseDate($value);
					$value = implode('-', $date);
					unset($dc);
				break;

			case 'TM':
					$dc = new DicomCommon();
					$time = $dc->parseTime($value);
					$value = implode(':', $time);
					unset($dc);
				break;

			default:
				break;
		}
		return $value;
	}
}
