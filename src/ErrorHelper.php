<?php

namespace EnvoyMediaGroup\Columna;

class ErrorHelper {

    /**
     * @param mixed $var
     * @return string
     */
    public static function varDumpToString($var): string {
        ob_start();
        var_dump($var);
        $export = ob_get_contents();
        ob_end_clean();
        $export = trim($export);
        $export = substr($export,strpos($export,"\n")+1);
        return $export;
    }

}