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

class QBBeanLinks extends FormField {

	static $model_map = array(
		'Accounts'		=> 'Account',
		'Contacts'		=> 'Contact',
		'Invoice'		=> 'Invoice',
		'CreditNotes'	=> 'CreditNote',
		'Quotes'		=> 'Quote',
		'Payments'		=> 'Payment',
		'PaymentsOut'	=> 'Payment',
		'Bills'			=> 'Bill',
		'ProductCatalog'=> 'Product',
		'Discounts'		=> 'Discount',
		'TaxRates'		=> 'TaxRate',
		'Assemblies'	=> 'Asembly',
	);

	function init($params=null, $model=null) {
        parent::init($params, $model);
	}

	function getRequiredFields() {
		switch($this->model->name) {
		case 'QBEntity':
			$fields = array('account_id', 'contact_id', 'qb_type');
			break;
		case 'QBItem':
		case 'QBTally':
			$fields = array('system_type', 'system_id', 'qb_type');
			break;
		default:
			$fields = array('qb_type');
		}
		return $fields;
	}

    function renderListCell(ListFormatter $fmt, ListResult &$result, $row_id) {
		$row = $result->getRowResult($row_id);
		switch($this->model->name) {
		case 'QBEntity':
			$fields = array(
				'account_id' => 'Accounts',
				'contact_id' => 'Contacts',
			);
			break;
		case 'QBItem':
		case 'QBTally':
			$type = $row->getField('system_type');
			if ($type == 'PaymentsOut')
				$type = 'Payments';
			$fields = array('system_id' => $type);
			break;
		default:
			return '';
		}
		$ret = '';
		foreach ($fields as $f => $module) {
			$id = $row->getField($f);
			if (!empty($id)) {
				if ($ret) $ret .= '&nbsp;';
				$model = self::$model_map[$module];
				$ret .= '<a href="index.php?module=' . $module . '&action=DetailView&record=' . $id . '">';
				$ret .= '<div class="theme-icon bean-' . $model . '"></div>';
				$ret .= '</a>';
			}
		}
		if ($ret) $ret .= '&nbsp;';
		$ret .= $row->getField('qb_type', '', true);
		return $ret;
    }
}
