<?php

if( $this->NEW['object_id'] == 0 && $this->NEW['table_id'] == 0 ){
    $DB->query("UPDATE seo_structure SET object_id = '{$this->NEW['id']}' WHERE id='{$this->NEW['id']}' ");
}
