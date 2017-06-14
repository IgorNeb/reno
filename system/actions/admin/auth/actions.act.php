<?php
/** 
 * Сохраниение параметров привилегий 
 * @package Pilot 
 * @subpackage Auth 
 * @author Rudenko Ilya <rudenko@delta-x.com.ua> 
 * @copyright Delta-X, ltd. 2008
 */ 
$action_id    = globalVar($_REQUEST['action_id'], 0);
$group_id     = globalVar($_REQUEST['group_id'], 0);
$module_id    = globalVar($_REQUEST['module_id'], 0);
$table_update = globalVar($_REQUEST['table_update'], array());
$table_select = globalVar($_REQUEST['table_select'], array());
$view         = globalVar($_REQUEST['view'], array());
$event        = globalVar($_REQUEST['event'], array());

$view_insert = $event_insert = $select_insert = $update_insert = array();

/*
 * Получаем номер привилегия. Если нету - создаем
 */

if ( !$module_id ) Action::onError("Не выбран модуль");

$group_name = $DB->result("SELECT uniq_name FROM auth_group WHERE `id`='$group_id'");
if ( !$DB->rows ) Action::onError("Не существует такой роли.");

$action_id = $DB->result( "
    SELECT `id` FROM auth_action
    INNER JOIN `auth_group_action` ON `auth_group_action`.`action_id`=`auth_action`.`id` AND `auth_group_action`.`group_id`='{$group_id}'
    WHERE auth_action.module_id='$module_id'
" );
if (!$action_id){
    $action_id = $DB->insert("INSERT INTO `auth_action` SET `module_id`='{$module_id}', `title_ru`='Привилегии {$group_name}'");
    $DB->insert("INSERT INTO `auth_group_action` SET `group_id`='$group_id',`action_id`='$action_id'");
}

reset($table_update); 
while (list(,$row) = each($table_update)) {
	$update_insert[] = "('$action_id', '$row')"; 
	$select_insert[] = "('$action_id', '$row')";  // все таблицы с привилегией update поддерживают select
}

reset($table_select); 
while (list(,$row) = each($table_select)) {
	$select_insert[] = "('$action_id', '$row')";
}

reset($event); 
while (list(,$row) = each($event)) {
	$event_insert[] = "('$action_id', '$row')"; 
}

reset($view); 
while (list(,$row) = each($view)) {
	$view_insert[] = "('$action_id', '$row')"; 
}

$query = "
	lock tables 
		auth_action_table_select write,
		auth_action_table_update write,
		auth_action_event write,
		auth_action_view write
";
$DB->query($query);

$query = "delete from auth_action_table_select where action_id='$action_id'";
$DB->delete($query);

$query = "delete from auth_action_table_update where action_id='$action_id'";
$DB->delete($query);

$query = "delete from auth_action_view where action_id='$action_id' /* or structure_id in (0".implode(",", $view).") */";
$DB->delete($query);

$query = "delete from auth_action_event where action_id='$action_id'";
$DB->delete($query);

if (!empty($select_insert)) {
	$query = "insert ignore into auth_action_table_select (action_id,table_id) values ".implode(",", $select_insert);
	$DB->insert($query);
}

if (!empty($update_insert)) {
	$query = "insert ignore into auth_action_table_update (action_id,table_id) values ".implode(",", $update_insert);
	$DB->insert($query);
}

if (!empty($event_insert)) { 
	$query = "insert ignore into auth_action_event (action_id,event_id) values ".implode(",", $event_insert);
	$DB->insert($query);
}

if (!empty($view_insert)) {
	$query = "insert ignore into auth_action_view (action_id,structure_id) values ".implode(",", $view_insert);
	$DB->insert($query);
}

$DB->query("unlock tables");
Action::setOk(cms_message('CMS', 'Изменения успешно сохранены'));

?>