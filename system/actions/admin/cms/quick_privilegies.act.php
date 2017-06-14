<?php
/** 
 * Quick add privilegies
 * 
 * @package DeltaCMS
 * @subpackage Auth
 * @author Sergey Brezetskiy
 * @copyright c-format, 2014
 */
$module_id = globalVar( $_REQUEST["module_id"], 0 );
$group_id  = Auth::getUserGroup();

if ( !$module_id ) Action::onError("Не указан модуль");
if ( !$group_id ) Action::onError("Обновите страницу. Вы не авторизированы.");

$action_id = $DB->result( "
    SELECT `id` FROM auth_action
    INNER JOIN `auth_group_action` ON `auth_group_action`.`action_id`=`auth_action`.`id` AND `auth_group_action`.`group_id`='{$group_id}'
    WHERE auth_action.module_id='$module_id'
" );
if ( !$action_id ) $action_id = 0;

if (!$action_id){
    $group_name = $DB->result("SELECT uniq_name FROM auth_group WHERE `id`='$group_id'");
    $action_id = $DB->insert("INSERT INTO `auth_action` SET `module_id`='{$module_id}', `title_ru`='Привилегии {$group_name}'");
    $DB->insert("INSERT INTO `auth_group_action` SET `group_id`='$group_id',`action_id`='$action_id'");
}

$task = globalVar( $_REQUEST["task"], "" );
switch ($task) {
    case "page_permission":
        
        $checked = $DB->fetch_column("select structure_id, structure_id as id from auth_action_view where action_id='$action_id'");
        
        // Определяем перечень разделов в админ части
        $query = "
                select 
                        tb_structure.url,
                        tb_structure.id,
                        tb_structure.id as real_id,
                        tb_structure.structure_id as parent,
                        tb_view.action_id,
                        tb_structure.name_".LANGUAGE_CURRENT." as name,
                        group_concat(distinct if(tb_action.id='$action_id', concat('<font color=green>', tb_action.title_".LANGUAGE_CURRENT.", '</font>'), tb_action.title_".LANGUAGE_CURRENT.") order by tb_action.title_".LANGUAGE_CURRENT." separator ', ') as actions
                from cms_structure as tb_structure
                left join auth_action_view as tb_view on tb_view.structure_id=tb_structure.id
                left join auth_action as tb_action on tb_action.id=tb_view.action_id
                where tb_structure.module_id='$module_id'
                group by tb_structure.id
        ";
        $data = $DB->query($query, 'id');        
        reset($data);
        while(list($index,$row) = each($data)) {
            if ( isset($checked[ $row['id'] ]) ) continue;
            $DB->insert("insert ignore into auth_action_view (action_id,structure_id) values ('{$action_id}','{$row['id']}')");
        }
        $_RESULT["javascript"] = " $('.cms_privileg.page_permission').remove(); ";
        Action::setOk( "ok" );
        break;
    case "action_permission":
        // События к которым есть доступ
        $checked = $DB->fetch_column("select event_id from auth_action_event where action_id='$action_id'", 'event_id', 'event_id');

        // Определяем перечень событий
        $query = "
                select
                        tb_event.id,
                        concat(tb_event.description_".LANGUAGE_CURRENT.", ' [', tb_event.name, ']') as name,
                        group_concat(distinct if(tb_action.id='$action_id', concat('<font color=green>', tb_action.title_".LANGUAGE_CURRENT.", '</font>'), tb_action.title_".LANGUAGE_CURRENT.") order by tb_action.title_".LANGUAGE_CURRENT." separator ', ') as actions
                from cms_event as tb_event
                left join auth_action_event as tb_relation on tb_event.id=tb_relation.event_id
                left join auth_action as tb_action on tb_action.id=tb_relation.action_id
                where tb_event.module_id = '$module_id'
                group by tb_event.id
                order by tb_event.name
        ";
        $data = $DB->query($query);
        reset($data); 
        while (list(,$row) = each($data)) {
            if ( isset($checked[ $row['id'] ]) ) continue;
            $DB->insert("insert ignore into auth_action_event (action_id,event_id) values ('{$action_id}','{$row['id']}')");
        }
        $_RESULT["javascript"] = " $('.cms_privileg.action_permission').remove(); ";
        Action::setOk( "ok" );
        break;
    case "action_create_permission":
        $module_name = $DB->result("SELECT name FROM `cms_module` WHERE id='$module_id'");
        
        $actions = array();
        $path = ACTIONS_ROOT . "admin/" . strtolower($module_name);
        if ( is_dir($path) ){
            $action_dir = Filesystem::getAllSubdirsContent( $path, true, true );
            reset( $action_dir );
            while ( list($action_key,$action_value)=each($action_dir) ){

                $action_value  = delta_path( $action_value );
                $action_begin  = strpos($action_value, ACTIONS_ROOT . "admin/" . strtolower($module_name) . "/");
                $action_length = strlen( ACTIONS_ROOT . "admin/" . strtolower($module_name) . "/" );

                $action_value  = substr( $action_value, $action_begin + $action_length );
                $actions[]     = $action_value;
            }
        }
        
        while ( list(,$action_value)=each($actions) ){
            $action_value = substr( $action_value, 0, strpos($action_value,".") );
            $event_id = $DB->result("SELECT id FROM `cms_event` WHERE `name`='{$action_value}' AND `module_id`='{$module_id}' ");
            if ( !$event_id ){
                $desc = "";
                $content = file_get_contents( $path . "/" . $action_value . ".act.php" );
                preg_match('/(?:@description)\s+(.+)$/imsU', $content, $cron);
                $desc = globalVar( $cron[1] , "");

                $event_id = $DB->insert("
                    INSERT INTO `cms_event` SET
                    `module_id`='{$module_id}',
                    `name`='{$action_value}',
                    `description_ru`='{$desc}'
                ");
                $DB->insert("insert ignore into auth_action_event (action_id,event_id) values ('{$action_id}','{$event_id}')");
            }
        }
        $_RESULT["javascript"] = " $('.cms_privileg.action_create_permission').remove(); ";
        Action::setOk( "ok" );
        break;
    default :
        Action::onError("Не указано задание");
}
