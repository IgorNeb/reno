<?php
/**
 * Обрезка или сжатие изображения
 * @package Pilot
 * @subpackage Executables
 * @version 3.0
 * @author Rudenko Ilya <rudenko@delta-x.com.ua>
 * @author Naumenko A.
 * @copyright c-format, 2016
 */

/**
* Определяем интерфейс для поддержки интернационализации
* @ignore
*/
define('CMS_INTERFACE', 'SITE');

/**
* Configuration
*/
require_once('../../../system/config.inc.php');

$DB = DB::factory('default');

$parser = globalVar($_REQUEST['parser'], '');
$url = globalVar($_REQUEST['url'], '');

$src_file = UPLOADS_ROOT.$url;
$dst_file = SITE_ROOT."i/$parser/$url";
$dummy_file = SITE_ROOT.'i/'.$parser.'/1x1.gif';

if (is_file($dst_file)) {
	header('Expires: ' . gmdate("M d Y H:i:s", mktime(0, 0, 0, 1, 1, date('Y')+1)) . ' GMT');
    header('Cache-Control: public');
    header('Content-Type: image/jpeg');
	echo readfile($dst_file);
	exit;
} 

$query = "select * from cms_image_size where uniq_name='$parser'";
$info = $DB->query_row($query);
if ($DB->rows == 0) {
//	header("HTTP/1.0 404 Not Found");
	echo "Parser \"$parser\" not found.";
	exit;
}

if (!is_file($src_file) && !is_file($dummy_file)) {
	Image::createDummy($info['width'], $info['height'], $dummy_file);
	$src_file = $dummy_file;
    
} elseif (!is_file($src_file)) {
	$src_file = $dummy_file;
}

$extension = strtolower( Uploads::getFileExtension($src_file) );
if ($extension == 'gif') {

} else {
    $img = getimagesize($src_file);
    if ($info['crop'] == 1 || ($img[0] > $info['width'] || $img[1] > $info['height'])) {  
                        
        $Image = new Image($src_file);
        $Image->jpeg_quality = 100;
        $Image->resize($info['width'], $info['height'], $info['crop']);        
        if (false === $Image->save($dst_file)) {
            if (!is_dir(dirname($dst_file))) {
                mkdir(dirname($dst_file), 0777, true);
            }
            $Image->save($dst_file);
        }

        if ($info['watermark_id']) {
            //водяной знак уже на ужатое изображение
            $Image = new Image($dst_file);                        
            $Image->watermarkId($info['watermark_id']);
            $Image->save($dst_file);
        }
        
    } else {
        $Image = new Image($src_file);
        $Image->jpeg_quality = 100;
        $Image->watermarkId($info['watermark_id']);
        if (false === $Image->save($dst_file)) {
            if (!is_dir(dirname($dst_file))) {
                mkdir(dirname($dst_file), 0777, true);
            }
            copy($src_file, $dst_file);
        }
    }
    
    chmod($dst_file, 0664);
}

if (!is_file($dst_file) || !is_readable($dst_file)) {
	header("HTTP/1.0 404 Not Found");
	exit;
}

if($extension != 'gif'){
    //сжатие рисунка
    try {
        $image = new Imagick( $dst_file );
        $image->stripImage();
        $image->writeImage( $dst_file );
        $image->clear();
        $image->destroy();
    }
    catch ( Exception $e ){
    }
}

header('Expires: ' . gmdate("M d Y H:i:s", mktime(0, 0, 0, 1, 1, date('Y')+1)) . ' GMT');
header('Cache-Control: public');
header('Last-Modified: ' . gmdate("M d Y H:i:s") . ' GMT');
header('Content-Type: image/jpeg');
echo readfile($dst_file);
exit;

?>