<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

if ($this->NEW['type_id'] == 0) {
    $this->NEW['type_id'] = 1;
}

if ($this->NEW['type_id'] == 205 && (empty($this->NEW['date_event']) || empty($this->NEW['date_to']))) {
    if (empty($this->NEW['date_event'])) {
        $_SESSION['cmsEditError'][21121] = 'Поле "Дата проведения" обязательное для заполнения.';        
    }
    if (empty($this->NEW['date_to'])) {
        $_SESSION['cmsEditError'][4283] = 'Поле "Дата окончания" обязательное для заполнения.';        
    }
    Action::onError();
}