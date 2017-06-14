<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class cmsDocs
{
    function __construct() { }

    /**
     * 
     * @global type $DB
     * @param mixed(int|array) $ids
     */
    public static function getByIds($ids) 
    {
        global $DB;
        
        if (empty($ids)) {
            return "";
        }
        
        $data = $DB->query("SELECT * FROM `cms_documentation`"
                . "  WHERE 1 ".  where_clause('id', $ids)." ORDER BY priority");
        
        return self::get($data);
    }
    
    public static function get($data)
    {
        if (empty($data)) {
            return "";
        }
        
        $info = array();
        foreach ($data as $row) {
            $row['docs_file']= Uploads::getIsFile('cms_documentation', 'docs_file', $row['id'], $row['docs_file']);        
            if (empty($row['docs_file']) && empty($row['docs_txt'])) {
                continue;
            }
            $info[] = $row;
        }
        
        if (empty($info)) {
            return "";
        }
        
        $Template = new Template('cms/docs/popup_docs');
        $Template->iterateArray('/info/', null, $info);
        return $Template->display();
    }
    
}