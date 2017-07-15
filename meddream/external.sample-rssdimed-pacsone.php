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
		HIS integration script for PacsOne. Supports linking by Image UID,
		Series UID, multiple objects. To be renamed to external.php.
 */

	define("SHOW_ENABLED", true);		// true: HIS integration is enabled; false: disabled
	define("SHOW_DB", "pacs");		// PacsOne database
	define("SHOW_USER", "user");		// PacsOne user with view privileges
	define("SHOW_PASSWORD", "password");	// user's password

	/**
	 * Requires to define supported request list
	 */
	$supportedRequests = array('study', 'series', 'image', 'patient', 'accnum');


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
					{
						unset($_SESSION['login_post']);
					}
					else
					{
						unset($_SESSION['meddream_authenticatedUser']);
					}
				}
			}
		}
		else
			if (isset($_SESSION['login_series']) && $_SESSION['login_series'] != "")
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
						{
							unset($_SESSION['login_post']);
						}
						else
						{
							unset($_SESSION['meddream_authenticatedUser']);
						}
					}
				}
			}
			else
				if (isset($_SESSION['login_image']) && $_SESSION['login_image'] != "")
				{
					if (isset($_GET['image']))
					{
						$image = (string) $_GET['image'];
						if ($image != $_SESSION['login_image'])
						{
							$_SESSION['login_image'] = $image;
							unset($_SESSION['meddream_authenticatedUser']);
						}
					}
					else
					{
						if (isset($_POST['image']))
						{
							$image = (string) $_POST['image'];
							if ($image != $_SESSION['login_image'])
							{
								$_SESSION['login_image'] = $image;
								unset($_SESSION['meddream_authenticatedUser']);
							}
						}
						else
						{
							if (isset($_SESSION['login_post']))
							{
								unset($_SESSION['login_post']);
							}
							else
							{
								unset($_SESSION['meddream_authenticatedUser']);
							}
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
								{
									unset($_SESSION['login_post']);
								}
								else
								{
									unset($_SESSION['meddream_authenticatedUser']);
								}
							}
						}
					}
					else
						if (isset($_SESSION['login_accnum']) && $_SESSION['login_accnum'] != "")
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
									{
										unset($_SESSION['login_post']);
									}
									else
									{
										unset($_SESSION['meddream_authenticatedUser']);
									}
								}
							}
						}
						else
							if (isset($_REQUEST['study']))
							{
								unset($_SESSION['meddream_authenticatedUser']);
							}
							else
								if (isset($_REQUEST['series']))
								{
									unset($_SESSION['meddream_authenticatedUser']);
								}
								else
									if (isset($_REQUEST['image']))
									{
										unset($_SESSION['meddream_authenticatedUser']);
									}
									else
										if (isset($_REQUEST['patient']))
										{
											unset($_SESSION['meddream_authenticatedUser']);
										}
										else
											if (isset($_REQUEST['accnum']))
											{
												unset($_SESSION['meddream_authenticatedUser']);
											}

		if (isset($_SESSION['externaldessiondata']))
		{
			$_SESSION['externaldessiondata'] = array();
		}

		if (isset($_GET['dateFrom']))
		{
			setExternalSessionData('dateFrom', $_GET['dateFrom']);
		}
		if (isset($_POST['dateFrom']))
		{
			setExternalSessionData('dateFrom', $_POST['dateFrom']);
		}
		if (isset($_SESSION['DateFrom']))
		{
			setExternalSessionData('dateFrom', $_SESSION['DateFrom']);
		}

		if (isset($_GET['dateTo']))
		{
			setExternalSessionData('dateTo', $_GET['dateTo']);
		}
		if (isset($_POST['dateTo']))
		{
			setExternalSessionData('dateTo', $_POST['dateTo']);
		}
		if (isset($_SESSION['DateTo']))
		{
			setExternalSessionData('dateTo', $_SESSION['DateTo']);
		}
	}


	function setExternalSessionData($field, $value)
	{
		if (!isset($_SESSION['externaldessiondata']))
		{
			$_SESSION['externaldessiondata'] = array();
		}

		$_SESSION['externaldessiondata'][$field] = $value;
	}


	function externalLoginInfo(&$db, &$user, &$password)
	{
		if (SHOW_ENABLED)
		{
			global $audit, $authDB;

			if (isset($_SESSION['login_image']))
				unset($_SESSION['login_image']);
			if (isset($_SESSION['login_series']))
				unset($_SESSION['login_series']);
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
						}
						else
							$audit->log(true, "study '$study'");
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

							if (!check_series_uid($series, $message, $fatal))
							{
								$db = "";
								$user = "";
								$password = "";
								unset($_GET['series']);
								$audit->log(false, "series '$series', " . $authDB->formatConnectDetails('', ''));
								$authDB->goHome(true, $message, $fatal, $series, 'series');
							}
							else
								$audit->log(true, "series '$series'");
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

								if (!check_series_uid($series, $message, $fatal))
								{
									$db = "";
									$user = "";
									$password = "";
									unset($_POST['series']);
									$audit->log(false, "series '$series', " . $authDB->formatConnectDetails('', ''));
									$authDB->goHome(true, $message, $fatal, $series, 'series');
								}
								else
									$audit->log(true, "series '$series'");
							}
						}
						else
							if (isset($_GET['image']))
							{
								$image = (string) $_GET['image'];
								if (($image != "") && (isset($user)) && ($user == ""))
								{
									$db = SHOW_DB;
									$user = SHOW_USER;
									$password = SHOW_PASSWORD;
									$_SESSION['login_image'] = $image;

									if (!check_image_uid($image, $message, $fatal))
									{
										$db = "";
										$user = "";
										$password = "";
										unset($_GET['image']);
										$audit->log(false, "image '$image', " . $authDB->formatConnectDetails('', ''));
										$authDB->goHome(true, $message, $fatal, $image, 'image');
									}
									else
										$audit->log(true, "image '$image'");
								}
							}
							else
								if (isset($_POST['image']))
								{
									$image = (string) $_POST['image'];
									if (($image != "") && (isset($user)) && ($user == ""))
									{
										$db = SHOW_DB;
										$user = SHOW_USER;
										$password = SHOW_PASSWORD;
										$_SESSION['login_image'] = $image;
										$_SESSION['login_post'] = true;

										if (!check_image_uid($image, $message, $fatal))
										{
											$db = "";
											$user = "";
											$password = "";
											unset($_POST['image']);
											$audit->log(false, "image '$image', " . $authDB->formatConnectDetails('', ''));
											$authDB->goHome(true, $message, $fatal, $image, 'image');
										}
										else
											$audit->log(true, "image '$image'");
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
														$keys = join(';', $key);
														$_SESSION['login_accnum_key'] = $keys;
														$audit->log("SUCCESS, key(s): $keys", "accnum '$accnum'");
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
															$keys = join(';', $key);
															$_SESSION['login_accnum_key'] = $keys;
															$audit->log("SUCCESS, key(s): $keys", "accnum '$accnum'");
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
			(isset($_SESSION['login_series']) && ($_SESSION['login_series'] != "")) ||
			(isset($_SESSION['login_image']) && ($_SESSION['login_image'] != "")) ||
			(isset($_SESSION['login_patient']) && ($_SESSION['login_patient'] != "")) ||
			(isset($_SESSION['login_accnum']) && ($_SESSION['login_accnum'] != "")))
		{
			$privileges['closebutton'] = true;
		}
	}


	function externalActions(&$actions)
	{
		if (isset($_SESSION['login_study']) && ($_SESSION['login_study'] != ""))
		{
			$actions = array();
			$actions['action'] = "Show";
			$actions['option'] = "study";
			$actions['entry'] = array();
			$actions['entry'] = explode(';', $_SESSION['login_study']);
		}
		else
			if (isset($_SESSION['login_series']) && ($_SESSION['login_series'] != ""))
			{
				$actions = array();
				$actions['action'] = "Show";
				$actions['option'] = "series";
				$actions['entry'] = array();
				$actions['entry'] = explode(';', $_SESSION['login_series']);
			}
			else
				if (isset($_SESSION['login_image']) && ($_SESSION['login_image'] != ""))
				{
					$actions = array();
					$actions['action'] = "Show";
					$actions['option'] = "image";
					$actions['entry'] = array();
					$actions['entry'] = explode(';', $_SESSION['login_image']);
				}
				else
					if (isset($_SESSION['login_patient']) && ($_SESSION['login_patient'] != ""))
					{
						$actions = array();
						$actions['action'] = "Show";
						$actions['option'] = "patient";
						$actions['entry'] = array();
						$actions['entry'][0] = $_SESSION['login_patient'];
					}
					else
						if (isset($_SESSION['login_accnum']) && isset($_SESSION['login_accnum_key']) &&
							($_SESSION['login_accnum'] != '') && ($_SESSION['login_accnum_key'] != ''))
						{
							$actions = array();
							$actions['action'] = "Show";
							$actions['option'] = "study";
							$actions['entry'] = array();
							$actions['entry'] = explode(';', $_SESSION['login_accnum_key']);
						}
	}


	function check_study_uid($uid, &$err, &$fatal)
	{
		global $backend, $authDB, $log;

		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			return false;
		}

		$uid = $authDB->sqlEscapeString($uid);
		$uid = str_replace(',', ';', $uid);
		$uids = explode(';', $uid);
		$uid = "('" . join("','", $uids) . "')";//('uid','uid')
		$col = $backend->getPacsConfigPrm('F_STUDY_UUID');
		$sql = "SELECT private FROM study WHERE $col IN $uid";
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


	function check_accnum($accnum, &$key, &$err, &$fatal)
	{
		global $backend, $authDB, $log;

		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			return false;
		}

		$key = array();
		$numbers = explode(';', $accnum);
		$col = $backend->getPacsConfigPrm('F_STUDY_UUID');

		for ($i = 0; $i < count($numbers); $i++)
		{
			$accnum_tmp = explode(',', $authDB->sqlEscapeString($numbers[$i]));
			$accnum_tmp1 = "('" . join("','", $accnum_tmp) . "')";
			$sql = "SELECT $col FROM study WHERE accessionnum IN $accnum_tmp1";
			$rs = $authDB->query($sql);
			if (!$rs)
			{
				$log->asErr("SQL error: '" . $authDB->getError() . "'");
				$err = 'errorValidateQuery';
				$fatal = 1;
				return false;
			}

			$keys_tmp = array();
			while ($row = $authDB->fetchNum($rs))
				$keys_tmp[] = $row[0];
			if (count($keys_tmp))
				$key[] = join(',', $keys_tmp);
		}

		if (count($key))
			return true;

		$err = 'errorObjNotFound';
		return false;
	}


	function check_series_uid($uid, &$err, &$fatal)
	{
		global $backend, $authDB, $log;

		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			return false;
		}

		$uid = str_replace(',', ';', $uid);
		$uid = $authDB->sqlEscapeString($uid);
		$uid = str_replace(',', ';', $uid);
		$uids = explode(';', $uid);
		$uid = "('" . join("','", $uids) . "')";//('uid','uid')
		$col = $backend->getPacsConfigPrm('F_SERIES_UUID');
		$sql = "SELECT instances FROM series WHERE $col IN $uid";
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


	function check_image_uid($uid, &$err, &$fatal)
	{
		global $backend, $authDB, $log;

		$err = '';
		$fatal = 0;

		if (!$authDB->connect(SHOW_DB, SHOW_USER, SHOW_PASSWORD))
		{
			$err = 'errorValidateConnect';
			$fatal = 2;
			return false;
		}

		$uid = str_replace(',', ';', $uid);
		$uid = $authDB->sqlEscapeString($uid);
		$uid = str_replace(',', ';', $uid);
		$uids = explode(';', $uid);
		$uid = "('" . join("','", $uids) . "')";//('uid','uid')
		$col = $backend->getPacsConfigPrm('F_IMAGE_UUID');
		$sql = "SELECT seriesuid FROM image WHERE $col IN $uid";
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

		$sql = "SELECT private FROM patient WHERE origid='" . $authDB->sqlEscapeString($uid) . "'";
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
