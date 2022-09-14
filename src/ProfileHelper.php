<?php

namespace EnvoyMediaGroup\Columna;

use Exception;

class ProfileHelper {

    /** @var int */
    static $hrtime;
    /** @var int */
    static $mem_usage;
    /** @var int */
    static $mem_peak_usage;

    public static function start() {
        self::$hrtime = hrtime(true);
        self::$mem_usage = memory_get_usage(true);
        self::$mem_peak_usage = memory_get_peak_usage(true);
    }

    public static function endAndRestart() {
        self::end();
        self::start();
    }

    public static function end() {
        if (!isset(self::$hrtime)) {
            throw new Exception(__CLASS__.'::'.__FUNCTION__." timer was not started.");
        }

        print "Time (ms): " . number_format((hrtime(true) - self::$hrtime) / 1000000) . "\n";
        print "Mem: " . self::formatBytes(memory_get_usage(true) - self::$mem_usage) . "\n";
        print "Mem (peak): " . self::formatBytes(memory_get_peak_usage(true) - self::$mem_peak_usage) . "\n";

        self::$hrtime = null;
        self::$mem_usage = null;
        self::$mem_peak_usage = null;
    }

    /**
     * @param int $bytes
     * @param int $decimals
     * @return string
     */
    protected static function formatBytes(int $bytes, int $decimals = 2): string {
        $size = array('B','KB','MB','GB');
        $factor = intval(floor((strlen($bytes) - 1) / 3));
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . $size[$factor];
    }

}