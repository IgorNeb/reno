<?php
/**
 * Клас для SEO Оптимизации 
 * @package DeltaCMS
 * @subpackage Seo
 * @version 3.0
 * @author Naumenko A. 
 * @copyright Copyright 2014, c-format ltd.
 */

class  SEO
{
	
    /**
	 * Таблица, над которой ведётся работа
	 *
	 * @var int
	 */
	public $table_id = 0;
	
    /**
	 * Добавляется ли таблица в индекс
	 *
	 * @var boolean
	 */
	public $is_index_table = false;
        
    /**
     * Тип страницы
     * @var int
     */
    public $type_id = 0;
        
        
    /**
	 * Конструктор
	 *
	 * @param string $table_name
	 */
	public function __construct($table_name) {
                
	}
        
    /**
	 * Обновляет информацию в seo индексе или добавляем таблицу
	 *
	 * @param string $table_name
	 * @param mixed $id
	 * @return void
	 */
	public static function update($table_name, $id = 0) 
    {                
		// Проверяем, существует ли поисковый индекс по заданной таблице
		if (!method_exists('Seo', $table_name)) {
			return false;
		}
		  
		// Обновляем индекс
		self::$table_name($id);
         
        //int or array
        $seoid = (!empty($id)) ? self::getId($id, $table_name) : 0;

        //Обновление урлов                
        Seo::rebuildRelation($seoid);

        //добавление метатегов        
        if (is_array($seoid)) {
            foreach ($seoid as $sid) {
                self::generateMetaTag(0, $sid);
            }
        } else {
            self::generateMetaTag(0, $seoid);
        }
	}
        
    /**
     * Обновление урла
     * 
     * @global DB $DB
     * @param string $table_name
     * @param int $object_id
     * @param array $childs
     * @return boolean
     */
    public static function updateUrl($table_name, $object_id, $childs)
    {
        global $DB;

        if (empty($childs)) {
            return false;
        }
        $seo_id = self::getId($object_id, $table_name);
        if (empty($seo_id)) {
            return false;
        }

        $DB->update("UPDATE seo_structure SET `url` = '' WHERE 1 " . where_clause("id", array_keys($childs)));
        self::rebuildRelation($seo_id); 

        $where = ($table_name == 'shop_group') ? " AND tb_seo.table_id <> '2926' " : '';
        $childs_new = self::getChilds($table_name, $object_id, $seo_id, $where);
        
        foreach ($childs as $id => $url_old) {
            if (isset($childs_new[$id])) {
                self::redirect($id, $url_old, $childs_new[$id], 'update');
            }
        }
        return true;
    }
    
    /**
     * Возращает ID дочерных элементов
     * 
     * @global $DB $DB
     * @param string $table_name
     * @param int $object_id
     * @param int $seo_id
     * @return array
     */
    public static function getChilds($table_name, $object_id, $seo_id = 0, $where = '')
    {
        global $DB;

        if (empty($seo_id)) {
            $seo_id = self::getId($object_id, $table_name);
            if (empty($seo_id)) {
                return false;
            }
        }

        $childs = $DB->fetch_column("SELECT tb_seo.id, tb_seo.url "
                    . " FROM seo_structure_relation as tb_relation "
                    . " INNER JOIN seo_structure as tb_seo ON tb_seo.id=tb_relation.id "
                    . " WHERE 1 " . where_clause('tb_relation.parent', $seo_id) . $where);
        return $childs;
    }
        
    /**
     * 
     * @global DB $DB
     * @param string $table_name
     * @param int $object_id
     */
    public static function changeParent($table_name, $object_id)
    {
        global $DB;
        
        $table_id = self::getTableId($table_name);            
        $DB->update("update seo_structure set group_id='0' where table_id='$table_id' and object_id='$object_id'");
        
        self::updateGroupRelation($table_id);
    }
        
    /**
     * Удаление редиректа, если такой есть
     * 
     * @global $DB
     * @param string $table_name
     * @param int $object_id         
     */
    public static function checkLinkIsRedirect($table_name, $id = 0) 
    {
        global $DB;
        
        // Проверяем, существует ли поисковый индекс по заданной таблице
        if (!method_exists('Seo', $table_name)) {
            return false;
        }

        $table_id = self::getTableId($table_name);

        //если стоит редирект в таблице - удаляем его
        $url = $DB->result("SELECT url FROM seo_structure WHERE `object_id` ='$id' and table_id='$table_id' ");           
        if (!empty($url)) {
            $DB->delete("DELETE FROM `site_structure_redirect` WHERE `url_old`='$url'");                
        }   
        
        $DB->delete("DELETE FROM `site_structure_redirect` WHERE `url_old`='".CMS_HOST."' or `url_old`='".CMS_HOST."/'");   
    }
        
    /**
     * Добавление в сео структуру страниц прикрепленных разделов
     * 
     * @global DB $DB
     * @param int $id - страница сайта site_structure
     */
    public static function buildStructureItems($id = 0)
    {
        global $DB;
        
        $items = $DB->query("SELECT tb_item.table_name, tb_item.object_id AS id, tb_seo.id AS structure_id "
                . " FROM site_structure tb_site "
                . " INNER JOIN site_structure_item  AS tb_item ON tb_item.id=tb_site.item_id "
                . " INNER JOIN seo_structure  AS tb_seo ON tb_seo.object_id=tb_site.id AND tb_seo.table_id='29'"
                . " WHERE tb_site.`item_id` <> '0' " . where_clause('tb_site.id', $id)
                . " ORDER BY tb_item.table_name ");  
         
        foreach ($items as $item) {
            $table_name = $item['table_name'];
            if ($table_name == 'shop_brands') {
                continue;
            }
            if ($table_name == 'news_type') {
                //разделы новостей
                $ids = $DB->fetch_column("SELECT DISTINCT(id) FROM news_type_relation WHERE parent = '{$item['id']}' and id <> '{$item['id']}' ");                                         

                if (count($ids)) { 
                    //если есть подразделы 
                    self::news_type($ids);

                    array_push($ids, $item['id']);
                    $news_ids = $DB->fetch_column("SELECT id FROM news_message WHERE 1 " . where_clause('type_id', $ids));                    
                    self::news_message($news_ids);                        
                } else {
                    //если нет подразделов
                    $news_ids = $DB->fetch_column("SELECT id FROM news_message WHERE type_id='{$item['id']}' ");                    
                    self::news_message($news_ids, $item['structure_id']);                                                 
                }
            } elseif ($table_name == 'gallery_video_group') {
                
                $ids = $DB->fetch_column("SELECT DISTINCT(id) FROM gallery_video_group_relation"
                          . " WHERE parent = '{$item['id']}' and id <> '{$item['id']}' ");  
                          
                if (count($ids)) { 
                    //если есть подразделы 
                    self::gallery_video_group($ids);
                    array_push($ids, $item['id']);

                    $childs_ids = $DB->fetch_column("SELECT id FROM gallery_video WHERE 1 " . where_clause('group_id', $ids));                    
                    self::gallery_video($childs_ids);                        
                } else {
                    //если нет подразделов
                    $childs_ids =$DB->fetch_column("SELECT id FROM gallery_video WHERE group_id='{$item['id']}' ");                    
                    self::gallery_video($childs_ids, $item['structure_id']);                                                 
                }
            } elseif ( method_exists('Seo', $table_name)) {
                self::$table_name($item['id'], $item['structure_id']);
            }
        }
         
        if (is_module('Shop')) {
            self::shop_group( 0 );                     
            self::shop_brands( 0 );                     
            self::shop_product( 0 );
        }
             
             
        self::rebuildRelation();
        
        $DB->update("UPDATE seo_structure SET is_update='0' ");

        self::generateMetaTag(0, 0);
        
    }
       
    /**
     * Проверка seo структуры
     * 
     * @global DB $DB
     * @param string $table_name
     */
    public static function checkStructure($table_name)
    {
        global $DB;

        // Проверяем, существует ли поисковый индекс по заданной таблице
        if (!method_exists('Seo', $table_name)) {
           return false;
        }

        $table_id = self::getTableId($table_name);

        $data = $DB->fetch_column("select tb_seo.id from seo_structure as tb_seo"
                . " left join `$table_name` as tb_table on tb_table.id=tb_seo.object_id "
                . " where tb_table.id is null and tb_seo.table_id='$table_id'");
        
        if (count($data)) {
            $query = "delete from seo_structure where id in (".implode(', ', $data).")" ;
            $DB->query($query);         
        }
        
        $data = $DB->fetch_column("select tb_seo.id from seo_structure as tb_seo"
                . " inner join `$table_name` as tb_table on tb_table.id=tb_seo.object_id and tb_seo.table_id='$table_id'"
                . " where tb_table.active='0' and (tb_seo.active='1' or tb_seo.is_sitemap='1')");
        if (count($data)) {
            $query = "update seo_structure set active='0' where id in (".implode(', ', $data).") "
                    . " or group_id in (".implode(', ', $data).") ";
            $DB->query($query);
        }

        $data = $DB->fetch_column("select tb_seo.id from seo_structure as tb_seo "
                . " inner join `$table_name` as tb_table on tb_table.id=tb_seo.object_id and tb_seo.table_id='$table_id'"
                . " inner join seo_structure  as tb_parent on tb_parent.id=tb_seo.group_id "
                . " where tb_table.active='1' and tb_seo.active='0' and tb_parent.active='1' ");
        if (count($data)) {
            $query = "update seo_structure set active='1' where id in (".implode(', ', $data).")";
            $DB->query($query); 
        }
        
    }
        
    /**
     * Удаление подразделов, прикрепленных к структуре.
     *       Редиректы не добавляются
     * 
     * @global $DB
     * @param int $id - страница site_structure         
     * @return void
     */
    public static function deleteStructureItems($id)
    {
        global $DB;

        $seo_id = self::getId($id, 'site_structure');
        if (empty($seo_id)) {
            return false;
        }

        $seo_st = (is_array($seo_id)) ? implode(', ', $seo_id) : $seo_id;
        
        $items = $DB->fetch_column("SELECT tb_relation.id "
                    . " FROM seo_structure_relation as tb_relation "
                    . " WHERE tb_relation.parent in ($seo_st) and tb_relation.id not in ($seo_st) ");
        if (count($items)) {
            $DB->delete("delete from seo_structure where 1 ".where_clause("`id`", $items));                
        }
        
    }
    
    /**
     * Возвращает id объекта
     * @global $DB
     * @param int $object_id 
     * @param string $table_name
     * @return array
     */
    private static function getId($object_id, $table_name)
    {
        global $DB;

        $table_id = self::getTableId($table_name);
        $id = $DB->fetch_column("SELECT id FROM seo_structure WHERE `object_id` ='$object_id' and table_id='$table_id' ");
        return $id;
    }
        
    /**
     * Возращает id Радителя в сео струкртуре
     * @global $DB
     * @param int $parent_id
     * @param string $table_name
     * @return int $id
     */
    public static function getStructureId($parent_id, $table_name)
    {
        global $DB;

        $id = $DB->fetch_column("SELECT tb_seo.id "
                . " FROM site_structure tb_site "
                . " INNER JOIN site_structure_item  AS tb_item ON tb_item.id=tb_site.item_id "
                . " INNER JOIN seo_structure  AS tb_seo ON tb_seo.object_id=tb_site.id AND tb_seo.table_id='29'"
                . " WHERE tb_site.`item_id` <> '0' AND tb_item.object_id='$parent_id' AND tb_item.table_name='$table_name' "
                . " ORDER BY tb_item.table_name ");  

        if (empty($id)) {
            //значить это не прикрепленный раздел, а уже существует в сео структуре
            $table_id = self::getTableId($table_name);
            $id = (int)$DB->result("SELECT id FROM `seo_structure` WHERE `object_id`='$parent_id' and `table_id`='$table_id' ");            
        }

        if (is_array($id) && count($id) == 1){
            $id = array_pop($id);
        }
        return $id;
    }
    
     /**
     * Добавление страницы в сео структуру
      * 
     * @global DB $DB
     * @param int $table_id
     * @param int|array $group_ids
     * @param int $object_id
     * @param int $parent_object_id
     * @param string $uniq_name
     * @param array $names_fields
     * @param array $data
     * @return boolean 
     */
    public static function add($table_id, $group_ids, $object_id, $parent_object_id, $uniq_name, $names_fields, $data)
    {
        global $DB;

        if (!is_array($names_fields)) {
            $names_fields = explode(', ', $names_fields);
        }
        
        $key_fields = array_merge($names_fields, array('active', 'priority', 'page_priority', 'is_sitemap', 'change_frequency'));
        $fields = array();
        foreach ($key_fields as $key) {
            if (isset($data[$key])) {
                $fields[] = " `$key` = '".$DB->escape(trim($data[$key]))."'";                    
            }    
        }
        
        if (empty($fields)) {
            return false;  
        }

        $fields[] = " `is_update` = '1'"; 
        $sets = implode(', ', $fields);

        if (!is_array($group_ids) || empty($group_ids)) {
            $group_ids = (empty($group_ids)) ? array( 0 => 0) : array($group_ids);
        }
        //добавление страницы в структуру
        $query = " INSERT INTO seo_structure 
                   SET
                        `group_id`          = '%d', 
                        `object_id`         = '$object_id', 
                        `object_group_id`   = '$parent_object_id',                             
                        `table_id`          = '$table_id',                             
                        `uniq_name`         = '$uniq_name',                            
                         $sets   
                   ON DUPLICATE KEY UPDATE  `uniq_name` = '$uniq_name', `object_group_id`   = '$parent_object_id', $sets ";
     
        foreach ($group_ids as $group_id) {
            $squery = sprintf($query, $group_id);            
            $DB->query($squery);  
         
        }
    }
        
    /**
     * Обновление радителя после добавление страниц, и добавление cсылки
     * @global $DB 
     * @param int $table_id
     * @return boolean 
     */
    public static function updateGroupRelation($table_id)
    {
        global $DB;

        $query = "UPDATE 
                        seo_structure as tb_seo, seo_structure as tb_seo_relation
                    SET tb_seo.group_id = tb_seo_relation.id
                  WHERE tb_seo.object_group_id = tb_seo_relation.object_id 
                    AND tb_seo.group_id='0' AND tb_seo.table_id='$table_id'
                    AND tb_seo.table_id='$table_id' AND tb_seo.id > 1";        
        $DB->query($query);
    }

    /**
     * Обновление ссылок в таблицы, только там где пустые url 
     * 
     * @global DB $DB
     * @param int|array $id
     */
    public static function rebuildRelation($id = 0)
    {
        global $DB;

        if (!empty($id) && !is_array($id)) {
            $id = (array)$id;
        }
        
        if (is_array($id)) {
            foreach ($id as $group_id) {
                self::rebuild_relation($group_id);
            }
        } else {
            self::rebuild_relation();
        }
        $where = (!empty($id)) ? " or tb_empty.id in (".implode(", ", $id).")" : "";
        self::update_url($where);

        //так как урл товара /{$uniq_name}/
        $DB->update("UPDATE seo_structure SET url = CONCAT('".CMS_HOST."', '/', uniq_name) WHERE table_id='2926'"); 

        $DB->update("UPDATE seo_structure SET is_update='0' WHERE is_update='1'");         
    }
        
    /**
     * Построение всей таблицы связей или для определенной строки
     * @global DB $DB
     * @param int $id
     */
    private static function rebuild_relation($id = 0)
    {
        global $DB;

        if (empty($id)) {
            $Structure = new Structure('seo_structure');              
            $Structure->rebuildRelation('group_id'); 
            unset($Structure);
        } else {
            $group_id = $id;
            $groups = array($group_id);
            $i=0;
            while ($group_id > 0) {
                $group_id = (int)$DB->result("select group_id from seo_structure where id='{$group_id}'");
                if ($group_id > 0) {                    
                    array_unshift($groups, $group_id);
                }
                $i++;
                if ($i == 5) {
                    break;
                }
            }
            
            $insert = array(); $i = 1;
            foreach ($groups as $group_id) {
                $insert[] = "('$id', '$group_id', '$i')";
                $i++;
            }
            if (count($insert)) {
                $DB->delete("delete from seo_structure_relation where id='$id'");
                $DB->query("insert ignore into seo_structure_relation (`id`, `parent`, `priority`) VALUES " . implode(',', $insert));
            }
        }
        
    }

    /**
     * Определяет URL для полей, которые не заполнены или другие
     * 
     * @param string $where where
     */
    public static function update_url($where = "") 
    {
        global $DB;

        $query = "
            SELECT
                tb_relation.id,
                GROUP_CONCAT(tb_structure.uniq_name ORDER BY tb_relation.priority SEPARATOR '/') AS url
            FROM `seo_structure_relation` AS tb_relation
            INNER JOIN `seo_structure` AS tb_structure ON tb_structure.id=tb_relation.parent
            INNER JOIN `seo_structure` as tb_empty on tb_empty.id=tb_relation.id
            WHERE tb_empty.url='' $where
            GROUP BY tb_relation.id
        ";
        $data = $DB->query($query);
        
        reset($data);
        while (list(, $row) = each($data)) {
            $DB->update("UPDATE `seo_structure` SET url='$row[url]' WHERE id='$row[id]'");
        }
    }

    /**
     * Удаление данных из seo индекса
     *
     * @param string $table_name
     * @param int $object_id - int or array
     * @return bool
     */
    public static function delete($table_name, $object_id)
    {
        global $DB;

        if (!method_exists('Seo', $table_name) || empty($object_id)) { 
            return false;             
        }
        
        $table_id = self::getTableId($table_name);
        $seo_id = $DB->fetch_column("SELECT id FROM seo_structure WHERE table_id='$table_id' " . where_clause('object_id', $object_id));
        if (empty($seo_id)) {
            return false;
        }

        $query =  " SELECT tb_seo.id, tb_seo.object_id, tb_seo.url as url_old, tb_parent.url as url_new  "
                . " FROM seo_structure_relation as tb_relation "
                . " INNER JOIN seo_structure as tb_seo ON tb_seo.id=tb_relation.id "
                . " INNER JOIN seo_structure as tb_parent ON tb_parent.id = tb_seo.group_id "
                . " WHERE 1 " . where_clause('tb_relation.parent', $seo_id);
        $data= $DB->query($query);

        if (count($data)) {
            reset($data);
            while (list(, $row)=each($data)) {                        
                $query = "delete from seo_structure where `id`='{$row['id']}'" ;
                $DB->delete("delete from seo_structure where `id`='{$row['id']}'");  
                $url_new = (!isset($row['url_new']) || empty($row['url_new'])) ? CMS_HOST.'/' : $row['url_new'];
                self::redirect($row['object_id'], $row['url_old'], $url_new, 'delete');                
            }
        }
        return true;
    }

    /**
     * Редирект страницы 
     * 
     * @param int $id - id страницы
     * @param string $url_old - из какого адрессы перенаправлять
     * @param string $url_new - новый адресс
     * @param string $task - операция
     **/
    public static function redirect($id,  $url_old, $url_new, $task)
    {   
        global $DB; 

        if (!preg_match("/^".CMS_HOST."/", $url_old)) {
            $url_old = CMS_HOST . $url_old;
        }   
        if ($url_old == CMS_HOST || $url_old == CMS_HOST ."/") {
          return false;
        }
        
        $DB->query("UPDATE site_structure_redirect SET url_new='$url_new' WHERE url_new = '$url_old' ");
        $query = "
               INSERT IGNORE INTO site_structure_redirect 
               SET structure_id = '{$id}',  
                   url_old   = '$url_old', 
                   url_new   = '$url_new', 
                   admin_id  = '".Auth::getUserId()."',
                   operation = '$task'
        ";
        $DB->insert($query);  

    }
    
    /**
     * Возращает шаблоны для опеределенного ID
     * 
     * @global $DB 
     * @return array $data
     */
    public static function getTemplateById($template_id, $id)
    {
        global $DB;

        if (empty($id)) {
            return $template_id;
        }

        $data = $DB->query("SELECT tb_template.uniq_name, tb_template.id"
                . " FROM seo_template as tb_template "                    

                . " INNER JOIN seo_structure as tb_seo on tb_seo.id='$id' "
                . " INNER JOIN seo_structure_relation as tb_relation on tb_relation.id = tb_seo.id  and tb_relation.parent = tb_template.structure_id "                    

                . " LEFT JOIN seo_type as tb_type on tb_type.id = tb_template.type_id and tb_seo.table_id = tb_type.table_id "
                . " LEFT JOIN seo_type as tb_type_st on tb_type_st.id = tb_template.type_id and tb_type_st.table_id = '0' "                    

                . " WHERE tb_template.active='1' and (tb_type.id is not null or (tb_type.id is null and tb_type_st.id is not null))"                        
                . " ORDER BY tb_relation.priority DESC, tb_template.type_id DESC ");  
        $templates = array();
        foreach ($data as $row) {
            if (isset($templates[$row['uniq_name']])) {
                continue;                
            }
            $templates[$row['uniq_name']] = $row['id'];
        }
        return $templates;
    }

    /**
     * Generate MetaTag for List Pages
     * @global type $DB
     * @param int $template_id - id шаблона
     * @param int $object_id   - id объекта
     */   
    public static function generateMetaTag($template_id = 0, $seo_id = 0)
    {
        global $DB;

        $template_id = self::getTemplateById($template_id, $seo_id);

        $languages =  getLanguageList();
        reset($languages);
        while (list($index, $lang) = each($languages)) {
            $query = " SELECT 
                            tb_template.id, 
                            tb_template.structure_id, 
                            tb_template.uniq_name as param, 
                            tb_template.content_".$lang." as content,
                            tb_type.table_id    
                    FROM seo_template as tb_template                                             
                    LEFT JOIN seo_type as tb_type on tb_type.id=tb_template.type_id
                    WHERE tb_template.active='1' "
                    . where_clause('tb_template.id', $template_id). "
            ";
            $data = $DB->query($query);               
            $fixed_field = array('{$parent_name}', '{$name}', '{$fullname}');

            reset($data);
            while (list(, $row)=each($data)) {
                $insert = array();

                preg_match_all('/\{\$([^\}]+)\}/i', $row['content'], $matches);
                if (!isset($matches[1])) {
                    continue;
                }

                $replace_array = array_diff(array_unique($matches[0]), $fixed_field);        
                sort($replace_array);        
                $words = str_replace(array('{', '}', '$'), '', $replace_array);       
                $groups_words = array();

                //словочетания из групп
                if (count($words)) {
                    reset($words); 
                    while (list($key, $uniq_name)=each($words)) {
                        $groups_words[$uniq_name] = $DB->fetch_column("
                                SELECT 
                                        tb_seo.id,
                                        tb_seo.name_".$lang." as name
                                FROM seo_words as tb_seo
                                INNER JOIN seo_words_group as tb_group ON tb_group.id = tb_seo.group_id
                                WHERE tb_group.uniq_name='$uniq_name' AND tb_seo.active = '1'				
                        ");
                    }
                }

                $where_structures = "";
                if (!empty($row['structure_id'])) {
                    $where_structures = " and tb_structure.id in (select id from seo_structure_relation where parent='{$row['structure_id']}') ";
                }

                $relation = $DB->query(
                      " SELECT tb_structure.id, tb_structure.group_id, tb_structure.object_id, tb_structure.table_id "
                    . " FROM seo_structure as tb_structure"
                    . " WHERE tb_structure.is_hands_metatags='0'  "
                            . $where_structures    
                            . where_clause('tb_structure.id', $seo_id)
                            . where_clause('tb_structure.table_id', $row['table_id']) 
              );

                reset($relation);
                while (list(, $row_relation) = each($relation)) {
                    //генерация случайных словосочетаний
                    $rand_array = self::rand_groups_words($groups_words);							
                    ksort($rand_array);

                    //шаблон
                    $template= str_replace($replace_array, $rand_array, $row['content']);	

                    $insert[] = "('{$row_relation['object_id']}','{$row_relation['group_id']}', '{$row_relation['table_id']}', '".$DB->escape($template)."')";
                }

                if (count($insert)) {
                    $query = "
                        INSERT INTO seo_structure (`object_id`,`group_id`, `table_id`, `".$row['param']."_".$lang."`)"
                        . " VALUES ".implode(", ", $insert)
                        . "  on duplicate key update `".$row['param']."_".$lang."`=values(`".$row['param']."_".$lang."`)";
                    $DB->query($query);
                }   
            }        
        }

    }

    /**
     * Возращает массив случайным образом
     * 
     * @param array $arr 
     * @return array
     */
    public static function rand_groups_words($arr)
    {
        $data = array();
        reset($arr);
        while (list($uniq_name, $row) = each($arr)) {		
            $id = array_rand($row, 1);		
            $data[$uniq_name] = $row[$id];		
        }
        return $data;
    }
    
    /**
     * Возращает ID таблицы
     * @global $DB
     * @param string $table_name
     * @return int $table_id
     */
    private static function getTableId($table_name)
    {
        global $DB;

        $id = $DB->result("select `id` from `cms_table` where name='{$table_name}'");
        return $id;
    }
    

    /**
     * Название полей базы в зависимости от языков
     * 
     * @return string
     */
    private static function get_fields_name()
    {
        $languages =  getLanguageList();
        return 'name_' . implode(', name_', $languages);
        
    }
    /**
    * Методы для SEO, название метода должно соответсвовать названию таблицы
    */

    /**
     * Структура сайта
     *
     * @param int $id
     * @param int $group_id
     */
    private static function site_structure($id, $group_id = 0)
    {
        global $DB;

        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        }  

        $name_fields = self::get_fields_name();
        
        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, structure_id, $name_fields, uniq_name, active, priority,
                        IF (`id` = '824', '1', '0.2') as page_priority,
                        IF (`id` = '824', 'always', 'monthly') as change_frequency
                    FROM `$table_name`
                    WHERE id > $start_id ".where_clause("id", $id)."
            ";
            $data = $DB->query($query);  
            
            reset($data);
            while (list(, $row) = each($data)) { 
                $seo_id = self::getId($row['structure_id'], $table_name);
                self::add($table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row);
                $start_id = $row['id'];
            }
            
        } while (!empty($data));

        if (empty($group_id)) {
            //обновляем структуру
            self::updateGroupRelation($table_id);
        }  
        
    }
   
    /**
     *
     * @param int $id
     * @param int $group_id
     */
    private static function news_type($id, $group_id = 0)
    {
        global $DB;

        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 

        $name_fields = self::get_fields_name();

        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, type_id as structure_id, $name_fields, uniq_name, active, priority,   
                        '0.2' as page_priority,
                        'monthly' as change_frequency
                    FROM `$table_name`
                    WHERE id > $start_id " . where_clause("id", $id)."
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(,$row) = each($data)) {   
                $seo_id = self::getStructureId($row['structure_id'], $table_name);

                if (!empty($seo_id)) {                     
                    self::add($table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row);
                }    
                $start_id = $row['id'];
            }
        } while (!empty($data));

        //обновляем структуру
        self::updateGroupRelation($table_id);

    }

    /**	
     * Видео
     * 
     * @param int $id
     * @param int $group_id
     */
    private static function gallery_video_group($id, $group_id = 0)
    {
        global $DB;

        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 

        $name_fields = self::get_fields_name();

        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, group_id as structure_id, $name_fields, uniq_name, active, priority,
                        '0.2' as page_priority,
                        'monthly' as change_frequency
                    FROM `$table_name`
                    WHERE id > $start_id ".where_clause("id", $id)."
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(,$row) = each($data)) {   
                $seo_id = self::getStructureId($row['structure_id'], $table_name);
                if (!empty($seo_id)) {                     
                    self::add($table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row);
                }    
                $start_id = $row['id'];
            }
        } while (!empty($data));

        //обновляем структуру
        if (empty($group_id)) {
            //обновляем структуру
            self::updateGroupRelation($table_id);
        }    

    }

    /**	
     * Видео файлы
     * @param string $table_name
     * @param string $field_name
     * @return int
     */
    private static function gallery_video($id, $group_id = 0)
    {
        global $DB;
        
        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 

        $name_fields = self::get_fields_name();

        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, group_id as structure_id, $name_fields, uniq_name, active, priority
                    FROM `$table_name`
                    WHERE id > $start_id ".where_clause("id", $id)."
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(,$row) = each($data)) {   
                $seo_id = (empty($group_id)) ? self::getStructureId($row['structure_id'], 'gallery_video_group') : $group_id;
                self::add($table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row);
                $start_id = $row['id'];
            }
        } while(!empty($data));

        //обновляем структуру
        //self::updateGroupRelation($table_id);
    }
    
    /**
     * 
     *
     * @param string $table_name
     * @param string $field_name
     * @return int
     */
    private static function news_message($id, $group_id = 0)
    {
        global $DB;

        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 

        $name_fields = self::get_fields_name();

        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, type_id as structure_id, $name_fields, uniq_name, active, priority,                        
                        '0.2' as page_priority,
                        'monthly' as change_frequency
                    FROM `$table_name`
                    WHERE id > $start_id AND type_id <> 1  " . where_clause("id", $id)."
                    ORDER BY id    
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(,$row) = each($data)) {   
                $seo_id = (empty($group_id)) ? self::getStructureId($row['structure_id'], 'news_type') : $group_id;
                self::add($table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row);
                $start_id = $row['id'];
            }
        } while (!empty($data));

        //обновляем структуру
        if (empty($group_id)) {
            //обновляем структуру
            self::updateGroupRelation($table_id);
        }    
        
    }

    /**
	 * Добавление страниц shop_group
	 *
	 * @param int $id
	 * @param int $group_id
	 * @return int
	 */
	private static function shop_group($id, $group_id = 0) 
    {
        global $DB;

        if (!is_module('Shop')) { return false; }            

        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 
        $name_fields = self::get_fields_name();
        
        $start_id = 1;
        do {
            $query = "
                    SELECT
                        id, group_id as structure_id, $name_fields,
                        uniq_name, active, priority,                        
                        '0.7' as page_priority
                    FROM `$table_name`
                    WHERE id > $start_id ".where_clause("id", $id)."
                    ORDER BY id     
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(,$row) = each($data)) {  
                if( $row['structure_id'] == 1){ 
                    $seo_id = self::getStructureId(824, 'site_structure');
                }  else{
                    $seo_id = self::getStructureId($row['structure_id'], 'shop_group');                   
                }    
                self::add($table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row);
                $start_id = $row['id'];
            }
        } while( !empty($data) );
        //обновляем структуру
        if (empty($group_id)) {
            self::updateGroupRelation($table_id);
        }
	}
        
        
	 /**
	 * Добавление страниц shop_product
	 *
	 * @param int $id
	 * @param int $group_id
	 * @return int
	 */
	private static function shop_product( $id, $group_id = 0)
    {
        global $DB;

        if (!is_module('Shop')) { return false; }            

        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 
        $name_fields = self::get_fields_name();
        
        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, group_id as structure_id, 
                        nameseo_".LANGUAGE_CURRENT." as name_".LANGUAGE_CURRENT.", 
                        uniq_name, active, priority,                                
                        '0.5' as page_priority
                    FROM `$table_name`
                    WHERE id > $start_id ".where_clause("id", $id) . "
                    ORDER BY id    
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(,$row) = each($data)) {  
                $seo_id = ( empty($group_id) ) ? self::getStructureId($row['structure_id'], 'shop_group') : $group_id;                   
                self::add( $table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row );
                $start_id = $row['id'];
            }
        } while( !empty($data) );
    }

     /**
	 * Добавление страниц Акций
	 *
	 * @param int $id
	 * @param int $group_id
	 * @return int
	 */
	private static function shop_stock($id, $group_id = 0)
    {
        global $DB;

        if (!is_module('Shop')) { return false; }            

        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 
        $name_fields = self::get_fields_name();
        
        $seo_id = self::getStructureId(0, 'shop_stock');            
         
        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, 0 as structure_id, 
                        $name_fields, uniq_name, active, priority,                                
                        '0.2' as page_priority,
                        'monthly' as change_frequency
                    FROM `$table_name`
                    WHERE id > $start_id ".where_clause("id", $id) . "
                    ORDER BY id    
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(, $row) = each($data)) {  
                self::add( $table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row );
                $start_id = $row['id'];
            }
        } while( !empty($data) );
    }
     /**
	 * Добавление страниц Акций
	 *
	 * @param int $id
	 * @param int $group_id
	 * @return int
	 */
	private static function shop_present($id, $group_id = 0)
    {
        global $DB;

        if (!is_module('Shop')) { return false; }            

        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 
        $name_fields = self::get_fields_name();
                    
        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, group_id as structure_id, 
                        $name_fields, url as uniq_name, active, priority,                                
                        '0.2' as page_priority,
                        'monthly' as change_frequency
                    FROM `$table_name`
                    WHERE id > $start_id ".where_clause("id", $id) . "
                    ORDER BY id    
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(, $row) = each($data)) {  
                $seo_id = ( empty($group_id) ) ? self::getStructureId($row['structure_id'], $table_name) : $group_id;    
                self::add( $table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row );
                $start_id = $row['id'];
            }
        } while( !empty($data) );
    }
    /**
     * Добавление страниц shop_brands
     *
     * @param int $id
     * @param int $group_id
     * @return int
     */
    public static function shop_brands($id, $group_id = 0) 
    {
        global $DB;
        
        $table_name = __FUNCTION__;
        $table_id   = self::getTableId($table_name);    
        if (!$table_id) {
            return false;
        } 
        $name_fields = self::get_fields_name();
        
        $seo_id = ( empty($group_id) ) ? self::getStructureId(0, 'shop_brands') : $group_id;                   
         
        $start_id = 0;
        do {
            $query = "
                    SELECT
                        id, '0' as structure_id,                         
                        $name_fields, uniq_name, priority, active,
                        '0.8' as page_priority    
                    FROM `$table_name`
                    WHERE id > $start_id ".where_clause("id", $id)."
                    ORDER BY id    
            ";
            $data = $DB->query($query);
            reset($data);
            while (list(,$row) = each($data)) {                  
                self::add( $table_id, $seo_id, $row['id'], $row['structure_id'], $row['uniq_name'], $name_fields, $row );
                $start_id = $row['id'];
            }
        } while( !empty($data) );       
            
	}
    
    /**
     * Обработка данных о странице
     * 
     * @param string $table_name - название таблицы
     * @param int $object_id - id объекта
     * @return array
     */
    public static function parseHeaders($table_name)
    {            
        global $DB;

        $http_url = trim(NO_LANGUAGE_URL_FORM, "?");            
        $site_url = preg_replace("/\/$/", '',  CMS_HOST. trim($http_url, '.html'));
        
        $page = (isset($_GET['_page']) && $_GET['_page'] > 1) ? $_GET['_page'] : 0; 
       
        $table_id = self::getTableId($table_name);

        $data = $DB->query_row(" SELECT 
                            tb_seo.id as seo_id,        
                            tb_seo.table_id,
                            tb_seo.object_id,
                            tb_seo.is_index, tb_seo.page_priority, tb_seo.last_modified, tb_seo.change_frequency, 
                            unix_timestamp(tb_seo.last_modified) as last_modified,
                            tb_seo.name_".LANGUAGE_CURRENT." as name,
                            tb_seo.headline_".LANGUAGE_CURRENT." as headline,
                            tb_seo.title_".LANGUAGE_CURRENT." as title,
                            tb_seo.description_".LANGUAGE_CURRENT." as description,
                            tb_seo.keywords_".LANGUAGE_CURRENT." as keywords,
                            tb_seo.content_".LANGUAGE_CURRENT." as seo_content,
                            tb_seo.canonical,  
                            tb_relation.name_".LANGUAGE_CURRENT." as parent_name  "
                     . " FROM seo_structure as tb_seo "
                     . " LEFT JOIN seo_structure as tb_relation on tb_relation.id=tb_seo.group_id"
                     . " WHERE tb_seo.`url`='$site_url'");

        foreach ($data as $i=>$name) {
            if (!empty($name)) {
                $data[$i] = stripslashes($name);
            }
        }
        
        if (count($data) > 0) {
            if ($data['table_id'] == 2926 && $data['object_id'] > 0) {
                $data['fullname'] = $DB->result("SELECT IFNULL(`namefull_".LANGUAGE_CURRENT."`, `name_".LANGUAGE_CURRENT."`)"
                        . " FROM shop_product WHERE id='{$data['object_id']}'");
            }
            //$fixed_data['name'] = (!empty($data['name'])) ? $data['name'] : $fixed_data['name'];
            $fixed_template = array('headline', 'title', 'description', 'keywords');
            reset($fixed_template);                
            while (list(, $key)=each($fixed_template)) {                    
                if (!isset($data[ $key ]) || empty($data[$key])) { 
                    continue;                    
                }

                preg_match_all('/\{\$([^\}]+)\}/i', $data[$key], $matches);
                
                if (isset($matches[0])) {
                    //заменяем найденные переменные
                    reset($matches[0]);                    
                    while (list(, $value) = each($matches[0])) {
                        $index = str_replace(array('{', '}', '$'), '', $value);
                        if (isset($data[ $index ])) {
                            if (preg_match('/^\{\$('.$index.'\})/i', $data[$key]) || preg_match('/\. \{\$('.$index.'\})/i', $data[$key])) {
                                $data[$key] = str_replace($value, $data[ $index ], $data[$key]);
                            } else {
                                $data[$key] = str_replace($value, $data[ $index ], $data[$key]);
                            }    
                        } else {
                            $data[$key] = str_replace($value, '', $data[$key]);
                        }
                    }
                }
            }

            $fixed_template = parse_headers($data['name'], $data['headline'], $data['title'], $data['description']);    
          
            $data = array_merge($data, $fixed_template);
        }

        if (isset($data['canonical']) && !empty($data['canonical'])) {
            $data['canonical'] = str_replace(array('https', 'http', '://', CMS_HOST), '', $data['canonical']);
            $data['canonical'] = self::getPageCanonical($data['canonical'], $page);  
        } else {
            if (!empty($data) && HTTP_REQUEST_URI !== '/') {             
                $data['canonical'] = self::getPageCanonical('', $page);             
            } elseif (strpos(HTTP_REQUEST_URI, '?') !== false || !empty($page)) { 
                $data['canonical'] = self::getPageCanonical('', $page);
            }
        }
        
        return $data;
    }

    /**
	 * Возвращает TRUE если не выбран фильтр или выбран только один, 
     *      или если один + подкатегория
     *      если цена, тогда FALSE
	 *
	 * @param array $arr параметры фильтра
	 * @return boolean
	 */
	public static function isIndexFilter($arr) 
    {
        global $DB;
        
        if (isset($_GET['filter']) && preg_match('/sticker=/', $_GET['filter'])) { return false; }
        
		if (count($arr) == 0) return true;
        
		if (isset($arr['price'])) return false;
		
        if (count($arr) == 1 || (count($arr) == 2 && isset($arr['category'])) ) {            
            foreach ($arr as $row) {							
				if (count($row) > 2) return false;
			}	
            $param_id = key($arr);
            return $DB->result("SELECT is_index FROM shop_group_param WHERE id='{$param_id}'");			
            
        } elseif (count($arr) == 2) {            
            foreach ($arr as $row) {							
				if (count($row) > 1) {                    
                    return false;
                }   
			}
            
            $noindex = $DB->result("SELECT id FROM shop_group_param WHERE is_index = '0' " . where_clause('id', array_keys($arr)));	
            return (empty($noindex)) ? true : false;
        }      
		return false;
	}
    
    /**
     * Мета-данные для страниц фильтра       
     * @param int $object_id - id объекта, группы товаров
     * @return array
     */
    public static function parseFilterHeaders($object_id, $param_name)
    {            
        global $DB;

        $fixed_data = $DB->query_row(
                       " select 
                            tb_seo.id,
                            tb_seo.name_".LANGUAGE_CURRENT." as parent_name,
                            '$param_name' as name "
                     . " from seo_structure as tb_seo "                        
                     . " where tb_seo.object_id ='$object_id' and tb_seo.table_id='2918' ");
        if (empty($fixed_data)) {
            return array();
        }

        //выбор шаблонов для данной странице
        $query = " SELECT 
                            tb_template.id, 
                            tb_template.structure_id, 
                            tb_template.uniq_name, 
                            tb_template.content_".LANGUAGE_CURRENT." as content,
                            tb_type.table_id    
                    FROM seo_template as tb_template                                             
                    INNER JOIN seo_type as tb_type on tb_type.id=tb_template.type_id                        
                    WHERE tb_template.active='1' and tb_type.table_id = '2797' 
                           and tb_template.structure_id in (select parent from seo_structure_relation where id='{$fixed_data['id']}')
                    ORDER BY tb_template.structure_id DESC ";
        $templates = $DB->query($query);
        if (empty($templates)) {
            return array();
        }

        $data = array('headline'=>'', 'title'=>'', 'description'=>'', 'keywords'=>'');

        reset($templates);                
        while (list(, $row)=each($templates)) {  
            $key = $row['uniq_name'];
            if (!empty($data[$key])) { 
                continue;                 
            }

            preg_match_all('/\{\$([^\}]+)\}/i', $row['content'], $matches);

            $data[$key] = $row['content'];
            if (isset($matches[0])) {
                //заменяем найденные переменные
                reset($matches[0]);                    
                while (list(, $value) = each($matches[0])) {
                    $index = str_replace(array('{', '}', '$'), '', $value);
                    if (isset($fixed_data[ $index ])) {
                        if (preg_match('/^\{\$('.$index.'\})/i', $data[$key]) || preg_match('/\. \{\$('.$index.'\})/i', $data[$key])) {
                            $data[$key] = str_replace($value, $fixed_data[ $index ], $data[$key]);
                        } else {
                            $data[$key] = str_replace($value, $fixed_data[ $index ], $data[$key]);
                        }    
                    } else {
                        $data[$key] = str_replace($value, '', $data[$key]);
                    }
                }
            }
        }
        
        $data = parse_headers($fixed_data['name'], $data['headline'], $data['title'], $data['description']);    
        return $data;
    }

    /**
     * Canonical
     * 
     * @param string $misc_url - урл
     * @param int $page 
     * @return string 
     */
    public static function getPageCanonical($misc_url, $page = 0) 
    {
        if (empty($misc_url)) {
            if (isset($_SERVER['SCRIPT_URL'])) {
                $misc_url = (empty($page)) ? $_SERVER['SCRIPT_URL'] : str_replace('/page-'.$page .'/', '/', $_SERVER['SCRIPT_URL']);                
            } elseif (HTTP_REQUEST_URI) {
                $misc_url = (empty($page)) ? HTTP_REQUEST_URI : str_replace('/page-'.$page .'/', '/', HTTP_REQUEST_URI);
            }
        }
        
        $url = str_replace('//', '/', CMS_URL . $misc_url);
        $url = 'https://' . str_replace('//', '/', CMS_HOST .'/'. $misc_url);
        $canonical = '<link rel="canonical" href="' . $url . '" />'."\n";                    
        return $canonical;
    }

      /**
     * Canonical
     * 
     * @param string $misc_url - урл
     * @param int $page 
     * @return string 
     */
    public static function getFilterCanonical($group_url, $param_id, $info_id) 
    {
        global $DB;
        
        $name = '';
        if (is_int($param_id) && $param_id > 0) {
            if ($param_id == 1) {
                $result = $DB->result("
                                    SELECT LOWER(tb_data.uniq_name) as param_value
                                    FROM `shop_brands` as tb_data 
                                    WHERE tb_data.id='{$info_id}'"); 
                $name = 'producer_'. $result;
            } else {
                $uniq_once = $DB->query_row("
                    SELECT LOWER(tb_data.uniq_name) as param_value, LOWER(tb_param.uniq_name) as param_name
                    FROM `shop_info_data` as tb_data 
                    INNER JOIN shop_group_param as tb_param on tb_param.info_id=tb_data.info_id  and tb_param.id='{$param_id}'
                    WHERE tb_data.id='{$info_id}'");
                $name = $uniq_once['param_name'] .'_'. $uniq_once['param_value'];
            }
        }
        
        $url = 'https://' . str_replace('//', '/', CMS_HOST .'/'. $group_url . $name . '/');
        $canonical = '<link rel="canonical" href="' . $url . '" />'."\n";                    
        return $canonical;
    }
    
    /**
     * Canonical
     * 
     * @param string $misc_url - урл
     * @param int $prev - prev page
     * @param int $next - next page
     * @return string 
     */

    public static function getCanonical($misc_url = '', $prev = 0, $next = 1) 
    {
        $canonical = '<link rel="canonical" href="https://'.CMS_HOST.''.$_SERVER['SCRIPT_URL'].'" />'."\n";       

        if ($prev == 1) {
            $canonical = '<link rel="prev" href="https://'.CMS_HOST.$misc_url.'/" />'."\n" . $canonical;
        } elseif ($prev > 1) {    
             $canonical = '<link rel="prev" href="https://'.CMS_HOST.$misc_url.'/page-'.($prev).'/" />'."\n" . $canonical;
        }
        if ($next > 1) {
            $canonical .= '<link rel="next" href="https://'.CMS_HOST.$misc_url.'/page-'.($next).'/" />'."\n";
        }
        return $canonical;
    }
    
    /**
    * Ссылки на социальные сети
    * @global type $DB
    * @return array $data 
    */
    public static function getCodeAnalytic()
    {
        global $DB;

        $Cache = new CacheSql('seo_settings', 'seo_settings', false);
        $data = $Cache->read();
        
        if (!empty($data)) {
            return $data;  
        }
        $query = "SELECT uniq_name, `value` FROM `seo_settings` WHERE active='1'";
        $data = $DB->fetch_column($query); 

        $Cache->write($data);
        
        return $data;            
    }
    
    /**
	 * Вставляем в шаблон Код аналитики 
	 *
	 * @param text $content
	 * @return text
	 */
	public static function addCodeAnalytic($content) 
    {
        if (!SEO_ACTIVE) {
            return $content;
        }
        
        $codes = self::getCodeAnalytic();
        
        if (isset($codes['head']) && !empty($codes['head'])) {
            //перед закрывающим тегом head
            preg_match("~</head>~ismU", $content, $matches, PREG_OFFSET_CAPTURE);
            $pos = (!empty($matches[0][1]))? $matches[0][1] : 0;
            $content = substr($content, 0, $pos) . $codes['head'] . substr($content, $pos);
        }
        
        if (isset($codes['body']) && !empty($codes['body'])) {
            //после открывающего тега body
            preg_match("~<body>~ismU", $content, $matches, PREG_OFFSET_CAPTURE);
            $pos = (!empty($matches[0][1])) ? $matches[0][1] : 0;
            $content = substr($content, 0, $pos) .'<body>'. $codes['body'] . substr($content, ($pos + 6));
        }
        
        if (isset($codes['bodylast']) && !empty($codes['bodylast'])) {
            //перед закрывающим тегом body
            preg_match("~</(?:body|html)>~ismU", $content, $matches, PREG_OFFSET_CAPTURE);
            $pos = (!empty($matches[0][1])) ? $matches[0][1] : 0;
            $content = substr($content, 0, $pos) . $codes['bodylast'] . substr($content, $pos);
        }
		
		return $content;
	}
        
}

?>