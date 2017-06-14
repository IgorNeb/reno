<?php
/** 
 * Скрипт, который сохраняет параметры системы 
 * @package DlatCMS 
 * @subpackage CMS 
 * @author Rudenko Ilya
 * @copyright c-format, 2016
 */
$module_id = globalVar($_REQUEST['module_id'], 0);
$all_settings = globalVar($_REQUEST['all_settings'], 0);

$where = ($all_settings == 0) ? "and tb_settings.show_in_settings='1'" : "";

/**
 * Добавляем в БД значения
 */

$query = "
	select
		tb_settings.id,
		concat(upper(tb_module.name), '_', upper(tb_settings.name)) as name,
		tb_settings.value,
		tb_settings.type
	from cms_settings as tb_settings
	inner join cms_module as tb_module on tb_module.id=tb_settings.module_id
	where tb_settings.type <> 'devider' " 
            .  where_clause("tb_settings.module_id", $module_id) 
            .  $where 
	." order by tb_module.name asc, tb_settings.name asc
";

$data = $DB->query($query);
reset($data);
while (list(,$row) = each($data)) {
	if ($row['type'] == 'bool' && !isset($_POST[ $row['id'] ])) {
		// Для checkbox, которые не выделены ставим значение = 0
		$_POST[ $row['id'] ] = 0;
	} elseif ($row['type'] == 'file') {
		if (isset($_FILES[$row['id']]) && !empty($_FILES[$row['id']]['name'])) {
			// Закачиваем файл с картинкой
			$file = UPLOADS_ROOT.'cms_settings/'.strtolower($row['name']).'/'.strtolower($_FILES[$row['id']]['name']);
			Uploads::moveUploadedFile($_FILES[$row['id']]['tmp_name'], $file);
			$_POST[$row['id']] = substr(Uploads::getURL($file), 1);
		} elseif (isset($_POST[$row['id'].'_del']) && $_POST[$row['id'].'_del'] = 1 && is_file(SITE_ROOT.substr($row['value'], 1))) {
			// Удаляем файл
			unlink(SITE_ROOT.substr($row['value'], 1));
			$_POST[$row['id']] = '';
		} elseif (!is_file(SITE_ROOT.$row['value'])) {
			// Файл был удалён по FTP
			$_POST[$row['id']] = '';
		} else {
			// Не производим никаких изменений
			continue;
		}
	} elseif ($row['type'] == 'int') {
		$_POST[$row['id']] = intval($_POST[$row['id']]);
        
	} elseif ($row['type'] == 'time' && isset($_POST[$row['id'].'_unit'])) {
		$_POST[$row['id']] *= intval($_POST[ $row['id'].'_unit' ]);
		
	} elseif ($row['type'] == 'float') {
        $_POST[$row['id']] = floatval(str_replace(',', '.', $_POST[$row['id']]));        
		$_POST[$row['id']] = str_replace(',', '.', round($_POST[$row['id']], 2));  
        
    } elseif ($row['type'] == 'text') {     
		$_POST[$row['id']] = Misc::word_wrapper($_POST[$row['id']], 150, '');       
	    
	} elseif ($row['type'] == 'bigtext') {     
		$_POST[$row['id']] = Misc::word_wrapper($_POST[$row['id']], 350, '');       
	}
	
	if ($row['value'] != $_POST[$row['id']]) {
		$query = "update cms_settings set value='".$_POST[$row['id']]."' where id='$row[id]'";
		$DB->update($query);
	}
}

/**
 * Изменение уникального индекса в таблице авторизации когда разрешены или 
 * запрещены параллельные сессии
 */
$auth_concurrent_session = $DB->result("select value from cms_settings where name='auth_concurrent_session'");

$DB->query("show tables like 'tmp_auth_online'");
if ($DB->rows > 0) {
	$DB->delete("drop table if exists tmp_auth_online");
}

$DB->query("create table tmp_auth_online like auth_online");
$DB->update("alter table tmp_auth_online drop key `user_id`");

if (empty($auth_concurrent_session)) {
	$DB->update("alter table tmp_auth_online add unique key user_id (`user_id`, `auth_group_id`)");
} else {
	$DB->update("alter table tmp_auth_online add key user_id (`user_id`, `ip`, `local_ip`, `auth_group_id`)");
}
	
$DB->query("insert ignore into tmp_auth_online select * from auth_online");
$DB->delete("drop table auth_online");
$DB->update("alter table tmp_auth_online rename auth_online");

// Формируем файл с конфигурацией
Install::updateMyConfig();

?>