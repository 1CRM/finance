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


class QBAccount extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $acct_number;
	var $acct_type;
	var $description;
	var $currency_qb_id;
	var $currency_id;
	var $sublevel;
	var $parent_qb_id;
	
	// - static fields
	
	var $object_name = 'QBAccount';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_accounts';
	
	var $qb_query_type = 'Account';
	var $listview_template = "AccountListView.html";
	var $search_template = "AccountSearchForm.html";
	
	var $qb_field_map = array(
		'AccountType' => 'acct_type',
		'AccountNumber' => 'acct_number',
		'Desc' => 'description',
		'Sublevel' => 'sublevel',
		'ParentRef' => 'parent_qb_id',
		'CurrencyRef' => 'currency_qb_id',
	);
	
	// -- Sync handling
	
	function &get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if($phase == 'Setup' && $stage == 'import') {
			$reqs[] = array(
				'type' => 'import_all',
				'base' => 'Account',
				//'action' => 'import_accounts',
				'optimize' => 'auto',
			);
		}
		return $reqs;
	}
	
	// NOTE: default handler is currently used instead
	function import_accounts($server_id, &$req, &$data, &$newreqs) {
		$rows = array();
		if(! isset($data['AccountRet']))
			return true;
		$rows =& $data['AccountRet'];
		
		$q = "SELECT id, qb_id FROM {$this->table_name} ".
				' WHERE server_id="'. $this->db->quote($server_id).'" '.
				' AND NOT deleted AND qb_is_active';
		$r = $this->db->query($q, true, "Error retrieving QB accounts");
		$all = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$all[$row['qb_id']] = $row['id'];
		}
		
		for($i = 0; $i < count($rows); $i++) {
			$bean = new QBAccount();
			$ok = $bean->handle_import_row($server_id, 'Account', $req, $rows[$i], $newreqs);
			if(! $ok)
				continue;
			if(isset($all[$qb_id]))
				unset($all[$qb_id]);
			$bean->save();
		}
		
		if($req->request_type == 'import_all' && count($all)) {
			$q = "UPDATE {$this->table_name} SET qb_is_active=0, deleted=1 WHERE id IN ('".implode("','", array_values($all))."')";
			$r = $this->db->query($q, true, "Error marking accounts inactive");
		}
		
		return true;
	}
	

	function handle_import_response($server_id, &$req, &$data, &$newreqs) {	
		$ret = parent::handle_import_response($server_id, $req, $data, $newreqs);
		if($ret)
			QBConfig::update_setup_status($req->sync_step, 'ok', $server_id);
	}

	static function load_accounts($server_id, $reload=false) {
		$modkey = 'Accounts';
		$qb_objs = null;
		if(! $reload)
			$qb_objs = qb_get_cache($server_id, $modkey, 'QB');
		if(isset($qb_objs))
			return true;
		
		$bean = new QBAccount();
		$where = ' server_id="'.$bean->db->quote($server_id).'" AND qb_is_active ';
		$all = $bean->get_full_list('', $where);
		$qb_objs = array();
		if($all)
			foreach($all as $bean) {
				$qb_objs[$bean->qb_id] = $bean;
			}
		qb_put_cache($server_id, $modkey, 'QB', $qb_objs);
		
		return true;
	}
	
	static function &get_accounts_by_type($server_id, $type, $reload=false) {
		self::load_accounts($server_id, $reload);
		if(! is_array($type) && $type !== false)
			$type = array($type);
		$ret = array();
		$qb_objs = qb_get_cache($server_id, 'Accounts', 'QB');
		if(is_array($qb_objs)) {
			foreach($qb_objs as $key => $acc) {
				if($type ===false || in_array($acc->acct_type, $type))
					$ret[$key] = $acc;
			}
		}
		return $ret;
	}
	
	function get_option_name() {
		$n = $this->name;
		if(! empty($this->acct_number))
			$n .= ' ('.$this->acct_number.')';
		return $n;
	}

	static function get_expense_accounts($server_id, $add_opts=false) {
		require_once 'modules/BookingCategories/BookingCategory.php';
		$catSeed = new BookingCategory;
		$cats = $catSeed->get_option_list('expenses');
		foreach($cats as $catId => $catName) {
			$accs[$catId]['label'] = $catName;
			$accs[$catId]['qb_id'] = QBConfig::get_server_setting($server_id, 'ExpenseCategories', $catId, '');
			if($add_opts) {
				$opts = self::get_accounts_by_type($server_id, false);
				if($opts) foreach($opts as $k=>$o)
					$opts[$k]->id = $k; // use qb_id for option list
				$accs[$catId]['options'] = $opts;
			}
		}
		return $accs;
	}


	static function expense_category_from_qb_account_id($server_id, $account_id)
	{
		$accounts = self::get_expense_accounts($server_id);
		foreach ($accounts as $iahId => $acc) {
			if ($acc['qb_id'] == $account_id) {
				require_once 'modules/BookingCategories/BookingCategory.php';
				$ret = new BookingCategory;
				$ret->retrieve($iahId);
				return $ret;
			}
		}
		return null;
	}

	static function qb_account_from_expense_id($server_id, $expense_id)
	{
		$accounts = self::get_expense_accounts($server_id);
		foreach ($accounts as $iahId => $acc) {
			if ($iahId == $expense_id) {
				$a = new QBAccount;
				$ret = $a->qb_retrieve($acc['qb_id'], $server_id);
				if ($ret) return $ret;
				break;
			}
		}
		return null;
	}
	
	
	// -- Setup
	
	function setup_template_step(&$cfg, &$tpl, $step)
	{
		if($step != 'ExpenseCategories')
			return false;
		$server_id = QBServer::get_primary_server_id();
		if(! $server_id) {
			$tpl->assign('NO_SERVER', '1');
			return 'no_server';
		}
		$html = '';
		$source = self::get_expense_accounts($server_id, true);
		foreach($source as $an => $acc) {
			$left_opt = array($an => $acc['label']);
			$map = array($an => $acc['qb_id']);
			$html .= qb_match_up_html('account', $left_opt, $acc['options'], $map);
		}
		$tpl->assign('BODY', $html);
	}
	
	function update_setup_config(&$cfg, $step, &$errs)
	{
		if($step != 'ExpenseCategories')
			return false;
		$map = array_get_default($_REQUEST, 'account_map');
		$server_id = QBServer::get_primary_server_id();
		$status = 'ok';
		$source = self::get_expense_accounts($server_id, true);
		$configKey = 'ExpenseCategories';
		foreach($source as $an => $acc) {
			$newval = array_get_default($map, $an, '');
			QBConfig::save_server_setting($server_id, $configKey, $an, $newval);
		}
		
		return $status;
	}

/*	
	function get_default_import_terms($server_id) {
		$def_terms = QBConfig::get_server_setting($server_id, 'Terms', 'DefaultImport', '');
		return $def_terms;
	}
 */
}
