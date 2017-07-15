<?php
/*
	Original name: external.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		A legacy universal HIS integration script. NOT RECOMMENDED neither for new
		installations nor updates, see quick_install-HIS_integration.txt.
 */

	define("SHOW_ENABLED", true);	// true: HIS integration is enabled; false: disabled

	/* database name

		PacsOne
			Choose a single database even if the PACS is configured for multiple ones

		DCM4CHEE
			"pacsdb" by default

		Conquest
			"conquest" by default.

			For SQLite3 this is a full path to the database file, like in $login_form_db.
			However	the "|ALIAS" part is not supported.

		ClearCanvas
			"ImageServer" by default

		WADO
		DICOM
			See $login_form_db in config.php

		FileSystem
			Any non-empty string
	 */
	define("SHOW_DB", "db_name");

	/* user's name

		PacsOne
			A PacsOne user with view privileges

		DCM4CHEE
			Any database user (internal DCM4CHEE users are not supported)

		Conquest
		ClearCanvas
			A database user

		FileSystem
		WADO
		DICOM
			Any non-empty string (no actual login required)
	 */
	define("SHOW_USER", "user_name");

	/* user's password

		FileSystem
		WADO
		DICOM
			Any non-empty string

		<remaining configurations>
			A corresponding password
	 */
	define("SHOW_PASSWORD", "password");

	/**
	 * Requires to define supported request list
	 */
	$supportedRequests = array('study', 'patient', 'file');

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
				if (isset($_SESSION['login_file']) &&
					$_SESSION['login_file'] != "")
				{
					if (isset($_GET['file']))
					{
						$file = (string) $_GET['file'];
						if ($file != $_SESSION['login_file'])
						{
							$_SESSION['login_file'] = $file;
							unset($_SESSION['meddream_authenticatedUser']);
						}
					}
					else
					{
						if (isset($_POST['file']))
						{
							$file = (string) $_POST['file'];
							if ($file != $_SESSION['login_file'])
							{
								$_SESSION['login_file'] = $file;
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
									if (isset($_GET['file']))
										unset($_SESSION['meddream_authenticatedUser']);
									else
										if (isset($_POST['file']))
											unset($_SESSION['meddream_authenticatedUser']);
	}

	function externalLoginInfo(&$db, &$user, &$password)
	{
		if (SHOW_ENABLED)
		{
			if (isset($_SESSION['login_study']))
				unset($_SESSION['login_study']);
			if (isset($_SESSION['login_patient']))
				unset($_SESSION['login_patient']);
			if (isset($_SESSION['login_file']))
				unset($_SESSION['login_file']);
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

						$db = "";
						$user = "";
						$password = "";
						unset($_GET['study']);
						$authDB->goHome(true, $message, $fatal, $study, 'study');
						exit;
					}
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

							$db = "";
							$user = "";
							$password = "";
							unset($_POST['study']);
							$authDB->goHome(true, $message, $fatal, $study, 'study');
							exit;
						}
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

								$db = "";
								$user = "";
								$password = "";
								unset($_GET['patient']);
								$authDB->goHome(true, $message, $fatal, $patient, 'patient');
								exit;
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
								if (!check_patient_uid($patient, $message, $fatal))
								{
									global $authDB;

									$db = "";
									$user = "";
									$password = "";
									unset($_POST['patient']);
									$authDB->goHome(true, $message, $fatal, $patient, 'patient');
									exit;
								}
							}
						}
						else
							if (isset($_GET['file']))
							{
								$file = (string) $_GET['file'];
								if (($file != "") && (isset($user)) && ($user == ""))
								{
									$db = SHOW_DB;
									$user = SHOW_USER;
									$password = SHOW_PASSWORD;
									$_SESSION['login_file'] = $file;
									if (!check_file($file, $message, $fatal))
									{
										global $authDB;

										$db = "";
										$user = "";
										$password = "";
										unset($_GET['file']);
										$authDB->goHome(true, $message, $fatal, $file, 'file');
										exit;

									}
								}
							}
							else
								if (isset($_POST['file']))
								{
									$file = (string) $_POST['file'];
									if (($file != "") && (isset($user)) && ($user == ""))
									{
										$db = SHOW_DB;
										$user = SHOW_USER;
										$password = SHOW_PASSWORD;
										$_SESSION['login_file'] = $file;
										$_SESSION['login_post'] = true;
										if (!check_file($file, $message, $fatal))
										{
											global $authDB;

											$db = "";
											$user = "";
											$password = "";
											unset($_POST['file']);
											$authDB->goHome(true, $message, $fatal, $file, 'file');
											exit;
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
		if (($_SESSION['login_study'] != "") || ($_SESSION['login_patient'] != "") ||
				($_SESSION['login_file'] != ""))
		{
			$privileges['closebutton'] = true;
		}
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
			if (isset($_SESSION['login_study_pk']) && $_SESSION['login_study_pk'] !='')
				$actions['entry'][0] = $_SESSION['login_study_pk'];
			else
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
				if (isset($_SESSION['login_file']) &&
					($_SESSION['login_file'] != ''))
				{
					$actions = array();
					$actions['action'] = "Show";
					$actions['option'] = "image";
					$actions['entry'] = array();
					$actions['entry'][0] = $_SESSION['login_file'];
				}
	}

	function check_study_uid($uid, &$err, &$fatal)
	{
		global $backend, $authDB, $log;

		$err = '';
		$fatal = 0;

		if ($backend->pacs != "DCM4CHEE")
			unset($_SESSION['login_study_pk']);		/* for different PACSes in the same host */
		if (($backend->pacs == 'WADO') || ($backend->pacs == 'DICOM'))
			return true;

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

		switch ($backend->pacs)
		{
			case "PACSONE":
				$sql = "SELECT private FROM study WHERE $authDB->F_STUDY_UUID='" .
					$authDB->sqlEscapeString($uid) . "'";
				break;

			case "DCM4CHEE":
				if (strpos($uid, '.') !== false)
					$sql = "SELECT pk FROM study WHERE study_iuid='" . $authDB->sqlEscapeString($uid) . "'";
				else
					$sql = "SELECT pk FROM study WHERE pk='" . $authDB->sqlEscapeString($uid) . "'";
				break;

			case "CONQUEST":
				$sql = "SELECT StudyID FROM DICOMStudies WHERE StudyInsta='" . $authDB->sqlEscapeString($uid) . "'";
				break;

			case "CLEARCANVAS":
				$sql = "SELECT GUID FROM Study WHERE StudyInstanceUid='" . $authDB->sqlEscapeString($uid) . "'";
				break;

			default:
				return 4;
		}

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

		if ($backend->pacs == "DCM4CHEE")
			$_SESSION['login_study_pk'] = $row['pk'];
		return true;
	}

	function check_patient_uid($uid, &$err, &$fatal)
	{
		global $authDB, $log;

		$err = '';
		$fatal = 0;

		if (($backend->pacs == 'WADO') || ($backend->pacs == 'DICOM'))
			return true;
		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			return false;
		}

		switch ($backend->pacs)
		{
			case "PACSONE":
				$sql = "SELECT private FROM patient WHERE origid='" . $authDB->sqlEscapeString($uid) . "'";
				break;

			case "DCM4CHEE":
				$sql = "SELECT pk FROM patient WHERE pat_id='" . $authDB->sqlEscapeString($uid) . "'";
				break;

			case "CONQUEST":
				$sql = "SELECT PatientNam FROM DICOMPatients WHERE PatientID='" . $authDB->sqlEscapeString($uid) . "'";
				break;

			case "CLEARCANVAS":
				$sql = "SELECT GUID FROM Patient WHERE PatientId='" . $authDB->sqlEscapeString($uid) . "'";
				break;

			default:
				$err = 'unsupported PACS';
				$fatal = 1;
				return false;
		}

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

	function check_file($file, &$err, &$fatal)
	{
		global $authDB;

		$err = '';
		$fatal = 0;

		$path = str_replace("..", "", $authDB->archive_dir_prefix . $file);
		$path = str_replace("\\", "/", $path);
		$path = str_replace("//", "/", $path);

		if (@file_exists($path))
			return true;

		$err = 'errorObjNotFound';
		$fatal = 2;
		return false;
	}
?>
