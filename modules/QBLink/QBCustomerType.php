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


class QBCustomerType extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $iah_value;
	
	// - static fields
	
	var $object_name = 'QBCustomerType';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_customertypes';
	
	var $qb_query_type = 'CustomerType';
	var $listview_template = "CustomerTypeListView.html";
	var $search_template = "CustomerTypeSearchForm.html";
	
	
	// -- Sync handling
	
	function get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if($phase == 'Setup') {
			if($stage == 'import') {
				$reqs[] = array(
					'type' => 'import_all',
					'base' => 'CustomerType',
					'action' => 'import_types',
					'optimize' => 'auto',
				);
			}
			else if($stage == 'export') {
				// $this->register_pending_exports($server_id);  done in Configuration
				$this->add_export_requests($server_id, $reqs, qb_batch_size($server_id, 'Products', 'export'));
			}
		}
		return $reqs;
	}
	
	function register_pending_exports($server_id, $max=null) {
		$sid = $this->db->quote($server_id);
		$query = "SELECT iah_value FROM {$this->table_name} WHERE server_id='$sid' AND NOT deleted";
		$r = $this->db->query($query, true);
		$exported = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$exported[] = $row['iah_value'];
		}
		$vals = self::get_iah_account_types_array();
		foreach($vals as $k => $v) {
			if(! $k || in_array($k, $exported)) continue;
			$ctype = new QBCustomerType();
			$ctype->name = $v;
			$ctype->iah_value = $k;
			$ctype->server_id = $server_id;
			$ctype->save();
		}
	}
	
	function &retrieve_pending_export($server_id, $max_export=-1, $update=false, $qb_type=null) {
		$sid = $this->db->quote($server_id);
		$query = "SELECT id FROM {$this->table_name} WHERE (qb_id='' OR qb_id IS NULL) ".
			"AND server_id='$sid' AND NOT deleted ORDER BY name";
		if($max_export >= 0)
			$query .= " LIMIT $max_export";
		$result = $this->db->query($query, true, "Error retrieving objects pending export");
		$qb_beans = array();
		while($row = $this->db->fetchByAssoc($result)) {
			$seed = new $this->object_name;
			if($seed->retrieve($row['id'], false) !== null) // do not HTML-encode fields
				$qb_beans[] = $seed;
		}
		return $qb_beans;
	}
	
	function &get_export_request(&$req, &$errmsg, $update=false) {
		if($update)
			return false;
		$req = array(
			'base' => 'CustomerType',
			'type' => 'export',
			'params' => array(
				'CustomerTypeAdd' => array(
					'Name' => $this->name,
				),
			),
		);
		return true;
	}
	
	function import_types($server_id, &$req, &$data, &$newreqs) {
		$rows = array();
		if(isset($data['CustomerTypeRet']))
			$rows += $data['CustomerTypeRet'];
		
		$used = array();
		$q = "SELECT id, qb_id, iah_value FROM {$this->table_name} ".
				' WHERE server_id="'. $this->db->quote($server_id).'" '.
				' AND NOT deleted AND qb_is_active';
		$r = $this->db->query($q, true, "Error retrieving QB customer types");
		$all = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$all[$row['qb_id']] = $row['id'];
			if($row['iah_value'])
				$used[$row['iah_value']] = 1;
		}
		
		for($i = 0; $i < count($rows); $i++) {
			$bean = new QBCustomerType();
			$ok = $bean->handle_import_row($server_id, 'CustomerType', $req, $rows[$i], $newreqs);
			if(! $ok)
				continue;
			if(isset($all[$bean->qb_id]))
				unset($all[$bean->qb_id]);
			if(empty($bean->id) && ! empty($_SESSION['exporting_customer_types'])) {
				$n = strtolower($bean->name);
				if(isset($map[$n]) && empty($used[$map[$n]])) {
					$bean->iah_value = $map[$n];
					$used[$map[$n]] = 1;
					qb_log_debug("Mapped customer types: {$bean->qb_id} -- {$bean->iah_value}");
				}
			}
			$bean->save();
		}
		
		if(0 && empty($_SESSION['exporting_customer_types'])) {
			if($req->request_type == 'import_all' && count($all)) {
				$q = "UPDATE {$this->table_name} SET qb_is_active=0, deleted=1 WHERE id IN ('".implode("','", array_values($all))."')";
				$r = $this->db->query($q, true, "Error marking customer types inactive");
			}
		}

		return true;
	}
	
	static function load_types($server_id, $reload=false) {
		$modkey = 'CustomerTypes';
		$qb_objs = null;
		if(! $reload)
			$qb_objs = qb_get_cache($server_id, $modkey, 'QB');
		if(isset($qb_objs))
			return true;
		
		$bean = new QBCustomerType();
		$iah_vals = self::get_iah_account_types_array();
		$where = ' server_id="'.$bean->db->quote($server_id).'" ';
		$all = $bean->get_full_list('', $where);
		$qb_objs = array();
		$qb_by_id = array();
		$map_qb = array();
		$map_iah = array();
		$map_iah_id = array();
		$none_mapped = true;
		if($all)
			foreach($all as $bean) {
				$qb_objs[$bean->qb_id] = $bean;
				$qb_by_id[$bean->id] = $bean;
				if($bean->iah_value) {
					$map_qb[$bean->iah_value] = $bean->qb_id;
					$none_mapped = false;
				}
				$map_iah[$bean->qb_id] = $bean->iah_value;
				$map_iah_id[$bean->id] = $bean->iah_value;
			}
		qb_put_cache($server_id, $modkey, 'QB', $qb_objs);
		qb_put_cache($server_id, $modkey, 'QB_Id', $qb_by_id);
		qb_put_cache($server_id, $modkey, 'IAH', $iah_vals);
		qb_put_cache($server_id, $modkey, 'QBMap', $map_qb);
		qb_put_cache($server_id, $modkey, 'IAHMap', $map_iah);
		qb_put_cache($server_id, $modkey, 'IAHMap_Id', $map_iah_id);
		qb_put_cache($server_id, $modkey, 'none_mapped', $none_mapped);
		
		return true;
	}
	
	static function &get_qb_customer_types($server_id, $qb_id) {
		self::load_types($server_id);
		$types = qb_get_cache($server_id, 'CustomerTypes', 'QB');
		$ret = null;
		if(isset($types[$qb_id]))
			$ret = $types[$qb_id];
		return $ret;
	}

	static function get_iah_account_types_array() {
		global $app_list_strings;
		$ret = $app_list_strings['account_type_dom'];
		return $ret;
	}

	static function &from_iah_type($server_id, $iah_id) {
		self::load_types($server_id);
		$map = qb_get_cache($server_id, 'CustomerTypes', 'QBMap');
		$ret = null;
		if(isset($map[$iah_id]))
			$ret = self::get_qb_customer_types($server_id, $map[$iah_id]);
		return $ret;
	}
	
	static function &to_iah_type($server_id, $qb_id) {
		self::load_types($server_id);
		$map = qb_get_cache($server_id, 'CustomerTypes', 'IAHMap');
		$ret = array_get_default($map, $qb_id, '');
		return $ret;
	}

	
	// -- Setup
	
	function speculative_map($server_id) {
		$qb_types = qb_get_cache($server_id, 'CustomerTypes', 'QB');
		$iah_types = qb_get_cache($server_id, 'CustomerTypes', 'IAH');
		$pre_map = array();
		$map = array();
		foreach($iah_types as $k => $v) {
			$pre_map[strtolower($k)] = $k;
		}
		foreach($qb_types as $v) {
			$nm = strtolower($v->name);
			if(isset($pre_map[$nm]))
				$map[$v->id] = $pre_map[$nm];
		}
		return $map;
	}
		
	function setup_template_step(&$cfg, &$tpl, $step) {
		global $mod_strings;
		if($step != 'CustomerTypes')
			return false;
		$server_id = QBServer::get_primary_server_id();
		if(! $server_id) {
			$tpl->assign('NO_SERVER', '1');
			return 'no_server';
		}
		self::load_types($server_id, true);
		$qb_types = qb_get_cache($server_id, 'CustomerTypes', 'QB_Id');
		$iah_types = qb_get_cache($server_id, 'CustomerTypes', 'IAH');
		$iah_types_c = array('create_new' => $mod_strings['LBL_CFG_MAP_CREATE_NEW']) + $iah_types;
		if(qb_get_cache($server_id, 'CustomerTypes', 'none_mapped'))
			$map = $this->speculative_map($server_id);
		else
			$map = qb_get_cache($server_id, 'CustomerTypes', 'IAHMap_Id');
		$html = qb_match_up_html('customer_type', $qb_types, $iah_types_c, $map);
		if(! $html) {
			$html = '<p>'.$mod_strings['LBL_CFG_NO_CUSTOMER_TYPES'].'</p>';
			$html .= '<p>'.$mod_strings['LBL_CFG_EXPORT_ALL_ACCOUNT_TYPES'].'</p>';
			$html .= "<input title='' class='button' type='submit'
					onclick=\"this.form.save_step.value='1';\"
					value=\"{$mod_strings['LBL_CFG_EXPORT_ACCOUNT_TYPES_BUTTON']}\"
					id='export_all_types' name='export_all_types' />";
		}
		else {
			$html = update_selects_javascript('customer_type_') . $html;
		}
		$tpl->assign('BODY', $html);
	}
		
	function update_setup_config(&$cfg, $step, &$errs) {
		if($step != 'CustomerTypes')
			return false;
		$map = array_get_default($_REQUEST, 'customer_type_map');
		/*if(! $map || ! is_array($map)) {
			$errs[] = "No customer types mapped";
			return false;
		}*/
		$server_id = QBServer::get_primary_server_id();
		self::load_types($server_id, true);
		$qb_types = qb_get_cache($server_id, 'CustomerTypes', 'QB_Id');
		$status = 'ok';
		$add_types = array();
		foreach($qb_types as $type) {
			$iah_val = array_get_default($map, $type->id, '');
			if($iah_val == 'create_new') {
				$iah_val = $type->name;
				$add_types[$iah_val] = $iah_val;
			}
			$type->iah_value = $iah_val;
			$type->save();
		}
		if($add_types) {
			$dd_name = 'account_type_dom';
			$dd = $GLOBALS['app_list_strings'][$dd_name];
			$dd += $add_types;
			AppConfig::set_local("lang.lists.base.app.$dd_name", $dd);
			AppConfig::save_local('lang');
		}
		$ex_all = array_get_default($_REQUEST, 'export_all_types');
		if($ex_all) {
			
			$this->register_pending_exports($server_id);
		}
		return $status;
	}
	
}

