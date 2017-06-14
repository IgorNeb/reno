<?php

// Удаляем файлы
$Structure = new Structure($this->table['name']);
$Structure->delete($this->OLD['url']);

// Удаляем информацию о сайте
if (empty($this->OLD['structure_id'])) {
	Structure::deleteSite($this->OLD['id']);
}


/**
 * Добавляем запись об удалении страницы
 
$query = "
	INSERT INTO site_structure_redirect 
	SET structure_id = '{$this->OLD['id']}', 
		url_old   = '{$this->OLD['url']}', 
		url_new   = '/', 
		admin_id  = '".Auth::getUserId()."',
		operation = 'delete',
		dtime     = '".date("Y-m-d H:i:s")."'
";
$DB->insert($query); 
*/


?>