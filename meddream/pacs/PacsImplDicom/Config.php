<?php

/** @brief Implementation for <tt>$pacs='DICOM'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Dicom;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='DICOM'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	protected $multipleModalitySearch = true;   /**< @brief <tt>$multiple_modality_search</tt> (config.php) */


	public function configure()
	{
		/* initialize $this->config->data, import some generic settings including $this->dbms */
		$err = parent::configure();
		if (strlen($err))
			return $err;
		$cnf = $this->config->data;

		/* $dbms (already imported by parent)

			Samples of config.php tell that this variable is ignored; however
			DB.php will react to 'MySQL' etc accordingly, and that means
			unnecessary troubleshooting. Let's clean it out.

			Of course if this pseudo-PACS ever gets the login functionality,
			then $dbms will need to correspond to the actual DBMS used for logins.
		 */
		$this->dbms = '';

		/* validate other parameters already imported by parent */
		if (empty($this->dbHost))
		{
			return __METHOD__ . ': $db_host (config.php) is not set';
		}
		if (empty($this->dcm4cheRecvAet))
		{
			return __METHOD__ . ': [DICOM] $dcm4che_recv_aet (config.php) must be set to an AET that DcmRcv will use';
		}
		if (empty($this->loginFormDb))
		{
			return __METHOD__ . ': $login_form_db (config.php) is not set';
		}

		/* initialize $retrieveEntireStudy */
		$prm = get_cfg_var('meddream.retrieve_entire_study');
		if ($prm === false)
			/* no such option: provide a default value.

				ini_get() is for PHP's own options only and apparently always returns
				zero for custom options.
			 */
			$prm = 1;
		else
			$prm = (int) $prm;
		$this->retrieveEntireStudy = $prm;
		if (isset($cnf['retrieve_entire_study_override']))
			$this->retrieveEntireStudy = $cnf['retrieve_entire_study_override'];

		/* parameters that do not need validation */
		if (isset($cnf['multiple_modality_search']))
			$this->multipleModalitySearch = $cnf['multiple_modality_search'];

		return '';
	}


	public function exportCommonData($what = null)
	{
		$cd = parent::exportCommonData($what);
		$cd['multiple_modality_search'] = $this->multipleModalitySearch;
		return $cd;
	}


	/* Prefers the alias (last element) if one exists.

		Meanwhile getLoginFormDb() from ConfigAbstract is sufficient as it simply returns
		the unprocessed string needed by QR.php.
	 */
	public function getDatabaseNames()
	{
		$lfd = explode('|', $this->loginFormDb);
		return array(array_pop($lfd));
	}


	public function supportsAuthentication()
	{
		return false;
	}
}
