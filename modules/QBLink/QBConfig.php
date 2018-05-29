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


require_once('modules/QBLink/QBServer.php');
require_once('modules/QBLink/qb_utils.php');

class QBConfig extends SugarBean {

	var $loaded = array();
	var $settings = array();

	var $table_name = 'qb_config';
	var $module_dir = 'QBLink';
	var $object_name = 'QBConfig';
	
	static $default_setup_mgr;
	static $primary_srv_set;
	static $primary_srv_id;
	
	var $sync_opts = array(
		'Server' => array(
			'allow_sync',
		),
		'Import' => array(
			'Products',
			'Customers',
			'Vendors',
			'Estimates',
			'Invoices',
			'Bills',
		),
		'Export' => array(
			'Products',
			'Customers',
			'Vendors',
			'OnlyInvoicedAccounts',
			'OnlyBilledAccounts',
			'Quotes',
			'Invoices',
			'Bills',
		),
		'Batch' => array(
			'Invoices/import',
			'Invoices/export',
			'Payments/import',
			'Payments/export',
			'Accounts/import',
			'Accounts/export',
			'Products/import',
			'Products/export',
		),

	);
	
	function clear_config() {
		qb_reset_cfg_cache();
	}

	static function load_config($server_id='', $cat = null) {
		global $db;
		$loaded = qb_cfg_loaded('', $cat);
		$query = "SELECT cfg.* FROM qb_config cfg";
		if(isset($server_id)) {
			$query .= sprintf(" WHERE cfg.server_id='%s'", $db->quote($server_id));
			if(isset($cat)) {
				$query .= sprintf(" AND cfg.category='%s'", $db->quote($cat));
				qb_reset_cfg_cache($server_id, $cat);
			}
			qb_reset_cfg_cache($server_id);
		}
		$r = $db->query($query, true);
		while($row = $db->fetchByAssoc($r, -1, false)) {
			qb_put_cfg_cache($row['server_id'], $row['category'], $row['name'], $row['value']);
		}
		if(isset($server_id))
			return qb_get_cache_group($server_id, $cat);
		return true;
	}
	
	static function get_setting($cat, $name, $default=null) {
		return self::get_server_setting('', $cat, $name, $default);
	}
	
	static function put_setting($cat, $name, $value) {
		return self::put_server_setting('', $cat, $name, $value);
	}
	
	static function get_server_setting($server_id, $cat, $name, $default=null) {
		if(! qb_cfg_loaded($server_id, $cat))
			self::load_config($server_id, $cat);
		$ret = qb_get_cfg_cache($server_id, $cat, $name);
		if(! isset($ret))
			return $default;
		return $ret;
	}
	
	static function put_server_setting($server_id, $cat, $name, $value) {
		qb_put_cfg_cache($server_id, $cat, $name, $value);
	}
	
	static function save_settings(&$settings) {
		global $db;
		$qs = array();
		foreach($settings as $svr_id => $catvals) {
			foreach($catvals as $cat => $vals) {
				foreach($vals as $name => $val) {
					$old = self::get_server_setting($svr_id, $cat, $name);
					if($val === null)
						$qs[] = sprintf("DELETE FROM qb_config WHERE server_id='%s' AND category='%s' and name='%s'",
							$db->quote($svr_id), $db->quote($cat), $db->quote($name));
					else if($old === null) {
						$id = create_guid();
						$qs[] = sprintf("INSERT INTO qb_config SET id='%s', server_id='%s', category='%s', name='%s', value='%s'",
							$id, $db->quote($svr_id), $db->quote($cat), $db->quote($name), $db->quote($val));
					}
					else
						$qs[] = sprintf("UPDATE qb_config SET value='%s' WHERE server_id='%s' AND category='%s' AND name='%s'",
							$db->quote($val), $db->quote($svr_id), $db->quote($cat), $db->quote($name));
					self::put_server_setting($svr_id, $cat, $name, $val);
				}
			}
		}
		foreach($qs as $q)
			$db->query($q, true, "Error updating QBLink config");
	}
	
	static function save_setting($cat, $name, $value, $reset=false) {
		return self::save_server_setting('', $cat, $name, $value, $reset);
	}
	
	static function save_server_setting($server_id, $cat, $name, $value, $reset=false) {
		$arr = array($server_id => array($cat => array($name => $value)));
		return self::save_settings($arr);
	}
	
	static function update_setup_status($step, $status, $srv_id=null) {
		$primary = QBServer::get_primary_server_id();
		$set_primary = ($srv_id !== null) ? $srv_id : (($step == 'Basic') ? '' : $primary);
		self::save_server_setting($set_primary, $step, 'setup_status', $status);
		$steps = self::get_setup_steps(true);
		$enabled = true;
		foreach($steps as $k=>$step) {
			$status = self::get_setup_status($k);
			if($status != 'ok' /* && (! empty($status) || ! isset($step['config']) || ! $step['config'])*/) {
				$enabled = false;
				qb_log_info("$k ($status)");
			}
		}
		if($primary) {
			if($enabled &&! self::get_server_setting($primary, 'Server', 'allow_sync'))
				$enabled = false;
			QBServer::set_sync_enabled($enabled, $primary);
		}
	}
	
	static function get_setup_status($step) {
		if(empty(self::$primary_srv_set)) {
			self::$primary_srv_id = QBServer::get_primary_server_id();
			self::$primary_srv_set = true;
		}
		$primary = self::$primary_srv_id;
		if($step == 'Basic') $primary = '';
		return self::get_server_setting($primary, $step, 'setup_status', '');
	}
	
	static function get_sync_phases($reload=false) {
		$sync_phases = qb_get_cache('', 'Setup', 'sync_phases');
		if(! isset($sync_phases) || $reload) {
			$sync_phases = array();
			include('modules/QBLink/sync_steps.php');
			qb_put_cache('', 'Setup', 'sync_phases', $sync_phases);
			if(isset($sync_phases['Setup']))
				self::$default_setup_mgr = array_get_default($sync_phases['Setup'], 'mgr', 'QBConfig');
		}
		return $sync_phases;
	}
	
	static function get_setup_steps($reload=false) {
		$sync_phases = self::get_sync_phases($reload);
		return $sync_phases['Setup']['steps'];
	}
	
	function step_status_color($status) {
		if(substr($status, 0, 3) == 'no_')
			return 'red';
		switch($status) {
		case 'ok':
			return 'green';
		case 'semi':
			return 'yellow';
		case 'error':
			return 'red';
		default:
			return 'grey';
		}
	}
	
	function init_setup_step($current='') {
		$errs = array();
		$all_status = 'ok';
		$first = $second = $back = $next = $last = '';
		$setnext = false;
		$setup_steps = $this->get_setup_steps();
		
		foreach($setup_steps as $key => $step) {
			$status = self::get_setup_status($key);
			if($all_status == 'error')
				$status = '';			

			$setup_steps[$key]['status'] = $status;
			$setup_steps[$key]['color'] = $this->step_status_color($status);
			$setup_steps[$key]['name'] = translate($step['label'], $this->module_dir);
			
			if(isset($step['config']) && ! $step['config'])
				continue;

			if(empty($first))
				$first = $key;
			else if(empty($second))
				$second = $key;
			if($setnext) {
				$next = $key;
				$setnext = false;
			}
			if(! $current && ($status == 'semi' || $status == 'error')) {
				$current = $key;
			}
			if($current == $key) {
				$back = $last;
				$setnext = true;
			}
			$last = $key;
		}
		if(! $current) {
			$current = $first;
			$next = $second;
		}
		$this->current_step = $current;
		$this->nav = compact('first', 'back', 'current', 'next', 'last');
		$this->steps = $setup_steps;
		if(count($errs)) {
			$this->errors = $errs;
			return false;
		}
		return true;
	}
	
	function get_step_name() {
		return $this->steps[$this->current_step]['name'];
	}
	
	function save_setup_step($step) {
		$setup_steps = $this->get_setup_steps();
		if(! isset($setup_steps[$step])) {
			$this->errors[] = 'Unknown setup step: '.$step;
			return false;
		}
		if(! $this->load_setup_bean($step, $mgr_obj, $errs)) {
			$this->errors[] = 'Error loading setup bean';
			return false;
		}
		if(! method_exists($mgr_obj, 'update_setup_config')) {
			$this->errors[] = "Class ".get_class($mgr_obj)." does not define update_setup_config()";
			return false;
		}
		$errs = array();
		$new_status = $mgr_obj->update_setup_config($this, $step, $errs);
		if($new_status != 'ok')
			$this->errors = $errs;
		self::update_setup_status($step, $new_status);
		return $new_status;
	}
		
	function load_handler($handler, &$bean, &$errs) {
		$objfile = "modules/QBLink/$handler.php";
		if(! file_exists($objfile)) {
			$errs[] = "File does not exist: $objfile";
			return false;
		}
		require_once($objfile);
		if(! class_exists($handler)) {
			$errs[] = "Class is not defined: $handler";
			return false;
		}
		$bean = new $handler();
		return true;
	}
	
	function load_setup_bean($step, &$mgr_obj, &$errs) {
		$steps = $this->get_setup_steps();
		$mgr = array_get_default($steps[$step], 'mgr', self::$default_setup_mgr);
		return $this->load_handler($mgr, $mgr_obj, $errs);
	}
	
	function setup_template(&$tpl) {
		$key = $this->current_step;
		$errs = array();
		for($i=0; $i<1; $i++) {
			if(! $this->load_setup_bean($key, $mgr_obj, $errs))
				continue;
			$old_status = $this->steps[$key]['status'];
			/*if(! method_exists($mgr_obj, 'check_setup_config')) {
				$errs[] = "Class ".get_class($mgr_obj)." does not define check_setup_config()";
				continue;
			}
			$new_status = $mgr_obj->check_setup_config($key);
			if(! $new_status)
				$errs[] = "Status for step '$key' could not be determined";*/
			$new_status = $old_status;
			$this->steps[$key]['status'] = $new_status;
			$this->steps[$key]['color'] = $this->step_status_color($new_status);
			if($old_status != $new_status) {
				$this->update_setup_status($key, $new_status);
			}
			if(method_exists($mgr_obj, 'setup_template_step')) {
				$mgr_obj->setup_template_step($this, $tpl, $key);
			}
		}
		$stps = $this->steps;
		foreach($stps as $k=>$s)
			if(isset($s['config']) && ! $s['config'])
				unset($stps[$k]);
		$tpl->assign('NAV', $this->nav);
		$tpl->assign('STEPS', $stps);
		if(count($errs)) {
			$this->errors = $errs;
			return false;
		}
		return true;
	}
	
	function load_request_handler(&$req, &$mgr_obj, &$errs) {
		$phases = $this->get_sync_phases();
		if(! isset($phases[$req->sync_phase]))
			return false;
		$ph = $phases[$req->sync_phase];
		$mgr = $ph['mgr'];
		if(isset($ph['steps']) && isset($ph['steps'][$req->sync_step]))
			$mgr = array_get_default($ph['steps'][$req->sync_step], 'mgr', $mgr);
		return $this->load_handler($mgr, $mgr_obj, $errs);
	}
	
	function get_pending_requests($server_id, $stage, $step) {
		$reqs = array();
		// no requests needed for Basic or Sync_Info
		return $reqs;
	}
	
	function setup_template_step(&$cfg, &$tpl, $step) {
		global $mod_strings;
		if($step == 'Basic') {
			$srv = new QBServer();
			if($srv->retrieve_primary()) {
				$tpl->assign('PRIM_SERV', $srv->get_display_params());
				if(self::get_setting('Server', 'limit_filename', 0))
					$tpl->assign('LIMIT_FILENAME', 1);
			}
			//else {
				$last_id = $srv->get_last_connect_server_id();
				if($last_id && $last_id != $srv->id && $srv->retrieve($last_id))
					$tpl->assign('NEW_SERV', $srv->get_display_params());
			//}
			$tpl->assign('PRIM_SERVER_INFO_TITLE',
				get_form_header($mod_strings['LBL_CFG_SERVER_INFO_TITLE'], '', false));
			$tpl->assign('ALT_SERVER_INFO_TITLE',
				get_form_header($mod_strings['LBL_CFG_ALT_SERVER_INFO_TITLE'], '', false));
			return true;
		}
		else if($step == 'Sync_Opts') {
			$server_id = QBServer::get_primary_server_id();
			if(! $server_id) {
				$tpl->assign('NO_SERVER', '1');
				return 'no_server';
			}
			$tpl->assign('IMPORT_TITLE',
				get_form_header($mod_strings['LBL_CFG_IMPORT_TITLE'], '', false));
			$tpl->assign('EXPORT_TITLE',
				get_form_header($mod_strings['LBL_CFG_EXPORT_TITLE'], '', false));
			$tpl->assign('BATCH_TITLE',
				get_form_header($mod_strings['LBL_CFG_BATCH_TITLE'], '', false));
			$tpl->assign('CONFIRM_TITLE',
				get_form_header($mod_strings['LBL_CFG_CONFIRM_TITLE'], '', false));
			foreach($this->sync_opts as $cat => $os) {
				foreach($os as $op) {
					$val = self::get_server_setting($server_id, $cat, $op, '1');
					$nm = strtoupper("{$cat}_{$op}");
					$tpl->assign($nm, $val);
					$tpl->assign("{$nm}_CHECKED", $val ? ' checked="checked" ' : '');
				}
			}

			$sizes = array();
			$batch_types = array('Invoices', 'Payments', 'Accounts', 'Products');
			$batch_directions = array('import', 'export');
			foreach ($batch_types as $type) {
				foreach ($batch_directions as $dir) {
					$sizes[$type][$dir] = qb_batch_size($server_id, $type, $dir);
				}
			}
			$tpl->assign('BATCH', $sizes);

			return true;
		}
		return false;
	}
	
	function update_setup_config(&$cfg, $step, &$errs) {
		if($step == 'Basic') {
			$srv = new QBServer();
			$loaded = $srv->retrieve_primary();
			
			$alt_srv_id = array_get_default($_REQUEST, 'alt_server_id');
			$alt_srv_action = array_get_default($_REQUEST, 'alt_server_action');
			if($alt_srv_action == 'ignore')
				$srv->set_last_connect_server_id('');
			else if($alt_srv_action == 'replace')
				$srv->set_primary_server_id($alt_srv_id);
			else if($alt_srv_action == 'update') {
				if($loaded && $srv->subsume_server($alt_srv_id)) {
					$srv->save(); // gets retrieved again later
					$srv->set_last_connect_server_id('');
				}
			}
			$new_primary = array_get_default($_REQUEST, 'set_primary_server_id');
			if($new_primary)
				QBServer::set_primary_server_id($new_primary);
			$limit_filename = array_get_default($_REQUEST, 'limit_filename');
			if(isset($limit_filename))
				self::save_setting('Server', 'limit_filename', $limit_filename);
			if($srv->retrieve_primary())
				return 'ok';
			$errs[] = "No QuickBooks server has been selected for synchronization.";
			$lastconn = $srv->get_last_connect_server_id();
			if($lastconn)
				return 'semi';
			return '';
		}
		else if($step == 'Sync_Opts') {
			$server_id = QBServer::get_primary_server_id();
			if(! $server_id) {
				$errs[] = "No QuickBooks server has been selected for synchronization.";
				return '';
			}
			if(empty($_REQUEST['save_sync_opts']))
				return 'ok';
			foreach($this->sync_opts as $cat => $os) {
				foreach($os as $op) {
					$nm = "{$cat}_{$op}";
					$val = array_get_default($_REQUEST, $nm, '');
					self::save_server_setting($server_id, $cat, $op, $val);
				}
			}
			if(self::get_server_setting($server_id, 'Server', 'allow_sync'))
				return 'ok';
			return 'semi';
		}
		return false;
	}
	
	function get_owner_id() {
		return iah_std_owner_id();
	}
	
	function phase_can_begin($server_id, $phase) {
		return true;
	}
	
	function phase_get_percent_complete($server_id, $phase, $stage, $all_stages) {
		// may wish to calculate percentage for each stage
		$pos = array_search($stage, $all_stages);
		if($pos === false) return 0;
		return $pos * 100 / count($all_stages);
	}
	
}

?>
