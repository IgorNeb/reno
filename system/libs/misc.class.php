<?php
/**
 * Класс, который содержит статические методы, которые в большинстве своем не связаны друг с другом
 * @package DeltaCMS
 * @subpackage CMS
 * @version 3.0
 * @author Rudenko Ilya <rudenko@delta-x.com.ua>
 * @copyright C-format
 */

class Misc 
{
    /**
     * 
     * @global DB $DB
     * @param int $fk_table_id
     * @param int $selected_id
     * @param array $filter
     * @param int $offset
     * @return type
     */
	public static function cmsFKeyReference($fk_table_id, $selected_id = 0, $filter = array(), $offset = 0) 
    {
		global $DB;

		$select = array();
		$where = array(1);
		$table = cmsTable::getInfoById($fk_table_id);
		$fields = cmsTable::getFields($fk_table_id);
		
		$Template = new Template(SITE_ROOT.'templates/cms/admin/fkey_reference');
		$Template->setGlobal('table_id', $fk_table_id);
		
		reset($fields);
		while (list(,$row) = each($fields)) {
			if ($row['is_reference'] == 0 && $row['name'] != 'id' && $row['name'] != $table['fk_show_name']) {
				continue;
			}
			$select[] = $row['name'];
			if (isset($filter[$row['name']]) && !empty($filter[$row['name']])) {
				$where[] = "`$row[name]` like '%".$filter[$row['name']]."%'";
				$row['filter_value'] = $filter[$row['name']];
			}
			
			if ($row['name'] == 'id') {
				$row['width'] = "10%";
			}
			$Template->iterate('/title/', null, $row);
		}
		
		$order_by_index = (!empty($filter['id'])) ? " if(id={$filter['id']}, 0, 1) ASC, " : "";
		
		$query = "
			SELECT sql_calc_found_rows `".implode("`,`", $select)."`
			FROM `$table[table_name]` 
			WHERE ".implode(" AND ", $where)."
			ORDER BY $order_by_index `$table[fk_order_name]` $table[fk_order_direction]
		".self::limit_mysql(20, $offset);
		$data = $DB->query($query);
		
		reset($data);
		while (list(,$row) = each($data)) {
			$tmpl_row = $Template->iterate('/row/', null, $row);
			$title = str_replace("'", '', implode("; ", $row));
			reset($row);
			while (list($key, $value) = each($row)) {
				$Template->iterate('/row/field/', $tmpl_row, array('value' => $value, 'name' => $key, 'title' => htmlspecialchars($title)));
			}
		}
		
		// Пролистывание страниц
		$query = "SELECT found_rows()";
		$total_rows = $DB->result($query);
		
		$Template->set('page_list', self::pages($total_rows, 20, 10, 0, true, false, null, 'send({$offset});', $offset));
		
		return $Template->display();
	}
	
	/**
	 * Выводит ссылки на пролистывание страниц
	 *
	 * @param int $total_rows - суммарное количество рядов
	 * @param int $rows_per_page - количество рядов, которое выводится на одной странице
	 * @param int $show_pages - количество ссылок для перехода по страницам
	 * @param mixed $keyword - уникальный код данной листалки. Указывается номер листалки, которая выводится на странице. Если указать вместо этого параметра значение, которое начинается со знака /, например /News/p{$offset}, то пролистывание страниц будет производится через mod_rewrite
	 * @param boolean $show_all_link - вывести в конце списка страниц ссылку "Показать все"
	 * @param boolean $show_text_links - вывести в начале и конце списка ссылки "Первая", "Последняя"
	 * @param string $anchor_name - при переходе по страницам использовать якорь #test
	 * @param string $javascript - для пролистывания страниц использовать указанный JavaScript код. {$offset} подставляется номер страницы (смещение)
	 * @param int $page_start - номер страницы. Используется в тех случаях когда номер страницы содержится не в параметре $_GET[offset][intval($keyword)]
	 * @return string
	 */
	public static function pages($total_rows, $rows_per_page, $show_pages = 10, $keyword = 0, $show_all_link = false, $show_text_links = false, $anchor_name = '', $javascript = '', $page_start = null) 
    {
        $rows_per_page = intval($rows_per_page);
		if ($total_rows <= $rows_per_page) {
            return '';
        }
		if($rows_per_page == -1) {
            return '';
        }
               
		$anchor_name = (empty($anchor_name)) ? '' : "#$anchor_name";
		$anchor_name = (strlen($anchor_name) == 1) ? '': $anchor_name;
		
		if (is_null($page_start)) {
            $page_start = globalVar($_GET['_page'], 0);
		}
		if ( $page_start == 0) {
            $page_start = 1;
		}				
		// Определяем формат ссылки
		if (!empty($javascript) && !empty($anchor_name)) {
			$link = 'href="'.$anchor_name.'" onclick="'.$javascript.'"';
		} elseif (!empty($javascript) && empty($anchor_name)) {
			$link = 'href="javascript:void(0);" onclick="'.$javascript.'"';
		} else {
			/**
			 * Определяем параметры, переданные методом GET
			 * Удаляем из запроса все, что начинается на 2 подчеркивания
			 * Используется для передачи параметров, установленных mod_rewrite
			 */
			$get = $_GET;
			unset($get['_offset'][$keyword], $get['_REWRITE_URL'], $get['_GALLERY_URL']);
			if (substr($keyword, 0, 1) == '/') {
				reset($get);
				while (list($key,) = each($get)) {
					if (substr($key, 0, 1) == '_') unset($get[$key]);
				}
				$get = http_build_query($get);
				$get = (empty($get)) ? '' : '?'.$get;
				$get = '';
				$link = 'href="'.$keyword.$get.$anchor_name.'"';
			} else {
				$link = 'href="?'.urlencode("_offset[$keyword]").'={$offset}&'.http_build_query($get).$anchor_name.'"';
			}			
		}
		
		
		//$next = ($page_start + $rows_per_page >= $total_rows) ?	$total_rows - 1 : $page_start + $rows_per_page;                
        $previous = ( $page_start == 1 ) ? $page_start : $page_start - 1; 
		$next = ( $page_start * $rows_per_page >= $total_rows) ? $page_start : $page_start + 1;                
		$return = '';
		
		
		/**
		 * Блок ссылок:
		 * 
		 * v.1: 20%        60%         20%          <-- если текущая страница >60% от show_pages и < total_pages - (60% * show_pages)
		 *      первые ... средние ... последние
		 * 
		 * v.2: 80%        20%                      <-- если текущая страница <=60% от show_pages
		 *      первые ... последние
		 * 
		 * v.3: 20%        80%                      <-- если текущая страница >= total_pages - (60% * show_pages)
		 * 	    первые ... последние
		 *
		 */
		$current_page = $page_start - 1;
		$total_pages = ceil($total_rows / $rows_per_page) - 1;
		
		$first_block_start = 0;
		$last_block_end = $total_pages;

		if ($total_pages <= $show_pages) {
			// v.0 - просто список страниц
			$first_block_end = $total_pages;
			$middle_block_start = $middle_block_end = $last_block_end = $last_block_start = 0;
		} elseif ($current_page <= $show_pages*0.6) {
			// v.2
			$first_block_end = ceil($show_pages*0.8)-1;
			$middle_block_start = $middle_block_end = 0;
			$last_block_start = $total_pages - floor($show_pages*0.2) + 1;
		} elseif ($current_page >= $total_pages - $show_pages*0.5) {
			// v.3
			$first_block_end = ceil($show_pages*0.2)-1;
			$middle_block_start = $middle_block_end = 0;
			$last_block_start = $total_pages - floor($show_pages*0.8) + 1;
		} else {
			// v.1
			$first_block_end = ceil($show_pages*0.2)-1;
			$last_block_start = $total_pages - floor($show_pages*0.2) + 1;
			$middle_block_count = $show_pages - ($first_block_end-$first_block_start+1) - ($last_block_end-$last_block_start+1);
			$middle_block_start = $current_page - floor($middle_block_count/2);
			$middle_block_end = $middle_block_end = $middle_block_start + $middle_block_count - 1;
		}
		
//		$debug = array(
//			'total_pages' => $total_pages,
//			'show_pages' => $show_pages,
//			'current_page' => $current_page,
//			
//			'first' => array('start' => $first_block_start, 'end' => $first_block_end),
//			'middle' => array('start' => $middle_block_start, 'end' => $middle_block_end),
//			'last' => array('start' => $last_block_start, 'end' => $last_block_end)
//		);
//		x($debug);
		
        $Template = new Template('site/pagination');
                
		if ($show_text_links) {
			// Показываем ссылку на предыдущую страницу
			$active = ($page_start > 1) ? false : true;
			$row = self::pageGetLink($link, $previous, cmsMessage::get('MSG_SITE_PAGE_PREV'), $active, 'prev_page');
            $Template->iterate('/page/', null, $row );
		}
		
		// Формируем список страниц		
		for ($i = $first_block_start+1; $i <= ($first_block_end+1); $i++) {
			$active = ( $i == $page_start) ? true : false;
			$row = self::pageGetLink($link, $i, $i, $active);
            $Template->iterate('/page/', null, $row );
		}
		
		if ($middle_block_start != 0) {
            $Template->iterate('/page/', null, array('is_dots' => true) );
                        
			for ($i = $middle_block_start+1; $i <= $middle_block_end+1; $i++) {
				$active = ( $i == $page_start) ? true : false;
                $row = self::pageGetLink($link, $i, $i, $active);
                $Template->iterate('/page/', null, $row );
			}
		}
		
		if ($last_block_end != 0){ //$last_block_start) {
			#$return .= '<span class="page_dots">...</span>';
            $Template->iterate('/page/', null, array('is_dots' => true) );
		}
		
		if ($last_block_start != 0) {
			for ($i = $last_block_start+1; $i <= $last_block_end+1; $i++) {
                $active = ( $i == $page_start) ? true : false;
                #$return .= self::pageGetLink($link, $i, $i, $active);
                $row = self::pageGetLink($link, $i, $i, $active);
                $Template->iterate('/page/', null, $row );
			}
		}		
		
		if ($show_text_links) {
			// Показываем ссылку на следующую страницу
			$active = ($page_start != $next && $page_start != -1) ? false : true;
			$row = self::pageGetLink($link, $next, cmsMessage::get('MSG_SITE_PAGE_NEXT'), $active, 'next_page');
            $Template->iterate('/page/', null, $row );
		}
		
		if ($show_all_link) {
			// Показываем ссылку "Показать все"
			$active = ($page_start == -1) ? true : false;
			$row = self::pageGetLink($link, -1, cmsMessage::get('MSG_SITE_PAGE_ALL'), $active);
            $Template->iterate('/page/', null, $row );
		}
		
        $return = $Template->display();
		return $return;
	}
	
    /**
     * Формирование номера страницы-ссылки
     * 
     * @param string $link - ccылка
     * @param int $offset_value - номер страницы
     * @param string $name - подпись (1, 2, следующая)
     * @param boolean $active - активна ли
     * @param string $additional_class - дополнительный клас к ссылке
     * @return string
     */
	private static function pageGetLink($link, $offset_value, $name, $active, $additional_class='')
    {
            
        $verbal = (is_numeric($name)) ? 0 : 1;

        $link = ( $offset_value == 1) ? str_replace(array('page-{$offset}/'), array(''), $link) : str_replace('{$offset}', $offset_value, $link);

        $page_array = array( 'name'=>$name, 'link'=>$link, 'active' => $active, 'class'=>$additional_class, 'verbal' =>$verbal );

        return $page_array;
	}
	
	/**
	 * Функция формирует ограничение LIMIT для запросов к MySQL
	 * 
	 * @param int $rows_per_page
	 * @param int $page_start
	 * @return string 
	 */
	public static function limit_mysql($rows_per_page, $page_start = null) 
    {            
		$page = (is_null($page_start)) ? globalVar($_GET['_page'], 0) : $page_start;
		if ($page < 0 || $rows_per_page == -1) {
            return '';
        }
                
        $page  = ($page > 0) ? ($page - 1) * $rows_per_page : $page * $rows_per_page;
		return " LIMIT ".intval($page).", ".intval($rows_per_page)." ";
	}
    
	/**
     * Разбивка текста на страницы, разделитель <hr/>
     * 
     * @param text $content
     * @param int $keyword
     * @param int $page_start
     * @return text
     */
	public static function pagedContent($content, $keyword = 0, $page_start = null) 
    {
		if (stripos($content, '<hr') === false) return $content;
		
		$content = preg_split("/<hr[^>]*>/i", $content, -1, PREG_SPLIT_NO_EMPTY);
		$page_list = Misc::pages(count($content), 1, 10, 0, true, true);
		
		$page_start = (empty($page_start)) ? globalVar($_GET['_offset'][$keyword], 0): $page_start;
		if ($page_start < 0) {
            return implode("<p>", $content).'<br><center>'.$page_list.'</center>';
        }
        
		return (isset($content[$page_start])) ? $content[$page_start].$page_list: $content[0].$page_list;
	}

	/**
	* Обрезает строку с определенным количеством символов, не разрывая слова
    * 
	* @param string $str
	* @param int $len
    * @param string $dot 
	* @return string
	*/
	public static function word_wrapper($str, $len, $dot = '...') 
    {
		$cut_pos = strpos(wordwrap($str, $len, '<stop>', true), '<stop>');
		return ($cut_pos) ? substr($str, 0, $cut_pos) . $dot: $str;
	}
	
    /**
     * Вывод html редактора по клику в админ панели для таблиц
     * 
     * @param int $id
     * @param string $table_name - таблица БД
     * @param string $field_name - поле, которое будет редактироваться
     * @param string $title - текст ссылки
     * @param bool $icon - выводить з иконкою
     * @return string $link html ссылка 
     */
    public static function html_editor($id, $table_name, $field_name, $title, $icon = true)
    {
        $editor = "<a href=\"#\" onclick=\"EditorWindow('event=editor/content&id=".$id."&table_name=".$table_name."&field_name="
                . $field_name . "', '$table_name', '$id');return false;\">" . $title . "</a>";
        $editor = '<span class="edit_text"></span> ' . $editor;
        return $editor;
    }

    /**
     * Код для вставки пользовательской функции
     * @param int $id
     * @param string $snipp
     * @param string $attr
     * @return string
     */
    public static function code_paste($id, $snipp, $attr = 'name')
    {
        $html = "<a href='#' title='Скопировать код' class='clickboard' id='clickboard_{$id}'>&lt;div&gt;{".$snipp." {$attr}=&quot;".$id."&quot;}&lt;/div&gt;</a>";
        $html .= "<div class='clickboard-img'></div>";
        return $html;
    }
    
    /**
     * Вывод текстового редактора по клику в админ панели для таблиц
     * 
     * @param int $id
     * @param string $table_name - таблица БД
     * @param string $field_name - поле, которое будет редактироваться
     * @param string $title - текст ссылки
     * @return string $link html ссылка 
     */
    public static function text_editor($id, $table_name, $field_name, $title)
    {
        $editor = "<a href=\"#\" onclick=\"EditScript('id={$id}&table_name={$table_name}&field_name={$field_name}', '$table_name', '$id');return false;\">" 
                . $title . "</a>";
        $editor = '<span class="edit_text"></span> ' . $editor;
        return $editor;
    }
    
	/**
    * Генерация случайных последовательностей символов
    * 
    * @param int $chars
    * @param string $genChars
    * @return string
    */
	public static function randomKey($chars, $genChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789') 
    {
		$retkey = "";
		for ($i = 1; $i <= $chars; $i++) {
			$rand = rand(1,strlen($genChars));
			$retkey .= substr($genChars,$rand -1,1);
		}
		return ($retkey);
	}
	
	/**
	 * Генерирует ключ
     * 
	 * @static 
	 * @param int $chars
	 * @param int $blocks
	 * @param string $separator
	 * @return string
	 */
	public static function keyBlock($chars, $blocks, $separator = '-') 
    {
		$key = "";
		for($i = 0; $i < $blocks;$i++) {
			//Create an array of keys
			$key[] = self::randomKey($chars);
		}
		return implode($separator, $key);
	}
	
    /**
     * Выполняет противополжные действия от bin2hex
     * 
     * @param string $hexdata
     * @return string
     */
	public static function hex2bin($hexdata) 
    {
		for ($i=0;$i<strlen($hexdata);$i+=2) {
			$bindata.=chr(hexdec(substr($hexdata,$i,2)));
		}
		return $bindata;
	}
	
	/**
	 * Определение колонок, которые надо открывать, для поля cmsEdit - ext_multiple
	 *
	 * @param object $DBServer - соединение с БД, в которой находится редактируемое поле
	 * @param int $master_id - id ряда, который сейчас редактируется
	 * @param array $parent_tables - список таблиц, которые будут выведены начиная со следующего уровня
	 * @param string $relation_table_name
	 * @param string $relation_select_field
	 * @param string $relation_parent_field
	 * @return void
	 */
	public static function extMultipleOpen(DB $DBServer, $master_id, $parent_tables, $relation_table_name, $relation_select_field, $relation_parent_field) 
    {
		global $DB;
		
		$query = "
			DROP TEMPORARY TABLE IF EXISTS `tmp_open`;
			CREATE TEMPORARY TABLE `tmp_open` (id INT UNSIGNED NOT NULL, PRIMARY KEY (id)) ENGINE=MyISAM;
		";
		$DBServer->multi($query);

		/**
		 * Определение таблиц и названий полей, которые являются родительскими в таблице
		 */
		$open_tables = array();
		reset($parent_tables);
		while(list(,$row) = each($parent_tables)) {
			$query = "
				SELECT
					tb_table.name AS table_name,
					tb_field.name AS parent_field_name
				FROM cms_table AS tb_table
				LEFT JOIN cms_field AS tb_field ON tb_field.id=tb_table.parent_field_id
				WHERE tb_table.id='$row'
			";
			$open_tables[] = $DB->query_row($query);
		}
		
		// таблица, которая выводит значения на данном уровне
		$select_table = array_shift($open_tables);
		$query = "
			INSERT IGNORE INTO tmp_open (id)
			SELECT tb_0.id
			FROM `$select_table[table_name]` AS tb_0 ";
		$where_table = array(); // таблица, с которой выбираются значения
		$where_table_index = 0;
		reset($open_tables);
		while(list($index, $row) = each($open_tables)) {
			$index++;
			$query .= "
			INNER JOIN `$row[table_name]` AS tb_$index ON tb_$index.`$row[parent_field_name]`=tb_".($index-1).".id";
			$where_table = $row;
			$where_table_index = $index;
		}
		$query .= "
			INNER JOIN `$relation_table_name` AS tb_relation ON tb_relation.`$relation_select_field`=tb_$where_table_index.`id`
			WHERE tb_relation.`$relation_parent_field`='".$master_id."'
		";
		$DBServer->insert($query, 'id', 'id');	
		
		return 0;
	}
	

	/**
	 * Копирует ряды в таблице
	 *
	 * @param string $table_name
	 * @param array $where_fields
	 * @param array $update_fields
	 */
	public static function copyRows($table_name, $where_condition, $substitute = array()) 
    {
		global $DB;
		$insert = array();
		$field_list = array();
		$last_inserted_id = -1;
		if (empty($where_condition)) {
			$where_condition = 1;
		}
		
		$query = "
			SELECT *
			FROM `$table_name`
			WHERE $where_condition
		";
		$data = $DB->query($query);
		reset($data); 
		while (list(,$row) = each($data)) { 
			$row_insert = array();
			reset($row); 
			while (list($field,$value) = each($row)) { 
				if (isset($substitute[$field])) {
					$row_insert[] = "'".$substitute[$field]."'";
				} elseif ($field == 'id') {
					continue;
				} elseif (is_null($value)) {
					$row_insert[] = "NULL";
				} else {
					$row_insert[] = "'$value'";
				}
			}
			if (empty($field_list)) {
				unset($row['id']);
				$field_list = "`".implode("`,`", array_keys($row))."`";
			}
			$insert[] = "(".implode(",", $row_insert).")";
		}
		
		
		if (!empty($insert)) {
			$query = "INSERT INTO `$table_name` ($field_list) VALUES ".implode(",",$insert);
			$last_inserted_id = $DB->insert($query);
		}
		return  $last_inserted_id;
	}
	 
    /**
	 * Определяет URL для товара или группы
	 *
	 * @param string $table_name
	 * @param string $field_name
	 * @param int $id
	 * @param string $name
	 * @return string
	 */
	public static function getURL($table_name, $field_name, $id, $name) 
    {
		global $DB;
		
		$url = name2url( $name );
                
		$query = "select `$field_name` from `$table_name` where $field_name like '$url%'";
		if (!empty($id)) {
			$query .= " and id!='$id' ";
		}
		$data = $DB->fetch_column($query);
		
		// Запись с таким URL уже существует
		if (false !== in_array($url, $data)) {
			$counter = 0;
			do {
				if (false === in_array($url.'-'.$counter, $data)) {
					break;
				} else {
					$counter++;
				}
			} while (1);
			$url .= "-$counter";
		}
		return $url;
	}
    
	/**
	 * Фиксирует изменения в таблице
	 *
	 * @param object $DBServer
	 * @param string $table_name
	 * @param int $row_id
	 * @param enum $action_type
	 * @param array $data
	 * @return bool
	 */
	public static function cvsDbDiff($DBServer, $table_name, $row_id, $action_type, $data) 
    {
		global $DB; 
		
		// Определяем id таблицы, которая обновляется
		$query = "
			select tb_table.id
			from cms_db as tb_db
			inner join cms_table as tb_table on tb_table.db_id=tb_db.id
			where
				tb_db.alias='".$DBServer->db_alias."' and
				tb_table.name='$table_name'
		";
		$table_id = $DB->result($query);
		if ($DB->rows != 1) {
			return false;
		}
		
		// Определяем существующие в таблице значения
		$query = "select * from `".$DBServer->db_name."`.`$table_name` where id='$row_id'";
		$old = $DBServer->query_row($query);
		
		// Подгружаем данные из таблицы
		$fields = cmsTable::getFields($table_id);
		
		// Создаём транзакцию
		$query = "
			insert into cvs_db_transaction (admin_id,table_id,event_type,row_id) 
			values ('".$_SESSION['auth']['id']."', '$table_id', '$action_type', '$row_id')
		";
		$transaction_id = $DB->insert($query);

		// Определяем данные, которые поступили на обновление
		reset($data); 
		while (list($field_name, $value) = each($data)) { 
			if (!$fields[$field_name]['is_real']) {
				continue;
			} elseif (!isset($fields[ $field_name ])) {
				continue;
			}
			
			if (is_null($value) && ($value != $old[$field_name] || $action_type == 'insert')) {
				$query = "
					insert into cvs_db_change (transaction_id, field_id, field_language, value_null) 
					values ('$transaction_id', '".$fields[ $field_name ]['id']."', '".$fields[ $field_name ]['field_language']."', 'true')
				";
				$DB->insert($query);
			} elseif ($value != $old[$field_name] || $action_type == 'insert') {
				$query = "
					insert into cvs_db_change (transaction_id, field_id, field_language, value_".$fields[$field_name]['pilot_type'].") 
					values ('$transaction_id', '".$fields[ $field_name ]['id']."', '".$fields[ $field_name ]['field_language']."', '$value')
				";
				$DB->insert($query);
			}
		}
		return true;
	}
	
	/**
	 * Посылает письмо по электронной почте по указанному шаблону
	 *
	 * @param string $email Может содержать имя, например "Admin <admin@email.com>"
	 * @param string $subject
	 * @param string $content - html текст письма
	 * @param array $extra_headers
	 * @param bool $plain_text
	 * @param array $attachments
	 * @param bool $immediatly
	 * @return mixed
	 */	
	public static function sendMail($email, $subject, $content, $extra_headers = array(), $plain_text = false, $attachments = array(), $immediatly = false) 
    {
		$Sendmail = new Sendmail(CMS_MAIL_ID, $subject, $content);
		$Sendmail->send($email, $immediatly);
	}
	
	/**
	 * Формирует кеш расположения классов системы
     * 
	 * @return void
	 */
	public static function refreshLibsCache() 
    {	
		$listing = Filesystem::getAllSubdirsContent(LIBS_ROOT, true);
		
		$cache_content = "<?php\n\n\$_LIBS_CACHE = array(\n";
		
		reset($listing); 
		while (list(,$row) = each($listing)) { 
			$content = php_strip_whitespace($row); 
			
			if (preg_match('~class\s+([a-z0-9_]+)\s*~i', $content, $match)) {
				$filename = substr($row, strlen(LIBS_ROOT));
				$cache_content .= "	'".strtolower($match[1])."' => '$filename',\n";
			}
		}

		$cache_content .= ");";
		
		file_put_contents(CACHE_ROOT.'libs_cache.php', $cache_content);
	}
    
    /**
     * Обработка полей вида `name_ru` к `name`. 
     * Подставляется дефолтное значение, если не заполненное поле
     * 
     * @param array $info
     * @return array $info
     */
    public static function parseLangFields($info)
    {
        $language_site = getLanguageList();
        $keyone = key($info);
        
        if (!is_int($keyone)) {
            $info = array($info);
        }
        
        foreach ($info as $i => $row) {
            foreach ($row as $key => $val) {                
                $language = substr($key, -3);
                $language = ltrim($language, '_');
                if (strpos($key, '_') !== false && in_array($language, $language_site)) {                
                    $no_language = substr($key, 0, -3);
                    $default_language = $no_language."_".LANGUAGE_SITE_DEFAULT;
                    if ($language == LANGUAGE_CURRENT) {
                        $info[$i][$no_language] = (empty($val) && isset($row[$default_language])) ? $row[$default_language] : $val;                        
                    }
                    unset($info[$i][$key]);
                } 
            }
        }
        
        if (!is_int($keyone)) {
            $info = array_pop($info);
        }
        return $info;
    }
 
    public static function langCountry() 
    {
        global $DB;
        
        $languages = getLanguageList();
        
        //$data = $DB->fetch_column("SELECT country, code FROM `cms_language` WHERE country <> '' AND code in ('".implode("', '", $languages)."') ");
        $data = array(804=>'ru');
        return $data;
    }
      
    /**
     * Очистка кода от унікод символов
     * @param string $text
     * @return string
     */
    public static function clearFromUnicode($text)
    {
        $str = htmlentities($text);
        
        $sru = 'ёйцукенгшщзхъфывапролджэячсмитьбюії';
        $s1 = array_merge(utf8_str_split($sru), utf8_str_split(strtoupper($sru)), range('A', 'Z'), range('a','z'), range('0', '9'), array('!', '&',' ', '#',';','%','?',':','(',')','-','_','=','+','[',']',',','.','/','\\'));
        $codes = array();
        for ($i=0; $i < count($s1); $i++) {
            $codes[] = ord($s1[$i]);
        }
        $str_s = utf8_str_split($str);
        for ($i=0; $i < count($str_s); $i++) {
            if (!in_array(ord($str_s[$i]), $codes)) {
                $str = str_replace($str_s[$i], '', $str);
            }
        } 
        
        return $str;
    }
}
?>
