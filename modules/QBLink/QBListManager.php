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



require_once('include/ListView/ListViewManager.php');

class QBListManager extends ListViewManager {


    function __construct($type) {
        parent::__construct('listview', true);
        $this->show_create_button = false;
		$this->addHiddenFields(array('list_type' => $type));
		$this->loadRequest();
		if(! $this->initModuleView('QBLink', 'QB' . $type, null, null, null, 'QB' . $type))
			ACLController::displayNoAccess();
		$fmt = $this->getFormatter();
		$mu = $fmt->getMassUpdate();
		$mu->addHandler(
			array(
				'name' => 'QBListManager',
				'class' => 'QBListManager',
				'file' => 'modules/QBLink/QBListManager.php',
			),
			'action'
		);
	}
	
	function showMassUpdate() {
		return true;
	}

	function listupdate_perform($mu, $perform, $ids)
	{
		switch($mu->request['list_type']) {
			case 'Item':
				require_once('modules/QBLink/QBItem.php');
				$seed = new QBItem();
				break;
			case 'Tally':
				require_once('modules/QBLink/QBTally.php');
				$seed = new QBTally();
				break;
			case 'Account':
				require_once('modules/QBLink/QBAccount.php');
				$seed = new QBAccount();
				break;
			case 'Entity':
			default:
				require_once('modules/QBLink/QBEntity.php');
				$seed = new QBEntity();
		}

		$server_id = QBServer::get_primary_server_id();
		if ($perform == 'RegisterLocal') {
			if ($server_id) {
				$seed->register_local_objects($server_id);
			}
			return;
		}

		if(!empty($ids)) {
			switch ($perform) {
				case 'Delete':
					$seed->qb_mass_change_status($ids, 'pending_delete');
					break;
				case 'EnableSync':
					$seed->qb_mass_change_status($ids, '');
					break;
				case 'DisableSync':
					$seed->qb_mass_change_status($ids, 'disabled');
					break;
				case 'ForceUpdate':
					$seed->qb_mass_change_status($ids, 'pending_update');
					break;
				case 'ReRegister':
					$seed->qb_mass_re_register($ids);
					break;
				case 'ReImport':
					$seed->reset_all_qb_time($server_id, $ids);
					break;
			}
			return;
		}

	}

	function getButtons()
	{
		$buttons = array();
		$buttons['register_local'] = array(
			'label' => translate('LBL_REGISTER_BUTTON_LABEL'),
			'params' => array(
				'perform' => "sListView.sendMassUpdate('".$this->list_id."', 'RegisterLocal', false, null, true);",
			),
		);
		$buttons['enable'] = array(
			'label' => translate('LBL_ENABLE_SYNC_BUTTON_LABEL'),
			'params' => array(
				'perform' => "sListView.sendMassUpdate('".$this->list_id."', 'EnableSync', false, null, false);",
			),
		);
		$buttons['disable'] = array(
			'label' => translate('LBL_DISABLE_SYNC_BUTTON_LABEL'),
			'params' => array(
				'perform' => "sListView.sendMassUpdate('".$this->list_id."', 'DisableSync', false, null, false);",
			),
		);
		$buttons['force_update'] = array(
			'label' => translate('LBL_FORCE_UPDATE_BUTTON_LABEL'),
			'params' => array(
				'perform' => "sListView.sendMassUpdate('".$this->list_id."', 'ForceUpdate', false, null, false);",
			),
		);
		$buttons['re_register'] = array(
			'label' => translate('LBL_RE_REGISTER_BUTTON_LABEL'),
			'params' => array(
				'perform' => "sListView.sendMassUpdate('".$this->list_id."', 'ReRegister', false, null,  false);",
			),
		);
		$buttons['re_import'] = array(
			'label' => translate('LBL_RESET_DATES_BUTTON_LABEL'),
			'params' => array(
				'perform' => "sListView.sendMassUpdate('".$this->list_id."', 'ReImport', false, null, false);",
			),
		);

		return $buttons;
	}

}
?>
