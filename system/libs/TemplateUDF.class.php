<?php
/**
* Функции которые используются в шаблонах
* v2.1 добавлен метод text 
* @package DeltaCMS
* @subpackage CMS
* @version 2.1
* @author Rudenko Ilya <rudenko@ukraine.com.ua>
* @copyright Copyright 2004, Delta-X ltd.
*/

class TemplateUDF 
{
	
	/**
	 * Обработка входящих в UDF параметров, как в виде ассоциативного массива,
	 * так и числового
     * 
	 * @access public
	 * @param array $default
	 * @param array $param
	 * @return array
	 */
	public static function parseParam($default, $param) 
    {
		return array_merge($default, $param);
	}
	
	/**
	 * Преобразовывает nl2br
	 * @param array $param
	 * @return string
	 */
	public static function nl2br($param) 
    {
		$param = self::parseParam(array('text'=>''), $param);
		return nl2br($param['text']);
	}
	
	/**
	 * Форматирует число
     * 
	 * @param array $param (float $number, int $decimals, string $dec_point, string $thousands_sep)
	 * @return string
	 */
	public static function number_format($param)
    {
		$param = self::parseParam(array('number'=>0, 'decimals'=>0, 'dec_point'=>'.', 'thousands_sep'=>' '), $param);
		return number_format($param['number'], $param['decimals'], $param['dec_point'], $param['thousands_sep']);
	}
	
    /**
	 * Выводит форму подписки
	 *
	 * @param array $param
	 * @return string
	 */
    public static function subscribe($param)
    {
        $param = self::parseParam(array('name' => '', 'template'=> 'form/subscribe'), $param);
	    $Template = new Template( $param['template'] );
        return $Template->display();
    }
    
	/**
	 * Форматирует Дату
     * 
	 * @param array(string format, int $tstamp)
	 * @return string
	 */
	public static function date_format($param) 
    {
		$param = self::parseParam(array('tstamp'=>time(), 'format' => LANGUAGE_DATE), $param);
		return date($param['format'], $param['tstamp']);
	}
	
	/**
	 * Экранирование строки
     * 
	 * @param array(string $text, enum $type(html, htmlall, url, quotes))
	 * @return string
	 */
	public static function escape($param) 
    {
		$param = self::parseParam(array('text'=>'', 'type'=>'html'), $param);
		
		switch ($param['type']) {
			case 'htmlall':
				return htmlentities($param['text'], ENT_QUOTES, LANGUAGE_CHARSET);
				break;
			case 'url':
				return urlencode($param['text']);
				break;
			case 'quotes':
				return addslashes($param['text']);
				break;
			default :
				return htmlspecialchars($param['text'], ENT_QUOTES, LANGUAGE_CHARSET);
				break;
		}
	}
	
	/**
	 * Установка флага checked и selected
     * 
	 * @param array(string $value, string $selected)
	 * @return string
	 */
	public static function checked($param) 
    {
		$param = self::parseParam(array('value'=>'', 'selected'=>''), $param);
		if ($param['value'] == $param['selected']) {
			return ' checked selected ';
		} else {
			return '';
		}
	}
    
	/**
	 * Выпадающий список опций
     * 
	 * @param array(array $options, mixed $selected) $param
	 * @return string
	 */
	public static function html_options($param) 
    {
		$param = self::parseParam(array('options'=>array(), 'selected'=>array()), $param);
		$checked = (!is_array($param['selected'])) ? array($param['selected']) : $param['selected'];
		$return = '';		
        
		reset($param['options']);
		while (list($key, $val) = each($param['options'])) {
			$selected = (in_array($key, $checked)) ? 'selected' : '';
			$return .= '<option '.$selected.' value="'.$key.'">'.$val.'</option>';
		}		
		return $return;
	}
	
    /**
     * Вывод даты
     * 
     * @param array(string $name) $param
     * @return string
     */
	public static function html_select_date($param) 
    {
		$param = self::parseParam(array('onchange' => '', 'name'=>'', 'day' => date('d'), 'month' => date('m'), 'year' => date('Y'), 'calendar'=>'true'), $param);
		
		if (isset($param['tstamp'])) {
			$param['day'] = date('d', $param['tstamp']);
			$param['month'] = date('m', $param['tstamp']);
			$param['year'] = date('Y', $param['tstamp']);
		}
		
		// Дни
		$html['d'] = '<input type="text" onkeyup="'.$param['onchange'].'" name="'.$param['name'].'[day]" value="'.$param['day'].'" size="2" maxlength="2" id="'.str_replace(array('[', ']'), '_', $param['name']).'_day"> ';
		
		// Список месяцев
		$html['m'] = '<select onchange="'.$param['onchange'].'" size="1" name="'.$param['name'].'[month]" id="'.str_replace(array('[', ']'), '_', $param['name']).'_month">';
		for ($i=1; $i<=12; $i++) {
			$selected = ($i == $param['month']) ? 'selected' : '';
			$html['m'] .= '<option '.$selected.' value="'.$i.'">'.constant('LANGUAGE_MONTH_GEN_'.$i).'</OPTION>'."\n";
		}
		$html['m'] .= '</select> ';
		
		// Год
		$html['y'] = '<input onkeyup="'.$param['onchange'].'" type="text" name="'.$param['name'].'[year]" value="'.$param['year'].'" size="4" maxlength="4" id="'.str_replace(array('[', ']'), '_', $param['name']).'_year"> ';
		
		// Календарь
		if ($param['calendar'] == 'true') {
			$html_calendar = ' <a href="javascript: void(0);" onclick="g_Calendar.show(event, \''.str_replace(array('[', ']'), '_', $param['name']).'\', false, \'dd/mm/yyyy\', new Date(1900, 0, 1, 0, 0, 0), new Date(2030, 11, 31, 0, 0, 0), 1900); return false;"><img src="/design/cms/img/js/calendar/calendar.gif" width="34" height="21" alt="Выберите дату" border="0" align="absmiddle"></a>';
		}
		
		$format = preg_split("/[^a-z]+/i", strtolower(LANGUAGE_DATE), -1, PREG_SPLIT_NO_EMPTY);
		$result = '';
		reset($format);
		while (list(, $period) = each($format)) {
			$result .= $html[$period];
		}
		return $result.$html_calendar;
	}
	
	/**
     * Вывод краткой даты
     * 
     * @param array(string $name) $param
     * @return string
     */
	public static function html_short_date($param) 
    {
		$param = self::parseParam(array('onchange' => '', 'name'=>'', 'day' => date('d'), 'month' => date('m'), 'year' => date('Y'), 'calendar'=>'true'), $param);
		
		// Если указан параметр tstamp, то дату определяем по нему
		if (isset($param['tstamp'])) {
			$param['day'] = date('d', $param['tstamp']);
			$param['month'] = date('m', $param['tstamp']);
			$param['year'] = date('Y', $param['tstamp']);
		}
		
		// Дни
		$html['d'] = '<input type="text" onkeyup="'.$param['onchange'].'" name="'.$param['name'].'[day]" value="'.$param['day'].'" style="width:25px;" maxlength="2" id="'.str_replace(array('[', ']'), '_', $param['name']).'_day">';
		
		// Список месяцев
		$html['m'] = '<input type="text" onkeyup="'.$param['onchange'].'" name="'.$param['name'].'[month]" value="'.$param['month'].'"style="width:25px;" maxlength="2" id="'.str_replace(array('[', ']'), '_', $param['name']).'_month">';
		
		// Год
		$html['y'] = '<input onkeyup="'.$param['onchange'].'" type="text" name="'.$param['name'].'[year]" value="'.$param['year'].'" style="width:35px;" maxlength="4" id="'.str_replace(array('[', ']'), '_', $param['name']).'_year">';
		
		// Календарь
		if ($param['calendar'] == 'true') {
			$html_calendar = '<a href="javascript: void(0);" onclick="g_Calendar.show(event, \''.str_replace(array('[', ']'), '_', $param['name']).'\', false, \'dd/mm/yyyy\', new Date(1900, 0, 1, 0, 0, 0), new Date(2030, 11, 31, 0, 0, 0), 1900); return false;"><img src="/design/cms/img/js/calendar/calendar.gif" width="34" height="21" alt="Выберите дату" border="0" align="absmiddle"></a>';
		}
		
		$format = preg_split("/[^a-z]+/i", strtolower(LANGUAGE_DATE), -1, PREG_SPLIT_NO_EMPTY);
		$result = '';
        
		reset($format);
		while (list(, $period) = each($format)) {
			$result .= $html[$period];
		}
		return $result.$html_calendar;
	}
	
	
	/**
	 * Вывод времени
     * 
	 * @param array(string $name, int $hour, int $minute, int $second, bool $show_seconds) $param
	 * @return string
	 */
	public static function html_text_time($param) 
    {
		$param = self::parseParam(array('name'=>'', 'hour'=>date('H'), 'minute'=>date('i'), 'second'=>date('s'), 'show_seconds'=>true), $param);
		
		$hour = (empty($hour)) ? date('H', $param['timestamp']) : date('H');
		$minute = (empty($minute)) ? date('i', $param['timestamp']) : date('i');
		$second = (empty($second)) ? date('s', $param['timestamp']) : date('s');
		
		$html = '
			<input type="text" size="2" maxlength="3" name="'.$param['name'].'[hour]" id="'.str_replace(array('[', ']'), '_', $param['name']).'_hour" value="'.$param['hour'].'">:<input 
			type="text" size="2" maxlength="2" name="'.$param['name'].'[minute]" id="'.str_replace(array('[', ']'), '_', $param['name']).'_minute" value="'.$param['minute'].'">';
		if (!empty($param['show_seconds'])) {
			$html .= ':<input type="text" size="2" maxlength="2" name="'.$param['name'].'[second]" id="'.str_replace(array('[', ']'), '_', $param['name']).'_second" value="'.$param['second'].'">
				<a href="javascript: void(0);" onclick="javascript:DateTime = new Date();document.getElementById(\''.str_replace(array('[', ']'), '_', $param['name']).'_hour\').value=DateTime.getHours();document.getElementById(\''.str_replace(array('[', ']'), '_', $param['name']).'_minute\').value=DateTime.getMinutes();document.getElementById(\''.str_replace(array('[', ']'), '_', $param['name']).'_second\').value=DateTime.getSeconds();"><img src="/design/cms/img/button/time_now.gif" align="top" width="22" height="21" border="0" alt="Установить текущее время"></a>
			';
		}
		return $html;
	}
	
    /**
     * вывод константы
     * 
     * @param array $param -- name - константа, value - значение для %s, default - быстрое обновление константы         
     * @return string
     */
	public static function text($param) 
    {
		$param = self::parseParam(array('name'=>'', 'value'=>'', 'default'=>''), $param);
        if (empty($param['name'])) {
            return "";
        } 
        $value = strtoupper($param['name']);
        $matches = explode("_", $param['name']);
        if (count($matches)<3){ 
            return $value; 
        }
        if (!empty($param['default'])) { //быстрое обновление константы  
            cmsMessage::set($matches[1], $value, $param['default']);
        }
        return cmsMessage::get($value, $param['value']);
	}
    
    /**
     * вывод галлереи
     * 
     * @param array $param - array(name => ID галлереи, template => шаблон)
     * @return string
     */
	public static function gallery($param) 
    {
		$param = self::parseParam(array('name'=>0, 'template'=>'gallery'), $param);
        if (empty($param['name'])) {
            return "";
        } 
        
        $gallery = new Gallery('gallery_group', $param['name']);
        $photos = $gallery->getPhotos(100, 0);	
        
        if (empty($photos)) {
            return "";
        }
        
        $type = $gallery->getGroupInfo();
        
        $Template = new Template('gallery/' . $param['template']);
        $Template->set($type);
        $Template->iterateArray('/gallery/', null, $photos);
        return $Template->display();
	}
    
     /**
     * вывод видео
     * 
     * @param array $param - array(name => ID видео, template => шаблон)
     * @return string
     */
	public static function video($param) 
    {
		$param = self::parseParam(array('name'=>0, 'template'=>'video'), $param);
        if (empty($param['name'])) {
            return "";
        }
        
        $video = Gallery::getVideoFile($param['name']);                       
        if (empty($video)) {
            return "";
        }
        
        $Template = new Template('gallery/video');    
        $Template->iterateArray('/video/', null, $video);          
        return $Template->display();
	}
        
	/**
	 * Выводит форму, которая описана в системе
	 *
	 * @param array $param
	 * @return string
	 */
	public static function form( $param ) 
    {
		global $DB;
		
		$param = self::parseParam(array('name' => '', 'template'=> 'form/default'), $param);
		
		// Загружаем данные формы
		$Form = new FormLight($param['name']);
        $template = (empty($Form->template)) ? $param['template'] : $Form->template;
        
        $Template = new Template( $template );
		$Template->set('form_id', $Form->form_id);
		$Template->set($Form->info);
                        
		$data = $Form->loadParam();            
		if (empty($data)) {                    
            return cmsMessage::get('MSG_FORM_NOT_FOUND', $param['name'] );
		}
                
		// Captcha
		if (FORM_CAPTCHA || $Form->form_id == 37) {
            $Template->set('captcha_html', Captcha::createHtml());
        }
        $user_data = array();
        if (Auth::isLoggedIn()) {
            $user_data = Auth::getInfo();
            unset($user_data['name']);
            $user_data += Auth::getDataInfo($user_data['id']);
        }
        
		reset($data); 
		while (list(, $row) = each($data)) {                        
			if (isset($_REQUEST[$row['uniq_name']])) {
				$row['default_value'] = $_REQUEST[$row['uniq_name']];
                
			} elseif (substr($row['uniq_name'], 0, 7) != 'passwd_' && isset($user_data[ $row['uniq_name'] ])) {
				$row['default_value'] = $user_data[$row['uniq_name']];
			}
                        
			if ($row['type'] == 'hidden') {
				$Template->iterate('/hidden/', null, $row);
                
			} else {                
				$tmpl_row = $Template->iterate('/row/', null, $row);
                
                if (!empty($row['info']) && $row['type'] == 'dealer') {
                    $Template->iterateArray('/row/info/', $tmpl_row, $row['info']);
                    
                } else {
                    foreach ($row['info'] as $key => $val) {
                        $Template->iterate('/row/info/', $tmpl_row, array(
                            'key' => $key, 
                            'value' => $val, 
                            'uniq_name' => $row['uniq_name'])
                        );
                    }
                }
			}           
		}
                
        if (isset($_REQUEST[$param['name'].'_error'])) {
            $Template->set('error', $_REQUEST[$param['name'].'_error']);
        }
		return $Template->display();
	}
	
	
	/**
	 * Выводит блок, который описан в системе
	 *
	 * @param array $param
	 * @return string
	 */
	public static function block($param) 
    {
		global $DB, $Site;
		
		$param = self::parseParam(array('name' => '', 'template'=> 'block/default'), $param);
		// Определение контента url блока 
        $url = trim(CURRENT_URL_FORM, '?');
		$query = "
			SELECT id, content_".LANGUAGE_CURRENT." as content, area, is_editor
			FROM block 
			WHERE uniq_name = '{$param['name']}' AND area = 'url'  AND url = '$url'
		";
		$info = $DB->query_row($query);
		
		// url блок не обнаружен, определение контента общего блока  
		if ($DB->rows == 0) {			
            $query = "
                    SELECT id, content_".LANGUAGE_CURRENT." as content, area, is_editor
                    FROM block 
                    WHERE uniq_name = '{$param['name']}' AND area = 'site'
            ";
            $info = $DB->query_row($query);

            // общий блок не обнаружен
            if ($DB->rows == 0) {  
                return '';    
            } 
		}
		
		// Блок обнаружен, но контент пуст
		if (empty($info['content']) && Auth::isAdmin()) {
			$info['content'] = "<p style='margin:10px; color:#999; text-align:center; font-size:10px;'>Пустой блок.<br/>Пожалуйста, добавьте контент.</p>";
		}
		 
		// Загрузка контента блока на сайт
		$Template = new Template($param['template']);                
		$Template->set('id', (isset($info['id'])) ? $info['id'] : 0);
        $Template->set('content', id2url($info['content']));
        $Template->set('is_editor', $info['is_editor']);
		return $Template->display();
	}
	
	/**
	 * Выводит блок, который описан в системе
	 *
	 * @param array $param
	 * @return string
	 */
	public static function maillist_block($param) 
    {
		global $DB, $Site;
		$param = self::parseParam(array('name' => '', 'id'=>0, 'template'=> 'block/maillist_block'), $param);
		$query = "
			SELECT id, content, is_editor
			FROM maillist_block 
			WHERE uniq_name = '{$param['name']}' AND message_id = '{$param['id']}' 
		";
		$info = $DB->query_row($query);
        
        if (empty($info)) {
            $last = $DB->query_row("select `content`, `is_editor` from maillist_block WHERE uniq_name = '{$param['name']}' ORDER BY id desc LIMIT 1");
            $DB->insert("INSERT INTO maillist_block (`uniq_name`, `message_id`, `content`, `is_editor`) "
                    . " VALUES('{$param['name']}', '{$param['id']}', '".  addslashes($last['content'])."', '{$last['is_editor']}' ) ");
            $info = $DB->query_row($query);
        }
                
		// Блок обнаружен, но контент пуст
		if (empty($info['content']) && Auth::isAdmin() ){
			$info['content'] = "<p style='margin:10px; color:#999; text-align:center; font-size:10px;'>Пустой блок.<br/>Пожалуйста, добавьте контент.</p>";
		}
		 
		// Загрузка контента блока на сайт
		$Template = new Template($param['template']);
		$Template->set('id', (isset($info['id'])) ? $info['id'] : 0);
        $Template->set('content', id2url($info['content']));
        $Template->set('is_editor', $info['is_editor']);		
		return $Template->display();
	}
        
	
	/**
	 * Формирование ссылки, доступной только авторизованным пользователям
	 * Для неавторизованных - сначала показывается окно авторизации
	 *
	 * @param array(string $href) $param
	 * @return string
	 */
	public static function auth_link($param) 
        {
		$param = self::parseParam(array('href'=>''));
		return auth_link($param['href']);
	}
        
        public static function get_worker($department)
    {
        global $DB;
        $comanda = new Comanda();
        
        $department = self::parseParam(array('name' => 0, 'template'=> ''), $department);
            	if (empty($department['name'])) {
            return '';                
        }
        
        $department_id = $comanda->getDepartmentById($department['name']);//$DB->result("SELECT id FROM site_department WHERE id='{$department['name']}' and active=1 ORDER BY priority");
        if (empty($department_id) or $department_id == NULL) {
            return '';
        }

        if ($department['template'] == '') {
            $department['template'] = 'department/workers';
        }  
            $workers = $comanda->workerInfoByDepartment($department_id); //$DB->query("SELECT name_". LANGUAGE_CURRENT ." as name FROM site_department_worker WHERE department_id='{$department_id}' "
            //. " AND active=1 ORDER BY priority");           
            $Template = new Template($department['template']);
            $Template->setGlobal('count', count($workers));
            $Template->iterateArray('/worker/', null, $workers);
 
            return $Template->display();
    }			
	
	/**
	 * Вывод баннеров
	 *
	 * @param array $param
	 * @return string
	 */
	public static function banner($param) 
    {
		global $Site;		
        $param = self::parseParam(array('name' => '', 'template'=> '', 'shop_group_id'=>0), $param);
		if (empty($param['name']) || !is_module('Banner')) {
            return '';                
        }
               
        $Banner = new Banner($param['name'], $Site->structure_id, $Site->parents);
		$banners = $Banner->select($param['shop_group_id']);
                
        if (empty($banners)) {
            return '';
        }
        
        if ($param['template'] == '') {
            $param['template'] = 'banner/' . Banner::getBannerTmpl($param['name']);
        }
        
		$Template = new Template($param['template']);
		$Template->setGlobal('count', count($banners));
		$Template->iterateArray('/banner/', null, $banners);
        if (BANNER_SHOW_EDIT_LINK) {                    
            $Template->set('group_id', $Banner->getGroupId($param['name']));
        }
		return $Template->display();
	}
	 
	/**
	 * Комментарии
     * 
	 * @param array $param
	 * @return string
	 */
	public static function comment($param) 
    {
		global $DB;
		
		$param = self::parseParam(array('table_name' => 'news_message', 'object_id'=> 0), $param);
        if (empty($param['object_id'])) {
            return '';
        }
        
		$captha = (COMMENT_CAPTCHA) ? Captcha::createHtml() : '';
        
		$Comment = new Comment($param['table_name'], $param['object_id']);
        $count_comments = $Comment->getCountComment();
        
		$html = '';
        if ($count_comments > 0) {
            if ($param['table_name'] == 'shop_product') {
                $html  = '<div class="col-md-6 col-sm-12">';
                $html .= '<div class="h1">Отзывы покупателей <span> ('.$count_comments.') </span></div>';
                $html .= $Comment->getComments(0, $captha);
                $html .= '</div>';
            } else {
                $html = $Comment->getComments(0, $captha);
            }
        }
        
        $template_form = ($param['table_name'] == 'shop_product') ? 'comment/product_form' : 'comment/default_form';
		$TmplComment = new Template($template_form);
		$TmplComment->set('table_name', $param['table_name']);
		$TmplComment->set('object_id', $param['object_id']);
		$TmplComment->set('captcha_html', $captha);
        
		if (isset($_SESSION['ActionError']['id']) && $_SESSION['ActionError']['id'] == 0) {
			$TmplComment->set('display', 1);
			$TmplComment->set('new_comment', $_SESSION['ActionError']['comment']);
			if (!Auth::isLoggedIn()) {
				$TmplComment->set('new_user_name', $_SESSION['ActionError']['user_name']);
				$TmplComment->set('new_user_email', $_SESSION['ActionError']['user_email']);
			}
		} elseif (Auth::isLoggedIn()) {
            $user = Auth::getInfo();
            $TmplComment->set('new_user_name', $user['name']);
            $TmplComment->set('new_user_email', $user['email']);
        }

        if (isset($_SESSION['ActionError']) && isset($_SESSION['ActionReturn']['error'])) {
            reset($_SESSION['ActionReturn']['error']);
            while (list(, $error) = each($_SESSION['ActionReturn']['error'])) {        
               $TmplComment->iterate('/error/', null, array('name'=> $error) );
            }
        }
        
        if (Auth::isAdmin()) {
            $TmplComment->set('may_vote', true);
        } else {
            //рейтинг        
            $ip = HTTP_IP;
            $may_vote = $DB->result("SELECT count(*) FROM site_vote WHERE `ip`='$ip' AND `table`='{$param['table_name']}' AND object_id='{$param['object_id']}'");
            if ($may_vote == 0) {
                $TmplComment->set('may_vote', true);
            }
        }
        
        if ($param['table_name'] == 'shop_product') {
            $html .= $TmplComment->display();
        } else {
            $html = $TmplComment->display() . $html;
        }
		return $html;
	}
	
    /**
     * вывод слайдера
     * 
     * @param type $param
     * @return string
     */
	public static function slider($param)
    {
		$param = self::parseParam(array('name' =>'', 'template'=>''), $param);
        if (empty($param['name'] )) {
            return "";
        }
        $sliders = Banner::getSliders( $param['name'] );                      
        if (empty($sliders)) {
            return "";
        }
        if (empty($param['template'])) {
            $param['template'] = 'banner/'.Banner::getSlidersTmpl($param['name']);
        }
        $Template = new Template($param['template']);
        if (count($sliders) == 1 && isset($sliders[0]['is_video'])) {
            $Template->set($sliders[0]);
        } else {
            $Template->iterateArray('/slider/', null, $sliders);
        }
        return $Template->display();
	}
        
    /**
     * Голосувалка
     * 
     * @param type $param
     * @return string
     */
    public static function stars($param)
    {
        $param = self::parseParam(array('id' => 0, 'rating' => 0, 'amount'=>0), $param);
        if (empty($param['id'] )) {
            return '';
        }
        $param['width'] = round( $param['rating'] * 14 );
         
        $Template = new Template( 'shop/stars' );
        $Template->set($param);
        return $Template->display();
    }
        
	/**
	 * Вывод клаендаря на сайте
	 * 
	 * @param array $param
	 * @return string
	 */
	public static function calendar($param)
    {
		$param = self::parseParam(array('links' => array(), 'current_date'=> time(), 'show_month' => time(), 'type' => ''), $param);
		$current_day = (date('Y-m', $param['current_date']) == date('Y-m', $param['show_month'])) ? date('j', $param['current_date']) : 0;
		$show_month = date('n', $param['show_month']);
		$show_year = date('Y', $param['show_month']);
		$number_of_days = date('t', mktime(0, 0, 0, $show_month, 1, $show_year));
		$name_month = constant('LANGUAGE_MONTH_NOM_'.$show_month);
		
		// определяем первый день месяца как день недели (пн либо вт ...) 
		$first_weekday = date('w', mktime(0, 0, 0, $show_month, 1, $show_year));
		$first_weekday--;
		if ($first_weekday == -1) {
			$first_weekday = 6;
		}
		
		$html = '
			<table>
				<thead>
					<tr id="adjust">
						<td valign="middle">
							<a onclick="updateCalendar('.intval($show_month - 1).', '.$show_year.', '.$param['current_date'].', \''.$param['type'].'\'); return false;" href="#"><img align="middle" src="/img/news/calendar_left.png" /></a>
						</td>
						<td class="month" colspan="5"><div>'.$name_month.' '.$show_year.'</div></td>
						<td valign="middle">
							<a onclick="updateCalendar('.intval($show_month + 1).', '.$show_year.', '.$param['current_date'].', \''.$param['type'].'\'); return false;" href="#"><img align="middle" src="/img/news/calendar_right.png" /></a>
						</td>
					</tr>
					<tr id="day"><td>пн</td><td>вт</td><td>ср</td><td>чт</td><td>пт</td><td class="weekend">сб</td><td class="weekend">вс</td></tr>
				</thead>	
				<tr>';
		
		// проходим по всем дням месяца и заполняем их
		for ($i = 0; $i < $number_of_days + $first_weekday ; $i++) {
			if ($i % 7 == 0 && $i != 0) {
				$html .= '</tr><tr>';
			}
			
			if ($i < $first_weekday) {
				$html .= '<td>&nbsp;</td>';	
			} else {
				$iterateday = $i - $first_weekday + 1;
				$today = ($iterateday == $current_day) ? 'today' : '';
				$weekend = (date('w', mktime(0, 0, 0, $show_month, $iterateday, $show_year)) == 0 || date('w', mktime(0, 0, 0, $show_month, $iterateday, $show_year)) == 6) ? 'weekend' : '';
				
				// ставим ссылку с даты
				$html .= (isset($param['links'][$iterateday])) ?
					'<td class="'.$today.' '.$weekend.'"><a href="'.$param['links'][$iterateday].'">'.$iterateday.'</a></td>':
					'<td class="'.$today.' '.$weekend.'">'.$iterateday.'</td>';
			}
		}
		
		$html .= '</tr></table>';
		return $html;	
	}

	/**
	 * Форма поиска товаров
	 * 
	 * @param array $param
	 * @return string
	 */
	public static function admin_search_products($param)
    {
        $param = self::parseParam( array('table_name' => '', 'version_show' => 0, 'product_id' => 0, 'object_id' => 0), $param );

        $group_options = ShopEdit::groupTree(1); // Список категорий   
        
        $Template = new Template('cms/shop/search_form');
        $Template->set($param);
        $Template->set('group_options', $group_options);  
        return $Template->display();
    }
	
    /**
	 * Выводит ифноБлоки
	 *
	 * @param array $param
	 * @return string
	 */
    public static function infoblock($param)
    {
        $param = self::parseParam(array( 'name' => 0, 'template'=> '' ), $param);

        $info_id = intval($param['name']);
        
        $content = "";//(Auth::isAdmin()) ? '<a href="/admin/site/infoblock/?group_id='.$info_id.'" target="_blank">[ред. ИнфоБлок]</a>' : "";
        
        if (empty($info_id)) {
            return $content; 
        }

        $Info = new Infoblock($info_id);        
        if (empty($Info->block)) { 
            return $content; 
        }
        
        //Инфоблок списком
        $message = $Info->getMessage();
        
        if (!empty($param['template'])) {  
            $Info->setTemplate($param['template']);            
        }
        
        return $Info->display($message);
    }
        
   
    
}

?>