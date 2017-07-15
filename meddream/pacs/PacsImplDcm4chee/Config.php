<?php

/** @brief Implementation for <tt>$pacs='DCM4CHEE'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Dcm4chee;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='DCM4CHEE'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	public function configure()
	{
		/* initialize $this->config->data, import some generic settings including $this->dbms */
		$err = parent::configure();
		if (strlen($err))
			return $err;
		$cnf = $this->config->data;

		/* validate actual values of $dbms */
		if (($this->dbms != "MYSQL") && ($this->dbms != "MSSQL") &&
				($this->dbms != "SQLSRV"))
			return __METHOD__ . ": [DCM4CHEE] Unsupported value of \$dbms '" . $this->dbms . "' in config.php. " .
				'Allowed are "MySQL", "MSSQL", "SQLSRV".';

		/* validate $db_host

			Import and validation take place in AuthDB and below. However their messages
			are not for the end user -- they do not refer to config.php and parameter
			name there.
		 */
		if (empty($this->dbHost))
			return __METHOD__ . ': $db_host (config.php) is not set';

		/* $login_form_db */
		if (empty($this->loginFormDb))
			return __METHOD__ . ': $login_form_db (config.php) is not set';

		/* import $archive_dir_prefix */
		$sl = strlen($this->archiveDirPrefix);
		if (!$sl)
			return __METHOD__ . ': $archive_dir_prefix (config.php) is not set';
		$lc = $this->archiveDirPrefix[$sl - 1];
		if (($lc != "/") && ($lc != "\\"))
			$this->archiveDirPrefix .= DIRECTORY_SEPARATOR;
		if (!@is_dir($this->archiveDirPrefix))
			return __METHOD__ . ': $archive_dir_prefix (config.php) is not a directory: "' . $this->archiveDirPrefix . '"';

		/* provide default value for $admin_username */
		if (!strlen($this->adminUsername))
			$this->adminUsername = 'admin';

		return '';
	}
}
