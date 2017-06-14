<?php

/* 
 * Добавление страниц в сео структуру в ручном режиме
 */
$url = str_replace(array('http://', 'https://', '.html', 'www.'), '', $this->NEW['url']);

if (!preg_match("/^".CMS_HOST."/", $url)) {
    Action::onError( 'Не правильно добавлена ссылка ');
}

$matches = parse_url($url);
if (!isset($matches['path'])) { 
    Action::onError( 'Не правильно добавлена ссылка ');     
}

$names = explode('/', trim($url, '/'));
if ($names[0] !== CMS_HOST || count($names) == 1) {
    Action::onError('Не правильно добавлена ссылка'); 
}
$last_item = array_pop($names);

$link = (!empty($names)) ? implode('/', $names) : '';
$seoid = $DB->result("select id from seo_structure where url = '" . $link . "' ");

if (empty($seoid)) {
    Action::onError($link . 'Не возможно определить радительский раздел');
}

$this->NEW['url']      = '';
$this->NEW['group_id'] = $seoid;
$this->NEW['uniq_name'] = $last_item;
$this->NEW['is_hands']  = 1;
