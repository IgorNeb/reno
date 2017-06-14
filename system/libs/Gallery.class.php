<?php
/**
 * Вывод фотогалереи
 * 
 * @package DeltaCMS
 * @subpackage Gallery 
 * @version 3.0
 * @author Naumenko A.
 * @copyright c-format, ltd. 2016
 */
class Gallery
{
	
	/**
	 * Таблица, которая содержит информацию о группах
	 *
	 * @var string
	 */
	public $group_table = '';
	
	/**
	 * id текущей группы
	 *
	 * @var int
	 */
	public $group_id = 0;
	
	/**
	 * Количество фотографий в группе
	 *
	 * @var int
	 */
	public $total = 0;
	
	/**
	 * Информация о таблице, в которой содержится описание групп
	 *
	 * @var array
	 */
	public $table_info = array();
        
    /**
     * Путь к разделу
     * 
     * @var type 
     */
    public $path = array();
	
	/**
	 * Конструктор класса
	 *
	 * @param string $group_table
	 * @param int $group_id
	 */
	public function __construct($group_table, $group_id) 
    {
		global $DB;
		
		$this->group_table = $group_table;
		$this->group_id = $group_id;
		
		// Определяем название родительского поля
		$this->table_info = cmsTable::getInfoByAlias($DB->db_alias, $group_table);
	}
		
	/**
	 * Информация о текущем разделе галереи и путь к нему
	 * 
	 * @return array
	 */
	public function getPath() 
    {
		global $DB;
        
		$query = "
			SELECT 
				tb_group.name_".LANGUAGE_CURRENT." AS name,
				tb_group.url
			FROM {$this->group_table}_relation  AS tb_relation
			INNER JOIN {$this->group_table} AS tb_group ON tb_relation.parent = tb_group.id
			WHERE tb_relation.id = '$this->group_id'
			ORDER BY tb_relation.priority
		";
		return $DB->query($query);
	}
     
    /**
     * Получаем полный путь категории прикрепленный к разделу
     *      
     * @param int $group_id
     * @return string 
     */
	public function getUrlGroup($group_id)
    {
        global $DB;		

        if (isset($this->path[$group_id])) {
            return $this->path[$group_id];
        }

        $url = '/' . LANGUAGE_URL;                
        $query = "SELECT
                        tb_relation.parent AS id, tb_group.uniq_name, CONCAT( tb_site.url, '/') AS url
                      FROM `{$this->group_table}_relation` AS tb_relation 
                INNER JOIN `{$this->group_table}` AS tb_group ON tb_relation.parent=tb_group.id
                 LEFT JOIN site_structure_item AS tb_item ON tb_item.object_id=tb_relation.parent AND tb_item.table_name='{$this->group_table}'
                 LEFT JOIN site_structure AS tb_site ON tb_site.item_id=tb_item.id
                     WHERE tb_relation.id ='$group_id' 
                  ORDER BY tb_relation.priority";                        
        $data = $DB->query($query, 'id');

        reset($data);
        while (list(, $row) = each($data)) {
            if (is_null($row['url'])) {
                $url .= $row['uniq_name'] . "/";
            } else {
                $url = '/' . LANGUAGE_URL . substr($row['url'], strpos($row['url'], '/', 1) + 1);   
            }
            $this->path[$row['id']] = $url;
        }
        
        return $this->path[$group_id];
	}
	
	/**
     * Информация о разделе
     * 
     * @global type $DB
     * @param int $group_id
     * @return array
     */
	public function getGroupInfo($group_id = 0)
    {
		global $DB;
		
		$group_id = (empty($group_id)) ? $this->group_id : $group_id;
		$query = "
			SELECT *,
				name_".LANGUAGE_CURRENT." AS name
			FROM `$this->group_table`
			WHERE id='$group_id'
		";
		$info = $DB->query_row($query);
		
		return $info;
	}
	
	/**
	 * Список групп
	 * 
	 * @param int $parent_id
	 * @param int $limit
	 * @return array
	 */
	public function getGroups($parent_id = -1, $limit = 100)
    {
		global $DB;
		
		$parent_id = ($parent_id == -1) ? $this->group_id : $parent_id;
		
		$query = "
			SELECT id, name_".LANGUAGE_CURRENT." AS name, photo, uniq_name
			  FROM `$this->group_table`
			 WHERE `{$this->table_info['parent_field_name']}`='$parent_id'
		  ORDER BY priority ASC
             LIMIT $limit
		";
		$data = $DB->query($query);
                
		reset($data);
		while (list($index, $row)=each($data)) {
            $data[$index]['photo'] = Uploads::getIsFile($this->group_table, 'photo', $row['id'], $row['photo'], '');			
            $data[$index]['index'] = $index;
            $data[$index]['url']   = $this->getUrlGroup($row['id']);			
		}
		return $data;
	}
	
	/**
	 * Фотографии
	 * 
	 * @param int $per_page - количество на страницу
	 * @param int $offset - с какой фотографии по порядку начинать вывод
	 * @param int $user_panel - для сайта выводить только активные изображения
	 */
	public function getPhotos($per_page, $offset, $user_panel = 1)
    {
		global $DB;
                
		$return = array();
		$query = "
			SELECT SQL_CALC_FOUND_ROWS *, description_".LANGUAGE_CURRENT." AS title
			  FROM gallery_photo
			 WHERE group_id = '$this->group_id'
               AND group_table_name = '{$this->group_table}' " . where_clause('active', $user_panel)."
		  ORDER BY priority ASC
			".Misc::limit_mysql($per_page, $offset)."
		";     
		$photos = $DB->query($query);        
		$this->total = $DB->result("SELECT FOUND_ROWS()");        
		reset($photos);
		while (list($index, $row) = each($photos)) {            
			$row['photo'] = Uploads::getIsFile('gallery_photo', 'photo', $row['id'], $row['photo'], '');
            if (!empty($row['photo'])) {
                $row['index'] = $index + 1;
                $return[] = $row;
            }
		}
		
		return $return;
	}
        
    /**
	 * Фотографии	 
	 * @param mixed (int | array) $group_id
	 * @param int $per_page - количество на страницу
	 * @param int $offset - с какой фотографии по порядку начинать вывод
	 */
	public function getServicePhotos($per_page, $offset = 0)
    {
		global $DB;
             
        $index = 1;
		$return = array();
        
		$query = "
			SELECT SQL_CALC_FOUND_ROWS *, description_".LANGUAGE_CURRENT." AS title
			  FROM gallery_photo
			 WHERE active='1' AND photo <> ''
               AND group_table_name = '{$this->group_table}' " . where_clause('group_id', $this->group_id)."
		  ORDER BY id DESC
			" . Misc::limit_mysql($per_page, $offset)."
		";              
		$photos = $DB->query($query);     
        
		$this->total = $DB->result("SELECT FOUND_ROWS()");        
		
		foreach ($photos as $i => $row) {
			$row['photo'] = Uploads::getIsFile('gallery_photo', 'photo', $row['id'], $row['photo'], '');
            
            if (!empty($row['photo'])) {
                $row['index'] = $index++;
                $return[] = $row;
            }
		}
		
		return $return;
	}
    
    /**
     * Видео файлы 
     * 
     * @global DB $DB
     * @param bool $recursive все видеофайлы, й дочерные
     * @param string $where условие отбора
     * @param int $page_start
     * @param int $limit
     * @return array
     */
	public function getVideos($recursive = true, $where = '', $page_start = 0, $limit = 9)
    {
		global $DB;
                
        if ($recursive) {
			$query = "
				SELECT tb_relation.id
                  FROM `{$this->group_table}_relation` AS tb_relation
				 WHERE tb_relation.parent = '{$this->group_id}' ";
			$group_id = $DB->fetch_column($query);		
		} else {
            $group_id = $this->group_id;
        }
		        
		$query = "
                SELECT SQL_CALC_FOUND_ROWS
                        *,
                        name_".LANGUAGE_CURRENT." AS name,                        
                        content_".LANGUAGE_CURRENT." as content,
                        announcement_".LANGUAGE_CURRENT." as description,
                        DATE_FORMAT(date, '%d.%m.%Y') as dateto
                FROM gallery_video
                WHERE group_table_name = '$this->group_table' 
                    ". where_clause('group_id', $group_id)."  
                    ". where_clause('active', 1). $where ."
                ORDER BY date DESC, priority 
               ".Misc::limit_mysql($limit, $page_start )."
		";
		$data = $DB->query($query);
        
		$this->total = $DB->result("SELECT FOUND_ROWS()");
		reset($data);
		while (list($index, $row) = each($data)) {			
			$data[$index]['img']   = Uploads::getIsFile('gallery_video', 'img', $row['id'], $row['img'], '');
			$data[$index]['index'] = $index;
            $data[$index]['url']   = $this->getUrlGroup( $row['group_id'] ) . $row['uniq_name'] .'/';                        
		}
        
		return $data;
	}
        
	/**
	 * Видео файлы 
	 * 
     * @param global $DB
     * @param mixed int|array $id
	 * @return array $data - видеофайлы
	 */
	public static function getVideoFile($id)
    {
		global $DB;                
        
		$query = "
                SELECT
                        id, group_id, uniq_name,
                        name_".LANGUAGE_CURRENT." AS name,
                        code, type, img,
                        content_".LANGUAGE_CURRENT." as content,
                        DATE_FORMAT(date, '%d.%m.%Y') as dateto
                FROM gallery_video
                WHERE active='1' " . where_clause('id', $id);
		$data = $DB->query($query);
        
        foreach ($data as $i => $row) {
            $data[$i]['img'] = Uploads::getIsFile('gallery_video', 'img', $row['id'], $row['img'], '');                      
        }
		return $data;
	}
    
	/**
	 * Удаление группы фотографий
	 *
	 * @param int $group_id
	 * @return int
	 */
	public function deleteGroup($group_id = -1)
    {
		global $DB;
		
		$group_id = ($group_id == -1) ? $this->group_id : $group_id;
		
		$query = "SELECT * FROM gallery_photo WHERE group_id='$group_id' AND group_table_name = '{$this->table_info['table_name']}'";
		$photos = $DB->query($query);
        
		reset($photos);
		while (list(, $row) = each($photos)) {
			$this->deletePhoto($row['id']);
		}
		
		$DB->delete("DELETE FROM `{$this->group_table}` WHERE id='$group_id'");
		return $DB->affected_rows;
	}
	
    /**
	 * Удаление фотографий определенной группы или товара
	 *
	 * @param string $group_table_name
	 * @param int $group_id
	 * @return int
	 */
	public static function deletePhotoGroup($group_table_name, $group_id)
    {
		global $DB;
		
		$query = "SELECT * FROM gallery_photo WHERE group_id='$group_id' AND group_table_name='{$group_table_name}'";
		$photos = $DB->query($query);
        
        if (count($photos) > 0) {
            $Gallery = new Gallery($group_table_name, $group_id);
            reset($photos);
            while (list(, $row) = each($photos)) {
                $Gallery->deletePhoto($row['id']);
            }

            $DB->delete("DELETE FROM `gallery_photo` WHERE group_id='$group_id' AND group_table_name='{$group_table_name}'");
            return $DB->affected_rows;
        }
        
        return 0;
	}
        
	/**
	 * Удаление фотографии и превью
	 *
	 * @param int $photo_id
	 * @return int
	 */
	public function deletePhoto($photo_id)
    {
		global $DB;
		
		$photo = $DB->query_row("SELECT * FROM gallery_photo WHERE id='$photo_id'");
		if ($DB->rows == 0) {
			return 0;
		}
        
        //путь к изображению
		$file = Uploads::getFile('gallery_photo', 'photo', $photo_id, $photo['photo']);
        
		$delete = array($file);
                
        //выборка всех превьюшек, если они есть
        $url = substr($file, strlen(UPLOADS_ROOT));        
        $parser = $DB->fetch_column("SELECT uniq_name FROM `cms_image_size` ");
        foreach ($parser as $uniq_name) {
            $delete[] = SITE_ROOT.'i/'.$uniq_name.'/'.$url;
		}
        
        //удаление изображений
		foreach ($delete as $filename) {
            if (is_file($filename) && is_writable($filename)) {
                unlink($filename);
            }
		}
        
		$DB->delete("DELETE FROM `gallery_photo` WHERE id='$photo_id'");
		return $DB->affected_rows;
	}
    
    /**
     * Создание превюшек
     * @param array $images
     * @param string $parser
     */
    public function checkIThumb($images, $parser) 
    {   
        global $DB;
        
        if (empty($images)) {
            return ;
        }
        
        $query = "select * from cms_image_size where uniq_name='$parser'";
        $info = $DB->query_row($query);
                
        foreach ($images as $row) {
            $dst_file = SITE_ROOT . 'i/'.$parser.'/' . $row['photo'];
            if (!is_file($dst_file)) {
                $Image = new Image(SITE_ROOT . 'uploads/' . $row['photo']);
                $Image->jpeg_quality = 100;
                $Image->resize($info['width'], $info['height'], $info['crop']);        
                $Image->save($dst_file);
            }
        }
    }
}

?>