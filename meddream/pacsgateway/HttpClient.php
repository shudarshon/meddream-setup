<?php

namespace Softneta\MedDream\Core\PacsGateway;

use Softneta\MedDream\Core\Logging;


/** @brief cURL-based HTTP(s) client. */
class HttpClient
{
	/** @brief Value for the @c CURLOPT_CONNECTTIMEOUT setting, in seconds */
	const CONNECT_TIMEOUT_SEC = 10;

	protected $log;         /**< @brief Instance of Logging */
	protected $baseUrl;     /**< @brief Base URL for the endpoints */


	/** @brief Result parser for request().

		@param string $url         URL of the request (for logging)
		@param string $duration    Timing information (for logging)
		@param string $curlError   Result of curl_error()
		@param string $httpStatus  HTTP response code
		@param string $httpBody    Body of the response (might be large and/or not necessarily in JSON format)

		@retval false      Something failed, an error message might have been logged
		@retval otherwise  Data read from the remote resource (usually of @c string type)

		A separate method for unit tests.
	 */
	public function parseResult($url, $duration, $curlError, $httpStatus, &$httpBody)
	{
		/* obvious failures */
		if (strlen($curlError))
		{
			$msg = "endpoint $url: failure (in $duration s), HTTP status $httpStatus, cURL error '$curlError'";
			$this->log->asErr($msg);
			return false;
		}

		/* a "normal" response */
		if ($httpStatus === 200)
		{
			$msg = "endpoint $url: success (in $duration s), data = " . var_export($httpBody, true);
			$this->log->asDump($msg);
			return $httpBody;
		}

		/* REST-like behavior: HTTP Status is not 200 and Body contains a JSON-formatted error message.
		   The only message that must reach the user (by converting to a single 'error' element) is
		   "object not found" etc, others are logged.
		 */
		if (strlen($httpBody) && (($httpBody[0] == '{') || ($httpBody[0] == '[')))
		{
			$this->log->asWarn("endpoint $url: HTTP status $httpStatus (in $duration s), attempting to work around");
			$this->log->asDump('body = ', $httpBody);

			$arr = @json_decode($httpBody, true);
			if (!is_array($arr))
			{
				$this->log->asErr('json_decode failed (' . json_last_error() . ') on ' . var_export($httpBody, true));
				return false;
			}

			/* attempt to extract a user-friendly message

				Sometimes it's indicated by a substring in .detail, sometimes also by a certain
				value of .title.
			 */
			if (!in_array('detail', array_keys($arr)))
			{
				$this->log->asErr('mandatory field missing in ' . var_export($httpBody, true));
				return false;
			}
			$userError = null;
			if (strpos($arr['detail'], 'does not exist') !== false)
			{
				$userError = $arr['detail'];
			}
			if (is_null($userError) && in_array('title', array_keys($arr)))
				if ($arr['title'] == 'User Error')
				{
					$userError = $arr['detail'];
				}
			if (!is_null($userError))
			{
				/*  We assume that json_encode won't fail, as input data is coming from json_decode.
					(The most common reason is wrong UTF-8 encoding -- unlikely in this case.)
					Even if it fails, PacsGw.php will react to a non-array result.
				 */
				$arr = array('error' => $userError);
				$result = @json_encode($arr, $flags);
				$this->log->asDump('mapped result: ', $result);
				return $result;
			}

			$this->log->asErr('endpoint failure: ' . var_export($httpBody, true));
			return false;
		}
		else
		{
			$this->log->asErr("endpoint $url: HTTP status $httpStatus (in $duration s), body = " .
				var_export($httpBody, true));
			return false;
		}
	}


	/** @brief Constructor.

		@param string  $baseUrl  Initializer for @link $baseUrl @endlink
		@param Logging $logger   Initializer for @link $log @endlink
	 */
	public function __construct($baseUrl, Logging $logger)
	{
		$this->baseUrl = $baseUrl;
		$this->log = $logger;
	}


	/** @brief Make a HTTP(s) request.

		@param string $relativeUrl  A URL relative to @link $baseUrl @endlink

		@retval false      Something failed
		@retval otherwise  Data read from the remote resource
	 */
	public function request($relativeUrl)
	{
		if (!strlen($this->baseUrl))
		{
			$this->log->asErr('base URL not specified');
			return false;
		}
		if (!function_exists('curl_init'))
		{
			$this->log->asErr('cURL PHP extension is missing');
			return false;
		}

		$tm = microtime(true);

		$url = $this->baseUrl . $relativeUrl;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);		/* we don't have a client certificate */
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);			/* we are connecting to a trusted server (localhost) */
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SEC);

		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		if (strlen($err))
		{
			$num = curl_errno($ch);
			$err = "($num) $err";
		}
		curl_close($ch);

		return $this->parseResult($url, sprintf('%.3f', microtime(true) - $tm), $err, $httpcode, $result);
	}
}
