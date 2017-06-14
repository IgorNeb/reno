<?php
/*
 * Получаем данные: логин и соц сеть
 */

$user_social_id = globalVar($_REQUEST['user_social_id'], 0);
$user_social    = globalVar($_REQUEST['user_social'], '');
$name           = globalVar($_REQUEST['name'], '');
$last_name      = globalVar($_REQUEST['last_name'], '');
$email      = globalVar($_REQUEST['email'], '');
$photo      = globalVar($_REQUEST['photo'], '');

/*
 * Если хоть что-то пустое  - ничего не делаем
 * Если какая-то левая сеть - также
 */
if ( (!$user_social_id) || !(($user_social=='google') || ($user_social=='vk') || ($user_social=='fb')) ) exit();

/*
 * Дописуем первую букву что бы не было логинов
 * Ид может совпасть в вк и фб
 */
$card = $user_social . $user_social_id . '@' . $user_social . '.com';
$login_card = $user_social . $user_social_id;


/*
 * Так как мы пароль не знаем, то генерируем случайный
 */
$password = md5(uniqid(rand(),1));

/*
 * Определяем группу
 * Берем ид по названию соц сети(должны быть созданы - иначе создаем)
 */
$user_group = 0;
/*$query = "
    SELECT id
    FROM auth_group
    WHERE `uniq_name` = '{$user_social}'
";
$data_group = $DB->query_row( $query );
if (!count($data_group))
{
    $query = "
        INSERT INTO auth_group
        (`uniq_name`, `name`) VALUES ('{$user_social}', '{$user_social}')
    ";
    $user_group = $DB->insert( $query );
}
else
{
    $user_group = $data_group['id'];
}*/



/*
 * Остальные переменные нужные для регистрации
 */
$site_id               = '824';
$register_ip           = constant('HTTP_IP');
$register_local_ip     = constant('HTTP_LOCAL_IP');

$cookie_referer        = substr(globalVar($_COOKIE['referer'], ''), 0, 255);
$cookie_refered_page   = substr(globalVar($_COOKIE['refered_page'], ''), 0, 255);
$cookie_referral_hit   = substr(globalVar($_COOKIE['partner_hit'], ''), 0, 32);
$cookie_referral_id    = globalVar($_COOKIE['partner'], 0);
                
$user_group = trim(globalVar($user_group, 0));


$query = "
         SELECT *
         FROM auth_user
         WHERE `login`='{$card}' or `email`= '{$user_social_id}'
		";
$data_auth = $DB->query_row($query);
if (!count($data_auth))
{
    if (empty($email)) {
        $email = $card;
    } else {
        $query = "
                SELECT *
                FROM auth_user
                WHERE `email`= '{$email}'
               ";
        $data_exists = $DB->query_row($query);
        if (!empty($data_exists)) {
            $email = $card;
        }
    }
    
    $query = "
             INSERT INTO auth_user 
             SET login              = '$card',
             group_id               = '$user_group', 
             email                  = '$email',
             passwd                 = '".md5($password)."',
             name                   = '{$name}',
             site_id                = '$site_id',
             registration_dtime     = NOW(),  
             register_ip            = '$register_ip',
             register_local_ip      = '$register_local_ip',
             referer                = '".$DB->escape($cookie_referer)."',
             refered_page           = '".$DB->escape($cookie_refered_page)."'
    ";
    $user_id = $DB->insert($query); 
	//Сохраняем дополнительные данные пользователя


	$params['firstname'] = $name;
	$params['lastname']  = $last_name;
	//$params['city']      = $city;

    if ($user_social == 'vk') {
        $url = "https://api.vk.com/method/users.get?user_ids=".$user_social_id."&fields=photo_max&name_case=Nom&version=5.64";    
        $response = json_decode(file_get_contents($url)); 
     
        if (!empty($response)) {
            foreach ($response as $resp) {
                if (isset($resp[0]->photo_max)) {
                    $photo = $resp[0]->photo_max;                      
                }
            }
        }
    }
    
	$query = "insert into auth_user_data (`user_id`,`name`, `lastname`, `photo`)
		values ('$user_id', '".$name."', '".$last_name."', '".$photo."')";
	$DB->insert($query);
	

} else {
	$user_id = $data_auth['id'];
}

Auth::login($user_id);

		
$_RESULT['javascript'] = 'location.reload();';


exit();

?>
