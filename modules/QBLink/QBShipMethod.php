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
require_once('modules/ShippingProviders/ShippingProvider.php');


class QBShipMethod extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $related_id;
	
	// - static fields
	
	var $object_name = 'QBShipMethod';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_shipmethods';
	
	var $qb_query_type = 'ShipMethod';
	var $listview_template = "ShipMethodListView.html";
	var $search_template = "ShipMethodSearchForm.html";
	
	
	// -- Sync handling
	
	function get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if($phase == 'Setup' && $stage == 'import') {
			$reqs[] = array(
				'type' => 'import_all',
				'base' => 'ShipMethod',
				'action' => 'import_methods',
				'optimize' => 'auto',
			);
		}
		return $reqs;
	}
	
	function import_methods($server_id, &$req, &$data, &$newreqs) {
		if(! isset($data['ShipMethodRet']))
			return true;
		$rows =& $data['ShipMethodRet'];
		
		$used = array();
		$q = "SELECT id, qb_id, related_id FROM {$this->table_name} ".
				' WHERE server_id="'. $this->db->quote($server_id).'" '.
				' AND NOT deleted AND qb_is_active';
		$r = $this->db->query($q, true, "Error retrieving QB shipping methods");
		$all = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$all[$row['qb_id']] = $row['id'];
			if($row['related_id'])
				$used[$row['related_id']] = 1;
		}
		
		for($i = 0; $i < count($rows); $i++) {
			$bean = new QBShipMethod();
			$ok = $bean->handle_import_row($server_id, 'ShipMethod', $req, $rows[$i], $newreqs);
			if(! $ok)
				continue;
			if(isset($all[$bean->qb_id]))
				unset($all[$bean->qb_id]);
			$bean->save();
		}
		
		if($req->request_type == 'import_all' && count($all)) {
			$q = "UPDATE {$this->table_name} SET qb_is_active=0, deleted=1 WHERE id IN ('".implode("','", array_values($all))."')";
			$r = $this->db->query($q, true, "Error marking shipping methods inactive");
		}
		
		return true;
	}
	
	static function load_methods($server_id, $reload=false) {
		$modkey = 'ShipMethod';
		$qb_objs = null;
		if(! $reload)
			$qb_objs = qb_get_cache($server_id, $modkey, 'QB');
		if(isset($qb_objs))
			return true;
		
		$bean = new QBShipMethod();
		$iah_vals = self::get_iah_ship_methods();
		$where = ' server_id="'.$bean->db->quote($server_id).'" ';
		$all = $bean->get_full_list('', $where);
		$qb_objs = array();
		$map_qb = array();
		$map_iah = array();
		$none_mapped = true;
		if($all)
			foreach($all as $bean) {
				$qb_objs[$bean->qb_id] = $bean;
				if($bean->related_id) {
					$map_qb[$bean->related_id] = $bean->qb_id;
					$none_mapped = false;
				}
				$map_iah[$bean->qb_id] = $bean->related_id;
			}
		qb_put_cache($server_id, $modkey, 'QB', $qb_objs);
		qb_put_cache($server_id, $modkey, 'IAH', $iah_vals);
		qb_put_cache($server_id, $modkey, 'QBMap', $map_qb);
		qb_put_cache($server_id, $modkey, 'IAHMap', $map_iah);
		qb_put_cache($server_id, $modkey, 'none_mapped', $none_mapped);
		
		return true;
	}
	
	static function &get_qb_method($server_id, $qb_id) {
		self::load_methods($server_id);
		$methods = qb_get_cache($server_id, 'ShipMethod', 'QB');
		$ret = null;
		if(isset($methods[$qb_id]))
			$ret = $methods[$qb_id];
		return $ret;
	}

	static function &get_iah_ship_methods() {
		$bean = new ShippingProvider();
		$all = $bean->get_full_list('', ' status="Active" ');
		$iah_objs = array();
		if($all)
			foreach($all as $bean) {
				$iah_objs[$bean->id] = $bean;
			}
		return $iah_objs;
	}

	static function &from_iah_method($server_id, $iah_id) {
		self::load_methods($server_id);
		$map = qb_get_cache($server_id, 'ShipMethod', 'QBMap');
		$ret = null;
		if(isset($map[$iah_id]))
			$ret = self::get_qb_method($server_id, $map[$iah_id]);
		return $ret;
	}

	static function &to_iah_method($server_id, $qb_id) {
		self::load_methods($server_id);
		$map = qb_get_cache($server_id, 'ShipMethod', 'IAHMap');
		$ret = array_get_default($map, $qb_id, '');
		return $ret;
	}

	
	// -- Setup
	
	function speculative_map($server_id) {
		$qb_methods = qb_get_cache($server_id, 'ShipMethod', 'QB');
		$iah_methods = qb_get_cache($server_id, 'ShipMethod', 'IAH');
		$pre_map = array();
		$map = array();
		foreach($iah_methods as $k => $v) {
			$nm = strtolower($v->name);
			$pre_map[$nm] = $k;
			if($nm == 'fedex')
				$pre_map['federal express'] = $k;
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
		if($step != 'ShipMethods')
			return false;
		$server_id = QBServer::get_primary_server_id();
		if(! $server_id) {
			$tpl->assign('NO_SERVER', '1');
			return 'no_server';
		}
		self::load_methods($server_id, true);
		$qb_methods = qb_get_cache($server_id, 'ShipMethod', 'QB');
		$iah_methods = qb_get_cache($server_id, 'ShipMethod', 'IAH');
		$iah_methods = array('create_new' => $mod_strings['LBL_CFG_MAP_CREATE_NEW']) + $iah_methods;
		if(qb_get_cache($server_id, 'ShipMethod', 'none_mapped'))
			$map = $this->speculative_map($server_id);
		else
			$map = qb_get_cache($server_id, 'ShipMethod', 'IAHMap');
		$html = qb_match_up_html('shipmethods', $qb_methods, $iah_methods, $map);
		if(! $html)
			$html = $mod_strings['LBL_CFG_NO_SHIP_METHODS'];
		else
			$html = update_selects_javascript('shipmethods_') . $html;
		/*$html .= '<hr>';
		$def_method_opts = array('-99' => $mod_strings['LBL_CFG_METHOD_DEFAULT_IMPORT']);
		$def_method = $this->get_default_import_ship_method($server_id);
		$map = array('-99' => $def_method);
		$html .= qb_match_up_html('shipmethods', $def_method_opts, $iah_methods, $map);*/
		$tpl->assign('BODY', $html);
	}
	
	/*function get_default_import_terms($server_id) {
		$def_method = QBConfig::get_server_setting($server_id, 'ShipMethod', 'default_import', '');
		return $def_method;
	}*/
	
	function update_setup_config(&$cfg, $step, &$errs) {
		if($step != 'ShipMethods')
			return false;
		$map = array_get_default($_REQUEST, 'shipmethods_map');
		if(! $map || ! is_array($map)) {
			$errs[] = "No shipping methods mapped";
			return false;
		}
		$server_id = QBServer::get_primary_server_id();
		self::load_methods($server_id, true);
		$qb_methods = qb_get_cache($server_id, 'ShipMethod', 'QB');
		$status = 'ok';
		foreach($qb_methods as $method) {
			$rel_id = array_get_default($map, $method->id, '');
			if($rel_id == 'create_new') {
				$prov = new ShippingProvider();
				$prov->name = $method->name;
				$prov->status = 'Active';
				$rel_id = $prov->save();
			}
			$method->related_id = $rel_id;
			$method->save();
		}
		//if(isset($map['-99']))
		//	QBConfig::save_server_setting($server_id, 'ShipMethod', 'default_import', $map['-99']);
		return $status;
	}
	
}

