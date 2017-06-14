<?php
/**
 * Маршрутизатор
 * 
 * @package DeltaCMS
 * @subpackage Route
 * @version 3.0
 * @author Naumenko A.
 * @copyright c-format, 2016
 */
class Route
{
    /**
	 * Раздел, в котором находится пользователь
	 *
	 * @var int
	 */
	private $url = '';
    
    /**
     * Урл обрабатывемой страницы
     * @var type 
     */
    private $page_url = '';
    
    /** 
     * Страница магазина
     * @var bool
     */
	private $is_page_shop = false;
	
    /** 
     * Завершения ссылки на .html
     * @var bool
     */
	private $is_page_html = false;
    
    /**
     * Зарезервированные страницы
     * @var array
     */
    private $shop_reserve_page = array('novelty', 'discounts');  
    /**
     * Части урла
     * @var array
     */
    private $party = array();
    
    /**
     * Прикрепленный раздел
     * @var array
     */
    private $item = array();
    
    /**
     * Екземпляр класса
     * @var Route
     */
    private static $instance = null;
    
    /**
     * DEBUG
     * @var bool
     */
    private $debug = false;
    //private $debug = true;
    
    /**
     * @return Singleton
     */
    public static function getInstance($url)
    {
        if (null === self::$instance)
        {
            self::$instance = new self($url);
        }
        return self::$instance;
    }
    
    private function __clone() {}
    
    /**
	 * Конструктор класса
	 *
	 * @param string $url
	 * @param DB $DB
	 * @param string $table_name
	 */
	private function __construct($url) 
    {
        
        if (!IS_DEVELOPER) {
            $this->debug = false;
        }
        
        $this->url = $this->clearURL($url);
        if ($this->debug) {
            x('URL');
            x($this->url);
        }
        
        if (empty($this->url)) { //главная
           $this->data = $this->get($this->url);
           return;
        }
        
        $this->party = $this->urlToParty();
        
        $this->data = array();
       
        /*
        //проверка магазина
        if (is_module('Shop')) {
            $data = array();
            if (count($this->party) == 2) {                
                $data = $this->isProduct();
                $this->page_url = 'shop/product';                                    
            }
            
            if ($this->debug) {
                x($this->party);
            }   
            if (!empty($data) && $data['active'] == 0) {
                //возможно нужно добавлять редирект
                $this->data = array();
                return;
            }
            
            if (empty($data)) {
                $this->page_url = 'shop';
                $data = $this->isGroup();                
            }
           
            if (empty($data) && count($this->party) > 1) {
                $data = $this->isFilter();                
            } 
         
            if (!empty($data)) {                
                $this->is_page_shop = true;   
                if ($this->debug) {
                    x($this->data);
                } 
                if ($this->activeGroup($data['group_id'])) {
                    $this->item = $data;
                    $this->data = $this->get($this->page_url);
                    $this->data['shop_group_id'] = $data['group_id'];
                } else {
                    $this->page_url = $this->url;
                }
                //else 404
            }
        }
        
        if (!$this->is_page_shop) {
        */     
            $this->page_url = $this->url;
            
            //страница сайта            
            $page = $this->checkPage();            
            
            if (!empty($page)) {                
                $this->data = $page;     
                
                if (!empty($this->party) ) {
                    //проверка прикрепленных разделов
                    $this->item = $this->checkItem($page);                    
                    
                    if (empty($this->item) && isset($page['id']) && $page['id'] == 1006) { 
                        //фильтр tradein
                        $_GET['filter'] = $this->party[0];  
                        
                    } elseif (empty($this->item)) {
                        $this->data = array();                                                
                    } 
                    
                } elseif (empty($this->item) && isset($page['table_name']) && !empty($page['is_item'])) {
                    global $DB;
                    
                     //страницы новостей
                    $table = cmsTable::getInfoByAlias('default', $page['table_name']);         
                    $field_name = str_replace("_" . $table['default_language'], "_" . LANGUAGE_CURRENT, $table['fk_show_name']);
                
                    $this->item = $DB->query_row(""
                    . " SELECT "
                    . "     id, 0 as group_id, '1' as is_item, '{$page['table_name']}' as table_name, `{$field_name}` as name" 
                    . " FROM `{$page['table_name']}` WHERE id = '{$page['object_id']}' and active='1' ");                    
                    
                }
            }
            //x($this->item , false, true);
        //}
        
    }
    
    /**
     * Является ли страница магазином
     * @return bool
     */
    public function isShopPage()
    {
        return $this->is_page_shop;
    }
    
    /**
     * Возращает прикрепленный раздел
     * @return data
     */
    public function getItem()
    {  
        if ($this->debug) {
            x('getItem :: item');
            x($this->item);
        }
        
        return $this->item;
    }
    
    /**
     * Возращает страницу
     * @return data
     */
    public function getPage()
    {   
        if ($this->debug) {
            x('getPage :: data');
            x($this->data);
        }        
        return $this->data;
    }
    
    /**
     * Путь к страницы (Хлебные крошки)
     * @global DB $DB
     * @return array
     */  
    public function getPath() 
    {
        global $DB;
        
        $query = "
                SELECT
                    tb_structure.id,
                        tb_structure.name_".LANGUAGE_CURRENT." AS name,
                        IFNULL(tb_structure.substitute_url, CONCAT(tb_structure.url, '/')) AS url
                  FROM site_structure AS tb_structure
            INNER JOIN site_structure_relation AS tb_relation ON tb_relation.parent = tb_structure.id		
                 WHERE tb_relation.id = '{$this->data['id']}'
                   AND tb_structure.uniq_name <> 'shop'
                   AND tb_structure.uniq_name <> 'product'
              ORDER BY tb_relation.priority ASC
                 LIMIT 1, 10";
        $path = $DB->query($query);        
        foreach ($path as $i => $row) {
            if (!preg_match('/^http/', $row['url'])) {
                $row['url'] = trim(str_replace(CMS_HOST . '/', '', $row['url']), '/');
                $path[$i]['url'] = '/' . LANGUAGE_URL . $row['url'] . '/';
            }
        }
          
        if (empty($this->item)) {
            //Нет прикрепленных разделов
            if ($this->debug) {            
                x('getPath :: path');
                x($path);
            }
            end($path);
            $key = key($path);
            $path[$key]['url'] = '';
            return $path;
        }
        
        if (isset($this->item['id']) && ($this->item['table_name'] == 'shop_group' || $this->item['table_name'] == 'shop_product')) {            
            
            $group_id = ($this->item['table_name'] == 'shop_product') ? $this->item['group_id'] : $this->item['id'];
            
            //Аксессуары
            $query = "
                    SELECT
                            tb_group.id,
                            tb_group.name_".LANGUAGE_CURRENT." as name,                                            
                            tb_group.uniq_name,                               
                            CONCAT('/".LANGUAGE_URL."', tb_group.url, '/') as url
                      FROM shop_group as tb_group
                INNER JOIN shop_group_relation as tb_relation on tb_relation.parent=tb_group.id
                     WHERE tb_relation.id='{$group_id}' and tb_group.group_id > 0
                  ORDER BY tb_relation.priority
            ";
            $shop_path = $DB->query($query, "id");             
            if (!empty($shop_path)) {  
                foreach ($shop_path as $row) {
                    $path[] = $row;
                }    
            }            
            
        } elseif ($this->item['is_item'] == 0 
                && ($this->item['table_name'] !== $this->data['table_name']
                ||
                ($this->item['group_id'] != $this->data['object_id'])) ) {
            //страницы новостей
            $table = cmsTable::getInfoByAlias('default', $this->data['table_name']);         
            
            if (!empty($table['relation_table_name'])) {
            
                $field_name = str_replace("_" . $table['default_language'], "_" . LANGUAGE_CURRENT, $table['fk_show_name']);
            
                $parent_item = $DB->query("select tb_relation.`parent` as id, tb_table.`{$field_name}` as name, tb_table.uniq_name "
                            . " from `{$table['relation_table_name']}` as tb_relation " 
                            . " inner join `{$this->data['table_name']}` as tb_table on tb_table.id = tb_relation.parent "
                            . " where tb_relation.id='{$this->item['group_id']}' AND tb_relation.priority > "
                            . " (select priority from news_type_relation where parent = '{$this->data['object_id']}' and id= {$this->item['group_id']})  "
                            . " order by tb_relation.priority ", "id");    
                if ($this->debug ) {            
                    x('getPath :: withItem :: RelationItem');
                    x($parent_item);
                }
              
                $url = $path[count($path) - 1]['url'];
                reset($parent_item);
                while (list($key, $row) = each($parent_item)) {           
                    $url .= $row['uniq_name'] . '/';
                    $row['url'] = $url;
                    $path[] = $row;
                }
                
            } 
        }
       
        if ($this->item['table_name'] != 'shop_group' && !empty($this->item['id']) && empty($this->item['is_item'])) {
            $path[] = $this->item;                    
        }
       
        if ($this->debug) {            
            x('getPath :: withItem :: path');
            x($path);
        }
        end($path);
        $key = key($path);
        $path[$key]['url'] = '';
                
        return $path;
    }
    
    /**
     * Перестроение хлебных крошек, для страниц вида woman/novelty/clothing/
     * @param array $path
     * @return array
     */
    public function refreshWithShop($path)
    {
        $shopuniq = globalVar($_REQUEST['shopuniq'], '');
        
        if (empty($shopuniq)) {
            return $path;
        }
        
        $fullpath = array();
        ///для страниц типа woman/novelty/clothing/
        $name = cmsMessage::get('MSG_SITE_PAGENAME_'.($shopuniq));
        
        $i = 1; $url = '';
        foreach ($path as $id=>$row) {
            if ($i == 1) {                          
                $fullpath[$id] = $row;
                $row = array('name'=>$name, 'url' => $row['url'] . $shopuniq . '/', 'id' => 0);
                $id = 0;                
            } else {
                $parts = explode('/', trim($row['url'], '/'));
                array_splice($parts, 2, 0, $shopuniq);
                $row['url'] = '/'. implode('/', $parts) .'/';
            }
            $fullpath[$id] = $row;
            $i++;
        }
        return $fullpath;
    }
    
    /**
     * Завершение отладки
     */
    public function finishDebug()
    {
        if ($this->debug) {
            die();
        }
    }
    
    /**
     * Возращает страницу по урлу
     * @global DB $DB
     * @param string $url
     * @return array
     */
    private function get($url)
    {  
        global $DB;
        
        $host_default = Site::getSiteHost();

        $site_url = (empty($url)) ? $host_default : $host_default.'/'.$url;
            
        $query = "
            SELECT id, url, LOWER(tb_structure.url) AS filename
            FROM `site_structure` AS tb_structure
            WHERE tb_structure.url='" . $site_url . "' AND tb_structure.active='1'
        ";
        return $DB->query_row($query);
    }
    
    /**
     * Проверка прикрепленных разделов
     *  /producer/scott/
     * 
     * @param array $page
     * @return array $item
     */
    private function checkItem($page)
    {
        global $DB;
          
        if (!isset($page['table_id']) || empty($page['table_id'])) {
            return array();
        }
        
        $select = $this->queryRelationTable($page['table_id']);        
        $objects = array();
        
        // проверка всех промежуточных значений ссылки 
        foreach ($this->party as $value) {                    
            $query = str_replace('%s', $value, implode(' UNION ', $select));  
            $objects = $DB->query_row($query);
            if (empty($objects)) {                
                break;
            }
        }        
        if ($this->debug) {
            x('checkItem :: finds object');
            x($objects);
        }
        return $objects;    
    }
    
    /**
     * Запросы с Таблиц, наследуемые от исходной
     * 
     * @global DB $DB
     * @param int $table_id
     * @return array
     */
    private function queryRelationTable($table_id)
    {
        global $DB;
       
        $select = array();
        
        //родительские таблицы для данного раздела
        $query = "
                SELECT 
                        tb_table.name as table_name, tb_table.id as table_id, tb_parent.name as parent_field 
                FROM cms_table AS tb_table
          INNER JOIN cms_field AS tb_field on tb_table.id=tb_field.table_id
          LEFT JOIN cms_field AS tb_parent on tb_parent.id=tb_table.parent_field_id
          INNER JOIN cms_field AS tb_field_uniq on tb_table.id=tb_field_uniq.table_id AND tb_field_uniq.name='uniq_name'
          INNER JOIN cms_field AS tb_field_active on tb_table.id=tb_field_active.table_id AND tb_field_active.name='active'
               WHERE 
                      (tb_table.id='$table_id' AND tb_table.fk_show_id <> '0')
                  OR
                      (tb_field.fk_table_id='$table_id' AND tb_table.parent_field_id <> '0' AND tb_table.fk_show_id <> '0')
            GROUP BY tb_table.name          
        ";
        $tables = $DB->query($query); 
        
        $cms_fields_active = $DB->fetch_column("SELECT table_id FROM `cms_field` WHERE `name`='active_admin'");
        
        $where_cond = '';
        if (Auth::isAdmin()) {
            $where_cond = " OR active_admin = 1 ";
        }
            
        foreach ($tables as $row) {
            if (!empty($row['parent_field'])) {
                $field = "`{$row['parent_field']}` AS group_id, ";
            } else {
                $field = "'0' AS group_id, ";
            }
            $field .= ($row['table_name'] == 'auto_modification') ? '`name` ' : " name_".LANGUAGE_CURRENT." as name";            
            
            $where = (in_array($row['table_id'], $cms_fields_active)) ? $where_cond : '';
            $select[] = "(SELECT id, '0' as is_item, '{$row['table_name']}' as table_name, $field "
                    . "  FROM `{$row['table_name']}` WHERE uniq_name ='%s' and (active='1' $where) )";
        }        
        return $select;
    }
    
    /**
     * Проверка страницы
     * @global DB $DB
     * @return array
     */
    private function checkPage() 
    {
        global $DB;
        
        $host_default = Site::getSiteHost();
        
        $party = $this->party;
        
        $where = (Auth::isAdmin()) ? " OR tb_structure.active_admin = 1 " : "";
            
        $page = array();
        while (count($party) > 0) { 
            $query = "SELECT 
                            tb_structure.id, LOWER(tb_structure.url) AS filename, LOWER(tb_structure.url) as url,
                            IF(tb_structure.item_id > 0, 1, 0) is_item,
                            tb_item.object_id, 
                            tb_item.table_name,
                            tb_table.id AS table_id
                        FROM site_structure AS tb_structure
                   LEFT JOIN site_structure_item AS tb_item ON tb_item.id=tb_structure.item_id
                   LEFT JOIN cms_table AS tb_table ON tb_table.name=tb_item.table_name
                       WHERE tb_structure.url = '" . $host_default . '/' . implode('/', $party) ."' AND 
                    (tb_structure.active = 1 $where )";
            
            $page = $DB->query_row($query);
            
            if (count($page) > 0) {                     
                $turl = implode('\/', $party);
                $turl = preg_replace('/^'.$turl.'/', '', $this->url);   
                
                $this->url = implode('/', $party);        
                $this->party = (empty($turl)) ? array() : explode('/', trim($turl, '/'));
                        
                break;                
            }
            array_pop($party);                
        }
        
        return $page;
    }
       
    /**
     * Разбивка урла на массив
     * @return array
     */
    private function urlToParty()
    {
        return explode('/', $this->url);
    }
    
    /**
     * очистка урла от дополнительных параметров
     * @param string $url
     * @return string
     */
    private function clearURL($url)
    {
        if (preg_match('/(\/page-(\d)+)/', $url, $matches)) {
            if (isset($matches[2])) {
                $_GET['_page'] = $matches[2];
                $_GET['_REWRITE_URL'] = $url = str_replace($matches[0], '', $url);
            }
        }
        
        if (preg_match('/(\.html)/', $url, $matches )) {
            $this->is_page_html = true;
        }
        
        $clearurl = str_replace(array(".html", ".htm"), "", $url);
        $params = explode('/', trim($clearurl, '/'));
        if (LANGUAGE_CURRENT != LANGUAGE_SITE_DEFAULT && isset($params[0]) && $params[0] == LANGUAGE_CURRENT){
            array_shift($params);
        }
        
        return implode('/', $params);
    }
    
    /**
     * Проверка, является ли страницой товара
     * @global DB $DB
     * @return array
     */
    private function isProduct()
    {
        global $DB;
        
        $data = $DB->query_row("
                    SELECT 
                        tb_product.id, tb_product.group_id, tb_product.active,
                        tb_product.name_".LANGUAGE_CURRENT." AS name, 
                        'shop_product' as table_name,                        
                        CONCAT('/".LANGUAGE_URL."', tb_product.uniq_name, '".SHOP_PRODUCT_URL_END."') as url
                    FROM shop_product as tb_product 
                    WHERE tb_product.`uniq_name`='". implode('/', $this->party) . "'");
        return $data;        
    }
     
    /**
     * Проверка страницы с фильтром
     * @return array
     */
    private function isFilter($url = '')
    {
        $params = (empty($url)) ? $this->party : explode('/', $url);
        $filter = array_pop($params);        
        $data   = $this->isGroup(implode('/', $params));

        if (count($data) > 0) {
            //существование параметров фильтра проверяется при обработке
            if (in_array($params[0], $this->shop_reserve_page)) {
                unset($params[0]);
            }
            $_GET['filter'] = $filter;
            $_GET['shop'] = implode('/', $params);
        } 
        return $data;
    }
    
    /**
     * Страница категории товаров
     * @global DB $DB
     * @param string $url
     * @return array
     */
    private function isGroup($url = '')
    {
        global $DB;      
        
        $parts = ((empty($url)) ? $this->party : explode('/', $url));
        
        if (!empty($parts) && in_array($parts[0], $this->shop_reserve_page)) {
            //новинки/распродажи
            $_REQUEST['shopuniq'] = $parts[0];            
            unset($parts[0]);
            $url = implode('/', $parts);
            $this->page_url = $this->party[0];           
        }
            
        $parent_shop = Shop::parentShop();
        $url = $parent_shop .'/' . ((empty($url)) ? $this->url : $url);
        
        $data = $DB->query_row("SELECT 'shop_group' as table_name, tb_group.id, tb_group.id as group_id, tb_group.url, "
                . "     tb_relation.priority, tb_group.uniq_name "
                . " FROM shop_group as tb_group "
                . " INNER JOIN shop_group_relation as tb_relation on tb_relation.id=tb_group.id and tb_relation.id=tb_relation.parent " 
                . " WHERE tb_group.`url` = '$url' and tb_group.active='1'");
        
        if (count($data) > 0 && $data['priority'] == 5) {
            $_GET['filter'] = 'category_' . $data['uniq_name'];
            //return array();
        }
        return $data;
    }
    
    /**
     * проверка активны ли все родительские категории
     * @global DB $DB
     * @param int $group_id
     * @return bool
     */
    private function activeGroup($group_id)
    {
        global $DB;
        
        $noactive = $DB->result("SELECT count(tb_relation.id) FROM shop_group_relation as tb_relation                 
                INNER join shop_group as tb_group on tb_group.id=tb_relation.parent
                WHERE `tb_relation`.id='{$group_id}' and tb_group.active='0'");
        return ($noactive > 0) ? false : true;
    }
}