<?php
/** 
 * Закачка файлов в галерею 
 * @package DeltaCMS
 * @subpackage Gallery 
 * @author Naumenko A.
 * @copyright c-format, 2014
 */ 

//$upload_handler = new UploadHandler();
$table_name  = globalVar($_REQUEST['table'], '');
$field       = globalVar($_REQUEST['field'], '');
$parent_id   = globalVar($_REQUEST['id'], 0);
$current_url_link   = globalVar($_REQUEST['current_url_link'], '');

if (empty($field)) {
    echo(" <script> location.reload(); </script> ");
    exit;    
}

// Добавляем картинку в БД
$query = "select ifnull(max(priority),0)+1 from gallery_photo where `group_id`='$parent_id'";
$priority = $DB->result($query, 1);

$extension = strtolower(Uploads::getFileExtension($_FILES['file']['name']));
$query = "insert into gallery_photo (group_id, group_table_name, photo, priority)"
        . " values ('$parent_id', '$table_name', '".$DB->escape($extension)."', '$priority')";
$id = $DB->insert($query);

//загружаем файл
$filename = UPLOADS_ROOT . Uploads::getStorage('gallery_photo', 'photo', $id ).'.'.$extension;
$dir = substr($filename, 0, strrpos($filename, '/') + 1);

$upload_handler = new UploadHandler(array(
    'upload_dir' => $dir, 
    'image_filename' => $filename)
);

$filename = Uploads::getImageURL($filename);

if ($extension != 'gif') { 
    //сжатие изображения и обрезка больших
    $max_width  = (int)GALLERY_MAX_ALLOWED_WIDTH;
    $max_height = (int)GALLERY_MAX_ALLOWED_HEIGHT;
    Image::compressImagick(UPLOADS_ROOT . $filename, $max_width, $max_height);
}

//вывод в шаблон
$table_id = $DB->result("SELECT id FROM cms_table WHERE name='gallery_photo'"); 
$row = array(
    "id" => $id, 
    "title" => "", 
    "photo" => $filename, 
    "active" => 1
);
$filesize = filesize(UPLOADS_ROOT . $filename);
if ($filesize > 1024 * 1024) {
    $row['size'] = number_format( ($filesize * 100 / (1024 * 1024)) / 100, 2, '.', ' ')  . ' MB';
} else {
    $row['size'] = number_format( ($filesize * 100 / 1024) / 100 ) . ' KB';
}

$Template =  new Template(SITE_ROOT.'templates/cms/admin/ui_gallery_photo');               
$Template->setGlobal('table_id', $table_id);
$Template->setGlobal('current_url_link', $current_url_link);
$Template->iterate('/photo/', null, $row);
echo $Template->display();

exit;
?>