<?php
/*
	Original name: DbImpl.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		Unified API for database access. Support for $dbms='' which is a
		legitimate companion for configurations like $pacs='DICOM'.
 */

namespace Softneta\MedDream\Core\Database;


/** @brief A pseudo-implementation that keeps <tt>$dbms=''</tt> working */
class DbImpl extends DbAbstract
{
	public function reconnect($additionalOptions = array())
	{
		return '';
	}


	public function close()
	{
		return true;
	}


	public function query($sql, $returnVarName = '', $bindVarName = '', $data = null)
	{
		return false;		/* indicate error: this function should not be called */
	}


	public function free($result)
	{
		return false;
	}


	public function fetchAssoc(&$result)
	{
		return false;
	}


	public function fetchNum(&$result)
	{
		return false;
	}


	public function getAffectedRows($result)
	{
		return false;
	}


	public function getInsertId()
	{
		return false;
	}


	public function getError()
	{
		return 'internal: this function shall not be called';
	}


	public function sqlEscapeString($stringToEscape)
	{
		return $stringToEscape;
	}


	/** @brief Implementation of DbIface::tableExists(). */
	public function tableExists($name)
	{
		return false;
	}
}
