<?php

/**
 * используется чтобы формировать транслитерацию
 * имени файла если установлеи модуль SEO   


if(!empty($this->NEW["headline_".LANGUAGE_CURRENT])) {
	$tmppath = preg_replace(array('/\s+/', '/\'/', '/\?/'), array('-', '', ''), $this->NEW["headline_".LANGUAGE_CURRENT]);
	$tmppath = Charset::translit($tmppath);
	$tmppath = preg_replace(array('/\s+/', '/[^a-zA-Z0-9-_]/'), array('-', ''), $tmppath);
	
	// Делаем проверку нету ли такого имени уже в базе 	 
	if(!empty($tmppath)) {
		if ($this->action_type == 'update') {
			$query = "select count(id) from news_message where path = '".$DB->escape($tmppath)."' and id <> '".$this->OLD['id']."'";
		} else {
			$query = "select count(id) from news_message where path = '".$DB->escape($tmppath)."'";
		}
		$result = $DB->result($query);
		if(!empty($result)) {
			$tmppath .= $this->NEW['id'];
		} 
		$DB->update("UPDATE news_message SET `path` = '".$DB->escape($tmppath)."' WHERE id = '".$this->NEW['id']."'");
	}
}
 */
?>