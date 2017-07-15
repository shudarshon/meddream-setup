<?php
/*
	Original name: external.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		td <tomas2mb@gmail.com>
		tb <tomas.burba@softneta.com>

	Description:
		HIS integration script for PacsOne PACS. Supports translation of
		a Patient ID and Accession Number pair to a corresponding Study UID.

		To be renamed to external.php.
 */

	define("SHOW_ENABLED", true);		// true: HIS integration is enabled; false: disabled
	define("SHOW_DB", "dbname");		// PacsOne database
	define("SHOW_USER", "user");		// PacsOne user with view privileges
	define("SHOW_PASSWORD", "pass");	// user's password

	/**
	 * Requires to define supported request list
	 */
	$supportedRequests = array('study', 'patient', 'accnum');

	function externalAuthentication()
	{
		$changed = false;
		$isSession = false;
		$isRequest = false;
		if (isset($_SESSION['login_study']) &&
			($_SESSION['login_study'] != ''))
		{
			$isSession = true;
			if (isset($_REQUEST['study']))
			{
				$isRequest = true;
				$study = (string) $_REQUEST['study'];
				if ($study != $_SESSION['login_study'])
				{
					$changed = true;
					$_SESSION['login_study'] = $study;
				}
			}
		}
		else
			if (isset($_SESSION['login_patient']) &&
				($_SESSION['login_patient'] != ''))
			{
				$isSession = true;
				//patient
				if (isset($_REQUEST['patient']))
				{
					$isRequest = true;
					$patient = (string) $_REQUEST['patient'];
					if ($patient != $_SESSION['login_patient'])
					{
						$changed = true;
						$_SESSION['login_patient'] = $patient;
					}
				}

				//accession number
				if (!isset($_SESSION['login_accnum']))
				{
					if (isset($_REQUEST['accnum']))
					{
						$isRequest = true;
						$changed = true;
						$_SESSION['login_accnum'] = $_REQUEST['accnum'];
					}
				}
				else
					if ($_SESSION['login_accnum'] != '')
					{
						if (isset($_REQUEST['accnum']))
						{
							$isRequest = true;
							$accnum = (string) $_REQUEST['accnum'];
							if ($accnum != $_SESSION['login_accnum'])
							{
								$changed = true;
								$_SESSION['login_accnum'] = $accnum;
							}
						}
					}
			}
			else
				if (isset($_REQUEST['study']) ||
					isset($_REQUEST['patient']) ||
					(isset($_REQUEST['accnum']) && isset($_REQUEST['patient'])))
					$isRequest = true;

		if ($isSession && !$isRequest)
		{
			if (isset($_SESSION['login_post']))
				unset($_SESSION['login_post']);
			else
				unset($_SESSION['meddream_authenticatedUser']);
		}

		if ((!$isSession && $isRequest) || $changed)
			unset($_SESSION['meddream_authenticatedUser']);
	}


	function externalLoginInfo(&$db, &$user, &$password)
	{
		if (SHOW_ENABLED)
		{
			global $audit;

			if (isset($_SESSION['login_study']))
				unset($_SESSION['login_study']);
			if (isset($_SESSION['login_study_equiv']))
				unset($_SESSION['login_study_equiv']);
			if (isset($_SESSION['login_patient']))
				unset($_SESSION['login_patient']);
			if (isset($_SESSION['login_accnum']))
				unset($_SESSION['login_accnum']);
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

							if (isset($_GET['accnum']))
								$accnum = $_GET['accnum'];
							else
								$accnum = '';

							if (!strlen($accnum))
							{
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
							else
							{
								$_SESSION['login_accnum'] = $accnum;
								if (check_patient_and_accnum($patient, $accnum, $study, $message, $fatal))
								{
									$_SESSION['login_study_equiv'] = $study;
									$audit->log("SUCCESS, key $study", "patient '$patient', accnum '$accnum'");
								}
								else
								{
									global $authDB;

									$audit->log(false, "patient '$patient', accnum '$accnum', " . $authDB->formatConnectDetails('', ''));

									$db = "";
									$user = "";
									$password = "";
									unset($_GET['patient']);
									unset($_GET['accnum']);
									$authDB->goHome(true, $message, $fatal, $patient . '/' . $accnum,
										'patient/accnum');
									exit;
								}
							}
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

								if (isset($_POST['accnum']))
									$accnum = $_POST['accnum'];
								else
									$accnum = '';

								if (!strlen($accnum))
								{
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
								else
								{
									if (check_patient_and_accnum($patient, $accnum, $study, $message, $fatal))
									{
										$_SESSION['login_study_equiv'] = $study;
										$audit->log("SUCCESS, key $study", "patient '$patient', accnum '$accnum'");
									}
									else
									{
										global $authDB;

										$audit->log(false, "patient '$patient', accnum '$accnum', " . $authDB->formatConnectDetails('', ''));

										$db = "";
										$user = "";
										$password = "";
										unset($_POST['patient']);
										unset($_GET['accnum']);
										$authDB->goHome(true, $message, $fatal, $patient . '/' . $accnum,
											'patient/accnum');
										exit;
									}
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
				(isset($_SESSION['login_patient']) && ($_SESSION['login_patient'] != "")) ||
				(isset($_SESSION['login_study_equiv']) && ($_SESSION['login_study_equiv'] != "")))
			$privileges['closebutton'] = true;
	}


	function externalActions(&$actions)
	{
		if (isset($_SESSION['login_study']) &&
			($_SESSION['login_study'] != ""))
		{
			$actions = array();
			$actions['action'] = "Show";
			$actions['option'] = "study";
			$actions['entry'] = array();
			$actions['entry'][0] = $_SESSION['login_study'];
		}
		else
			/* tricky: login_patient also exists in this case so it must be checked later */
			if (isset($_SESSION['login_study_equiv']) &&
				($_SESSION['login_study_equiv'] != ""))
			{
				$actions = array();
				$actions['action'] = "Show";
				$actions['option'] = "study";
				$actions['entry'] = array();
				$actions['entry'][0] = $_SESSION['login_study_equiv'];
			}
			else
				if (isset($_SESSION['login_patient']) &&
					($_SESSION['login_patient'] != ""))
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


	function check_patient_and_accnum($patient, $accnum, &$study, &$err, &$fatal)
	{
		global $authDB, $log;

		$study = '';
		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			return false;
		}
		$sql = "SELECT uuid FROM study WHERE patientid='" . $authDB->sqlEscapeString($patient) .
			"' AND accessionnum='" . $authDB->sqlEscapeString($accnum) . "' ORDER BY uuid ASC";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			$err = 'errorValidateQuery';
			$fatal = 1;
			return false;
		}
		$uids = array();
		while ($row = $authDB->fetchNum($rs))
			$uids[] = $row[0];
		if (!count($uids))
		{
			$err = 'errorObjNotFound';
			return false;
		}
		else
		{
			if (count($uids) > 1)
				exit("combination of Patient ID ($patient) and Accession Number ($accnum)" .
					" results in multiple studies:<br>\n" .
					join("<br>\n", $uids));

			$study = $uids[0];
		}

		return true;
	}
?>
