<?php 
/**
 * Формирование карты сайта. Карта формируется на основе Сео структуры
 * Карта будет изменена, если вчера были внесены изменения в структуру
 * 
 * @author Naumenko A.
 * @description Формирование карты сайта
 * @copyright c-format, 2014
 * @crontab В 01:00:00
 */

chdir(dirname(__FILE__));
require_once('../../crontab.inc.php');

if ( !SEO_SITEMAP && !SEO_SITEMAP_IMAGE ){  exit; }
	

if (!defined('DESIGN_URL')) {
    define('DESIGN_URL', '/design/vinzer/');
}

//Удаляем старые файлы sitemap
$listing = Filesystem::getDirContent(SITE_ROOT.'static/sitemap/', false, false, true);
reset($listing);
while (list(,$row) = each($listing)) {
	echo "[i] Remove obsolete $row\n";
	unlink(SITE_ROOT.'static/sitemap/'.$row);	
}

$listing = Filesystem::getDirContent(SITE_ROOT.'static/sitemap/image/', false, false, true);
reset($listing);
while (list(,$row) = each($listing)) {
    echo "[i] Remove obsolete $row\n";
    unlink(SITE_ROOT.'static/sitemap/image/'.$row);
}

// Формируем карту сайта
$sitemaps = array();
$lang     = array( 
			'ru'=> array('link' => '/', 'hreflang' => 'ru')
		);
$langURL = '';

$query = "select * from site_structure_site";
$sites = $DB->query($query);
reset($sites);
while (list(,$site) = each($sites)) {
	echo "[i] $site[url] " . NL;
        
    if (SEO_SITEMAP) {
        /*
        *  SITEMAP
        */
        $Sitemap = new Sitemap($site['url'], $lang);
        $Sitemap->clear();

        // Формируем карту сайта на основании таблицы seo_structure
        $query = "SELECT `id`, 
                        REPLACE(`url`, '".$site['url']."', '') as url,
                        `last_modified`, `change_frequency`, `page_priority`, table_id "
                . " FROM seo_structure "
                . " WHERE `is_sitemap`='1' AND "
                . "        p_status = 200 AND "
                . "        active='1' AND "
                . "        `url` LIKE '{$site['url']}%' "
                . " ORDER BY group_id, priority ";
        $data = $DB->query($query);            
        reset($data);
        while (list(,$row) = each($data)) {
            $row['url'] = ($row['table_id'] == 2926) ? trim($row['url'], '/') . '.html' : (!empty($row['url']) ? trim($row['url'], '/')  . '/' : '');
           
            $Sitemap->addUrl( $row['url'], $row['last_modified'], $row['change_frequency'], $row['page_priority']);                    
        }

        $Sitemap->build(SITE_ROOT.'static/sitemap/', "$site[url].xml", "https://$site[url]/static/sitemap/", false);            
        $sitemaps = $Sitemap->getSitemaps();
        echo NL . "SITEMAP OK" . NL;
        unset($Sitemap);
    }

    if (SEO_SITEMAP_IMAGE || is_module('Shop')) {
        /*
        * IMGSITEMAP
        */
        $Sitemap = new Sitemap($site['url']);
        $Sitemap->clear();

        $query = "SELECT tb_seo.`id`, tb_seo.`object_id`, tb_group.image, tb_group.`name_".LANGUAGE_CURRENT."` as caption, "
              . " REPLACE(tb_seo.`url`, '".$site['url']."', '') as url, tb_seo.`last_modified`, tb_seo.`change_frequency`, tb_seo.`page_priority` "
              . " FROM seo_structure tb_seo "
              . " INNER JOIN shop_group AS tb_group on tb_group.id=tb_seo.object_id "
              . " WHERE "
                . "     tb_seo.table_id='2918' AND "
                . "     tb_seo.p_status = 200  AND"
                . "     tb_seo.`url` like '{$site['url']}%' AND "
                . "     tb_seo.`is_sitemap`='1' AND "
                . "     tb_seo.active='1' "
              . " ORDER BY tb_seo.group_id, tb_seo.priority";
        $data = $DB->query($query);

        reset($data);
        while (list(,$row) = each($data)) {
            
            $row['url'] = '/' . $langURL . trim($row['url'], '/'). '/';
            
            $image = Uploads::getIsFile('shop_group', 'image', $row['object_id'], $row['image']);
            if (!empty($image)) {
                $Sitemap->addUrl($row['url'], $row['last_modified'], $row['change_frequency'], $row['page_priority']);	
                $Sitemap->addImage($image, $row['caption']);			
            }
        }

        // Товары
        $query = "select `id`, `object_id`, `name_".LANGUAGE_CURRENT."` as caption, 
                         REPLACE(`url`, '".$site['url']."', '') as url, `last_modified`, `change_frequency`, `page_priority` "
                . " from seo_structure "
                . " where table_id='2926' and p_status = 200 and `is_sitemap`='1' and active='1' and `url` like '{$site['url']}%' "
                . " order by group_id, priority";
        $data = $DB->query($query);

        reset($data);
        while (list(,$row) = each($data)) {
            
            $row['url'] = '/' . $langURL . trim($row['url'], '/'). '.html';
            
            $image = Shop::getPhotoProduct($row['object_id'], '/uploads/', 1, false);
            if (!empty($image)) {
                $Sitemap->addUrl($row['url'], $row['last_modified'], $row['change_frequency'], $row['page_priority']);	
                $Sitemap->addImage($image, $row['caption']);				
            }
        }

        $Sitemap->setUrlsetParams('xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"');
        $Sitemap->build(SITE_ROOT.'static/sitemap/image/', "$site[url].xml", "https://$site[url]/static/sitemap/", false, true);

        $sitemaps = $Sitemap->getSitemaps();        
        echo NL . "IMGSITEMAP OK" . NL;
    }
       
}


echo "OK" . NL;


?>