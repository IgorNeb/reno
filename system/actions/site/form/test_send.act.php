<?php


$form_name = globalVar($_REQUEST['form_name'], ''); //название формы form_test как в админке
$current_path = globalVar($_REQUEST['current_path'], ''); // /comanda/?
$form_data = globalVar($_REQUEST['form'], array()); // данный полученый из формы.
//$admin_id = globalVar($_REQUEST['form']['worker_id'], 0); // 0 - int
//x($_REQUEST['form']['worker_id']);
//x($form_name);


//if (empty($admin_id)) {
//    exit;
//}


$email = 'kharinserhiy@gmail.com';
//$email = $DB->result("SELECT email FROM site_department_worker WHERE id={$admin_id}");
////x($data);
if (empty($email)) {
    exit();
}

$Form = new FormLight($form_name); // имя формы $form_name - form_test

//обработка данных формы
$data = $Form->checkFields($form_data);
//x($data);

//foreach ($data as $key => $row) {
//   x($row);
//}

if (!$data) {   // если есть ошибки - этот блок
    //ошибка заполнения
    $_RESULT['javascript'] = "";
    foreach ($Form->getError() as $key => $error) {
        $_RESULT[ $form_name.'_error'] = $error;
        $_RESULT['javascript'] .= "$('#form_".$form_name." .form_section.".$key."').addClass('error');";
    }
    $_RESULT['javascript'] .= "$('.btn-form, #".$form_name."_error').show();";
    $_RESULT['javascript'] .= "scrollToElem('#form_".$form_name."');";
    exit;
}

//ошибок ввода нету? - этот блок
$Template = new TemplateDB('cms_mail_template', 'user', 'question');
$Template->set('current_path', $current_path);

////передача в письмо данных
foreach ($data as $key => $row) {
    if ($row['type'] != 'file') {
        $Template->iterate('/row/', null, array('title' => $row['title'], 'value' => nl2br($row['value'])));
    }    
}
//
 $Template->set(array(
//     'theme' => $data['theme']['value'], 
     'email' => $data['email']['value'],
//     'question' => $data['question']['value']
         ));

 
// отправка e-mail
$Form->sendmail($Template->display(), true);
$Sendmail = new Sendmail(CMS_MAIL_ID, $Template->title, $Template->display());
$Sendmail->send($email, true);

// Отправка автоответа
if (isset($data['email'])) { 
    $Form->sendAutoreply($data['email']['value']);     
}
//
////сохранение в базу данных
//$insID = $Form->saveFormParam($data);
//
//
///*
// * Инсертим, если поля  по дефолту не null - заполняем
// */
//$data = $DB->insert("INSERT INTO site_worker_feedback (worker_id, admin_email, theme, question, email) "
// . "VALUES({$admin_id}, '{$email}', '".addslashes($data['theme']['value'])."', '".addslashes($data['question']['value'])."', '{$data['email']['value']}')");


//сообщение пользователю
if (!empty($Form->info['result_text'])) {    
	$_RESULT['wrap'.$form_name] = $Form->resultText($form_data);
    
} elseif (!empty($Form->info['destination_url'])) {
	$_RESULT['javascript'] = "document.location.href='{$Form->info['destination_url']}'";
    
} else {
	$_RESULT['form_'.$form_name] = '';
}

exit;
?>