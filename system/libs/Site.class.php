<?php
/**
 * Обработка запросов и вывод структуры сайта
 * 
 * @package DeltaCMS
 * @subpackage Site
 * @version 3.0
 * @author Naumenko A.
 * @copyright c-format, 2016
 */
class Site 
{
	/**
	 * Раздел, в котором находится пользователь
	 *
	 * @var int
	 */
	public $structure_id = 0;
	
	/**
	 * Сайт, на котором находится пользователь
	 *
	 * @var int
	 */
	public $site_id = 0;
	
	/**
	 * Адрес сайта, на котором находится пользователь
	 *
	 * @var string
	 */
	public $site_url = '';
	
	/**
	 * Родительские разделы
	 *
	 * @var array
	 */
	public $parents = array();
	 
    /**
	 * Id object прикрепленного раздела
	 * @var int
	 */
	private  $item_object_id = 0;
        
    /**
	 *  Таблица раздела
	 *  @var string
	 */
	private  $item_table_name = '';
       
    /**
     * Категория товаров
     * @var int
     */
    private $shop_group_id = 0;
    
    /**
	 *  Не найдена страница
	 *  @var boolean
	 */
	public  $flag404 = false;
    
    /**
     * Выводить текст страницы
     * @var boolean
     */
    public $showContent = true;

    /**
     * Выводить текст страницы после php файла
     * @var boolean
     */
    public $afterContent = false;
        
	/**
	 * Путь к странице
	 *
	 * @var string
	 */
	public $url;
	
	/**
	 * Название таблицы, которая обрабатывается
	 *
	 * @var string
	 */
	public $table_name;
	
	/**
	 * Имя файла с контентом
	 *
	 * @var string
	 */
	public $filename = '';
	
	/**
	 * Название 404 шаблона
	 *
	 * @var string
	 */
	public $error_template_name = '';
	
	/**
	 * Шаблон по умолчанию
	 *
	 * @var string
	 */
	public $default_template_name = '';
	
	/**
	 * id группы авторизации
	 *
	 * @var int
	 */
	public $auth_group_id = 0;
	
    /**
     * Путь
     * 
     * @var type 
     */    
    public $breacrumbs = array();
	
    /**
     * Есть страница в сео модуле
     * @var bool
     */
    public $isSeo = false;
    
	/**
	 * Конструктор класса
	 *
	 * @param string $url
	 * @param DB $DB
	 * @param string $table_name
	 */
	function __construct($url, $table_name) 
    {
        global $DB;

        $this->table_name = $table_name;

        if (substr($url, -3) == '.js' || substr($url, -4) == '.css') {
            trigger_error("Impossible to serve url which ends on .js and .css as html request. URL: $url", E_USER_ERROR);
            exit;
        }    	

        // удаляем язык
		$url = trim(strtolower($url), '/');
        if (substr($url, 0, 2) == LANGUAGE_CURRENT && preg_match("/^\/".LANGUAGE_CURRENT."\/(.*)/", $_SERVER['REQUEST_URI']) )  {
            $url = substr($url, 3);
        }
       
        // Информация о сайте
        $info = $this->defaultInfoSite();
		if (!empty($info)) {
			$this->auth_group_id = $info['auth_group_id'];
			$this->site_url      = $info['url'];
			$this->site_id       = $info['id'];
			$this->error_template_name = $info['error_template_name'];
			$this->default_template_name = $info['default_template_name'];
			
			//Форсирование использования https, если указано в настройках системы
			if ($info['force_https'] && $_SERVER['REQUEST_METHOD'] == 'GET' && HTTP_SCHEME == 'http') {
				header('Location: https://' . CMS_HOST . HTTP_REQUEST_URI);
				exit;
			}
		} 
	
        if (CMS_INTERFACE == 'SITE') {
            
            //даже если страница существует, если добавлен редирект, перебрасываем
            $this->checkRedirectLink($url);
                        
            $Route = Route::getInstance($url);  
            
            $info  = $Route->getPage();
                       
            if (!empty($info)) {
                
                if (isset($info['shop_group_id'])) {
                    $this->shop_group_id = $info['shop_group_id'];
                }
                
                $item  = $Route->getItem();                
                
                if (count($item) > 0) {
                    $this->item_object_id  = $item['id'];
                    $this->item_table_name = $item['table_name'];
                } elseif (isset($info['table_name'])) {
                    //к странице прикреплен раздел, но не выбран
                    $this->item_table_name = $info['table_name'];
                }
            }
            //x($item, false, true);
            $Route->finishDebug();
            
        } else {            
            $query = "
                SELECT id, url, LOWER(tb_structure.url) AS filename
                  FROM `$this->table_name` AS tb_structure
                 WHERE tb_structure.url='".addcslashes($url, "'")."'
                   AND tb_structure.active='1'
            ";
            $info = $DB->query_row($query);            
        } 
        		
        if (empty($info)) {            
            // 404
            $this->structure_id = 0;
            $this->parents = array();
            return;            
            
        }

		$this->structure_id = $info['id'];
		$this->url = $info['url'];
		$this->filename = $info['filename'];
                        
		$query = "
			SELECT parent, priority 
			FROM `{$this->table_name}_relation`
			WHERE id='$this->structure_id' 
			ORDER BY priority ASC
		";
		$this->parents = $DB->fetch_column($query, 'priority', 'parent');
        
	}

    /**
     * Страница магазина
     * @return type
     */
    public function idShopPage()
    {
        $Route = Route::getInstance('');
        
        return $Route->isShopPage() ? $this->shop_group_id : 0;
    }
    
    /**
     * Информацио о сайте
     * @global DB $DB
     * @return array
     */
    private function defaultInfoSite() 
    {
        global $DB;
        
        $Cache = new CacheSql('site_structure_site', 'site_structure_site_main_info', false);
        $data = $Cache->read();
        
        if (!empty($data)) {
            return $data;
        }
        
        $cms_host_no_www = (strtolower(substr(CMS_HOST, 0, 4)) == 'www.') ? substr(CMS_HOST, 4): CMS_HOST;
        // Информация о сайте
        $query = "
        (
            select 
                -1 as priority,
                tb_site.url,
                tb_site.id,
                tb_site.auth_group_id,
                tb_site.force_https,
                concat(tb_error_template_group.name, '/', tb_error_template.name) as error_template_name,
                concat(tb_default_template_group.name, '/', tb_default_template.name) as default_template_name
            from site_structure_site as tb_site
            inner join site_structure_site_alias as tb_alias on tb_site.id = tb_alias.site_id
            inner join site_template as tb_error_template on tb_site.error_template_id = tb_error_template.id
            inner join site_template_group as tb_error_template_group on tb_error_template.group_id = tb_error_template_group.id
            inner join site_template as tb_default_template on tb_site.default_template_id = tb_default_template.id
            inner join site_template_group as tb_default_template_group on tb_default_template.group_id = tb_default_template_group.id
            where tb_alias.url in ('".CMS_HOST."', '$cms_host_no_www') and tb_site.active='1'
        ) UNION (
            select 
                tb_site.priority,
                tb_site.url,
                tb_site.id,
                tb_site.auth_group_id,
                tb_site.force_https,
                concat(tb_error_template_group.name, '/', tb_error_template.name) as error_template_name,
                concat(tb_default_template_group.name, '/', tb_default_template.name) as default_template_name
            from site_structure_site as tb_site
            inner join site_template as tb_error_template on tb_site.error_template_id = tb_error_template.id
            inner join site_template_group as tb_error_template_group on tb_error_template.group_id = tb_error_template_group.id
            inner join site_template as tb_default_template on tb_site.default_template_id = tb_default_template.id
            inner join site_template_group as tb_default_template_group on tb_default_template.group_id = tb_default_template_group.id
            where tb_site.active='1'
        ) order by priority asc limit 1
		";
		$data = $DB->query_row($query);
        
        $Cache->write($data);
        
        return $data;
    }
    
	/**
	 * Возвращает id сайта по имени хоста
	 * @param string $hostname
	 * @return int
	 */
	public static function getSiteId($hostname) 
    {
		global $DB;
		
		$query = "
			select tb_site.id
			from site_structure_site as tb_site
			inner join site_structure_site_alias as tb_alias on tb_site.id = tb_alias.site_id
			where tb_alias.url = '$hostname'
		";
		return $DB->result($query, 0);
	}
	
    /**
     * Проверка, является ли страница главной
     * 
     * @return bool 
     */
    public function isMainPage()
    {
        return ($this->site_id == $this->structure_id) ? true : false;
    }
        
	/**
	 * Информация о текущей странице сайта
	 *
	 * @return array
	 */
	public function getInfo() 
    {
		global $DB;
		
		if ($this->table_name != 'site_structure') {
			return array();
		}
		        
		$info = $DB->query_row("
			SELECT
				tb_structure.*,
				unix_timestamp(tb_structure.last_modified) as last_modified,
				ifnull(concat(tb_design_group.name, '/', tb_design.name), '$this->default_template_name') AS template_design
			FROM site_structure AS tb_structure
			LEFT JOIN site_template AS tb_design ON tb_structure.template_id = tb_design.id
			LEFT JOIN site_template_group AS tb_design_group ON tb_design.group_id = tb_design_group.id
			WHERE tb_structure.id = '$this->structure_id'
		");
	
		if (empty($info)) {
            
           $info = $this->get404Info();
           return $info;
        } 
        
        $info = Misc::parseLangFields($info);
                        
        // Определяем обработчик шаблона
        $info['template_parser'] = SITE_ROOT . 'design/' . $info['template_design'] . '.inc.php';

        if ($info['access_level'] != 'any') {
            $query = "SELECT group_id FROM site_group_relation WHERE structure_id = '$this->structure_id'";
            $info['access_groups'] = $DB->fetch_column($query);
        } else {
            $info['access_groups'] = array();
        }
       
        $path = $this->getPath();
        $last = array_pop($path);
                
		$meta_data = array();
		if (SEO_ACTIVE && isset($info['id'])) {  
            $meta_data = (empty($this->item_table_name)) ? Seo::parseHeaders($this->table_name) : Seo::parseHeaders($this->item_table_name);             
            if (!empty($meta_data)) {
                $this->isSeo = true;
            }
        }   
                
        if (isset($last['name']) && (empty($meta_data) || !isset($meta_data['name']))) {
            $data = parse_headers($last['name'], $last['name'], $last['name'], $last['name'], '');             
            $meta_data = array_merge($meta_data, $data);
            
        } elseif (isset($info['name'])) {
            $data = parse_headers($info['name'], $info['name'], $info['name'], $info['name'], '');
            $info = array_merge($info, $data);
        }
            
        if (CMS_INTERFACE == 'SITE' && isset($info['id'])) {
            //Добавляем проставленные инфоблоки
            $infloBlocks = $DB->fetch_column("select id from site_infoblock where structure_id = '{$info['id']}' order by priority");
            foreach ($infloBlocks as $infoblock_id) {                        
                $info['content'] .= TemplateUDF::infoblock(array('name'=>$infoblock_id));
            }
        }
        
        //прикреплена галерея к новости
        if (CMS_INTERFACE == 'SITE' && $info['gallery_id'] != 0) {			
			$info['content'] .= "<div class='conContent'>" . TemplateUDF::gallery(array('name' => $info['gallery_id'])) . "</div>";
		}
           
		//заменяем значения            
        $info = array_merge($info, $meta_data);
		      
        $info['image_top'] = Uploads::getIsFile('site_structure', 'image_top', $info['id'], $info['image_top'], '');
        $info['image_mob'] = Uploads::getIsFile('site_structure', 'image_mob', $info['id'], $info['image_mob'], '');
        
        if ((IS_TABLET || IS_MOBILE) && !empty($info['image_mob'])) {
            $info['image_top'] = $info['image_mob'];
        }    
		return $info;
	}
	
	/**
	 * Функция возвращает путое значение, если доступ к системе есть, в случае, если доступа нет, то выводится окно ввода логина
	 *
	 * @param enum $access_level
	 * @param array $access_groups
	 * @return text
	 */
	public function checkAccess($access_level, $access_groups) 
    {
		if ($access_level == 'any') {
			return '';
		}
		
		if (!Auth::isLoggedIn()) {
			// Страница предназначена только для зарегистрированных пользователей, а пользователь даже не зашёл в систему, выводим форму для входа
			return Auth::displayLoginForm();
		}
		
		$user = array_merge(array('group_id' => 0, 'confirmed' => false, 'checked' => false), $_SESSION['auth']);
		
		if (!empty($access_groups) && !in_array($user['group_id'], $access_groups)) {
			
			// Страница доступна только для пользователей из определенных групп, а текущий пользователь не принадлежит ни одной из этих групп
			$TmplContent = new Template(TEMPLATE_ROOT.'user/error_badgroup');
			return $TmplContent->display();
			
		} elseif ($access_level == 'confirmed' && !$user['confirmed']) {
			
			// Страница предназначена только для пользователей, которые подтвердили свой e-mail
			$TmplContent = new Template(TEMPLATE_ROOT.'user/error_confirmed');
			return $TmplContent->display();
			
		} elseif ($access_level == 'checked' && !$user['checked']) {
			
			// Страница предназначена только для пользователей, которых подтвердил администратор системы
			$TmplContent = new Template(TEMPLATE_ROOT.'user/error_checked');
			return $TmplContent->display();
			
		}
		
		return '';
	}
	
	/**
	 * Возвращает путь к текущей странице
	 * 
	 * @return array
	 */
	public function getPath() 
    {      
        
        if (!empty($this->breacrumbs)) {
            return $this->breacrumbs;
        }
        
        $Route = Route::getInstance('');
        $path  = $Route->getPath();
        
        $path_main = array(
                        'id'   => 0, 
                        'name' => cmsMessage::get("MSG_SITE_HOME"), 
                        'url'  => '/' . LANGUAGE_URL
                    );
        $this->breacrumbs = array_merge(array($path_main), $path);
        
		return $this->breacrumbs;
	}
	
	/**
	 * Возвращает шаблон из хлебными крошками
	 * 
	 * @param array $path - переданные не стандартные хлебные крошки	 
	 * @return array
	 */
	public function getTemplatePath($path = array())
    {
        if (empty($path)) {
            $path = $this->getPath();
        }

        if (count($path) == 1) {
            return '';
        }

        $Template = new Template("site/breacrumbs");
        $Template->iterateArray("/path/", null, $path);                
		return $Template->display();
	}
        
	/**
	 * Определяем разделы, которые находятся в выбранном меню
     *  
     * @param int $structure_id - parent_id
	 * @param string $menu_type - тип меню
     * @param int $level - уровень 1 или 2, мах 3
     * @param boolean $is_cache добавлять ли значение в кеш
	 * @return array
	 */
	public function getMenu($structure_id = -1, $menu_type = 'top_menu', $level = 1, $is_chache = true)
    {
		global $DB;
		                
		if ($structure_id < 0) {
            $structure_id = $this->site_id;
        }
       
        $data = $this->getMenuCache($structure_id, $menu_type, $is_chache); //все уровни меню, возращаем из кеша
        $data = $this->parseMenu($data, (($level > 1) ? true : false));
         
        if ($level > 1) {
            //второй уровень
            foreach ($data as $index => $row) {
                if (isset($row['submenu'])) {       
                    $next = $this->parseMenu($row['submenu'], (($level > 2) ? true : false));
                    if ($level > 2) {
                        //третий уровень
                        foreach ($next as $subindex => $subrow) {
                            if (isset($subrow['submenu'])) {                    
                                $next[$subindex]['submenu'] = $this->parseMenu($subrow['submenu'], false);
                            }
                        }
                    }
                    $data[$index]['submenu'] = $next;
                }
            }
        } 
               
		return $data;
	}

    /**
     * Обработка пунком меню
     * 
     * @param array $data
     * @param bool $show_submenu - если false, тогда подпункты удаляются
     * @return array
     */
    private function parseMenu($data, $show_submenu)
    {            

        reset($data);
        while(list($index, $row) = each($data)) {
            
            $data[$index]['class'] = (in_array($row['id'], $this->parents)) ? 'active' : 'node';
            
            if (!preg_match('/^http/', $row['url'])) {
                $row['url'] = trim(str_replace(CMS_HOST . '/', '', $row['url']), '/');
                $data[$index]['url'] = '/' . LANGUAGE_URL . $row['url'] . '/';
            }
        
            if (HTTP_REQUEST_URI == $data[$index]['url']) {
                $data[$index]['current'] = 1;    
                $data[$index]['url'] = '';  
                $data[$index]['class'] = 'active';
            }
            
            if (!$show_submenu && isset($row['submenu'])) {
                unset($data[$index]['submenu']);
            }
        } 
        return $data;
    }
    
    /**
     * Выборка всех пунктов меню и запись в кеш
     * @param int $structure_id
     * @param enum $menu_type (top_menu, left_menu)
     * @param boolean $is_cache добавлять ли значение в кеш
     * @return array
     */
    public function getMenuCache($structure_id = -1, $menu_type = 'top_menu', $is_chache = true)
    {                  
		if ($structure_id < 0) {
            $structure_id = $this->site_id;
        }
         
        $Cache = new CacheSql('site_structure', 'menu_' . $menu_type . '__' . $structure_id);
        $data = array();//$Cache->read_by_time();
        
        if (!empty($data) && $is_chache) {
            return $data;  
        }
        
        $data = $menu = array();
        for ($i=0; $i < 5; $i++) {
            $level = $this->menu_query($structure_id, $menu_type);
            if (empty($level)) {                
                break;
            }
            $structure_id = array_keys($level);
            $data[] = $level;            
        } 
        
        $len = count($data);
        for ($l = $len - 1; $l > 0; $l--) {
            $level = $data[$l];
            foreach ($level as $id => $row) {
                if (isset($data[$l-1][$row['structure_id']])) {
                    $data[$l-1][$row['structure_id']]['submenu'][$id] = $row;
                }
            }
            unset($data[$l]);
        }
       
        if (isset($data[0])) {
            $menu = $data[0];
        }
        
        if ($is_chache) {
            $Cache->write($menu);
        }
        
        return $menu;        
    }
    
    /**
     * Запрос по выборке пунков
     * @global DB $DB
     * @param int $structure_id
     * @param enum $menu_type (top_menu, left_menu)
     * @return array
     */
    private function menu_query($structure_id, $menu_type){
        
		global $DB;
		 
        if (empty($structure_id)) {
            return array();
        }
        
        $query = "SELECT
                        tb_structure.id,
                        tb_structure.structure_id,
                        tb_structure.name_".LANGUAGE_CURRENT." AS name,
                        tb_structure.uniq_name,
                        IFNULL(tb_structure.substitute_url, CONCAT(tb_structure.url, '/')) AS url
                     FROM site_structure AS tb_structure
                    WHERE FIND_IN_SET('$menu_type', tb_structure.show_menu) > 0 "
                         . where_clause('tb_structure.structure_id', $structure_id) ."
                      AND tb_structure.active='1' 
                 ORDER BY tb_structure.priority ASC"; 
         $data = $DB->query($query, "id");
         
         return $data;
    }
	/**
     * Шаблон 404
     * 
     * @global DB $DB
     * @return array
     */
	public function get404Info()
    {
               
		$info = array(
			'template_design' => $this->error_template_name, 
			'cache' => 'false', 
			'name' => '', 
			'headline' => '', 
			'title' => '', 
			'keywords' => '', 
			'description' => '', 
			'access_level' => 'any',
			'access_groups' => array(),
			'substitute_url' => '',
			'last_modified' => time(),
			'content' => '',
		);
        
		header("HTTP/1.0 404 Not Found"); 
		header("HTTP/1.1 404 Not Found"); 
		header("Status: 404 Not Found"); 
		/*
        if (CMS_VINZER_CLOSE_SITE && !in_array(HTTP_IP, dexplode(CMS_VINZER_CLOSE_SITE_IP))) {
            $info['template_design'] = 'vinzerhtml/index';
        }*/
		// Определяем обработчик шаблона
		$info['template_parser'] = SITE_ROOT.'design/'.$info['template_design'].'.inc.php';
		return $info;
	}
	
    
    /**
     * Быстрый вывод 404 ошибки
     * 
     * @param bool $add_to_log добавление инфо в лог
     */
    public function cross404($add_to_log = true)
    {    
        $this->getErrorTemplate();

        $page_info = $this->get404Info();
        if ($add_to_log) { 
            $this->log404();             
        }
        
        $TmplDesign = new Template(SITE_ROOT.'design/'.$page_info['template_design']);
        $TmplDesign->set($page_info);

        $Site = $this;
        if (is_file($page_info['template_parser'])) {
           require_once($page_info['template_parser']);
        }

        $content = $TmplDesign->display();
        echo mod_deflate($content);
        exit; // established to stop viruses that used to be added at the end of file iframe
    }
        
    /**
     * Шаблон для 404
     * 
     * @global type $DB
     */
    public function getErrorTemplate()
    {
       global $DB;

        if (empty($this->error_template_name)) {
            $query = "
                select concat(tb_group.name, '/', tb_template.name)
                from site_template as tb_template
                inner join site_template_group as tb_group on tb_group.id=tb_template.group_id
                where tb_template.id='".CMS_DEFAULT_404_TEMPLATE."'

            ";
            $this->error_template_name = $DB->result($query, 'default');
        }
    }
        
	/**
	* Обработчик неудачных запросов страниц
	* @return void
	*/
	public function log404() 
    {
		global $DB;
		
		if (!is_file(LOGS_ROOT.'404.log')) {
			touch(LOGS_ROOT.'404.log');
		}
		
		$_SERVER['HTTP_REFERER'] = (!empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : "unknown";
		$_SERVER['HTTP_USER_AGENT'] = (!empty($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : "unknown";
			
		if (is_writable(LOGS_ROOT.'404.log') && filesize(LOGS_ROOT.'404.log')/(1000 * 1000) < 100) {
			$fp = fopen(LOGS_ROOT . '404.log', 'a');
			
			fwrite($fp, "\n
				[BEGIN]".str_repeat('-', 50)."
				Date: ".date('Y-m-d H:i:s')."
				URL: ".(defined('HTTP_REQUEST_URI') ? "http://" . CMS_HOST . HTTP_REQUEST_URI : 'shell')."
				IP: ".HTTP_IP." (".HTTP_LOCAL_IP.")
				Refferer: ".$_SERVER['HTTP_REFERER']." 
				UserAgent: ".$_SERVER['HTTP_USER_AGENT']."   
				[END]".str_repeat("-", 50)."\n");
			fclose($fp);
		}  
        
		if (defined('SEO_ACTIVE') && SEO_ACTIVE && defined('HTTP_REQUEST_URI')) {
            $url = defined('CURRENT_URL_FORM') ? "http://" . CMS_HOST . HTTP_REQUEST_URI : 'shell';
            
            $query = "INSERT INTO `seo_log_404` (`date`, `url`, `ip`, `referer`, `user_agent`, `count`)"
                    . " VALUES ( NOW(),  '$url', '".HTTP_IP."', '".$_SERVER['HTTP_REFERER']."', '".$_SERVER['HTTP_USER_AGENT']."', '1')"
                    . " on duplicate key update `count`=`count`+1 ";
            $DB->query($query);
        }
	}
	
    /**
     * 301 Redirect: если страница была перемещена 
     * @global DB $DB
     * @param type $url
     */
    public function checkRedirectLink($url)
    {
        global $DB;

        if (!preg_match("/^".CMS_HOST."/", $url)) {
            $url = CMS_HOST.'/'.$url;
        }
        $url = $DB->escape($url);
                        
        $query = "
                SELECT
                        tb_structure.id,
                        tb_structure.url_old,
                        tb_structure.url_new
                FROM site_structure_redirect AS tb_structure
                WHERE tb_structure.url_old='$url' or tb_structure.url_old='http://".$url."/' or
                      tb_structure.url_old='{$url}/' or tb_structure.url_old='{$url}.html'
        ";
        $redirect = $DB->query_row($query);
       
        if ($DB->rows > 0) {
            if (preg_match("/^".CMS_HOST."/", $redirect['url_new'])) {
               $redirect['url_new'] = str_replace(CMS_HOST, "", $redirect['url_new']);
            }
            
            $redirect['url_new'] = trim($redirect['url_new'], '/');
            if (!empty($redirect['url_new'])) {
                $redirect['url_new'] .= '/';
            }
            
            header( "HTTP/1.1 301 Moved Permanently" );
            header( "Location: /" . LANGUAGE_URL. $redirect['url_new'] );
            exit;
        }
	}
	
    /**
     * Проверка на регистр
     * @return bool
     */
	public function checkPageUrlCase()
    {
        $matches = explode("?", HTTP_REQUEST_URI);
        $url = $matches[0];
		return (strtolower($url) == $url) ? true : false;
	}
   
    /**
     * Id объекта прикрепленного раздела к структуре	
     * @return int
     */
	public function getItemId()
    {
        return $this->item_object_id;
	}
    
    /** 
     * Id объекта прикрепленного раздела к структуре	
     * @return int
     */
    public function getItemTable()
    {
        return $this->item_table_name;
    }
    
    /**
     * Телефонные номера
     * 
     * @global DB $DB
     * @return array $data 
     */
    public function getSitePhone()
    {
        global $DB;

        $Cache = new CacheSql('site_phone');
        $data = $Cache->read();
        
        if (!empty($data)) {
            return $data;  
        }

        $query = "
            SELECT
                    tb_phone.id,
                    tb_phone.name_".LANGUAGE_CURRENT." as name,
                    tb_phone.phone
            FROM site_phone tb_phone WHERE tb_phone.active='1'            
            ORDER BY tb_phone.priority
        ";
        $data = $DB->query( $query ); 
        
        if (count($data)) {
            $data[0]['main'] = true; 
        }
        /*
        foreach ($data as $i=>$row) {
            $data[$i]['phone'] = str_replace( array('(',')'), array('<span>', '</span>'), $row['phone']);
        }*/
        
        $Cache->write($data);
        
        return $data;            
    }
    
    /**
    * Ссылки на социальные сети
    * @global type $DB
    * @return array $data 
    */
    public function getSocButton()
    {
        global $DB;

        $Cache = new CacheSql('site_socbutton');
        $data = array();//$Cache->read();
        
        if (!empty($data)) {
            return $data;  
        }
        $query = "
             SELECT
                tb_button.id,
                tb_button.name_".LANGUAGE_CURRENT." as name,                 
                tb_button.uniq_name as class,                 
                tb_button.link,
                tb_button.active,
                tb_button.image,
                tb_button.priority
            FROM site_socbutton tb_button            
            WHERE tb_button.active='1' and tb_button.link <> ''
            ORDER BY tb_button.priority                
        ";
        $data = $DB->query($query); 
        foreach ($data as $i => $row) {
            $data[$i]['image'] = Uploads::getIsFile('site_socbutton', 'image', $row['id'], $row['image']);
        }
        
        $Cache->write($data);
        
        return $data;            
    }

    /**
     * Возращает урл для конкретной страницы 
     * @global DB $DB
     * @param int $structure_id
     * @param string $key
     * @return mixed $url
     */
    public static function getPageUrl($structure_id, $key = 'url')
    {
        global $DB;

        $Cache = new CacheSql('site_structure', 'structure_' . $structure_id);
        $data = $Cache->read();
        
        if (!empty($data)) {   
           return (!empty($key)) ? $data[$key] : $data; 
        }

        $data = $DB->query_row("
                SELECT 
                     id, 
                     CONCAT('/".LANGUAGE_URL."', REPLACE(url, '".CMS_HOST."/', ''), '/') as url, 
                     name_".LANGUAGE_CURRENT." as name 
                FROM site_structure AS tb_structure
                WHERE id = '$structure_id' AND active='1' ");

        $Cache->write($data);
        
        return (!empty($key) && isset($data[$key])) ? $data[$key] : $data; 
    }
        
    /**
     * Вывод языков для меню
     * @global type $DB
     * @return array $data 
     */
    public function getMenuLanguage()
    {
        $url_current = trim(NO_LANGUAGE_URL_FORM, '?');

        $language = array();       
        $languages = getLanguageList();
        
        $languages = getLanguageActiveList();
        
        foreach ($languages as $lang) {
            $language[$lang]['lang'] = $lang;
            $language[$lang]['name'] = $lang;
            $language[$lang]['url'] = ($lang !== LANGUAGE_SITE_DEFAULT) ? '/' . $lang . $url_current : $url_current;
        }
        $language[LANGUAGE_CURRENT]['class'] = 'active'; 
        /*$current = $language[LANGUAGE_CURRENT];
        unset($language[LANGUAGE_CURRENT]);
        $language[LANGUAGE_CURRENT] = $current;*/
        
        return $language;            
    }
        
    /**
     * 
     * @global type $DB
     * @return type
     */
    public static function getSiteHost()
    {
        global $DB;
        
        $Cache = new CacheSql('site_structure_site');
        $data = $Cache->read();
        
        if (!empty($data)) {
            return $data;  
        }
        
        $data = $DB->result("select `url` from site_structure_site where active='1' ");

        $Cache->write($data);
        
        return $data;
    }
       
}

?>