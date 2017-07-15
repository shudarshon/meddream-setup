<?php
/*
	Original name: external.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>
		al <audrius.liutkus@softneta.lt>

	Description:
		HIS integration script for PacsOne PACS. Includes MedDreamRIS support.
		To be renamed to external.php.
 */

	define("SHOW_ENABLED", true);		// true: HIS integration is enabled; false: disabled
	define("SHOW_DB", "dbname");		// PacsOne database
	define("SHOW_USER", "user");		// PacsOne user with view privileges
	define("SHOW_PASSWORD", "password");	// user's password
	define("SHOW_FAIL_MAX", 5);		// (MedDreamRIS) number of incorrect logins until a delay is introduced
	define("SHOW_FAIL_DELAY", 900);		// (MedDreamRIS) duration of the delay in seconds

	/**
	 * Requires to define supported request list
	 */
	$supportedRequests = array('study', 'patient', 'accnum');

	function externalAuthentication()
	{
		if (isset($_SESSION['login_study']) &&
			$_SESSION['login_study'] != "")
		{
			if (isset($_GET['study']))
			{
				$study = (string) $_GET['study'];
				if ($study != $_SESSION['login_study'])
				{
					$_SESSION['login_study'] = $study;
					unset($_SESSION['meddream_authenticatedUser']);
				}
			}
			else
			{
				if (isset($_POST['study']))
				{
					$study = (string) $_POST['study'];
					if ($study != $_SESSION['login_study'])
					{
						$_SESSION['login_study'] = $study;
						unset($_SESSION['meddream_authenticatedUser']);
					}
				}
				else
				{
					if (isset($_SESSION['login_post']))
						unset($_SESSION['login_post']);
					else
						unset($_SESSION['meddream_authenticatedUser']);
				}
			}
		}
		else
			if (isset($_SESSION['login_patient']) &&
				$_SESSION['login_patient'] != "")
			{
				if (isset($_GET['patient']))
				{
					$patient = (string) $_GET['patient'];
					if ($patient != $_SESSION['login_patient'])
					{
						$_SESSION['login_patient'] = $patient;
						unset($_SESSION['meddream_authenticatedUser']);
					}
				}
				else
				{
					if (isset($_POST['patient']))
					{
						$patient = (string) $_POST['patient'];
						if ($patient != $_SESSION['login_patient'])
						{
							$_SESSION['login_patient'] = $patient;
							unset($_SESSION['meddream_authenticatedUser']);
						}
					}
					else
					{
						if (isset($_SESSION['login_post']))
							unset($_SESSION['login_post']);
						else
							unset($_SESSION['meddream_authenticatedUser']);
					}
				}
			}
			else
				if (isset($_SESSION['login_accnum']) &&
					$_SESSION['login_accnum'] != "")
				{
					if (isset($_GET['accnum']))
					{
						$accnum = (string) $_GET['accnum'];
						if ($accnum != $_SESSION['login_accnum'])
						{
							$_SESSION['login_accnum'] = $accnum;
							unset($_SESSION['meddream_authenticatedUser']);
						}
					}
					else
					{
						if (isset($_POST['accnum']))
						{
							$accnum = (string) $_POST['accnum'];
							if ($accnum != $_SESSION['login_accnum'])
							{
								$_SESSION['login_accnum'] = $accnum;
								unset($_SESSION['meddream_authenticatedUser']);
							}
						}
						else
						{
							if (isset($_SESSION['login_post']))
								unset($_SESSION['login_post']);
							else
								unset($_SESSION['meddream_authenticatedUser']);
						}
					}
				}
				else
					if (isset($_REQUEST['study']))
						unset($_SESSION['meddream_authenticatedUser']);
					else
						if (isset($_REQUEST['patient']))
							unset($_SESSION['meddream_authenticatedUser']);
						else
							if (isset($_REQUEST['accnum']))
								unset($_SESSION['meddream_authenticatedUser']);

		if (isset($_SESSION['externaldessiondata']))
			$_SESSION['externaldessiondata'] = array();

		if (isset($_POST['dateFrom']))
			setExternalSessionData('dateFrom', $_POST['dateFrom']);
		if (isset($_GET['dateFrom']))
			setExternalSessionData('dateFrom', $_GET['dateFrom']);
		if (isset($_SESSION['DateFrom']))
			setExternalSessionData('dateFrom', $_SESSION['DateFrom']);

		if (isset($_POST['dateTo']))
			setExternalSessionData('dateTo', $_POST['dateTo']);
		if (isset($_GET['dateTo']))
			setExternalSessionData('dateTo', $_GET['dateTo']);
		if (isset($_SESSION['DateTo']))
			setExternalSessionData('dateTo', $_SESSION['DateTo']);
	}

	function setExternalSessionData($field, $value)
	{
		if (!isset($_SESSION['externaldessiondata']))
			$_SESSION['externaldessiondata'] = array();

		$_SESSION['externaldessiondata'][$field] = $value;
	}

	function externalLoginInfo(&$db, &$user, &$password, $IP = NULL)
	{
		if (SHOW_ENABLED)
		{
			global $audit;

			if (!is_null($IP))
			{
				global $authDB;

				if ((empty($user) || ($user == 'root')) && !empty($password))
				{
					$value = checkIP($IP);
					if ($value >= 0)
					{
						$authDB->goHome(true, "errorTooManyFailures-" . $value);
						exit;
					}
				}

				if (!empty($user) && ($user !== 'root'))
				{
					$value = checkIP($IP);
					if ($value >= 0)
					{
						$authDB->goHome(true, "errorTooManyFailures-" . $value);
						exit;
					}

					if (!login_HIS($user, $password))
					{
						/* home.php will react to empty $user */
						$db = '';
						$user = '';
						$password = '';
						return;
					}
					$db = SHOW_DB;
					$user = SHOW_USER;
					$password = SHOW_PASSWORD;

					/* no errors, delete IP from the IP log table */
					clearIP($IP);
				}
			}

			if (isset($_SESSION['login_study']))
				unset($_SESSION['login_study']);
			if (isset($_SESSION['login_accnum']))
				unset($_SESSION['login_accnum']);
			if (isset($_SESSION['login_accnum_key']))
				unset($_SESSION['login_accnum_key']);
			if (isset($_SESSION['login_patient']))
				unset($_SESSION['login_patient']);
			if (isset($_SESSION['login_post']))
				unset($_SESSION['login_post']);

			if (isset($_GET['study']))
			{
				$study = (string) $_GET['study'];
				if (($study != "") && (isset($user)) && ($user == ""))
				{
					$db = SHOW_DB;
					$user = SHOW_USER;
					$password = SHOW_PASSWORD;
					$_SESSION['login_study'] = $study;
					if (!check_study_uid($study, $message, $fatal))
					{
						global $authDB;

						$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));

						$db = "";
						$user = "";
						$password = "";
						unset($_GET['study']);
						$authDB->goHome(true, $message, $fatal, $study, 'study');
						exit;
					}
					else
						$audit->log(true, "study '$study'");
				}
			}
			else
				if (isset($_POST['study']))
				{
					$study = (string) $_POST['study'];
					if (($study != "") && (isset($user)) && ($user == ""))
					{
						$db = SHOW_DB;
						$user = SHOW_USER;
						$password = SHOW_PASSWORD;
						$_SESSION['login_study'] = $study;
						$_SESSION['login_post'] = true;
						if (!check_study_uid($study, $message, $fatal))
						{
							global $authDB;

							$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));

							$db = "";
							$user = "";
							$password = "";
							unset($_POST['study']);
							$authDB->goHome(true, $message, $fatal, $study, 'study');
							exit;
						}
						else
							$audit->log(true, "study '$study'");
					}
				}
				else
					if (isset($_GET['patient']))
					{
						$patient = (string) $_GET['patient'];
						if (($patient != "") && (isset($user)) && ($user == ""))
						{
							$db = SHOW_DB;
							$user = SHOW_USER;
							$password = SHOW_PASSWORD;
							$_SESSION['login_patient'] = $patient;
							if (!check_patient_uid($patient, $message, $fatal))
							{
								global $authDB;

								$audit->log(false, "patient '$patient', " . $authDB->formatConnectDetails('', ''));

								$db = "";
								$user = "";
								$password = "";
								unset($_GET['patient']);
								$authDB->goHome(true, $message, $fatal, $patient, 'patient');
								exit;
							}
							else
								$audit->log(true, "patient '$patient'");
						}
					}
					else
						if (isset($_POST['patient']))
						{
							$patient = (string) $_POST['patient'];
							if (($patient != "") && (isset($user)) && ($user == ""))
							{
								$db = SHOW_DB;
								$user = SHOW_USER;
								$password = SHOW_PASSWORD;
								$_SESSION['login_patient'] = $patient;
								$_SESSION['login_post'] = true;
								if (!check_patient_uid($patient, $message, $fatal))
								{
									global $authDB;

									$audit->log(false, "patient '$patient', " . $authDB->formatConnectDetails('', ''));

									$db = "";
									$user = "";
									$password = "";
									unset($_POST['patient']);
									$authDB->goHome(true, $message, $fatal, $patient, 'patient');
									exit;
								}
								else
									$audit->log(true, "patient '$patient'");
							}
						}
						else
							if (isset($_GET['accnum']))
							{
								$accnum = (string) $_GET['accnum'];
								if (($accnum != "") && (isset($user)) && ($user == ""))
								{
									$db = SHOW_DB;
									$user = SHOW_USER;
									$password = SHOW_PASSWORD;
									$_SESSION['login_accnum'] = $accnum;
									if (!check_accnum($accnum, $key, $message, $fatal))
									{
										global $authDB;

										$audit->log(false, "accnum '$accnum', " . $authDB->formatConnectDetails('', ''));

										$db = "";
										$user = "";
										$password = "";
										unset($_GET['accnum']);
										$authDB->goHome(true, $message, $fatal, $accnum, 'accnum');
										exit;
									}
									else
									{
										$_SESSION['login_accnum_key'] = $key;
										$audit->log("SUCCESS, key $key", "accnum '$accnum'");
									}
								}
							}
							else
								if (isset($_POST['accnum']))
								{
									$accnum = (string) $_POST['accnum'];
									if (($accnum != "") && (isset($user)) && ($user == ""))
									{
										$db = SHOW_DB;
										$user = SHOW_USER;
										$password = SHOW_PASSWORD;
										$_SESSION['login_accnum'] = $accnum;
										$_SESSION['login_post'] = true;
										if (!check_accnum($accnum, $key, $message, $fatal))
										{
											global $authDB;

											$audit->log(false, "accnum '$accnum', " . $authDB->formatConnectDetails('', ''));

											$db = "";
											$user = "";
											$password = "";
											unset($_POST['accnum']);
											$authDB->goHome(true, $message, $fatal, $accnum, 'accnum');
											exit;
										}
										else
										{
											$_SESSION['login_accnum_key'] = $key;
											$audit->log("SUCCESS, key $key", "accnum '$accnum'");
										}
									}
								}

			/* also provide login credentials for imagejpeg.php */
			global $imagejpeg;
			if (isset($imagejpeg) && $imagejpeg)	/* defined and initialized only there */
			{
				global $imagejpegsize;
				$db = SHOW_DB;
				$user = SHOW_USER;
				$password = SHOW_PASSWORD;
				$imagejpegsize = 1024;
			}
		}
	}

	function externalPrivileges(&$privileges)
	{
		if ((isset($_SESSION['login_study']) && ($_SESSION['login_study'] != "")) ||
				(isset($_SESSION['login_patient']) && ($_SESSION['login_patient'] != "")))
			$privileges['closebutton'] = true;
	}

	function externalActions(&$actions)
	{
		if (isset($_SESSION['login_study']) &&
			($_SESSION['login_study'] != ''))
		{
			$actions = array();
			$actions['action'] = "Show";
			$actions['option'] = "study";
			$actions['entry'] = array();
			$actions['entry'][0] = $_SESSION['login_study'];
		}
		else
			if (isset($_SESSION['login_patient']) &&
				($_SESSION['login_patient'] != ''))
			{
				$actions = array();
				$actions['action'] = "Show";
				$actions['option'] = "patient";
				$actions['entry'] = array();
				$actions['entry'][0] = $_SESSION['login_patient'];
			}
			else
				if (isset($_SESSION['login_accnum']) &&
					isset($_SESSION['login_accnum_key']) &&
					($_SESSION['login_accnum'] != '') &&
					($_SESSION['login_accnum_key'] != ''))
				{
					$actions = array();
					$actions['action'] = "Show";
					$actions['option'] = "study";
					$actions['entry'] = array();
					$actions['entry'][0] = $_SESSION['login_accnum_key'];
				}
	}

	/*
		$user		string from the login form
		$password	-//-
		return		true: match, false: mismatch or error (the latter is only logged)
	 */
	function login_HIS($user, $password)
	{
		global $authDB;
		global $log;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
			return false;

		$user = mysql_real_escape_string($user);
		$sql = "SELECT user_psw, user_id, user_usty_id, user_inst_id, user_first_name, user_last_name," .
			" user_default_language FROM risusersinfo WHERE user = '$user'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			return false;
		}
		$row = $authDB->fetchAssoc($rs);
		if (!$row)
		{
			$log->asErr("unknown username: '$user'");
			return false;	/* caller will handle that */
		}
		$p_stored = $row[0];
		$p_actual = sha1($password);
		if (strcasecmp($p_stored, $p_actual))
		{
			$log->asErr("wrong password for '$user'");
			return false;	/* same userland message as if name not found: standard security practice */
		}

		//set session info about user rights..
		$_SESSION["user_id"] = $row[1];
		$_SESSION["user_type_id"] = $row[2];
		$_SESSION["user_inst_id"] = $row[3];
		$_SESSION["user_full_name"] = $row[4] . " " . $row[5];
		$_SESSION["user_language"] = $row["user_default_language"];
		return true;
	}

	function check_study_uid($uid, &$err, &$fatal)
	{
		global $authDB;
		global $log;

		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			/*
				If SHOW_USER or SHOW_PASSWORD is invalid, only administrator
				can fix that by editing this file; can't offer the login
				form as credentials from it are used later and elsewhere.

				Error message is logged in AuthDB::connect().
			 */
			return false;
		}
		$sql = "SELECT private FROM study WHERE uuid='" . $authDB->sqlEscapeString($uid) . "'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			$err = 'errorValidateQuery';
			$fatal = 1;
			return false;
		}
		$row = $authDB->fetchAssoc($rs);
		if (!$row)
		{
			$err = 'errorObjNotFound';
			return false;
		}

		return true;
	}

	function check_accnum($accnum, &$key, &$err, &$fatal)
	{
		global $authDB;
		global $log;

		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			/*
				If SHOW_USER or SHOW_PASSWORD is invalid, only administrator
				can fix that by editing this file; can't offer the login
				form as credentials from it are used later and elsewhere.

				Error message is logged in AuthDB::connect().
			 */
			return false;
		}
		$sql = "SELECT uuid FROM study WHERE accessionnum='" . $authDB->sqlEscapeString($accnum) . "'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			$err = 'errorValidateQuery';
			$fatal = 1;
			return false;
		}
		$row = $authDB->fetchNum($rs);
		if (!$row)
		{
			$err = 'errorObjNotFound';
			return false;
		}

		$key = $row[0];
		return true;
	}

	function check_patient_uid($uid, &$err, &$fatal)
	{
		global $authDB;
		global $log;

		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			return false;
		}
		$sql = "SELECT private FROM patient WHERE origid='" . $authDB->sqlEscapeString($uid) .
			"'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			$err = 'errorValidateQuery';
			$fatal = 1;
			return false;
		}
		$row = $authDB->fetchAssoc($rs);
		if (!$row)
		{
			$err = 'errorObjNotFound';
			return false;
		}

		return true;
	}

	/* Flood control: starting from the SHOW_FAIL_MAX-th login failure, attempts
	   must be separated by at least SHOW_FAIL_DELAY seconds.

	   Returns number of *minutes* for which logging in will be denied. If the
	   last failure was sufficiently long ago, returns -1. May return FALSE in
	   case of errors.
	 */
    function checkIP($IP)
    {
		global $authDB;
		global $log;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
			return false;

		$result = -1;
		$currentDate = time();
		$guessedDate = $currentDate - SHOW_FAIL_DELAY;

		/* count occurences of this IP and set time when user can log in */
		$sql = "SELECT COUNT(loip_id) as 'count', max(loip_datetime) as 'last_action'" .
			" FROM risiploginlog WHERE loip_ip = '$IP' AND loip_datetime >= '$guessedDate'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			return false;
				/* caller must use conditions like "$retval >= LIMIT"; then FALSE
				   won't be interpreted as "OK to log in"
				 */
		}
		$row = $authDB->fetchAssoc($rs);
		if (!$row)
		{
			$log->asErr("database integrity");
			return false;
		}

		$count = $row['count'];
		$realDate = $row['last_action'];

		if ($count >= SHOW_FAIL_MAX)
		{
			$interval = $currentDate - strtotime($realDate);

			if ($interval >= SHOW_FAIL_DELAY)
			{
				clearIP($IP);
				$result = -1;
			}
			else
				$result = (int) ((SHOW_FAIL_DELAY - $interval) / 60);
		}
		else
		{
			/* insert into the failure log */
			$sql = "INSERT INTO risiploginlog (loip_ip, loip_datetime) VALUES ('$IP', '" .
				date("Y-m-d H:i:s", $currentDate) . "')";
			$rs = $authDB->query($sql);
			if (!$rs)
			{
				$log->asErr("SQL error: '" . $authDB->getError() . "'");
				return false;
			}
		}

		return $result;
	}


	function clearIP($IP)
	{
		global $authDB;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
			return false;

		$sql = "DELETE FROM risiploginlog WHERE loip_ip = '$IP'";
		$authDB->query($sql);
	}
?>
