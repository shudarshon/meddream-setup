<?php

namespace Softneta\MedDream\Core\QueryRetrieve;

use Softneta\MedDream\Core\Logging;


/** @brief Access to directory structure created by DcmRcv. */
class QrCache
{
	protected $rootDir;         /**< @brief Root directory of the cache */
	protected $log;             /**< @brief An instance of Logging */


	/** @brief Collect files from cache directory tree, delete outdated ones.

		@param string $baseDir  Base directory to start from. __Must not contain a trailing
		                        directory separator.__
		@param string $ext      Extension of file names that will be included in the scan.
		                        __Must not contain a leading <tt>'.'</tt> character.__ If empty,
		                        all files are included.
		@param string $subdir   Subdirectory to be applied to $baseDir
		@param int    $depth    Collect files at this number of subdirectories below $subdir
		@param int    $minTime  Minimum modification timestamp of files to include
		@param array  $list     Storage for returned filenames (see format below). If not
		                        specified, filenames are not collected and $minTime is ignored
		                        so that all files become outdated.

		Recursively scans subdirectories of "$baseDir/$subdir",.at bottom
		of @p $depth collects files with extensions @p $ext, then adds relative
		paths (not including @p $baseDir) to @p $list.

		If the timestamp of those matching files is smaller than @p $minTime,
		they are deleted and not added to @p $list; if all found files were deleted,
		then the directory is deleted as well. Use zero @p $minTime to disable this.
	 */
	protected function scanWithSubdirs($baseDir, $ext, $subdir, $depth, $minTime, &$list = null)
	{
		$success = true;

		/* build a full path for reliable access. The current directory
		   is hardly predictable in some cases.
		 */
		$dir_path = $baseDir;
		if (strlen($subdir))
			$dir_path .= '/' . $subdir;

		/* scan it */
		$dh = @opendir($dir_path);
		if (!$dh)
			return false;
		$num_purged = 0;
		$num_found = 0;
		while (($file = readdir($dh)) !== false)
		{
			/* special directory entries are not needed */
			if (($file == '.') || ($file == '..'))
				continue;

			$num_found++;

			/* build a full path again */
			$path = $dir_path . '/' . $file;
			if (is_dir($path) && ($depth > 0))
			{
				/* descend deeper */
				if (strlen($subdir))
					$rel_path = $subdir . '/' . $file;
				else
					$rel_path = $file;

				$success = $this->scanWithSubdirs($baseDir, $ext, $rel_path, $depth - 1,
					$minTime, $list);
				if (!$success)
					break;
			}
			else
			{
				/* ignore/remove old enough *.part regardless of depth and $ext.

					An "old enough" file with this extension should be safe to remove as DcmRcv
					unlikely still holds it open. This, of course,  requires adequate $minTime.
				 */
				if (strlen($file) > 5)
					if (strripos($file, '.part', -5))
					{
						if ($minTime)
						{
							$mt = @filemtime($path);
							if ($mt !== false)
								if ($mt < $minTime)
								{
									if (@unlink($path))
										$num_purged++;
								}
						}

						continue;
					}

				/* we'll collect files only at the bottom */
				if (!$depth)
				{
					/* filter out wrong extensions

						In case of a flat cache, this function is called with empty $ext,
						as a UID for a file name effectively means "unpredictable extension
						that consists of numbers".
					 */
					$ext_good = false;
					$ext_len = strlen($ext) + 1;
					if ($ext_len == 1)
						$ext_good = true;		/* empty $ext disables the filter */
					else
						if (strlen($file) > $ext_len)		/* otherwise comparing has no sense */
							if (strripos($file, ".$ext", -$ext_len))
									/* int(0) means a file named merely ".dcm" etc, however such a name
									   does not contain the Image UID; so we can handle this identically
									   to bool(false).
									 */
								$ext_good = true;
					if (!$ext_good)
						continue;

					/* do we need to remove all matching files regardless of timestamp? */
					if (is_null($list))
					{
						if (@unlink($path))
							$num_purged++;
						continue;
					}

					/* the file might also be too old; let's remove it, then */
					if ($minTime)
					{
						$mt = @filemtime($path);
						if ($mt !== false)
							if ($mt < $minTime)
							{
								if (@unlink($path))
									$num_purged++;
								continue;
							}
					}

					/* finally, just add to the list */
					if (strlen($subdir))
						$rel_path = $subdir . '/' . $file;
					else
						$rel_path = $file;
						$list[] = $rel_path;
				}
			}
		}

		closedir($dh);

		/* remove an empty directory

			Possible situations:

			1) the directory is merely empty ($num_found=0, however then $num_purged
			   remains zero as well). We don't keep these.

			2) we successfully deleted ($num_purged) some files, and these were
			   the only files that we found.

			As a result, subdirectories below $depth are left intact. This might be
			beneficial -- even a modified DcmRcv doesn't create them.

			NOTE: this function supports the "flat" cache from original DcmRcv, too,
			however will remove its directory as well. On the other hand, the original
			DcmRcv is too inefficient and won't be used, hence no additional logic
			here.
		 */
		if ($num_purged == $num_found)
			@rmdir($dir_path);

		return $success;
	}


	/** @brief A minimal constructor.

		@param Logging      $log               An instance of Logging

		Also initializes @link $rootDir @endlink.
	 */
	public function __construct(Logging $log)
	{
		$this->log = $log;
		$this->rootDir = str_replace('\\', '/', dirname(__DIR__)) . '/temp/cached';
	}


	/** @brief Return a timestamp meddream.cache_age_max (php.ini) seconds in the past.

		Files with modification timestamps below this one are not eligible to caching.
	 */
	public function calcOutdatedTime($maxAge = '0', $currentTime = null)
	{
		/* The parameter are there for unit tests. However for typical use they default
		   to "automatic" values which must be recalculated.
		 */
		if (is_null($currentTime))
			$currentTime = time();
		if ($maxAge == '0')
			$maxAge = ini_get('meddream.cache_age_max');

		if ($maxAge != '0')
		{
			if (!strlen($maxAge))
				$max_age = '86400';		/* 1 day */
			$maxAge = (int) $maxAge;
			$minTime = $currentTime - $maxAge;
		}
		else
			$minTime = 0;

		return $minTime;
	}


	/** @brief Return UIDs of specified study in the cache.

		@param string $studyUid   %Study Instance UID

		The return value also includes some other attributes, however they will be empty. Format:

		<ul>
			<li><tt>'count'</tt> - number of series subarrays
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'lastname'</tt> - always empty string
			<li><tt>'firstname'</tt> - always empty string
			<li><tt>'uid'</tt> - %Study Instance UID (duplicates @p $studyUid)
			<li><tt>'patientid'</tt> - always empty string
			<li><tt>'sourceae'</tt> - always empty string
			<li><tt>'studydate'</tt> - always empty string
			<li><tt>'studytime'</tt> - always empty string
			<li><tt>'notes'</tt> - always @c 2
			<li>@c 0 ... @c N (numeric keys) - a series subarray

			Format of a series subarray:

			<ul>
				<li><tt>'count'</tt> - number of image subarrays
				<li>@c 0 ... @c M (numeric keys) - an image subarray
				<li><tt>'id'</tt> - Series Instance UID + @c '*' + %Study Instance UID
				<li><tt>'description'</tt> - always empty string
				<li><tt>'modality'</tt> - always empty string
				<li><tt>'seriesno'</tt> - always @c 0

				Format of an image subarray:

				<ul>
					<li><tt>'id'</tt> - SOP Instance UID + @c '*' + Series Instance UID + @c '*' + %Study Instance UID
					<li><tt>'numframes'</tt> - always @c 0
					<li><tt>'path'</tt> - full path to a cached file
					<li><tt>'xfersyntax'</tt> - always empty string
					<li><tt>'bitsstored'</tt> - always @c 0
					<li><tt>'sopclass'</tt> - always empty string
					<li><tt>'instanceno'</tt> - always @c 0
					<li><tt>'acqno'</tt> - always @c 0
				</ul>
			</ul>
		</ul>
	 */
	public function studyGetMetadata($studyUid)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $studyUid, ')');

		$rtns = array(
			'count' => 0,
			'error' => '',
			'firstname' => '',
			'lastname' => '',
			'notes' => 2,
			'patientid' => '',
			'sourceae' => '',
			'studydate' => '',
			'studytime' => '',
			'uid' => $studyUid
		);

		/* make some preparations so that scanWithSubdirs() can remove outdated files */
		$min_mod_time = $this->calcOutdatedTime();
		clearstatcache();

		/* list the directory and extract UIDs from the path */
		$files = array();
		$dir = $this->rootDir;
		$ext = 'dcm';			/* also required below, when stripping it from the path */
		$err = $this->scanWithSubdirs($dir, $ext, $studyUid, 1, $min_mod_time, $files);

		/* reorder data into proper format */
		$keys_uniq = array();
		foreach ($files as $file)
		{
			/* strip the extension from the path */
			if (strlen($ext))
				$clean_path = str_replace(".$ext", '', $file);
			else
				$clean_path = $file;

			/* extract UIDs from the path */
			$uids = explode('/', $clean_path);
			switch (count($uids))
			{
			case 3:
				/* [0] is the same as $studyUid, we'll ignore it */
				$series_uid = $uids[1];
				$image_uid = $uids[2];
				break;

			case 2:
				/* a situation when scanWithSubdirs() was called with $studyUid
				   already appended to $dir
				 */
				$series_uid = $uids[0];
				$image_uid = $uids[1];
				break;

			case 1:
				$series_uid = '';
				$image_uid = $uids[0];
			}

			/* prepare combined UIDs that we'll return as Image/Series UIDs */
			$allids2 = "$series_uid*$studyUid";
			$allids3 = "$image_uid*$series_uid*$studyUid";

			/* initialize properties common to all series */
			if (!$rtns['count'])
			{
				$rtns['uid'] = $studyUid;
				$rtns['patientid'] = '';
				$rtns['lastname'] = '';
				$rtns['firstname'] = '';
				$rtns['studydate'] = '';
				$rtns['studytime'] = '';
				$rtns['sourceae'] = '';
				$rtns['notes'] = 2;
			}

			/* perhaps a new series must be added */
			$i = array_search($allids2, $keys_uniq);
			if ($i === false)
			{
				$i = $rtns['count']++;	/* ready to use because of 0-based indices */

				$keys_uniq[] = $allids2;
				$rtns[$i] = array('count' => 0, 'id' => $allids2,
					'description' => '', 'modality' => '', 'seriesno' => '');
			}

			/* finally, we simply augment the selected series */
			$rtns[$i]['count']++;
			$rtns[$i][] = array('id' => $allids3, 'numframes' => '', 'xfersyntax' => '',
				'bitsstored' => '', 'sopclass' => '', 'instanceno' => '', 'acqno' => '',
				'path' => $dir . '/' . $file);
		}

		$log->asDump('$rtns = ', $rtns);
		$log->asDump('end ' . __METHOD__);
		return $rtns;
	}


	/** @brief Return UIDs of specified series in the cache.

		@param string $seriesUid  Series Instance UID (a true one, __not__ in format SER_UID*STU_UID)
		@param string $studyUid   %Study Instance UID

		The return value also includes some other attributes, however they will be empty. Format:

		<ul>
			<li><tt>'error'</tt> - an error message, empty if success
			<li><tt>'count'</tt> - number of image subarrays
			<li><tt>'firstname'</tt> - always empty string
			<li><tt>'lastname'</tt> - always empty string
			<li><tt>'fullname'</tt> - always empty string
			<li><tt>'image-000000'</tt>...<tt>'image-NNNNNN'</tt> - image subarrays

				Format of an image subarray:

				<ul>
					<li><tt>'studyid'</tt> - %Study Instance UID
					<li><tt>'seriesid'</tt> - Series Instance UID
					<li><tt>'imageid'</tt> - SOP Instance UID
					<li><tt>'path'</tt> - full path to a cached file
					<li><tt>'xfersyntax'</tt> - always empty string
					<li><tt>'numframes'</tt> - always empty string
					<li><tt>'sopclass'</tt> - always empty string
					<li><tt>'bitsstored'</tt> - Bits Stored (if supported by %PACS)
				</ul>
		</ul>
	 */
	function seriesGetMetadata($seriesUid, $studyUid)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $seriesUid, ', ', $studyUid, ')');

		$rtns = array('count' => 0, 'error' => '', 'lastname' => '', 'firstname' => '',
			'fullname' => '');

		/* make some preparations so that scanWithSubdirs() can remove outdated files */
		$min_mod_time = $this->calcOutdatedTime();
		clearstatcache();

		/* list the directory and extract UIDs from the path */
		$files = array();
		$dir = $this->rootDir . '/' . $studyUid;
		$ext = 'dcm';			/* also required below, when stripping it from the path */
		$err = $this->scanWithSubdirs($dir, $ext, $seriesUid, 0, $min_mod_time, $files);

		$keys_uniq = array();
		foreach ($files as $file)
		{
			/* strip the extension from the path */
			if (strlen($ext))
				$clean_path = str_replace(".$ext", '', $file);
			else
				$clean_path = $file;

			/* extract UIDs from the path */
			$uids = explode('/', $clean_path);
			switch (count($uids))
			{
			case 3:
				/* [0] is the same as $studyUid, we'll ignore it */
				$seriesUid = $uids[1];
				$imageUid = $uids[2];
				break;

			case 2:
				/* a situation when scanWithSubdirs() was called with $studyUid
				   already appended to $dir
				 */
				$seriesUid = $uids[0];
				$imageUid = $uids[1];
				break;

			case 1:
				$seriesUid = '';
				$imageUid = $uids[0];
			}

			/* finally, we simply augment the selected series */
			$rtns['count']++;
			$rtns[] = array(
				'studyid' => $studyUid,
				'seriesid' => $seriesUid,
				'imageid' => $imageUid,
				'numframes' => '',
				'xfersyntax' => '',
				'bitsstored' => '',
				'sopclass' => '',
				'path' => $dir . '/' . $file);
		}

		$log->asDump('$rtns = ', $rtns);
		$log->asDump('end ' . __METHOD__);
		return $rtns;
	}


	/** @brief Build path to an entry given its UIDs.

		@param string $imageUid   SOP Instance UID
		@param string $seriesUid  Series Instance UID
		@param string $studyUid   %Study Instance UID

		@return array

		Elements of the returned value:

		<ul>
			<li><tt>'error'</tt> - error message (empty string if success)
			<li><tt>'path'</tt> - path to the entry. __The file does not necessarily exist.__
		</ul>
	 */
	public function getObjectPath($imageUid, $seriesUid, $studyUid)
	{
		return array('error' => '',
			'path' => $this->rootDir . "/$studyUid/$seriesUid/$imageUid.dcm");
	}


	/** @brief Remove the specified study from the cache.

		@param string $studyUid   %Study Instance UID

		@retval false Failed to scan one of mandatory subdirectories

		Currently there is no verification whether the files were successfully removed.
	 */
	public function purgeStudy($studyUid)
	{
		$log = $this->log;

		$log->asDump('begin ' . __METHOD__ . '(', $studyUid, ')');

		/* make some preparations so that scanWithSubdirs() can remove outdated *.part */
		$min_mod_time = $this->calcOutdatedTime();
		clearstatcache();

		/* will purge all files with matching extensions as $this->scanWithSubdirs()
		   is called with NULL $list. However *.part files are still filtered
		   by modification date.
		 */
		$err = $this->scanWithSubdirs($this->rootDir, 'dcm', $studyUid, 1, $min_mod_time);

		$log->asDump('$err = ', $err);
		$log->asDump('end ' . __METHOD__);
		return $err;
	}
}
