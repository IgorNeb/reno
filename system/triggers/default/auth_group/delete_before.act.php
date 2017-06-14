<?php

/* 
 * Удалений данных из таблиц
 */

$action_ids = $DB->fetch_column( "
        SELECT `id` FROM auth_action as tb_action
        INNER JOIN `auth_group_action` as tb_group ON tb_group.`action_id`=tb_action.`id` 
        WHERE tb_group.`group_id`='{$this->OLD['id']}'
    ");
        
if (count($action_ids) > 0) {     
    $where = " where action_id in (".implode(', ', $action_ids).")";
    //clear rules
    $query = "delete from `auth_action_table_select` " . $where;
    $DB->delete($query);

    $query = "delete from `auth_action_table_update` " . $where;
    $DB->delete($query);

    $query = "delete from `auth_action_view` " . $where;
    $DB->delete($query);

    $query = "delete from `auth_action_event` " . $where;
    $DB->delete($query);
    
    $query = "delete from `auth_group_action` " . $where;
    $DB->delete($query);
}