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
require_once('modules/QBLink/QBCurrency.php');
require_once('modules/QBLink/QBTerms.php');
require_once('modules/QBLink/QBCustomerType.php');
require_once('modules/Accounts/Account.php');
require_once('modules/Contacts/Contact.php');
//require_once('modules/Opportunities/Opportunity.php');
//require_once('modules/Project/Project.php');


class QBEntity extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $qb_type;
	var $parent_qb_id;
	
	var $first_sync;
	var $sync_status;
	var $status_msg;

	var $account_id;
	var $contact_id;
	// possibly used in future
	//var $opportunity_id;
	//var $project_id;
	//var $employee_id;

	// - runtime fields
	
	var $account_name;
	var $contact_name;
	var $opportunity_name;
	var $project_name;
	
	// - static fields
	
	var $object_name = 'QBEntity';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_entities';
	
	var $qb_query_type = 'Entity';
	var $listview_template = "EntityListView.html";
	var $search_template = "EntitySearchForm.html";
	
	var $update_field_map = array(
		'TimeCreated' => 'qb_date_entered',
		'TimeModified' => 'qb_date_modified',
		'IsActive' => 'qb_is_active',
	);
	
	var $account_field_map = array(
		'Phone' => 'phone_office',
		'AltPhone' => 'phone_alternate',
		'Fax' => 'phone_fax',
		'Email' => 'email1',
		'Notes' => 'description',
		'CreditLimit' => 'credit_limit',
	);
	
	var $contact_field_map = array(
		'Phone' => 'phone_work',
		'Mobile' => 'phone_mobile',
		'AltPhone' => 'phone_other',
		'Fax' => 'phone_fax',
		'Email' => 'email1',
		'Notes' => 'description',
	);
	
	var $qb_field_map = array(
		'ParentRef' => 'parent_qb_id',
	);

	var $bases = array(
		'Customers' => 'Customer',
		'Vendors' => 'Vendor',
	);

	var $_sync_date;

	function save($check_notify=false) {
		if($this->qb_type == 'Customer' && strpos($this->name, ':') !== false)
			$this->qb_type = 'Job';
		return parent::save($check_notify);
	}
	
	function &retrieve_for_account($acct_id, $server_id) {
		$acct_id = $this->db->quote($acct_id);
		$sid = $this->db->quote($server_id);
		$q = "SELECT id FROM {$this->table_name} WHERE account_id='$acct_id' AND server_id='$sid' AND NOT deleted";
		$r = $this->db->query($q, true, "Error finding related QBEntity");
		$ret = null;
		if($row = $this->db->fetchByAssoc($r)) {
			$ret = $this->retrieve($row['id']);
		}
		return $ret;
	}
	
	// -- Import handling
	
	function &get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if(empty($this->bases[$phase])) {
			qb_log_debug("QBTally - no handler for phase $phase");
			return $reqs;
		}
		$base = $this->bases[$phase];
		$edition = QBServer::get_server_edition($server_id);
		if(QBConfig::get_server_setting($server_id, 'Import', $phase)) {
			if($stage == 'import') {
				$reqs[] = array(
					'type' => 'import',
					'base' => $base,
					'optimize' => 'auto',
					'params' => array(
						'ActiveStatus' => 'All',
					),
				);
				// need a separate phase for Vendors, and maybe Employees and OtherNames
				// when added, make sure qb_type is used with add_import_requests etc.
			} elseif ($stage == 'ext_import') {
				$this->add_import_requests($server_id, $reqs, qb_batch_size($server_id, 'Accounts', 'import'), false, $base);
			}
		}
		if(QBConfig::get_server_setting($server_id, 'Export', $phase)) {
			if($stage == 'export') {
				$this->register_pending_exports($server_id, 1000, $phase);
				$this->add_export_requests($server_id, $reqs, qb_batch_size($server_id, 'Accounts', 'export'), false, $base);
			}
			else if($stage == 'reg_update') {
				$this->register_pending_updates($server_id);
			}
			else if($stage == 'pre_update') {
				// we always grab object details if available
				// these are especially needed if import is disabled
				$this->add_import_requests($server_id, $reqs, qb_batch_size($server_id, 'Accounts', 'import'), true, $base);
			}
			else if($stage == 'update') {
				$this->add_export_requests($server_id, $reqs, qb_batch_size($server_id, 'Accounts', 'export'), true, $base);
			}
			else if($stage == 'delete') {
				// must not be open in multi-user mode
				if(! QBServer::get_server_multi_user($server_id))
					$this->add_delete_requests($server_id, $reqs, qb_batch_size($server_id, 'Accounts', 'export'));
			}
		}
		return $reqs;
	}
	
	
	function register_import_without_id(&$bean) {
		$this->pre_import_by_name[$bean->qb_type][$bean->id] = $bean->name;
	}
	
	function get_extra_import_requests(&$reqs) {
		if(! isset($this->pre_import_by_name) || ! is_array($this->pre_import_by_name)) {
			return;
		}
		foreach($this->pre_import_by_name as $cat => $ns) {
			$names = array_values($ns);
			if($names) {
				$import_req = array(
					'type' => 'import',
					'base' => $cat,
					'params' => array(
						'FullName' => $names,
					),
				);
				$reqs[] = $import_req;
			}
		}
		unset($this->pre_import_by_name);
	}
	
	
	function perform_sync($mode, $qb_type, &$details, &$errmsg, &$newreqs) {
		if($qb_type == 'Employee')
			return false;
		
		$update = ! empty($this->id) && ! empty($this->account_id);
		$override = $this->sync_status == 'pending_update';
		$this->qb_type = $qb_type;
		
		if($mode == 'post_iah_update')
			return true; // nothing to do

		if(! $update || ! $override) {
			$this->_sync_date = null;
			$id = $this->import_account($details, $update);
			if(! empty($this->prevent_save))
				return true;
			if($id)
				$this->account_id = $id;
		
			$id = $this->import_contact($details, $update);
			if($id)
				$this->contact_id = $id;
		
			if($this->account_id && $this->contact_id) {
				// slow version
				/*$acct = new Account();
				if($acct->retrieve($this->account_id)) {
					$acct->primary_contact_id = $this->contact_id;
					$acct->save();
				}*/
				$fdefs = AppConfig::setting("model.fields.Account", array());
				if(isset($fdefs['primary_contact_id'])) {
					$query = sprintf("UPDATE accounts SET primary_contact_id='%s' WHERE id='%s'",
						$this->contact_id, $this->account_id);
					$this->db->query($query, false);
				}
			}
			if (empty($this->_sync_date)) {
				$this->_sync_date = qb_date_last_sync();
			}
			$this->date_last_sync = $this->_sync_date;
			$this->sync_status = '';
		}
		
		return true;
	}
	
	
	function import_account(&$details, $update=false) {
		global $current_user;
		$account_name = '';
		if(isset($details['Name']))
			$account_name = $details['Name'];
		// $details['CompanyName'] - ignored - could be used for parentage?
		if($account_name === '')
			return '';
		$account_name = trim($account_name);
		$this->name = $account_name;
		
		if($update) {
			if(! $this->fetch_account())
				return false;
			$focus =& $this->account;
		}
		else {
			if(empty($this->id)) {
				$query = "SELECT id FROM qb_entities WHERE qb_type='" . $this->db->quote($this->qb_type) . "' AND name='".$this->db->quote($account_name)
					."' AND (qb_id IS NULL OR qb_id='') AND server_id='".$this->server_id."' AND NOT deleted";
				$result = $this->db->query($query, true);
				if($row = $this->db->fetchByAssoc($result)) {
					$other = new QBEntity();
					$other->retrieve($row['id']);

					/*
					if ($other->qb_type != $this->qb_type) {
						$this->sync_status = 'import_error';
						$this->status_msg = "Duplicate account name. Check if $other->qb_type with this name exists" ;
						return '';
					}
					 */

					$other->qb_id = $this->qb_id;
					$other->qb_type = $this->qb_type;
					$other->qb_editseq = $this->qb_editseq;
					$other->first_sync = 'imported'; // should technically be another state (reconciled?)
					$other->sync_status = '';
					$other->status_msg = '';
					$other->save();
					$this->prevent_save = true;
					return;
				}
			}

			if ($this->qb_type == 'Vendor') {
				$is_supplier = 1;
			} else {
				$is_supplier = 0;
			}
			$focus = new Account();
			$query = "SELECT id FROM accounts WHERE name='".$this->db->quote($account_name)."' AND is_supplier=$is_supplier AND NOT deleted";
			// also check mappings of previously imported CompanyNames to Accounts?
			$result = $this->db->query($query, true);
			if($row = $this->db->fetchByAssoc($result)) {
				$focus->retrieve($row['id']);

				/*
				if ((bool)$focus->is_supplier ^ ($this->qb_type == 'Vendor')) {
					$this->sync_status = 'import_error';
					$this->status_msg = "Duplicate account name. Check if $other->qb_type with this name exists" ;
					return '';
				}
				 */

				return $focus->id;
			}
			$focus->date_entered = qb_import_date_time($details['TimeCreated'], false);
			$focus->assigned_user_id = $current_user->id;
		}

		if($update) {
			static $srv;
			if(! isset($srv)) {
				$srv = new QBServer();
				$srv->retrieve_primary();
			}
			$old_name = qb_export_name($srv, $focus->name);
			if($old_name != $account_name)
				$focus->name = $account_name;
		}
		else
			$focus->name = $account_name;
		foreach($this->account_field_map as $qb_f => $f) {
			if(isset($details[$qb_f]))
				$focus->$f = $details[$qb_f];
		}
		
		if(isset($details['CurrencyRef'])) {
			$iah_cur = QBCurrency::to_iah_currency($this->server_id, $details['CurrencyRef']['ListID']);
			if(! $iah_cur) {
				$this->sync_status = 'import_error';
				$this->status_msg = 'Currency unknown';
				return '';
			}
			$focus->currency_id = $iah_cur->id;
		}
		else {
			$iah_cur = QBCurrency::get_qb_import_currency($this->server_id);
			$focus->currency_id = $iah_cur->id;
		}
		
		if(isset($details['TermsRef']))
			$terms = QBTerms::to_iah_terms($this->server_id, $details['TermsRef']['ListID']);
		else
			$terms = '';
				
		if($this->qb_type == 'Vendor') {
			$focus->purchase_credit_limit = $focus->credit_limit;
			$focus->credit_limit = null;
			$focus->is_supplier = 1;
			$focus->default_purchase_terms = $terms;
		}
		else {
			$focus->default_terms = $terms;
		}
		
		if(isset($details['CustomerTypeRef'])) {	
			$t = QBCustomerType::to_iah_type($this->server_id, $details['CustomerTypeRef']['ListID']);
			if($t) $focus->account_type = $t;
		}
		
		// TaxCodeRef - not used
		
		if($this->parent_qb_id) {
			$par = new QBEntity();
			if($par->qb_retrieve($this->parent_qb_id, $this->server_id))
				$this->parent_id = $par->account_id;
		}
		
		// need to set default discount level
		
		$this->set_addresses($focus, $details, 'billing_address_', 'shipping_address_');
		
		$acc_id = $focus->save();
		$this->_sync_date = $focus->date_modified;
		if(! $update)
			qb_log_debug("created new account {$acc_id}");
		return $acc_id;
	}
	
	
	function set_addresses(&$focus, &$details, $pfx1, $pfx2) {
		if(isset($details['BillAddress']))
			$bill_addr = $details['BillAddress'];
		else if(isset($details['VendorAddress']))
			$bill_addr = $details['VendorAddress'];
		if(! empty($bill_addr)) {
			foreach($this->import_address($bill_addr) as $f => $v) {
				$set_f = $pfx1 . $f;
				$focus->$set_f = $v;
			}
		}
		if(isset($details['ShipAddress'])) {
			foreach($this->import_address($details['ShipAddress']) as $f => $v) {
				$set_f = $pfx2 . $f;
				$focus->$set_f = $v;
			}
		}
	}

	
	function import_contact(&$details, $update=false) {
		global $current_user;
		$first_name = $last_name = '';
		if(! empty($details['FirstName']) || ! empty($details['LastName'])) {
			if(! isset($details['LastName']) || $details['LastName'] === '')
				$last_name = $details['FirstName'];
			else {
				$last_name = $details['LastName'];
				if(isset($details['FirstName']))
					$first_name = $details['FirstName'];
			}
		}
		if($last_name === '')
			return '';
		
		$focus = new Contact;
		if($update && $this->contact_id) {
			if(! $focus->retrieve($this->contact_id))
				return false;
		}
		else {
			if($this->account_id) {
				$query = "SELECT contacts.id FROM accounts_contacts rel ".
					"LEFT JOIN contacts ON contacts.id=rel.contact_id ".
					"WHERE rel.account_id='".$this->db->quote($this->account_id)."' ".
					"AND NOT rel.deleted AND NOT contacts.deleted AND ";
			}
			else {
				$query = "SELECT contacts.id FROM contacts WHERE ";
			}
			$query .= "contacts.first_name='".$this->db->quote($first_name)."' ".
					" AND contacts.last_name='".$this->db->quote($last_name)."'";
			// also check mappings of previously imported CompanyNames to Accounts?
			$result = $this->db->query($query, true);
			if($row = $this->db->fetchByAssoc($result)) {
				if($focus->retrieve($row['id']))
					return $focus->id;
			}
			$focus->date_entered = qb_import_date_time($details['TimeCreated'], false);
			$focus->assigned_user_id = $current_user->id;
		}
		
		$focus->first_name = $first_name;
		$focus->last_name  = $last_name;
		foreach($this->contact_field_map as $qb_f => $f) {
			if(isset($details[$qb_f]))
				$focus->$f = $details[$qb_f];
		}
		
		$this->set_addresses($focus, $details, 'primary_address_', 'alt_address_');
		
		if(empty($focus->account_id))
			$focus->account_id = $this->account_id;
		$ctc_id = $focus->save();
		if(! $update)
			qb_log_debug("created new contact {$ctc_id}");
		return $ctc_id;
	}
	
	
	/* not used
	function import_opportunity(&$details) {
		global $app_list_strings;
		
		$job_name = '';
		if(empty($details['JobStatus']) || $details['JobStatus'] == 'None')
			return '';
		if(isset($details['JobDesc']))
			$job_name = $details['JobDesc'];
		else
			$job_name = $details['Name'];
		if($job_name === '')
			return '';
				
		$focus = new Opportunity;
		$focus->name = $job_name;
		
		// JobStartDate currently ignored
		$end_date = '';
		if(! empty($details['JobEndDate']))
			$end_date = $details['JobEndDate'];
		else if(! empty($details['JobProjectedEndDate']))
			$end_date = $details['JobProjectedEndDate'];
		$focus->date_closed = $end_date;
		
		switch($details['JobStatus']) {
			case 'Awarded':
			case 'InProgress':
				// create project, then fall through
			case 'Closed':
				$focus->sales_stage = 'Closed Won';
				$focus->probability = '100';
				break;
			case 'NotAwarded':
				$focus->sales_stage = 'Closed Lost';
				$focus->probability = '0';
				break;
			case 'Pending':
				$focus->sales_stage = 'Prospecting';
				$focus->probability = '50'; // best guess - user can adjust
				break;
		}

		// IAH only		
		if(isset($app_list_strings['sales_forecast_dom']))
			$focus->forecast_category = $app_list_strings['sales_forecast_dom'][$focus->sales_stage];
		
		$focus->account_id = $this->account_id;
		$focus->save();
		$this->conn->log_debug("created new opportunity {$focus->id}");
		return $focus->id;
	}
	*/

	
	function import_address(&$addr) {
		$ret = array('street' => '', 'city' => '', 'state' => '', 'postalcode' => '', 'country' => '');
		$street_addr = array();
		foreach(array('Addr1', 'Addr2', 'Addr3', 'Addr4') as $qb_f) {
			if(isset($addr[$qb_f]) && trim($addr[$qb_f]) !== '')
				$street_addr[] = $addr[$qb_f];
		}
		$ret['street'] = implode("\n", $street_addr);
		foreach(array('City', 'PostalCode', 'Country') as $qb_f) {
			if(isset($addr[$qb_f]))
				$ret[strtolower($qb_f)] = $addr[$qb_f];
		}
		if(isset($addr['State']))
			$ret['state'] = $addr['State'];
		else if(isset($addr['Province']))
			$ret['state'] = $addr['Province'];
		else if(isset($addr['County']))
			$ret['state'] = $addr['County'];
		return $ret;
	}

	
	// -- Export handling
	
	
	function fetch_account() {
		$acct = new Account();
		if($acct->retrieve($this->account_id, false)) { // do not HTML-escape field values
			$this->account =& $acct;
			return true;
		}
		return false;
	}
		
	
	function get_export_request(&$ret, &$errmsg, $update=false) {
		$base = $this->qb_type;
		$ret = array(
			'type' => $update ? 'update' : 'export',
			'base' => $base,
			//'action' => 'post_export_update',
		);
		if(empty($this->account)) {
			if(empty($this->account_id) || ! $this->fetch_account()) {
				$errmsg = "No associated account";
				return false;
			}
		}
		$acct =& $this->account;
		$this->name = $acct->name;

		$details = array(
			'ListID' => null,
			'EditSequence' => null,
			'Name' => $this->name,
			'CompanyName' => null,
			'Salutation' => null,
			'FirstName' => null,
			'LastName' => null,
			'BillAddress' => null,
			'ShipAddress' => null,
			'VendorAddress' => null,
			'Phone' => null,
			'AltPhone' => null,
			'Fax' => null,
			'Email' => null,
			'Contact' => null,
			'AltContact' => null,
			'CustomerTypeRef' => null,
			'TermsRef' => null,
			'CreditLimit' => null,
			'Notes' => null,
		);

		if (!$update) {
			$details['CompanyName'] = $this->name;
		}

		if ($base == 'Customer') {
			$details['BillAddress'] = qb_export_address($this->server_id, $acct, 'billing_address_');
			$details['ShipAddress'] = qb_export_address($this->server_id, $acct, 'shipping_address_');
		}
		if ($base == 'Vendor') {
			$details['VendorAddress'] = qb_export_address($this->server_id, $acct, 'billing_address_');
		}
		if($update) {
			$details['ListID'] = $this->qb_id;
			$details['EditSequence'] = $this->qb_editseq;
		}
		foreach($this->account_field_map as $qb_f => $f) {
			if(isset($acct->$f) && $acct->$f !== '') {
				$details[$qb_f] = $acct->$f;
			}
		}
		
		$edition = QBServer::get_server_edition($this->server_id);
		if($edition == 'CA')
			unset($details['Mobile']); // not supported
		
		if(! empty($acct->account_type)) {
			$ctype = QBCustomerType::from_iah_type($this->server_id, $acct->account_type);
			if($ctype)
				$details['CustomerTypeRef'] = $ctype->get_ref();
		}

		// TODO implement VendorType
		if ($base == 'Vendor') {
			$details['CustomerTypeRef'] = null;
		}
		
		if(! empty($acct->default_terms)) {
			$terms = QBTerms::from_iah_terms($this->server_id, $acct->default_terms);
			if($terms)
				$details['TermsRef'] = $terms->get_ref();
		}
		
		if(isset($acct->credit_limit) && $acct->credit_limit !== '')
			$details['CreditLimit'] = qb_format_price($acct->credit_limit);
		
		$multi = QBConfig::get_server_setting($this->server_id, 'Server', 'multi_currency');
		if($multi) {
			$currency = QBCurrency::from_iah_currency($this->server_id, $acct->currency_id);
			if(! $currency) {
				$errmsg = "Currency not mapped";
				$this->retry_export_later = true;
				return false;
			}
			$details['CurrencyRef'] = $currency->get_ref();
		}
		
		if(! $update) {
			// IAH only
			/*if(isset($acct->balance) && $acct->balance !== '') {
				$details['OpenBalance'] = sprintf('%.2f', $acct->balance);
				$details['OpenBalanceDate'] = date('Y-m-d');
			}*/
			// FIXME - cannot set opening balance.
			// need to create an initial invoice
		}
		
		if($acct->primary_contact_id && ! $this->contact_id)
			$this->contact_id = $acct->primary_contact_id;
		$ctc = new Contact();
		if($this->contact_id && $ctc->retrieve($this->contact_id, false)) {
			global $app_list_strings;
			$details['Salutation'] = $app_list_strings['salutation_dom'][$ctc->salutation];
			$details['FirstName'] = $ctc->first_name;
			$details['LastName'] = $ctc->last_name;
			$details['Contact'] = $ctc->first_name.' '.$ctc->last_name;
		}
		
		$this->date_last_sync = $acct->date_modified;

		$details = $this->reorderDetails($details, $base);
		
		$op = $update ? 'Mod' : 'Add';
		$ret['params'][$base.$op] =& $details;
		//qb_log_debug($ret);
		return true;
	}
	
	
	/* not used
	function init_from_opportunity($opportunity_id, $account_id, $parent_id) {
		$this->init_from_account($account_id);
		$this->opportunity_id = $opportunity_id;
		$opp = new Opportunity();
		if($opp->retrieve($opportunity_id, false)) { // do not HTML-escape field values
			$this->name = $opp->name;
			
			$this->export_details['Name'] = $this->name;
			$this->export_details['Notes'] = $this->description;
			
			$status = '';
			switch($opp->sales_stage) {
				case 'Closed Won':
					// Awarded or InProgress if project exists
					$status = 'Closed';
					break;
				case 'Closed Lost':
					$status = 'NotAwarded';
					break;
				default:
					$status = 'Pending';
					break;
			}
			$this->export_details['JobStatus'] = $status;
			
			// JobStartDate is given by project - or could be opportunity's creation date
			// no balance unless a project is also found - ignoring opportunity amount?
			
			$this->export_details['JobProjectedEndDate'] = $opp->date_closed;
			if($opp->sales_stage == 'Closed Won')
				$this->export_details['JobEndDate'] = $opp->date_closed;
			$this->export_details['JobDesc'] = $this->name;
			
			return true;
		}
		return false;
	}
	*/
	
	
	function register_export_accounts(&$added, $server_id, $vendors, $max_register=-1) {
		$inv_only = false;
		$qb_type = $vendors ? 'Vendor' : 'Customer';
		if ($vendors) {
			$inv_table = 'bills';
			$vendor_clause = 'AND acc.is_supplier';
			$account_id = 'supplier_id';
			$inv_only = QBConfig::get_server_setting($server_id, 'Export', 'OnlyBilledAccounts');
		} else {
			$inv_table = 'invoice';
			$vendor_clause = 'AND (!acc.is_supplier OR acc.is_supplier IS NULL)';
			$account_id = 'billing_account_id';
			$inv_only = QBConfig::get_server_setting($server_id, 'Export', 'OnlyInvoicedAccounts');
		}

		$sid = $this->db->quote($server_id);
		if($inv_only) {
			$query = "SELECT DISTINCT acc.id, acc.name FROM {$inv_table} inv ".
				"LEFT JOIN accounts acc ON inv.$account_id=acc.id ".
				"LEFT JOIN {$this->table_name} ent ".
					"ON (ent.server_id='$sid' AND ent.account_id=acc.id AND NOT ent.deleted AND ent.qb_type = '$qb_type') ".
				"WHERE NOT inv.deleted AND ent.id IS NULL AND NOT acc.deleted ".
				"ORDER BY acc.name";
		}
		else {
			$query = "SELECT acc.id, acc.name, acc.is_supplier FROM accounts acc ".
					"LEFT JOIN {$this->table_name} ent ".
						"ON (ent.server_id='$sid' AND ent.account_id=acc.id AND NOT ent.deleted AND ent.qb_type = '$qb_type') ".
					"WHERE ent.id IS NULL AND NOT acc.deleted $vendor_clause ".
					"ORDER BY acc.name";
		}
		if($max_register > 0) $query .= " LIMIT $max_register";
		$result = $this->db->query($query, true, "Error retrieving Account IDs for export");
		while($row = $this->db->fetchByAssoc($result)) {
			if($max_register >= 0 && count($added) >= $max_register)
				break;
			$seed = new QBEntity();
			$seed->qb_type = $qb_type;
			$seed->account_id = $row['id'];
			$seed->name = $row['name'];
			$seed->sync_status = 'pending_export';
			$seed->status_msg = '';
			$seed->server_id = $server_id;
			$seed->save();
			$added[] = $seed->id;
		
		}
		return true;
	}
	

	/* not used
	function register_export_opportunities(&$added, $max_register=-1) {
		$query = "SELECT opps.id, opps.account_id, e1.id as parent_id FROM opportunities opps ".
				"LEFT JOIN {$this->table_name} e1 ON e1.account_id=opps.account_id ".
				"LEFT JOIN {$this->table_name} e2 ON e2.opportunity_id=opps.id ".
				"WHERE e1.id IS NOT NULL AND e2.id IS NULL NOT opps.deleted ".
				"ORDER BY opps.name";
		if($max_register > 0) $query .= " LIMIT $max_register";
		$result = $this->db->query($query, true, "Error retrieving Opportunity IDs for export");
		$templates = array();
		$i = 0;
		while($row = $this->db->fetchByAssoc($result)) {
			$opp = new QBEntity();
			if($opp->init_from_opportunity($row['id'], $row['account_id'], $row['parent_id']))
				$templates[] = $opp;
			$i++;
			if($i >= 1)
				break;
		}
		if(! $this->qb_export($templates))
			return false;
		$ret = array();
		$numt = count($templates);
		for($i = 0; $i < $numt; $i++) {
			if(! empty($templates[$i]->id))
				$ret[$templates[$i]->id] = $templates[$i]->account_id;
		}
		return $ret;
	}
	*/
	
	
	function &register_pending_exports($server_id, $max_register=-1, $phase = null) {
		$added = array();


		if(QBConfig::get_server_setting($server_id, 'Export', 'Vendors') && ($phase == 'Vendors' || $phase === null)) {
			$this->register_export_accounts($added, $server_id, true, $max_register);
		}
		if(QBConfig::get_server_setting($server_id, 'Export', 'Customers') && ($phase == 'Customers' || $phase === null)) {
			$this->register_export_accounts($added, $server_id, false, $max_register);
		}
		//if($max_register)
		//	$this->register_export_opportunities($added, $max_register);
		return $added;
	}
	
		
	function register_pending_updates($server_id) {
		$rel_date = 'date_last_sync';
		$tbl = 'accounts';
		
		$sid = $this->db->quote($server_id);
		$query = "UPDATE `{$this->table_name}` me ".
				"LEFT JOIN `$tbl` rel ON rel.id=me.account_id ".
				"SET me.sync_status='pending_update' ".
				"WHERE me.server_id='$sid' ".
				"AND me.first_sync IN ('imported','exported') ".
				"AND (me.sync_status='' OR me.sync_status IS NULL) ".
				"AND me.$rel_date IS NOT NULL ".
				"AND me.$rel_date < rel.date_modified ".
				"AND rel.id IS NOT NULL AND NOT me.deleted";
		//qb_log_info($query);
		$result = $this->db->query($query, false);
		if(! $result) {
			qb_log_error("Error marking entities for update");
			return false;
		}
		return true;
	}
	
	
	// -- SugarBean overrides and utility methods
	
	function fill_in_additional_list_fields() {
		$query = "SELECT ".
				"accounts.name AS account_name ".
				", CONCAT_WS(contacts.first_name, contacts.last_name) AS contact_name ".
				//", opportunities.name AS opportunity_name ".
				//", project.name AS project_name ".
			"FROM {$this->table_name} ".
			"LEFT JOIN accounts ON accounts.id={$this->table_name}.account_id AND NOT accounts.deleted ".
			"LEFT JOIN contacts ON contacts.id={$this->table_name}.contact_id AND NOT contacts.deleted ".
			//"LEFT JOIN opportunities ON opportunities.id={$this->table_name}.opportunity_id AND NOT opportunities.deleted ".
			//"LEFT JOIN project ON project.id={$this->table_name}.project_id AND NOT project.deleted ".
			"WHERE {$this->table_name}.id='{$this->id}' ".
			"LIMIT 1";
		$result = $this->db->query($query, true, "Error retrieving related object names");
		if($row = $this->db->fetchByAssoc($result))
			foreach($row as $k=>$v) $this->$k = $v;
	}
	
	function get_list_view_data() {
		$row_data = parent::get_list_view_data();
		
		$row_data['ACCOUNT_LINK'] = $this->get_linked_icon('account', 'Accounts');
		$row_data['CONTACT_LINK'] = $this->get_linked_icon('contact', 'Contacts');
		$row_data['ACCOUNT_LINK'] .= $row_data['CONTACT_LINK'];
		//$row_data['OPPORTUNITY_LINK'] = $this->get_linked_icon('opportunity', 'Opportunities');
		//$row_data['PROJECT_LINK'] = $this->get_linked_icon('project', 'Project');
		
		return $row_data;
	}
	
	function qb_re_register($qb_id) {
		// update short/long names; mark deleted if necessary; mark pending update if necessary
		global $timedate;
		$seed = new QBEntity();
		if(! $seed->retrieve($qb_id))
			return false;
		$upd = false;
		$no_account = true;
		$no_contact = true;
		if($seed->account_id && $seed->fetch_account() && ! $seed->account->deleted) {
			$no_account = false;
			$seed->name = $seed->account->name;
			$seed->set_update_status_if_modified($seed->account);
			$upd = true;
		}
		if($seed->contact_id && $seed->fetch_contact() && ! $seed->contact->deleted) {
			$no_contact = false;
		}
		if($no_account && $no_contact) {
			$seed->deleted = 1;
			$upd = true;
		}
		if($upd)
			$seed->save();
		return true;
	}


	function reorderDetails($details, $base)
	{
		switch ($base) {
			case 'Vendor':
				$order = array(
					'ListID',
					'EditSequence',
					'Name',
					'CompanyName',
					'Salutation',
					'FirstName',
					'LastName',
					'BillAddress',
					'ShipAddress',
					'VendorAddress',
					'Phone',
					'AltPhone',
					'Fax',
					'Email',
					'Contact',
					'AltContact',
					'Notes',
					'VendorTypeRef',
					'TermsRef',
					'CreditLimit',
				);

				break;
			default:
				return $details;
		}

		$ret = array();

		foreach ($order as $k) {
			if (isset($details[$k])) {
				$ret[$k] = $details[$k];
				unset($details[$k]);
			}
		}
		foreach ($details as $k => $v) {
			$ret[$k] = $v;
		}
		return $ret;
	}

	
	function get_qb_type_dom()
	{
		global $app_list_strings;
		$dom = $app_list_strings['qb_entity_types_dom'];
		return array(''=>'') + $dom;
	}

}

?>
