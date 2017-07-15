<?php

/** @brief Implementation for <tt>$pacs='dcm4chee-arc'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Dcm4chee_arc;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='dcm4chee-arc'</tt>. */
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
		if (($this->dbms != "MYSQL") && ($this->dbms != "OCI8"))
			return __METHOD__ . ": [dcm4chee-arc] Unsupported value of \$dbms '" . $this->dbms . "' in config.php. " .
				'Allowed are "MySQL", "OCI8".';

		/* validate $db_host

			Import and validation take place in AuthDB and below. However their messages
			are not for the end user -- they do not refer to config.php and parameter
			name there.
		 */
		if (empty($this->dbHost))
			return __METHOD__ . ': $db_host (config.php) is not set';

		/* $login_form_db */
		if (($this->dbms != 'OCI8'))
			if (empty($this->loginFormDb))
				return __METHOD__ . ': $login_form_db (config.php) is not set';

		return '';
	}
}
