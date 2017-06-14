<?php

/**
 * Класс обработки команды компании
 * 
 * @package DeltaCMS
 * @subpackage Company
 * @version 2.0
 * @author Naumenko A.
 * @copyright c-format, 2017
 */

class Company {
    
    /**
     * Подразделения компании
     * @global type $DB
     * @return array
     */
    public function teamGroup()
    {
        global $DB;
        
        $data = $DB->query("select tb_group.id, tb_group.name_".LANGUAGE_CURRENT." AS name "
                . " FROM `company_team_group` as tb_group "
                . " INNER JOIN `company_team` as tb_team ON tb_team.group_id = tb_group.id and tb_team.active='1'"
                . " WHERE tb_group.active=1 "
                . " GROUP BY tb_group.id "
                . " ORDER BY tb_group.priority");
        return $data;
    }
    
    /**
     * Сотрудники
     * @param int $group_id - подразделение 
     * 
     * @global type $DB
     * @return array
     */
    public function teamEmployee($group_id = 0)
    {
        global $DB;
        
        $data = $DB->query("select "
                    . "     tb_team.id, "
                    . "     tb_team.group_id, "
                    . "     tb_team.image, "
                    . "     tb_team.email as email, "
                    . "     tb_team.post_".LANGUAGE_CURRENT." as post,"
                    . "     tb_team.name_".LANGUAGE_CURRENT." AS name "                    
                . " FROM `company_team` tb_team "
                . " INNER JOIN `company_team_group` as tb_group ON tb_group.id=tb_team.group_id "
                . " WHERE tb_team.image <> '' " 
                        . where_clause("tb_team.active", 1) 
                        . where_clause("tb_team.group_id", $group_id)
                . " GROUP BY tb_team.id ORDER BY tb_group.priority, tb_team.priority");
        reset($data);
        while( list($i, $row)=each($data) ){
            $data[$i]['image'] = Uploads::getIsFile('company_team', "image", $row['id'], $row['image']); 
        }
        return $data;
    }
    
}    