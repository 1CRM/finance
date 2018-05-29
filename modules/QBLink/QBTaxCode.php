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


require_once('modules/QBLink/QBConfig.php');
require_once('modules/QBLink/QBServer.php');
require_once('modules/QBLink/QBBean.php');
require_once('modules/QBLink/qb_utils.php');
require_once('modules/TaxCodes/TaxCode.php');
require_once('modules/TaxRates/TaxRate.php');

class QBTaxCode extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $code;
	var $related_id;
	var $charge_tax_1;
	var $tax_rate_1;
	var $charge_tax_2;
	var $tax_rate_2;
	var $is_piggyback;
	var $is_ec_vat;
	
	// - static fields
	
	var $object_name = 'QBTaxCode';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_taxcodes';
	
	var $qb_query_type = 'TaxCode';
	var $listview_template = "TaxCodeListView.html";
	var $search_template = "TaxCodeSearchForm.html";
	
	
	var $qb_field_map = array(
		'Desc' => 'name',
		// CA/UK
		'Tax1Rate' => 'tax_rate_1',
		'Tax2Rate' => 'tax_rate_2',
	);
	
	function get_base($server_id) {
		$edition = QBServer::get_server_edition($server_id);
		if($edition == 'US')
			$base = 'SalesTaxCode';
		else
			$base = 'TaxCode';
		return array($edition, $base);
	}
	
	
	function get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		list($edition, $base) = $this->get_base($server_id);
		if($phase == 'Setup' && $stage == 'import' && $step == 'TaxCodes') {
			$reqs[] = array(
				'type' => 'import_all',
				'base' => $base,
				'action' => 'import_taxcodes',
				'optimize' => 'auto',
			);
		}
		return $reqs;
	}
	
	function perform_sync($mode, $qb_type, &$details, &$errmsg, &$newreqs) {
		$edition = QBServer::get_server_edition($this->server_id);
		if($edition == 'US') {
			$this->charge_tax_1 = qb_parse_bool($details['IsTaxable']);
		}
		else {
			$this->charge_tax_1 = ! qb_parse_bool($details['IsTax1Exempt']);
			$this->charge_tax_2 = ! qb_parse_bool($details['IsTax2Exempt']);
			$this->is_piggyback = qb_parse_bool($details['IsPiggybackRate']);
			$this->is_ec_vat = qb_parse_bool($details['IsEXVatCode']);
		}
		$this->code = $details['Name'];
		return true;
	}
	
	
	function import_taxcodes($server_id, &$req, &$data, &$newreqs) {
		list($edition, $base) = $this->get_base($server_id);
		if(! isset($data[$base.'Ret']))
			return true;
		$rows =& $data[$base.'Ret'];
		
		$used = array();
		$q = "SELECT id, qb_id, related_id FROM {$this->table_name} ".
				' WHERE server_id="'. $this->db->quote($server_id).'" '.
				' AND NOT deleted AND qb_is_active';
		$r = $this->db->query($q, true, "Error retrieving QB tax codes");
		$all = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$all[$row['qb_id']] = $row['id'];
			if($row['related_id'])
				$used[$row['related_id']] = 1;
		}
				
		for($i = 0; $i < count($rows); $i++) {
			$bean = new QBTaxCode();
			$ok = $bean->handle_import_row($server_id, 'TaxCode', $req, $rows[$i], $newreqs);
			if(! $ok)
				continue;
			if(isset($all[$bean->qb_id]))
				unset($all[$bean->qb_id]);
			if(empty($bean->id) && $base != 'SalesTaxCode') {
				$qb_rates = array();
				if($bean->charge_tax_1)
					$qb_rates[] = sprintf('%0.5f', $bean->tax_rate_1);
				if($bean->charge_tax_2) {
					$k2 = sprintf('%0.5f', $bean->tax_rate_2);
					if($bean->is_piggyback)
						$k2 .= 'x';
					$qb_rates[] = $k2;
				}
				if(! count($qb_rates))
					$bean->related_id = '-99';
			}
			$bean->save();
		}
		
		if($req->request_type == 'import_all' && count($all)) {
			$q = "UPDATE {$this->table_name} SET qb_is_active=0, deleted=1 WHERE id IN ('".implode("','", array_values($all))."')";
			$r = $this->db->query($q, true, "Error marking tax codes inactive");
		}
		
		return true;
	}
	
	
	static function &get_qb_tax_code($server_id, $qb_id) {
		self::load_taxes($server_id);
		$codes = qb_get_cache($server_id, 'TaxCode', 'QB');
		$ret = null;
		if(isset($codes[$qb_id])) {
			$ret = $codes[$qb_id];
		}
		return $ret;
	}
	
	static function &get_iah_tax_codes() {
		$iah_objs = array();
		$bean = new TaxCode();
		$bean->retrieve('-99');
		$iah_objs[$bean->id] = $bean;
		$all = $bean->get_full_list('', ' status="Active" ');
		if($all)
			foreach($all as $bean) {
				$iah_objs[$bean->id] = $bean;
			}
		return $iah_objs;
	}

	static function &get_qb_tax_rate($server_id, $qb_id) {
		self::load_taxes($server_id);
		$rates = qb_get_cache($server_id, 'TaxRate', 'QB');
		$ret = null;
		if(isset($rates[$qb_id]))
			$ret = $rates[$qb_id];
		return $ret;
	}

	static function &get_iah_tax_rates() {
		$bean = new TaxRate();
		$all = $bean->get_full_list('', ' status="Active" ');
		$iah_objs = array();
		if($all)
			foreach($all as $bean) {
				$iah_objs[$bean->id] = $bean;
			}
		return $iah_objs;
	}
	
	static function &from_iah_tax_code($server_id, $iah_id, $tax_rates=null) {
		self::load_taxes($server_id);
		$map = qb_get_cache($server_id, 'TaxCode', 'QBMap');
		$ret = null;
		if(! $iah_id) $iah_id = -99;

		if(isset($map[$iah_id])) {
			$ret = self::get_qb_tax_code($server_id, $map[$iah_id]);
		}
		if($ret && is_array($tax_rates)) {
			// does not check compounding or order of tax rates
			$check = array();
			foreach(self::get_iah_code_rates($iah_id) as $r)
				$check[$r['id']] = (float)$r['rate'];
			$ok = true;
			if (!empty($check)) {
				foreach($tax_rates as $t) {
					list($tid, $trate, $tcomp) = $t;
					if(! isset($check[$tid]) || $check[$tid] != $trate) {
						$ok = false;
						break;
					}
				}
			}
			if(! $ok) {
				$alt_code = self::match_iah_tax_rates($server_id, $tax_rates);
				if($alt_code)
					$ret = self::get_qb_tax_code($server_id, $map[$alt_code]);
				else
					$ret = new QBTaxCode();
			}
		}
		return $ret;
	}
	function &from_iah_tax_rate($server_id, $iah_id) {
		self::load_taxes($server_id);
		$map = qb_get_cache($server_id, 'TaxRate', 'QBMap');
		$ret = null;
		if(isset($map[$iah_id]))
			$ret = self::get_qb_tax_rate($server_id, $map[$iah_id]);
		return $ret;
	}

	static function &to_iah_tax_code($server_id, $qb_id) {
		self::load_taxes($server_id);
		$map = qb_get_cache($server_id, 'TaxCode', 'IAHMap');
		$ret = array_get_default($map, $qb_id, '-99');
		return $ret;
	}
	function &to_iah_tax_rate($server_id, $qb_id) {
		self::load_taxes($server_id);
		$map = qb_get_cache($server_id, 'TaxRate', 'IAHMap');
		$ret = array_get_default($map, $qb_id, '');
		return $ret;
	}
	
	function match_iah_tax_rates($server_id, $tax_rates) {
		static $by_rates;
		if(! isset($by_rates)) {
			$iah_codes = qb_get_cache($server_id, 'TaxCode', 'IAH');
			$by_rates = array();
			foreach($iah_codes as $c) {
				$rates = $c->get_tax_rates(false, false);
				$akey = array();
				foreach($rates as $r) {
					$akey[] = $r['id'].':'.sprintf('%0.5f', $r['rate']);
				}
				sort($akey);
				$key = implode(':', $akey);
				if(! isset($by_rates[$key])) $by_rates[$key] = $c->id;
			}
		}
		$akey = array();
		foreach($tax_rates as $t) {
			list($tid, $trate, $tcomp) = $t;
			$akey[] = $tid.':'.sprintf('%0.5f', $trate);
		}
		sort($akey);
		$key = implode(':', $akey);
		if(isset($by_rates[$key]))
			return $by_rates[$key];
		return false;
	}
	
	static function &standard_qb_tax_code($server_id) {
		$code = self::from_iah_tax_code($server_id, STANDARD_TAXCODE_ID);
		return $code;
	}
	
	static function &exempt_qb_tax_code($server_id) {
		$code = self::from_iah_tax_code($server_id, '-99');
		return $code;
	}
	
	static function load_taxes($server_id, $reload=false) {
		$modkey = 'TaxCode';
		$qb_objs = null;
		if(! $reload)
			$qb_objs = qb_get_cache($server_id, $modkey, 'QB');
		if(isset($qb_objs))
			return true;
		
		self::load_tax_rates($server_id, $reload);
		
		$bean = new QBTaxCode();
		$where = ' server_id="'.$bean->db->quote($server_id).'" ';
		$all = $bean->get_full_list('', $where);
		$qb_objs = array();
		$iah_vals = self::get_iah_tax_codes();
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
	
	static function load_tax_rates($server_id, $reload=false) {
		$modkey = 'TaxRate';
		$qb_objs = null;
		if(! $reload)
			$qb_objs = qb_get_cache($server_id, $modkey, 'QB');
		if(isset($qb_objs))
			return true;
		$qb_objs = array();
		$map_qb = array();
		$map_iah = array();
		$bean = new QBTaxCode();
		$iah_vals = self::get_iah_tax_rates();

		$edition = QBServer::get_server_edition($server_id);
		if($edition == 'US') {
			$none_mapped = true;
			$all = self::load_us_sales_taxes($server_id);
			if($all)
				foreach($all as $bean) {
					$qb_objs[$bean->qb_id] = $bean;
					if($bean->system_type == 'TaxRates' && $bean->system_id) {
						$map_qb[$bean->system_id] = $bean->qb_id;
						$none_mapped = false;
					}
					$map_iah[$bean->qb_id] = $bean->system_id;
				}
		}
		else {
			global $mod_strings;
			if($edition == 'CA') {
				$qb_objs[1] = array(
					'name' => $mod_strings['LBL_TAX_CA_GST'],
					'qb_id' => 1,
					'std_rate' => '', // can't determine
					'match_code' => 'GST|general sales',
				);
				$qb_objs[2] = array(
					'name' => $mod_strings['LBL_TAX_CA_PST'],
					'qb_id' => 2,
					'std_rate' => '',
					'match_code' => 'PST|provincial',
				);
			}
			else if($edition == 'UK') {
				$qb_objs[1] = array(
					'name' => $mod_strings['LBL_TAX_UK_VAT'],
					'qb_id' => 1,
					'std_rate' => '',
					'match_code' => 'VAT|value added',
				);
			}
			$iah1 = QBConfig::get_server_setting($server_id, 'TaxRate', 'system_id_1');
			$iah2 = QBConfig::get_server_setting($server_id, 'TaxRate', 'system_id_2');
			if($iah1) $map_qb[$iah1] = 1;
			if($iah2) $map_qb[$iah2] = 2;
			$map_iah[1] = $iah1;
			$map_iah[2] = $iah2;
			$none_mapped = ! ($iah1 || $iah2);
		}
		qb_put_cache($server_id, $modkey, 'QB', $qb_objs);
		qb_put_cache($server_id, $modkey, 'IAH', $iah_vals);
		qb_put_cache($server_id, $modkey, 'QBMap', $map_qb);
		qb_put_cache($server_id, $modkey, 'IAHMap', $map_iah);
		qb_put_cache($server_id, $modkey, 'none_mapped', $none_mapped);
	}
	
	static function get_iah_code_rates($code_id) {
		static $rates;
		if(! isset($rates))
			$rates = array();
		if(isset($rates[$code_id]))
			return $rates[$code_id];
		$code = new TaxCode();
		$code->retrieve($code_id);
		$code_rates = $code->get_tax_rates(false, false);
		$rates[$code_id] = array();
		foreach($code_rates as $r) {
			$rates[$code_id][] = array('id' => $r['id'], 'rate' => $r['rate'], 'compounding' => $r['compounding']);
		}
		return $rates[$code_id];
	}
	
	static function &load_us_sales_taxes($server_id) {
		global $db;
		require_once('modules/QBLink/QBItem.php');
		$seeditem = new QBItem();
		$where = ' server_id="'.$db->quote($server_id).'" '.
			' AND qb_type="ItemSalesTax" AND qb_is_active ';
		$all = $seeditem->get_full_list('', $where);
		return $all;
	}
	
	function get_option_name() {
		$nm = $this->name . ' ('.$this->code.')';
		return $nm;
	}
	
	function speculative_map_rates($server_id) {
		$qb_rates = qb_get_cache($server_id, 'TaxRate', 'QB');
		$iah_rates = qb_get_cache($server_id, 'TaxRate', 'IAH');
		$edition = QBServer::get_server_edition($server_id);
		$map = array();
		if($edition == 'US') {
			$by_rate = array();
			foreach($iah_rates as $id => $rto) {
				$rt = sprintf('%0.5f', $rto->rate);
				if(! isset($by_rate[$rt])) $by_rate[$rt] = $id;
			}
			foreach($qb_rates as $qbo) {
				$rt = sprintf('%0.5f', $qbo->assoc_rate);
				if(isset($by_rate[$rt]))
					$map[$qbo->qb_id] = $by_rate[$rt];
			}
		}
		else {
			$match = array();
			foreach($qb_rates as $id => $qbo) {
				$match[$id] = $qbo['match_code'];
			}
			foreach($iah_rates as $id => $rto) {
				foreach($match as $qid => $pat) {
					if(preg_match("/($pat)/i", $rto->name)) {
						unset($match[$qid]);
						$map[$qid] = $id;
					}
				}
			}
		}
		return $map;
	}
	
	function speculative_map_codes($server_id) {
		$qb_codes = qb_get_cache($server_id, 'TaxCode', 'QB');
		$iah_codes = qb_get_cache($server_id, 'TaxCode', 'IAH');
		$map = array();
		$by_rates = array();
		foreach($iah_codes as $c) {
			$rates = $c->get_tax_rates(false, false);
			$akey = array();
			foreach($rates as $r) {
				$akey[] = sprintf('%0.5f', $r['rate']);
			}
			$key = implode(':', $akey);
			if(! isset($by_rates[$key])) $by_rates[$key] = $c->id;
		}
		$quick_map = array(
			'.' => '',
			'E' => '-99',
			'S' => constant('STANDARD_TAXCODE_ID'),
		);
		foreach($qb_codes as $c) {
			$akey = array();
			if($c->charge_tax_1)
				$akey[] = sprintf('%0.5f', $c->tax_rate_1);
			if($c->charge_tax_2)
				$akey[] = sprintf('%0.5f', $c->tax_rate_2);
			$key = implode(':', $akey);
			if(isset($quick_map[$c->code])) {
				$map[$c->qb_id] = $quick_map[$c->code];
			}
			else if(isset($by_rates[$key]))
				$map[$c->qb_id] = $by_rates[$key];
		}
		return $map;
	}

	function setup_template_step(&$cfg, &$tpl, $step) {
		$server_id = QBServer::get_primary_server_id();
		if(! $server_id) {
			$tpl->assign('NO_SERVER', '1');
			return 'no_server';
		}
		if($step == 'TaxRates') {
			$this->load_taxes($server_id, true);
			$qb_rates = qb_get_cache($server_id, 'TaxRate', 'QB');
			$iah_rates = qb_get_cache($server_id, 'TaxRate', 'IAH');
			if(qb_get_cache($server_id, 'TaxRate', 'none_mapped'))
				$map = $this->speculative_map_rates($server_id);
			else
				$map = qb_get_cache($server_id, 'TaxRate', 'IAHMap');
			$html = qb_match_up_html('taxrates', $qb_rates, $iah_rates, $map);
			$tpl->assign('BODY', $html);
		}
		else if($step == 'TaxCodes') {
			global $mod_strings;
			$this->load_taxes($server_id, true);
			$qb_codes = qb_get_cache($server_id, 'TaxCode', 'QB');
			$iah_codes = qb_get_cache($server_id, 'TaxCode', 'IAH');
			if(qb_get_cache($server_id, 'TaxCode', 'none_mapped'))
				$map = $this->speculative_map_codes($server_id);
			else
				$map = qb_get_cache($server_id, 'TaxCode', 'IAHMap');
			$iah_codes = array('create_new' => $mod_strings['LBL_CFG_MAP_CREATE_NEW']) + $iah_codes;
			$html = qb_match_up_html('taxcodes', $qb_codes, $iah_codes, $map);
			if(! $html)
				$html = $mod_strings['LBL_CFG_NO_TAX_CODES'];
			else
				$html = update_selects_javascript('taxcodes_') . $html;
			$tpl->assign('BODY', $html);
		}
		return false;		
	}
	
	function update_setup_config(&$cfg, $step, &$errs) {
		global $db;
		$server_id = QBServer::get_primary_server_id();
		$edition = QBServer::get_server_edition($server_id);
		$status = false;
		if($step == 'TaxRates') {
			if($edition == 'US') {
				if(isset($_REQUEST['taxrates_map']) && is_array($_REQUEST['taxrates_map'])) {
					$all = self::load_us_sales_taxes($server_id);
					if($all) {
						foreach($all as $bean) {
							$val = array_get_default($_REQUEST['taxrates_map'], $bean->id, '');
							if($val != $bean->system_id) {
								$bean->system_id = $val;
								$bean->system_type = $val ? 'TaxRates' : '';
								$bean->save();
							}
						}
					}
					// avoid any potential caching issues
					self::load_tax_rates($server_id, true);
				}
			}
			else {
				if(isset($_REQUEST['taxrates_map']) && is_array($_REQUEST['taxrates_map'])) {
					for($idx = 1; $idx <= 2; $idx++) {
						$val = array_get_default($_REQUEST['taxrates_map'], $idx);
						QBConfig::save_server_setting($server_id, 'TaxRate', 'system_id_'.$idx, $val);
					}
				}
			}
			$status = 'ok';
		}
		else if($step == 'TaxCodes') {
			$map = array_get_default($_REQUEST, 'taxcodes_map');
			/*if(! $map || ! is_array($map)) {
				$errs[] = "No tax codes mapped";
				return false;
			}*/
			$this->load_taxes($server_id, true);
			$qb_codes = qb_get_cache($server_id, 'TaxCode', 'QB');
			foreach($qb_codes as $code) {
				$rel_id = array_get_default($map, $code->id, '');
				if($rel_id == 'create_new') {
					$iah_code = new TaxCode();
					if(! isset($iah_last_code_pos)) {
						$q = "SELECT MAX(position) AS position FROM `{$iah_code->table_name}` WHERE NOT deleted";
						$r = $iah_code->db->query($q, true, "Error retrieving last tax code position");
						$row = $iah_code->db->fetchByAssoc($r);
						$iah_last_code_pos = $row['position'] + 1;
					}
					$iah_code->code = $code->code;
					$iah_code->name = $code->name;
					$iah_code->status = 'Active';
					$iah_code->position = $iah_last_code_pos ++;
					$ratemap = qb_get_cache($server_id, 'TaxRate', 'IAHMap');
					$taxes = array();
					if($edition != 'US') {
						if($code->charge_tax_1)
							$taxes[1] = $code->tax_rate_1;
						if($edition != 'UK' && $code->charge_tax_2)
							$taxes[2] = $code->tax_rate_2;
						foreach($taxes as $k=>$r) {
							if(empty($ratemap[$k])) {
								$errs[] = "Tax rate $k is not mapped to an info@hand tax record.";
								return false;
							}
							$row = array(
								'rate_id' => $ratemap[$k],
								'override_rate' => 1,
								'custom_rate' => $r,
								'position' => $k,
							);
							if($k == 2 && $code->is_piggyback) {
								$row['override_compounding'] = 1;
								$row['custom_compounding'] = 1;
							}
							$taxes[$k] = $row;
						}
					}
					$rel_id = $iah_code->save();
					$iah_code->load_relationship('taxrates');
					foreach($taxes as $t) {
						$iah_code->taxrates->add($t['rate_id'], $t);
					}
				}
				$code->related_id = $rel_id;
				$code->save();
			}
			/*if(isset($_REQUEST['taxcodes_map']) && is_array($_REQUEST['taxcodes_map'])) {
				for($idx = 1; $idx <= 2; $idx++) {
					$val = array_get_default($_REQUEST['taxrates_map'], $idx);
					QBConfig::save_server_setting($server_id, 'TaxRate', 'system_id_'.$idx, $val);
				}
			}*/
			$status = 'ok';
		}
		return $status;
	}
	
	static function check_sales_tax_enabled($server_id) {
		$disabled = QBConfig::get_server_setting($server_id, 'Server', 'sales_tax_disabled');
		return empty($disabled);
	}
	
	
	function get_ref() {
		return array(
			'ListID' => $this->qb_id,
			// must use code instead of name
			'FullName' => $this->code,
		);
	}

	
}
