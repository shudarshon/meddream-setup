<?php
namespace Softneta\MedDream\Core;

class HttpUtils
{
    private static $ERROR_MESSAGES = array(
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error'
    );

    public static function getParam($var, $default = null)
    {
        if (isset($_REQUEST[$var]))
            return $_REQUEST[$var];
        if ($default === null)
            self::error('missing ' . $var);
        return $default;
    }

    public static function errorFromArray($error)
    {
        self::error(array('code' => $error[1], 'detail' => $error[2]));
    }

    public static function error($error, $code = 500)
    {
        if (function_exists('http_response_code'))
            http_response_code($code);
        elseif (isset(self::$ERROR_MESSAGES[$code])) {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . self::$ERROR_MESSAGES[$code]);
        }
        self::returnJSON(array('error' => $error));
    }

    public static function returnJSON($value)
    {
        header('Content-Type: application/json');
        exit(json_encode($value));
    }
}
