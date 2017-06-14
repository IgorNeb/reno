<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

$show_results = false;
//$TmplDesign->set('headline', '');
//$TmplDesign->set('breacrumbs', '');
$TmplDesign->set('page_class', 'noetic');

$result_id = $Site->getItemId();
$log_id = globalVar($_REQUEST['result'], 0);

if (!empty($result_id)) {
    //Страница результата  
//    if (IS_DEVELOPER) {
//        $_SESSION['noetic_selection_id'] = 65;
//    }
    if (!empty($log_id)) {        
        $_SESSION['noetic_selection_id'] = $DB->result("SELECT selection_id FROM `noetic_log_result` WHERE id='{$log_id}' AND result_id='$result_id'");
        if (empty($_SESSION['noetic_selection_id'])) {
            header("Location: /".LANGUAGE_URL."noetic/");
            exit();
        }
    }
    $Noetic = new Noetic();
    $data = $Noetic->start()->getResult($result_id);
    $Template = new Template('noetic/result');
    $Template->set('result', $data);
    
    $TmplContent->set('result_tmpl', $Template->display());
    $TmplDesign->set('socImage', $data['image_soc'] ? $data['image_soc'] : $data['image']);
    $TmplDesign->set('description', $data['description']);
    
} elseif (!Auth::isLoggedIn()) {
    
    $Noetic = new Noetic();
    if (!$Noetic->noetic_id) {
        $Site->cross404();
        exit();
    }
    
    $form_tmpl = Auth::displayLoginForm(false, false);
    $TmplContent->set('login_form', $form_tmpl);
    
    $TmplContent->set('show_greeting', true);
    $TmplContent->set('noetic', $Noetic->getInfo());
    
} else {
//          $url = "https://api.vk.com/method/users.get?user_ids=19349652&fields=photo,photo_max&name_case=Nom&version=5.64";    
//        $response = json_decode(file_get_contents($url)); 
//       
    $Noetic = new Noetic();
        
    if (!$show_results) {        
        //Пользователь проходил тест, возращаем его на последний вопрос
        $questions = $Noetic->start()->getNextQuestion();     
        
        if (isset($questions['id'])) {
            $question_id = $questions['id'];

            $answers   = $Noetic->getAnswer($question_id);
            
            $Template = new Template('noetic/question');
            $Template->setGlobal('step', $Noetic->next);

            $Template->set($questions);
            $Template->iterateArray('/answer/', null, $answers);

            $TmplContent->set('steps_noetic', $Template->display());
            $TmplContent->set('count_step', $Noetic->total);
            $TmplContent->set('step', $Noetic->next);
            
        } else {
            $TmplContent->set('repeat', true);         
            if (!$Noetic->noetic_id) {
                $Site->cross404();
                exit();
            }
            $TmplContent->set('noetic', $Noetic->getInfo());
            
        }

    }

}