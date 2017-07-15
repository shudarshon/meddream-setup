<?php
/*
	Original name: external.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		HIS integration script for the the 'FileSystem' pseudo-PACS.
		To be renamed to external.php.
 */

	define("SHOW_ENABLED", true);		// true: HIS integration is enabled; false: disabled
	define("SHOW_DB", "FileSystem");	// see $login_form_db in config.php
	define("SHOW_USER", "user");		// any non-empty string (no actual login required)
	define("SHOW_PASSWORD", "password");	// anything

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
			global $authDB;
			global $audit;

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
					$r = check_study_uid($study);
					if ($r)
					{
						$db = "";
						$user = "";
						$password = "";

						$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));
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
						$r = check_study_uid($study);
						if ($r)
						{
							$db = "";
							$user = "";
							$password = "";

							$audit->log(false, "study '$study', " . $authDB->formatConnectDetails('', ''));
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
							$r = check_patient_uid($patient);
							if ($r)
							{
								$db = "";
								$user = "";
								$password = "";

								$audit->log(false, "patient '$patient', " . $authDB->formatConnectDetails('', ''));
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
								$r = check_patient_uid($patient);
								if ($r)
								{
									$db = "";
									$user = "";
									$password = "";

									$audit->log(false, "patient '$patient', " . $authDB->formatConnectDetails('', ''));
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
									if (check_file($file))
									{
										$audit->log(false, "file '$file', " . $authDB->formatConnectDetails('', ''));

										$db = "";
										$user = "";
										$password = "";
										unset($_GET['file']);
										$authDB->goHome(true, "errorObjNotFound", 2, $file, 'file');
										exit;
									}
									else
										$audit->log(true, "file '$file'");
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
										if (check_file($file))
										{
											$audit->log(false, "file '$file', " . $authDB->formatConnectDetails('', ''));

											$db = "";
											$user = "";
											$password = "";
											unset($_POST['file']);
											$authDB->goHome(true, "errorObjNotFound", 2, $file, 'file');
											exit;
										}
										else
											$audit->log(true, "file '$file'");
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
				(isset($_SESSION['login_file']) && ($_SESSION['login_file'] != "")))
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

	function check_study_uid($uid)
	{
		return 4;	/* unsupported */
	}

	function check_patient_uid($uid)
	{
		return 4;	/* unsupported */
	}

	function check_file($file)
	{
		global $backend;

		clearstatcache();

		$path = str_replace("..", "", $backend->pacsConfig->getArchiveDirPrefix() . $file);

		/* trailing directory separators aren't supported by file_exists */
		$sl = strlen($path);
		$lc = substr($path, -1);
		if (($lc === '/') || ($lc === '\\'))
			$path = substr($path, 0, -1);

		return !@file_exists($path);
	}
?>
