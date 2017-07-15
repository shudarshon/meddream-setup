<?php
/*
	Original name: dcmsys.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Functions important for the 'DCMSYS' pseudo-DBMS
 */

//TODO: centralized timeout (probably in config.php)

use Softneta\MedDream\Core\Logging;

require_once __DIR__ . '/autoload.php';

define('LOGIN_PREFIX', 'login');
define('LOGOUT_PREFIX', 'logout');
define('QIDO_RS_PREFIX', 'router/qido-rs/studies');
define('WADO_RS_PREFIX', 'router/wado-rs/studies');

/* a wrapper for json_last_error() which doesn't exist in PHP 5.2 */
function json_get_error()
{
	if (function_exists('json_last_error'))
		return json_last_error();
	else
		return -1;
}


/* safe read of a tag: the tag itself, or a further subelement accessed as ['Value'],
   or a further subelement accessed as [0], might be missing.

   $is_PN_VR enables another subelement indexed by keys 'Alphabetic', 'Phonetic',
   'Ideographic'.

   If some key is missing, will return either NULL or $def_value depending
   on $substitute_NULL. However with $is_PN_VR, '' is always returned.
 */
function dcmsys_read_tag($key, $arr, $is_PN_VR = false, $substitute_NULL = false, $def_value = '')
{
	/* first sub-index */
	if (!array_key_exists($key, $arr))
		return $substitute_NULL ? NULL : $def_value;
	$arr1 = $arr[$key];
	if (is_null($arr1))		/* not encountered yet but who knows */
		return $substitute_NULL ? NULL : $def_value;

	/* sub-index 'Value' */
	if (!array_key_exists('Value', $arr1))
		return $substitute_NULL ? NULL : $def_value;
	$arr2 = $arr1['Value'];
	if (is_null($arr2))		/* an often-seen size optimization */
		return $substitute_NULL ? NULL : $def_value;

	/* sub-index 0 */
	if (!array_key_exists(0, $arr2))
		return $substitute_NULL ? NULL : $def_value;
	$arr3 = $arr2[0];

	/* in case of non-PN tags, that's all! */
	if (!$is_PN_VR)
		return $arr3;

	/* PN will additionally have a sub-index 'Alphabetic' (or, at the same level,
	   'Phonetic' and 'Ideographic'). Let's combine as much as possible into one
	   string.
	 */
	if (array_key_exists('Alphabetic', $arr3))
		$value = $arr3['Alphabetic'];
	else
		$value = '';
	if (array_key_exists('Phonetic', $arr3))
		$value .= ' (' . $arr3['Phonetic'] . ')';
	if (array_key_exists('Ideographic', $arr3))
		$value .= ' (' . $arr3['Ideographic'] . ')';
	return trim(str_replace('^', ' ', $value));
}


/* add date separators to a string of 8 digits (full DICOM-style date) */
function dcmsys_date_from_dicom($str)
{
	if (strlen($str) == 8)
	{
		$final = preg_replace("/(\d\d\d\d)(\d\d)(\d\d)/", '$1-$2-$3', $str, -1, $num);
		if ($num === 1)			/* excludes NULL which indicates error */
			return $final;
	}
	return $str;
}


/* add time separators to a string of 6+ digits (DICOM-style time with
   an optional fractional part which will be left intact)
 */
function dcmsys_time_from_dicom($str)
{
	if (strlen($str) >= 6)
	{
		$final = preg_replace("/(\d\d)(\d\d)(\d\d)(.*)/", '$1:$2:$3$4', $str, -1, $num);
		if ($num === 1)			/* excludes NULL which indicates error */
			return $final;
	}
	return $str;
}


/* returns value of the authentication cookie, or FALSE
   in case of error ($error will be updated, too)
 */
function dcmsys_connect($user, $password, $base_url, &$error)
{
	$error = '';
	$result = false;

	/* follow_location requires PHP 5.3.4+ */
	if (version_compare(PHP_VERSION, '5.3.4', '<'))
	{
		$error = __FUNCTION__ . ': PHP version 5.3.4+ required';
		return false;
	}

	/* additional parameters */
	$data = http_build_query(array('login' => $user, 'password' => $password));
	$params = array('http' => array('timeout' => 5.0,
		'follow_location' => false,
		'method' => 'POST',
		'header' => "Content-type: application/x-www-form-urlencoded\r\n",
		'content' => $data));
	$ctx = stream_context_create($params);

	/* make a request */
	$url = $base_url . LOGIN_PREFIX;
	$fp = @fopen($url, 'rb', false, $ctx);
	if (!$fp)
	{
		$err0 = error_get_last();
		$error = __FUNCTION__ . ": failed to open '$url': " . $err0['message'];
		return false;
	}
	$headers = $http_response_header;
///var_export($headers);
///echo "\n";

	/* ensure a known status code */
	if (!count($headers))
		$error = __FUNCTION__ . ': strange headers/1: ' . var_export($headers, true);
	else
	{
		$status = $headers[0];
		$sa = explode(' ', $status);
		if (count($sa) < 2)
			$error = __FUNCTION__ . ': strange headers/2: ' . var_export($headers, true);
		else
		{
			$code = $sa[1];
			if ($code != 202)	/* as per API specification v3 */
				$error = __FUNCTION__ . ': unexpected server response: "' .
					join('\r\n', $headers) . '"';
		}
	}

	/* extract the authentication cookie */
	if (!$error)
	{
		foreach ($headers as $hdr)
			if (stripos($hdr, 'Set-Cookie: ') === 0)
			{
				$value = substr($hdr, 12);

				/* sometimes two are seen, "suid" and "mojolicious"*/
				if (strpos($value, 'mojolicious=') === 0)
				{
					$result = $value;
					break;
				}
			}

		if (!$result)
			$error = __FUNCTION__ . ': strange headers/3: ' . var_export($headers, true);
	}

	/* if there is some problem with headers, let's preserve up to 512 bytes
	   of body for diagnostic
	 */
	if ($error)
	{
		$body = @fread($fp, 512);
		if ($body === false)
		{
			$err0 = error_get_last();
			$error .= "\nmessage body: <read failed: " . $err0['message'] . '>';
		}
		else
		{
			$body = addcslashes($body, "\0..\37");

			/* the length could have increased up to four times, reduce again */
			if (strlen($body) > 512)
				$body = substr($body, 0, 512);

			$error .= "\nmessage body: '$body'";
		}
	}
		/* otherwise body is discarded -- nobody needs it */

	@fclose($fp);
///echo "$result\n";
	return $result;
}


/* destroys the current session referenced by $authDB->connection */
function dcmsys_disconnect($authDB, &$error)
{
	$error = '';
	$result = false;

	/* follow_location requires PHP 5.3.4+ */
	if (version_compare(PHP_VERSION, '5.3.4', '<'))
	{
		$error = __FUNCTION__ . ': PHP version 5.3.4+ required';
		return false;
	}

	/* additional parameters */
	$params = array('http' => array('timeout' => 5.0,
		'follow_location' => false,
		'header' => "Content-type: application/json\r\n" .
			'Cookie: ' . $authDB->connection . "\r\n"));
	$ctx = stream_context_create($params);

	/* make a request */
	$url = $authDB->db_host . LOGOUT_PREFIX;
	$fp = @fopen($url, 'rb', false, $ctx);
	if (!$fp)
	{
		$err0 = error_get_last();
		$error = __FUNCTION__ . ": failed to open '$url': " . $err0['message'];
		return false;
	}
	$headers = $http_response_header;
///var_export($headers);
///echo "\n";

	/* ensure a known status code */
	if (!count($headers))
		$error = __FUNCTION__ . ': strange headers/1: ' . var_export($headers, true);
	else
	{
		$status = $headers[0];
		$sa = explode(' ', $status);
		if (count($sa) < 2)
			$error = __FUNCTION__ . ': strange headers/2: ' . var_export($headers, true);
		else
		{
			$code = $sa[1];
			if ($code != 202)
				$error = __FUNCTION__ . ': unexpected server response: "' .
					join('\r\n', $headers) . '"';
		}
	}

	/* if there is some problem with headers, let's preserve up to 512 bytes
	   of body for diagnostic
	 */
	if ($error)
	{
		$body = @fread($fp, 512);
		if ($body === false)
		{
			$err0 = error_get_last();
			$error .= '; message body: <read failed: ' . $err0['message'] . '>';
		}
		else
		{
			$body = addcslashes($body, "\0..\37");

			/* the length could have increased up to four times, reduce again */
			if (strlen($body) > 512)
				$body = substr($body, 0, 512);

			$error .= "; message body: '$body'";
		}
	}
		/* otherwise body is discarded -- nobody needs it */

	@fclose($fp);

	return strlen($error) == 0;
}


/* fetch an object via WADO-RS

	$url: a ready to use URL of the object
	$authDB: an object instance. Uses and updates its variable ::connection.
	&$headers:
		on call:
			string with additional request headers (each entry ends with \r\n)

			if empty, is replaced by "Accept: application/json\r\n",
			otherwise taken as is (do not forget to add Accept:)
		on return:
			array with response headers for further processing
	$need_close_session: if true, will do session_write_close() just before
		reading from stream, which might take a long time

	Returns false in case of error; the actual message went to logs.
 */
function dcmsys_rq(&$authDB, $url, &$headers, $need_close_session = false)
{
	$log = new Logging();
	$log->asDump('begin ' . __FUNCTION__);

	/* follow_location requires PHP 5.3.4+ */
	if (version_compare(PHP_VERSION, '5.3.4', '<'))
	{
		$log->asErr(__FUNCTION__ . ': PHP version 5.3.4+ required');
		return false;
	}

	/* will automatically retry with re-login if needed */
	$new_auth_cookie = '';
	$initial_headers = $headers;
	$has_failed = 1;		/* NOTE: 0, 1, 2 */
	for ($num_tries = 1; ; $num_tries++)
	{
		/* append additional parameters */
		if (!strlen($initial_headers))
			$rq_hdr = "Accept: application/json\r\n";
		else
			$rq_hdr = $initial_headers;
		$params = array('http' => array('timeout' => 5.0,
			'follow_location' => false,
			'header' => 'Cookie: ' . $authDB->connection . "\r\n$rq_hdr"));
		$ctx = stream_context_create($params);

		/* pass to server */
		$fp = @fopen($url, 'rb', false, $ctx);
		if (!$fp)
		{
			$err0 = error_get_last();
			$log->asErr("failed to open '$url': " . $err0['message']);

			return false;
		}

		/* need headers: for diagnostic if further reading from URL fails, and
		   also for a new value of the authentication cookie
		 */
		$headers = $http_response_header;
		$error = '';
		if (!count($headers))
		{
			$error = 'strange headers/1: ' . var_export($headers, true);
				/* separate variable: $error will be needed near foreach() below */
			$log->asWarn($error);
		}
		else
		{
			$status = $headers[0];
			$sa = explode(' ', $status);
			if (count($sa) < 2)
			{
				$error = 'strange headers/2: ' . var_export($headers, true);
				$log->asWarn($error);
			}
			else
			{
				$code = $sa[1];
				if ($code == 200)
					$has_failed = 0;
				else
				{
					/* let's detect a HTTP 302 that redirects to /api/login */
					if ($code == 302)
					{
						foreach ($headers as $hdr)
							if (stripos($hdr, 'Location: ') === 0)
							{
								$loc = substr($hdr, 10);	/* 10: 'Location: ' */
								if ($loc == '/api/login')
									$has_failed = 2;	/* will retry */

								/* stopping in any case as there won't be multiple Location headers */
								break;
							}
					}

					/* $has_failed == 2 means everything is still under control, hence no generic logging */
					if ($has_failed < 2)
					{
						$error = 'unexpected server response: "' . join('\r\n', $headers) . '"';
						$log->asWarn($error);
					}
				}
			}
		}

		/* do we need to login once more? */
		if (!$has_failed)
			break;
		if ($num_tries > 1)
		{
			$error = 'failed after ' . $num_tries . ' attempt(s): "' . join('\r\n', $headers) . '"';
			$log->asErr($error);
			break;
		}
		$log->asInfo('session has expired, trying to re-login');
		if (!$authDB->connect('', $authDB->user, $authDB->password))
		{
			$error = 're-login failed, giving up';
			$log->asErr($error);
			break;
		}
	}
	if ($has_failed)
	{
		@fclose($fp);
		return false;
	}

	/* extract the authentication cookie */
	foreach ($headers as $hdr)
		if (stripos($hdr, 'Set-Cookie: ') === 0)
		{
			$value = substr($hdr, 12);

			/* sometimes two are seen, "suid" and "mojolicious"*/
			if (strpos($value, 'mojolicious=') === 0)
			{
				$new_auth_cookie = $value;
				break;
			}
		}
	if (!$new_auth_cookie)
	{
		$error = 'strange headers/3: ' . var_export($headers, true);
		$log->asWarn($error);
	}

	/* update the authentication cookie */
	if (strlen($new_auth_cookie))
	{
		$log->asDump('updated cookie: ', $new_auth_cookie);
		$authDB->connection = $new_auth_cookie;
	}
	if (strlen(session_id()))		/* if already closed, we'll avoid an error */
	{
		/* store the updated cookie in the session as well, in order to
		   avoid additional AuthDB::connect() in its constructor
		 */
		if (strlen($new_auth_cookie))
			$_SESSION[$authDB->sessionHeader . 'DcmsysAuthCookie'] = $new_auth_cookie;

		/* fopen() takes not much time -- only what is needed to establish a
		   connection. $http_response_header() is also available immediately.
		   But, stream_get_contents() will return only after all data has
		   been downloaded. When lengthy operation is expected, we must handle
		   $need_close_session exactly here.
		 */
		if ($need_close_session)
			@session_write_close();
	}

	$response = @stream_get_contents($fp);
	if ($response === false)
	{
		$err0 = error_get_last();
		$log->asErr("failed to read from '$url': " . $err0['message']);
	}
	@fclose($fp);

	/* finalize */
	$log->asDump('end ' . __FUNCTION__);
	return $response;
}


/* search */
function dcmsys_query_study($authDB, $num_records, $patient_id = '', $patient_name = '', $study_id = '',
	$acc_num = '', $study_desc = '', $ref_phys = '', $date_from = '', $date_to = '', $modality = '')
{
	$rtns = array('error' => '', 'count' => 0);				/* our result */

	/* some housekeeping */
	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump('begin ' . $modulename . '/' . __FUNCTION__);
	if (!strlen($authDB->connection))
	{
		$log->asErr(__FUNCTION__ . ': not connected');
		$rtns['error'] = 'not connected';
		return $rtns;
	}

	/* build a query URL */
	$url = $authDB->db_host . QIDO_RS_PREFIX . "?limit=$num_records&includefield=00080020" .
		'&includefield=00080030&includefield=00080050&includefield=00080061&includefield=00080090' .
		'&includefield=00081030&includefield=00100010&includefield=00100020&includefield=00100030' .
		'&includefield=00200010';
	if (strlen($patient_id))
		$url .= '&PatientID=' . rawurlencode('*' . $patient_id . '*');
	if (strlen($patient_name))
		$url .= '&PatientName=' . rawurlencode('*' . $patient_name . '*');
	if (strlen($study_id))
		$url .= '&StudyID=' . rawurlencode('*' . $study_id . '*');
	if (strlen($acc_num))
		$url .= '&AccessionNumber=' . rawurlencode('*' . $acc_num . '*');
	if (strlen($study_desc))
		$url .= '&StudyDescription=' . rawurlencode('*' . $study_desc . '*');
	if (strlen($ref_phys))
		$url .= '&ReferringPhysicianName=' . rawurlencode('*' . $ref_phys . '*');
	if (strlen($date_from) || strlen($date_to))
	{
		$url .= '&StudyDate=';
		if (!strlen($date_from))
			$url .= '19011213';		/* minimum of time_t */
		else
			$url .= str_replace('.', '', $date_from);
		$url .= '-';
		if (!strlen($date_to))
			$url .= '20380119';		/* maximum of time_t */
		else
			$url .= str_replace('.', '', $date_to);
	}
	if (strlen($modality))				//TODO: does the router support more than one modality at once?
		$url .= "&ModalitiesInStudy=$modality";

	/* pass to server */
	$log->asDump('request: ', $url);
	$headers = '';		/* no additional headers, use defaults */
	$raw = dcmsys_rq($authDB, $url, $headers);
	if ($raw === false)
	{
		$rtns['error'] = 'QIDO-RS request failed, see logs';
		return $rtns;
	}
	$log->asDump('server returned: ', $raw);		/* single quotes inside as JSON uses double quotes */

	/* parse it */
	$parsed = json_decode($raw, true);
	if (is_null($parsed))
	{
		$rtns['error'] = 'JSON parser failed (' . json_get_error() . ')';
		$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
		return $rtns;
	}
	$log->asDump('after parsing: ', $parsed);

	/* build an array of format required in our caller (search.php) */
	$num_found = 0;
	foreach ($parsed as $entry)
	{
		/* mandatory tags */
		$value = dcmsys_read_tag('0020000D', $entry, false, true);
		if (is_null($value))
		{
			$rtns['error'] = "mandatory element missing in #$num_found: 0020000D";
			$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
			return $rtns;
		}
		$rtns[$num_found]['uid'] = $value;

		/* optional tags */
		$rtns[$num_found]['id'] = dcmsys_read_tag('00200010', $entry);
		$rtns[$num_found]['patientid'] = dcmsys_read_tag('00100020', $entry);
		$rtns[$num_found]['patientname'] = dcmsys_read_tag('00100010', $entry, true);
		$rtns[$num_found]['patientbirthdate'] = dcmsys_date_from_dicom(dcmsys_read_tag('00100030', $entry));
		$rtns[$num_found]['modality'] = dcmsys_read_tag('00080061', $entry);
			///TODO: test with mixedmod/*
		$rtns[$num_found]['description'] = dcmsys_read_tag('00081030', $entry);
		$rtns[$num_found]['date'] = dcmsys_date_from_dicom(dcmsys_read_tag('00080020', $entry));
		$rtns[$num_found]['time'] = dcmsys_time_from_dicom(dcmsys_read_tag('00080030', $entry));
		$rtns[$num_found]['accessionnum'] = dcmsys_read_tag('00080050', $entry);
		$rtns[$num_found]['referringphysician'] = dcmsys_read_tag('00080090', $entry, true);
		$rtns[$num_found]['sourceae'] = dcmsys_read_tag('00020016', $entry, true);

		/* unsupported metadata etc */
		$rtns[$num_found]['readingphysician'] = '';
		$rtns[$num_found]['notes'] = 2;			/* won't be supported: database access is needed */
		$rtns[$num_found]['reviewed'] = '';
		$rtns[$num_found]['received'] = '';
		$rtns[$num_found]['datetime'] = $rtns[$num_found]['date'] . ' ' . $rtns[$num_found]['time'];

		$num_found++;
	}
	$rtns['count'] = $num_found;

	/* finalize */
	$log->asDump('returning: ', $rtns);
	$log->asDump('end ' . $modulename . '/' . __FUNCTION__);
	return $rtns;
}


/* fetch study structure as required by study.php */
function dcmsys_fetch_study($authDB, $uid)
{
	function assign_to_study(&$dst, $src, &$keys_uniq)
	{
		/* initialize fields that relate to the study only */
		if (!$dst['count'])
		{
			$dst['uid'] = $src['studyid'];
			$dst['patientid'] = $src['patientid'];
			$dst['studydate'] = $src['date'];

			$dst['lastname'] = '';
			$dst['firstname'] = '';
			$pa = explode('^', $src['patientname']);
			if (isset($pa[0]))
			{
				/* if there is at least one separator, then we shall extract the Last Name
				   to dedicated field and keep the remainder together as First Name (but
				   without those ugly '^')
				 */
				$dst['lastname'] = array_shift($pa);
				$remaining = trim(join(' ', $pa));
				$dst['firstname'] = $remaining;
			}
			$dst['notes'] = $src['notes'];
			$dst['sourceae'] = $src['sourceae'];
		}

		/* perhaps a new series must be added */
		$i = array_search($src['seriesid'], $keys_uniq);
		if ($i === FALSE)
		{
			$i = $dst['count']++;	/* ready to use because of 0-based indices */
			$keys_uniq[] = $src['seriesid'];
			$dst[$i] = array('count' => 0,
				'id' => $src['seriesid'] . '*' . $src['studyid'],
				'description' => $src['description'], 'modality' => $src['modality']);
				/* note combined UIDs: dcmsys_fetch_series() needs both to access a series */
		}

		/* finally, we simply augment the selected series */
		$dst[$i]['count']++;
		$dst[$i][] = array('id' => $src['imageid'] . '*' . $src['seriesid'] . '*' . $src['studyid'],
			'numframes' => $src['numframes'], 'xfersyntax' => $src['xfersyntax'],
			'bitsstored' => $src['bitsstored'], 'path' => $src['path']);
			/* combined UIDs, again: dcmsys_fetch_image needs all three at once */

	}

	$rtns = array('error' => '', 'count' => 0);		/* our result */

	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump('begin ' . $modulename . '/' . __FUNCTION__);
	if (!strlen($authDB->connection))
	{
		$log->asErr(__FUNCTION__ . ': not connected');
		$rtns['error'] = 'not connected';
		return $rtns;
	}

	/* build a query URL */
	$url = $authDB->db_host . WADO_RS_PREFIX . "/$uid/metadata";

	/* pass to server */
	$log->asDump('request: ', $url);
	$headers = '';		/* no additional headers, use defaults */
	$raw = dcmsys_rq($authDB, $url, $headers);
	if ($raw === false)
	{
		$rtns['error'] = 'WADO-RS request failed, see logs';
		return $rtns;
	}
	$log->asDump('server returned: ', $raw);		/* single quotes inside as JSON uses double quotes */

	/* parse it */
	$parsed = json_decode($raw, true);
	if (is_null($parsed))
	{
		$rtns['error'] = 'JSON parser failed (' . json_get_error() . ')';
		$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
		return $rtns;
	}
	$log->asDump('after parsing: ', $parsed);

	/* build an array of format required in our caller (search.php) */
	$num_found = 0;
	$series_uids = array();					/* tracks unique UIDs */
	foreach ($parsed as $entry)
	{
		$rsp = array();						/* linear storage for a single image */

		/* mandatory tags */
		$value = dcmsys_read_tag('0020000D', $entry, false, true);
		if (is_null($value))
		{
			$rtns['error'] = "mandatory element missing in #$num_found: 0020000D";
			$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
			return $rtns;
		}
		$rsp['studyid'] = $value;
		$value = dcmsys_read_tag('0020000E', $entry, false, true);
		if (is_null($value))
		{
			$rtns['error'] = "mandatory element missing in #$num_found: 0020000E";
			$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
			return $rtns;
		}
		$rsp['seriesid'] = $value;
		$value = dcmsys_read_tag('00080018', $entry, false, true);
		if (is_null($value))
		{
			$rtns['error'] = "mandatory element missing in #$num_found: 00080018";
			$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
			return $rtns;
		}
		$rsp['imageid'] = $value;

		/* optional tags */
		$rsp['patientid'] = dcmsys_read_tag('00100020', $entry);
		$rsp['patientname'] = dcmsys_read_tag('00100010', $entry, true);
		$rsp['description'] = dcmsys_read_tag('0008103e', $entry);
		$rsp['modality'] = dcmsys_read_tag('00080060', $entry);
		$rsp['date'] = dcmsys_date_from_dicom(dcmsys_read_tag('00080020', $entry));
		$rsp['xfersyntax'] = dcmsys_read_tag('00020010', $entry);
		$rsp['numframes'] = dcmsys_read_tag('00280018', $entry, false, false, 1);

		/* unsupported metadata etc */
		$rsp['bitsstored'] = 8;
		$rsp['notes'] = 2;			/* won't be supported: database access is needed */
		$rsp['sourceae'] = '';
		$rsp['path'] = '';

		/* add to hierarchical storage */
		if (($rsp['modality'] == 'PR') ||( $rsp['modality'] == 'KO'))
			continue;			/* our own filter */
		assign_to_study($rtns, $rsp, $series_uids);

		$num_found++;
	}

	/* finalize */
	if (!$rtns['count'])
		if (empty($rtns['error']))
			$rtns['error'] = "No images to display\n(some might have been skipped)";
	$log->asDump('returning: ', $rtns);
	$log->asDump('end ' . $modulename . '/' . __FUNCTION__);
	return $rtns;
}


/* fetch series metadata as required by saveSeries.php, managePrint.php */
function dcmsys_fetch_series($authDB, $study_uid, $series_uid)
{
	$rtns = array('error' => '', 'count' => 0);				/* our result */

	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asErr('begin ' . $modulename . '/' . __FUNCTION__);
	if (!strlen($authDB->connection))
	{
		$log->asErr(__FUNCTION__ . ': not connected');
		$rtns['error'] = 'not connected';
		return $rtns;
	}

	/* build a query URL */
	$url = $authDB->db_host . WADO_RS_PREFIX . "/$study_uid/series/$series_uid/metadata";

	/* pass to server */
	$log->asDump('request: ', $url);
	$headers = '';		/* no additional headers, use defaults */
	$raw = dcmsys_rq($authDB, $url, $headers);
	if ($raw === false)
	{
		$rtns['error'] = 'WADO-RS request failed, see logs';
		return $rtns;
	}
	$log->asDump('server returned: ', $raw);		/* single quotes inside as JSON uses double quotes */

	/* parse it */
	$parsed = json_decode($raw, true);
	if (is_null($parsed))
	{
		$rtns['error'] = 'JSON parser failed (' . json_get_error() . ')';
		$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
		return $rtns;
	}
	$log->asDump('after parsing: ', $parsed);

	/* build an array of format required in our caller (saveSeries.php, managePrint.php) */
	$num_found = 0;
	$series_uids = array();					/* tracks unique UIDs */
	foreach ($parsed as $entry)
	{
		/* mandatory tags */
		$value = dcmsys_read_tag('0020000D', $entry, false, true);
		if (is_null($value))
		{
			$rtns['error'] = "mandatory element missing in #$num_found: 0020000D";
			$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
			return $rtns;
		}
		$rtns[$num_found]['studyid'] = $value;
		$value = dcmsys_read_tag('0020000E', $entry, false, true);
		if (is_null($value))
		{
			$rtns['error'] = "mandatory element missing in #$num_found: 0020000E";
			$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
			return $rtns;
		}
		$rtns[$num_found]['seriesid'] = $value;
		$value = dcmsys_read_tag('00080018', $entry, false, true);
		if (is_null($value))
		{
			$rtns['error'] = "mandatory element missing in #$num_found: 00080018";
			$log->asErr(__FUNCTION__ . ': ' . $rtns['error']);
			return $rtns;
		}
		$rtns[$num_found]['imageid'] = $value;

		/* optional tags */
		$rtns[$num_found]['xfersyntax'] = dcmsys_read_tag('00020010', $entry);
		$rtns[$num_found]['numframes'] = dcmsys_read_tag('00280018', $entry, false, false, 0);

		/* unsupported metadata etc */
		$rtns[$num_found]['bitsstored'] = 8;
		$rtns[$num_found]['path'] = '';

		$num_found++;
		$rtns['count'] = $num_found;
	}

	/* finalize */
	$log->asDump('returning: ', $rtns);
	$log->asDump('end ' . $modulename . '/' . __FUNCTION__);
	return $rtns;
}


/* fetch a raw DICOM file */
function dcmsys_fetch_image($authDB, $study_uid, $series_uid, $image_uid)
{
	$rtn = array('path' => NULL, 'error' => '');

	$modulename = basename(__FILE__);
	$log = new Logging();
	$log->asDump('begin ' . $modulename . '/' . __FUNCTION__ . " ($study_uid, $series_uid, $image_uid)");
	if (!strlen($authDB->connection))
	{
		$log->asErr(__FUNCTION__ . ': not connected');
		$rtn['error'] = 'not connected';
		return $rtn;
	}

	/* build a query URL */
	$url = $authDB->db_host . WADO_RS_PREFIX . "/$study_uid/series/$series_uid/instances/$image_uid";

	/* pass to server */
	$log->asDump('request: ', $url);
	$headers = "Accept: multipart/related; type=application/dicom\r\n";
	$raw = dcmsys_rq($authDB, $url, $headers, true);
		/* true: close the session as this operation might take a long time */
	if ($raw === false)
	{
		$rtn['error'] = 'WADO-RS request failed, see logs';
		return $rtn;
	}

	/* a piece of body for troubleshooting (used more than once) */
	$body_start = substr($raw, 0, 512);	/* 512 usually includes delimiters, DICOM Preamble and a few first tags */
	$body_start = addcslashes($body_start, "\0..\37");
	$len = strlen($raw);

	/* can't dump everything to logs (too much data), however a small piece will be useful */
	$msg = "server returned: $len byte(s)";
	if ($len)
		$msg .= ' starting with "' . $body_start. '"';
	$log->asDump($msg);

	/* extract the "boundary" delimiter from headers */
	$boundary = NULL;
	foreach ($headers as $hdr)
		if (stripos($hdr, 'Content-Type: ') === 0)
		{
			/* if this header is found, it must contain the "boundary" attribute */
			$pos = stripos($hdr, 'boundary=');
			if ($pos === false)
			{
				$log->asErr("missing multipart boundary in Content Type: '" . var_export($headers, true) . "'");
				$error = 'invalid server response, see logs';
				return false;
			}
			$part_with_bnd = substr($hdr, $pos + 9);	/* 9: "boundary=" */

			/* get rid of trailing  "; type=..." */
			$chunks = explode(';', $part_with_bnd);
			$value = $chunks[0];

			/* so far the router uses a quoted string, though examples in RFC1341
			   have none. We'll make them optional.
			 */
			$len = strlen($value);
			if ($value[$len - 1] == '"')
				$value = substr($value, 0, $len - 1);
			if ($value[0] == '"')
				$value = substr($value, 1);

			$boundary = $value;
		}
	if (is_null($boundary))
	{
		$log->asErr('missing Content Type: ' . var_export($headers, true));
		$rtn['error'] = 'invalid server response, see logs';
		return $rtn;
	}

	/* extract a corresponding part from $raw

		official spec is http://www.w3.org/Protocols/rfc1341/7_2_Multipart.html#z0,
		for our purposes there are no deviations
	 */
	$pos = strpos($raw, "\r\n\r\n");
		/* $raw begins with the boundary (there is no preamble) and some irrelevant
		   headers, we'll just skip them in this dumb manner
		 */
	if ($pos === false)		/* just in case */
	{
		$log->asErr("EOLs missing after multipart headers: '$body_start'");
		$rtn['error'] = 'invalid server response, see logs';
		return $rtn;
	}
	$raw = substr($raw, $pos + 4);	/* 4: \r\n\r\n */

	/* we'll also ignore the epilogue and a boundary preceding it */
	$end_expected = "\r\n--$boundary--\r\n";
	$len_end = strlen($end_expected);
	$end_actual = substr($raw, -$len_end);
	if ($end_actual != $end_expected)
	{
		/* this junk can confuse php_meddream, better be informed in advance */
		$end_safe = addcslashes($end_actual, "\0..\37");
		$log->asWarn("strange end of body: '$end_safe'");
	}
	else
		$raw = substr($raw, 0, strlen($raw) - $len_end);
	$log->asDump('extracted ' . strlen($raw) . ' byte(s) of data');

	/* temporary file is needed; because tempnam() has issues, let's do our own */
	$ofn = '';
	$tries = 10;
	do
	{
		if (!(--$tries))
			break;
		$ofn = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR .
			date('YmdHis') . sprintf('%07u', mt_rand(0, 9999999)) . '.tmp.dcm';
		$ofh = @fopen($ofn, 'x');		/* fails on existing files! */
	} while ($ofh === FALSE);
	if ($tries < 1)
	{
		$rtn['error'] = "still not unique: '$ofn'";
		return $rtn;
	}
	fwrite($ofh, '.');
	fclose($ofh);
	if (file_put_contents($ofn, $raw) === false)
	{
		$rtn['error'] = "failed writing '$ofn'";
		return $rtn;
	}
	$rtn['path'] = $ofn;

	/* finalize */
	$log->asDump('returning: ', $rtn);
	$log->asDump('end ' . $modulename . '/' . __FUNCTION__);
	return $rtn;
}

?>
