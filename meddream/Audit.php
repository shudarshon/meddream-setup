<?php
/*
	Original name: Audit.php

	Copyright: Softneta, 2016

	Classification: public

	Owner: tb <tomas.burba@softneta.com>

	Contributors:
		tb <tomas.burba@softneta.com>

	Description:
		HIPAA audit logger
 */

namespace Softneta\MedDream\Core;


/** @brief HIPAA audit logger.

	The intended pattern:

@verbatim
	$audit = new Audit('MY ACTION');
	.
	:
	if ($something_failed)
		$audit->log(false, $action_input_parameters);
	.
	:
	$audit->log(true, $action_input_parameters);
@endverbatim
 */
class Audit
{
	protected $operation;       /**< @brief Cached value of the action name */
	protected $sid;             /**< @brief Cached value of session ID to identify a particular client */


	/** @brief Constructor.

		@param string $action  Name of the action that will be logged later.

		@warning @p $action is for the name only, __do not include any "input" to your action__
		         here. The name goes to a separate column in a file of TSV (tab-separated
		         values) format, and having different strings to refer to the same action will
		         needlessly complicate parsing.

		@p $action will be remembered for subsequent calls of log().

		The current session ID will also be remembered, so make sure the
		session is already created.
	 */
	public function __construct($action)
	{
		$this->operation = $action;
		$this->sid = session_id();
	}


	/** @brief Writes a formatted event.

		@param mixed $success   Indicates the final outcome, see below
		@param string $details  Input parameter(s) of the action being logged

		Possible values of @p $success:

		<ul>
			<li><tt>null</tt>:  Do not output this column. As it is the last one, parsers
			                    should not have big problems with such an inconsistency.
			<li><tt>true</tt>:  Replaced with text "SUCCESS"
			<li><tt>false</tt>: Replaced with text "FAILURE"
			<li>otherwise:      Output as is
		</ul>
	 */
	public function log($success = NULL, $details = '')
	{
		/* the official way to turn on audit logging: comment or remove the line below */
		return;

		/* timestamp */
		$msg = '[' . date('Y-m-d H:i:s') . "]\t";

		/* session ID */
		$msg .= $this->sid;
		if (!strlen($this->sid))
			$msg .= '???';

		/* action */
		$msg .= "\t" . $this->operation;

		/* details */
		$msg .= "\t";
		if (strlen($details) || ($this->operation == 'SEARCH'))
				/* 'SEARCH' is here just for nicer look in a text file */
			$msg .= "($details)";

		/* result */
		if (!is_null($success))
		{
			if (is_bool($success))
				$resultStr = $success ? 'SUCCESS' : 'FAILURE';
			else
				$resultStr = $success;
			$msg .= "\t- $resultStr";
		}

		$msg .= "\r\n";

		$file = dirname(__FILE__) . '/log/audit-' . date('Ymd') . '.log';
		file_put_contents($file, $msg, FILE_APPEND);
	}
}
