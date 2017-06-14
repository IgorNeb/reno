<?php

/* 
 * Интелектуальный подбор авто
 * @package DeltaCMS
 * @subpackage Noetic
 * @author Nauemnko A.
 * @copyright (c) 2015, c-format
 */

$name   = globalVar($_REQUEST['name'], '');
$answer = globalVar($_REQUEST['answer'], 0);
$step = globalVar($_REQUEST['step'], 0);

//$lang_url = globalVar($_REQUEST['_language'], '');
//if(!empty($lang_url)) $lang_url .= '/'; 

$Noetic = new Noetic();
$questions = $Noetic->start()->getNextQuestion($step - 1);

if (isset($questions['id']) && !empty($answer)) {
    $Noetic->setAnswer($answer, $questions['id']);
    $questions = $Noetic->start()->getNextQuestion($step);   
}

if (isset($questions['id'])) {
    
    $Noetic->setStep($step+1);
    
    $question_id = $questions['id'];

    $answers   = $Noetic->getAnswer($question_id);
   
    $Template = new Template('noetic/question');
    $Template->setGlobal('step', $Noetic->next);
    $Template->set('count_step', $Noetic->total);
    
    $answer_id = $Noetic->getLogAnswer($question_id);
    $Template->setGlobal('answer_id', $answer_id);
    
    $Template->set($questions);
    $Template->iterateArray('/answer/', null, $answers);

    $_RESULT['steps_noetic'] = $Template->display();
    $_RESULT['javascript'] = "Noetic.update(".$Noetic->next.");";
    
} else {    
    //Пользователь прошел тест, выдаем результат        
    $data = $DB->query_row("SELECT"
            . "     tb_result.id, tb_result.uniq_name, COUNT(tb_relation.result_id) as counts "          
            . " FROM `noetic_result` AS tb_result "                
            . " INNER JOIN `noetic_answer` AS tb_relation ON tb_relation.result_id = tb_result.id "
            . " INNER JOIN `noetic_log_question` AS tb_log ON tb_log.answer_id = tb_relation.id "                
            . " WHERE tb_log.selection_id = '{$Noetic->selection_id}'"
            . "   AND tb_relation.result_id > 0 AND tb_result.active=1"
            . " GROUP BY tb_result.id "
            . " ORDER BY counts DESC, tb_result.priority ASC LIMIT 1");      
            
    if (!empty($data)) {
        $DB->insert("INSERT INTO `noetic_log_result` (`selection_id`, `result_id`, `task`, `image`) "
                . " VALUES( '{$Noetic->selection_id}', '{$data['id']}', 'show', '') ");
        // $_RESULT['javascript'] = "window.location.href='/noetic/" . $data['uniq_name'] ."/';";            
        
        //Страница результата
        $Noetic = new Noetic();
        $data = $Noetic->start()->getResult($data['id']);
        
        $Template = new Template('noetic/result');
        $Template->set('result', $data);

        $_RESULT['wrap_noetic'] = $Template->display();
        $_RESULT['javascript'] = "autoScroll('#wrap_noetic', 100);";
        $_RESULT['javascript'] .= "shareInit();$('.share').on('click', function(){ $('.share42init').toggleClass('show'); });";
    }
    
}
exit;