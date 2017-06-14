<?php
/**
 * Класс, который отвечает за вывод баннеров
 * v2.1 Профайл добавлен к группе баннеров, а не к отдельному баннеру
 *      К слайдерам добавлено выбор к-ства и сортировка
 *
 * @package DeltaCMS
 * @subpackage Banner
 * @version 3.0
 * @copyright c-format
 */

class Banner
{
	/**
	 * Информация о доступных к показу баннерах
	 *
	 * @var array
	 */
	private $banner = array();
	
	/**
	 * Количество баннеров, которые выводятся в группе
	 *
	 * @var int
	 */
	private $banner_count = 0;
	
	/**
	 * Конструктор класса
	 *
	 * @param string $group_name - группа баннеров
	 * @param int $structure_id - текущий раздел
	 * @param array $structure_parents - родительские разделы
	 */
	public function __construct($group_name, $structure_id, $structure_parents) 
    {
		global $DB;
		
		// Отбираем профили, которые можно использовать на данной странице
		$query = "
			SELECT is_recursive, structure_id, banner_id, banner_count
			  FROM `banner_profile_cache`
			 WHERE `date` = current_date()
               AND `hour` = hour(now())
               AND `group_name` = '$group_name'
		";
		$profile = $DB->query($query);
        if ($DB->rows == 0) {
            Banner::buldCache();
            $profile = $DB->query($query);                    
        }
               
		reset($profile);
		while (list($index,$row) = each($profile)) {
			$row['structure_id'] = preg_split("/,/", $row['structure_id'], -1, PREG_SPLIT_NO_EMPTY);
                        
			if ($row['is_recursive'] == 0 && !in_array($structure_id, $row['structure_id'])) {                            
				continue;
			} elseif ($row['is_recursive'] && !array_intersect($row['structure_id'], $structure_parents)) {                            
				continue;
			} else {
				$this->banner[$row['banner_id']] = $row['banner_id'];
				$this->banner_count = $row['banner_count'];
			}
		} 
	}
	
	/**
	 * Делает выборку баннеров для показа на странице
	 * 	
	 * @return array
	 */
	public function select($shop_id = 0) 
    {
		global $DB;
		
		$stat = array();
		
        if ($this->banner_count == 0) {
			return array();
		} elseif (count($this->banner)) {		
			$query = "SELECT *, "
                    . "     button_".LANGUAGE_CURRENT." as button, "
                    . "     title_".LANGUAGE_CURRENT." as title, "
                    . "     subtitle_".LANGUAGE_CURRENT." as subtitle, "
                    . "     description_".LANGUAGE_CURRENT." as description,"
                    . "     REPLACE(link, 'https://" . CMS_HOST . "/', '/".LANGUAGE_URL."' ) as link "
                  . "   FROM banner_banner "
                  . "  WHERE id in (".implode(",", $this->banner).") " . where_clause('shop_group_id', $shop_id);                        
        } else {
            return array();
        }
		
		$banners = $DB->query($query); 		
		$banners = $this->shuffle($banners);
		
		reset($banners);
		while (list($index, $row) = each($banners)) {
            $row['is_video'] = false;
            
			$banner_file = Uploads::getFile('banner_banner', 'image', $row['id'], $row['image']);
			$image_size = (is_file($banner_file)) ? getimagesize($banner_file) : array();            
            //$row['link'] = (!empty($row['link'])) ? '/'.LANGUAGE_URL.'tools/banner/click.php?id='.$row['id'] : '';
            
			if (!empty($row['html'])) {
				// HTML баннер
				$row['type'] = 'html';
				$row['html'] = str_replace('[[link]]', $row['link'], $row['html']);
			} elseif (empty($image_size)) {
                // Нет картинки для баннера
                unset($banners[$index]);
                continue;
			/*} elseif ($image_size[2] == IMAGETYPE_SWF || $image_size[2] == IMAGETYPE_SWC) {
				// Flash
				$row['type'] = 'flash';
				$row['flash_vars'] = str_replace('[[link]]', urlencode($row['link']), $row['flash_vars']);
				$row['tag_attr'] = $image_size[3];
				$row['image_url'] = Uploads::getURL($banner_file);*/
			} else {
				// Обычный баннер
				$row['type'] = 'image';
				$row['tag_attr'] = $image_size[3];
				$row['image_url']   = Uploads::getURL($banner_file);
				$row['image']       = Uploads::getImageURL($banner_file);
				$row['image_small'] = Uploads::getIsFile('banner_banner', 'image_small', $row['id'], $row['image_small'], '');
                if (empty($row['image_small'])) {
                    $row['image_small'] = $row['image'];
                }
                
                //video
                $row['video_mp4'] = Uploads::getIsFile('banner_banner', 'video_mp4', $row['id'], $row['video_mp4']);
                $row['video_webm'] = Uploads::getIsFile('banner_banner', 'video_webm', $row['id'], $row['video_webm']);
                if (!empty($row['video_mp4']) || !empty($row['video_webm'])) {
                    $row['is_video'] = true;
                }
            
			}
			
            if ($row['is_video']) {
                $banners = array($row);
                $stat[] = date('Y-m-d H:i:s')."\t".HTTP_IP."\t".HTTP_LOCAL_IP."\t$row[id]\t".Auth::getUserId()."\n";
                break;
            }
            
			$banners[$index] = $row;
			$stat[] = date('Y-m-d H:i:s')."\t".HTTP_IP."\t".HTTP_LOCAL_IP."\t$row[id]\t".Auth::getUserId()."\n";
		}
        		
        if (BANNER_SAVE_STAT) {
            if (rand(0,1000) > 950) {
                // Чистка статистики
                $this->cleanup();
                // Обновление статистики
                $this->saveStat();
            }

            // Сохраняем статистику
            $fp = fopen(LOGS_ROOT.'banner_view.log', 'a');
            flock($fp, LOCK_EX);
            fwrite($fp, implode("\n", $stat));
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        
		return $banners;
	}
	
    /**
     * Возращает ID группы баннеров
     * 
     * @global DB $DB
     * @param string $uniq_name
     * @return int
     */
    public function getGroupId($uniq_name)
    {
        global $DB;
        return $DB->result("SELECT id FROM banner_group WHERE uniq_name='$uniq_name'");
    }
    
	/**
	 * Перемешивает баннеры, выозращает только $this->group[banner_count] баннеров
	 * При отборе баннеров учитываются их weight
     * 
	 * @param array $banners
	 * @return array
	 */
	protected function shuffle($banners) 
    {
		$p = $ret = array(); // $p - коэфициэнты для баннеров (например, 0-1000, 1001-1200, 1201-2000)
		$interval = 100000;
		$start    = $sum = 0;
		
		// Узнаем суммарный вес всех баннеров
		reset($banners); 
		while (list(,$row) = each($banners)) { 
			$sum += $row['weight']; 
		}
		
		// Просчитываем начальные точки в распределении для каждого баннера
		reset($banners); 
		while (list($index, $row) = each($banners)) { 
			$start += $row['weight']/$sum * $interval;
			$p[$index] = $start;
		}
		
		$counter = 1000;
		while (count($ret) < $this->banner_count && $counter > 0) {
			$counter--;
			$r = rand(0, $interval);
			reset($p); 
			while (list($index, $row) = each($p)) {
				if (!isset($banners[$index])) {
                    continue;
                }
				if (count($banners) == 0) {
                    break;
                }
				if ($r <= $row) {
					$ret[] = $banners[$index];
					unset($banners[$index]);
					continue 2;
				}
			}
		}
		
		return $ret;
	}
	
	/**
	 * Загружает данные с файла в таблицу
     * 
	 * @param string $type 
     * @return array $banner_stat
	 */
	private function loadFile($type) 
    {
		global $DB;
		
		$tmp_file = TMP_ROOT.uniqid('banner_'.$type);
		$insert = array();
		$banner_stat = array();
		
		if (!is_file(LOGS_ROOT.'banner_'.$type.'.log')) {
            return array();
        }
		Filesystem::rename(LOGS_ROOT.'banner_'.$type.'.log', $tmp_file);
		
		$fp = fopen($tmp_file, 'r');
		if (!$fp) {
            // исключение для тех случаев, когда нет папки tmp или временный файл был удален
            return; 
        }
		while (!feof($fp)) {
			$line = fgets($fp);
			$line = preg_split("/\t/", $line, -1, PREG_SPLIT_NO_EMPTY);
			if (empty($line) || count($line) < 5) {
                continue;                
            }
                        
			$insert[] = "('$line[3]', inet_aton('$line[1]'), inet_aton('$line[2]'), '$line[0]', '$line[4]')";
			$banner_stat[$line[3]] = (isset($banner_stat[$line[3]])) ? $banner_stat[$line[3]] + 1 : 1;
			if (count($insert) > 500) {
				$query = "INSERT INTO banner_{$type}_raw (banner_id, ip, local_ip, tstamp, user_id) VALUES ".implode(",", $insert);
				$DB->insert($query);
				$insert = array();
			}
		}
		if (!empty($insert)) {
			$query = "INSERT INTO banner_{$type}_raw (banner_id, ip, local_ip, tstamp, user_id) VALUES ".implode(",", $insert);
			$DB->insert($query);
		}
		
		unlink($tmp_file);
		
		return $banner_stat;
	}
	
	/**
	 * Формирует статистику по показам баннеров
	 *
	 */
	protected function saveStat() 
    {
		global $DB;
		
		// статистика кликов
		$banner_stat = $this->loadFile('click');
		reset($banner_stat);
		while (list($banner_id, $row) = each($banner_stat)) {
			$query = "UPDATE banner_banner SET stat_click=stat_click+$row WHERE id='$banner_id'";
			$DB->update($query);
		}
		
		// статистика просмотров
		$banner_stat = $this->loadFile('view');
		reset($banner_stat);
		while (list($banner_id, $row) = each($banner_stat)) {
			$query = "UPDATE banner_banner SET stat_view=stat_view+$row WHERE id='$banner_id'";
			$DB->update($query);
		}
				
		// Обновляем статистику
		$query = "
			REPLACE INTO banner_stat (banner_id, date, view)
                  SELECT banner_id, date_format(tstamp, '%Y-%m-%d'), COUNT(*)
                    FROM banner_view_raw
                   WHERE tstamp > current_date() - interval 1 day
                GROUP BY banner_id, year(tstamp), month(tstamp), dayofmonth(tstamp)
		";
		$DB->insert($query);
		
		$query = "
			INSERT INTO banner_stat (banner_id, date, click)
                 SELECT banner_id, date_format(tstamp, '%Y-%m-%d'), count(*)
                   FROM banner_click_raw
                  WHERE tstamp > current_date() - interval 1 day
               GROUP BY banner_id, year(tstamp), month(tstamp), dayofmonth(tstamp)
           ON DUPLICATE KEY UPDATE click=VALUES(click)
		";
		$DB->insert($query);
	}
	
	/**
	 * Формирует очередь баннеров, которые необходимо показывать
	 *
	 * @param int $duration
	 */
	public static function buldCache($duration = 7) 
    {
		global $DB;
		
		$query = "TRUNCATE TABLE banner_profile_cache";
		$DB->delete($query);
		
		for ($i = 0; $i < $duration; $i++) {
			$query = "
				INSERT INTO banner_profile_cache (date, hour, group_name, banner_count, profile_id, is_recursive, structure_id, banner_id)
				SELECT 
						current_date() + interval $i day,
						tb_hour.`hour`,
						tb_group.uniq_name,
						tb_group.banner_count,
                        tb_profile.id,
						tb_profile.is_recursive,
						GROUP_CONCAT(distinct tb_structure.structure_id),
						GROUP_CONCAT(distinct tb_banner.id)
				FROM banner_profile_structure tb_structure
				INNER JOIN banner_profile tb_profile ON tb_structure.profile_id = tb_profile.id
				INNER JOIN banner_profile_hour tb_hour ON tb_profile.id = tb_hour.profile_id
                INNER JOIN banner_group as tb_group ON tb_group.profile_id = tb_profile.id					
				INNER JOIN banner_banner as tb_banner ON tb_banner.group_id = tb_group.id
				WHERE tb_profile.date_from <= current_date() + interval $i day
                  AND tb_profile.date_to >= current_date() + interval $i day
				  AND FIND_IN_SET(dayofweek(current_date() + interval $i day), tb_profile.weekdays)
				  AND tb_banner.active=1
				GROUP BY tb_profile.is_recursive, tb_profile.id, tb_hour.`hour`, tb_banner.group_id
			";
			$DB->insert($query);
		}	
	}

	/**
	 * Удаление устаревших данных статистики	 
	 */
	protected function cleanup() 
    {
		global $DB;
		
		$query = "DELETE FROM banner_click_raw WHERE tstamp < CURRENT_DATE() - INTERVAL 120 DAY";
		$DB->delete($query);
		
		$query = "DELETE FROM banner_stat WHERE `date` < CURRENT_DATE() - INTERVAL 360 DAY";
		$DB->delete($query);

		$query = "DELETE FROM banner_view_raw WHERE `tstamp` < NOW() - INTERVAL 3 DAY";
		$DB->delete($query);
	}
	
	/**
	 * Получение $limit слайдов категории
     * @param string $uniq_name уникальное имя категории
     * @param int $limit 
     * @return array $slider
	 */
	public static function getSliders($uniq_name, $limit = null)
    {
		global $DB;
		
        $query = "SELECT tb_group.amount, tb_group.sort_type "
                . " FROM banner_slidergroup as tb_group "
               . " WHERE tb_group.uniq_name='{$uniq_name}' ";
        $info = $DB->query_row($query);                 
        if (empty($info)) {
            return array();        
        }
     
        $order = ($info['sort_type'] == 'rand') ? " RAND() " : " tb_slider.priority ASC";  
          
		$query = "SELECT
                        tb_slider.*,
                        tb_slider.title_".LANGUAGE_CURRENT." AS title,				
                        tb_slider.description_".LANGUAGE_CURRENT." as description,										
                        REPLACE(tb_slider.link, 'http://" . CMS_HOST . "/', '' ) as link
                    FROM `banner_slider` as tb_slider
              INNER JOIN `banner_slidergroup` as tb_group ON tb_group.`id` = tb_slider.`group_id`
                   WHERE tb_slider.active=1 AND tb_group.uniq_name = '{$uniq_name}' AND tb_slider.image <> ''
                ORDER BY $order
        ";
        $query .= ($limit !== null ) ? " LIMIT ".$limit : " LIMIT ".$info['amount'];    
		$data = $DB->query($query);
        
		$sliders = array();
		
        foreach ($data as $index => $row) {
		
            $row['image'] = Uploads::getIsFile('banner_slider', 'image', $row['id'], $row['image'], '' );                        
            $row['image_small'] = Uploads::getIsFile('banner_slider', 'image_small', $row['id'], $row['image_small']);
            if (empty($row['image_small'])) {
                $row['image_small'] = $row['image'];
            }
            
            $row['index'] = $index + 1;

            if (!empty($row['link']) && strpos($row['link'], 'http') === false) {
                $row['link'] = "/" . LANGUAGE_URL . ltrim($row['link'], '/');
            }
            /*
            //video
            $row['video_mp4_file']  = Uploads::getIsFile('banner_slider', 'video_mp4', $row['id'], $row['video_mp4']);
            $row['video_webm_file'] = Uploads::getIsFile('banner_slider', 'video_webm', $row['id'], $row['video_webm']);
           
            if (!empty($row['video_mp4_file']) || !empty($row['video_webm_file'])) {
                $row['is_video'] = true;
                $sliders = array($row);
                break;
            }*/
            
            $sliders[] = $row;
		}
		return $sliders;		
	}
    
	/**
     * Возвращает шаблон слайдера
     * 
     * @global DB $DB
     * @param string $uniq_name - группа
     * @return string type - шаблон
     */
	public static function getSlidersTmpl($uniq_name)
    {
		global $DB;
		
		$query = "SELECT `template` FROM `banner_slidergroup` WHERE uniq_name = '{$uniq_name}'";
		return $DB->result($query);
	}
    
	/**
     * Возвращает шаблон баннера
     * 
     * @global type $DB
     * @param string $uniq_name - группа
     * @return string type - шаблон
     */
	public static function getBannerTmpl($uniq_name)
    {
		global $DB;
        
		$query = "SELECT `template` FROM `banner_group` WHERE uniq_name = '{$uniq_name}'";                            
		return $DB->result($query);
	}
        
    /**
     * Вывод слайдера за условия, когда есть такой шаблон
     * @param string $uniq_name группа слайдера
     * @return string
     */
	public static function displaySlider($uniq_name)
    {		
        $slider = self::getSliders($uniq_name);	        
        if (count($slider) == 0) { 
            return '';
        }
        //шаблон для слайдера
        $template = self::getSlidersTmpl($uniq_name);    
        
        $Template = new Template('banner/' . $template);
        $Template->iterateArray('/banner/', null, $slider);	
        return $Template->display();		        
	}
    
}

?>