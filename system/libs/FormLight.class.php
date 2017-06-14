<?php
/**
 * Класс, который выводит формы на сайте
 * @package Pilot
 * @subpackage Form
 * @version 3.0
 * @author Rudenko Ilya <rudenko@delta-x.ua>
 * @copyright c-format, 2016
 */

class FormLight 
{
	/**
	 * id формы
	 * @var int
	 */
	public $form_id = 0;
        
    /**
     * Заголовок формы
     * @var string
     */
	public $title = '';
    
	/**
	 * E-mail адреса, на которые надо отправлять данные
	 * @var array
	 */
	public $email = array();
        
	/**
	 * Дополнительные параметры к форме
	 * @var array
	 */
	public $info = array();
	
    /**
     * Уникальное название формы
     * @var string
     */
	private $form_name = '';
        
    /**
     * Ошибки заполнения формы
     * @var array
     */
	private $error = array();
        
    /**
     * Прикрепленные файлы
     * @var array
     */
    private $attach = null;
        
	/**
	 * Конструктор
	 *
	 * @param string $uniq_name - уникальное имя формы
	 */
	public function __construct($uniq_name) 
    {
		global $DB;
		
        $this->form_name = $uniq_name;
                
		$query = "
			SELECT *,
				`title_".LANGUAGE_CURRENT."` as title,				
				`button_".LANGUAGE_CURRENT."` as button,								
				`resulttext_".LANGUAGE_CURRENT."` as result_text,
                `autoreply_".LANGUAGE_CURRENT."` as autoreply,
                `description_".LANGUAGE_CURRENT."` as description
			FROM `form` 
			WHERE `uniq_name` = '$uniq_name'
		";
		$info = $DB->query_row($query); 
		if ($DB->rows > 0) {
			$this->form_id = $info['id'];
            $this->title = $info['title'];

            $info['action_form'] = (empty($info['action'])) ? 'form/send' : $info['action'];
            $info['button'] = (empty($info['button'])) ? cmsMessage::get("MSG_FORM_SEND_FORM") : $info['button'];
            $info['destination_url'] = '/' . LANGUAGE_URL . trim($info['destination_url'], '/') . '/';
            $this->info = $info;
                        
			$this->email = dexplode($info['email']);
		}
	}
	
	/**
	 * Загружает поля, которые есть в форме
	 *
	 * @return array
	 */
	public function loadParam()
    {
		global $DB;
		
		// Информация о полях
		$query = "
			SELECT 
				tb_field.id,
				tb_field.form_id,
				tb_field.uniq_name,
                tb_field.title_".LANGUAGE_CURRENT."   AS title,
				tb_field.comment_".LANGUAGE_CURRENT." AS comment,
				tb_field.type,
				tb_field.is_check,
				tb_field.class_name,
				tb_field.required,				
				tb_regexp.regular_expression AS `regexp`,
				tb_field.default_value_".LANGUAGE_CURRENT." AS default_value
			FROM form_field AS tb_field
			LEFT JOIN cms_regexp AS tb_regexp ON tb_regexp.id=tb_field.regexp_id
			WHERE tb_field.form_id='$this->form_id'
			ORDER BY tb_field.priority
		";
		$data = $DB->query($query, 'id');
        
        //Обработка полей формы
        $this->parseParam($data);
        
		// Справочники для полей
		$query = "
			SELECT id, field_id, uniq_name, title_".LANGUAGE_CURRENT." AS title
			  FROM form_field_value
             WHERE field_id IN (0".implode(",", array_keys($data)).")
		  ORDER BY priority";
		$info = $DB->query($query);
        
		reset($info);
		while (list(, $row) = each($info)) {
			$data[$row['field_id']]['info'][$row['uniq_name']] = $row['title'];
		}		
		return $data;
	}
    
    /**
     * Обработка полей формы перед выводом при необходимости
     * 
     * @global Site $Site
     * @param array $data
     */
    private function parseParam(&$data)
    {     
        global $DB;
        
        //$index = 1;
        foreach ($data as $id => $row) {       
            $info = array();
            
            if ($row['type'] == 'dealer') {
                
                $query = "  select 
                                    tb_dealer.id,
                                    tb_dealer.latlng
                            from site_dealer tb_dealer
                            where tb_dealer.active=1 
                            order by tb_dealer.priority
                    ";
                $dealer = $DB->query($query);
                foreach ($dealer as $i => $dealer_row) {
                    $lat = dexplode($dealer_row['latlng']);
                    if (count($lat) == 2) {
                        $dealer_row['lat'] = $lat[0];
                        $dealer_row['lng'] = $lat[1];
                        //$DB->update("UPDATE site_dealer SET latlng ='".$lat[1].",".$lat[0]."' WHERE id='{$dealer_row['id']}'");
                        $info[] = $dealer_row;
                    }
                }
            }
            
            $data[$id]['info'] = $info;   
		}		
    }
    
    /**
     * Сохранение данных формы
     * 
     * @global DB $DB
     * @param array $data
     * @return bool
     */
    public function saveFormParam($data)
    {
        global $DB;

        $base_info = array('name', 'phone', 'email');      
        
        //сохранение базовых данных
        $insert = array();
        foreach ($base_info as $key) {
            if (isset($data[$key]['value'])) {
                $insert[] =  " `$key` = '{$data[$key]['value']}'";
                unset($data[$key]);
            }
        }          
        if (empty($insert)) {
            return false;
        }

        $query = "INSERT INTO `site_form_feedback` SET `form_id` = '{$this->form_id}', ".implode(', ', $insert)."";
        $claim_id = $DB->insert($query);

        // сохранение дополнительных данных 
        $insert = array(); 
        
        $i = 1;
        foreach ($data as $key => $row) {            
            if (empty($row['value']) || $row['type'] == 'passwd') {
                continue; 
            }
            if ($row['type'] == 'file') {
                $extension = Uploads::getFileExtension($row['value']); 
                $query = "INSERT INTO `site_form_feedback_value` (`feedback_id`, `name`, `value`, `type`, `priority`) "
                       . "VALUES ('$claim_id', '".$DB->escape($row['title'])."', '$extension', 'file', '$i')";                    
                $id = $DB->insert($query);
                Filesystem::rename($row['value'], UPLOADS_ROOT . strtolower("site_form_feedback_value/value/".Uploads::getIdFileDir($id).'.'.$extension), true);                           
            } else {
                $insert[] =  "('$claim_id', '".$DB->escape($row['title'])."', '".$DB->escape($row['value'])."', '{$row['type']}', '$i')";                    
            }
            $i++;
        }
        
        if (count($insert)){
            $DB->query("INSERT INTO `site_form_feedback_value` 
                             (`feedback_id`, `name`, `value`, `type`, `priority`) 
                        VALUES ".implode(', ', $insert) );
        }
        return $claim_id;
    }
        
    /**
	 * Проверка полей формы
     * 
	 * @param array - Заполненные поля формы
	 * @return mixed (array or boolean)
	 */
	public function checkFields($data)
    {
        global $DB;
        // Проверяем CAPTCHA
//        if ( FORM_CAPTCHA && !Auth::isLoggedIn() 
//           && !Captcha::check(globalVar($_REQUEST['captcha_uid'], ''), globalVar($_REQUEST['captcha_value'], ''))) 
//        {
//            $this->error['capthca'] = cmsMessage::get("MSG_FORM_CAPTHCA_ERROR"); 
//            return false;
//        }

        //загрузка полей формы
        $fields = $this->loadParam();
        foreach ($fields as $row) {
            // Проверяем правильность ввода данных
            $uniq_name = $row['uniq_name']; 
            $title = trim($row['title'], '*');

            if ($row['type'] == 'file') { 
                //обработка прикрепленных файлов
                $data[$uniq_name] = $this->getAttach($uniq_name);
                if (isset($this->error['file'])) { 
                    return false;
                }
            }
            
            if ($row['required'] 
                && (!isset($data[$uniq_name]) || empty($data[$uniq_name]) 
                    || (!is_array($data[$uniq_name]) && trim($data[$uniq_name]) == $row['title']) )
            ) { //обязательные для заполнения                   
                $this->error[$uniq_name] = cmsMessage::get("MSG_FORM_ERROR_REQUIRED") ." \"$title\""; 
                return false;                    
            } elseif (!isset($data[$uniq_name])) {                    
                continue;                    
            } elseif (!empty($data[$uniq_name]) 
                   && !empty($row['regexp'])
                   && !preg_match($row['regexp'], $data[$uniq_name]) 
            ) { //не верно заполненные поля
                $this->error[$uniq_name] = cmsMessage::get("MSG_FORM_ERROR_INPUT") ." \"$title\""; 
                return false;
            }
        }

        $form_data = array();
        foreach ($fields as $row) {
            $uniq_name = $row['uniq_name']; 
            if (!isset($data[ $uniq_name ])) { 
                continue;
            }
            // Обработка данных                
            $form_data[$uniq_name] = array( 
                                        'title'     => trim($row['title'], '*'),
                                        'uniq_name' => $uniq_name,
                                        'type'      => $row['type'],
                                        'value'     => $data[$uniq_name]
                                    );

            if (is_array($data[$uniq_name])) {
                $form_data[$uniq_name]['value'] = implode(", ", $data[$uniq_name]);
                
            } elseif ($row['type'] == 'enum') {
                $query = "SELECT title_".LANGUAGE_CURRENT." FROM `form_field_value` WHERE `field_id`='{$row['id']}' AND `uniq_name`='{$data[$uniq_name]}' ";
                $value = $DB->result($query); 
                if (!empty($value)) {
                    $form_data[$uniq_name]['value'] = $DB->result($query); 
                }
                
            } elseif ($uniq_name == 'product_id' && $row['type'] == 'hidden') {
                $product = $DB->query_row("SELECT tb_product.name_".LANGUAGE_CURRENT." AS name, "
                        . "     CONCAT('/', tb_group.url, '/', tb_product.uniq_name, '/' ) as url "
                        . " FROM `shop_product` as tb_product "
                        . " INNER JOIN shop_group as tb_group ON tb_group.id=tb_product.group_id "
                        . " WHERE tb_product.`id`='{$data[$uniq_name]}'  "); 
                         
                if (!empty($product)) {
                    $form_data[$uniq_name]['value'] = "<a href='".CMS_URL."{$product['url']}' target='_blank'>".$product['name']."</a>";                            
                }            
            }
        } 

        return $form_data;
	}
        
    /**
     * Возращает загруженные файлы к форме
     * Если не указанное поле ($uniq_name) - возращает все файлы
     * 
     * @param string $uniq_name - поле
     * @return  string|array - все файлы или определенного поля
     */
    public function getAttach($uniq_name = '')
    {
        if (is_null($this->attach)) {
            $this->attach = $this->loadAttachFile();
        }

        if (!empty($uniq_name)) {
            if (!isset($this->attach[$uniq_name])) {
                $this->attach[$uniq_name] = ''; 
            }
            return $this->attach[$uniq_name];
        } else {
            return $this->attach;
        }
    }

    /**
     * Обработка загруженныx файлов к форме
     * 
     * @return string|array
     */
    public function loadAttachFile()
    {
        $allowed_extension = array('jpg', 'jpeg', 'gif','bmp','png', 'pdf', 'doc', 'docx', 'rtf', 'xls', 'xlsx', 'ppt');

        $attach = $files = array();
        if (!isset($_FILES) || empty($_FILES)) { 
            return $attach; 
        }
        $uid = 'form_'.uniqid();

        // преоброзование массива            
        reset($_FILES['form']);
        while (list($title, $row) = each($_FILES['form'])) {    
            foreach ($row as $key => $value) {                
                $files[$key][$title] = $value;
            }    
        }

        foreach ($files as $title => $row) {           
            if ($row['error'] != 0) {  
                // файл закачан с ошибкой, игнорируем его
                continue;
            }
            $extension = Uploads::getFileExtension($row['name']);
            if (!in_array($extension, $allowed_extension)) {
                $this->error['file'] = cmsMessage::get("MSG_FORM_ERROR_FILEEXTENSION"); 
                return false;
            }
            Uploads::moveUploadedFile($row['tmp_name'], TMP_ROOT . $uid . '/'.$title.'.'.$extension);
            $attach[ $title ] =  TMP_ROOT . $uid . '/' . $title . '.' . $extension;
        }
        
        return $attach;
    }

    /**
     * Отправка автоответа. Если не задано, с какого E-mail отправлять, отправка с E-mail по умолчанию
     * 
     * @param string $email
     */
    public function sendAutoreply($email) 
    {  
        $from_email_id = (!empty($this->info['from_email_id'])) ? $this->info['from_email_id'] : CMS_MAIL_ID;
        
        if (!empty($email) && $from_email_id && !empty($this->info['autoreply'])) {
            $Sendmail = new Sendmail($from_email_id, $this->title, $this->info['autoreply']);
            $Sendmail->send($email, true);                
        }
    }

    /**
     * Оправляем уведомление админам о заполнении формы
     * 
     * @param text $content - тело письма
     * @param bool $send_now - мгновенная отправка
     */
    public function sendmail($content, $send_now = true)
    {            
        $Sendmail = new Sendmail(CMS_MAIL_ID, $this->title, $content);

        //прикрепленные файлы
        $attach = $this->getAttach();
        foreach ($attach as $row) { 
            $Sendmail->attach($row);                 
        }
        
        $emails = $this->getEmails();
        foreach ($emails as $email) {            
            $Sendmail->send($email, $send_now);
        }
    }
    
    /**
     * Сообщение, которое появляется после отправки формы
     * @param array $data
     * @return text
     */
    public function resultText($data)
    {
        $Template = new TemplateString($this->info['result_text']);
        $Template->set($data);
        return $Template->display();
    }

    /**
     * Почтовые ящики формы
     * 
     * @return type
     */
    public function getEmails()
    {
        return $this->email;
    }

    /**
     * Возрат ошибок
     * 
     * @return array
     */
    public function getError()
    {
        return $this->error;
    }
	
}

?>