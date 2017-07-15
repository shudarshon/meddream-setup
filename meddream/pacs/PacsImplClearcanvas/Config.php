<?php

/** @brief Implementation for <tt>$pacs='ClearCanvas'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Clearcanvas;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='ClearCanvas'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
	public function configure()
	{
		/* initialize $this->config->data, import some generic settings including $this->dbms */
		$err = parent::configure();
		if (strlen($err))
			return $err;
		$cnf = $this->config->data;

		/* validate $dbms */
		if (($this->dbms != "MSSQL") && ($this->dbms != "SQLSRV"))
			return '[ClearCanvas] $dbms (config.php) is not "MSSQL" or "SQLSRV"';

		/* validate $login_form_db */
		if (empty($this->loginFormDb))
			return __METHOD__ . ': $login_form_db (config.php) is not set';

		return '';
	}
}
