<?php

/* 
 * Не даем удалить вопрос Укажите ваш пол.
 */

if ($this->OLD['id'] == 2) {
    Action::onError('Вы не можете удалить вопрос Укажите ваш пол.');
}