<?php
/**
 * вызагрузка из базы языковых параметров в файлы /language/..
 * @package Pilot
 * @subpackage SDK
 * @version 1.0
 * @author Naumenko A.
 * @copyright (c) 2014, c-format
 */

/**
 * Проверяем права доступа на обновление
 */
$table_id = 2816;

if (!Auth::updateTable($table_id)) {
	$query = "SELECT name, title_".LANGUAGE_CURRENT." AS title FROM cms_table WHERE id='".$table_id."'";
	$table = $DB->query_row($query);
	Action::setError(cms_message('CMS', 'У Вас нет прав на добавление значений в таблицу "%s" (%s)', $table['title'], $table['name']));
	Action::onError();
}

cmsMessage::saveDataToFile();