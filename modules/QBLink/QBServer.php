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


require_once('data/SugarBean.php');
require_once('modules/QBLink/QBConfig.php');
require_once('modules/QBLink/QBRequest.php');


class QBServer extends SugarBean {

	// Saved fields
	var $id;
	var $name;
	var $last_connect;
	var $last_sync_result;
	var $last_sync_msg;
	var $sync_options;
	var $server_info;
	var $company_filename;
	var $ip_address;
	var $qb_file_id;
	var $qb_owner_id;
	var $qb_edition;
	var $qb_version;
	var $qb_xml_version;
	var $qb_xml_supported;
	var $connector;	
	var $date_entered;
	var $date_modified;
	var $deleted;

	// runtime only
	var $server_info_arr;
	var $sync_options_arr;
	var $next_req_id;
	
	var $module_dir = 'QBLink';
	var $table_name = 'qb_servers';
	var $object_name = 'QBServer';
	var $new_schema = true;
	var $processed = true; // disable all workflow for this object
	
	var $unique_fields = array(
		'company_filename',
		'qb_file_id',
		'qb_owner_id',
	);
	var $connect_update_fields = array(
		'ip_address',
		'name',
		'qb_edition',
		'qb_version',
		'qb_xml_version',
		'qb_xml_supported',
		'server_info_arr',
		'sync_options_arr',
		'last_connect',
	);
	var $consistent_fields = array(
		'ip_address',
		'company_filename',
		'qb_file_id',
		'qb_owner_id',
		'qb_xml_version',
		'qb_xml_supported',
	);
	var $serialize_fields = array(
		'sync_options_arr' => 'sync_options',
		'server_info_arr' => 'server_info',
	);
	
	var $sync_stages = array(
		//'config',
		'reg_update', // register local modified objects for update in QB
		'import',
		'ext_import',
		'export',
		'pre_update',
		'update',
		'delete',
	);
	
	var $qbxml_encoding = 'utf-8';
	var $asciify_text = true;
	var $log_requests = true; // debug
	
	
	static function get_primary_server_id() {
		return QBConfig::get_setting('setup', 'primary_server_id');
	}
	
	function set_primary_server_id($id=null) {
		if(! $id) $id = $this->id;
		return QBConfig::save_setting('setup', 'primary_server_id', $id);
	}
	
	function get_last_connect_server_id() {
		return QBConfig::get_setting('setup', 'last_connect_server_id');
	
	}
	function set_last_connect_server_id($id=null) {
		if(! $id) $id = $this->id;
		return QBConfig::save_setting('setup', 'last_connect_server_id', $id);
	}

	function &retrieve_primary() {
		$id = self::get_primary_server_id();
		if(! $id)
			$ret = false;
		else
			$ret = $this->retrieve($id);
		return $ret;
	}
	
	function retrieve($id = -1, $enc=true) {
		$ret = parent::retrieve($id, $enc);
		if($ret) {	
			foreach($this->serialize_fields as $k=>$v) {
				if($this->$v)
					$this->$k = unserialize(from_html($this->$v));
				else
					$this->$k = array();
			}
		}
		return $ret;
	}
	
	function save($check_notify=false) {
		foreach($this->serialize_fields as $k=>$v) {
			if(is_array($this->$k))
				$this->$v = serialize($this->$k);
		}
		$id = parent::save($check_notify);
		return $id;
	}
	
	// make sure these params haven't changed mid-session
	// pretty much obsolete with QBWC 2.0 - HCP result sent on first query only
	function consistency_check(&$cfg, $params) {
		if(strlen(trim($params['hcp_response']))) {
			$this->parse_hcp_query_response($params['hcp_response'], $params);
			foreach($this->consistent_fields as $f)
				if(isset($params[$f]) && $params[$f] != $this->$f)
					return false;
		}
		return true;
	}
	
	function lookup_server($params) {
		$query = "SELECT id FROM {$this->table_name} WHERE NOT deleted ";
		foreach($this->unique_fields as $f)
			if(isset($params[$f]))
				$query .= sprintf(" AND `$f`='%s' ", $this->db->quote($params[$f]));
		$r = $this->db->query($query, true);
		if($row = $this->db->fetchByAssoc($r))
			return $row['id'];
		return null;
	}
	
	function on_connect_server(&$cfg, $params) {
		$succ = $this->parse_hcp_query_response($params['hcp_response'], $params);
		
		unset($params['hcp_response']);
		if(defined('QBLINK_DEBUG')) {
			$fp = fopen('qb_regserv.txt', 'w');
			if(! $succ)
				fwrite($fp, "error parsing prefs\n");
			fwrite($fp, print_r($params, true));
			fwrite($fp, "-----\n");
			fclose($fp);
		}
		
		if($svr_id = $this->lookup_server($params))
			$this->retrieve($svr_id);
		if(empty($this->id)) {
			foreach($this->unique_fields as $f)
				if(isset($params[$f]))
					$this->$f = $params[$f];
		}
		foreach($this->connect_update_fields as $f)
			if(isset($params[$f]))
				$this->$f = $params[$f];
		
		$id = $this->save();
		$this->retrieve($id);
		
		// temp
		//$this->set_primary_server_id();
		$this->set_last_connect_server_id();
		$prim = self::get_primary_server_id();
		if(! $prim)
			QBConfig::update_setup_status('Basic', 'semi');
		
		return $id;
	}
	
	
	// must call save() after successful invocation
	function subsume_server($alt_id) {
		$alt = new QBServer();
		if(! $alt->retrieve($alt_id))
			return false;
		foreach($this->unique_fields as $f) {
			$this->$f = $alt->$f;
		}
		$alt->mark_deleted($alt_id);
		return true;
	}
	
	
	function &get_parser($reinit=false) {
		if(! isset($this->parser) || $reinit) {
			require_once('modules/QBLink/QBXMLParser.php');
			$this->parser = new QBXMLParser();
		}
		return $this->parser;
	}

	
	function parse_qbxml(&$data, $newparser=false) {
		$p = $this->get_parser();
		if($p->parse($data)) {
			return $p->responses;
		}
		return false;
	}

	
	function parse_qbxml_bool($val) {
		if($val === 'true')
			return true;
		if($val === 'false')
			return false;
		return null;
	}
	
	
	function qbxml_value(&$src, $type, $_path) {
		$path = array();
		for($i = 2; $i < func_num_args(); $i++) {
			$a = func_get_arg($i);
			if(is_array($a)) $path += $a;
			else $path[] = $a;
		}
		$node =& $src;
		$last = array_pop($path);
		if($type == 'list')
			$default = array();
		else
			$default = null;
		foreach($path as $p) {
			if(! isset($node[$p]))
				return $default;
			$node =& $node[$p];
		}
		if(! isset($node[$last]))
			return $default;
		$val =& $node[$last];
		if($type == 'bool') {
			return qb_parse_bool($val);
		}
		else if($type == 'list') {
			if(is_array($val))
				return $val;
			else
				return array($val);
		}
		return $val;
	}
	
	
	function get_qbxml_version() {
		$ver = $this->qb_xml_version;
		if($this->qb_edition != 'US')
			$ver = $this->qb_edition . $ver;
		return $ver;
	}

	
	// parse Host, Company, Preferences query responses with normalization
	function parse_hcp_query_response(&$hcp_result, &$params) {
		$params['server_info_arr'] = array('downloaded' => 1);
		$info =& $params['server_info_arr'];
		$edition = $params['qb_edition'];
		
		$arr = $this->parse_qbxml($hcp_result);
		if(! is_array($arr) || count($arr) != 3)
			return false;
		
		$host = @$arr[0]['root']['HostQueryRs']['HostRet'][0];
		if($arr[0]['attrs']['statusCode'] != '0' || ! $host)
			return false;
		$info['product_name'] = $this->qbxml_value($host, 'str', 'ProductName');
		$info['qb_major_ver'] = $this->qbxml_value($host, 'str', 'MajorVersion');
		$info['qb_minor_ver'] = $this->qbxml_value($host, 'str', 'MinorVersion');
		$info['qb_xml_supported'] = $this->qbxml_value($host, 'list', 'SupportedQBXMLVersion');
		$info['multi_user'] = $this->qbxml_value($host, 'str', 'QBFileMode') == 'MultiUser' ? 1 : 0;
		
		$company = @$arr[1]['root']['CompanyQueryRs']['CompanyRet'][0];
		if($arr[1]['attrs']['statusCode'] != '0' || ! $company)
			return false;
		$info['company_name'] = $this->qbxml_value($company, 'str', 'CompanyName');
		$months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
		$fy_start = $this->qbxml_value($company, 'str', 'FirstMonthFiscalYear');
		$info['fy_start'] = array_search($fy_start, $months);
		if($info['fy_start'] === false) $info['fy_start'] = $fy_start;
		
		if(is_array($company['DataExtRet']))
			foreach($company['DataExtRet'] as $ext) {
				if($ext['DataExtName'] == 'FileID') {
					$params['qb_file_id'] = $ext['DataExtValue'];
					$params['qb_owner_id'] = $ext['OwnerID'];
				}
			}
		else {
			$params['qb_file_id'] = '';
			$params['qb_owner_id'] = '';
		}
		
		$prefs = @$arr[2]['root']['PreferencesQueryRs']['PreferencesRet'][0];
		if($arr[2]['attrs']['statusCode'] != '0' || ! $prefs)
			return false;
		$info['require_acct_nums'] = $this->qbxml_value($prefs, 'bool', 'AccountingPreferences', 'IsUsingAccountNumbers');
		$info['require_accounts'] = $this->qbxml_value($prefs, 'bool', 'AccountingPreferences', 'IsRequiringAccounts');
		$info['multi_currency'] = $this->qbxml_value($prefs, 'bool', 'AccountingPreferences', 'IsUsingMulticurrency');
		if (is_null($info['multi_currency'])) {
			$info['multi_currency'] = $this->qbxml_value($prefs, 'bool', 'MultiCurrencyPreferences', 'IsMultiCurrencyOn');
		}
		$info['home_currency'] = $this->qbxml_value($prefs, '', 'AccountingPreferences', 'HomeCurrencyRef');
		if (is_null($info['home_currency'])) {
			$info['home_currency'] = $this->qbxml_value($prefs, '', 'MultiCurrencyPreferences', 'HomeCurrencyRef');
		}
		$info['foreign_item_prices'] = $this->qbxml_value($prefs, 'bool', 'AccountingPreferences', 'IsUsingForeignPricesOnItems');
		$info['using_estimates'] = $this->qbxml_value($prefs, 'bool', 'JobsAndEstimatesPreferences', 'IsUsingEstimates');
		$info['using_inventory'] = $this->qbxml_value($prefs, 'bool', 'PurchasesAndVendorsPreferences', 'IsUsingInventory');
		$info['using_auto_discounts'] = $this->qbxml_value($prefs, 'bool', 'PurchasesAndVendorsPreferences', 'IsAutomaticallyUsingDiscounts');
		$info['using_units_of_measure'] = $this->qbxml_value($prefs, 'bool', 'PurchasesAndVendorsPreferences', 'IsUsingUnitsOfMeasure');
		$info['auto_applying_payments'] = $this->qbxml_value($prefs, 'bool', 'SalesAndCustomersPreferences', 'IsAutoApplyingPayments');
		
		$info['allow_customer_tax_codes'] = $this->qbxml_value($prefs, 'bool', 'TaxPreferences', 'AllowCustomerTaxCodes');
		$info['taxes'] = array();
		// Canada/UK
		for($i = 1; ; $i++) {
			if(! isset($prefs['TaxPreferences']['ChargeTax'.$i]))
				break;
			$info['taxes'][$i-1] = array(
				'charged' => $this->qbxml_value($prefs, 'bool', 'TaxPreferences', 'ChargeTax'.$i),
				'track_expenses' => $this->qbxml_value($prefs, 'bool', 'TaxPreferences', 'TrackTax'.$i.'Expenses'),
				'reporting_period' => $this->qbxml_value($prefs, 'str', 'TaxPreferences', 'Tax'.$i.'ReportingPeriod'),
			);
		}
		$info['default_customer_tax_code'] = $this->qbxml_value($prefs, 'str', 'TaxPreferences', 'DefaultCustomerTaxCodeRef');
		
		// US
		if(isset($prefs['SalesTaxPreferences'])) {
			$info['taxes'][] = array(
				'charged' => true, // ?
				'track_expenses' => null,
				'reporting_period' => $this->qbxml_value($prefs, 'bool', 'SalesTaxPreferences', 'PaySalesTax'),
			);
			$info['default_item_sales_tax'] = $this->qbxml_value($prefs, 'ref', 'SalesTaxPreferences', 'DefaultItemSalesTaxRef');
			$info['default_taxable_sales_tax'] = $this->qbxml_value($prefs, 'ref', 'SalesTaxPreferences', 'DefaultTaxableSalesTaxCodeRef');
			$info['default_nontaxable_sales_tax'] = $this->qbxml_value($prefs, 'ref', 'SalesTaxPreferences', 'DefaultNonTaxableSalesTaxCodeRef');
			$info['sales_tax_disabled'] = false;
		}
		else if($edition == 'US') {
			$info['sales_tax_disabled'] = true;
		}
		
		$first_day = $this->qbxml_value($prefs, 'str', 'TimeTrackingPreferences', 'FirstDayOfWeek');
		if(! isset($first_day))
			$first_day = 'Sunday'; // probably US
		$days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
		$info['first_day_of_week'] = array_search($first_day, $days);
		if($info['first_day_of_week'] === false) $info['first_day_of_week'] = $first_day;
		$info['access_personal_data'] = $this->qbxml_value($prefs, 'bool', 'CurrentAppAccessRights', 'IsPersonalDataAccessAllowed');
		
		$params['qb_version'] = $info['qb_major_ver'] . '.' . $info['qb_minor_ver'];
		$params['qb_xml_supported'] = implode('|', $info['qb_xml_supported']);
		$params['name'] = "{$info['product_name']} ({$params['qb_edition']}/{$params['qb_version']})";
		$params['last_connect'] = qb_date_time();
		return true;
	}
	
	function update_info_cache() {
		if(! is_array($this->server_info_arr)) {
			qb_log_error('Error updating server info cache');
			return false;
		}
		$info =& $this->server_info_arr;
		QBConfig::save_server_setting($this->id, 'Server', 'qb_xml_version', $this->qb_xml_version);
		QBConfig::save_server_setting($this->id, 'Server', 'qb_edition', $this->qb_edition);
		QBConfig::save_server_setting($this->id, 'Server', 'qb_version', $info['qb_version']);
		QBConfig::save_server_setting($this->id, 'Server', 'multi_user', $info['multi_user']);
		QBConfig::save_server_setting($this->id, 'Server', 'multi_currency', $info['multi_currency']);
		QBConfig::save_server_setting($this->id, 'Server', 'using_estimates', $info['using_estimates']);
		QBConfig::save_server_setting($this->id, 'Server', 'using_inventory', $info['using_inventory']);
		QBConfig::save_server_setting($this->id, 'Server', 'sales_tax_disabled', array_get_default($info, 'sales_tax_disabled', 0) ? 1 : 0);
		if(isset($info['home_currency']) && is_array($info['home_currency'])) {
			QBConfig::save_server_setting($this->id, 'Currencies', 'primary_qb_id', $info['home_currency']['ListID']);
		}
		return true;
	}
	
	static function get_server_edition($server) {
		if ($server instanceof QBServer)
			$edition = $server->qb_edition;
		else
			$edition = QBConfig::get_server_setting($server, 'Server', 'qb_edition');
		return $edition;
	}
	
	static function get_server_multi_user($server) {
		if ($server instanceof QBServer)
			$server_id = $server->id;
		else
			$server_id = $server;
		$mu = QBConfig::get_server_setting($server_id, 'Server', 'multi_user');
		return $mu;
	}
	
	static function get_sync_enabled($server_id) {
		$enabled = QBConfig::get_server_setting($server_id, 'Server', 'sync_enabled', 0);
		return $enabled;
	}
	
	static function set_sync_enabled($enabled, $server_id) {
		QBConfig::save_server_setting($server_id, 'Server', 'sync_enabled', $enabled);
	}


	function get_config_warnings() {
		global $mod_strings;
		$warn = array();
		$info = $this->server_info_arr;
		if(is_array($info) && ! empty($info['downloaded'])) {
			if(! empty($info['sales_tax_disabled']))
				$warn[] = $mod_strings['LBL_SALES_TAX_DISABLED'];
			if(empty($info['using_estimates']))
				$warn[] = $mod_strings['LBL_ESTIMATES_DISABLED'];
			if(empty($info['using_inventory']))
				$warn[] = $mod_strings['LBL_INVENTORY_DISABLED'];
		}
		return $warn;
	}

	function get_display_params() {
		$p = get_object_vars($this);
		if(! empty($this->server_info_arr))
			$p['company_name'] = $this->server_info_arr['company_name'];
		$warn = $this->get_config_warnings();
		if($warn) $p['warnings'] = $warn;
		return $p;
	}
	
	
	function &create_request(&$session, &$vals, &$handler) {
		$type = array_get_default($vals, 'type');
		if($type == 'import_all' || $type == 'import_updated' || $type == 'import') {
			$vals['operation'] = $vals['base'] . 'QueryRq';
			$vals['preparse'] = 'list:' . $vals['base'] . 'QueryRs';
		}
		else if($type == 'export') {
			$vals['operation'] = $vals['base'] . 'AddRq';
			$vals['preparse'] = 'export:' . $vals['base'] . 'AddRs'; // .':' . $vals['base'] . 'Ret:0';
		}
		else if($type == 'update') {
			$vals['operation'] = $vals['base'] . 'ModRq';
			$vals['preparse'] = 'update:' . $vals['base'] . 'ModRs'; // .':' . $vals['base'] . 'Ret:0';
		}
		else if($type == 'delete') {
			$vals['operation'] = $vals['base'] . 'DelRq';
			$vals['preparse'] = 'delete:' . $vals['base'] . 'DelRs';
		}
				
		$params = array( // need correct ordering because Intuit don't understand XML
			'ListID' => null, 'FullName' => null,
			'TxnID' => null, 'RefNumber' => null, 'RefNumberCaseSensitive' => null,
			'MaxReturned' => null, 'ActiveStatus' => null,
			'FromModifiedDate' => null, 'ToModifiedDate' => null,
			'ModifiedDateRangeFilter' => null, 'TxnDateRangeFilter' => null,
			'NameFilter' => null, 'NameRangeFilter' => null,
			'EntityFilter' => null,
			'IncludeLineItems' => null, 'IncludeLinkedTxns' => null,
			'IncludeRetElement' => null, 'OwnerID' => null,
		);
		if(! empty($vals['params']) && is_array($vals['params']))
			foreach($vals['params'] as $k=>$v)
				$params[$k] = $v;
		
		$attrs = array();
		if(! empty($vals['attrs']) && is_array($vals['attrs']))
			foreach($vals['attrs'] as $k=>$v)
				$attrs[$k] = $v;		
		
		if(! $this->next_req_id)
			$this->next_req_id = time();
		
		$req = new QBRequest();
		if(! empty($attrs['requestID']))
			$req->sequence = $attrs['requestID'];
		else
			$req->sequence = $this->next_req_id ++;
		foreach(array('sync_phase', 'sync_stage', 'sync_step', 'action', 'related_id') as $f) {
			if(isset($vals[$f])) {
				$req->$f = $vals[$f];
				unset($vals[$f]);
			}
			else $req->$f = '';
		}
		
		$fdefs = AppConfig::setting("model.fields.{$handler->object_name}", array());
		if(!empty($fdefs)) {
			if(isset($fdefs['qb_date_modified']) && empty($handler->disable_date_filter)) {
				$vals['date_limit_table'] = $handler->table_name;
			}
			if(isset($fdefs['qb_type']) && empty($handler->disable_qbtype_filter)) {
				$vals['qbtype_filter'] = $vals['base'];
			}
		}
		$vals['attrs'] = $attrs;
		$vals['params'] = $params;
		$req->params_arr = $vals;

		$req->status = 'pending';
		$req->session_id = $session->id;
		$req->request_type = $type;
		
		if(! $this->optimize_process_request($req))
			return false;
		
		return $req;
	}
	
	
	function optimize_process_request(&$req) {
		$type = $req->request_type;
		$vals =& $req->params_arr;
		$attrs =& $vals['attrs'];
		$params =& $vals['params'];
		$optimize = array_get_default($vals, 'optimize', 'none');
		$txns = array(
			'Invoice',
			'Estimate',
			'ReceivePayment',
			'Bill',
			'BillPaymentCheck',
			'BillPaymentCreditCard',
			'CreditMemo',
			'Check',
			'ARRefundCreditCard',
		);

		$batch_type = qb_base_to_batch_type($vals['base']);
		$batch_dir = ($type == 'import' || $type == 'import_all' || $type == 'import_updated') ? 'import' : 'export';
		$batch_size = qb_batch_size($this->id, $batch_type, $batch_dir);
		if($optimize == 'auto' && ($type == 'import' || $type == 'import_all')) {
			$edition = self::get_server_edition($this);
			$step = "$req->sync_phase/$req->sync_step";
			$prep = "$req->sync_stage:{$vals['preparse']}";
			//qb_log_debug("get date limit ($this->id) ($step) ($prep)");
			$date_limit = QBConfig::get_server_setting($this->id, $step, $prep);
			if($date_limit && ! empty($vals['date_limit_table'])) {

				do {

					// Upgrade from older DB structure - requery payment methods if type is not set
					if($vals['base'] == 'PaymentMethod') {
						$query = "SELECT id FROM qb_paymentmethods WHERE type IS NULL AND server_id='{$this->id}'";
						$res = $this->db->query($query);
						if ($this->db->fetchByAssoc($res)) {
							break;
						}
					}


					if(! empty($vals['qbtype_filter']))
						$qbtype_filter = ' AND qb_type="'.$this->db->quote($vals['qbtype_filter']).'" ';
					else
						$qbtype_filter = '';
					$q = "SELECT MAX(qb_date_modified) AS maxd FROM `{$vals['date_limit_table']}` WHERE NOT deleted ".
						"$qbtype_filter AND server_id='".$this->id."'";
					$r = $this->db->query($q, true, "Error retrieving modified date");
					if($row = $this->db->fetchByAssoc($r)) {
						if($row['maxd'] && $row['maxd'] != '0000-00-00 00:00:00') {
							$req->request_type = 'import_updated';
							$vals['type'] = $req->request_type;
							$ltm = strtotime($row['maxd'].' - 12 hours');
							if($ltm > 0) {
								$dt = date('Y-m-d H:i:s', $ltm);
								$dt = qb_export_date_time($dt, false);
								if(in_array($vals['base'], $txns)) {
									$params['ModifiedDateRangeFilter']['FromModifiedDate'] = $dt;
								}
								else
									$params['FromModifiedDate'] = $dt;
								qb_log_debug("setting from-date filter: $dt");
							}
						}
					}
				} while (false);
			}
			if($type != 'import_all') {
				if($edition == 'US') {
					$optimize = 'iter';
					$attrs['iterator'] = 'Start';
				}
				else if(in_array($vals['base'], $txns)) {
					if(empty($params['ModifiedDateRangeFilter']) && empty($params['TxnID']))
						$optimize = 'bydate'; // or 'bycust'
					else
						$optimize = 'none';
				}
				else {
					$optimize = 'byname';
				}
			}
		}
		else if($optimize == 'iter') {
			$attrs['iterator'] = 'Continue';
			// assume iteratorID is set
		}
		
		if($optimize == 'byname') {
			if(! empty($req->last_import_name)) {
				if(isset($vals['prev_last_import_name']) && $vals['prev_last_import_name'] == $req->last_import_name) {
					// short-circuit so we don't loop forever, importing this one item
					// note: we could also check if the number of returned rows is less than the number
					// we requested, but this seems slightly more reliable (what if we fail to recognize a returned row)
					qb_log_debug("request query: short circuit on repeated import name");
					return false;
				}
				qb_log_debug("repeating {$req->sync_phase} query from name: $req->last_import_name");
				$vals['prev_last_import_name'] = $req->last_import_name;
				$params['NameRangeFilter'] = array('FromName' => $req->last_import_name);
			}
			else if($req->send_count > 1) {
				qb_log_debug("abandoning by-name query optimization: no name found for last query");
				$optimize = 'none';
				$params['NameRangeFilter'] = null;
				$params['MaxReturned'] = null;
			}
		}
		else if($optimize == 'bycust') {
			global $db;
			$lastid = array_get_default($_SESSION, 'last_customer_id_'.$vals['base']);
			$q = "SELECT qb_id FROM qb_entities WHERE qb_type='Customer' AND qb_is_active AND NOT deleted AND qb_id IS NOT NULL";
			if($lastid)
				$q .= " AND qb_id > '".$db->quote($lastid)."' ";
			$q .= " ORDER BY qb_id LIMIT 101";
			$r = $db->query($q, true, "Error querying customer IDs");
			$qids = array();
			while($row = $db->fetchByAssoc($r)) {
				$qids[] = $row['qb_id'];
			}
			if($qids) {
				if(count($qids) > 100) {
					array_pop($qids);
					$req->iter_remain = 1;
				}
				else
					$req->iter_remain = 0;
				$params['EntityFilter'] = array('ListID' => $qids);
				$_SESSION['last_customer_id_'.$vals['base']] = $qids[count($qids)-1];
			}
			else
				return false;
		}
		else if($optimize == 'bydate') {
			$min_date = -60; // start 60 months ago
			$df_offs = 2;
			$last_date = array_get_default($_SESSION, 'last_filter_date_'.$vals['base']);
			$req->iter_remain = 1;
			if(! $last_date) {
				$last_date = $min_date;
				$now = time();
				$now -= $now % 86400;
				$_SESSION['filter_now_date_'.$vals['base']] = $now;
			}
			else {
				$last_date += $df_offs;
				if($last_date >= 0)
					$req->iter_remain = 0;
				$now = $_SESSION['filter_now_date_'.$vals['base']];
			}
			$last = date('Y-m-d', $now + (($last_date - $df_offs) * 86400 * 30));
			$next = date('Y-m-d', $now + ($last_date * 86400 * 30));
			$params['TxnDateRangeFilter'] = array(
				'FromTxnDate' => ($last_date == $min_date ? null : $last),
				'ToTxnDate' => ($last_date >= 0 ? null : $next),
			);
			$_SESSION['last_filter_date_'.$vals['base']] = $last_date;
		}
		
		if($optimize == 'iter' || $optimize == 'iter_next' || $optimize == 'byname' || $optimize == 'bydate') {
			if(empty($params['MaxReturned'])) {
				if(isset($vals['per_request']))
					$params['MaxReturned'] = $vals['per_request'];
				else
					$params['MaxReturned'] = $batch_size;
			}
		}
		
		$vals['optimize'] = $optimize;
		return true;
	}
	
	
	function encode_requests(&$requests, &$enc_count, $stop_on_error=false) {
		$parser = $this->get_parser();
		$header = '<?xml version="1.0" encoding="'. $this->qbxml_encoding . '" ?>'. "\r\n".
			'<?qbxml version="' .$this->get_qbxml_version(). '" ?>'. "\r\n".
			"<QBXML>\r\n";
		
		$on_error = ($stop_on_error) ? 'stopOnError' : 'continueOnError';
		$header .= '<QBXMLMsgsRq onError="' .$on_error. '">'. "\r\n";
		
		$max_send = 100;
		$send_count = 0;
		$body = '';
		for(reset($requests); ($k = key($requests)) !== null; next($requests)) {
			if($requests[$k]->status == 'pending') {
				$req =& $requests[$k];
				if(!empty($req->no_batch) && $send_count)
					break;
				if(empty($req->params_arr))
					continue;
				$vals =& $req->params_arr;
				
				$req_params = $parser->encode_params($vals['params']);
				$attrs = array("requestID=\"{$req->sequence}\"");
				foreach($vals['attrs'] as $idx => $val) {
					$val = $parser->xml_escape($val);
					$attrs[] = "$idx=\"$val\"";
				}
				$req_attrs = implode(' ', $attrs);
		
				$req->qbxml = '<' .$vals['operation']. ' ' .$req_attrs. '>'.
						$req_params.
						'</' .$vals['operation']. ">\r\n";
				
				$body .= $req->qbxml;
				$enc_count ++;
				$send_count ++;
				if(!empty($req->no_batch) || $send_count >= $max_send)
					break;
			}
			else
				$enc_count ++;
		}
		
		$footer = "</QBXMLMsgsRq>\r\n".
				"</QBXML>\r\n";
		if(! $body)
			return '';
		$qbxml = $header . $body . $footer;
		
		if($this->asciify_text) {
			$qbxml = qb_asciify_string($this->qbxml_encoding, $qbxml);
		}
		else if(strtolower($this->qbxml_encoding) != 'utf-8') {
			$qbxml = mb_convert_encoding($qbxml, $this->qbxml_encoding);
		}
				
		return $qbxml;
	}
	
	
	function parse_response(&$qbxml) {
		$parser = $this->get_parser();
		$this->parse_error = '';
		if($parser->parse($qbxml)) {
			return $parser->responses;
		}
		else {
			$this->parse_error = $parser->parse_error;
			return false;
		}
	}
	
	function format_response(&$val, &$req, &$errs) {
		if(is_array($req->params_arr))
			$fmt = array_get_default($req->params_arr, 'preparse', '');
		else $fmt = '';
		$fmt = explode(':', $fmt);
		if(count($fmt) < 2)
			$fmt[] = '';
		if($fmt[0] == 'list') {
			if(! isset($val[$fmt[1]])) {
				$errs[] = "Expected list response element '{$fmt[1]}'";
				return false;
			}
			$val = $val[$fmt[1]];
			if(! is_array($val))
				$val = array();
		}
		else if($fmt[0] == 'export' || $fmt[0] == 'update') {
			for($i = 1; $i < count($fmt) && strlen($fmt[$i]); $i++) {
				if(! isset($val[$fmt[$i]])) {
					$errs[] = "Expected export response element '{$fmt[$i]}'";
					return false;
				}
				$val = $val[$fmt[$i]];
			}
		}
		else if($fmt[0] == 'delete') {
			for($i = 1; $i < count($fmt) && strlen($fmt[$i]); $i++) {
				if(! isset($val[$fmt[$i]])) {
					$errs[] = "Expected delete response element '{$fmt[$i]}'";
					return false;
				}
				$val = $val[$fmt[$i]];
			}
		}
		return true;
	}
	
	function set_sync_result($status, $message='') {
		if($this->last_sync_result == 'aborted' && $status == 'success')
			// must go through 'pending' first
			return;
		$this->last_sync_result = $status;
		$this->last_sync_msg = $message;
		$this->_status_updated = true;
	}
	
	function status_updated() {
		return ! empty($this->_status_updated);
	}
}
