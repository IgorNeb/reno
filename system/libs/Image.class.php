<?php
/**
 * Класс по работе с картинками
 * 
 * @package DeltaCMS
 * @subpackage CMS 
 * @version 3.0
 * @author Naumenko A.
 * @copyright c-format, ltd. 2014
 */

class Image
{
	/**
	 * Информация о картинке
	 * 
	 * @var array
	 */
	private $info = array();
	
	/**
	 * Качество создаваемых JPEG картинок
	 * 
	 * @var int 
	 */
	public $jpeg_quality = 80;

	/**
	 * Файл картинки
	 * 
	 * @var mixed 
	 */
	private $file = '';
	
	/**
	* Файл сохраненной картинки
	*
	* @var mixed
	*/
	private $dstfile = '';
	
	/**
	 * Ширина картинки
	 * 
	 * @var int 
	 */
	public $width = 0;
	
	/**
	 * Высота картинки
	 * 
	 * @var int  
	 */
	public $height = 0;
	
	/**
	 * Дескриптор картинки
	 * 
	 * @var resource
	 */
	private $im;
	
	/**
	 * Флаг, указывающий на то, что картинка была изменениа
	 *
	 * @var bool
	 */
	private $changed = false;
	
	/**
	 * Конструктор класса
	 * 
	 * @param string $file
	 */
	public function __construct($file) 
    {
		if (!is_file($file)) {
			trigger_error(cms_message('CMS', 'Не найден файл с картинкой %s', $file), E_USER_ERROR);
		}
		
		$this->file = $file;
		$this->info = getimagesize($this->file);
		
		if (empty($this->info)) {
			// не поддерживаемый тип файла
			trigger_error(cms_message('CMS', 'Неподдерживаемый тип картинки: %s', $file), E_USER_ERROR);
		}
		
		$this->width = $this->info[0];
		$this->height = $this->info[1];
		$this->im = $this->load($this->file);
		
		/*
		 * Так как IE не поддерживает формат CMYK для картинок JPEG,
		 * необходимо их конвертировать, так же после конвертации
		 * они занимают меньше места
		 */
		if ($this->info[2] == IMAGETYPE_JPEG && isset($this->info['bits']) && $this->info['bits'] == 4) {
			$dst = imagecreatetruecolor($this->width, $this->height);
			imagecopy($dst, $this->im, 0, 0, 0, 0, $this->width, $this->height);
			imagedestroy($this->im);
			$this->im = $dst;
		}

	}
	
	
	/**
	 * Открывает картинку на редактирование
	 * 
	 * @param string $file
	 * @return resource
	 */
	private function load($file) 
    {
		$type = getimagesize($file);
		switch ($type[2]) {
			case IMAGETYPE_GIF:
				return imagecreatefromgif($file);
				break;
			case IMAGETYPE_JPEG:
				return imagecreatefromjpeg($file);
				break;
			case IMAGETYPE_PNG:
				return imagecreatefrompng($file);
				break;
			default:
				trigger_error(cms_message('CMS', 'Не найден файл с картинкой %s', $file), E_USER_ERROR);
				break;
		}
	}
	
	/**
	 * Создает новую картинку с измененными размерами в формате JPG
	 * если возникнет идея изменять размеры картинок и сохранять их в gif,
	 * обязательно перед сохранением использовать ф-ю imagecolormatch
	 * 
	 * @param int $width
	 * @param int $height
	 * @param bool $resize
	 * 
	 * @return mixed
	 */
	private function changeSize($width, $height, $resize)
    {
		if ($resize === false && $this->width < $width && $this->height < $height) {
			// изменение размера невозможно так как картинка меньше чем создаваемая
			return false;
		}
		
		// Не указываем этот параметр. так как метод changeSize вызывается и для создания пиктограммы
		// $this->changed = true;
		
		if ($resize) {
			$width_scale = 100 * $width / $this->width;
			$height_scale = 100 * $height / $this->height;
			$scale = ($width_scale > $height_scale) ? $width_scale : $height_scale;
			 
			$new_width  = ($width_scale > $height_scale) ? $width : intval($this->width * $scale / 100);
			$new_height = ($width_scale > $height_scale) ? intval($this->height * $scale / 100) : $height;
		}  
		else{
			$new_width = 100 * $width / $this->width;
			$new_height = 100 * $height / $this->height;
			$scale = ($new_height > $new_width) ? $new_width : $new_height;

			$new_width = intval($this->width * $scale / 100);
			$new_height = intval($this->height * $scale / 100);
		}
		
		if (empty($new_height) || empty($new_width)) {
            return $this->im;
        }

		$im = imagecreatetruecolor($new_width, $new_height);
		imagefill($im, 0,0, imagecolorallocate($im, 255, 255, 255));
		imagecopyresampled($im, $this->im, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height);
		
		// устанавливаем новые размеры картинки
		if ($resize) {
			$this->width = $new_width;
			$this->height = $new_height;
		}
		
		return $im;
	}
    
    /**
     * Вычисление размеров для сжатия изображения
     * 
     * @param int $size
     * @return array
     */
	public function compress($size)
    {
		if ($this->width > $size) {
			$k = ceil($this->width / $size);
			$len['width'] = $size;
			$len['height']	= ceil($this->height / $k);
		} elseif ($this->height > $size) {
			$k = ceil($this->height / $size);
			$len['height'] = $size;
			$len['width']	= ceil($this->width / $k);
		} else {
            $len['width'] = $this->width;
			$len['height'] = $this->height;
		}
		return $len;
	}
	
    /**
     * Сжатие изображений с помощью библиотеки Imagick
     * @param string $filename - путь
     * @param int $max_width
     * @param int $max_height
     * @return boolean
     */
    public static function compressImagick($filename, $max_width, $max_height)
    {
        if (!file_exists($filename)) {
            return false;
        }
        
        try {
            $image = new Imagick($filename);   
            
            $img    = getimagesize($filename);
            
            if ($img[0] > $max_width || $img[1] > $max_height){  
                //сжатие слишком больших изображений   
                if ($img[0] > $max_width) {
                    $max_height = 0;
                } else {
                    $max_width = 0;
                }
                $image->resizeImage($max_width, $max_height, Imagick::FILTER_UNDEFINED, 1);
            }    
        
            $image->stripImage();
            $image->writeImage($filename);
            $image->clear();
            $image->destroy();   
        }
        catch ( Exception $e ){
        }
        return true;
    }
    
    /**
     * Обрезка изображения
     * 
     * @param resource $im
     * @param int $width_desired
     * @param int $height_desired
     * @return resource 
     */
	public function crop($im, $width_desired, $height_desired)
    {
		$width_real  = imageSX($im);
		$height_real = imageSY($im);
		
		$width_diff  = $width_real - $width_desired;
		$height_diff = $height_real - $height_desired;
		
		$width_shift  = round($width_diff / 2);
		$height_shift = round($height_diff / 2);
		 
		if ($width_diff <= 0 && $height_diff <= 0) {
            return $im;
        }
		
		if ($width_diff > 0 && $height_diff > 0) {
			$img = imagecreatetruecolor($width_desired, $height_desired);
			imagefill($img, 0,0, imagecolorallocate($img, 255, 255, 255));
	   	 	imagecopy($img, $im, 0, 0, $width_shift, $height_shift, $width_desired, $height_desired);
	   	 	  
		} elseif ($width_diff > 0 && $height_diff <= 0) {
			$img = imagecreatetruecolor($width_desired, $height_real);
	  	  	imagefill($img, 0,0, imagecolorallocate($img, 255, 255, 255));
	   	 	imagecopy($img, $im, 0, 0, $width_shift, 0, $width_desired, $height_real);
	   	 	
		} elseif ($width_diff <= 0 && $height_diff > 0) {
			$img = imagecreatetruecolor($width_real, $height_desired);
	  	  	imagefill($img, 0,0, imagecolorallocate($img, 255, 255, 255)); 
	   	 	imagecopy($img, $im, 0, 0, 0, $height_shift, $width_real, $height_desired);
		}
		
		return $img;
	}
	
    /**
	 * Добавляем скругленные углы
	 * @param int $radius_x - радиус скругления по X
	 * @param int $radius_y - радиус скругления по Y
	 */
	
	public function addRoundEdges($radius_x = 3, $radius_y = 3) 
    {
		$thumb = new Imagick();
		//читаем картинку по полному пути
		$thumb->readImage($this->dstfile);
        
		$thumb->setImageFormat("png");
		$thumb->roundCorners($radius_x, $radius_y);
		file_put_contents($this->dstfile, $thumb);
		//подчищаем за собой		
		//$canvas->destroy();		
		//$shadow->destroy();
		$thumb->destroy();
	}
	
	/**
	 * Изменяет размер картинки
	 *
	 * @param int $width
	 * @param int $height
     * @param bool $resize
     * @return bool
	 */
	public function resize($width, $height, $resize = true) 
    {
		if (empty($height) || empty($width)) {
			return false;
		}
		
		$im = $this->changeSize($width, $height, $resize); 
		if ($resize) {
            $im = $this->crop($im, $width, $height);
        }
		 
		if ($im !== false) {
			$this->im = $im;
			$this->changed = true;
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Создает пиктограмму для картинки
	 *
	 * @param string $file
	 * @param int $width
	 * @param int $height
     * @return bool
	 */
	public function thumb($file, $width, $height) 
    {
		if (empty($height) || empty($width)) {
			return false;
		}
		
		$im = $this->changeSize($width, $height, false);
		if ($im !== false) {
			if (!is_dir(dirname($file))) {
				mkdir(dirname($file), 0777, true);
			}
			imagejpeg($im, $file, $this->jpeg_quality);
			imagedestroy($im);
			return true;
		} else {
			return false;
		}
	}
    
	/**
	 * Наложение водяного знака на картинку
	 *
	 * @param int $watermark_id
	 */
	public function watermarkId($watermark_id) 
    {
		global $DB;
        
		if (empty($watermark_id)) {
			return false;
		}
		
		$query = "SELECT * FROM cms_watermark WHERE id='$watermark_id'";
		$watermark = $DB->query_row($query);
		if (empty($watermark)) {
			return false;
		}
		$file = UPLOADS_ROOT.'cms_watermark/file/'.Uploads::getIdFileDir($watermark['id']).'.'.$watermark['file'];
		$this->watermark($file, $watermark['pos_x'], $watermark['pos_y'], $watermark['pad_x'], $watermark['pad_y'], $watermark['transparency']);
	}
	
	/**
	 * Наложение одной картинки поверх другой с прозрачностью
	 * 
	 * @param string $logo_file
	 * @param string $pos_x
	 * @param string $pos_y
	 * @param int $padding_x
	 * @param int $padding_y
	 * @param int $alpha 0 - прозрачный - 100 видимый
	 */
	public function watermark($logo_file, $pos_x, $pos_y, $padding_x, $padding_y, $alpha) 
    {
		if (!is_file($logo_file)) {
			// Не найдена картинка с водяным знаком
			return false;
		}
		
		$info = getimagesize($logo_file);
		$width = $info[0];
		$height = $info[1];
		if ($width >= $this->width || $height > $this->height) {
			// Водяной знак больше чем сама картинка
			return false;
		}
		$this->changed = true;
		
		// Определяем координаты верхнего левого угла водяного знака
		if ($pos_y == 'bottom') {
			$y = $this->height - $height - $padding_y;
		} elseif ($pos_y == 'top') {
			$y = $padding_y;
		} else {
			$y = intval($this->height / 2 - $height / 2);
		}
		
		if ($pos_x == 'right') {
			$x = $this->width - $width - $padding_x;
		} elseif ($pos_x == 'left') {
			$x = $padding_x;
		} else {
			$x = intval($this->width / 2 - $width / 2);
		}
		
		$logo_im = $this->load($logo_file);
		if ($alpha == 0) {
			imagealphablending($this->im, true);
			imagecopy($this->im, $logo_im, $x, $y, 0, 0, $width, $height);
		} else {
			imagecopymerge($this->im, $logo_im, $x, $y, 0, 0, $width, $height, 100-$alpha);
		}
		imagedestroy($logo_im);
		
		return true;
	}
	
    /**
     * Текст на изображение
     * @param string $src_file
     * @param string $text
     */
    public function setText($src_file, $text)
    {
        $tmp_file = str_replace('.jpg', '', $src_file).'_tmp.jpg';
        $image = new Imagick();
        $draw  = new ImagickDraw();
        $pixel = new ImagickPixel('rgba(100%, 75%, 0%, 1.0)');

        /* New image */
        $image->newImage(280, 60, $pixel);

        /* Black text */
        $draw->setFillColor('black');

        /* Font properties */
        $draw->setFont(SITE_ROOT . "system/fonts/verdana.ttf");
        $draw->setFontSize( 16 );
        $draw->setGravity(Imagick::GRAVITY_CENTER );

        /* Create text */
        //$image->annotateImage($draw, 0, 0, 0, 'User Name - CEO');               
        $image->annotateImage($draw, 0, 0, 0, $text);
        $image->writeImage($tmp_file);  
        
        if (!empty($tmp_file) && file_exists($tmp_file)) {
            //водяной знак уже на ужатое изображение                            
            $this->watermark($tmp_file, 'left', 'top', 52, 220, 0);
            Filesystem::delete($tmp_file);
        }
       // return $tmp_file;
    }
    
	/**
	 * Сохраняет картинку
	 * 
     * @param string $file
	 */
	public function save($file = '') 
    {
		$this->dstfile = $file;
        
		if (!$this->changed) {
			return false;
		}
        
		$file = (empty($file)) ? $this->file : $file;
		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0750, true);
		}
		imagejpeg($this->im, $file, $this->jpeg_quality);		
	}
	
	/**
	 * Создает пустую белую картинку
	 *
	 * @param int $width
	 * @param int $height
	 * @param int $file
	 */
	public static function createDummy($width, $height, $file) 
    {
		$im = imagecreatetruecolor($width, $height);
		imagefill($im, 0,0, imagecolorallocate($im, 255, 255, 255));
		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0777, true);
		}
		imagegif($im, $file);
	}
    
}
?>