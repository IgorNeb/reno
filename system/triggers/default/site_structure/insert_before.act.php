<?php
// ≈сть ли доступ к родительскому разделу
if (!Auth::structureAccess($this->NEW['structure_id'])) {
	Action::onError(cms_message('CMS', 'Вы не можете создавать страницы в этом разделе'));
}
