<?php
/**
 * Удаление файла
 * @package Pilot
 * @subpackage CMS
 * @author Naumenko A.
 * @copyright c-format, 2014
 */
$id = globalVar($_REQUEST['id'], 0);
$filename = globalVar($_REQUEST['filename'], '');

// Проверка прав редактирования таблицы пользователем
if (!Auth::updateTable('gallery_photo')) {
	Action::setError('У Вас нет прав на редактирование таблицы %s.', $table_name);
	exit;
}

$DB->delete("delete from `gallery_photo` where id = '$id'");

Uploads::deleteImage( UPLOADS_ROOT.$filename );

//удаление данных с кеша
$Cache = new CacheSql('site_service_gallery');
$Cache->delete();

$Cache = new CacheSql('shop_product');
$Cache->delete();

$_RESULT['javascript'] = "$('#il_$id').remove();";
Action::setOk("Файл удален");
exit;
?>