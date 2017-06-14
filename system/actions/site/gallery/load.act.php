<?php
/**
 * Загрузить фотографии к модификации
 * @package DeltaCMS
 * @subpackage Gallery
 * @author Nauemnko A.
 * @copyright (c) 2017, c-format
 */

$modif_id = globalVar($_REQUEST['modif_id'], 0);
$type = globalVar($_REQUEST['type'], 'medias');

$videos = array();

$Auto = new Auto($modif_id);
$gallery = $Auto->getModifGallery($type, 100);    
    
$_RESULT['auto_gallery']  = $gallery;
$_RESULT['javascript']  = "isotopeAjax();";
//x($gallery);
exit();
  
if($type == 'media'){    
    $gallery = $Auto->getModelGallery( array(), 'video' );  
    $gallery .= $Auto->getModelGallery( array(), $type, 100, AUTO_MODEL_PHOTO_COUNT );  
    
    $selector = "model_gallery__wrap";
}  
else{
    $gallery = $Auto->getModelGallery( array(6, 3, 3, 3, 3), $type );    
    
    $selector = "model_fancy_block";
}

$_RESULT[ $selector ] = $gallery . "<div class='clear'>&nbsp;</div>";
//'easeInSine'
$_RESULT['javascript'] = " $('#".$selector." > div').each(function(i){ $(this).hide().delay(100*i).show(200); }); ";
if( $type == 'video' || $type=='media' ){
    $_RESULT['javascript'] .= "clickFancyVideo();";
}

/** 
 * Загрузка галереи
 * @package DeltaCMS
 * @subpackage Gallery
 */

$service_id = globalVar($_REQUEST['service_id'], 0);
$collection_id = globalVar($_REQUEST['collection_id'], 0);
$page    = globalVar($_REQUEST['page'], 0);

if (empty($service_id)) {
    exit();
}

if (!empty($collection_id)) {
    $gallery = new Gallery('site_service_gallery', $collection_id);
} else {
    $gallery = new Gallery('site_service', $service_id);
}

$photos = $gallery->getServicePhotos(GALLERY_PAGE_COUNT, $page);	

if (count($photos) == 0) {
    exit();
}

$Template = new Template('gallery/block');
$Template->iterateArray('/gallery/', null, $photos);
$_RESULT['modal_form'] = $Template->display();

$_RESULT['javascript'] = "setGallery();";

$limit_show = (GALLERY_PAGE_COUNT * $page);
if ($gallery->total > $limit_show) {    
    $_RESULT['javascript'] .= "$('.plus-more').attr('onclick', 'loadMorePhoto(".$service_id.", ".$collection_id.", ".($page+1).");return false;');";
} else {
    $_RESULT['javascript'] .= "$('.plus-more').remove();";
}
 