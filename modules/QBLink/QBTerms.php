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


class QBTerms extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $iah_value;
	
	// - static fields
	
	var $object_name = 'QBTerms';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_terms';
	
	var $qb_query_type = 'Terms';
	var $listview_template = "TermsListView.html";
	var $search_template = "TermsSearchForm.html";
	
	// -- Sync handling
	
	function get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if($phase == 'Setup' && $stage == 'import') {
			$reqs[] = array(
				'type' => 'import_all',
				'base' => 'Terms',
				'action' => 'import_terms',
				'optimize' => 'auto',
			);
		}
		return $reqs;
	}
	
	function import_terms($server_id, &$req, &$data, &$newreqs) {
		$rows = array();
		if(isset($data['StandardTermsRet']))
			$rows += $data['StandardTermsRet'];
		if(isset($data['DateDrivenTermsRet']))
			$rows += $data['DateDrivenTermsRet'];
		
		$used = array();
		$q = "SELECT id, qb_id, iah_value FROM {$this->table_name} ".
				' WHERE server_id="'. $this->db->quote($server_id).'" '.
				' AND NOT deleted AND qb_is_active';
		$r = $this->db->query($q, true, "Error retrieving QB terms");
		$all = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$all[$row['qb_id']] = $row['id'];
			if($row['iah_value'])
				$used[$row['iah_value']] = 1;
		}
		
		for($i = 0; $i < count($rows); $i++) {
			$bean = new QBTerms();
			$ok = $bean->handle_import_row($server_id, 'Terms', $req, $rows[$i], $newreqs);
			if(! $ok)
				continue;
			if(isset($all[$bean->qb_id]))
				unset($all[$bean->qb_id]);
			/*if(empty($bean->id)) {
				$n = strtolower($bean->name);
				if(isset($map[$n]) && empty($used[$map[$n]])) {
					$bean->iah_value = $map[$n];
					$used[$map[$n]] = 1;
					qb_log_debug("Mapped terms: {$bean->qb_id} -- {$bean->iah_value}");
				}
			}*/
			$bean->save();
		}
		
		if($req->request_type == 'import_all' && count($all)) {
			$q = "UPDATE {$this->table_name} SET qb_is_active=0, deleted=1 WHERE id IN ('".implode("','", array_values($all))."')";
			$r = $this->db->query($q, true, "Error marking terms inactive");
		}

		return true;
	}
	
	static function load_terms($server_id, $reload=false) {
		$modkey = 'Terms';
		$qb_objs = null;
		if(! $reload)
			$qb_objs = qb_get_cache($server_id, $modkey, 'QB');
		if(isset($qb_objs))
			return true;
		
		$bean = new QBTerms();
		$iah_vals = self::get_iah_terms_array();
		$where = ' server_id="'.$bean->db->quote($server_id).'" ';
		$all = $bean->get_full_list('', $where);
		$qb_objs = array();
		$map_qb = array();
		$map_iah = array();
		$none_mapped = true;
		if($all)
			foreach($all as $bean) {
				$qb_objs[$bean->qb_id] = $bean;
				if($bean->iah_value) {
					$map_qb[$bean->iah_value] = $bean->qb_id;
					$none_mapped = false;
				}
				$map_iah[$bean->qb_id] = $bean->iah_value;
			}
		qb_put_cache($server_id, $modkey, 'QB', $qb_objs);
		qb_put_cache($server_id, $modkey, 'IAH', $iah_vals);
		qb_put_cache($server_id, $modkey, 'QBMap', $map_qb);
		qb_put_cache($server_id, $modkey, 'IAHMap', $map_iah);
		qb_put_cache($server_id, $modkey, 'none_mapped', $none_mapped);
		
		return true;
	}

	static function &get_qb_terms($server_id, $qb_id) {
		self::load_terms($server_id);
		$terms = qb_get_cache($server_id, 'Terms', 'QB');
		$ret = null;
		if(isset($terms[$qb_id]))
			$ret = $terms[$qb_id];
		return $ret;
	}

	static function get_iah_terms_array() {
		global $app_list_strings;
		$ret = $app_list_strings['terms_dom'];
		return $ret;
	}

	static function &from_iah_terms($server_id, $iah_id) {
		self::load_terms($server_id);
		$map = qb_get_cache($server_id, 'Terms', 'QBMap');
		$ret = null;
		if(isset($map[$iah_id]))
			$ret = self::get_qb_terms($server_id, $map[$iah_id]);
		return $ret;
	}

	static function &to_iah_terms($server_id, $qb_id) {
		self::load_terms($server_id);
		$map = qb_get_cache($server_id, 'Terms', 'IAHMap');
		$ret = array_get_default($map, $qb_id, '');
		return $ret;
	}

	
	// -- Setup
	
	function speculative_map($server_id) {
		$qb_terms = qb_get_cache($server_id, 'Terms', 'QB');
		$iah_terms = qb_get_cache($server_id, 'Terms', 'IAH');
		$pre_map = array();
		$map = array();
		foreach($iah_terms as $k => $v) {
			$pre_map[strtolower($k)] = $k;
			if(preg_match('/^(.*) Days$/', $k, $m))
				$pre_map[strtolower($m[1])] = $k;
		}
		foreach($qb_terms as $v) {
			$nm = strtolower($v->name);
			if(isset($pre_map[$nm]))
				$map[$v->id] = $pre_map[$nm];
		}
		return $map;
	}
		
	function setup_template_step(&$cfg, &$tpl, $step) {
		global $mod_strings;
		if($step != 'Terms')
			return false;
		$server_id = QBServer::get_primary_server_id();
		if(! $server_id) {
			$tpl->assign('NO_SERVER', '1');
			return 'no_server';
		}
		self::load_terms($server_id, true);
		$qb_terms = qb_get_cache($server_id, 'Terms', 'QB');
		$iah_terms = qb_get_cache($server_id, 'Terms', 'IAH');
		$iah_terms_c = array('create_new' => $mod_strings['LBL_CFG_MAP_CREATE_NEW']) + $iah_terms;
		if(qb_get_cache($server_id, 'Terms', 'none_mapped'))
			$map = $this->speculative_map($server_id);
		else
			$map = qb_get_cache($server_id, 'Terms', 'IAHMap');
		$html = qb_match_up_html('terms', $qb_terms, $iah_terms_c, $map);
		if(! $html)
			$html = $mod_strings['LBL_CFG_NO_TERMS'];
		else
			$html = update_selects_javascript('terms_') . $html;
		$html .= '<hr>';
		$def_terms_opts = array('-99' => $mod_strings['LBL_CFG_TERMS_DEFAULT_IMPORT']);
		$def_terms = $this->get_default_import_terms($server_id);
		$map = array('-99' => $def_terms);
		$html .= qb_match_up_html('def_terms', $def_terms_opts, $iah_terms, $map, false);
		$tpl->assign('BODY', $html);
	}
	
	function get_default_import_terms($server_id) {
		$def_terms = QBConfig::get_server_setting($server_id, 'Terms', 'default_import', '');
		return $def_terms;
	}
	
	function update_setup_config(&$cfg, $step, &$errs) {
		if($step != 'Terms')
			return false;
		$map = array_get_default($_REQUEST, 'terms_map');
		$def_map = array_get_default($_REQUEST, 'def_terms_map');
		if(! $map || ! is_array($map)) {
			$errs[] = "No terms mapped";
			return false;
		}
		$server_id = QBServer::get_primary_server_id();
		self::load_terms($server_id, true);
		$qb_terms = qb_get_cache($server_id, 'Terms', 'QB');
		$status = 'ok';
		$add_terms = array();
		foreach($qb_terms as $term) {
			$iah_val = array_get_default($map, $term->id, '');
			if($iah_val == 'create_new') {
				$iah_val = $term->name;
				$add_terms[$iah_val] = $iah_val;
			}
			$term->iah_value = $iah_val;
			$term->save();
		}
		if($add_terms) {
			$dd_name = 'terms_dom';
			$dd = $GLOBALS['app_list_strings'][$dd_name];
			$dd += $add_terms;
			AppConfig::set_local("lang.lists.base.app.$dd_name", $dd);
			AppConfig::save_local('lang');
		}
		if(isset($def_map['-99']))
			QBConfig::save_server_setting($server_id, 'Terms', 'default_import', $def_map['-99']);
		return $status;
	}
	
}

