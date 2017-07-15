<?php
/*
	Original name: clearcanvas.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		ClearCanvas-related functions. First, structure of a study is partially
		based on XML files. Second, there are path prefixes, called "filesystems"
		and "partitions", that are defined in the database.
 */

use Softneta\MedDream\Core\Logging;


function ClearCanvas_collect_storage(&$authDB, &$filesystems, &$partitions)
{
	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump('begin ' . $modulename . '/' . __FUNCTION__);
	$return = array();

	/* filesystems */
	$filesystems = array();
	$sql = "SELECT CONVERT(varchar(50), GUID) AS filesysid, FilesystemPath FROM Filesystem";
	$log->asDump('$sql = ', $sql);
	$rs = $authDB->query($sql);
	if (!$rs)
	{
		$return['error'] = "Error in SQL (1): '" . $authDB->getError() . "'";
		$log->asErr('$authDB->query: ' . $return['error']);
		return 0;
	}
	$count = 0;
	while ($row = $authDB->fetchAssoc($rs))
	{
		$log->asDump("result #$count: ", $row);

		$filesystems[$row['filesysid']] = $row['FilesystemPath'];
		$count++;
	}
	if (!$count)
	{
		$log->asErr('FATAL: database integrity (1)');
		return 0;
	}

	/* partitions */
	$partitions = array();
	$sql = "SELECT CONVERT(varchar(50), GUID) AS partnid, PartitionFolder FROM ServerPartition";
	$log->asDump('$sql = ', $sql);
	$rs = $authDB->query($sql);
	if (!$rs)
	{
		$return['error'] = "Error in SQL (2): '" . $authDB->getError() . "'";
		$log->asErr('$authDB->query: ' . $return['error']);
		return 0;
	}
	$count = 0;
	while ($row = $authDB->fetchAssoc($rs))
	{
		$log->asDump("result #$count: ", $row);

		$partitions[$row['partnid']] = trim($row['PartitionFolder']);
			/* trim(): one ClearCanvas instance had trailing spaces */
		$count++;
	}
	if (!$count)
	{
		$log->asErr('FATAL: database integrity (2)');
		return 0;
	}

	/* that's all, folks! */
	$log->asDump('end ' . $modulename . '/' . __FUNCTION__);
	return 1;
}


function cmp_instances($a, $b)
{
	return $a['instance'] - $b['instance'];
}


function ClearCanvas_fetch_study($dir, $uid, &$arr)
{
	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump('begin ' . $modulename . '/' . __FUNCTION__ . "('$dir', '$uid')");

//$log->asDump('$arr = ', $arr);

	/* load the study description file and do basic checks */
	$file = $dir . "\\$uid\\$uid.xml";
	$log->asDump('$file = ', $file);
	$xd = @file_get_contents($file);
	if ($xd === FALSE)
	{
		$log->asErr("failed to read '$file'");
		return 0;
	}
	$xs = xml2struct($xd);
	$log->asDump('$xs = ', $xs);
	if (!$xs['count'] || (($xs[0]['name'] != 'ClearCanvasStudyXml') &&
		($xs[0]['name'] != 'InfoDifStudyXml')))
	{
		$log->asErr("corrupt '$file' (1)");
		return 0;
	}
	if (!$xs[0]['count'] || ($xs[0][0]['name'] != 'Study'))
	{
		$log->asErr("corrupt '$file' (2)");
		return 0;
	}
	if (!$xs[0][0]['count'] || ($xs[0][0][0]['name'] != 'Series'))
	{
		$log->asErr("corrupt '$file' (3)");
		return 0;
	}
	$sa = $xs[0][0];
//$log->asDump('$sa = ', $sa);

	/* load corresponding instance IDs etc for each series */
	for ($i = 0; $i < $arr['count']; $i++)
	{
		$series_id = $arr[$i]['id'];

//$log->asDump('$series_id = ', $series_id);

		for ($j = 0; $j < $sa['count']; $j++)
			if ($sa[$j]['attributes']['UID'] == $series_id)
			{
				/* "BaseInstance" keeps most common tags */
				$ba = NULL;
				for ($t = 0; $t < $sa[$j]['count']; $t++)
					if ($sa[$j][$t]['name'] == 'BaseInstance')
						if ($sa[$j][$t]['count'])	/* sometimes it's empty */
							if ($sa[$j][$t][0]['name'] == 'Instance')
							{
								$ba = $sa[$j][$t][0];
								break;
							}

//$log->asDump('$ba = ', $ba);

				/* collect a couple of attributes from all "Instance"s */
				$arr[$i]['count'] = 0;
				for ($k = 0; $k < $sa[$j]['count']; $k++)	/* all children of a "Series" */
					if ($sa[$j][$k]['name'] == 'Instance')	/* skip "BaseInstance" */
					{
						$ia = $sa[$j][$k];
//$log->asDump('$ia = ', $ia);
						if (!$ia['attributes'] || !array_key_exists('UID', $ia['attributes']))
						{
							/* <Instance> without own attributes, or "UID" missing??? */
							$log->asErr("corrupt '$file' (4) [$i,$j,$k]");
							return 0;
						}

						/* extract (0028,0008) Number Of Frames */
						$nf = '0';
						for ($t = 0; $t < $ia['count']; $t++)
							if ($ia[$t]['name'] == 'Attribute')
							{
								if (!$ia[$t]['attributes'] || !array_key_exists('Tag', $ia[$t]['attributes']))
								{
									/* <Attribute> without own attributes, or "Tag" missing??? */
									$log->asErr("corrupt '$file' (5) [$i,$j,$k,$t]");
									return 0;
								}
								if ($ia[$t]['attributes']['Tag'] == '00280008')
								{
									$nf = $ia[$t][0];
									break;
								}
							}
						if (!$nf && $ba)
							for ($t = 0; $t < $ba['count']; $t++)
								if ($ba[$t]['name'] == 'Attribute')
								{
									if (!$ba[$t]['attributes'] || !array_key_exists('Tag', $ba[$t]['attributes']))
									{
										/* <Attribute> without own attributes, or "Tag" missing??? */
										$log->asErr("corrupt '$file' (6) [$i,$j,$k,$t]");
										return 0;
									}
									if ($ba[$t]['attributes']['Tag'] == '00280008')
									{
										$nf = $ba[$t][0];
										break;
									}
								}

						/* extract (0028,0101) Bits Stored */
						$bs = '8';
						for ($t = 0; $t < $ia['count']; $t++)
							if ($ia[$t]['name'] == 'Attribute')
							{
								if (!$ia[$t]['attributes'] || !array_key_exists('Tag', $ia[$t]['attributes']))
								{
									/* <Attribute> without own attributes, or "Tag" missing??? */
									$log->asErr("corrupt '$file' (7) [$i,$j,$k,$t]");
									return 0;
								}
								if ($ia[$t]['attributes']['Tag'] == '00280101')
								{
									$bs = $ia[$t][0];
									break;
								}
							}
						if (!$bs && $ba)
							for ($t = 0; $t < $ba['count']; $t++)
								if ($ba[$t]['name'] == 'Attribute')
								{
									if (!$ba[$t]['attributes'] || !array_key_exists('Tag', $ba[$t]['attributes']))
									{
										/* <Attribute> without own attributes, or "Tag" missing??? */
										$log->asErr("corrupt '$file' (8) [$i,$j,$k,$t]");
										return 0;
									}
									if ($ba[$t]['attributes']['Tag'] == '00280101')
									{
										$bs = $ba[$t][0];
										break;
									}
								}

						/* extract (0020,0013) Instance Number, *from each instance* */
						$in = 0;
						for ($t = 0; $t < $ia['count']; $t++)
							if ($ia[$t]['name'] == 'Attribute')
							{
								if (!$ia[$t]['attributes'] || !array_key_exists('Tag', $ia[$t]['attributes']))
								{
									/* <Attribute> without own attributes, or "Tag" missing??? */
									$log->asErr("corrupt '$file' (9) [$i,$j,$k,$t]");
									return 0;
								}
								if ($ia[$t]['attributes']['Tag'] == '00200013')
								{
									$in = $ia[$t][0];
									break;
								}
							}

						/* preserve what has been found */
						if (array_key_exists('TransferSyntaxUID', $ia['attributes']))
							$ts = $ia['attributes']['TransferSyntaxUID'];
						else
							$ts = '';	/* this time we'll be lenient */
						$arr[$i][] = array('id' => $ia['attributes']['UID'] . "*$series_id",
							'path' => "$dir\\$uid\\$series_id\\" . $ia['attributes']['UID'] . '.dcm',
							'instance' => $in,
							'xfersyntax' => $ts,
							'numframes' => $nf,
							'bitsstored' => $bs);
						$arr[$i]['count']++;
					}
			}
	}
//$log->asDump('before sorting: $arr = ', $arr);

	/* sort by Instance Number */
	for ($i = 0; $i < $arr['count']; $i++)
	{
		/* array must be of different format for sorting */
		$series = array();
		for ($j = 0; $j < $arr[$i]['count']; $j++)
			$series[] = $arr[$i][$j];

		usort($series, 'cmp_instances');

		for ($j = 0; $j < $arr[$i]['count']; $j++)
			$arr[$i][$j] = $series[$j];
	}
//$log->asDump('after sorting: $arr = ', $arr);

	$log->asDump('end ' . $modulename . '/' . __FUNCTION__);
	return 1;
}


function ClearCanvas_fetch_series(&$authDB, $series_id)
{
	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump('begin ' . $modulename . '/' . __FUNCTION__ . "('$series_id')");

	/* a reference to the study is required first */
	$sql = "SELECT CONVERT(varchar(50), StudyGUID) AS studyidx FROM Series WHERE SeriesInstanceUid='" .
		$authDB->sqlEscapeString($series_id) . "'";
	$log->asDump('$sql = ', $sql);
	$rs = $authDB->query($sql);
	if (!$rs)
	{
		$log->asErr('$authDB->query/1: ' . $return['error']);
		return NULL;
	}
	if ($row = $authDB->fetchAssoc($rs))
	{
		$log->asDump('result: ', $row);
		$study_idx = $row['studyidx'];
	}
	else
	{
		$log->asErr("series '$series_id' is missing!");
		return NULL;
	}

	/* then we can obtain reference to the partition and 2nd order reference
	   to the study folder :)
	 */
	$sql = "SELECT CONVERT(varchar(50), ServerPartitionGUID) AS partnidx, " .
			"CONVERT(varchar(50), StudyStorageGUID) AS studystoridx, StudyInstanceUid FROM Study " .
			"WHERE GUID='" . $authDB->sqlEscapeString($study_idx) . "'";
	$log->asDump('$sql = ', $sql);
	$rs = $authDB->query($sql);
	if (!$rs)
	{
		$log->asErr('$authDB->query/2: ' . $return['error']);
		return NULL;
	}
	if ($row = $authDB->fetchAssoc($rs))
	{
		$log->asDump('result: ', $row);

		$partn_idx = $row['partnidx'];
		$studystor_idx = $row['studystoridx'];
		$study_id = $row['StudyInstanceUid'];
	}
	else
	{
		$log->asErr('FATAL: database integrity/1');
		return NULL;
	}

	/* ...also reference to the filesystem, and (finally) the study folder itself */
	$sql = "SELECT CONVERT(varchar(50), FilesystemGUID) AS filesysidx, StudyFolder" .
			" FROM FilesystemStudyStorage WHERE StudyStorageGUID='" .
			$authDB->sqlEscapeString($studystor_idx) . "'";
	$log->asDump('$sql = ', $sql);
	$rs = $authDB->query($sql);
	if (!$rs)
	{
		$log->asErr('$authDB->query/3: ' . $return['error']);
		return NULL;
	}
	if ($row = $authDB->fetchAssoc($rs))
	{
		$log->asDump('result: ', $row);

		$filesys_idx = $row['filesysidx'];
		$study_dir = $row['StudyFolder'];
	}
	else
	{
		$log->asErr('FATAL: database integrity/2');
		return NULL;
	}

	/* for filesystems and partitions themselves there is a separate function */
	if (!ClearCanvas_collect_storage($authDB, $filesystems, $partitions))
	{
		$log->asErr("study storage unknown, will not continue");
		return NULL;
	}

	/* at last we know where to find the study description XML */
	$path = $filesystems[$filesys_idx] . "\\" . $partitions[$partn_idx] .
		"\\$study_dir\\$study_id\\$study_id.xml";
	$log->asDump('$path = ', $path);
	$xd = @file_get_contents($path);
	if ($xd === FALSE)
	{
		$log->asErr("failed to read '$path'");
		return NULL;
	}
	$xs = xml2struct($xd);
	$log->asDump('$xs = ', $xs);
	if (!$xs['count'] || ($xs[0]['name'] != 'ClearCanvasStudyXml'))
	{
		$log->asErr("corrupt '$path' (1)");
		return NULL;
	}
	if (!$xs[0]['count'] || ($xs[0][0]['name'] != 'Study'))
	{
		$log->asErr("corrupt '$path' (2)");
		return NULL;
	}
	if (!$xs[0][0]['count'] || ($xs[0][0][0]['name'] != 'Series'))
	{
		$log->asErr("corrupt '$path' (3)");
		return NULL;
	}
	$sa = $xs[0][0];
//$log->asDump('$sa = ', $sa);

	/* load corresponding instance IDs etc for given series */
	$return = array();
	for ($j = 0; $j < $sa['count']; $j++)
		if ($sa[$j]['attributes']['UID'] == $series_id)
		{
			/* "BaseInstance" keeps most common tags */
			$ba = NULL;
			for ($t = 0; $t < $sa[$j]['count']; $t++)
				if ($sa[$j][$t]['name'] == 'BaseInstance')
					if ($sa[$j][$t]['count'])	/* sometimes it's empty */
						if ($sa[$j][$t][0]['name'] == 'Instance')
						{
							$ba = $sa[$j][$t][0];
							break;
						}

//$log->asDump('$ba = ', $ba);

			/* collect a couple of attributes from all "Instance"s */
			$count = 0;
			for ($k = 0; $k < $sa[$j]['count']; $k++)	/* all children of a "Series" */
				if ($sa[$j][$k]['name'] == 'Instance')	/* skip "BaseInstance" */
				{
					$ia = $sa[$j][$k];
//$log->asDump('$ia = ', $ia);
					if (!$ia['attributes'] || !array_key_exists('UID', $ia['attributes']))
					{
						/* <Instance> without own attributes, or "UID" missing??? */
						$log->asErr("corrupt '$path' (4) [$j,$k]");
						return NULL;
					}

					/* extract (0028,0008) Number Of Frames */
					$nf = '0';
					for ($t = 0; $t < $ia['count']; $t++)
						if ($ia[$t]['name'] == 'Attribute')
						{
							if (!$ia[$t]['attributes'] || !array_key_exists('Tag', $ia[$t]['attributes']))
							{
								/* <Attribute> without own attributes, or "Tag" missing??? */
								$log->asErr("corrupt '$path' (5) [$j,$k,$t]");
								return NULL;
							}
							if ($ia[$t]['attributes']['Tag'] == '00280008')
							{
								$nf = $ia[$t][0];
								break;
							}
						}
					if (!$nf && $ba)
						for ($t = 0; $t < $ba['count']; $t++)
							if ($ba[$t]['name'] == 'Attribute')
							{
								if (!$ba[$t]['attributes'] || !array_key_exists('Tag', $ba[$t]['attributes']))
								{
									/* <Attribute> without own attributes, or "Tag" missing??? */
									$log->asErr("corrupt '$path' (6) [$j,$k,$t]");
									return NULL;
								}
								if ($ba[$t]['attributes']['Tag'] == '00280008')
								{
									$nf = $ba[$t][0];
									break;
								}
							}

					/* extract (0028,0101) Bits Stored */
					$bs = '8';
					for ($t = 0; $t < $ia['count']; $t++)
						if ($ia[$t]['name'] == 'Attribute')
						{
							if (!$ia[$t]['attributes'] || !array_key_exists('Tag', $ia[$t]['attributes']))
							{
								/* <Attribute> without own attributes, or "Tag" missing??? */
								$log->asErr("corrupt '$path' (7) [$j,$k,$t]");
								return NULL;
							}
							if ($ia[$t]['attributes']['Tag'] == '00280101')
							{
								$bs = $ia[$t][0];
								break;
							}
						}
					if (!$bs && $ba)
						for ($t = 0; $t < $ba['count']; $t++)
							if ($ba[$t]['name'] == 'Attribute')
							{
								if (!$ba[$t]['attributes'] || !array_key_exists('Tag', $ba[$t]['attributes']))
								{
									/* <Attribute> without own attributes, or "Tag" missing??? */
									$log->asErr("corrupt '$path' (8) [$j,$k,$t]");
									return NULL;
								}
								if ($ba[$t]['attributes']['Tag'] == '00280101')
								{
									$bs = $ba[$t][0];
									break;
								}
							}

					/* preserve what has been found */
					if (array_key_exists('TransferSyntaxUID', $ia['attributes']))
						$ts = $ia['attributes']['TransferSyntaxUID'];
					else
						$ts = '';	/* this time we'll be lenient */
					$img = array('xfersyntax' => $ts, 'bitsstored' => $bs,
						'path' => $filesystems[$filesys_idx] . "\\" . $partitions[$partn_idx] .
							"\\$study_dir\\$study_id\\$series_id\\" . $ia['attributes']['UID'] .
							'.dcm');
					$return["image-" . sprintf("%06d", $count++)] = $img;
				}
			$return['count'] = $count;

			/* other series are irrelevant */
			break;
		}

	$log->asDump('end ' . $modulename . '/' . __FUNCTION__);
	return $return;
}


function ClearCanvas_fetch_instance(&$authDB, $series_id, $instance_id)
{
	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump('begin ' . $modulename . '/' . __FUNCTION__ . "('$series_id', '$instance_id')");

	/* a reference to the study is required first */
	$sql = "SELECT CONVERT(varchar(50), StudyGUID) AS studyidx FROM Series WHERE SeriesInstanceUid='" .
		$authDB->sqlEscapeString($series_id) . "'";
	$log->asDump('$sql = ', $sql);
	$rs = $authDB->query($sql);
	if (!$rs)
	{
		$log->asErr('$authDB->query/1: ' . $return['error']);
		return NULL;
	}
	if ($row = $authDB->fetchAssoc($rs))
	{
		$log->asDump('result: ', $row);
		$study_idx = $row['studyidx'];
	}
	else
	{
		$log->asErr("series '$series_id' is missing!");
		return NULL;
	}

	/* then we can obtain reference to the partition and 2nd order reference
	   to the study folder :)
	 */
	$sql = "SELECT CONVERT(varchar(50), ServerPartitionGUID) AS partnidx, " .
			"CONVERT(varchar(50), StudyStorageGUID) AS studystoridx, StudyInstanceUid FROM Study " .
			"WHERE GUID='" . $authDB->sqlEscapeString($study_idx) . "'";
	$log->asDump('$sql = ', $sql);
	$rs = $authDB->query($sql);
	if (!$rs)
	{
		$log->asErr('$authDB->query/2: ' . $return['error']);
		return NULL;
	}
	if ($row = $authDB->fetchAssoc($rs))
	{
		$log->asDump('result: ', $row);

		$partn_idx = $row['partnidx'];
		$studystor_idx = $row['studystoridx'];
		$study_id = $row['StudyInstanceUid'];
	}
	else
	{
		$log->asErr('FATAL: database integrity/1');
		return NULL;
	}

	/* ...also reference to the filesystem, and (finally) the study folder itself */
	$sql = "SELECT CONVERT(varchar(50), FilesystemGUID) AS filesysidx, StudyFolder" .
			" FROM FilesystemStudyStorage WHERE StudyStorageGUID='" .
			$authDB->sqlEscapeString($studystor_idx) . "'";
	$log->asDump('$sql = ', $sql);
	$rs = $authDB->query($sql);
	if (!$rs)
	{
		$log->asErr('$authDB->query/3: ' . $return['error']);
		return NULL;
	}
	if ($row = $authDB->fetchAssoc($rs))
	{
		$log->asDump('result: ', $row);

		$filesys_idx = $row['filesysidx'];
		$study_dir = $row['StudyFolder'];
	}
	else
	{
		$log->asErr('FATAL: database integrity/2');
		return NULL;
	}

	/* for filesystems and partitions themselves there is a separate function */
	if (!ClearCanvas_collect_storage($authDB, $filesystems, $partitions))
	{
		$log->asErr('study storage unknown, will not continue');
		return NULL;
	}

	/* at last we know where to find the study description XML */
	$path = $filesystems[$filesys_idx] . "\\" . $partitions[$partn_idx] .
		"\\$study_dir\\$study_id\\$study_id.xml";
	$log->asDump('$path = ', $path);
	$xd = @file_get_contents($path);
	if ($xd === FALSE)
	{
		$log->asErr("failed to read '$path'");
		return NULL;
	}
//$log->asDump('$xd = ', $xd);
	$xs = xml2struct($xd);
//$log->asDump('$xs = ', $xs);
	if (!$xs['count'] || (($xs[0]['name'] != 'ClearCanvasStudyXml') &&
		($xs[0]['name'] != 'InfoDifStudyXml')))
	{
		$log->asErr("corrupt '$path' (1)");
		return NULL;
	}
	if (!$xs[0]['count'] || ($xs[0][0]['name'] != 'Study'))
	{
		$log->asErr("corrupt '$path' (2)");
		return NULL;
	}
	if (!$xs[0][0]['count'] || ($xs[0][0][0]['name'] != 'Series'))
	{
		$log->asErr("corrupt '$path' (3)");
		return NULL;
	}
	$sa = $xs[0][0];
//$log->asDump('$sa = ', $sa);

	/* find our instance */
	$return = array();
	$found = 0;
	for ($j = 0; $j < $sa['count']; $j++)
		if ($sa[$j]['attributes']['UID'] == $series_id)
		{
			$found = 1;

			/* "BaseInstance" keeps most common tags */
			$ba = NULL;
			for ($t = 0; $t < $sa[$j]['count']; $t++)
				if ($sa[$j][$t]['name'] == 'BaseInstance')
					if ($sa[$j][$t]['count'])	/* sometimes it's empty */
						if ($sa[$j][$t][0]['name'] == 'Instance')
						{
							$ba = $sa[$j][$t][0];
							break;
						}
//$log->asDump('$ba = ', $ba);

			/* collect a couple of attributes from all "Instance"s */
			for ($k = 0; $k < $sa[$j]['count']; $k++)	/* all children of a "Series" */
				if ($sa[$j][$k]['name'] == 'Instance')	/* skip "BaseInstance" */
				{
					$ia = $sa[$j][$k];
//$log->asDump('$ia = ', $ia);

					if (!$ia['attributes'] || !array_key_exists('UID', $ia['attributes']))
					{
						/* <Instance> without own attributes, or "UID" missing??? */
						$log->asErr("corrupt '$path' (4) [$j,$k]");
						return NULL;
					}

					if ($ia['attributes']['UID'] == $instance_id)
					{
						$found = 2;

						/* extract (0028,0008) Number Of Frames */
						$nf = '0';
						for ($t = 0; $t < $ia['count']; $t++)
							if ($ia[$t]['name'] == 'Attribute')
							{
								if (!$ia[$t]['attributes'] || !array_key_exists('Tag', $ia[$t]['attributes']))
								{
									/* <Attribute> without own attributes, or "Tag" missing??? */
									$log->asErr("corrupt '$path' (5) [$j,$k,$t]");
									return NULL;
								}
								if ($ia[$t]['attributes']['Tag'] == '00280008')
								{
									$nf = $ia[$t][0];
									break;
								}
							}
						if (!$nf && $ba)
							for ($t = 0; $t < $ba['count']; $t++)
								if ($ba[$t]['name'] == 'Attribute')
								{
									if (!$ba[$t]['attributes'] || !array_key_exists('Tag', $ba[$t]['attributes']))
									{
										/* <Attribute> without own attributes, or "Tag" missing??? */
										$log->asErr("corrupt '$path' (6) [$j,$k,$t]");
										return NULL;
									}
									if ($ba[$t]['attributes']['Tag'] == '00280008')
									{
										$nf = $ba[$t][0];
										break;
									}
								}

						/* extract (0028,0101) Bits Stored */
						$bs = '8';
						for ($t = 0; $t < $ia['count']; $t++)
							if ($ia[$t]['name'] == 'Attribute')
							{
								if (!$ia[$t]['attributes'] || !array_key_exists('Tag', $ia[$t]['attributes']))
								{
									/* <Attribute> without own attributes, or "Tag" missing??? */
									$log->asErr("corrupt '$path' (7) [$j,$k,$t]");
									return NULL;
								}
								if ($ia[$t]['attributes']['Tag'] == '00280101')
								{
									$bs = $ia[$t][0];
									break;
								}
							}
						if (!$bs && $ba)
							for ($t = 0; $t < $ba['count']; $t++)
								if ($ba[$t]['name'] == 'Attribute')
								{
									if (!$ba[$t]['attributes'] || !array_key_exists('Tag', $ba[$t]['attributes']))
									{
										/* <Attribute> without own attributes, or "Tag" missing??? */
										$log->asErr("corrupt '$path' (8) [$j,$k,$t]");
										return NULL;
									}
									if ($ba[$t]['attributes']['Tag'] == '00280101')
									{
										$bs = $ba[$t][0];
										break;
									}
								}

						/* preserve what has been found */
						if (array_key_exists('TransferSyntaxUID', $ia['attributes']))
							$ts = $ia['attributes']['TransferSyntaxUID'];
						else
							$ts = '';	/* this time we'll be lenient */
						$return = array('xfersyntax' => $ts, 'bitsstored' => $bs,
							'path' => $filesystems[$filesys_idx] . "\\" . $partitions[$partn_idx] .
								"\\$study_dir\\$study_id\\$series_id\\" . $ia['attributes']['UID'] .
								'.dcm');

						/* other instances are irrelevant */
						break;
					}
				}
		}

	if (!$return)
		$log->asErr("no data (hint: $found)");
	else
		$log->asDump('end ' . $modulename . '/' . __FUNCTION__);
	return $return;
}


/*
			THE FORMAT

	Each XML node gets its own array:

		empty input				array('count' => 0)

		<aa></aa>				array('count' => 1,
									0 => array('count' => 0, 'name' => 'aa', 'attributes' => NULL) )

		<aa><bb b="B" /></aa>	array('count' => 1,
									0 => array('count' => 1, 'name' => 'aa', 'attributes' => NULL,
										0 => array('count' => 0, 'name' => 'bb',
											'attributes' => array('b' => 'B')) ) )

		<aa><bb /><bb /></aa>	array('count' => 1,
									0 => array('count' => 2, 'name' => 'aa', 'attributes' => NULL,
										0 => array('count' => 0, 'name' => 'bb', 'attributes' => NULL),
										1 => array('count' => 0, 'name' => 'bb', 'attributes' => NULL) ) )

	Node name is kept in the element "name". The element named "count"
	indicates how many integer-keyed subelements exist at this level.
	Such a subelement is also used for single scalars, and consequently
	"count" is zero for empty nodes.
 */

function xml2struct($str)
{
	/* check whether we have a valid file beginning as the parser
	   is unable to produce a comprehensive message on its own
	 */
	$fb_bin = substr($str, 0, 8);
	if ($fb_bin != pack('C*', 0xEF, 0xBB, 0xBF, 0x3C, 0x3F, 0x78, 0x6D, 0x6C))
		trigger_error("XML encoded in UTF-8 with BOM is required. First bytes are " .
			strtoupper(chunk_split(array_shift(unpack('H*', $fb_bin)), 2, ' ')) . ".");

	/* serialize via XML Parser */
	$parser = xml_parser_create('UTF-8');
	xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
	xml_parse_into_struct($parser, $str, $values, $tags);
	$e = xml_get_error_code($parser);
	if ($e)
		trigger_error('XML Parser: ' . xml_error_string($e));
	xml_parser_free($parser);

	$locations = array();
	$index = 0;
	$data = array('count' => 0);
	foreach ($values as $elem)
		switch ($elem['type'])
		{
		case 'open':
			array_push($locations, $index);
			$index = xml2struct_assign_at($locations, $data, $elem);
			break;

		case 'close':
			$index = array_pop($locations) + 1;
			break;

		case 'complete':
			array_push($locations, $index);		/* to address inner elements */
			xml2struct_assign_at($locations, $data, $elem);
			$index = array_pop($locations) + 1;
		}

	return $data;
}


function xml2struct_assign_at($locations, &$struct, &$elem)
{
	/* recursively walk down until we're at the last level */
	$idx = array_shift($locations);
	if (count($locations))
		return xml2struct_assign_at($locations, $struct[$idx], $elem);
			/* The return value is used in xml2struct() for initial $index when
			   a new container tag is found. If that tag contains a scalar
			   before subelements, then in order to preserve that scalar,
			   $index can't be zero.

				<element>
					scalar
					<subelement />
					...

			   It's a strange syntax indeed, however XML Parser does pass it
			   to us instead of rejecting the entire input. We'll do the same;
			   discarding that scalar would require additional effort anyway.
			 */

	/* add new container at this level */
	$struct['count']++;
	if (array_key_exists('attributes', $elem))
		$attr = $elem['attributes'];
	else
		$attr = NULL;
	$struct[$idx] = array('name' =>	$elem['tag'], 'attributes' => $attr);

	/* adding tag contents is a bit more complicated */
	if ($elem['type'] == 'complete')
	{
		if (array_key_exists('value', $elem))
		{
			$struct[$idx]['count'] = 1;
			$struct[$idx][0] = $elem['value'];
		}
		else
			$struct[$idx]['count'] = 0;
				/* there won't be 0 => ''!

				   XML Parser doesn't distinguish among "<tag />" and "<tag></tag>".
				   That's sad as the former asks for NULL instead of empty string.
				   Even "<tag />" and "<tag> </tag>" (note the space) are equivalent.
				   Only if XML_OPTION_SKIP_WHITE = 0, "<tag> </tag>" will yield a
				   non-empty value.
				 */
	}
	else
	{
		if (array_key_exists('value', $elem))
		{
			$struct[$idx]['count'] = 1;
			$struct[$idx][0] = $elem['value'];
			return 1;	/* 'open' with scalar as first subelement??? */
		}
		else
			$struct[$idx]['count'] = 0;
	}
	return 0;
}
