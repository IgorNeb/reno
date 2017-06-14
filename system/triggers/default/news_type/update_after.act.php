<?php

//require_once($this->triggers_root.'insert_after.act.php');

if($this->NEW['type_id'] == 0 && $this->NEW['sortby'] != $this->OLD['sortby']){
    $DB->query("UPDATE news_type SET sortby = '{$this->NEW['sortby']}' WHERE type_id='{$this->NEW['id']}' ");
}

?>
