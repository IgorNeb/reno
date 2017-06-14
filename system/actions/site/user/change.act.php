<?php

/** 
 * Сохранение параметров пользователя  
 * @subpackage User
 * @author Naumenko A.
 * @copyright C-format, 2016
 */ 

$user_email = strtolower(trim(globalVar($_REQUEST['email'], '')));
$params     = globalVar($_REQUEST['form'], array() );
$user_id    = Auth::getUserId();

if (empty($user_email) || !preg_match(VALID_EMAIL, $user_email) ) {
	Action::onError(cmsMessage::get('MSG_USER_LOGIN_ERROR_EMAIL'));       
} 

if (isset($params['name']) && empty($params['name'])) {
    Action::onError(cmsMessage::get('MSG_USER_ERROR_NAME'));  
}

// Вытягиваем данные про пользователя
$user = $DB->query_row("
	SELECT IF(TRIM(name) != '', name, login) as name, passwd, login, email 
	FROM auth_user WHERE id = '$user_id'
");


if( $user['email'] != $user_email){
    /**
    * Проверяем наличие пользователей с указанным e-mail адресом
    */
   $DB->query("SELECT id FROM auth_user WHERE (email='$user_email' OR login='$user_email') AND id != '$user_id'");
    if ($DB->rows > 0) {
         Action::onError(cmsMessage::get('MSG_USER_ERROR_NAME_EXISTS'));  
    } else{
        $DB->update("update auth_user SET `email`='$user_email' WHERE id='{$user_id}'");
    }
}

/**
 * Перечень параметров
 */
$insert = array();
reset($params);
while(list($key, $value) = each($params)){
    $insert[] = " `$key` = '$value' ";
}
if(count($insert) > 0){
    $DB->query("INSERT INTO auth_user_data SET user_id='{$user_id}', ".implode(", ", $insert). ""
        . " ON DUPLICATE KEY UPDATE " . implode(", ", $insert) );
}

$name = (isset($params['lastname']) && !empty($params['lastname']) ? $params['lastname'] . ' ' :  '') . $params['name'];
$DB->update("update auth_user SET name='{$name}' WHERE id='{$user_id}'");

/**
 * Автоматический логин для изменения данных
 */      
Auth::login($user_id, false, null);


/**
 * Высылаем администратору письмо, с указанием того, что пользователь изменил данные о себе

if (CMS_NOTIFY_EMAIL!='') {
	$mailto = CMS_NOTIFY_EMAIL;
	require_once(ACTIONS_ROOT.'site/user/notification.inc.php');
}

 */
Action::setSuccess("Поздравляем, ваши данные успешно изменены.");

