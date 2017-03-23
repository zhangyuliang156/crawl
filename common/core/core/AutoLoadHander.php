<?php

class AutoLoadHander
{
    static function autoload($classname)
    {
        if (strpos($classname, "\0") !== false) {
            return;
        }
        try {
            @include_once $classname.EXT_CLASS;
            return;
        } catch (Exception $e) {
            return self::__autoload_failed($classname, $e->getMessage());
        }
    }

    static function __autoload_failed($classname, $message)
    {
        eval(
            'if (!class_exists("ClassNotFoundException", false)) { '
            .'final class ClassNotFoundException extends Exception {/*_*/} }'
            .'throw new ClassNotFoundException("'.$classname.': '.$message.'");'
        );
    }
}
ignore_user_abort(true);
spl_autoload_extensions(EXT_CLASS);
spl_autoload_register(array('AutoLoadHander', "autoload"));
