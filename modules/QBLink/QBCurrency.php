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
require_once('modules/Currencies/Currency.php');


class QBCurrency extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $conversion_rate;
	var $symbol;
	var $iso4217;
	var $country;
	var $currency_id;
	
	// - static fields
	
	var $object_name = 'QBCurrency';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_currencies';
	
	var $qb_query_type = 'Currency';
	var $listview_template = "CurrencyListView.html";
	var $search_template = "CurrencySearchForm.html";
	
	var $qb_field_map = array(
		'ExchangeRate' => 'conversion_rate',
		'Symbol' => 'symbol',
		'Code' => 'iso4217',
		'Country' => 'country',
	);

	// -- Sync handling
	
	function &get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if($phase == 'Setup' && $stage == 'import' && $this->is_multi_currency($server_id)) {
			$reqs[] = array(
				'type' => 'import_all',
				'base' => 'Currency',
				'action' => 'import_currencies',
				'optimize' => 'auto',
			);
		}
		return $reqs;
	}
	
	function is_multi_currency($server_id) {
		$edition = QBServer::get_server_edition($server_id);
		$multi = QBConfig::get_server_setting($server_id, 'Server', 'multi_currency');
		return $multi;
	}
	
	function import_currencies($server_id, &$req, &$data, &$newreqs) {
		if(! isset($data['CurrencyRet']))
			return true;
		$rows =& $data['CurrencyRet'];

		$q = "SELECT id, iso4217 FROM currencies WHERE NOT deleted";
		$r = $this->db->query($q, true, "Error retrieving system currencies");
		$sys = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$sys[strtolower($row['iso4217'])] = $row['id'];
		}
		$sys[strtolower(AppConfig::setting('locale.base_currency.iso4217'))] = '-99';
		
		$q = "SELECT id, qb_id, currency_id FROM {$this->table_name} ".
			' WHERE server_id="'. $this->db->quote($server_id).'" '.
			' AND NOT deleted AND qb_is_active';
		$r = $this->db->query($q, true, "Error retrieving QB currencies");
		$all = array();
		while($row = $this->db->fetchByAssoc($r)) {
			$all[$row['qb_id']] = $row['id'];
		}
		
		for($i = 0; $i < count($rows); $i++) {
			$bean = new QBCurrency();
			$ok = $bean->handle_import_row($server_id, 'Currency', $req, $rows[$i], $newreqs);
			if(! $ok)
				continue;
			if(isset($all[$bean->qb_id]))
				unset($all[$bean->qb_id]);
			if(empty($bean->currency_id) && ! empty($bean->iso4217)) {
				$code = strtolower($bean->iso4217);
				if(isset($sys[$code]))
					$bean->currency_id = $sys[$code];
			}
			$bean->save();
		}
		
		if($req->request_type == 'import_all' && count($all)) {
			$q = "UPDATE {$this->table_name} SET qb_is_active=0, deleted=1 WHERE id IN ('".implode("','", array_values($all))."')";
			$r = $this->db->query($q, true, "Error marking currencies inactive");
		}
		
		return true;
	}
	
	
	static function load_rates($server_id, $reload=false) {
		$modkey = 'Currencies';
		$qb_objs = null;
		$iah_objs = null;
		if(! $reload)
			$qb_objs = qb_get_cache($server_id, $modkey, 'QB');
		if(isset($qb_objs))
			return true;
		
		$primary = QBConfig::get_server_setting($server_id, 'Currencies', 'primary_qb_id');
		
		$bean = new QBCurrency();
		$where = ' server_id="'.$bean->db->quote($server_id).'" ';
		$all = $bean->get_full_list('', $where);
		$qb_objs = array();
		$map_qb = array();
		$map_iah = array();
		if($all)
		foreach($all as $bean) {
			if($bean->qb_id == $primary)
				$bean->is_primary = true;
			$qb_objs[$bean->qb_id] = $bean;
			if($bean->currency_id) {
				$map_qb[$bean->currency_id] = $bean->qb_id;
				$map_iah[$bean->qb_id] = $bean->currency_id;
			}
		}
		if(! count($qb_objs)) {
			$bean = new QBCurrency();
			$bean->server_id = $server_id;
			$bean->qb_id = 'qb_home_currency';
			$bean->name = 'QB Home Currency';
			$bean->conversion_rate = '1.00000';
			$edition = QBServer::get_server_edition($server_id);
			if($edition == 'US') {
				$bean->iso4217 = 'USD';
				$bean->symbol = '$';
			}
			else if($edition == 'CA') {
				$bean->iso4217 = 'CAD';
				$bean->symbol = 'C$';
			}
			$hcid = $bean->save();
			QBConfig::save_server_setting($server_id, 'Currencies', 'primary_qb_id', $bean->qb_id);
			$bean->retrieve($hcid);
			$qb_objs[$bean->qb_id] = $bean;
		}
		qb_put_cache($server_id, $modkey, 'QB', $qb_objs);
		qb_put_cache($server_id, $modkey, 'QBMap', $map_qb);
		
		$bean = new Currency();
		$all = $bean->get_full_list('', ' status="Active" ');
		$iah_objs = array();
		if($all)
			foreach($all as $bean) {
				$iah_objs[$bean->id] = $bean;
			}
		$bean = new Currency();
		$bean->retrieve('-99');
		$bean->is_primary = true;
		$iah_objs[$bean->id] = $bean;
		qb_put_cache($server_id, $modkey, 'IAH', $iah_objs);
		qb_put_cache($server_id, $modkey, 'IAHMap', $map_iah);
		
		return true;
	}
	
	static function &get_qb_currency($server_id, $qb_id) {
		self::load_rates($server_id);
		$rates = qb_get_cache($server_id, 'Currencies', 'QB');
		$ret = null;
		if(isset($rates[$qb_id]))
			$ret = $rates[$qb_id];
		return $ret;
	}
	function &from_iah_currency($server_id, $iah_cid) {
		self::load_rates($server_id);
		$map = qb_get_cache($server_id, 'Currencies', 'QBMap');
		$ret = null;
		if(empty($iah_cid))
			$iah_cid = '-99';
		if(isset($map[$iah_cid]))
			$ret = self::get_qb_currency($server_id, $map[$iah_cid]);
		return $ret;
	}
	static function &to_iah_currency($server_id, $qb_cid) {
		self::load_rates($server_id);
		$rates = qb_get_cache($server_id, 'Currencies', 'IAH');
		$map = qb_get_cache($server_id, 'Currencies', 'IAHMap');
		$ret = null;
		if(isset($map[$qb_cid]))
			$ret = $rates[$map[$qb_cid]];
		return $ret;
	}
	
	static function &get_qb_home_currency($server_id) {
		// saved by server on connect
		$qb_home_cur = QBConfig::get_server_setting($server_id, 'Currencies', 'primary_qb_id');
		if(! $qb_home_cur)
			$ret = null;
		else
			$ret = self::get_qb_currency($server_id, $qb_home_cur);		
		return $ret;
	}

	static function &get_qb_import_currency($server_id) {
		$qb_cur = self::get_qb_home_currency($server_id);
		$ret = self::to_iah_currency($server_id, $qb_cur->qb_id);
		return $ret;
	}
	
	static function &convert_qb_iah($server_id, $amount, $qb_id=null, $iah_id='-99') {
		$ret = array(
			'orig_amount' => $amount,
			'conv_amount' => '0.00',
			'currency_id' => '',
			'exchange_rate' => null,
		);
		self::load_rates($server_id);
		$qb_home = self::get_qb_home_currency($server_id);
		if(! isset($qb_id))
			$qb_cur =& $qb_home;
		else
			$qb_cur = self::get_qb_currency($server_id, $qb_id);
		if(! $iah_id) $iah_id = '-99';
		if($qb_cur->currency_id == $iah_id)
			$qb_base =& $qb_cur;
		else
			$qb_base = self::from_iah_currency($server_id, $iah_id);
		
		$conv_amt = $amount * $qb_cur->conversion_rate / $qb_base->conversion_rate;
		$ret['conv_amount'] = $conv_amt;
		$exch_rate = sprintf('%0.5f', 1.0 * $qb_base->conversion_rate / $qb_cur->conversion_rate);
		$ret['exchange_rate'] = $exch_rate;
		$ret['currency_id'] = $qb_cur->currency_id;
		return $ret;
	}
	
	
	// -- Setup
	
	function setup_template_step(&$cfg, &$tpl, $step) {
		if($step != 'Currencies')
			return false;
		$server_id = QBServer::get_primary_server_id();
		if(! $server_id) {
			$tpl->assign('NO_SERVER', '1');
			return 'no_server';
		}
		self::load_rates($server_id, true);
		$qb_rates = qb_get_cache($server_id, 'Currencies', 'QB');
		$iah_rates = qb_get_cache($server_id, 'Currencies', 'IAH');
		$map = qb_get_cache($server_id, 'Currencies', 'QBMap');
		// auto-map primary currency
		foreach($qb_rates as $k=>$cur) {
			if(! empty($cur->is_primary) && empty($cur->currency_id)) {
				foreach($iah_rates as $r) {
					if(empty($map[$r->id]) && strtolower($r->iso4217) == strtolower($cur->iso4217)) {
						$qb_rates[$k]->currency_id = $r->id;
						break;
					}
				}
			}
		}
		foreach($iah_rates as $k=>$cur) {
			$nm = $iah_rates[$k]->name . ' ('.$cur->iso4217.')';
			$iah_rates[$k]->name = $nm;
		}
		$html = qb_match_up_html('currency', $qb_rates, $iah_rates, 'currency_id');
		if(! $html)
			$html = $mod_strings['LBL_CFG_NO_CURRENCIES'];
		$tpl->assign('BODY', $html);
	}
	
	function update_setup_config(&$cfg, $step, &$errs) {
		if($step != 'Currencies')
			return false;
		$map = array_get_default($_REQUEST, 'currency_map');
		if(! $map || ! is_array($map)) {
			$errs[] = "No currencies mapped";
			return false;
		}
		$server_id = QBServer::get_primary_server_id();
		self::load_rates($server_id, true);
		$qb_rates = qb_get_cache($server_id, 'Currencies', 'QB');
		$status = 'ok';
		foreach($qb_rates as $rate) {
			$rate->currency_id = array_get_default($map, $rate->id, '');
			if(! empty($rate->is_primary) && empty($rate->currency_id)) {
				$errs[] = "QuickBooks primary currency is not mapped";
				$status = 'no_primary';
			}
			$rate->save();
		}
		return $status;
	}
	
	function get_option_name() {
		$nm = $this->name . (strlen($this->iso4217) ? ' ('.$this->iso4217.')' : '');
		if(! empty($this->is_primary))
			$nm .= ' **';
		return $nm;
	}
	
	function get_ref() {
		if($this->qb_id == 'qb_home_currency')
			return null;
		return parent::get_ref();
	}
}

