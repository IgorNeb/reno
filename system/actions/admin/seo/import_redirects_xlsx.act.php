<?php
/**
* Загрузка редиректов
*    первый столбец - старый урл
*    второй столбец - новый урл
*
* @package DeltaCMS
* @subpackage Seo
* @version 3.0
* @author Naumenko A.
* @copyright (c) 2015, c-format
*/

ini_set("memory_limit", "100M");

$admin_id = Auth::getUserId();

if( isset($_FILES['xlsx']) && !empty($_FILES['xlsx']) ){
    //импорт группы товаров из xlsx файл

    //загрузка файла
    $filename = UPLOADS_ROOT.'tmp/import_tmp.xlsx';
    $row = $_FILES['xlsx'];
    if ($row['error'] == 0) {
        $extension = strtolower(Uploads::getFileExtension($row['name']));
        if($extension != 'xlsx'){
            exit;
        }
        else{
            if (file_exists($filename))
                unlink($filename);
            Uploads::moveUploadedFile($row['tmp_name'], $filename);
        }
    }

    require_once (SITE_ROOT . "system/libs/excel_classes/PHPExcel.php");
    require_once (SITE_ROOT . "system/libs/excel_classes/PHPExcel/Reader/Excel2007.php");

    $objReader = PHPExcel_IOFactory::createReader('Excel2007');
    $objReader->setReadDataOnly(true);

    //загружаем файл
    $objPHPExcel = $objReader->load($filename);
    $objWorksheet = $objPHPExcel->getActiveSheet();
    $countRows = $objPHPExcel->getActiveSheet()->getHighestRow(); //колиство всего строк

    //запись новых данных в базу
    $insert = array();
       
    for ($indexRow = 1; $indexRow <= $countRows; $indexRow++) {
        
        $old_url =  $objWorksheet->getCell( "A" . $indexRow )->getValue(); 
        $old_url = trim(str_replace(array('http://', 'https://',  'www.'), '', $old_url), '/');
        if (!preg_match('/^'.CMS_HOST.'/', $old_url)) {
            $old_url = CMS_HOST . '/' . $old_url;
        }
        
        $new_url =  $objWorksheet->getCell( "B" . $indexRow )->getValue(); 
        $new_url = trim(str_replace(array('http://', 'https://', 'www.'), '',$new_url), '/');
        if (!preg_match('/^'.CMS_HOST.'/', $new_url)) {
            $new_url = CMS_HOST . '/' . $new_url;
        }
        if ($new_url == CMS_HOST) {
            $new_url .= '/';
        }
        
        if (empty($old_url) || empty($new_url)) {
            continue;
        }
        $insert[] = "( '$old_url', '$new_url', '$admin_id', 'insert')";
        
        if (count($insert) > 500) {
            $DB->query("INSERT IGNORE INTO site_structure_redirect (`url_old`, `url_new`, `admin_id`, `operation`)"
                    . " VALUES " . implode(', ', $insert));
            $insert = array();
        }
    }

    // save catalog_equip_data
    if (!empty($insert)) {
        $DB->query(" INSERT IGNORE INTO site_structure_redirect (`url_old`, `url_new`, `admin_id`, `operation`)"
                . " VALUES " . implode(', ', $insert));
    }
    
    unset($objWorksheet);


} //end if

$_REQUEST['_return_path'] = "/admin/seo/site/redirects/?";


