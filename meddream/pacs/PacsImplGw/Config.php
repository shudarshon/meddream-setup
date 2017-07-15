<?php

/** @brief Implementation for <tt>$pacs='GW'</tt>. */
namespace Softneta\MedDream\Core\Pacs\Gw;

use Softneta\MedDream\Core\Pacs\ConfigIface;
use Softneta\MedDream\Core\Pacs\ConfigAbstract;


/** @brief Implementation of ConfigIface for <tt>$pacs='GW'</tt>. */
class PacsPartConfig extends ConfigAbstract implements ConfigIface
{
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

		if (!strlen($this->pacsGatewayAddr))
			return '$pacs_gateway_addr (config.php) is not set';

		/* Uncomment this ONLY for "type: qr" configuration of PGW */
//		$this->retrieveEntireStudy = 1;

		return '';
	}


	public function supportsAuthentication()
	{
		return false;
	}
}
