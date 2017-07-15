<?php
/*
	Original name: external.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		HIS integration script for Conquest PACS. To be renamed to external.php.
 */

	define("SHOW_ENABLED", true);			// true: HIS integration is enabled; false: disabled
	define("SHOW_DB", "conquest");			// Conquest database; SQLite3: path only, without alias (unlike $login_form_db)
	define("SHOW_USER", "user");			// database user
	define("SHOW_PASSWORD", "password");		// user's password

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
								else
									if (isset($_GET['accnum']))
										unset($_SESSION['meddream_authenticatedUser']);
									else
										if (isset($_POST['accnum']))
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
			if (isset($_SESSION['login_accnum']))
				unset($_SESSION['login_accnum']);
			if (isset($_SESSION['login_accnum_key']))
				unset($_SESSION['login_accnum_key']);
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
						$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));

						$db = "";
						$user = "";
						$password = "";
						unset($_GET['study']);
						$authDB->goHome(true, $message, $fatal, $study, 'study');
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
							$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));

							$db = "";
							$user = "";
							$password = "";
							unset($_POST['study']);
							$authDB->goHome(true, $message, $fatal, $study, 'study');
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
								$audit->log(false, "patient '$patient', " . $authDB->formatConnectDetails('', ''));

								$db = "";
								$user = "";
								$password = "";
								unset($_GET['patient']);
								$authDB->goHome(true, $message, $fatal, $patient, 'patient');
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
									$audit->log(false, "patient '$patient', " . $authDB->formatConnectDetails('', ''));

									$db = "";
									$user = "";
									$password = "";
									unset($_POST['patient']);
									$authDB->goHome(true, $message, $fatal, $patient, 'patient');
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
									$key = "";

									if (!check_accession_num($accnum, $key, $message, $fatal))
									{
										$audit->log(false, "accnum '$accnum', " . $authDB->formatConnectDetails('', ''));

										$db = "";
										$user = "";
										$password = "";
										unset($_GET['accnum']);
										$authDB->goHome(true, $message, $fatal, $accnum, 'accnum');
									}
									else
									{
										$audit->log("SUCCESS, key $key", "accnum '$accnum'");
										$_SESSION['login_accnum_key'] = $key;
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
										$key = "";

										if (!check_accession_num($accnum, $key, $message, $fatal))
										{
											$audit->log(false, "accnum '$accnum', " . $authDB->formatConnectDetails('', ''));

											$db = "";
											$user = "";
											$password = "";
											unset($_POST['accnum']);
											$authDB->goHome(true, $message, $fatal, $accnum, 'accnum');
										}
										else
										{
											$audit->log("SUCCESS, key $key", "accnum '$accnum'");
											$_SESSION['login_accnum_key'] = $key;
										}
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
				(isset($_SESSION['login_patient']) && ($_SESSION['login_patient'] != "")) ||
				(isset($_SESSION['login_accnum']) && ($_SESSION['login_accnum'] != "")))
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

	function check_study_uid($uid, &$err, &$fatal)
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

		$sql = "SELECT StudyID FROM DICOMStudies WHERE StudyInsta='" . $authDB->sqlEscapeString($uid) . "'";
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

		$sql = "SELECT PatientNam FROM DICOMPatients WHERE PatientID='" . $authDB->sqlEscapeString($uid) . "'";
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

	function check_accession_num($accnum, &$key, &$err, &$fatal)
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

		$sql = "SELECT StudyInsta FROM DICOMstudies WHERE AccessionN='" . $authDB->sqlEscapeString($accnum) . "'";
		$rs = $authDB->query($sql);
		if (!$rs)
		{
			$log->asErr("SQL error: '" . $authDB->getError() . "'");
			$err = 'errorValidateQuery';
			$fatal = 1;
			return false;
		}

		$row = $authDB->fetchAssoc($rs);
		if (isset($row['StudyInsta']))
		{
			$key = $row['StudyInsta'];
			return true;
		}

		$err = 'errorObjNotFound';
		return false;
	}
?>
