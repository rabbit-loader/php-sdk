<?php

namespace RabbitLoader\SDK;

class Util
{
    public static function getRequestMethod()
    {
        return empty($_SERVER['REQUEST_METHOD']) ? '' : strtolower($_SERVER['REQUEST_METHOD']);
    }

    public static function sendHeader($header, $replace)
    {
        if (!headers_sent()) {
            header($header, $replace);
        }
    }

    public static function append(&$body, $element)
    {
        $replaced = 0;
        $body = str_ireplace('</head>', $element . '</head>', $body, $replaced);
        if (!$replaced) {
            $body = str_ireplace('</body>', $element . '</body>', $body, $replaced);
        }
        if (!$replaced) {
            $body = str_ireplace('</html>', $element . '</html>', $body, $replaced);
        }
        return  $replaced > 0;
    }
}
