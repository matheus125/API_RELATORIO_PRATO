<?php

namespace Hcode\Model;

class Notification
{
    const SESSION_KEY = "notifications";

    public static function add($msg, $time = null)
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        array_unshift($_SESSION[self::SESSION_KEY], [
            "msg" => $msg,
            "time" => $time ?: date("H:i")
        ]);
    }

    public static function getAll()
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            return [];
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function getTotal()
    {
        return count(self::getAll());
    }

    public static function clear()
    {
        $_SESSION[self::SESSION_KEY] = [];
    }
}