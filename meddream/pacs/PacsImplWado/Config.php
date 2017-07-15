<?php

/** @brief Implementation for <tt>$pacs='WADO'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Wado;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='WADO'</tt>. */
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

		/* validate $db_host (already imported by parent)

			Here $this->dbHost is less important; we can use isset() to detect
			that parameter is absent altogether.
		 */
		if (empty($this->dbHost))
			return __METHOD__ . ': $db_host (config.php) is not set';

		/* $login_form_db */
		if (empty($this->loginFormDb))
			return __METHOD__ . ': $login_form_db (config.php) is not set';

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
