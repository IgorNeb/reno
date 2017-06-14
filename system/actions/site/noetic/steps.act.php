<?php

/* 
 * Интелектуальный подбор авто. 
 * @package DeltaCMS
 * @subpackage Noetic
 * @author Nauemnko A.
 * @copyright (c) 2015, c-format
 */

$task = globalVar($_REQUEST['task'], '' );
$step = globalVar($_REQUEST['step'], 0);
$answer = globalVar($_REQUEST['answer'], 0);

if ($task == 'repeat') {
    $session_id      = session_id(); 
    $where_condition = " session_id = '$session_id' LIMIT 1";
    Misc::copyRows('noetic_log_selection', $where_condition, array('dtime'=>date("Y-m-d H:i:s")) );
    
    unset($_SESSION['noetic_selection_id']);
    
    Header("Location: /" . LANGUAGE_URL);
    exit();
}

/*
if( $task == 'stats'){
    $model_id = globalVar($_REQUEST['model_id'], 0);
    $Noetic = new Noetic();
    $DB->insert("INSERT INTO `noetic_log_result` (`selection_id`, `model_id`, `task`) VALUES( '{$Noetic->selection_id}', '$model_id', 'transit' ) ");
}

*/

if ($task == 'steps') {
    $Noetic = new Noetic();
    
    $questions = $Noetic->start()->getNextQuestion($step);    
    if (isset($questions['id']) && !empty($answer)) {
        $Noetic->setAnswer($answer, $questions['id']);           
    }
    $questions = $Noetic->start()->getPrevQuestion($step);    
    if (empty($questions)) {
        exit();
    }
    
    $question_id = $questions['id'];
   
    $answers   = $Noetic->getAnswer($questions['id']);
    
    //$gallery   = $Noetic->getPhoto( $question_id);
    $Template = new Template('noetic/question');
    $Template->setGlobal('step', $step);
    $Template->set('count_step', $Noetic->total);
    
    $answer_id = $Noetic->getLogAnswer($question_id);
    $Template->setGlobal('answer_id', $answer_id);

    $Template->set( $questions );
    $Template->iterateArray('/answer/', null, $answers);

    $_RESULT['steps_noetic'] = $Template->display();
    $_RESULT['javascript'] = "Noetic.update(".$step.");";

}
