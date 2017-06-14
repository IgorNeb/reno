<?php

/**
 * Генерация шаблонов
 * @package Pilot
 * @subpackage Seo
 * @author Naumenko A.
 * @copyright c-format, 2014
 */

$id = globalVar($_GET['id'], 0);

Seo::generateMetaTag($id);

