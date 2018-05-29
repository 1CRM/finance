<?php
/* * 
 * 
 * Copyright 2004-2012 1CRM Systems Corp.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
*/



require_once 'include/layout/forms/FormField.php';

class QBBeanStatus extends FormField {

	function init($params=null, $model=null) {
        parent::init($params, $model);
	}

	function getRequiredFields() {
		return array('sync_status', 'status_msg');
	}

	function renderListCell(ListFormatter $fmt, ListResult &$result, $row_id) {
		global $app_list_strings;
		$row = $result->getRowResult($row_id);
		$sync_status = $row->getField('sync_status');
		$status_msg = to_html($row->getField('status_msg'));
		$status = array_get_default($app_list_strings['qb_sync_status_dom'], $sync_status, $sync_status);
		$img = '';
		if(! empty($status_msg)) {
			global $image_path;
			$img = '<img src="'.$image_path.'/Notes.gif" border="0" align="absmiddle" width="16" height="16" alt="" title="' . $status_msg . '"/>';
			$img = '<div class="theme-icon bean-Note" title="' . $status_msg . '" style="cursor:pointer"></div>';
		}
		
		if(preg_match('~(_blocked|_error)~', $sync_status))
			$status = '<span class="error">'.$status.'</span>';
		/*
		if(! $this->qb_is_active)
			$row_data['INACTIVE_MSG'] = ' <em>'.$mod_strings['LBL_INACTIVE'].'</em>';
		 */
		return $img . $status;
    }
}
