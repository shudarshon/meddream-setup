<?php
/*
	Original name: external.php

	Copyright: Softneta, 2017

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		kf <kestutis.freigofas@softneta.com>
		tb <tomas.burba@softneta.com>

	Description:
		HIS integration script for DCM4CHEE PACS. Supports linking by Series UID,
		multiple studies and multiple series. To be renamed to external.php.
 */

	define("SHOW_ENABLED", true);			// true: HIS integration is enabled; false: disabled
	define("SHOW_DB", "pacsdb");			// DCM4CHEE database
	define("SHOW_USER", "user");			// a database user
	define("SHOW_PASSWORD", "password");		// corresponding password

	/**
	 * Requires to define supported request list
	 */
	$supportedRequests = array('study', 'patient', 'series');

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
			if (isset($_SESSION['login_series']) &&
				$_SESSION['login_series'] != "")
			{
				if (isset($_GET['series']))
				{
					$series = (string) $_GET['series'];
					if ($series != $_SESSION['login_series'])
					{
						$_SESSION['login_series'] = $series;
						unset($_SESSION['meddream_authenticatedUser']);
					}
				}
				else
				{
					if (isset($_POST['series']))
					{
						$series = (string) $_POST['series'];
						if ($series != $_SESSION['login_series'])
						{
							$_SESSION['login_series'] = $series;
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
									if (isset($_GET['series']))
										unset($_SESSION['meddream_authenticatedUser']);
									else
										if (isset($_POST['series']))
											unset($_SESSION['meddream_authenticatedUser']);
	}

	function externalLoginInfo(&$db, &$user, &$password)
	{
		if (SHOW_ENABLED)
		{
			global $audit, $authDB;

			if (isset($_SESSION['login_series']))
				unset($_SESSION['login_series']);
			if (isset($_SESSION['login_study']))
				unset($_SESSION['login_study']);
			if (isset($_SESSION['login_study_key']))
				unset($_SESSION['login_study_key']);
			if (isset($_SESSION['login_series_key']))
				unset($_SESSION['login_series_key']);
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
					$key = "";

					if (!check_study_uid($study, $key, $message, $fatal))
					{
						$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));

						$db = "";
						$user = "";
						$password = "";
						unset($_GET['study']);
						$authDB->goHome(true, $message, $fatal, $study, 'study');
					}
					else
					{
						$audit->log('SUCCESS, key(s): ' . join(';', $key), "study '$study'");
						$_SESSION['login_study_key'] = $key;
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
						$key = "";

						if (!check_study_uid($study, $key, $message, $fatal))
						{
							$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));

							$db = "";
							$user = "";
							$password = "";
							unset($_POST['study']);
							$authDB->goHome(true, $message, $fatal, $study, 'study');
						}
						else
						{
							$audit->log('SUCCESS, key(s): ' . join(';', $key), "study '$study'");
							$_SESSION['login_study_key'] = $key;
						}
					}
				}
				else
					if (isset($_GET['series']))
					{
						$series = (string) $_GET['series'];
						if (($series != "") && (isset($user)) && ($user == ""))
						{
							$db = SHOW_DB;
							$user = SHOW_USER;
							$password = SHOW_PASSWORD;
							$_SESSION['login_series'] = $series;
							$key = "";

							if (!check_series_uid($series, $key, $message, $fatal))
							{
								$audit->log(false, "series '$series', " . $authDB->formatConnectDetails('', ''));

								$db = "";
								$user = "";
								$password = "";
								unset($_GET['series']);
								$authDB->goHome(true, $message, $fatal, $series, 'series');
							}
							else
							{
								$audit->log('SUCCESS, key(s): ' . join(';', $key), "series '$series'");
								$_SESSION['login_series_key'] = $key;
							}
						}
					}
					else
						if (isset($_POST['series']))
						{
							$series = (string) $_POST['series'];
							if (($series != "") && (isset($user)) && ($user == ""))
							{
								$db = SHOW_DB;
								$user = SHOW_USER;
								$password = SHOW_PASSWORD;
								$_SESSION['login_series'] = $series;
								$_SESSION['login_post'] = true;
								$key = "";

								if (!check_series_uid($series, $key, $message, $fatal))
								{
									$audit->log(false, "series '$series', " . $authDB->formatConnectDetails('', ''));

									$db = "";
									$user = "";
									$password = "";
									unset($_POST['series']);
									$authDB->goHome(true, $message, $fatal, $series, 'series');
								}
								else
								{
									$audit->log('SUCCESS, key(s): ' . join(';', $key), "series '$series'");
									$_SESSION['login_series_key'] = $key;
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
				(isset($_SESSION['login_series']) && ($_SESSION['login_series'] != "")) ||
				(isset($_SESSION['login_patient']) && ($_SESSION['login_patient'] != "")))
			$privileges['closebutton'] = true;
	}

	function externalActions(&$actions)
	{
		if (isset($_SESSION['login_study']) &&
			isset($_SESSION['login_study_key']) &&
			($_SESSION['login_study'] != '') &&
			($_SESSION['login_study_key'] != ''))
		{
			$actions = array();
			$actions['action'] = "Show";
			$actions['option'] = "study";
			$actions['entry'] = array();
			$actions['entry'] = $_SESSION['login_study_key'];
		}
		else
			if (isset($_SESSION['login_series']) &&
				isset($_SESSION['login_series_key']) &&
				($_SESSION['login_series'] != '') &&
				($_SESSION['login_series_key'] != ''))
			{
				$actions = array();
				$actions['action'] = "Show";
				$actions['option'] = "series";
				$actions['entry'] = array();
				$actions['entry'] = $_SESSION['login_series_key'];
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
	}

	function check_study_uid($uid, &$key, &$err, &$fatal)
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

		$key = array();
		$uids = explode(';', $uid);

		for ($i = 0; $i<sizeof($uids); $i++)
		{
			$uids_tmp = explode(',', $authDB->sqlEscapeString($uids[$i]));//array(uid,uid)
			$uids_tmp1 = "('" . join("','", $uids_tmp) . "')";//('uid','uid')
			$sql = "SELECT pk FROM study WHERE study_iuid IN " . $uids_tmp1;
			$rs = $authDB->query($sql);
			if (!$rs)
			{
				$log->asErr("SQL error: '" . $authDB->getError() . "'");
				$err = 'errorValidateQuery';
				$fatal = 1;
				return false;
			}

			$uids_tmp = array();
			while ($row = $authDB->fetchAssoc($rs))
				$uids_tmp[] = $row['pk'];

			if (sizeof($uids_tmp) > 0)
				$key[] = join(",", $uids_tmp);
		}

		if (sizeof($key) > 0)
			return true;

		$err = 'errorObjNotFound';
		return false;
	}

	function check_series_uid($uid, &$key, &$err, &$fatal)
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

		$key = array();
		$uids = explode(';', $uid);

		for ($i = 0; $i<sizeof($uids); $i++)
		{
			$uids_tmp = explode(',', $authDB->sqlEscapeString($uids[$i]));//array(uid,uid)
			$uids_tmp1 = "('" . join("','", $uids_tmp) . "')";//('uid','uid')
			$sql = "SELECT pk FROM series WHERE series_iuid IN " . $uids_tmp1;
			$rs = $authDB->query($sql);
			if (!$rs)
			{
				$log->asErr("SQL error: '" . $authDB->getError() . "'");
				$err = 'errorValidateQuery';
				$fatal = 1;
				return false;
			}

			$uids_tmp = array();
			while ($row = $authDB->fetchAssoc($rs))
				$uids_tmp[] = $row['pk'];

			if (sizeof($uids_tmp) > 0)
				$key[] = join(",", $uids_tmp);
		}

		if (sizeof($key) > 0)
			return true;

		$err = 'errorObjNotFound';
		return false;
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

		$sql = "SELECT pk FROM patient WHERE pat_id='" . $authDB->sqlEscapeString($uid) . "'";
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

		$err = 'errorObjNotFound';
		return false;
	}
?>
