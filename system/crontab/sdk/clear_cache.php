<?php
/** 
 * Очистка файлового кеша и кеша данных с базы
 * @package DeltaCMS
 * @subpackage CMS
 * @author Naumenko A.
 * @copyright c-format
 * @cron ~/30 * * * * 
 */

// загрузка конфигураций для задача крона
chdir(dirname(__FILE__));
require_once('../../crontab.inc.php');

$caches = array(CACHE_ROOT . 'site_structure/', CACHE_ROOT . 'query/');

foreach ($caches as $dirmname) {
    if (file_exists($dirmname)) {
        
        Filesystem::delDir($dirmname);
        
        if (file_exists($dirmname)) {    
            $files = Filesystem::getAllSubdirsContent($dirmname, true);
            echo "[E] NO CLEAR Dir " . $dirmname . NL;
        } else {
            echo "[i] Clear Dir " . $dirmname . NL;
        }
    } else {
        echo "[i] Dir " . $dirmname . " no exists!" . NL; 
    }
}
//echo "[i] FileCache Done" . NL;