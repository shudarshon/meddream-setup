<?php
/*
	Original name: external.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		HIS integration script for ClearCanvas PACS. To be renamed to external.php.
 */

	define("SHOW_ENABLED", true);			// true: HIS integration is enabled; false: disabled
	define("SHOW_DB", "ImageServer");		// ClearCanvas database
	define("SHOW_USER", "user");			// database user
	define("SHOW_PASSWORD", "password");		// user's password

	/**
	 *Requires to define supported request list
	 */
	$supportedRequests = array('study', 'patient');

	function externalAuthentication()
	{
		if (isset($_SESSION['login_study']) && $_SESSION['login_study'] != "")
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
			if (isset($_SESSION['login_patient']) && $_SESSION['login_patient'] != "")
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
				if (isset($_GET['patient']))
					unset($_SESSION['meddream_authenticatedUser']);
				else
					if (isset($_POST['patient']))
						unset($_SESSION['meddream_authenticatedUser']);
					else
						if (isset($_GET['study']))
							unset($_SESSION['meddream_authenticatedUser']);
						else
							if (isset($_POST['study']))
								unset($_SESSION['meddream_authenticatedUser']);
	}

	function externalLoginInfo(&$db, &$user, &$password)
	{
		if (SHOW_ENABLED)
		{
			global $audit, $authDB;

			if (isset($_SESSION['login_study']))
				unset($_SESSION['login_study']);
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
						$db = "";
						$user = "";
						$password = "";
						unset($_GET['study']);
						$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));
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
							$db = "";
							$user = "";
							$password = "";
							unset($_POST['study']);
							$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));
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
								$db = "";
								$user = "";
								$password = "";
								unset($_GET['patient']);
								$audit->log(false, "patient '$patient', " . $authDB->formatConnectDetails('', ''));
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
									$db = "";
									$user = "";
									$password = "";
									unset($_POST['patient']);
									$audit->log(false, "patient '$patient', " . $authDB->formatConnectDetails('', ''));
									$authDB->goHome(true, $message, $fatal, $patient, 'patient');
									exit;
								}
								else
									$audit->log(true, "patient '$patient'");
							}
						}

			/* also provide login credentials for imagejpeg.php */
			GLOBAL $imagejpeg;
			if (isset($imagejpeg) && $imagejpeg)	/* defined and initialized only there */
			{
				GLOBAL $imagejpegsize;
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
		if (isset($_SESSION['login_study']) && ($_SESSION['login_study'] != ''))
		{
			$actions = array();
			$actions['action'] = "Show";
			$actions['option'] = "study";
			$actions['entry'] = array();
			$actions['entry'] = explode(';', $_SESSION['login_study']);
		}
		else
			if (isset($_SESSION['login_patient']) && ($_SESSION['login_patient'] != ''))
			{
				$actions = array();
				$actions['action'] = "Show";
				$actions['option'] = "patient";
				$actions['entry'] = array();
				$actions['entry'][0] = $_SESSION['login_patient'];
			}
	}

	function check_study_uid($uid, &$err, &$fatal)
	{
		global $authDB, $log;

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

		$uids_flat = str_replace(',', ';', $authDB->sqlEscapeString($uid));
		$uids_all = explode(';', $uids_flat);
		$uid_lst = "('" . join("','", $uids_all) . "')";
		$sql = "SELECT GUID FROM Study WHERE StudyInstanceUid IN $uid_lst";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			$err = 'errorValidateQuery';
			$fatal = 1;
			return false;
		}
		$num = 0;
		while ($row = $authDB->fetchAssoc($rs))
			$num++;
		if ($num)
			return true;
		else
		{
			$err = 'errorObjNotFound';
			return false;
		}
	}

	function check_patient_uid($uid, &$err, &$fatal)
	{
		global $authDB, $log;

		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			return false;
		}

		$sql = "SELECT GUID FROM Patient WHERE PatientId='" . $authDB->sqlEscapeString($uid) .
			"'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			$err = 'errorValidateQuery';
			$fatal = 1;
			return false;
		}
		if ($row = $authDB->fetchAssoc($rs))
			return true;
		else
		{
			$err = 'errorObjNotFound';
			return false;
		}
	}
?>
