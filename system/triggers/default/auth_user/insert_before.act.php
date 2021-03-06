<?php

/**
 * Провер¤ем правильность заполнения IP-адресов,
 * с которых ограничен доступ администраторам из этой группы
 * Допустимые форматы:
 *  - диапазон 	   : 193.178.34.1 - 193.178.34.55
 *  - CIDR 		   : 193.178.34.0/24
 *  - одиночный IP : 193.178.34.2
 * Записи должны быть разделены переводами строки, запятыми или точкой
 * с запятой.
 */
 
$allow_ip = preg_split("~[,;\n\r]+~", $this->NEW['allow_ip'], -1, PREG_SPLIT_NO_EMPTY);

reset($allow_ip); 
while (list($index, $row) = each($allow_ip)) { 
	$row = $allow_ip[$index] = preg_replace("~[\s\t]+~", "", $row);
	
	if (
		/* просто IP */ !preg_match("~^([0-9]{1,3}\.){3}[0-9]{1,3}$~", $row) &&
		/* CIDR */ 		!preg_match("~^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$~", $row) &&
		/* диапазон */	!preg_match("~^([0-9]{1,3}\.){3}[0-9]{1,3}\-([0-9]{1,3}\.){3}[0-9]{1,3}$~", $row)
	)  {
		Action::onError("Ошибка в списке IP адресов, с которых разрешен доступ ('$row')");
	}
}

$this->NEW['allow_ip'] = implode("\n", $allow_ip);

$this->NEW['login'] = $this->NEW['email'];

$group_id = Auth::getUserGroup();
if ($group_id != 5 && $this->NEW['group_id'] == 5) {
    $this->NEW['group_id'] = 31;
}
