<?php

/* 
 * Интелектуальный подбор авто. 
 * @package DeltaCMS
 * @subpackage Noetic
 * @author Timofeev V.
 * @copyright (c) 2016, c-format
 */

$task = globalVar($_REQUEST['task'], '' );

if($task == 'repeat'){
    $session_id      = session_id(); 
    $where_condition = " session_id = '$session_id' LIMIT 1";
    Misc::copyRows('noetic_log_selection', $where_condition, array('dtime'=>date("Y-m-d H:i:s")) );
    $lang_url = LANGUAGE_URL;
    $_RESULT['javascript'] = "window.location.href='/{$lang_url}avto-u-nayavnosti/noetic/';";
    exit();
}

if( $task == 'stats'){
    $model_id = globalVar($_REQUEST['model_id'], 0);
    $Noetic = new Noetic();
    $DB->insert("INSERT INTO `noetic_log_result` (`selection_id`, `model_id`, `task`) VALUES( '{$Noetic->selection_id}', '$model_id', 'transit' ) ");
}

if( $task == 'steps'){
    $Noetic = new Noetic();
    $Noetic->deleteAnswer();
    
    $questions = $Noetic->getNextQuestion();
    $question_id = $questions['id'];

    $answers   = $Noetic->getAnswer($question_id);
    $gallery   = $Noetic->getPhoto( $question_id);

    $Template = new Template('noetic/question');
    $Template->setGlobal('step', $Noetic->next);
    $Template->set('count_step', $Noetic->total);
    
    for( $i=1; $i<=$Noetic->total; $i++){
        $Template->iterate('/steps/', null, array('number'=>$i) );
    }    
    if ($gallery) { $Template->set( $gallery ); }
    $Template->set( $questions );
    $Template->iterateArray('/answer/', null, $answers);

    $_RESULT['steps_noetic'] = $Template->display();

}

