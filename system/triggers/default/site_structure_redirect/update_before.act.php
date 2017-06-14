<?php

$this->NEW['url_old'] = str_replace(array('http://', 'https://', 'www.'), '', $this->NEW['url_old']);
$this->NEW['url_new'] = str_replace(array('http://', 'https://', 'www.'), '', $this->NEW['url_new']);

$this->NEW['url_old'] = trim($this->NEW['url_old'], '/');
$this->NEW['url_new'] = trim($this->NEW['url_new'], '/');

$this->NEW['admin_id'] = Auth::getUserId();