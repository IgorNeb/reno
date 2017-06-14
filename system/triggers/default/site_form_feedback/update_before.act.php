<?php

/**
 * Обновление админа и отправка ответа пользователю
 */

if ($this->NEW['form_id'] == 37) { 
    $this->NEW['status'] = $this->OLD['status'];
}

if ($this->OLD['status'] != $this->NEW['status']) {
    $this->NEW['last_admin'] = Auth::getUserId();
	$admin = Auth::getInfo();
	
}