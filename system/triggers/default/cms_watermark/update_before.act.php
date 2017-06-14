<?php
/**
 * Событие, которое возникает после изменения водяного знака
 * @package Pilot
 * @subpackage CMS
 * @author Rudenko Ilya <rudenko@delta-x.ua>
 * @copyright Delta-X, ltd. 2010
 */

if ($this->NEW['transparency'] == 100) {
	Action::onError(cms_message('CMS', 'Нельзя назначать водяному знаку 100% прозрачность. Для того, что б убрать водяной знак отключите его наложение.'));
}

?>