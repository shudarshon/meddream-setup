<?php
/*
	Original name: RetrieveStudy.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		Check if cached study (structure, file count) is the same.

		Provides functions:
			studyListIsTheSame($studyUid) - test study list
			downloadStudy($uid) - download study - a lengthy process
			verifyAndFetch($uid) - combines the two above
 */
namespace Softneta\MedDream\Core;

use Softneta\MedDream\Core\QueryRetrieve\QR;


/** @brief A helper that decides whether the study needs to be refreshed from %PACS.

	@todo Use Backend instead of Study.
 */
class RetrieveStudy
{
	private $study = null;
	private $log = null;
	private $qr = null;


	public function __construct(Study $study = null, Logging $log = null, Configuration $cnf = null)
	{
		$this->study = $study;
		$this->log = $log;

		/* 2016.10.28: unit tests fail because Translation requires translation files in
		   locale/ subdirectory, and these become available only after running _build.bat.
		   As a workaround we can provide an object of Translation, this way Translation::configure()
		   isn't called automatically.

		   Does dependence on _build.bat mean it's not a unit test but integration test?
		   Anyway this will be solved if this class uses Backend instead of Study, or
		   RetrieveStudyTest.php correctly initializes an instance of Study (this again
		   requires an instance of Backend initialized as below).
		 */
		$cs = new CharacterSet($log, $cnf);
		$fp = new ForeignPath($log, $cnf);
		$tr = new Translation();
		$backend = new Backend(array(), false, $log, $cnf, $cs, $fp, null, null, $tr);
			/* 2017.03.07: is it normal that we must pass null for instances of PacsGw and QR? */
		$this->qr = QR::getObj($backend);
	}


	/** @brief Download the entire study via DICOM C-MOVE.

		@param string $studyUid
		@return string - error from downloader
		@throws \Exception - error about empty uid
	 */
	public function downloadStudy($studyUid)
	{
		$this->log->asDump('begin ' . __METHOD__);
		$this->log->asDump('$studyUid: ', $studyUid);

		if (trim($studyUid) == '')
		{
			$error = 'Study UID is empty';
			$this->log->asErr('$error: ', $error);
			throw new \Exception($error);
		}

		$error = $this->qr->fetchStudy($studyUid, true);

		if (!empty($error))
			$this->log->asErr('$error: ', $error);
		else
			$this->log->asDump('end ' . __METHOD__);
	}


	/** @brief Check if the study structure and cached list are the same.

		@param string $studyUid
		@return boolean
		@throws \Exception
	 */
	public function studyListIsTheSame($studyUid)
	{
		$this->log->asDump('begin ' . __METHOD__);
		$this->log->asDump('$studyUid: ', $studyUid);

		if (is_null($this->study))
		{
			$error = 'Study must not be null';
			$this->log->asErr('$error: '.$error);
			throw new \Exception($error);
		}
		if (trim($studyUid) == '')
		{
			$error = 'Study Uid is empty';
			$this->log->asErr('$error: '.$error);
			throw new \Exception($error);
		}

		$structure = $this->getStructure($this->study, $studyUid);
		$cashed = $this->getCachedList($this->study, $studyUid);
		
		$diff = $this->getDiff($structure, $cashed);
		$this->log->asDump('$diff: ', $diff);
		$decision = true;
		
		if ($diff['cashedIsMissing'] > 0)
			$decision = false;
		
		if (!empty($diff['notIncludedInStructure']))
			$this->removeDiffFiles($diff['notIncludedInStructure']);

		$this->log->asDump('end ' . __METHOD__);
		
		return $decision;
	}


	/** @brief Perform studyListIsTheSame() and downloadStudy() at once. */
	public function verifyAndFetch($studyUid)
	{
		$error = '';
		$parts = explode('*', $studyUid);
		$studyUid = end($parts);

		if (trim($studyUid) == '')
			$this->log->asWarn('missing Study UID to retrieve study and compare structure');
		else
			try
			{
				if (!$this->studyListIsTheSame($studyUid))
					$error = trim($this->downloadStudy($studyUid));
			}
			catch (Exception $exc)
			{
				$error = $exc->getMessage();
			}

		return $error;
	}


	/** @brief Remove cached images that do not exist in study structure.

		@param array $images
		@return null
	 */
	public function removeDiffFiles($images)
	{
		$this->log->asDump('begin ' . __METHOD__);
		
		if (empty($images))
		{
			$this->log->asWarn('No images to remove');
			return;
		}

		$directories = array();
		
		//remove images, that left
		if (!empty($images)) 
		{
			foreach($images as $id=>$path)
			{
				if (file_exists($path))
				{
					@unlink($path);
					if (!file_exists($path))
						$this->log->asDump('Removed:', $path);
					else
						$this->log->asWarn('Failed to remove:', $path);
					$directories[dirname($path)] = null;
				}
			}
		}
		//try remove emty directories
		if (!empty($directories)) 
		{
			$this->log->asDump('Try remove directories:', $directories);
			foreach($directories as $key=>$value)
			{
				$empty = true;
				$handle = @opendir($key);
				if ($handle !== false) 
				{
					while (false !== ( $file = @readdir( $handle ))) 
					{
						if ( $file != "." && $file != ".." ) 
							$empty = false;
					}
					closedir($handle);
				}
				if ($empty)
				{
					$this->log->asDump('Removed directory:', $key);
					@rmdir($key);
				}
				else
				{
					$this->log->asDump('Directory not empty:', $key);
				}
			}
		}
			
		unset($directories);
		unset($images);
		
		$this->log->asDump('end ' . __METHOD__);
	}


	public function collectImages($list)
	{
		$images = array();
		
		if ($this->emptyList($list))
			return $images;
		
		$countSt = $list['count'];
		for($i = 0; $i< $countSt; $i++)
		{
			if (empty($list[$i]['count']))
				continue;
			
			$countSs = $list[$i]['count'];
			for($j = 0; $j< $countSs; $j++)
			{
				if (!isset($list[$i][$j]))
					continue;
				
				$image = $list[$i][$j];
				if (!empty($image['id']) && 
					!empty($image['path']))
				{
					$id = $this->getImageUid($image['id']);
					$images[$id] = $image['path'];
				}
			}
		}
		return $images;
	}


	/** @brief Check if structure is empty (contains no series)

		@param array $list - structure from sever or cache
		@return boolean
	 */
	public function emptyList($list)
	{
		if (empty($list['count']))
			return true;
		if ($list['count'] > 0)
			if (empty($list[0]))
				return true;
		return false;
	}


	/** @brief Get the first component from our combined UID.

		@param string $fullUid
		@return string
	 */
	public function getImageUid($fullUid)
	{
		$parts = explode('*', $fullUid);
		return $parts[0];
	}


	/** @brief Get study structure from server.

		@param Study $study
		@param string $studyUid
		@return array
	 */
	public function getStructure($study, $studyUid)
	{
		return $this->getStudyList($study, $studyUid, false);
	}


	/** @brief Get study structure from cache.

		@param Study $study
		@param string $studyUid
		@return array
	 */
	public function getCachedList($study, $studyUid)
	{
		return $this->getStudyList($study, $studyUid, true);
	}


	/** @brief Basic method for getting the study structure.

		@param Study $study
		@param string $studyUid
		@param boolean $cached
		@return array
	 */
	public function getStudyList($study, $studyUid, $cached)
	{
		if (($studyUid == '') && is_null($study))
			return array();
		
		return $study->getStudyList($studyUid, false, $cached);
	}


	/** @brief Recursively count the number of specified array elements.

		@param array $list
		@param string $countKey - key to search for. Not supported so far, trivial count() is used.
		@return int
	 */
	public function getItemsCount($list, $countKey = '')
	{
		$count = 0;
		if (empty($list))
			return $count;

		foreach ($list as $value)
			$count += $this->getCount($value, $countKey);

		return $count;
	}


	/**
	 * 
	 * @param array $item
	 * @param string $key - assoc key to look
	 * @return type
	 */
	public function getCount($item, $key = '')
	{
		if (trim($key) != '')
			if (isset($item[$key]))
				return (int)$item[$key];

		return 0;
	}


	/** @brief Difference between study structures from server and cache.

		@param array $structure
		@param array $cashed
		@return array

		Format of the returned array:

			array(
				'notIncludedInStructure' => array(), //need to delete from cache
				'cashedIsMissing' => 0, //files missing in cache
				'cashed' => 0, //number of cached items
				'structure' => 0 //number of server items
			);
	 */
	public function getDiff($structure, $cashed)
	{
		$return = array(
			'notIncludedInStructure' => array(),
			'cashedIsMissing' => 0,
			'cashed' => 0,
			'structure' => 0
		);
		
		if ($this->emptyList($structure))
		{
			$return['cashed'] = $this->getItemsCount($cashed, 'count');
			return $return;
		}
		else
			if ($this->emptyList($cashed))
			{
				$return['structure'] = $this->getItemsCount($structure, 'count');
				$return['cashedIsMissing'] = $return['structure'];
				return $return;
			}

		$images = $this->collectImages($cashed);
		$return['cashed'] = count($images);
		
		$countSt = $structure['count'];
		for($i = 0; $i<$countSt; $i++)
		{
			if (empty($structure[$i]['count']))
				continue;
			
			$countSs = $structure[$i]['count'];
			for($j = 0; $j< $countSs; $j++)
			{
				if (!isset($structure[$i][$j]))
					continue;
				
				$return['structure']++;
				
				$image = $structure[$i][$j];
				if (!empty($image['id']))
				{
					$id = $this->getImageUid($image['id']);
					if (isset($images[$id]))
						unset($images[$id]);
					else
						$return['cashedIsMissing']++;
				}
			}
		}
		
		$return['notIncludedInStructure'] = $images;
			
		return $return;
	}
}
