<?php
/**
 * Класс обработки новостей
 * 
 * @package DeltaCMS
 * @subpackage News
 * @version 3.0
 * @author Naumenko A.
 * @copyright c-format, 2014
 */

class News 
{
	
	/**
	 * Количество записей
	 * @var int
	 */
	public $total = 0;
	
    /*
     * Ссылки категорий
     * @var array 
     */
    public $path = array();
                
	/**
	 * Информация о рубрике
	 *
	 * @param string $type or int $type
	 * @return array
	 */
	public function getType($type)
    {
		global $DB;
        
        $type_id = 0;
        if (is_numeric($type)) {
            $type_id = $type;
            $type = ''; 
		}
        
		$query = "
			select 
				tb_type.id,				
				tb_type.type_id,				
				tb_type.uniq_name,
				tb_type.image,
				tb_type.name_".LANGUAGE_CURRENT." AS name
			FROM news_type AS tb_type 			
			WHERE 1 " . where_clause('tb_type.uniq_name', $type)."
                    " . where_clause('tb_type.id', $type_id)."  
			LIMIT 1
		";               
		$info = $DB->query_row($query);
        
		return $this->parseType($info);
	}
	
	/**
	 * Возвращает полный перечень дочерных групп
	 * 
	 * @param int $type_id	
     * @param boolean $recursive - выводить родительские категории если нет дочерних          
     * @param boolean $show_parent - выводить родительскую категорию
	 * @return array
	 */
	public function getChildTypes($type_id, $recursive = false, $show_parent = false) 
    {
		global $DB;
		
		$query = "
			SELECT
				tb_type.id,				
				tb_type.type_id,				
				tb_type.uniq_name,
				tb_type.name_".LANGUAGE_CURRENT." AS name						
			FROM news_type AS tb_type 			
            INNER JOIN news_type_relation as tb_relation on tb_relation.parent=tb_type.id
            INNER JOIN news_message as tb_message on tb_message.type_id=tb_relation.id AND tb_message.active=1
			WHERE tb_type.active='1' ".where_clause('tb_type.type_id', $type_id)."  
            GROUP BY tb_type.id    
            ORDER BY tb_type.priority            
		";        
		$data = $DB->query($query, 'uniq_name');
        
        if (empty($data) && $recursive) {
            //выбор родительских групп если нет дочерных
            $query = "
                    SELECT 
                            tb_type.id,	
                            tb_type.type_id,				
                            tb_type.uniq_name,
                            tb_type.name_".LANGUAGE_CURRENT." AS name						
                    FROM news_type AS tb_parent 			
                    INNER JOIN news_type AS tb_type ON tb_type.type_id=tb_parent.type_id                             
                    INNER JOIN news_type_relation as tb_relation on tb_relation.id=tb_type.id
                    INNER JOIN news_message as tb_message on tb_message.type_id=tb_relation.parent AND tb_message.active=1
                    WHERE tb_type.active='1' AND tb_type.type_id > 202                    
                            " . where_clause('tb_parent.id', $type_id) . "  
                    GROUP BY tb_type.id ORDER BY tb_type.priority            
            ";   
            $data = $DB->query($query, 'uniq_name');                                        
        }
        if ($show_parent) {
            //добавление в начало ссылки на родительскую категорию
            reset($data);
            list(, $parent) = each($data);                    
            while (isset($parent['type_id']) && !empty($parent['type_id'])) {
               $parent = $this->getType($parent['type_id']);
               
               $parent['name'] = cmsMessage::get("MSG_SITE_ALL_NEWS");
               $parent['class'] = 'active';
               array_unshift($data, $parent);
               break;
            }
        }
                    
		reset($data);
		while (list($index, $row) = each($data)) {
			$data[$index] = $this->parseType($row);
		}
		return $data;
	}
        
	/**
	 * Обрабатывает вывод рубрик
	 *
	 * @param array $row
	 * @return array
	 */
	private function parseType($row)
    {
		if (empty($row)) {
            return array();
        }
		$row['url'] = $this->getUrlType( $row['id'] );
		return $row;
	}
	
    /**
     * Получение заголовков новостей прикрепленных к определенному разделу каталога
     * @param int $limit - количество новостей на страницу
     * @param mixed (string or int) $type - уникальное имя рубрики
     * @param int $group_id - раздел
     * @return array
     */
    public function getHeadlinesByGroup($limit, $type, $group_id)
    {        
        $where = " AND tb_message.id in (SELECT message_id FROM news_shop_group_relation WHERE group_id = $group_id) ";
        $data = $this->getHeadlines($limit, $type, true, null, null, $where);
        
        return $data;
    }
    
	/**
	 * Получение заголовков новостей
	 *
	 * @param int $limit - количество новостей на страницу
	 * @param $type_id - ID рубрики
	 * @param bool $recursive - показывать вложенные новости
	 * @param bool $order - сотрировка, если не указано, выводится как задано к разделу
	 * @param int $page_start - с которой странице начинать выборку
	 * @param string $where - дополнительное условие отбора
	 * @return array
	 */
    public function getHeadlines($limit, $type_id, $recursive = true, $order = null, $page_start = null, $where = "") 
    {
		
            global $DB;
                
        if (empty($type_id)) {
            return array();
        }
                  
            $date_field = 'date';  
            $where .= ' AND (tb_message.date_to is null OR tb_message.date_to > NOW())';
       
            //для главной
            $join = " INNER JOIN news_type_relation AS tb_relation ON tb_relation.id = tb_message.type_id and tb_relation.priority=3    
                      INNER JOIN news_type AS tb_type ON tb_type.id = tb_relation.parent ";
        
        
		if ($recursive) {
			$query = "
				SELECT tb_relation.id
				FROM news_type AS tb_type
                INNER JOIN news_type_relation AS tb_relation ON tb_relation.parent=tb_type.id
				WHERE 1 "
                    . where_clause("tb_type.id", $type_id);
			$type_id = $DB->fetch_column($query);
		}		
        
        if (is_null($order)) {
            $order = $DB->result("SELECT IF(`sortby` = 'date', 'date desc', `sortby`) as sortby FROM news_type WHERE 1 " . where_clause('id', $type_id)); 
            if (empty($order)) {
                $order = 'priority';
            }
            $order = "tb_message." . $order;
        }
        
        $order = "tb_message.popular DESC, " . $order;
        
		$query = "
			SELECT SQL_CALC_FOUND_ROWS				
				tb_message.id,                
				tb_message.name_".LANGUAGE_CURRENT." AS headline, 
				tb_message.announcement_".LANGUAGE_CURRENT." AS announcement,
                tb_message.image,
                tb_message.views,

                LOWER(tb_message.uniq_name) AS url,   
                CASE MONTH(tb_message.$date_field) ".LANGUAGE_MONTH_GEN_SQL." END AS month_txt,
                DATE_FORMAT(tb_message.$date_field, '%e') AS day_txt,
                DATE_FORMAT(tb_message.$date_field, '%Y') AS year_txt,
                
                tb_type.show_time,
                tb_type.id AS type_id,
                tb_type.uniq_name AS type_uniq,
                tb_type.name_".LANGUAGE_CURRENT." as type
			FROM news_message AS tb_message 
            $join    
			WHERE tb_message.date < NOW()
				AND tb_message.active='1' AND tb_type.active='1' $where				
				" . where_clause('tb_type.id', $type_id) . "  
			ORDER BY $order 
			".Misc::limit_mysql($limit, $page_start)."
		";     
		$data = $DB->query($query);		
		$this->total = $DB->result( "SELECT FOUND_ROWS()" );
		
		return $this->parseHeaders($data);
	}
    
	/**
	 * Обработка заголовков
	 *
	 * @param array $data
	 * @return array
	 */
	public function parseHeaders($data) 
    {	
		reset($data);
		while (list($index, $row) = each($data)) {					                    
            $data[$index]['image'] = Uploads::getIsFile('news_message', 'image', $row['id'], $row['image'], '');
            // Определяем путь к новости
            $data[$index]['type_url'] = $this->getUrlType($row['type_id']);
            $data[$index]['url'] = $data[$index]['type_url'] . $data[$index]['url'].'/';	
                       
            if (isset($row['show_time']) && $row['show_time']) {
                $row['day_txt'] = str_pad($row['day_txt'], 2, 0, STR_PAD_LEFT);
                $data[$index]['date_txt'] = $row['day_txt'] .' ' .$row['month_txt'] . ' '.$row['year_txt'];                
            }
            
           // $data[$index]['index'] = $index + 1;
		}
        
		return $data;
	}
	
    /**
     * Создает обрезанное изображение для главной страницы (нижний правый угол)
     *  и возращает ссылку на изображение
     * 
     * @global DB $DB
     * @param string $file
     * @param string $parser
     * @return string
     */
    public function cropImage($file, $parser) 
    {
        global $DB;
                
        $filename = str_replace('/uploads/', 'i/news/', $file);
        
        if (file_exists(SITE_ROOT . $filename)) {
            return '/'. $filename;
        }
        
        $query = "select * from cms_image_size where uniq_name='$parser'";
        $info = $DB->query_row($query);

        $file = SITE_ROOT . trim($file, '/');
        $img = getimagesize($file);
        if ($img[0] > $info['width'] || $img[1] > $info['height']){  
            try {
                Image::createDummy($info['width'], $info['height'], SITE_ROOT . $filename);

                $image = new Imagick($file);
                $image->cropImage($info['width'], $info['height'], $img[0] - $info['width'], $img[1] - $info['height']);
                $image->stripImage();
                $image->writeImage(SITE_ROOT . $filename);
                $image->clear();
                $image->destroy();   
            }
            catch ( Exception $e ){
            }
        }
      
        return '/'. $filename;
    }
	/**
	* Получаем полный путь категории новости
	* @param int $type_id - група текущей новости	
	* @return string $url
	*/
	public function getUrlType($type_id, $lang = null)
    {
		global $DB;		
		
        if (isset($this->path[ $type_id ])) {
            return $this->path[ $type_id ];
        }

        if (is_null($lang)) {
            $lang = LANGUAGE_URL;
        }
                
		$url = '/';                
        $query = "SELECT
                    tb_relation.parent AS id, tb_type.uniq_name, CONCAT( tb_site.url, '/') AS url
                FROM news_type_relation AS tb_relation 
                INNER JOIN news_type AS tb_type ON tb_relation.parent=tb_type.id
                LEFT JOIN site_structure_item AS tb_item ON tb_item.object_id=tb_relation.parent AND tb_item.table_name='news_type'
                LEFT JOIN site_structure AS tb_site ON tb_site.item_id=tb_item.id
                WHERE 1 " . where_clause("tb_relation.id", $type_id) . " 
                ORDER BY tb_relation.priority";
		$types = $DB->query($query, 'id');
        
        reset($types);
        while (list(, $row) = each($types)) {
            if (is_null($row['url'])) {
                $url .= $row['uniq_name'] . "/";
            } else {
                $url = '/'.$lang.substr($row['url'], strpos($row['url'], '/', 1) + 1);   
            }
            $this->path[ $row['id'] ] = $url;
        }
        
        return $this->path[ $type_id ];
	}

    /**
	 * Получаем новость. Новость должна быть опубликована в группе,
	 * принадлежащей текущему сайту
	 *
	 * @param int $id
     * @return array
	 */
	public function getMessage($id) 
    {
		global $DB;
                
		// Пустое значение передаётся на всех страницах вывода информации, поэтому делаем проверку без SQL запроса
		// сделано это для того, что б не делать двойную проверку на наличие текста новости и наличия empty(id)
		if ( empty($id) ) return array();
           
        $where = (Auth::isAdmin()) ? " OR tb_message.active_admin = 1 " : '';
         
		$query = "
			SELECT 
				tb_message.id, 
				tb_message.name_".LANGUAGE_CURRENT." AS headline, 
                tb_message.title_".LANGUAGE_CURRENT." AS title,
				tb_message.content_".LANGUAGE_CURRENT." AS content,
				tb_message.announcement_".LANGUAGE_CURRENT." AS announcement,
                tb_message.img_content, 
                tb_message.views,
                tb_message.likes_up,
                
                tb_message.date as date_fororder,                    
				DATE_FORMAT(tb_message.date, '".LANGUAGE_DATE_SQL."') AS date,				
				CASE MONTH(tb_message.date) ".LANGUAGE_MONTH_GEN_SQL." END AS month_from,
				DATE_FORMAT(tb_message.date, '%e') AS day_from,
				DATE_FORMAT(tb_message.date, '%Y') AS year_from,
                tb_message.dtime AS dbformat_date,

                tb_message.gallery_group_id AS gallery_id,
                tb_message.video_id,
                                  
				tb_type.uniq_name AS type_uniq,
				tb_type.name_".LANGUAGE_CURRENT." AS type_name,				
				tb_message.type_id
			FROM news_message AS tb_message
            INNER JOIN news_type AS tb_type ON tb_type.id=tb_message.type_id
			WHERE (tb_message.active='1' $where) ".where_clause('tb_message.id', $id) . "
			GROUP BY tb_message.id
		";
		$data = $DB->query_row($query);
		if ($DB->rows == 0) {			
			return array();
		}
        
        $DB->update("UPDATE news_message SET `views` = `views` + 1 WHERE id='{$id}' ");
		
        $data['img_content'] = Uploads::getIsFile('news_message', 'img_content', $data['id'], $data['img_content'], '');
		// Текст новости		
		$data['content'] = id2url($data['content']);                
        if (!empty($data['content'])) {
            $methods = get_class_methods('TemplateUDF');
            $data['content'] = preg_replace_callback("/{(" . implode("|", $methods) . ")([^}]*)}/", array('Template', 'staticContentCallback'), str_replace('&quot;', '"', $data['content']));				
        }
		     
        //прикреплена галерея к новости
		if ($data['gallery_id'] != 0) {			
			$data['content'] .= "<div class='conContent'>" . TemplateUDF::gallery(array('name' => $data['gallery_id'])) . "</div>";
		}
        
        //прикреплено видео
		if ($data['video_id'] != 0) {
            $data['content'] .= "<div class='conContent'>" . TemplateUDF::video(array('name' => $data['video_id'])) . "</div>";  			
		}
        
		return $data;
	}
	
    /**
	 * Получаем предыдущую или последующую от текущей новость в зависимости от значения параметр $side = 0|1. 
	 * Новость должна быть опубликована в группе, принадлежащей текущему сайту.
	 *
	 * @param int $id
	 * @param int $type_id
     * @param int $limit
	 * @param string $date - if false  select all message by type
	 * @param int $side
	 * @return array
	 */
	public function getNearbyMessages($id, $type_id, $limit=null, $date = false, $side=0)
    {
		global $DB;
				
		// Пустое значение передаётся на всех страницах вывода информации, поэтому делаем проверку без SQL запроса
		// сделано это для того, что б не делать двойную проверку на наличие текста новости и наличия empty(id)
		if (empty($id)) {
			return array();
		}
		
		$order = ($side == 0) ? "DESC" : "ASC";
		$direction = ($side == 0) ? "<" : ">";
		
        $where = ( $date ) ? " AND tb_message.date $direction '$date' " : "";
        
		$query = "  
			SELECT 
                    tb_message.id, 
                    tb_message.name_".LANGUAGE_CURRENT." AS headline,
                    tb_message.type_id,
                    tb_message.img_content,
                    tb_message.uniq_name as url,
                    DATE_FORMAT(tb_message.date, '".LANGUAGE_DATE_SQL."') AS date,				
                    CASE MONTH(tb_message.date) ".LANGUAGE_MONTH_GEN_SQL." END AS month_from,
                    DATE_FORMAT(tb_message.date, '%e') AS day_from,
                    DATE_FORMAT(tb_message.date, '%Y') AS year_from
             FROM news_message AS tb_message
			WHERE tb_message.date <= NOW() 
              AND tb_message.id != '$id' $where               
              AND tb_message.type_id='$type_id' 
              AND tb_message.active='1'
		 	ORDER BY tb_message.date $order, tb_message.priority $order, tb_message.dtime $order
			LIMIT $limit
		";          
        
		$data = $DB->query($query);				
		reset($data);
		while (list($index, $row) = each($data)) {
            $data[$index]['img_content'] = Uploads::getIsFile('news_message', 'img_content', $row['id'], $row['img_content'], '');
            $data[$index]['url'] = $this->getUrlType( $row['type_id'] ) . $data[$index]['url'];			
		}
		return $data;
	}
    
}
	
?>