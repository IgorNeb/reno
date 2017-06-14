<?php

$this->NEW['uniq_name'] = strtoupper( name2url($this->NEW['uniq_name'], 250) );
$this->NEW['uniq_name'] = str_replace("-", "_", $this->NEW['uniq_name']);
$matches = explode("_", $this->NEW['uniq_name']);
if( count($matches) < 3 || 'msg' != strtolower($matches[0])){
    Action::onError( 'Не правильно заполнено поле Параметр. Пример MSG_MODULENAME_TITLE' );
}

$this->NEW['module'] = strtolower($matches[1]);

