<?php

/*
 * Установка модуля
 * @author Sergey Brezetskiy
 * @year 2014
 */

$uid = 'install_'.uniqid();

$attach = array();
if (isset($_FILES) && !empty($_FILES)) {
    reset($_FILES);
    while (list($title, $row) = each($_FILES)) {
	if ($row['error'] != 0) {
            // файл закачан с ошибкой, игнорируем его
            continue;
	}
	$extension = Uploads::getFileExtension($row['name']);
        if ( $extension != "zip" ) {
            Filesystem::delete(TMP_ROOT.$uid.'/');
            Action::onError ("Должен быть один zip-файл");
        }
	Uploads::moveUploadedFile($row['tmp_name'], TMP_ROOT.$uid.'/'.$title.'.'.$extension);
	$attach[] = TMP_ROOT.$uid.'/'.$title.'.'.$extension;
    }
}

if ( count($attach) != 1 ) {
    Filesystem::delete(TMP_ROOT.$uid.'/');
    Action::onError ("Должен быть один zip-файл");
}

$work_dir = TMP_ROOT.$uid . "/files/";

#unzip file
$zip_path = $attach[0];
$zip = new ZipArchive;
$res = $zip->open( $zip_path );
if ($res === TRUE) {
    $zip->extractTo( $work_dir );
    $zip->close();
} else {
    Filesystem::delete(TMP_ROOT.$uid.'/');
    Action::onError('Не могу разархивировать файл');
};


if ( !is_file($work_dir . "config.xml") ){
    Filesystem::delete(TMP_ROOT.$uid.'/');
    Action::onError('Нету файла конфигураций');    
}

$xml = simplexml_load_file( $work_dir . "config.xml" );
$xml = json_decode(json_encode((array)$xml), TRUE);

$module_name = globalVar( $xml["name"], "" );
$module_desc = globalVar( $xml["description"], "" );

if ( empty($module_name) ) {
    Filesystem::delete(TMP_ROOT.$uid.'/');
    Action::onError('Имя модуля обазательно для заполнения');
}

if ( empty($module_desc) ) {
    Filesystem::delete(TMP_ROOT.$uid.'/');
    Action::onError('Описание модуля обазательно для заполнения');
}


$module_id = $DB->result("select id from cms_module where name='$module_name'");
if ( !$module_id ) $module_id = $DB->insert("INSERT INTO cms_module SET `name`='$module_name', `description_ru`='$module_desc' ");


//move class(es)
$path = globalVar( $xml["path_classes"], "");
if ( $path ){
    $path = $work_dir . $path;
    if ( is_dir($path) ){
        Filesystem::rename($path, LIBS_ROOT, true);
    }
}

//move system site actions
$path = globalVar( $xml["path_actions_site"], "");
if ( $path ){
    $path = $work_dir . $path;
    if ( is_dir($path) ){
        Filesystem::rename($path, ACTIONS_ROOT . "site/", false);
    }
}

//move system admin actions
$path = globalVar( $xml["path_actions_admin"], "");
Filesystem::delete( ACTIONS_ROOT . "admin/" . strtolower($module_name) . "/" );
if ( $path ){
    $path = $work_dir . $path;
    if ( is_dir($path) ){
        Filesystem::rename($path, ACTIONS_ROOT . "admin/" . strtolower($module_name) . "/", true);
    }
}

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

$privile_events = array();
$install_events = array();

reset( $actions );
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
        $privile_events[] = $event_id;
    }
    
    $install_events[] = $event_id;
}

$del_events = array();
if ( !count($install_events) ){
    $del_events = $DB->fetch_column("SELECT id FROM `cms_event` WHERE `module_id`='{$module_id}'");
}
else {
    $del_events = $DB->fetch_column("SELECT id FROM `cms_event` WHERE `module_id`='{$module_id}' AND `id` NOT IN ('" . implode("','",$install_events) . "') ");
}

#delete from auth_action_event
if ( count($del_events) ) {
    $DB->delete("DELETE FROM `auth_action_event` WHERE `event_id` IN ('" . implode("','",$del_events) . "') ");
    $DB->delete("DELETE FROM `cms_event` WHERE `id` IN ('" . implode("','",$del_events) . "') ");
}

#set priveligies to admin
$privilege_admin_id = $DB->result("SELECT id FROM auth_group WHERE `uniq_name`='AdminFull'");
$privilege_id       = 0;
if ( $privilege_admin_id ){
    $privilege_id = $DB->result( "
       SELECT `id` FROM auth_action
       INNER JOIN `auth_group_action` ON `auth_group_action`.`action_id`=`auth_action`.`id` AND `auth_group_action`.`group_id`='{$privilege_admin_id}'
       WHERE auth_action.module_id='$module_id'
    " );
    if (!$privilege_id){
        $privilege_id = $DB->insert("INSERT INTO `auth_action` SET `module_id`='{$module_id}', `title_ru`='Привилегии AdminFull'");
        $DB->insert("INSERT INTO `auth_group_action` SET `group_id`='$privilege_admin_id',`action_id`='$privilege_id'");
    }
}
if ( count($privile_events) && $privilege_id ){
    reset( $privile_events );
    while ( list(,$privile_event)=each($privile_events) ){
        $DB->insert("INSERT INTO `auth_action_event` SET `action_id`='{$privilege_id}', `event_id`='{$privile_event}'");
    }
}


//move cms structure files
$path = globalVar( $xml["path_cms_structure"], "");
if ( $path ){
    $path = $work_dir . $path;
    if ( is_dir($path) ){
        Filesystem::rename($path, CMS_STRUCTURE, true);
    }
}

$cms_structure_new = array();
$cms_structure_was = array();

$cms_structure = globalVar( $xml["cms_structure_tree"]["cms_structure"], array() );
reset( $cms_structure );
while ( list(,$struct_elem)=each($cms_structure) ){
    $uniq_name = globalVar( $struct_elem["uniq_name"], "" );
    if ( empty($uniq_name) ){
        Filesystem::delete(TMP_ROOT.$uid.'/');
        Action::onError("Неправильная структура файла");
    }
    
    $url = globalVar( $struct_elem["url"], "" );
    if ( empty($url) ){
        Filesystem::delete(TMP_ROOT.$uid.'/');
        Action::onError("Неправильная структура файла");
    }    
    
    //если создан - пропускаем
    $insert_struct = $DB->result("SELECT id FROM `cms_structure` WHERE `url`='$url'");
    if ( $DB->rows ) {
        $cms_structure_was[] = $insert_struct;
        continue;
    }
    
    $parent = 0;
    
    //узнаем ид родителя , если не первый елемент
    if ( strpos($url,"/") !== FALSE ){
        $parent_url = substr( $url, 0, strrpos( $url, "/" ) );
        $parent = $DB->result("SELECT id FROM `cms_structure` WHERE `url`='$parent_url'");
        if ( !$parent ){
            Filesystem::delete(TMP_ROOT.$uid.'/');
            Action::onError("Неправильная структура файла");            
        }
    }
    
    $priority = $DB->result(" SELECT priority FROM `cms_structure` WHERE `structure_id`='$parent' ORDER BY `priority` DESC LIMIT 1 ");
    $priority++;
    
    $insert_struct = $DB->insert("
        INSERT INTO `cms_structure` SET
        `uniq_name`    = '$uniq_name',
        `url`          = '$url',
        `module_id`    = '$module_id',
        `structure_id` = '$parent',
        `no_link`      = '" . globalVar( $struct_elem["no_link"], false ) . "',
        `name_ru`      = '" . globalVar( $struct_elem["name_ru"], false ) . "',
        `title_ru`     = '" . globalVar( $struct_elem["title_ru"], false ) . "',
        `show_menu`    = '" . globalVar( $struct_elem["show_menu"], "" ) . "',
        `priority`     = '$priority',
        `active`       = '" . globalVar( $struct_elem["active"], false ) . "'
    ");
    
    $cms_structure_new[] = $insert_struct;
}

$cms_structure_all = array_merge( $cms_structure_new, $cms_structure_was );
if ( !count($cms_structure_all) ) $cms_structure_all[] = -1;
$cms_structure_del = $DB->fetch_column("SELECT id FROM `cms_structure` WHERE `module_id`='$module_id' AND id NOT IN ('" . implode("','",$cms_structure_all) . "')");


if ( count($cms_structure_del) ){
    $del_arr = $DB->query("SELECT * FROM `cms_structure` WHERE id IN ('" . implode("','",$cms_structure_del) . "')");
    reset( $del_arr );
    while ( list(,$del_row)=each( $del_arr ) ){
        if (is_file( CMS_STRUCTURE . $del_row["url"] . ".ru.php" ) )
            Filesystem::delete ( CMS_STRUCTURE . $del_row["url"] . ".ru.php" );
        if (is_file( CMS_STRUCTURE . $del_row["url"] . ".ru.tmpl" ) )
            Filesystem::delete ( CMS_STRUCTURE . $del_row["url"] . ".ru.tmpl" );        
    }
    $DB->delete("DELETE FROM `cms_structure` WHERE id IN ('" . implode("','",$cms_structure_del) . "')");
}

Filesystem::deleteEmptyDirs( CMS_STRUCTURE );

#set privilegies to admin
if ( count($cms_structure_new) && $privilege_id ){
    reset( $cms_structure_new );
    while ( list(,$privile_event)=each($cms_structure_new) ){
        $DB->insert("INSERT INTO `auth_action_view` SET `action_id`='{$privilege_id}', `structure_id`='{$privile_event}'");
    }
}


//move triggers files
$triggers_root = Filesystem::getDirContent( TRIGGERS_ROOT, false, true, false);
reset( $triggers_root );
while ( list(,$trigger_root)=each($triggers_root) ){
    $trigger_root = delta_path( $trigger_root );
    $dirs = Filesystem::getDirContent( TRIGGERS_ROOT . $trigger_root, false, true, false);
    reset( $dirs );
    while ( list(,$dir)=each($dirs) ){
        $dir = delta_path( $dir );
        if ( (strpos( $dir, strtolower($module_name)) !== FALSE) && ( !strpos($dir, strtolower($module_name)) ) ){
            Filesystem::delDir( TRIGGERS_ROOT . $trigger_root . $dir );
        }
    }
}

$path = globalVar( $xml["path_triggers"], "");
if ( $path ){
    $path = $work_dir . $path;
    if ( is_dir($path) ){
        Filesystem::rename($path, TRIGGERS_ROOT, true);
    }
}


//sql
$module = strtolower($module_name);

$install_prefix = "install_";
$dbname = globalVar( $DB->db_name, "" );
if ( empty($dbname) ) {
    Filesystem::delete(TMP_ROOT.$uid.'/');
    Action::onError("Не указана База Данных");
}

//triggers
$data_triggers = $DB->query("show triggers LIKE '$module%'");
reset( $data_triggers );
while ( list(,$trigger_row)=each($data_triggers) ){
    $DB->delete("DROP TRIGGER IF EXISTS {$trigger_row["trigger"]}");
}

//views
$data_views = $DB->fetch_column("SELECT TABLE_NAME FROM information_schema.`TABLES` WHERE TABLE_TYPE LIKE 'VIEW' AND TABLE_SCHEMA LIKE '$dbname' AND TABLE_NAME LIKE '$module%'");
reset( $data_views );
while ( list(,$view_name)=each($data_views) ){
    $DB->delete("DROP VIEW IF EXISTS {$view_name}");
}

if ( is_file($work_dir . "sql.sql") ){
    try {
        $command="mysql -h '" . DB_DEFAULT_HOST . "' -u '" . DB_DEFAULT_LOGIN . "' -p'" . DB_DEFAULT_PASSWORD . "' '" . DB_DEFAULT_NAME . "' < '" . $work_dir . "sql.sql" . "'";
        $output = shell_exec($command);
    } catch (Exception $ex) {
        Filesystem::delete(TMP_ROOT.$uid.'/');
        Action::onError("Не могу выполнить sql");
    }
}

$tables_old = array();
$tables_new = array();

$sql = "SHOW TABLES FROM $dbname LIKE '{$install_prefix}{$module}%'";
$tables = $DB->fetch_column( $sql );
reset( $tables );
while ( list($key,$value)=each($tables) ){
    $fields = $DB->query("SHOW FIELDS FROM $value","field");
    $value = substr($value, strlen($install_prefix));   
    $tables_new[$value] = $fields;
}

$sql = "SHOW TABLES FROM $dbname LIKE '{$module}%'";
$tables = $DB->fetch_column( $sql );
reset( $tables );
while ( list($key,$value)=each($tables) ){    
    $fields = $DB->query("SHOW FIELDS FROM $value","field");    
    $tables_old[$value] = $fields;
}

reset($tables_old);
while ( list($table_key,$table_row)=each($tables_old) ) //browse thru master tables 
{    
    if ( isset($tables_new[$table_key]) ){
        x_debug( $table_key );
    }
}

Filesystem::delete(TMP_ROOT.$uid.'/');
Action::setOk( "install modules" );

exit;

?>