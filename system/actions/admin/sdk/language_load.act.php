<?php
/**
 * загрузка в базу языковых параметров из файлов /language/
 * @package Pilot
 * @subpackage SDK
 * @version 1.0
 * @author Naumenko A.
 * @copyright (c) 2014, c-format
 */


$query = "truncate table cms_language_message";
$DB->delete($query);

$languases = getLanguageList();
reset($languases);
while(list(,$lang)=each($languases)){
    $dir = SITE_ROOT . "language/".$lang.'/';
    $dircontent = Filesystem::getDirContent($dir, true, false, true);  
    foreach($dircontent as $filename){
        $fileinfo = pathinfo($filename);
        if(!isset($fileinfo['extension']) || $fileinfo['extension'] != 'ini') continue;
       
        //пример названия файла msg_module.ru.ini
        preg_match("/^msg_([^\.]*)/", $fileinfo['filename'], $matches);  
        if ( count($matches) < 2 ) continue; 
        $module_name = $matches[1];
        
        //извлекаем переменные
        $strings = @parse_ini_file($filename);
        if(empty($strings)) continue;
        
        $insert = array();
        foreach ($strings as $param_name => $param_value){
            $insert[] = "('$param_name', '$module_name', '".addslashes($param_value)."')";
        }
                                
        $query = "
                insert into cms_language_message (`uniq_name`, `module`, `name_$lang`)
                values ".implode(", ", $insert). "
                ON DUPLICATE KEY UPDATE name_{$lang}=VALUES(name_{$lang})
        ";
        $DB->insert($query);
    }
}