<?php
/** 
 * Сохранение параметров пользователя  
 * @subpackage User
 * @author Naumenko A.
 * @copyright C-format, 2014
 */ 
$user_email            = strtolower(trim(globalVar($_REQUEST['user_email'], '')));
$user_password         = trim(globalVar($_REQUEST['user_password'], ''));
$user_password_confirm = trim(globalVar($_REQUEST['user_password_confirm'], ''));
$params                = globalVar($_REQUEST['form'], array() );

$user_name             = globalVar($_REQUEST['user_name'], '');
$user_fename           = globalVar($_REQUEST['user_fename'], '');

$auto_login            = globalEnum($_REQUEST['auto_login'], array('true', 'false'));
$register_ip           = constant('HTTP_IP');
$register_local_ip     = constant('HTTP_LOCAL_IP');

$cookie_referer        = substr(globalVar($_COOKIE['referer'], ''), 0, 255);
$cookie_refered_page   = substr(globalVar($_COOKIE['refered_page'], ''), 0, 255);

/**
 * Проверяем правильность переданных данных
 */
$_RESULT['javascript'] = "$('#register input.error').removeClass('error');";

$error = $fields = array();

// пустой имя
if (empty($user_name)) {
	$error[] = cmsMessage::get("MSG_USER_ERROR_NAME");
    $fields[] = '.username';
}
// Не указан e-mail адрес
if (empty($user_email)) {
    $fields[] = '.useremail';
	$error[] = cmsMessage::get("MSG_USER_ERROR_EMAIL");
} 
//не верный email
if (!preg_match(VALID_EMAIL, $user_email)) {
	$fields[] = '.useremail';
	$error[] = cmsMessage::get("MSG_USER_LOGIN_ERROR_EMAIL");
}  
//не верный пароль
//if (!preg_match(VALID_PASSWD, $user_password)) { 
//    $fields[] = '.userpassword';
//	$error[] = cmsMessage::get("MSG_USER_ERROR_PASSWD");    
//} 
// Введенные пароли - не совпадают
//if ($user_password != $user_password_confirm) {	
//    $fields[] = '.userpassword';
//	$error[] = cmsMessage::get("MSG_USER_ERROR_PASSWD_NOCONFIRM");   
//}
// Проверяем CAPTCHA
//if (!Captcha::check(globalVar($_REQUEST['captcha_uid'], ''), globalVar($_REQUEST['captcha_value'], ''))) 
//{
//    $error[] = cmsMessage::get("MSG_FORM_CAPTHCA_ERROR"); 
//    $fields[] = '.captha_input';
//}

if (!empty($error)) {
    $_RESULT['register_error'] = implode('<br/>', $error);
    $_RESULT['javascript'] .= "$('#register ".implode(', #register ', $fields)."').parent().addClass('error');";
    $_RESULT['javascript'] .= "$('#register .btn-form').show();";
    //$_RESULT['javascript'] .= "$('html, body').animate({ scrollTop: $('.registration__title').offset().top }, 'slow');";
    exit;
}

/**
 * Проверяем, нет ли такого пользователя
 */
$query = "SELECT id FROM auth_user WHERE email='$user_email' OR login='$user_email'";
$DB->query($query);
if ($DB->rows > 0) {
	// Пользователь с таким электронным адресом уже зарегистрирован, воспользуйтесь формой напоминания пароля.
	$_RESULT['register_error'] = cmsMessage::get('MSG_USER_ERROR_NAME_EXISTS');
    $_RESULT['javascript'] .= "$('#register .btn-form').show();";
    exit;
}

/**
 * Определяем сайт, на котором регистрируется пользователь
 */
$query = "
	select site_id from site_structure_site_alias
	where url = '".globalVar($_SERVER['HTTP_HOST'], '')."'
";
$site_id = $DB->result($query, 0);
$confirm_code = strtolower(Misc::randomKey(32));

$user_password = gen_password(8);

/**
 * Сохраняем основные данные о пользователе
 */
$query = "
	INSERT IGNORE INTO auth_user 
	SET login               = '$user_email',		 
		email               = '$user_email',
		passwd          	= '".md5($user_password)."',  
		confirmation_code 	= '$confirm_code',
		confirmed       	= '1',
		site_id             = '$site_id',
		registration_dtime  = NOW(),  
		register_ip 		= '$register_ip',
		register_local_ip 	= '$register_local_ip',
		referer             = '".$DB->escape($cookie_referer)."',
		refered_page        = '".$DB->escape($cookie_refered_page)."',
		name                = '$user_name'
";
$user_id = $DB->insert($query);

$params = array('name'=> $user_name, 'lastname' => $user_fename);

/**
 * Перечень параметров
 */
$insert = array();
reset($params);
while(list($key, $value) = each($params)){
    $insert[] = " `$key` = '$value' ";
}
if (count($insert) > 0) {
    $user_data_id = $DB->insert("insert into auth_user_data SET user_id='{$user_id}', ".implode(", ", $insert));
}  
  
/**
 * Высылаем администратору письмо, с указанием того, что пользователь зарегистрировался
 */
if (AUTH_ADMIN_NOTIFY_EMAIL !='') {        
    $Template = new TemplateDB('cms_mail_template', 'User', 'registration');        
	$Template->set('user_id', $user_id);
	
	// Новые значения
	$query = "SELECT login, name, email FROM auth_user WHERE id='".$user_id."'";
	$data_user = $DB->query_row($query);
    $Template->set($data_user);
    
	$Sendmail = new Sendmail(CMS_MAIL_ID, $Template->title . ' #' . $user_id, $Template->display());
	$Sendmail->send(AUTH_ADMIN_NOTIFY_EMAIL, false);
}

/**
 * Высылаем пользователю письмо, с активацией аккаунта
 */
if (AUTH_CONFIRM_ACCOUNT) {
    
   $id = $user_id;
   require_once(ACTIONS_ROOT.'site/user/send_code.act.php');
   $_RESULT['register'] = "<h3>Вам відправлено лист із підтвердженням реєстрації</h3>";
   exit();
   
} elseif (AUTH_LOGIN_ON_REGISTER) {
    //Автоматический логин
    $logged_in = Auth::login($user_id, false, null);
    if (!$logged_in) {
        Auth::logLogin(0, time(), $user_email);
        $_RESULT['register-error'] =  'Доступ с IP заблокирован или Ваш аккаунт отключен администратором';
    }
}

//возращаем
$return_path = globalVar($_REQUEST['_return_path'], '');
if (!empty($return_path)) {
    $_RESULT['javascript'] =  "document.location.href='{$return_path}'";
} else {
    $_RESULT['register'] = "<h3>Вы успешно зарегистрированы на сайте</h3>";
}
exit;

?> 