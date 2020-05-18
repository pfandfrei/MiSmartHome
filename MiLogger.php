<?php
/*
 * Homegear Xiaomi Smarthome for Homegear with PHP 7.4
 * (c) Frank Motzkau 2020
 */

include_once 'MiConstants.php';
if (!class_exists('MiLogger'))
{
    final class MiLogger
    {
        /**
         * Call this method to get singleton
         *
         * @return UserFactory
         */
        public static function Instance()
        {
            static $inst = null;
            if ($inst === null) {
                $inst = new MiLogger();
            }
            return $inst;
        }

        /**
         * Private ctor so nobody else can instantiate it
         *
         */
        private function __construct()
        {
        }
        
        public function debug_log($message)
        {
            $now = strftime('%Y-%m-%d %H:%M:%S');
            error_log($now . ' >>  ' . $message . PHP_EOL, 3, MiConstants::LOGFILE);
        }
        
        public function error_log($text, $model='')
        {
            $now = strftime('%Y-%m-%d %H:%M:%S');
            if (strlen($model) > 0)
            {
                error_log($now . ' [ERROR] ' . $text . '(' . $model . ')' . PHP_EOL, 3, MiConstants::ERRFILE);
            }
            else
            {
                error_log($now . ' [ERROR] ' . $text . PHP_EOL, 3, MiConstants::ERRFILE);
            }
        }
        
        public function unknown_log($text)
        {
            $now = strftime('%Y-%m-%d %H:%M:%S');
            error_log($now . ' [UNKNOWN] ' . $text . PHP_EOL, 3, MiConstants::ERRFILE);
        }
        
        public function exception_log($e, $model='')
        {
            $now = strftime('%Y-%m-%d %H:%M:%S');
            if (strlen($model) > 0)
            {
                error_log($now . ' [EXCEPTION] '.$e->getFile().' line '.$e->getLine().'('.$e->getCode()." ".$e->getMessage()
                    . ' | ' . $model . ')' . PHP_EOL, 3, MiConstants::ERRFILE);
            }
            else
            {
                error_log($now . ' [EXCEPTION] '.$e->getFile().' line '.$e->getLine().'('.$e->getCode()." ".$e->getMessage().')' . PHP_EOL, 3, MiConstants::ERRFILE);
            }
            error_log($e->getTraceAsString() . PHP_EOL, 3, MiConstants::ERRFILE);
        }
    }

    function MiErrorHandler($errno, $errstr, $errfile, $errline) 
    {
        MiLogger::Instance()->error_log($errno.' '.$errstr.' '.$errfile.' '.$errline);
        return false;
    }
}