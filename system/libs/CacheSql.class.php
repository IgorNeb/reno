<?php
/** 
 * Класс, отвечающий за кеширование данных с базы
 * 
 * @package DeltaCMS
 * @subpackage CMS 
 * @version 3.0
 * @author Naumenko A.
 * @copyright c-format
 */ 

class CacheSql 
{
    /*
     * Таблица
     */
    private $table_name = '';
    
    /*
     * Файл кеша
     */
    private $file_cache = '';
    
    /*
     * Директория кеша
     */
    private $cache_path =  '';
    
    /**
	 * Конструктор класса
	 *
	 * @param string $table_name
	 * @param string $file_cache
	 */
	public function __construct($table_name, $file_cache = '', $is_lang = true) 
    {
		$this->table_name = $table_name;
        
        $this->cache_path = CACHE_ROOT . 'query/';
        
        if (empty($file_cache)) {
            $file_cache = $table_name;
        }
        
		$name = $file_cache . (($is_lang) ? '_'.LANGUAGE_CURRENT : '') . '.txt';
		$this->file_cache = $this->cache_path . $this->table_name .'/'. $name;
	}
    
    /**
     * Считывание с файла результата запроса
     * 
     * @return mixed(text, null) $data
     */
    public function read()
    {
        if (file_exists($this->file_cache)) {
            $handle   = fopen($this->file_cache, 'rb');
            $variable = fread($handle, filesize($this->file_cache));    
            fclose($handle);
            return @unserialize($variable);    
        } else {
            return null;
        }
    }
        
    /**
     * Считывание с файла результата запроса. Если кешу не больше полчаса         
     * 
     * @return mixed(text, null) $data
     */
    public function read_by_time() 
    {
        $dtime = strtotime("now");            
        if (file_exists($this->file_cache) && ( $dtime - filemtime($this->file_cache) ) > 1800) {
            //кеш живет полчаса
            @unlink($this->file_cache); 
        }
        
        return $this->read();            
    }
        
    /**
     * Записывание в файл результата запроса
     *
     * @param array $data         
     */
    public function write($data) 
    {
       if (!is_dir(dirname($this->file_cache))) {
           mkdir(dirname($this->file_cache), 0777, true);
       }   
       $handle = fopen($this->file_cache, 'w');
       fwrite($handle, serialize($data));
       fclose($handle);
    }
    
    /**
     * Удаление файла кеша. Если пустое значение $file, удаляем все файлы каталога
     * 
     * @param string $table_name
     * @param string $file
     */
    public function delete($file = '') 
    {
        if (!empty($file)) {
            $file = $this->cache_path . $this->table_name . '/' . $file;
            if (file_exists($file)) { 
                 @unlink($file); 
            }    
        } else {
            $dir_file = $this->cache_path . $this->table_name . '/';
            if (file_exists($dir_file) && $handle = opendir($dir_file)) {
                while (false !== ($file = readdir($handle))) {        
                    if (is_file($dir_file.$file) && $file != "." && $file != "..") { 
                        @unlink($dir_file.$file);  
                    } 
                }
                closedir($handle); 
            }   
        }
    }
    
    /**
     * Быстрое удаление всех файлов модуля магазин
     */
    public static function deleteShop() 
    {
        $dir_names = array('shop_group', 'shop_brands', 'shop_info_data', 'shop_stock', 'shop_present');
        
        $cache_path = CACHE_ROOT . 'query/';
        
        foreach($dir_names as $dir) {
            $dir_file = $cache_path . $dir . '/';
            if (file_exists($dir_file) && $handle = opendir($dir_file)) {
                while (false !== ($file = readdir($handle))) {        
                    if (is_file($dir_file.$file) && $file != "." && $file != "..") { 
                        @unlink($dir_file.$file);  
                    } 
                }
                closedir($handle); 
            }   
        }
    }
    
}
?>