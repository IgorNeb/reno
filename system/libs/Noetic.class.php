<?php

/**
 * Клас для Тестов
 * 
 * @package DeltaCMS
 * @subpackage Noetic
 * @version 1.0
 * @author Naumenko A. 
 * @copyright Copyright 2017, c-format ltd.
 */

class  Noetic  
{
    public $selection_id = 0;
    public $noetic_id = 0;
    public $next = 0;
    public $total = 0;
    
    /**
     * @global type $DB
     */
    public function __construct()
    {
        global $DB;       
        
        $this->noetic_id = $DB->result("SELECT id
                FROM `noetic_question_group` WHERE `active` = '1' ORDER BY id");                
    }
    
    /**
     * Инфо о тесте
     * @global type $DB
     * @return array
     */
    public function getInfo()
    {
        global $DB;  
        
        $noetic_row = $DB->query_row("SELECT 
                    tb_group.id, 
                    tb_group.name_".LANGUAGE_CURRENT." as name,
                    tb_group.image,
                    tb_group.image_mob,
                    tb_group.content_".LANGUAGE_CURRENT." as content
                FROM `noetic_question_group` tb_group WHERE tb_group.id='{$this->noetic_id}'"); 
                
        $noetic_row['image'] = Uploads::getIsFile('noetic_question_group', 'image', $noetic_row['id'], $noetic_row['image']);
        if (IS_MOBILE) {
            $noetic_row['image_mob'] = Uploads::getIsFile('noetic_question_group', 'image_mob', $noetic_row['id'], $noetic_row['image_mob']);
            $noetic_row['image'] = (empty($noetic_row['image_mob'])) ? $noetic_row['image'] : $noetic_row['image_mob'];
        } 
        
        return $noetic_row;
    }
    
    /**
     * @global type $DB
     */
    public function start()
    {
        global $DB;
        
        $this->selection_id = globalVar($_SESSION['noetic_selection_id'], 0);
        
        if (empty($this->selection_id)) {
            $session_id         = session_id(); 
            $this->selection_id = $DB->result("SELECT id FROM `noetic_log_selection` WHERE `session_id` = '$session_id' ORDER BY id DESC ");
            
            if (empty($this->selection_id)) {
                $user_id            = Auth::getUserId();            
                $ip                 = HTTP_IP;
                $this->selection_id = $DB->insert("INSERT INTO `noetic_log_selection` (`session_id`, `user_id`, `ip`)"
                        . " VALUES('$session_id', '$user_id', '$ip') ");
                $_SESSION['noetic_selection_id'] = $this->selection_id;
            }
        }
        
        $next_question = $DB->result("SELECT count(id) FROM `noetic_log_question` WHERE `selection_id` = '$this->selection_id' ");
        $this->next    = intval($next_question) + 1;
        
        $this->total = $DB->result("SELECT count(id) FROM `noetic_question` WHERE `active` = '1' AND group_id='{$this->noetic_id}'");        
        
        return $this;
    }
    
    /**
     * Следующий вопрос
     * @global type $DB
     * @param int $number
     * @return array
     */
    public function getNextQuestion($number = null)
    {
        global $DB;
        
        if (is_null($number)) {
            $number = $this->next - 1;
        }
        
        $is_mobile = globalVar($_SESSION['is_mobile'], 0);
        
        $question = $DB->query_row("SELECT "
                . " tb_group.id, tb_group.name_".LANGUAGE_CURRENT." as name, tb_group.image,tb_group.image_mob 
            FROM `noetic_question` tb_group
            INNER JOIN `noetic_answer` as tb_answer on tb_answer.question_id=tb_group.id
            GROUP BY tb_group.id
            ORDER BY tb_group.priority LIMIT $number, 1");       
        
        if (!empty($question)) {
            $question['image'] = Uploads::getIsFile('noetic_question', 'image', $question['id'], $question['image']);
            
            if ($is_mobile) {
                //Изображение для телефона
                $image = Uploads::getIsFile('noetic_question', 'image_mob', $question['id'], $question['image_mob']);
                if (!empty($image)) {
                    $question['image'] = $image;
                }
            }
        }
                
        return $question;
    }
   
    public function getPrevQuestion($step)
    {
        $step--;
        return $this->getNextQuestion($step);
    }
    
    /**
     * Варианты ответов на вопрос
     * @global type $DB
     * @param int $question_id
     * @return array
     */
    public function getAnswer($question_id)
    {
        global $DB;
       
        $data = $DB->query("SELECT
                    tb_answer.id, 
                    tb_answer.name_".LANGUAGE_CURRENT." as name
            FROM `noetic_answer` as tb_answer 
            WHERE tb_answer.question_id = '$question_id'            
            ORDER BY tb_answer.priority");        
       
        return $data;
    }
    
    public function getLogAnswer($question_id)
    {
        global $DB;
        
        return $DB->result("SELECT
                   answer_id
            FROM `noetic_log_question` 
            WHERE question_id = '$question_id' AND selection_id='{$this->selection_id}' ");             
    }
    public function setStep($step) {
        $this->next = $step;
    }
    /**
     * Ответ пользователя в статистику
     * @global type $DB
     * @param int $answer
     * @param int $question_id
     * @return array
     */
    public function setAnswer($answer_id, $question_id) 
    {
        global $DB;
       
        $answers = $DB->fetch_column("SELECT id FROM noetic_answer WHERE question_id='$question_id'");
        if (in_array($answer_id, $answers)) {
            $DB->query("INSERT INTO `noetic_log_question` (`selection_id`, `question_id`, `answer_id`) 
                    VALUES('{$this->selection_id}', '{$question_id}', '$answer_id')"
                . " on duplicate key update `answer_id` = '$answer_id' ");               
            $this->next++;        
        }
    }
    
    /**
     * Возращает результат теста
     * @global type $DB
     * @param int $result_id
     * @return array
     */
    public function getResult($result_id = 0)
    {
        global $DB;
        
        $is_girl = false;
        if (!empty($this->selection_id)) {
            $id = (int)$DB->result("SELECT id FROM noetic_log_question "
                    . " WHERE selection_id='$this->selection_id' AND answer_id = 2");            
            if (!empty($id)) {
                $is_girl = true;
            }
            
        }
        
        $is_mobile = globalVar($_SESSION['is_mobile'], 0);
        $field = ($is_mobile) ? 'image_mob' : 'image';
        
        $data = $DB->query_row("SELECT "
                . " tb_result.id, tb_result.name_".LANGUAGE_CURRENT." as name, 
                    tb_result.content_".LANGUAGE_CURRENT." as content,
                    tb_result.description_".LANGUAGE_CURRENT." as description,
                    tb_result.uniq_name,
                    tb_result.".$field." as image, tb_result.".$field."_girl as image_girl,
                    tb_result.image_soc, tb_result.image_soc_girl
            FROM `noetic_result` tb_result
            WHERE tb_result.id='$result_id'");       
        
        if (!empty($data)) {
            $data['description'] = strip_tags($data['description']);
            
            $data['image_girl'] = Uploads::getIsFile('noetic_result', $field . '_girl', $data['id'], $data['image_girl']);                       
            if ($is_girl && !empty($data['image_girl'])) {
                $data['image'] = $data['image_girl'];
            } else {
                $data['image'] = Uploads::getIsFile('noetic_result', $field, $data['id'], $data['image']);
            }
            
        }
        
        if (!empty($this->selection_id)) {
            $result_row = $DB->query_row("SELECT id, image FROM noetic_log_result WHERE selection_id='$this->selection_id' ");
      
            if (!empty($result_row)) {
                $data['uniq_name'] .= '/' . $result_row['id'];
                
                $image = '';//Uploads::getIsFile('noetic_log_result', 'image', $result_row['id'], $result_row['image']);

                if (empty($image) || is_null($image)) {
                    //Формирование картинки для соц сетей с определением пола                        
                    if ($is_girl) {         
                        $image = Uploads::getIsFile('noetic_result', 'image_soc_girl', $data['id'], $data['image_soc_girl'], '');                        
                    }
                    if (empty($image)) {            
                        $image = Uploads::getIsFile('noetic_result', 'image_soc', $data['id'], $data['image_soc'], '');
                    }                    
                    if (!empty($image)) {                           
                        $extension = Uploads::getFileExtension($image);
                        $newFile = UPLOADS_ROOT . Uploads::getStorage( 'noetic_log_result', 'image', $result_row['id']).'.'.$extension;            
                        Filesystem::copy(UPLOADS_ROOT . $image, $newFile, true);           
                        $DB->update("UPDATE `noetic_log_result` SET image='$extension' WHERE id='{$result_row['id']}'");

                        $this->createImageSoc($newFile, $data['name']);

                        $image = Uploads::getIsFile('noetic_log_result', 'image', $result_row['id'], $extension);

                    } 
                }
                
                $data['image_soc'] = $image;
                
                
            } else {
                $data['image_soc'] = Uploads::getIsFile('noetic_result', 'image_soc', $data['id'], $data['image_soc']);
            }
                        
        }
        return $data;
    }
    
    /**
     * Формирование изображения для соц сетей
     * @global type $DB
     * @param string $image
     * @param string $title
     * @return boolean
     */
    public function createImageSoc($image, $title)
    {
        global $DB;        
        
        $user_id = $DB->result("SELECT user_id FROM noetic_log_selection WHERE id='$this->selection_id'");
        $user = Auth::getDataInfo($user_id);
        if (empty($user)) {
            return false;
        }
        $id = $DB->result("SELECT id FROM auth_user_data WHERE user_id='{$user_id}'");
        
        $photo = '';
        if (!empty($user['photo']) && empty($user['local_photo'])) {
            
            $extension = Uploads::getFileExtension($user['photo']);
            Uploads::uploadByLink($user['photo'], 'auth_user_data', 'local_photo', $id, $extension);
             
            $photo = Uploads::getIsFile('auth_user_data', 'local_photo', $id, $extension, ''); 
            $newFile = Uploads::getStorage('auth_user_data', 'local_photo', $id) . '.png';
            
            $Images = new Image(UPLOADS_ROOT . $photo);  
            $Images->resize(142, 142, 1);
            $Images->save(UPLOADS_ROOT . $photo);
            unset($Images);
            
            $imagegick = new Imagick(UPLOADS_ROOT . $photo);
            $imagegick->setImageFormat("png");
            $imagegick->roundCorners(71, 71);
            $imagegick->writeImage(UPLOADS_ROOT . $newFile);    
            
            Filesystem::delete(UPLOADS_ROOT . $photo);
            
            $photo = $newFile;
            
            $DB->update("UPDATE auth_user_data SET local_photo = 'png' WHERE id= '$id' ");
            
        } elseif (!empty($user['local_photo'])) {
            $photo =  Uploads::getIsFile('auth_user_data', 'local_photo', $id, $user['local_photo'], ''); 
        }
        
        $Images = new Image($image);                
        if (!empty($photo)) {
            //водяной знак уже на ужатое изображение                                        
            $Images->watermark(SITE_ROOT . 'design/renault/img/circle.png', 'left', 'top', 100, 30, 0);
            $Images->watermark(UPLOADS_ROOT . $photo, 'left', 'top', 120, 50, 0);
        }
        
        $Images->setText($image, $user['firstname'] . ' — ' . "\n". str_replace('Ти - ', '', $title));
     
        $Images->save($image);
    }
    
    /**
     * Удаляем последний ответ
     * @global type $DB
     * @param int $answer
     * @return array
     */
    public function deleteAnswer()
    {
        global $DB;
       
        $id = $DB->result("SELECT id FROM `noetic_log_question` WHERE `selection_id` = '{$this->selection_id}' ORDER BY id DESC LIMIT 1");
        $DB->delete("DELETE FROM `noetic_log_question` "
                . "  WHERE id = '$id'  "); 
        
        $this->next--;          
    }
    
}