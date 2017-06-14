<?php
/**
 * Класс поиска
 * 
 * Названия некоторых методов соответсвуют названиям таблиц, благодаря этому при добавлении и удалении данных 
 * в классах cmsEditAdd, cmsEditDel автоматически формируется поисковый индекс при обновлении таблиц. Для добавления
 * индекса по новой таблице достаточно создать в этом классе новый метод.
 * 
 * @package Pilot
 * @subpackage Search
 * @version 3.0
 * @author Rudenko Ilya
 * @copyright c-format
 */

class Search
{
	/**
	 * Количество записей, которые будут обновляться за один раз, необходимо для того, 
	 * что б не переполнять память
	 */
	const limit = 500;
	
	/**
	 * Обновляет информацию в поисковом индексе
	 *
	 * @param string $table_name
	 * @param int $id
	 * @return mixed
	 */
	public static function update($table_name, $id = 0) 
    {
		// Проверяем, существует ли поисковый индекс по заданной таблице
		if (!method_exists('Search', $table_name)) {
			return false;
		}
		
		// Обновляем поисковый индекс
		return self::$table_name($id);
	}
	
	/**
	 * Удаление данных из поискового индекса
	 *
	 * @param string $table_name
	 * @param mixed $id
	 * @return bool
	 */
	public static function delete($table_name, $id) 
    {
		global $DB;
		
		// Проверяем, существует ли поисковый индекс по заданной таблице
		if (!method_exists('Search', $table_name)) {
			return false;
		}
		
		$query = "SELECT field_id FROM cms_field_static WHERE db_alias='default' AND table_name='$table_name'";
		$fields = $DB->fetch_column($query);
		
		$languages = preg_split("/,/", LANGUAGE_SITE_AVAILABLE, -1, PREG_SPLIT_NO_EMPTY);
		
		$query = "DELETE FROM search_content WHERE language IN ('".implode("','", $languages)."') "
                  . where_clause('id', $id)
                  . where_clause('field_id', $fields);
		$DB->delete($query);
		return true;
	}
	
	
	/**
	 * Полное обновление поискового индекса
	 *
	 */
	public static function reload() 
    {
		global $DB;
		$query = "TRUNCATE TABLE search_content";
		$DB->delete($query);
		
		$methods =  get_class_methods('Search');
		reset($methods);
		while (list(,$method) = each($methods)) {
			if (strstr($method, '_') !== false) {
				//echo "[i] Reload $method\n";
				Search::$method();
			}
		}
	}
	
	/**
	 * Формрование лога поиска по сайту
     * 
	 * @param string $keyword
	 * @param int $amount
	 * @return int $id
	 */
	public static function addToLog($keyword, $amount, $site_id) 
    {
		global $DB;
		
		$query = "
			INSERT INTO search_log 
			SET
				keyword = '".$DB->escape($keyword)."',
				site_id = '".$site_id."',
				amount = '".$amount."'
		";
		return $DB->insert($query);
	}
    
    /**
	 * Поиск по сайту
     * 
	 * @param string $text
	 * @param int $site_id
	 * @param int $page
	 * @return array
	 */
	public static function searchSite($text, $site_id, $limit, $page = 0) 
    {
		global $DB;
		
		$query = "
            SELECT SQL_CALC_FOUND_ROWS
                url,
                title,
                LEFT(content, 500) as content,
                MATCH(title, content) AGAINST ('$text') as rel
            FROM search_content
            WHERE   
                MATCH(title, content) AGAINST ('$text' IN BOOLEAN MODE)
                AND language='".LANGUAGE_CURRENT."'
                ".where_clause('site_id', $site_id)."
            ORDER BY rel DESC "
             . Misc::limit_mysql($limit, $page);        
        $data = $DB->query($query);
        
        if (empty($data)) {
            $query = "
                SELECT SQL_CALC_FOUND_ROWS
                    url,
                    title,
                    LEFT(content, 500) as content
                FROM search_content
                WHERE   
                    (title like '%$text%' OR content like '%$text%')
                    AND language='".LANGUAGE_CURRENT."'
                    ".where_clause('site_id', $site_id)."
                ORDER BY id ASC "
                 . Misc::limit_mysql(SEARCH_COUNT_ROWS, $page);     
            $data = $DB->query($query);
        }
		return $data;
    }
    
	/**
	 * Private методы
	 */
	
	
	/**
	 * Определяет id поля
	 *
	 * @param string $table_name
	 * @param string $field_name
	 * @return int
	 */
	private static function getFieldId($table_name, $field_name) 
    {
		global $DB;
		
		$query = "SELECT id FROM cms_field_static WHERE table_name='$table_name' and full_name='$field_name'";
		return $DB->result($query);
	}
	
	/**
	 * Добавляет новую запись в таблицу
	 *
	 * @param int $id
	 * @param int $field_id
	 * @param string $language
	 * @param int $site_id
	 * @param string $url
	 * @param string $title
	 * @param string $content
	 * @param string $change_dtime
	 * @param decimal $page_priority
	 */
	private static function add($id, $field_id, $language, $site_id, $url, $title, $content, $table_name)
    {
		global $DB;
		
		$content = self::clean($content);// x($content);
		if (empty($content)) {
			$content = $title;
        } 
		
        $title = substr(self::clean($title), 0, 500);
                
		$query = "
			REPLACE INTO search_content SET
				id = $id,
				field_id = $field_id,
				language = '$language',
				site_id = $site_id,
				url = '$url',
				title = '".  addslashes($title)."',
				content = '".addslashes($content)."',
                table_name = '$table_name'    
		";
		$DB->insert($query);
	}
	
	/**
	 * Очищает текст от лишних символов
	 *
	 * @param string $text
	 * @return string
	 */
	private static function clean($text)
    {
		// Удаляем &nbsp; &ndash; &#33;
		$text = preg_replace("/&[a-z#0-9]+;/", '', $text);
        $text = strip_tags($text);
        
        //удаляем формы, инфоблоки, галлереи ...
        $methods = get_class_methods('TemplateUDF');
        $text = preg_replace("/{(" . implode("|", $methods) . ")([^}]*)}/", '', str_replace('&quot;', '"', $text));				
        
		#return trim(mb_ereg_replace("/[\s\n\r\t]+/", ' ', mb_ereg_replace("/[^a-zA-Zа-яА-ЯіїєІЇЄ0-9\s]+/", " ", strip_tags($text))));
        return $text;
	}
	
	/**
	 * Методы для поиска, название метода должно соответсвовать названию таблицы
	 */
		
	/**
	 * Добавляет в индекс новости
	 *
	 * @param string $language
	 */
	private static function news_message($id = 0) 
    {
		global $DB;
		
		if (!is_module('News')) {
			return false;
		}
		
        $News = new News();
                
		$languages = preg_split("/,/", LANGUAGE_SITE_AVAILABLE, -1, PREG_SPLIT_NO_EMPTY);
		reset($languages);
		while (list(,$language) = each($languages)) {
			
			$field_id = self::getFieldId('news_message', "content_$language");
			$start_id = 0;
			do {
				$query = "
					SELECT 
						tb_message.id,
						tb_message.type_id,						
						tb_message.content_$language as content,
						tb_message.uniq_name as url,
						tb_message.name_$language as title
					FROM news_message as tb_message
					INNER JOIN news_type as tb_type on tb_type.id=tb_message.type_id
					WHERE 
						tb_message.active=1 and 
						tb_message.id > $start_id and 
						tb_message.content_$language is not null
						".where_clause("tb_message.id", $id)."
				";
				$data = $DB->query($query);
				reset($data);
				while (list(,$row) = each($data)) {
                    $row['url'] = $News->getUrlType( $row['type_id'], '' ) . $row['url'] . '/';	
					self::add($row['id'], $field_id, $language, 824, $row['url'], $row['title'], $row['content'], 'news_message');
					$start_id = $row['id'];
				}
			} while(!empty($data));
		}
	}
   
    /**
	 * Модификации
	 *
	 * @param string $language
	 */
	private static function auto_modification($id = 0)
    {
		global $DB;
		
        $table_name = __FUNCTION__;
 
        //урл по умолчанию
        $url_default = Site::getPageUrl(1004);
                
		$languages = preg_split("/,/", LANGUAGE_SITE_AVAILABLE, -1, PREG_SPLIT_NO_EMPTY);
        
		reset($languages);
		while (list(,$language) = each($languages)) {			
			$field_id = self::getFieldId($table_name, "content_$language");
			$start_id = 0;
                       
			do {
				$query = "
					SELECT 
						id,
						content_$language as content,                                                    
                        CONCAT('$url_default', uniq_name, '/') as url,
						name as title
					FROM `$table_name`		
					WHERE  active=1 and id > $start_id " . where_clause("id", $id) . "
				";
                $data = $DB->query($query);
                
				reset($data);
				while (list(,$row) = each($data)) {                                               
					self::add($row['id'], $field_id, $language, 824, $row['url'], $row['title'], $row['content'], $table_name);
					$start_id = $row['id'];
				}
			} while(!empty($data));
		}
	}
    
    /**
	 * Товары
	 *
	 * @param string $language
	 */
	private static function shop_product($id = 0)
    {
		global $DB;
		
        $table_name = __FUNCTION__;
 
        //урл по умолчанию
        $url_default = Site::getPageUrl(1006);
                
		$languages = preg_split("/,/", LANGUAGE_SITE_AVAILABLE, -1, PREG_SPLIT_NO_EMPTY);
        
		reset($languages);
		while (list(,$language) = each($languages)) {			
			$field_id = self::getFieldId($table_name, "name_$language");
			$start_id = 0;
                       
			do {
				$query = "
					SELECT 
						tb_product.id,
						'' as content,                                                    
                        IF (
                            tb_product.is_accessory = '1',
                            CONCAT('/', tb_group.url, '/', tb_product.uniq_name, '/'),                                
                            CONCAT('$url_default', tb_product.uniq_name, '/')
                        ) as url,
						tb_product.name_$language as title
					FROM `shop_product` as tb_product
                    INNER JOIN shop_group as tb_group on tb_group.id=tb_product.group_id
					WHERE tb_product.active=1 and tb_product.id > $start_id " . where_clause("tb_product.id", $id) . "
				";
                $data = $DB->query($query);
                
				reset($data);
				while (list(,$row) = each($data)) {                     
                    $row['content'] = $DB->result("SELECT value_text_$language FROM shop_product_value"
                            . " WHERE product_id = '{$row['id']}' AND param_id in (5, 22)");
                    
					self::add($row['id'], $field_id, $language, 824, $row['url'], $row['title'], $row['content'], $table_name);
					$start_id = $row['id'];
				}
			} while(!empty($data));
		}
	}
    
    /**
	 * Категории
	 *
	 * @param string $language
	 */
	private static function shop_group($id = 0)
    {
		global $DB;
		
        $table_name = __FUNCTION__;
                 
		$languages = preg_split("/,/", LANGUAGE_SITE_AVAILABLE, -1, PREG_SPLIT_NO_EMPTY);
        
		reset($languages);
		while (list(,$language) = each($languages)) {			
			$field_id = self::getFieldId($table_name, "name_$language");
			$start_id = 0;
                       
			do {
				$query = "
					SELECT 
						id,
						'' as content,                                                    
                        CONCAT('/', url, '/') as url,
						name_$language as title
					FROM `$table_name`                  
					WHERE active=1 and id <> 1 and id > $start_id " . where_clause("id", $id) . "
				";
                $data = $DB->query($query);
                
				reset($data);
				while (list(,$row) = each($data)) {                                               
					self::add($row['id'], $field_id, $language, 824, $row['url'], $row['title'], $row['content'], $table_name);
					$start_id = $row['id'];
				}
			} while(!empty($data));
		}
	}
    
	/**
	 * Добавляет в индекс контент сайта
	 *
	 * @param string $language
	 */
	private static function site_structure($id = 0)
    {
		global $DB;
		
		$id = 0;
		$languages = preg_split("/,/", LANGUAGE_SITE_AVAILABLE, -1, PREG_SPLIT_NO_EMPTY);
		reset($languages);
		while (list(,$language) = each($languages)) {
			
			$field_id = self::getFieldId('site_structure', "content_$language");
			
			$query = "SELECT url, id FROM site_structure_site WHERE url is not null";
			$site = $DB->fetch_column($query);
			
			$start_id = 0;
			do {
				$query = "
					select
						id,
						content_$language as content,
						url,
						name_$language as title
					FROM site_structure
					WHERE 
						active=1 and id <> '824' and
						id > $start_id
						".where_clause("id", $id)."
				";
				$data = $DB->query($query);
				reset($data);
				while (list(,$row) = each($data)) {                                           
					$host = (strpos($row['url'], '/') > 0) ? substr($row['url'], 0, strpos($row['url'], '/')): $row['url'];
					if (!isset($site[$host])) continue;
                    
					$site_id = $site[$host];
					self::add($row['id'], $field_id, $language, $site_id, substr($row['url'], strpos($row['url'], '/')).'/', $row['title'], $row['content'], 'site_structure');
					$start_id = $row['id'];
				}
			} while(!empty($data));
		}
	}
    
}

?>