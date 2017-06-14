<?php
/**
* Класс по работе с закачаными файлами
* @package DeltaCMS
* @subpackage Libraries
* @version 2.0
* @author Rudenko Ilya <rudenko@delta-x.com.ua>
* @copyright Delta-X ltd, 2004
*/

class Uploads
{
	
	/**
	 * Определяет путь к файлу, который сохраняется через редактор или систему в UPLOADS_ROOT и CONTENT_ROOT
	 *
	 * @param string $table_name
	 * @param string $field_name
	 * @param int $id
	 * @return string
	 */
	public static function getStorage($table_name, $field_name, $id)
    {
		return strtolower($table_name . '/' . $field_name . '/' . self::getIdFileDir($id));
	}
		
	/**
	 * По имени файла определяет его URL адрес
     * 
	 * @param string $file
	 * @return string
	 */
	public static function getURL($file)
    {
		if (false === strpos($file, SITE_ROOT)) {
			return '';
		} else {
			return substr($file, strlen(SITE_ROOT) - 1);
		}
	}
	
    /**
	 * По имени файла определяет адрес картинки c префиксом /uploads/
     * 
	 * @param string $file
	 * @return string
	 */
	public static function getUploadsURL($file)
    {
		return substr($file, strlen(SITE_ROOT)-1);
	}
        
	/**
	 * По имени файла определяет адрес картинки без префикса /uploads/
     * 
	 * @param string $file
	 * @return string
	 */
	public static function getImageURL($file)
    {
		if (false === strpos($file, UPLOADS_ROOT)) {
			return '';
		} else {
			return substr($file, strlen(UPLOADS_ROOT));
		}
	}
	
	/**
	 * Определяет расширение файла
     * 
	 * @param string $file
	 * @return mixed
	 */
	public static function getFileExtension($file) 
    {
		if (false === ($start = strrpos($file, '.'))) {
			return false;
		} else {
			return substr($file, $start + 1);
		}
	}
	
	/**
	* Определяет группирующую директорию картинки
	* 
	* Когда закачивается большое количество картинок, например несколько тысяч,
	* то с ними тяжело работать. Для этого мы разбиваем их по сотням в директории
	* 
	* @param int $id
	* @return string
	*/
	public static function getIdFileDir($id)
    {
		return sprintf("%04d/%02d", intval($id / 100), intval($id % 100));
	}
	
	/**
	* Определяет имя файла
     * 
	* @param string $table_name
	* @param string $field_name
	* @param int $id
	* @param string $extension
	* @return string
	*/
	public static function getFile($table_name, $field_name, $id, $extension)
    {
		return UPLOADS_ROOT . $table_name .'/'. $field_name .'/'. self::getIdFileDir($id) .'.'. $extension;
	}
	
	/**
	 * Создает HTML для картинки или Flash
	 * Раньше была функция Uploads::getHTML($image, $big_image = '', $attrib = ' border="0"', $alt = '')
	 *
	 * @param string $image_file
	 * @param string $attrib
	 */
	public static function htmlImage($image_file, $attrib = '')
    {
		$image_url = self::getURL($image_file);
		
		// Проверка наличия картинки
		if (!is_file($image_file)) {
			return '<img src="/img/shared/1x1.gif" alt="'.cms_message('CMS', 'Не найден файл с картинкой %s', $image_url).'">';
		}
		
		$thumbnail_file = self::getThumb($image_file);
		$image_type = getimagesize($image_file);
		if ($image_type[2] == IMAGETYPE_SWF || $image_type[2] == IMAGETYPE_SWC) {
			// Flash
			return '
				<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0" '.$image_type[3].' id="map">
				<param name="allowScriptAccess" value="sameDomain" />
				<param name="movie" value="'.$image_url.'" />
				<param name="menu" value="false" />
				<param name="quality" value="high" />
				<param name="bgcolor" value="#FFFFFF" />
				<embed src="'.$image_url.'" menu="false" quality="high" bgcolor="#FFFFFF" '.$image_type[3].' name="map" align="middle" allowScriptAccess="sameDomain" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />
				</object>
			';
		} elseif (is_file($thumbnail_file)) {
			// У картинки есть пиктограмма
			$thumbnail_type = getimagesize($thumbnail_file);
			$thumbnail_url = self::getURL($thumbnail_file);
			return '<a class="image" href="javascript:void(0);" onclick="showImage(\''.$image_url.'\');"><img src="'.$thumbnail_url.'" '.$thumbnail_type[3].' '.$attrib.'></a>';
		} else {
			// У картинки нет пиктограммы
			return '<img src="'.$image_url.'"  '.$attrib.'>';
		//	return '<img src="'.$image_url.'" '.$image_type[3].' '.$attrib.'>';
		}
	}
	
	
	/**
	 * Возвращает путь к файлу с пиктограммой не зависимо от того есть он или нет
	 *
	 * @param string $file
	 * @return string
	 */
	public static function getThumb($file) 
    {
		return substr($file, 0, strrpos($file, '.')) . '_thumb.jpg';
	}
	
	/**
	 * Возвращает пиктограмму к картинке
	 *
	 * @param string $image_file
	 * @param string $attrib
     * @return string
	 */
	public static function thumbImage($image_file, $attrib = ' border="0"')
    {
		// Проверка наличия картинки
		if (!is_file($image_file)) {
			return '<img src="/img/shared/1x1.gif" alt="'.cms_message('CMS', 'Не найден файл с картинкой %s', $image_file).'">';
		}
		
		$thumbnail_file = self::getThumb($image_file);
		$file = (is_file($thumbnail_file)) ? $thumbnail_file : $image_file;
		$type = getimagesize($file);
		return '<img src="'.substr($file, strlen(SITE_ROOT)-1).'" '.$type[3].' '.$attrib.'>';
	}
		
	/**
	 * Создает HTML для картинки, которая увеличивется через JqueryLightbox
	 *
	 * @param string $image_file
	 * @param string $attrib
	 */
	public static function lightboxImage($image_file, $title, $group = 'group', $attrib = ' border="0"')
    {
		// Проверка наличия картинки
		if (!is_file($image_file)) {
			return '<img src="/img/shared/1x1.gif" alt="'.cms_message('CMS', 'Не найден файл с картинкой %s', $image_file).'">';
		}
		
		$image_url = self::getURL($image_file);
		$thumbnail_file = self::getThumb($image_file);
		
		if (is_file($thumbnail_file)) {
			// У картинки есть пиктограмма
			$thumbnail_type = getimagesize($thumbnail_file);
			return '<a target="_blank" rel="lightbox-'.$group.'" href="'.$image_url.'" title="'.addcslashes($title, '"').'"><img src="'.substr($thumbnail_file, strlen(SITE_ROOT)-1).'" '.$thumbnail_type[3].' '.$attrib.'></a>';
		} else {
			// У картинки нет пиктограммы
			$image_type = getimagesize($image_file);
			return '<img src="'.$image_url.'" '.$attrib.' />';
            //return '<img src="'.$image_url.'" '.$image_type[3].' '.$attrib.' />';
		}
	}
    
	/**
	* Перемещает закачанный файл в новую директорию, если директория не существует, то создает ее
	* @param $uploaded_file
	* @param $new_file
	* @return bool
	*/
	public static function moveUploadedFile($uploaded_file, $new_file, $compression=true)
    {
		if (!is_dir(dirname($new_file))) {
			makedir(dirname($new_file), 0777, true);
		}
		
		$return = move_uploaded_file($uploaded_file, $new_file);
		if ($return === true) {
			chmod($new_file, 0640);
		}
           
        /*
         * сжатие изображения и обрезка больших
         */
        $ext = substr(strrchr($new_file, '.'), 1);
        $ext = strtoupper($ext);
        $formats = array("BMP", "JPEG", "JPG", "PNG", "TIFF");
        if ($compression && $return && in_array($ext, $formats)) {           
            $max_width  = (int)SITE_MAX_ALLOWED_WIDTH;
            $max_height = (int)SITE_MAX_ALLOWED_HEIGHT;
            Image::compressImagick($new_file, $max_width, $max_height);                                  
        }
		return $return;
	}
	
    /**
     * Загрузка изображения из удаленного сервера
     * 
     * @global DB $DB
     * @param string $link - ссылка на файл
     * @param string $table_name
     * @param string $field_name
     * @param int $id
     * @param string $extension
     */
    public static function uploadByLink($link, $table_name, $field_name, $id, $extension = null) 
    {
        global $DB;
        
        if ( is_null($extension) ) {
            $extension = Uploads::getFileExtension($link);
        }
        
        $newFile = UPLOADS_ROOT . Uploads::getStorage( $table_name, $field_name, $id ).'.'.$extension;    
        if ( !file_exists($newFile) ) {
            Filesystem::touch($newFile);
        }

        file_put_contents($newFile, file_get_contents($link));
        
        $DB->update("update $table_name set `$field_name` = '{$extension}' where id = '$id' ");
    }
    
	/**
	* Возврящает сообщение об ошибе в случае если такова имела место.
	* @static 
	* @param int $errno
	*/
	public static function check($errno) 
    {
		$return = '';
		switch ($errno) {
			case UPLOAD_ERR_OK:
				$return = '';
				break;
			case UPLOAD_ERR_INI_SIZE:
				$return = cms_message('CMS', 'The uploaded file exceeds the upload_max_filesize directive in php.ini.');
				break;
			case UPLOAD_ERR_FORM_SIZE:
				$return = cms_message('CMS', 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.');
				break;
			case UPLOAD_ERR_PARTIAL:
				$return = cms_message('CMS', 'The uploaded file was only partially uploaded.');
				break;
			case UPLOAD_ERR_NO_FILE:
				$return = cms_message('CMS', 'No file was uploaded.');
				break;
			case UPLOAD_ERR_NO_TMP_DIR:
				$return = cms_message('CMS', 'Missing a temporary folder.');
				break;
			case UPLOAD_ERR_CANT_WRITE:
				$return = cms_message('CMS', 'Failed to write file to disk.');
				break;
		}
		return $return;
	}
	
	/**
	 * Выводит ссылку на скачивание аттача
	 *
	 * @param string $url
	 * @param string $title
	 * @return string
	 */
	public static function htmlAttach($url, $title = '') 
    {
		if (substr($url, 0, strlen(UPLOADS_DIR) + 1) != '/'.UPLOADS_DIR) {
			return '';
		}
		return '<a href="/tools/cms/site/download.php?url='.$url.'">'.$title.'</a>';
	}
    
	/**
	 * Иконка к файлу, которая соответсвует его типу
	 *
	 * @param string $file
	 * @return string
	 */
	public static function getIcon($file)
    {
		$extension = strtolower(self::getFileExtension($file));
		if (is_file(SITE_ROOT.'img/shared/ico/'.$extension.'.gif')) {
			$img = '<img src="/img/shared/ico/'.$extension.'.gif" border="0">';
		} else {
			$img = '';
		}
		return $img;
	}
	
	/**
	 * Удаляет файл с картинкой
	 *
	 * @param string $file
	 */
	public static function deleteImage($file)
    {
		global $DB;
		
		$url = substr($file, strlen(UPLOADS_ROOT));
                
		$delete[] = $file;
		$delete[] = substr($file, 0, strrpos($file, '.')).'_thumb.jpg';
		
		$parser = $DB->fetch_column("SELECT uniq_name FROM `cms_image_size` ");
		reset($parser);
		while (list(, $uniq_name) = each($parser)) {
            $delete[] = SITE_ROOT.'i/'.$uniq_name.'/'.$url;
		}
		
		reset($delete);
		while (list(, $filename) = each($delete)) {
            if (is_file($filename) && is_writable($filename)) {
                unlink($filename);
            }
		}
	}
        
	/**
	* Возвращает имя файла, если такой файл существует
	* @param string $table_name
	* @param string $field_name
	* @param int $id
	* @param string $extension
	* @param string $path - /uploads/ or /i/
	* @return string
	*/
	public static function getIsFile($table_name, $field_name, $id, $extension, $path='/uploads/')
    {
        $filename = self::getFile($table_name, $field_name, $id, $extension);                
        return (file_exists($filename) && is_readable($filename)) ? $path . self::getImageURL($filename) : ""; 
	}
        
    /**
	 * Создает HTML для картинки, которая увеличивется через FancyBox
	 *
	 * @param string $image_file
	 * @param string $title
	 * @param int $thumb_width
     * @return string image
	 */
	public static function fancyImage($image_file, $title = '', $thumb_width = 200) 
    {
		// Проверка наличия картинки
        if (empty($image_file)) {
            return '';
        }
                
        $image_file = trim($image_file, '/');
		if (!is_file(SITE_ROOT . $image_file)) {
            return '<img src="/img/shared/1x1.gif" alt="Не найден файл с картинкой '. $image_file .'">';
		}
		list($width, $height, $type, $attr) = getimagesize(SITE_ROOT . $image_file);
                
        $image_file = '/' . $image_file;
        if ($width > $thumb_width) {
            return "<a href='$image_file' title='$title' class='fancy'><img src='$image_file' alt='$title' style='width:{$thumb_width}px' alt='$title'/></a>";
        }
        else{
            return "<img src='$image_file' alt='$title' />";
        }
	}
    
    /**
     * Изображение в новом окне
     * 
     * @param string $image_file
     * @param string $title
     * @param int $thumb_width
     * @return string
     */
    public static function targetImage($image_file, $title = '', $thumb_width = 200) {
		// Проверка наличия картинки
        if (empty($image_file)) {
            return '';
        }
                
        $image_file = trim($image_file, '/');
		if (!is_file(SITE_ROOT . $image_file)) {
            return '<img src="/img/shared/1x1.gif" alt="Не найден файл с картинкой '. $image_file .'">';
		}
		list($width, $height, $type, $attr) = getimagesize(SITE_ROOT . $image_file);
                
        $image_file = '/' . $image_file;
        return "<a href='$image_file' title='$title' target='_blank'><img src='$image_file' alt='$title' style='width:{$thumb_width}px' alt='$title'/></a>";
    }
    
    /**
	 * Создает HTML для вибео, которое проигривается через FancyBox
	 *
	 * @param string $title подпись 
	 * @param string $code код 
	 * @param string $type сервис 
     * @return string
	 */
    public static function fancyVideo($title, $code, $type = 'youtube')
    {
        $href = ($type == 'youtube') 
            ? "//www.youtube.com/watch?v={$code}?fs=1&amp;autoplay=1" 
            : "//vimeo.com/moogaloop.swf?clip_id={$code}&fs=1&amp;autoplay=1";        

        return "<a href='$href' class='fancyvideo'>".$title."</a>";   
    }
   
}


?>