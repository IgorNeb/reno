<?php
/**
 * Работа с языковими константами. Константы сохраняются /language/{$lang}/
 * Формат файлов 
 *  - название msg_{$module}.{$lang}.ini
 *  - переменные MSG_{$module}_%s="%s"
 * 
 *  - 3.0 в методе get не нужно указывать первым аргументом название модуля
 * 
 * @package DeltaCMS
 * @subpackage CMS
 * @version 3.0
 * @author Naumenko A.
 * @copyright (c) 2014, c-format
 */

class cmsMessage 
{
    /**
    * Массив языковых констант
    * @var array constants[][]
    */
    protected static $constants = array();
    
    /**
     * Возвращает значение, если оно существует в файле     
     * 
     * @param string $vars переменная
     * @return string значение
     */
    public static function get($vars, $params = '')
    {
        $vars = strtoupper($vars);
        $matches = explode("_", $vars);
        if (count($matches) < 3) { 
            return $vars; 
        }  
        
        $module = $matches[1];
        if (!isset(self::$constants[$module])) {
            self::loadFileModule($module);
        }
        
        if (isset(self::$constants[$module]) && isset(self::$constants[$module][$vars])) {
            return sprintf(self::$constants[$module][$vars], $params);                            
        } else {
             global $DB;  
             $module = strtolower($module);
             $query = "
                INSERT IGNORE INTO `cms_language_message` (`uniq_name`, `module`, `name_".LANGUAGE_CURRENT."`)
                VALUES ('$vars', '$module', '')                
            ";
            $DB->insert($query);
            
            if (rand(0,100) > 80) {
                 self::saveDataToFile();
            }
        }
        
        return $vars;
    }
    
     /**
     * Быстрое обновление константы в базе, файл нужно пересохранить
     * 
     * @param string $module название модуля
     * @param string $vars переменная
     * @param string $string значение
     * @return void
     */
    public static function set($module, $vars, $string)
    {
        global $DB;  
      
        $module = strtoupper($module);
        if (!isset(self::$constants[$module])){
            self::loadFileModule($module);
        }  
        
        $query = "
                INSERT INTO cms_language_message (`uniq_name`, `module`, `name_".LANGUAGE_CURRENT."`)
                VALUES ('$vars', '".strtolower($module)."', '$string') 
                ON DUPLICATE KEY UPDATE `name_".LANGUAGE_CURRENT."`=VALUES(`name_".LANGUAGE_CURRENT."`)               
        ";
        $DB->insert($query);        
        self::$constants[$module][$vars] = $string;    
        
        if (rand(0,100) > 90) {
            self::saveDataToFile();
        }
    }
    
    
    /**
     * Пересохранение кеша. Запис из базы в файлы
     * 
     * @param array $modules - название модуля
     * @return void;
     */   
    public static function saveDataToFile($modules = array())
    {
        global $DB;
        
        $name_langs = $DB->fetch_column("select full_name from cms_field_static where table_id='2816' and field_name='name'");            
           
        $languages = getLanguageList();
        
        foreach ($languages as $key => $lang) {
            $dir = SITE_ROOT . "language/".$lang.'/';
            if (empty($modules)) {
                Filesystem::delDir($dir);            
            }
            if (!in_array('name_'.$lang, $name_langs)) {
                unset($languages[$key]);
            }
        }
        //x($languages);die();
        
        if (empty($modules)) {
            $modules = $DB->fetch_column("SELECT module FROM cms_language_message GROUP BY module ");
        }
       
        foreach ($languages as $lang) {            
            $dir = SITE_ROOT . "language/".$lang.'/';
            
            foreach ($modules as $module) {
                $stat = array();
                $messages = $DB->fetch_column(""
                        . "SELECT `uniq_name`, `name_" . $lang . "` as name "
                        . "  FROM `cms_language_message`"
                        . " WHERE `module` = '$module' ORDER BY name_" . $lang);
                
                foreach ($messages as $param=>$value) {
                    $stat[] = $param . "=\"" . $value . "\"";         
                }

                $filename = $dir . "msg_".$module.".".$lang.".ini";
                if (!file_exists($filename) || is_writable($filename)) {
                    Filesystem::touch($filename);
                }
                // Сохраняем файл
                $fp = fopen($filename, 'w');
                flock($fp, LOCK_EX);
                fwrite($fp, implode("\n", $stat));
                flock($fp, LOCK_UN);
                fclose($fp);
            }   
        }
        
    }
    
    /**
     * Загружает языковый файл модуля
     * 
     * @param string $module название модуля
     * @return void
     */
    protected static function loadFileModule($module) 
    {        
        $filename = self::getFileName($module);        
        if ($filename) {
            $strings = @parse_ini_file($filename);
        }
        if (!isset($strings)) {
            $strings = array();
        }
        self::$constants[$module] = $strings;
    }
    
    /**
     * Возвращает название файла
     * 
     * @param string $module название модуля
     * @return string or false 
     */
    protected static function getFileName($module) 
    {
        $filename = LANGUAGE_ROOT . "msg_" . strtolower($module) . "." . LANGUAGE_CURRENT . ".ini";        
        if (file_exists($filename)) {
            return $filename;
        }
        return false;
    }
    
}

