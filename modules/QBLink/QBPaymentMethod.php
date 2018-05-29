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

if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point'); 


require_once('modules/QBLink/QBBean.php');
require_once('modules/QBLink/QBServer.php');


class QBPaymentMethod extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $iah_value;
	var $type;
	
	// - static fields
	
	var $object_name = 'QBPaymentMethod';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_paymentmethods';
	
	var $qb_query_type = 'PaymentMethod';
	var $listview_template = "PaymentMethodListView.html";
	var $search_template = "PaymentMethodSearchForm.html";
	
	var $ignore_editseq = true;
	
	var $qb_field_map = array(
		'PaymentMethodType' => 'type',
	);
	
	// -- Sync handling
	
	function get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if($phase == 'Setup' && $stage == 'import') {
			$reqs[] = array(
				'type' => 'import_all',
				'base' => 'PaymentMethod',
				'action' => 'import_methods',
				'optimize' => 'auto',
			);
		}
		return $reqs;
	}
	
	function import_methods($server_id, &$req, &$data, &$newreqs) {
		if(! isset($data['PaymentMethodRet']))
			return true;
		$rows =& $data['PaymentMethodRet'];
				
		$used = array();
		$q = "SELECT id, qb_id, iah_value FROM {$this->table_name} ".
				' WHERE server_id="'. $this->db->quote($server_id).'" '.
				' AND NOT deleted AND qb_is_active';
		$r = $this->db->query($q, true, "Error retrieving QB payment methods");
		$all = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$all[$row['qb_id']] = $row['id'];
			if($row['iah_value'])
				$used[$row['iah_value']] = 1;
		}
		
		for($i = 0; $i < count($rows); $i++) {
			$bean = new QBPaymentMethod();
			$ok = $bean->handle_import_row($server_id, 'PaymentMethod', $req, $rows[$i], $newreqs);
			if(! $ok)
				continue;
			if(isset($all[$bean->qb_id]))
				unset($all[$bean->qb_id]);
			$bean->save();
		}
		
		if($req->request_type == 'import_all' && count($all)) {
			$q = "UPDATE {$this->table_name} SET qb_is_active=0, deleted=1 WHERE id IN ('".implode("','", array_values($all))."')";
			$r = $this->db->query($q, true, "Error marking payment methods inactive");
		}
		return true;
	}
	
	static function load_methods($server_id, $reload=false) {
		$modkey = 'PaymentMethod';
		$qb_objs = null;
		if(! $reload)
			$qb_objs = qb_get_cache($server_id, $modkey, 'QB');
		if(isset($qb_objs))
			return true;
		
		$bean = new QBPaymentMethod();
		$where = ' server_id="'.$bean->db->quote($server_id).'" ';
		$all = $bean->get_full_list('', $where);
		$qb_objs = array();
		$iah_vals = self::get_iah_payment_methods();
		$map_qb = array();
		$map_iah = array();

		$qb_ids_by_type = array();
		$iah_ids_by_type = array();

		$none_mapped = true;
		if($all) {
			foreach($all as $bean) {
				$qb_objs[$bean->qb_id] = $bean;
				if($bean->iah_value) {
					$map_qb[$bean->iah_value] = $bean->qb_id;	
					$none_mapped = false;
					$iah_ids_by_type[$bean->type][] = $bean->iah_value;
				}
				$map_iah[$bean->qb_id] = $bean->iah_value;
				$qb_ids_by_type[$bean->type][] = $bean->qb_id;
			}
		}
		qb_put_cache($server_id, $modkey, 'QB', $qb_objs);
		qb_put_cache($server_id, $modkey, 'IAH', $iah_vals);
		qb_put_cache($server_id, $modkey, 'QBMap', $map_qb);
		qb_put_cache($server_id, $modkey, 'IAHMap', $map_iah);
		qb_put_cache($server_id, $modkey, 'none_mapped', $none_mapped);
		qb_put_cache($server_id, $modkey, 'qb_ids_by_type', $qb_ids_by_type);
		qb_put_cache($server_id, $modkey, 'iah_ids_by_type', $iah_ids_by_type);
		
		return true;
	}
	
	static function &get_qb_method($server_id, $qb_id) {
		self::load_methods($server_id);
		$methods = qb_get_cache($server_id, 'PaymentMethod', 'QB');
		$ret = null;
		if(isset($methods[$qb_id]))
			$ret = $methods[$qb_id];
		return $ret;
	}

	static function &get_iah_payment_methods() {
		global $app_list_strings;
		$ret = $app_list_strings['payment_type_dom'];
		if(isset($ret[''])) unset($ret['']);
		return $ret;
	}

	static function &from_iah_method($server_id, $iah_id) {
		self::load_methods($server_id);
		$map = qb_get_cache($server_id, 'PaymentMethod', 'QBMap');
		$ret = null;
		if(isset($map[$iah_id]))
			$ret = self::get_qb_method($server_id, $map[$iah_id]);
		return $ret;
	}

	static function &to_iah_method($server_id, $qb_id) {
		self::load_methods($server_id);
		$map = qb_get_cache($server_id, 'PaymentMethod', 'IAHMap');
		$ret = array_get_default($map, $qb_id, '');
		return $ret;
	}

	
	// -- Setup
	
	function speculative_map($server_id) {
		$qb_methods = qb_get_cache($server_id, 'PaymentMethod', 'QB');
		$iah_methods = qb_get_cache($server_id, 'PaymentMethod', 'IAH');
		$pre_map = array();
		$map = array();
		foreach($iah_methods as $k => $v) {
			$pre_map[strtolower($k)] = $k;
		}
		foreach($qb_methods as $v) {
			$nm = strtolower($v->name);
			if(isset($pre_map[$nm]))
				$map[$v->id] = $pre_map[$nm];
		}
		return $map;
	}
		
	function setup_template_step(&$cfg, &$tpl, $step) {
		global $mod_strings;
		if($step != 'PaymentMethods')
			return false;
		$server_id = QBServer::get_primary_server_id();
		if(! $server_id) {
			$tpl->assign('NO_SERVER', '1');
			return 'no_server';
		}
		self::load_methods($server_id, true);
		$qb_methods = qb_get_cache($server_id, 'PaymentMethod', 'QB');
		$iah_methods = qb_get_cache($server_id, 'PaymentMethod', 'IAH');
		$iah_methods2 = array('create_new' => $mod_strings['LBL_CFG_MAP_CREATE_NEW']) + $iah_methods;
		if(qb_get_cache($server_id, 'PaymentMethod', 'none_mapped'))
			$map = $this->speculative_map($server_id);
		else
			$map = qb_get_cache($server_id, 'PaymentMethod', 'IAHMap');
		$html = qb_match_up_html('paymentmethods', $qb_methods, $iah_methods2, $map);
		if(! $html)
			$html = $mod_strings['LBL_CFG_NO_PAYMENT_METHODS'];
		else
			$html = update_selects_javascript('paymentmethods_') . $html;
		$html .= '<hr>';
		$def_method_opts = array('-99' => $mod_strings['LBL_CFG_PAYMENTMETHODS_DEFAULT_IMPORT']);
		$def_method = $this->get_default_import_method($server_id);
		$map = array('-99' => $def_method);
		$iah_methods3 = array('' => $mod_strings['LBL_DD_REQUIRED']);
		if(! $def_method && ! isset($iah_methods['Unspecified']))
			$iah_methods3 += array('create_unspec' => $mod_strings['LBL_CFG_MAP_CREATE_UNSPEC']);
		$iah_methods3 += $iah_methods;
		$html .= qb_match_up_html('def_method', $def_method_opts, $iah_methods3, $map, false);
		$tpl->assign('BODY', $html);
	}
	
	function get_default_import_method($server_id) {
		$def_method = QBConfig::get_server_setting($server_id, 'PaymentMethod', 'default_import', '');
		return $def_method;
	}
	
	function update_setup_config(&$cfg, $step, &$errs) {
		global $mod_strings;
		if($step != 'PaymentMethods')
			return false;
		$map = array_get_default($_REQUEST, 'paymentmethods_map');
		$def_map = array_get_default($_REQUEST, 'def_method_map');
		if(! $map || ! is_array($map)) {
			$errs[] = "No payment methods mapped";
			return false;
		}
		$server_id = QBServer::get_primary_server_id();
		self::load_methods($server_id, true);
		$qb_methods = qb_get_cache($server_id, 'PaymentMethod', 'QB');
		$status = 'ok';
		$add_methods = array();
		foreach($qb_methods as $method) {
			$iah_val = array_get_default($map, $method->id, '');
			if($iah_val == 'create_new') {
				$iah_val = $method->name;
				$add_methods[$iah_val] = $iah_val;
			}
			$method->iah_value = $iah_val;
			$method->save();
		}
		if(isset($def_map['-99']) && $def_map['-99'] == 'create_unspec') {
			$add_methods['Unspecified'] = $mod_strings['LBL_DD_UNSPECIFIED'];
			$def_map['-99'] = 'Unspecified';
		}
		if($add_methods) {
			$dd_name = 'payment_type_dom';
			$dd = $GLOBALS['app_list_strings'][$dd_name];
			$dd += $add_methods;
			AppConfig::set_local("lang.lists.base.app.$dd_name", $dd);
			AppConfig::save_local('lang');
		}
		if(isset($def_map['-99']))
			QBConfig::save_server_setting($server_id, 'PaymentMethod', 'default_import', $def_map['-99']);
		return $status;
	}

	static function get_methods_by_type($server_id, $types, $qb = false)
	{
		if (!is_array($types)) {
			$types = array($types);
		}
		self::load_methods($server_id);
		if ($qb) {
			$ids = qb_get_cache($server_id, 'PaymentMethod', 'qb_ids_by_type');
		} else {
			$ids = qb_get_cache($server_id, 'PaymentMethod', 'iah_ids_by_type');
		}
		$ret = array();
		foreach ($ids as $t => $values) {
			if(in_array($t, $types)) {
				$ret += $values;
			}
		}

		// before 7.0
		if (empty($ret) && isset($ids[''])) {
			$ret = $types;
		}
		return $ret;
	}

	static function get_credit_card_methods($server_id, $qb = false)
	{
		$types = array(
			'AmericanExpress', 'Discover', 'MasterCard', 'OtherCreditCard', 'Visa',
		);
		return self::get_methods_by_type($server_id, $types, $qb);
	}

	static function get_check_methods($server_id, $qb = false)
	{
		$types = array(
			'Check',
			'Cheque',
		);
		return self::get_methods_by_type($server_id, $types, $qb);
	}


	static function is_check_method($server_id, $id, $qb = false)
	{
		$methods = self::get_check_methods($server_id, $qb);
		return in_array($id, $methods);
	}

	static function is_credit_card_method($server_id, $id, $qb = false)
	{
		$methods = self::get_credit_card_methods($server_id, $qb);
		return in_array($id, $methods);
	}
}

