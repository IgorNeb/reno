<?php
/** 
 * Копирование привилегий роли 
 * @package DeltaCMS 
 * @subpackage Auth 
 * @author Naumenko A.
 * @copyright c-format
 */ 

$group_id = globalVar($_REQUEST['group_id'], 0);
$from_group_id = globalVar($_REQUEST['from_group_id'], 0);

if ($group_id == 0 || $group_id == 5 || $from_group_id == 0 || $from_group_id == 5) {
    Action::onError("Не возможно скопировать роль");
}

$group = $DB->query_row("SELECT * FROM auth_group WHERE id='$group_id'");

$module_ids = $DB->fetch_column("SELECT module_id FROM auth_action WHERE 1 GROUP BY id");

if (count($module_ids) == 0) {
    Action::onError("Не возможно скопировать роль");
} 

$update_insert = $select_insert = $view_insert = $event_insert = array();

foreach ($module_ids as $module_id) {
    $action_id = $DB->result( "
        SELECT `id` FROM auth_action as tb_action
        INNER JOIN `auth_group_action` as tb_group ON tb_group.`action_id`=tb_action.`id` AND tb_group.`group_id`='{$group_id}'
        WHERE tb_action.module_id='$module_id'
    ");
        
    if ($action_id) {     
        //clear rules
        $query = "delete from auth_action_table_select where action_id='$action_id'";
        $DB->delete($query);

        $query = "delete from auth_action_table_update where action_id='$action_id'";
        $DB->delete($query);

        $query = "delete from auth_action_view where action_id='$action_id'";
        $DB->delete($query);

        $query = "delete from auth_action_event where action_id='$action_id'";
        $DB->delete($query);
    }
    
    $from_action_id = $DB->result( "
        SELECT `id` FROM auth_action as tb_action
        INNER JOIN `auth_group_action` as tb_group ON tb_group.`action_id`=tb_action.`id` AND tb_group.`group_id`='{$from_group_id}'
        WHERE tb_action.module_id='$module_id'
    ");
  
    if ($from_action_id) {
        if (!$action_id) {
            $action_id = $DB->insert("INSERT INTO auth_action (`module_id`,`title_ru`) VALUES ('$module_id', 'Привилегии ".$group['name']."')");
            $DB->insert("INSERT INTO `auth_group_action` (`action_id`, `group_id`) VALUES ('$action_id', '$group_id')");
        }
        //доступ к таблицам
        $table_update = $DB->fetch_column("select table_id from auth_action_table_update where action_id='$from_action_id'", 'table_id', 'table_id');
        $table_select = $DB->fetch_column("select table_id from auth_action_table_select where action_id='$from_action_id'", 'table_id', 'table_id');

        //к событиям
        $event = $DB->fetch_column("select event_id from auth_action_event where action_id='$from_action_id'", 'event_id', 'event_id');
        
        //к структуре
        $view = $DB->fetch_column("select structure_id, structure_id as id from auth_action_view where action_id='$from_action_id'");
        
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
    }
}

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

Action::setOk('Изменения успешно сохранены');