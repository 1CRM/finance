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
require_once('modules/QBLink/QBItem.php');
require_once('modules/QBLink/QBEntity.php');
require_once('modules/QBLink/QBCurrency.php');
require_once('modules/QBLink/QBTerms.php');
require_once('modules/QBLink/QBShipMethod.php');
require_once('modules/QBLink/QBPaymentMethod.php');
require_once('modules/QBLink/QBTaxCode.php');
require_once('modules/QBLink/QBAccount.php');
require_once('modules/Accounts/Account.php');
require_once('modules/Quotes/Quote.php');
require_once('modules/Invoice/Invoice.php');
require_once('modules/CreditNotes/CreditNote.php');
require_once('modules/Payments/Payment.php');
require_once('modules/Bills/Bill.php');

require_once 'include/database/ListQuery.php';
require_once 'include/database/RowUpdate.php';
require_once 'include/Tally/TallyUpdate.php';

define('MAX_TALLY_EXPORT_ATTEMPTS', 5);


class QBTally extends QBBean {

	// - saved fields
	
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $shortname;
	var $qb_type;
	var $parent_qb_id;
	
	var $first_sync;
	var $sync_status;
	var $status_msg;

	var $system_type;
	var $system_id;

	// - runtime fields
	
	var $system_name;
	
	// - static fields
	
	var $object_name = 'QBTally';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_tallies';
	
	var $qb_query_type = '';
	var $listview_template = "TallyListView.html";
	var $search_template = "TallySearchForm.html";
	
	var $tally_field_map = array(
		'Phone' => 'phone_work',
		'AltPhone' => 'phone_other',
		'Fax' => 'phone_fax',
		'Mobile' => 'phone_mobile',
		'Email' => 'email1',
		'Notes' => 'description',
	);
	
	var $qb_is_transaction_type = 1;
	
	var $phases = array(
		'Estimates' => array(
			'base' => 'Estimate',
			'iah_table' => 'quotes',
			'import_chk' => 'Estimates',
			'export_chk' => 'Quotes',
			'module' => 'Quotes',
		),
		'Invoices' => array(
			'base' => 'Invoice',
			'iah_table' => 'invoice',
			'import_chk' => 'Invoices',
			'export_chk' => 'Invoices',
			'module' => 'Invoice',
		),
		'Payments' => array(
			'base' => 'ReceivePayment',
			'iah_table' => 'payments',
			'import_chk' => 'Invoices',
			'export_chk' => 'Invoices',
			'module' => 'Payments',
		),
		
		'Bills' => array(
			'base' => 'Bill',
			'iah_table' => 'bills',
			'import_chk' => 'Bills',
			'export_chk' => 'Bills',
			'module' => 'Bills',
		),

		'BillCheckPayments' => array(
			'base' => 'BillPaymentCheck',
			'iah_table' => 'payments',
			'import_chk' => 'Bills',
			'export_chk' => 'Bills',
			'module' => 'PaymentsOut',
		),

		'BillCCPayments' => array(
			'base' => 'BillPaymentCreditCard',
			'iah_table' => 'payments',
			'import_chk' => 'Bills',
			'export_chk' => 'Bills', // not used - handled in BillCheckPayments
			'module' => 'PaymentsOut',
		),

		'CreditMemos' => array(
			'base' => 'CreditMemo',
			'iah_table' => 'credit_notes',
			'import_chk' => 'Invoices',
			'export_chk' => 'Invoices',
			'module' => 'CreditNotes',
		),

		'CreditMemosInvoices' => array(
			'base' => 'ReceivePayment',
			'iah_table' => 'credit_notes',
			'import_chk' => 'none',
			'export_chk' => 'Invoices',
			'module' => 'CreditNotes',
		),

		'Checks' => array(
			'base' => 'Check',
			'iah_table' => 'payments',
			'import_chk' => 'Invoices',
			'export_chk' => 'Invoices',
			'module' => 'Payments',
			'version' => 7.0,
		),

		'ARRefundCreditCards' => array(
			'base' => 'ARRefundCreditCard',
			'iah_table' => 'payments',
			'import_chk' => 'Invoices',
			'export_chk' => 'Invoices',
			'module' => 'Payments',
			'version' => 7.0,
		),

	);
	
	var $no_line_items_cmt = "No line items";

	function save($check_notify=false) {
		return parent::save($check_notify);
	}
	
	function &retrieve_for_related2($server_id, $system_type, $system_id, $qb_type, $encode=true, $require_synced=false) {
		$query = "SELECT id FROM `$this->table_name` ".
			"WHERE system_type='". $this->db->quote($system_type) ."' ".
			"AND system_id='". $this->db->quote($system_id) ."' ".
			"AND server_id='". $this->db->quote($server_id) ."' ".
			"AND NOT deleted ";
		if($require_synced)
			$query .= " AND (first_sync IN ('imported', 'exported') OR sync_status='import_error') ";
		if ($qb_type)
			$query .= " AND qb_type='" . $this->db->quote($qb_type) . "'";
		$result = $this->db->limitQuery($query,0,1,true, "Retrieving record by id $system_type:$system_id found ");
		$ret = null;
		if(empty($result))
			return $ret;
		$row = $this->db->fetchByAssoc($result, -1, $encode);
		if(empty($row))
			return $ret;
		$ret = $this->retrieve($row['id']);
		return $ret;
	}

	function &retrieve_for_related($server_id, $system_type, $system_id, $encode=true, $require_synced=false) {
		return $this->retrieve_for_related2($server_id, $system_type, $system_id, null, $encode, $require_synced);
	}
	
	function use_tally_group_types() {
		static $gtypes;
		if(!isset($gtypes)) {
			$gtypes = false;
			$fdefs = AppConfig::setting("model.fields.QuoteLineGroup", array());
			if(isset($fdefs['group_type']))
				$gtypes = true;
		}
		return $gtypes;
	}
	
	// --
	
	function phase_can_begin($server_id, $phase) {
		global $mod_strings;
		$err = '';
		if(QBConfig::get_server_setting($server_id, 'Export', 'Quotes')
		  || QBConfig::get_server_setting($server_id, 'Export', 'Invoices')) {
		  	// if we're only importing, then these don't matter
			if(! QBTaxCode::check_sales_tax_enabled($server_id)) {
				$err = $mod_strings['ERR_NO_SALES_TAX'];
			}
			else if(! QBItem::check_standard_items_synced($server_id)) {
				$err = $mod_strings['ERR_NO_STANDARD_ITEMS'];
			}
		}
		if($err) {
			qb_log_error($err);
			return array('allow' => false, 'error' => $err);
		}
		return true;
	}
	
	function &get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if(empty($this->phases[$phase])) {
			qb_log_debug("QBTally - no handler for phase $phase");
			return $reqs;
		}
		$ph = $this->phases[$phase];

		$version = QBConfig::get_server_setting($server_id, 'Server', 'qb_xml_version');
		if (isset($ph['version']) && $version < $ph['version']) {
			return $reqs;
		}

		$edition = QBServer::get_server_edition($server_id);

		$batch_type = $phase == 'Payments' ?  'Payments' : 'Invoices';
		$import_batch_size = qb_batch_size($server_id, $batch_type, 'import');
		$export_batch_size = qb_batch_size($server_id, $batch_type, 'export');
		
		if(QBConfig::get_server_setting($server_id, 'Import', $ph['import_chk'])) {
			if($stage == 'import') {	
				$step = "$phase/$step";
				$prep = "$stage:list:{$ph['base']}QueryRs";
				$prev_import = QBConfig::get_server_setting($server_id, $step, $prep);
				$incl_line_items = 'true'; // $prev_import ? 'true' : 'false';
				$import_req = array(
					'type' => 'import',
					'base' => $ph['base'],
					'params' => array(
						'IncludeLineItems' => $incl_line_items,
						'IncludeLinkedTxns' => null,
						'IncludeRetElement' => null,
					),
					'optimize' => 'auto',
					'no_batch' => true,
				);
				if($edition == 'US' && $incl_line_items == 'false') {
					$import_req['params']['IncludeRetElement'] = array(
						'TxnID',
						'TimeCreated', 'TimeModified',
						'EditSequence',
						'TxnNumber',
						'PayeeEntityRef',
						'TxnDate',
						'RefNumber',
						'Memo',
					);
				}
				if ($ph['base'] == 'Check') {
					if (!empty($import_req['params']['IncludeRetElement'])) {
						$import_req['params']['IncludeRetElement'] = array(
							'TxnID',
							'TimeCreated', 'TimeModified',
							'EditSequence',
							'TxnNumber',
							'PayeeEntityRef',
							'RefNumber',
							'TxnDate',
							'Memo',
							'LinkedTxn',
						);
					}
					$import_req['params']['IncludeLinkedTxns'] = 'true';
					//unset($import_req['params']['IncludeRetElement']);
				}

				if ($ph['base'] == 'ARRefundCreditCard') {
					if (!empty($import_req['params']['IncludeRetElement'])) {
						$import_req['params']['IncludeRetElement'] = array(
							'TxnID',
							'TimeCreated', 'TimeModified',
							'EditSequence',
							'TxnNumber',
							'CustomerRef',
							'RefundFromAccountRef', 'ARAccountRef',
							'RefNumber',
							'TotalAmount',
							'PaymentMethodRef',
							'TxnDate',
							'Memo',
							'RefundAppliedToTxnRet',
						);
					}
				}

				if ($ph['base'] != 'CreditMemo') {
					unset($import_req['params']['IncludeLinkedTxns']);
				} else {
					$import_req['params']['IncludeLinkedTxns'] = 'true';
					//$import_req['params']['IncludeRetElement'][] = 'LinkedTxn';
				}

				$reqs[] = $import_req;
			}
			else if($stage == 'ext_import') {
				$this->add_import_requests($server_id, $reqs, $import_batch_size, false, $ph['base']);
			}
		}
		
		if(QBConfig::get_server_setting($server_id, 'Export', $ph['export_chk'])) {
			if($stage == 'reg_update') {
				$this->register_phase_updates($server_id, $phase);
			}
			else if($stage == 'export') {
				$this->register_phase_exports($server_id, 500, $phase);
				$this->add_export_requests($server_id, $reqs, $export_batch_size, false, $ph['base']);
			}
			else if($stage == 'pre_update') {
				// we always grab object details if available
				// these are especially needed if import is disabled
				$this->add_import_requests($server_id, $reqs, $import_batch_size, true, $ph['base']);
			}
			else if($stage == 'update') {
				$this->add_export_requests($server_id, $reqs, $export_batch_size, true, $ph['base']);
			}
			else if($stage == 'delete') {
				$this->add_delete_requests($server_id, $reqs, $export_batch_size, $ph['base']);
			}
		}
		
		return $reqs;
	}
	
	function get_detail_qb_id(&$row) {
		$ret = parent::get_detail_qb_id($row);
		if (empty($ret)) {
			if (isset($row['AppliedToTxnRet']))
				$ret =  $row['AppliedToTxnRet'][0]['TxnID'];
		}
		return $ret;
	}
	
	function get_import_request_params($qb_ids, $qb_type) {
		$ret = array(
			'TxnID' => $qb_ids,
			'IncludeLineItems' => 'true',
		);
		if ($qb_type == 'CreditMemo') {
			$ret['IncludeLinkedTxns'] = 'true';
		}
		return $ret;
	}
	
	function perform_sync($mode, $qb_type, &$details, &$errmsg, &$newreqs) {
		$txn_date = array_get_default($details, 'TxnDate');
		global $timedate;
		$update = ! empty($this->id) && ! empty($this->system_id);
		$override = ($this->sync_status == 'pending_update' || $mode == 'post_iah_update');
		$this->qb_type = $qb_type;

		if ($this->qb_type == 'ReceivePayment' && $this->system_type == 'CreditNotes')
			return true;
		
		// object must only be updated again if the line items don't match (logic comes later)
		//if($mode == 'post_iah_update')
		//	return true;
		
		//if($mode == 'post_export') then re-export line items if necessary - no merge
		
		//qb_log_error("QBTALLY PERFORM SYNC qb_type: $qb_type update:{$update} override:{$override} status:{$this->sync_status}");
		
		$default_name = array_get_default($details, 'RefNumber', '');
		if(strlen($default_name)) $default_name = "[$default_name]";
		if(! empty($details['Memo'])) {
			if($default_name) $default_name .= ' ';
			$default_name .= $details['Memo'];
		}
		if (!strlen(trim($default_name))) {
			$default_name = '[empty]';
		}
		
		if($qb_type == 'Estimate' || $qb_type == 'Invoice' || $qb_type == 'CreditMemo') {
			$reg_only = ! isset($details[$qb_type.'LineRet'])
				&& ! isset($details[$qb_type.'LineGroupRet']);
		}
		else if($qb_type == 'Bill') {
			$reg_only = ! isset($details['ItemLineRet'])
				&& ! isset($details['ItemGroupLineRet'])
				&& ! isset($details['ExpenseLineRet']);
		}
		else if($qb_type == 'Check') {
			$reg_only = false;
		}
		else if($qb_type == 'ARRefundCreditCard') {
			$reg_only = ! isset($details['RefundAppliedToTxnRet']);
		}
		else {
			$reg_only = ! isset($details['AppliedToTxnRet']);
		}

		if($reg_only) {
			// short-circuit: we are only registering for a full import later
			if(! $update) {
				$this->first_sync = '';
				$this->name = $default_name;
			}
			if($this->first_sync != 'exported') {
				$this->sync_status = 'pending_import';
			}
			$this->date_last_sync = qb_date_last_sync();
			return true;
		}
		
		
		if($update) {
			if(! $this->fetch_system_object()) {
				$errmsg = "Error loading system object: $this->system_type $this->system_id";
				return false;
			}
			$focus =& $this->system_object;
		}
		else {
			if($qb_type == 'Estimate') {
				$focus = RowUpdate::blank_for_model('Quote');
			}
			else if($qb_type == 'Invoice') {
				$focus = RowUpdate::blank_for_model('Invoice');
			}
			else if($qb_type == 'CreditMemo') {
				$focus = RowUpdate::blank_for_model('CreditNote');
			}
			else if($qb_type == 'Bill') {
				$focus = RowUpdate::blank_for_model('Bill');
			}
			else if($qb_type == 'ReceivePayment') {
				$focus = RowUpdate::blank_for_model('Payment');
				$focus->set('id', create_guid());
			}
			else if($qb_type == 'BillPaymentCheck') {
				$focus = RowUpdate::blank_for_model('Payment');
				$focus->set('id', create_guid());
			}
			else if($qb_type == 'BillPaymentCreditCard') {
				$focus = RowUpdate::blank_for_model('Payment');
				$focus->set('id', create_guid());
			}
			else if($qb_type == 'Check') {
				$focus = RowUpdate::blank_for_model('Payment');
				$focus->set('id', create_guid());
			}
			else if($qb_type == 'ARRefundCreditCard') {
				$focus = RowUpdate::blank_for_model('Payment');
				$focus->set('id', create_guid());
			}
			else {
				$errmsg = "Unknown tally type: {$this->qb_type}";
				return false;
			}
			$this->system_object =& $focus;
		}

		//$focus->set('amount', 0);
		$focus->set('discount_before_taxes', 1);
		switch ($qb_type) {
			case 'BillPaymentCreditCard':
			case 'BillPaymentCheck':
			case 'Check':
				$acct_ref_name = 'PayeeEntityRef';
				break;
			case 'Bill':
				$acct_ref_name = 'VendorRef';
				break;
			default:
				$acct_ref_name = 'CustomerRef';
				break;
		}


		if(! isset($details[$acct_ref_name])) {
			$errmsg = "Tally with no related customer/vendor cannot be imported";
			return false;
		}

	
		$this->account_name = $details[$acct_ref_name]['FullName'];
		$this->parent_qb_id = $details[$acct_ref_name]['ListID'];
		$entity = new QBEntity();
		if(! $entity->qb_retrieve($this->parent_qb_id, $this->server_id)) {
			$errmsg = "Tally related account not yet imported";
			return false;
		}
		//qb_log_info('found account: '.$entity->name.' '.$entity->account_id);
		$acct = new Account();
		if(! $acct->retrieve($entity->account_id, false)) {
			$errmsg = "Error retrieving tally related account: {$entity->account_id}";
			return false;
		}
		
		global $current_user;
		$focus->set('assigned_user_id', $acct->assigned_user_id);
		if(! $focus->getField('assigned_user_id'))
			$focus->set('assigned_user_id', $current_user->id);
		$focus->set('currency_id', $acct->currency_id);
		if(isset($details['ExchangeRate'])) {
			$_REQUEST['override_exchange_rate']['exchange_rate'] = true;
			$focus->set('exchange_rate', $details['ExchangeRate']);
		}
		
		if($focus->model_name == 'Payment') {
			$focus->set('account_id', $acct->id);
		}
		else if(! $update || ! $override) {
			if ($qb_type == 'Bill') {
				$focus->set('supplier_id', $entity->account_id);
			} else {
				$focus->set('billing_account_id', $entity->account_id);
				$focus->set('shipping_account_id', $entity->account_id);
			}
			if(! empty($entity->opportunity_id))
				$focus->set('opportunity_id', $entity->opportunity_id);
		}

		if(! $update || ! $override) {
			if($focus->model_name == 'Invoice' || $focus->model_name == 'CreditNote' || $focus->model_name == 'Bill') {
				$focus->set('purchase_order_num', array_get_default($details, 'PONumber'));
				// IsPending, IsFinanceCharge, IsPaid
				$focus->set('due_date', qb_import_date(array_get_default($details, 'DueDate', '')));
				// ShipDate
				// AppliedAmount, BalanceRemaining  - calculated
				if(isset($details['BillAddress']))
					qb_import_address($this->server_id, $focus, $details['BillAddress'], 'billing_address_');
				if(isset($details['ShipAddress']))
					qb_import_address($this->server_id, $focus, $details['ShipAddress'], 'shipping_address_');
			}
			else if($focus->model_name == 'Quote') {
				$focus->set('valid_until', qb_import_date(array_get_default($details, 'DueDate', '')));
				if(isset($details['BillAddress']))
					qb_import_address($this->server_id, $focus, $details['BillAddress'], 'billing_address_');
			}
		}
		
		if(! $update) {
			if($default_name)
				$focus->set('name', $default_name);
			//$db_dt = qb_import_date($details['TxnDate'], false).' 12:00:00';
			//$focus->date_entered = $timedate->handle_offset($db_dt, $timedate->get_db_date_time_format(), false);
			if($focus->model_name == 'Invoice' || $focus->model_name == 'Quote' || $focus->model_name == 'CreditNote') {
				$focus->set('date_entered', qb_import_date($details['TxnDate'], false) . ' 12:00:00');
			} else {
				$focus->set('date_entered', qb_import_date_time($details['TimeCreated'], false));
			}
		}
		
		if($focus->model_name == 'Bill') {
			$focus->set('bill_date', qb_import_date($details['TxnDate'], false));
		}
	
		if($qb_type == 'Check' || $qb_type == 'ARRefundCreditCard') {
			$focus->set('refund', 1);
		}

		if($focus->model_name == 'Payment') {
			if(! ($payment_lines = $this->receive_payment($focus, $details, $errmsg)))
				return false;
		}
		else {
			if(! $update || ! $override) {
				if(isset($details['ShipMethodRef']))
					$focus->set('shipping_provider_id', QBShipMethod::to_iah_method($this->server_id, $details['ShipMethodRef']['ListID']));
	
				if(isset($details['TermsRef']))
					$focus->set('terms', QBTerms::to_iah_terms($this->server_id, $details['TermsRef']['ListID']));
				if(!$focus->getField('terms')) // cannot be empty
					$focus->set('terms', QBTerms::get_default_import_terms($this->server_id));
			}

			if(! $this->parse_line_items($focus, $details, $items, $errmsg))
				return false;

			if(! $update || ! $override) {
				if(!$focus->getField('name') && isset($items) && is_array($items) && count($items)) {
					$focus->set('name', $items[key($items)]['name']);
				}
			}
			
			$new_grps = $this->split_item_groups($items, $focus->model_name);
			if(! $update || ! $override) {
				if($update) // this preserves some details as a side-effect
					$differs = $this->match_exported_lines($focus, $new_grps);
				if(! $this->merge_import_line_items($focus, $new_grps, $errmsg))
					return false;
			}
			else if($update) {
				$differs = $this->match_exported_lines($focus, $new_grps);
				//qb_log_debug("MATCHING: ".($differs ? 'differs' : 'no change'));
				if($differs) {
					$exlog = array_get_default($_SESSION, 'tally_export_log', array());
					$c = array_get_default($exlog, $this->id, 0);
					if($c >= constant('MAX_TALLY_EXPORT_ATTEMPTS')) {
						$errmsg = "Export loop broken";
						return false;
					}
					if($this->self_get_export_requests($req, true)) {
						foreach ($reqs as $req)
							$newreqs[] = $req;
						$exlog[$this->id] = $c + 1;
						$_SESSION['tally_export_log'] = $exlog;
					}
				}
				else if($mode == 'post_iah_update')
					return true; // no action
			}
			
			//Tax1Total, Tax2Total - using calculated values
			

			if (!empty($details['LinkedTxn']) && $focus->model_name == 'CreditNote') {
				if (!isset($details['LinkedTxn'][0]))
					$txns = array($details['LinkedTxn']);
				else
					$txns = $details['LinkedTxn'];
				if (!$this->linkCreditNoteToInvoice($focus, $txns, $errmsg)) {
					$this->retry_export_later = true;
					return false;
				}
			}
		}
		
		if($focus->model_name == 'Payment') {
			if ($focus->getField('direction') == 'incoming') {
				$table = 'invoice';
				$type = 'Invoice';
			} else {
				$table = 'bills';
				$type = 'Bills';
			}
			if(is_array($focus->line_items) && count($focus->line_items)) {
				$inv_ids = array();
				foreach($focus->line_items as $pli)
					$inv_ids[] = $pli['invoice_id'];
				$query = sprintf("SELECT me.id FROM qb_tallies me ".
					"LEFT JOIN {$table} invoice ON invoice.id=me.system_id AND NOT invoice.deleted ".
					"WHERE me.system_type='{$type}' AND me.system_id IN ('%s') AND me.server_id='%s' AND NOT me.deleted ".
					"AND me.date_last_sync >= invoice.date_modified",
						implode("','", $inv_ids), $this->server_id);
				//qb_log_debug($query);
				$ret = $this->db->query($query, false);
				$upd_inv_dtls = array();
				if($ret) {
					while($dbrow = $this->db->fetchByAssoc($ret))
						$upd_inv_dtls[] = $dbrow['id'];
				}
				if(count($upd_inv_dtls)) {
					$payments_upd_query = sprintf("UPDATE qb_tallies me ".
						"LEFT JOIN {$table} invoice ON invoice.id=me.system_id AND NOT invoice.deleted ".
						"SET me.date_last_sync=invoice.date_modified ".
						"WHERE me.id IN ('%s') AND me.date_last_sync < invoice.date_modified",
							implode("','", $upd_inv_dtls));
				}
			}
		}
		
		if(! $focus->save()) {
			$errmsg = "Could not save tally related object";
			return false;
		}
		
		if($focus->model_name == 'Payment') {
			$existing = Payment::query_line_items($focus->getPrimaryKeyValue());
			Payment::update_line_items($focus, $existing, $payment_lines);
		}

		$rel_id = $focus->getPrimaryKeyValue();
			
			
		//qb_log_info("SAVED TALLY $this->name $this->sync_status $this->date_last_sync $focus->date_modified");
		
		// prevent unnecessary export of accounts after invoices saved
		global $disable_date_format;
		$acct_lastmod = $acct->date_modified;
		$ent_lastsync = $entity->date_last_sync;
		if(empty($disable_date_format)) {
			$acct_lastmod = $timedate->to_db($acct_lastmod);
			$ent_lastsync = $timedate->to_db($ent_lastsync);
		}
		if($ent_lastsync >= $acct_lastmod && empty($entity->sync_status) && $this->date_last_sync) {
			$entity->date_last_sync = $this->date_last_sync;
			$entity->save();
			$entity = null; // must be reloaded if needed
		}
		
		if($focus->model_name == 'Payment' && ! empty($payments_upd_query)) {
			// prevent unnecessary export of invoices after payment saved
			$this->db->query($payments_upd_query, false);
		}

		$number = $focus->getField('prefix');
		$nf = null;
		if($focus->model_name == 'Quote')
			$nf = 'quote_number';
		else if($focus->model_name == 'Invoice')
			$nf = 'invoice_number';
		else if($focus->model_name == 'CreditNote')
			$nf = 'credit_number';
		else if($focus->model_name == 'Bill')
			$nf = 'bill_number';
		else if($focus->model_name == 'Payment')
			$nf = 'payment_id';
		if ($nf)
			$number .= $focus->getField($nf);
		$this->shortname = $number;
		if(strlen($focus->getField('name')))
			$this->name = $focus->getField('name');
		else
			$this->name = $number;
		$this->system_type = $focus->model->module_dir;
		if($focus->model_name == 'Payment' && $focus->getField('direction') == 'outgoing') {
			$this->system_type = 'PaymentsOut';
		}
		$this->system_id = $rel_id;
		$this->date_last_sync = $focus->getField('date_modified');
	
		return true;
	}


	function linkCreditNoteToInvoice(&$focus, $txns, &$errmsg)
	{
		foreach ($txns as $txn) {
			if (array_get_default($txn, 'TxnType') == 'Invoice' && array_get_default($txn, 'LinkType') == 'AMTTYPE') {
				$inv = new QBTally();
				if(! $inv->qb_retrieve($txn['TxnID'], $this->server_id)) {
					$errmsg = 'Could not locate credit memo related invoice: '.$txn['TxnID'];
					return false;
				}
				$focus->set('invoice_id', $inv->system_id);
				$focus->set('apply_credit_note', 1);
				return true;
			}
		}
		$focus->set('apply_credit_note', 0);
		return true;
	}

	/* XXX !!!!!!!!!!*/	
	function parse_line_items(&$focus, &$details, &$ret, &$errmsg) {
		$this->import_line_idx = 0;
		$lines = array();
		$assms = array();
		$prefix = $this->qb_type;
		if ($this->qb_type == 'Bill') {
			$prefix = 'Item';
			if (isset($details['ExpenseLineRet'])) {
				$lines = $details['ExpenseLineRet'];
			}
		}
		if(isset($details[$prefix.'LineRet']))
			$lines = array_merge($lines, $details[$prefix.'LineRet']);
		if(isset($details[$prefix.'LineGroupRet']))
			$assms = $details[$prefix.'LineGroupRet'];
		if(isset($details[$prefix.'GroupLineRet']))
			$assms = $details[$prefix.'GroupLineRet'];
		
		$ret = array();
		while(1) {
			$lk = key($lines);
			$ak = key($assms);
			if($lk === null && $ak === null)
				break;
			if($lk === null || ($ak !== null && $lines[$lk]['_pos_'] > $assms[$ak]['_pos_'])) {
				$this->add_assembly($ret, $assms[$ak], $focus->model_name);
				next($assms);
			}
			else {
				$this->add_line_item($ret, $lines[$lk], '', $focus->model_name, $details);
				next($lines);
			}
		}
		return true;
	}

	function add_line_item(&$lines, $detail, $assembly_id, $object_name, $topLevel = array()) {
		$item_name = ''.array_get_default($detail, 'Desc');
		$related_type = 'ProductCatalog';
		$rel = new QBItem();
		$taxPercent = null;

		if (isset($topLevel['ItemSalesTaxRef'])) {
			$taxName = $topLevel['ItemSalesTaxRef']['FullName'];
			$taxPercent = $topLevel['SalesTaxPercentage'];
			$taxItem = new QBItem;
			$taxItem->qb_retrieve($topLevel['ItemSalesTaxRef']['ListID'], $this->server_id);
		}	
		if(isset($detail['TaxCodeRef'])) {
			$tax_code_id = QBTaxCode::to_iah_tax_code($this->server_id, $detail['TaxCodeRef']['ListID']);
			$taxCodeDetail = QBTaxCode::get_qb_tax_code($this->server_id, $detail['TaxCodeRef']['ListID']);
		} else if(isset($detail['SalesTaxCodeRef'])) {
			$tax_code_id = QBTaxCode::to_iah_tax_code($this->server_id, $detail['SalesTaxCodeRef']['ListID']);
			$taxCodeDetail = QBTaxCode::get_qb_tax_code($this->server_id, $detail['SalesTaxCodeRef']['ListID']);
		} else {
			$tax_code_id = '';
			$taxCodeDetail = null;
		}

		$amt = array_get_default($detail, 'Amount', 0.0);

		if(isset($detail['ItemRef']) && $detail['ItemRef']['ListID']) {
			$rel->qb_retrieve($detail['ItemRef']['ListID'], $this->server_id);
			$partno = $detail['ItemRef']['FullName']; // FIXME - may contain colon?
			$item_name = ''.array_get_default($detail, 'Desc', $rel->name);
		} elseif (isset($detail['AccountRef']) && $detail['AccountRef']['ListID']) {
			$expense = QBAccount::expense_category_from_qb_account_id($this->server_id, $detail['AccountRef']['ListID']);
			if ($expense) {
				$nl = array(
					'id' => 'newline~'.$this->import_line_idx++,
					'name' => $expense->name,
					'description' => $detail['Memo'],
					'quantity' => 1,
					'unit_price' =>  $detail['Amount'],
					'ext_price' =>  $detail['Amount'],
					'TxnLineID' => $detail['TxnLineID'],
					'related_type' => 'BookingCategories',
					'related_id' => $expense->id,
					'tax_class_id' => $tax_code_id,
				);
				$lines[$nl['id']] = $nl;
			}
			return;
		} else if ((float)$amt == 0.0) {
			$nl = array(
				'id' => 'newline~'.$this->import_line_idx++,
				'name' => '',
				'body' => $item_name,
				'is_comment' => 1,
				'TxnLineID' => $detail['TxnLineID'],
			);
			$lines[$nl['id']] = $nl;
			return;
		}

		if(! strlen($item_name))
			$item_name = strlen($partno) ? $partno : '-';
		if(! isset($this->shipping_item_id)) {
			$this->shipping_item_id = QBItem::get_standard_shipping_item_id($this->server_id);
		}
		$special = null;
				
		if($rel->qb_id == $this->shipping_item_id) {
			$amt = array_get_default($detail, 'Amount', 0.0);
			$special = array(
				'name' => $item_name,
				'related_type' => 'ShippingProviders',
				'related_id' => $focus->shipping_provider_id,
				'tax_class_id' => $tax_code_id,
				'rate' => $amt,
				'amount' => $amt,
			);
		}
		else if($rel->qb_type == 'ItemSubtotal') {
			$special = array(
				'subtotal' => true,
				'amount' => array_get_default($detail, 'Amount', 0.0),
				'qb_id' => $rel->qb_id,
			);
		}
		else if($rel->qb_type == 'ItemDiscount') {
			$special = array(
				'name' => $item_name,
				'related_type' => 'Discounts',
				'related_id' => $rel->system_id,
				'amount' => 0.0,
				'tax_class_id' => $tax_code_id,
			);
			if(isset($detail['Rate']))
				$special['amount'] = - $detail['Rate'];
			else if(isset($detail['RatePercent'])) {
				$special['rate'] = - $detail['RatePercent'];
				$special['amount'] = - $detail['Amount'];
			}
		}
		else if($rel->qb_type == 'ItemSalesTax') {
			$special = array(
				'name' => $item_name,
				'related_type' => 'TaxRates',
				'related_id' => $rel->system_id,
				'editable' => 1,
			);
			if(isset($detail['Rate']))
				$special['amount'] = $detail['Rate'];
			else if(isset($detail['RatePercent'])) {
				$special['rate'] = $detail['RatePercent'];
				$special['amount'] = $detail['Amount'];
			}
		}
		
		if($special) {
			if(! $assembly_id) { // these cannot be nested in an assembly in IAH
				$special['id'] = 'newadj~'.$this->import_line_idx++;
				$special['special'] = true;
				$special['TxnLineID'] = $detail['TxnLineID'];
				$lines[$special['id']] = $special;
			}
			return;
		}
		
		$nl = array(
			'id' => 'newline~'.$this->import_line_idx++,
			'name' => $item_name,
			'mfr_part_no' => $partno,
			'quantity' => array_get_default($detail, 'Quantity', 1),
			'related_type' => $related_type,
			'related_id' => '',
			'sum_of_components' => 0,
			'parent_id' => $assembly_id,
			'depth' => $assembly_id ? 1 : 0,
			'tax_class_id' => $tax_code_id,
			'cost_price' => array_get_default($detail, 'Rate', 0.0),
			'list_price' => 0.0,
			'unit_price' => 0.0,
			'std_unit_price' => null,
			'QBType' => $rel->qb_type,
			'TxnLineID' => $detail['TxnLineID'],
		);
		$nl['ext_quantity'] = $nl['quantity'];
		
		if($assembly_id) {
			$asm_qty = $lines[$assembly_id]['ext_quantity'];
			if($asm_qty) {
				$nl['quantity'] /= $asm_qty;
			}
		}
		
		$nl['ext_price'] = sprintf('%0.5f', $amt);
		if($nl['quantity'])
			$nl['unit_price'] = sprintf('%0.5f', $amt / $nl['ext_quantity']);
		else
			$nl['unit_price'] = $amt;
		$nl['list_price'] = $nl['cost_price']; // default only
		$nl['std_unit_price'] = $nl['unit_price']; // default only
		// may want to grab MarkupRate, MarkupRatePercent from Estimates
		if($rel->id) {
			$hrs_qbid = QBItem::get_booking_line_item_id($this->server_id);
			$prd_qbid = QBItem::get_custom_line_item_id($this->server_id);
			if($hrs_qbid == $rel->qb_id) {
				$nl['related_type'] = 'Booking';
			}
			else if($prd_qbid == $rel->qb_id) {
				$nl['related_id'] = '';
			}
			else if($rel->system_type == 'ProductCatalog' && $rel->system_id) {
				$nl['related_id'] = $rel->system_id;
				$prod = new Product();
				if($prod->retrieve($rel->system_id, false)) {
					$nl['list_price'] = $prod->list_price;
					$nl['std_unit_price'] = $prod->purchase_price;
				}
			}
		}
		$lines[$nl['id']] = $nl;

		if ($taxPercent && $taxCodeDetail && $taxCodeDetail->charge_tax_1) {
			$adj = array(
				'id' => 'newadj~'.$this->import_line_idx++,
				'name' => $taxName,
				'related_type' => 'TaxRates',
				'related_id' => $taxItem->system_id,
				'rate' => $taxPercent,
				'amount' => $nl['ext_price'] * $taxPercent / 100,
				'line_id' => $nl['id'],
				'special' => true,
			);
			$lines[$adj['id']] = $adj;
		}

	}
	
	function add_assembly(&$lines, $detail, $object_name) {
		$rel = new QBItem();
		$partno = '';
		if(isset($detail['ItemGroupRef'])) {
			$rel->qb_retrieve($detail['ItemGroupRef']['ListID'], $this->server_id);
			$partno = $detail['ItemGroupRef']['FullName']; // FIXME - may contain colon?
		}
		$nl = array(
			'id' => 'newasm~'.$this->import_line_idx++,
			'name' => array_get_default($detail, 'Desc', $rel->name),
			'mfr_part_no' => $partno,
			'quantity' => array_get_default($detail, 'Quantity', 1),
			'ext_quantity' => array_get_default($detail, 'Quantity', 1),
			'related_type' => 'Assemblies',
			'related_id' => '',
			'sum_of_components' => 1,
			'QBType' => $rel->qb_type,
			'TxnLineID' => $detail['TxnLineID'],
		);
		if($rel->id && $rel->system_type == 'Assemblies') {
			$nl['related_id'] = $rel->system_id;
		}
		$lines[$nl['id']] = $nl;
		if(isset($detail[$this->qb_type.'LineRet'])) {
			foreach($detail[$this->qb_type.'LineRet'] as $line_detail)
				$this->add_line_item($lines, $line_detail, $nl['id'], $object_name);
		}
	}
	
	function get_group_type(&$line, $object_name) {
		switch($line['related_type']) {
			case 'SupportedProducts':
			case 'SupportedAssemblies':
				return 'support';
			case 'Booking':
			case 'BookingCategories':
				$ret = $object_name =='Bill' ? 'expenses' : 'service';
				return $ret;
		}
		if(! empty($line['is_comment'])) {
			return '';
		}
		return 'products';
	}


	function createEmptyGroup(&$groups, $type)
	{
		$gid = 'newgrp~'.$this->import_line_idx ++;
		$groups[$gid] = array(
			'id' => $gid,
			'group_type' => $type,
			'lines' => array(),
			'lines_order' => array(),
			'adjusts' => array(),
			'adjusts_order' => array(),
		);
		return $gid;
	}

	/* XXX !!!!!!!!!!*/
	function &split_item_groups(&$import, $object_name) {
		$need_new_group = true;
		$new_grps = array();
		$subt_found = array();
		$gidx = -1;
		$use_group_types = $this->use_tally_group_types();
		$new_gtype = '';
		$carry_line = null;

		$normalLine = null;
		$gid = '';
		$needNewGroup = false;
		$curGroupType = 'products';
		$inSubtotal = false;

		foreach($import as $k => $l) {
			if (!empty($l['special'])) {
				if (empty($l['subtotal'])) {
					$needNewGroup = true;
				} else {
					$inSubtotal = true;
				}
				if ($normalLine) {
					if (!$gid || !$inSubtotal) {
						$gid = $this->createEmptyGroup($new_grps, $curGroupType);
					}
					$new_grps[$gid]['lines_order'][] = $normalLine;
					$new_grps[$gid]['lines'][$normalLine] = $import[$normalLine];
					$normalLine = null;
				}
				if (!$gid) {
					$gid = $this->createEmptyGroup($new_grps, $curGroupType);
				}
				if($l['related_type'] == 'Discounts') {
					$l['type'] = 'StdPercentDiscount';
				}
				else if($l['related_type'] == 'TaxRates') {
					$l['type'] = 'StandardTax';
					//$shipping_taxed = true;
				}
				else if($l['related_type'] == 'ShippingProviders') {
					$shipping_taxed = false;
					if(! empty($l['tax_class_id'])) {
						$code = new TaxCode();
						$rs = $code->get_tax_rates($l['tax_class_id'], false);
						foreach($rs as $r) {
							if($r['rate'])
								$shipping_taxed = true;
						}
					}
					$l['type'] = $shipping_taxed ? 'TaxedShipping' : 'UntaxedShipping';
				}
				if (empty($l['subtotal'])) {
					$new_grps[$gid]['adjusts_order'][] = $k;
					$new_grps[$gid]['adjusts'][$k] = $l;
				}
			} else {
				if ($normalLine) {
					if (!$gid || $needNewGroup) {
						$gid = $this->createEmptyGroup($new_grps, $curGroupType);
					}
					$new_grps[$gid]['lines_order'][] = $normalLine;
					$new_grps[$gid]['lines'][$normalLine] = $import[$normalLine];
					$needNewGroup = false;
				}
				$normalLine = $k;
				if (!empty($l['is_comment'])) {
					$newType = $curGroupType;
				} else {
					$newType = $this->get_group_type($l, $object_name);
				}
				if ($curGroupType != $newType || $inSubtotal) $needNewGroup = true;
				$curGroupType = $newType;
				if ($needNewGroup) {
					$needNewGroup = false;
					$gid = '';
				}
				$inSubtotal = false;
			}
		}
		if ($normalLine) {
			if (!$gid || $needNewGroup) {
				$gid = $this->createEmptyGroup($new_grps, $curGroupType);
				$needNewGroup = false;
			}
			$new_grps[$gid]['lines_order'][] = $normalLine;
			$new_grps[$gid]['lines'][$normalLine] = $import[$normalLine];
		}
		return $new_grps;
	}
	
	function &split_item_groups2(&$import, $object_name) {
		$need_new_group = true;
		$new_grps = array();
		$subt_found = array();
		$gid = '';
		$gidx = -1;
		$use_group_types = $this->use_tally_group_types();
		$new_gtype = '';
		$carry_line = null;
		
		foreach($import as $k => $l) {
			$blank_group = false;
			if(! empty($l['subtotal'])) {
				if(! empty($subt_found[$gidx]))
					$blank_group = true;
			}
			if(((empty($l['special']) || $blank_group) && $need_new_group) || ! $gid) {
				$gid = 'newgrp~'.$this->import_line_idx ++;
				$gidx ++;
				$new_grps[$gid] = array(
					'id' => $gid,
					'lines' => array(),
					'lines_order' => array(),
					'adjusts' => array(),
					'adjusts_order' => array(),
				);
				//$shipping_taxed = false;
				$need_new_group = false;
				if($use_group_types)
					$new_grps[$gid]['group_type'] = $new_gtype;
				$new_gtype = '';
			}
			if($carry_line) {
				$new_grps[$gid]['lines_order'][] = $carry_line;
				$new_grps[$gid]['lines'][$carry_line] = $import[$carry_line];
				$carry_line = null;
			}
			if(empty($l['special'])) {
				if($use_group_types) {
					$new_t = $this->get_group_type($l, $object_name);
					$old_t = $new_grps[$gid]['group_type'];
					if($new_t && $old_t != $new_t) {
						if(! $old_t)
							$new_grps[$gid]['group_type'] = $new_t;
						else if($new_t != 'products' || $old_t == 'service' || $old_t != 'expense') {
							$new_gtype = $t;
							$need_new_group = true;
							$carry_line = $k;
						}
					}
				}
				if(! $carry_line) {
					$new_grps[$gid]['lines_order'][] = $k;
					$new_grps[$gid]['lines'][$k] = $l;
				}
			}
			else {
				$need_new_group = true;
				if(! empty($l['subtotal'])) {
					$subt_found[$gidx] = true;
					$this->txn_line_ids[$gidx]['subtotal'] = $l['TxnLineID'];
					$this->txn_line_ids[$gidx]['subtotal_qbid'] = $l['qb_id'];
					continue;
				}
				if($l['related_type'] == 'Discounts') {
					$l['type'] = 'StdPercentDiscount';
				}
				else if($l['related_type'] == 'TaxRates') {
					$l['type'] = 'StandardTax';
					//$shipping_taxed = true;
				}
				else if($l['related_type'] == 'ShippingProviders') {
					$shipping_taxed = false;
					if(! empty($l['tax_class_id'])) {
						$code = new TaxCode();
						$rs = $code->get_tax_rates($l['tax_class_id'], false);
						foreach($rs as $r) {
							if($r['rate'])
								$shipping_taxed = true;
						}
					}
					$l['type'] = $shipping_taxed ? 'TaxedShipping' : 'UntaxedShipping';
				}
				$new_grps[$gid]['adjusts_order'][] = $k;
				$new_grps[$gid]['adjusts'][$k] = $l;
			}
		}
		if($carry_line) {
			$new_grps[$gid]['lines_order'][] = $carry_line;
			$new_grps[$gid]['lines'][$carry_line] = $import[$carry_line];
			$carry_line = null;
		}
		
		return $new_grps;
	}
	
	
	function merge_import_line_items(&$focus, &$new_grps, $errmsg) {
		$old_grps = $this->get_sorted_line_groups($focus);
		if (method_exists($focus, 'getTaxDate')) {
			$date = $focus->getTaxDate();
		} else {
			$date = null;
		}
		$focus->replaceGroups($new_grps, true, $date);
		return true;
	}

	/* XXX !!!!!!!!!!*/ 
	// note - does not account for all adjustments, only line items
	function match_exported_lines(&$focus, &$qb_grps) {
		$server = new QBServer();
		$old_grps = $this->get_sorted_line_groups($focus);
		$differs = false; // need to determine if changes are necessary
		$skipped = array();
		// todo - set a session variable to prevent infinite loops (max 10 export attempts, then fail)
		$this->txn_line_ids = array();

		reset($qb_grps);
		foreach($old_grps as $gid => $g) {
			if($g->id == 'GRANDTOTAL' || empty($g->lines))
				continue;
			$qbgk = key($qb_grps);
			if(! isset($qb_grps[$qbgk])) {
				$differs = true;
				break;
			}

			$grp_tax_rates = array();
			if(! empty($g->adjusts)) {
				foreach($g->adjusts as $adjidx => $adj) {
					if($adj['type'] != 'StandardTax' && $adj['type'] != 'CompoundedTax')
						continue;
					$grp_tax_rates[] = array($adj['related_id'], $adj['rate'], $adj['type'] == 'CompoundedTax');
				}
			}

			$check_asm = $check_prod = $check_hrs = $check_cmt = array();
			$asm_map = array();
			$old_lc = $qb_lc = 0;			
			foreach($g->lines as $oid => $ol) {
				if(! empty($ol['tax_class_id'])) {
					// lookup equivalent qb_id, then use related taxcode ID (to compensate for alternate tax codes chosen during export)
					$qb_code = QBTaxCode::from_iah_tax_code($server_id, $ol['tax_class_id']/*, $grp_tax_rates*/);
					if($qb_code && $qb_code->related_id)
						$ol['tax_class_id'] = $qb_code->related_id;
				}
				if($ol['related_type'] == 'Assemblies') {
					if(! $ol['quantity']) {
						// QB does not allow zero qty assemblies
						$skipped[$ol['id']] = true;
						continue;
					}
					$check_asm[$oid] = $ol;
					$check_asm[$oid]['parts'] = array();
					$asm_map[$ol['id']] = $oid;
				}
				else if($ol['depth']) {
					if(isset($asm_map[$ol['parent_id']]))
						$check_asm[$asm_map[$ol['parent_id']]]['parts'][$oid] = $ol;
					else if(! empty($skipped[$ol['parent_id']]))
						continue;
				}
				else if($ol['related_type'] == 'ProductCatalog') {
					$check_prod[$oid] = $ol;
				}
				else if($ol['related_type'] == 'Booking' || $ol['related_type'] == 'BookingCategories') {
					$check_hrs[$oid] = $ol;
				}
				else if(! empty($ol['is_comment'])) {
					$check_cmt[$oid] = $ol;
				}
				else
					qb_log_error("match_exported_lines: unhandled native type {$ol['related_type']}");
				$old_lc ++;
			}
			$qblines =& $qb_grps[$qbgk]['lines'];
			$asm_map = array();
			foreach($qblines as $lid => $qbl) {
				$found = false;
				if(! empty($qbl['is_comment'])) {
					if($qbl['body'] == $this->no_line_items_cmt)
						continue;
					foreach($check_cmt as $oid => $cmt) {
						if(empty($cmt['Matched'])) {
							$iah_body = qb_asciify_string($server->qbxml_encoding, trim($cmt['body']));
							$iah_body = preg_replace("/\r/", '', $iah_body);
							$qb_body = trim($qbl['body']);
							$qb_body = preg_replace("/\r/", '', $qb_body);
							if($iah_body == $qb_body) {
								$check_cmt[$oid]['Matched'] = true; // could just remove entry
								$this->txn_line_ids[$gid][$cmt['id']] = $qbl['TxnLineID'];
								$found = true;
								break;
							}
							/*else {
								qb_log_debug('NO MATCH');
								for($i = 0; $i < strlen($iah_body) && $i < strlen($qb_body); $i++) {
									if($iah_body{$i} != $qb_body{$i}) {
										$c = $iah_body{$i}; $d = $qb_body{$i};
										$e = ord($c); $f = ord($d);
										qb_log_debug("no match at $i: $c, $d - $e $f");
										break;
									}
								}
								qb_log_debug(substr($iah_body, 0, 10));
								qb_log_debug(substr($qb_body, 0, 10));
							}*/
						}
					}
				}
				else if($qbl['related_type'] == 'Booking' || $qbl['related_type'] == 'BookingCategories') {
					foreach($check_hrs as $oid => $hrs) {
						if(empty($hrs['Matched']) && $hrs['qb_id'] == $qbl['qb_id']) {
							$check_hrs[$oid]['Matched'] = true; // could just remove entry
							$this->txn_line_ids[$gid][$hrs['id']] = $qbl['TxnLineID'];
							if(! $this->import_preserve_details($hrs, $qblines[$lid])) {
								$differs = true;
							}
							$found = true;
							break;
						}
					}
				}
				else if($qbl['related_type'] == 'Assemblies') {
					foreach($check_asm as $oid => $asm) {
						if(empty($asm['Matched']) && $qbl['related_id'] == $asm['related_id']) {
							$check_asm[$oid]['Matched'] = true; // could just remove entry
							$this->txn_line_ids[$gid][$asm['id']] = $qbl['TxnLineID'];
							$asm_map[$qbl['id']] = $oid;
							if(! $this->import_preserve_details($asm, $qblines[$lid])) {
								$differs = true;
							}
							$found = true;
							break;
						}
					}
				}
				else if($qbl['related_type'] == 'ProductCatalog') {
					$asmid = $qbl['parent_id'];
					if($qbl['depth'] && isset($asm_map[$asmid])) {
						$asmidx = $asm_map[$asmid];
						foreach($check_asm[$asmidx]['parts'] as $prid => $pr) {
							if(empty($pr['Matched']) && ($qbl['related_id'] == $pr['related_id']
									|| (! $qbl['related_id'] && $qbl['quantity'] == $pr['quantity']) )) {
								$check_asm[$asmidx]['parts'][$prid]['Matched'] = true;
								$this->txn_line_ids[$gid][$pr['id']] = $qbl['TxnLineID'];
								if(! $this->import_preserve_details($pr, $qblines[$lid])) {
									$differs = true;
								}
								$found = true;
								break;
							}
						}
					}
					else {
						foreach($check_prod as $oid => $prod) {
							if(empty($prod['Matched']) && ($qbl['related_id'] == $prod['related_id']
									|| (! $qbl['related_id'] && $qbl['quantity'] == $prod['quantity']) )) {
								$check_prod[$oid]['Matched'] = true;
								// not necessary - export fresh item line to guarantee consistency
								$this->txn_line_ids[$gid][$prod['id']] = $qbl['TxnLineID'];
								if(! $this->import_preserve_details($prod, $qblines[$lid])) {
									$differs = true;
								}
								$found = true;
								break;
							}
						}
					}
				}
				else
					qb_log_error("match_exported_lines: unhandled QB type {$qbl['related_type']}");
				
				// copy pricing adjustment record
				if(! empty($qblines[$lid]['pricing_adjust_id'])) {
					/*$prcid = $qblines[$lid]['pricing_adjust_id'];
					if(isset($g->adjusts[$prcid])) { // never exists, possibly not indexed by ID
						$qb_grps[$qbgk]['adjusts_order'][] = $prcid;
						$qb_grps[$qbgk]['adjusts'][$prcid] = $g->adjusts[$prcid];
					}
					else*/
						$qblines[$lid]['pricing_adjust_id'] = '';
				}
				
				if(! $found) {
					/*qb_log_debug("Differs: Not Found");
					qb_log_debug($qbl);*/
					$differs = true;
				}
				$qb_lc ++;
			}
			
			if($old_lc != $qb_lc) {
				//qb_log_debug("Differs: Line Count");
				$differs = true;
			}
			next($qb_grps);
		}
		
		return $differs;
	}


	function get_sorted_line_groups($focus)
	{
		$groups = $focus->getGroups();
		$ret = array();
		foreach ($groups as $i => $g) {
			if (isset($g->group_type) && $g->group_type == 'expenses') {
				$ret[$i] = $g;
			}
		}
		foreach ($groups as $i => $g) {
			if (!isset($g->group_type) || $g->group_type != 'expenses') {
				$ret[$i] = $g;
			}
		}
		return $ret;
	}
	
	function import_preserve_details(&$iah_line, &$qb_line) {
		$same = true;
		$t = $qb_line['related_type'];
		if($t == 'Booking' && $iah_line['related_type'] == 'BookingCategories') {
			$t = $qb_line['related_type'] = 'BookingCategories';
		}
		$copy = array();
		$compare = array();
		if($t == 'Assemblies') {
			$compare += array(
				'quantity',
			);
			$copy += array(
				'mfr_part_no',
			);
		}
		else if($t == 'Booking' || $t == 'BookingCategories' || $t == 'ProductCatalog') {
			$compare += array(
				'quantity',
				'tax_class_id',
				'unit_price',
				// should compare name, but formatting differences could lead
				// to infinite update loops
			);
			$iah_line['unit_price'] = sprintf('%0.5f', $iah_line['unit_price']);
			$copy += array(
				'mfr_part_no',
				'cost_price', 'cost_price_usd', 'list_price', 'list_price_usd',
				'std_unit_price', 'std_unit_price_usd',
				'pricing_adjust_id');
			if($t == 'Booking' || $t == 'BookingCategories')
				$copy += array('related_id');
			// work around data error
			if(! isset($iah_line['ext_quantity']) && isset($iah_line['quantity']))
				$iah_line['ext_quantity'] = $iah_line['quantity'];
		}
		if(in_array('quantity', $compare) && empty($iah_line['ext_quantity'])) {
			// could have quantity=1 but assembly quantity=0, leading to loop
			$iah_line['quantity'] = '0'; 
			$iah_line['unit_price'] = '0';
		}
		foreach($copy as $f)
			if(isset($iah_line[$f]))
				$qb_line[$f] = $iah_line[$f];
		foreach($compare as $f) {
			if($f == 'tax_class_id') {
				if(! $iah_line[$f]) $iah_line[$f] = '-99';
				if(! $qb_line[$f]) $qb_line[$f] = '-99';
			}
			$l = ''.$qb_line[$f];  $r = ''.$iah_line[$f];
			if($f == 'unit_price') {
				$l = ''.$qb_line['ext_price'];
				$r = sprintf('%0.5f', /*$iah_line[$f] * $iah_line['ext_quantity']*/ $iah_line['ext_price']);
			}
			if($l != $r) {
				$t1 = gettype($qb_line[$f]); $t2 = gettype($iah_line[$f]);
				$same = false;
			}
		}
		return $same;
	}
	

	/* XXX !!!!!!!!!!!!*/
	function receive_payment(&$focus, &$details, &$errmsg) {
		if (isset($details['LinkedTxn'])) {
			$rows =& $details['LinkedTxn'];
		} elseif(isset($details['RefundAppliedToTxnRet'])) {
			$rows =& $details['RefundAppliedToTxnRet'];
		} elseif(! isset($details['AppliedToTxnRet'])) {
			$errmsg = "Missing AppliedToTxnRet";
			return false;
		} else {
			$rows =& $details['AppliedToTxnRet'];
		}
		$focus->set('amount', array_get_default($details, 'TotalAmount', false));
		if ($focus->getField('amount') === false) {
			$focus->set('amount', array_get_default($details, 'Amount', false));
		}
		$total = 0.0;

		if ($this->qb_type == 'BillPaymentCheck') {
			$focus->set('payment_type', 'Check');
			$focus->set('direction', 'outgoing');
		} elseif ($this->qb_type == 'BillPaymentCreditCard') {
			$focus->set('payment_type', 'Credit Card');
			$focus->set('direction', 'outgoing');
		} elseif ($this->qb_type == 'Check') {
			$focus->set('payment_type', 'Check');
			$focus->set('direction', 'outgoing');
		} else {
			if(isset($details['PaymentMethodRef']))
				$focus->set('payment_type', QBPaymentMethod::to_iah_method($this->server_id, $details['PaymentMethodRef']['ListID']));
			if(!$focus->getField('payment_type')) // required field in IAH
				$focus->set('payment_type',  QBPaymentMethod::get_default_import_method($this->server_id));
			if(!$focus->getField('direction'))
				$focus->set('direction', 'incoming');
		}
		if(isset($details['Memo']) && !$focus->getField('notes'))
			$focus->set('notes', trim(preg_replace('~^\[[^\]]+\]~', '', $details['Memo'])));
		if(!$focus->getField('customer_reference'))
			$focus->set('customer_reference', array_get_default($details, 'RefNumber'));
		if(!$focus->getField('payment_date'))
			$focus->set('payment_date', qb_import_date(array_get_default($details, 'TxnDate')));
		$line_items = array();
		$currency = new Currency();
		$currency->retrieve($focus->getField('currency_id'));
		if($focus->getField('exchange_rate') !== null)
			$currency->conversion_rate = $focus->getField('exchange_rate');
		if (!empty($rows) && !isset($rows[0])) {
			$rows = array($rows);
		}
		
		foreach($rows as $line) {
			if($line['TxnType'] == 'CreditMemo') {
				$focus->set('direction', 'credit');
			}
		}

		$paymentLinesSection = ($focus->getField('direction') == 'incoming') ? 'invoices' : (($focus->getField('direction') == 'credit') ? 'credits' : 'bills');
		$relatedIdName = ($focus->getField('direction') == 'incoming') ? 'invoice_id' : (($focus->getField('direction') == 'credit') ? 'credit_id' : 'bill_id');

		foreach($rows as $line) {
			if($line['TxnType'] != 'Invoice' && $line['TxnType'] != 'Bill' && $line['TxnType'] != 'CreditMemo') {
				// may be applied to journal entries and other transaction types
				qb_log_debug("Ignoring payment relation to {$line['TxnType']}");
				continue;
			}

			$inv = new QBTally();
			if(! $inv->qb_retrieve($line['TxnID'], $this->server_id)) {
				$errmsg = 'Could not locate payment-related invoice: '.$line['TxnID'];
				return false;
			}
			if(! $inv->system_id) {
				$errmsg = 'Related invoice has not been successfully imported: '.$inv->qb_id;
				return false;
			}

			$amount = array_get_default($line, 'Amount', false);
			if ($amount === false) {
				$amount = abs(array_get_default($line, 'RefundAmount', 0.0));
			}
			$amount = abs($amount);
			// currently no proper support for discounts at time of payment
			$amount += abs(array_get_default($line, 'DiscountAmount', 0.0));
			$line_items[$paymentLinesSection][] = array(
				$relatedIdName => $inv->system_id,
				'payment_id' => $focus->getField('id'),
				'amount' => $amount,
				'amount_usdollar' => $currency->convertToDollar($amount),
				'exchange_rate' => $currency->conversion_rate,
				'deleted' => 0,
				'date_modified' => gmdate("Y-m-d H:i:s"),
			);
			$total += $amount;
		}

		if ($focus->getField('amount') === false) {
			$focus->set('amount', $total);
		}
		return $line_items;
	}

	
	// -- Export handling
	
	function fetch_system_object() {
		if($this->system_type == 'Quotes')
			$model = 'Quote';
		else if($this->system_type == 'Invoice')
			$model = 'Invoice';
		else if($this->system_type == 'CreditNotes')
			$model = 'CreditNote';
		else if($this->system_type == 'Payments')
			$model = 'Payment';
		else if($this->system_type == 'PaymentsOut')
			$model = 'Payment';
		else if($this->system_type == 'Bills')
			$model = 'Bill';
		else return false;

		$result = ListQuery::quick_fetch($model, $this->system_id);
		if (!$result)
			return false;
		if ($model == 'Payment')
			$this->system_object = new RowUpdate($result);
		else
			$this->system_object = new TallyUpdate($result);
		return true;
	}
	
	
	function register_export_list($ph, &$added, $server_id, $max_register=-1) {
		global $beanList;
		$module = $ph['module'];
		$payment_type = "''";
		$refund = "''";
		$direction = "''";
		$on_extra = '';
		if($module == 'Quotes') {
			$qb_type = 'Estimate';
			$num = 'quote_number';
			$acct_id = 'billing_account_id';
			$name = 'tmod.name';
			$ext_where = '';
		}
		else if($module == 'Invoice') {
			$qb_type = 'Invoice';
			$num = 'invoice_number';
			$acct_id = 'billing_account_id';
			$name = 'tmod.name';
			$ext_where = '';
		}
		else if($module == 'CreditNotes') {
			$qb_type = 'CreditMemo';
			$num = 'credit_number';
			$acct_id = 'billing_account_id';
			$name = 'tmod.name';
			if ($ph['base'] == 'ReceivePayment') {
				$ext_where = ' AND tmod.apply_credit_note ';
				$qb_type = 'ReceivePayment';
				$on_extra = " AND tallies.qb_type='ReceivePayment' ";
			} else {
				$on_extra = " AND tallies.qb_type ='CreditMemo' ";
			}
		}
		else if($module == 'Bills') {
			$qb_type = 'Bill';
			$num = 'bill_number';
			$acct_id = 'supplier_id';
			$name = 'tmod.name';
			$ext_where = '';
		}
		else if($module == 'Payments') {
			$qb_type = 'ReceivePayment';
			$num = 'payment_id';
			$acct_id = 'account_id';
			$name = "CONCAT(tmod.prefix, tmod.$num)";
			$refund = "tmod.refund";
			$direction = 'tmod.direction';
			$payment_type = 'tmod.payment_type';

			$methods = QBPaymentMethod::get_credit_card_methods($server_id);
			$methods[] = 'Check';
			$ext_where = ' AND (!tmod.refund OR tmod.payment_type IN(\'' . join("','", $methods) . '\')) AND tmod.direction IN(\'incoming\', \'credit\') ';
		}
		else if($module == 'PaymentsOut') {
			$direction = 'tmod.direction';
			$payment_type = 'tmod.payment_type';
			$qb_type = 'BillPaymentCheck';
			$num = 'payment_id';
			$acct_id = 'account_id';
			$name = "CONCAT(tmod.prefix, tmod.$num)";
			$methods = QBPaymentMethod::get_credit_card_methods($server_id);
			$methods[] = 'Check';
			$ext_where = ' AND tmod.direction="outgoing" AND tmod.payment_type IN(\'' . join("','", $methods) . '\') ';
		}
		else {
			return false;
		}
		$obj = new $beanList[$module];
		$tbl = $obj->table_name;
		$sid = $this->db->quote($server_id);
		$query = "SELECT tmod.id, tmod.prefix, tmod.$num number, $name name, $payment_type payment_type, $refund refund, $direction direction FROM `$tbl` tmod ".
				"LEFT JOIN {$this->table_name} tallies ".
					"ON (tallies.server_id='$sid' AND NOT tallies.deleted ".
					"AND tallies.system_type='$module' AND tallies.system_id=tmod.id $on_extra ) ".
				"LEFT JOIN qb_entities accts ".
					"ON (accts.account_id=tmod.$acct_id AND accts.server_id='$sid' AND NOT accts.deleted) ".
				"WHERE tallies.id IS NULL AND accts.id IS NOT NULL AND accts.first_sync in ('imported', 'exported') ".
					"AND NOT tmod.deleted ".
				$ext_where.
				"GROUP BY accts.account_id ORDER BY $name";
		if($max_register > 0) $query .= " LIMIT $max_register";	
		$result = $this->db->query($query, true, "Error retrieving $module IDs for export");
		while($row = $this->db->fetchByAssoc($result)) {
			if($max_register >= 0 && count($added) >= $max_register)
				break;
			qb_log_debug("REGISTER $module {$row['id']} {$row['name']}");
			$do_save = true;
			$do_add = true;
			$status = 'pending_export';
			$first = '';
			if($module == 'PaymentsOut') {
				if (QBPaymentMethod::is_check_method($server_id, $row['payment_type'])) {
					$qb_type = 'BillPaymentCheck';
				} else {
					$qb_type = 'BillPaymentCreditCard';
				}
			} else if($module == 'Payments') {
				if ($row['direction'] == 'credit') {
					if (QBPaymentMethod::is_check_method($server_id, $row['payment_type'])) {
						$qb_type = 'Check';
					} else {
						$qb_type = 'ARRefundCreditCard';
					}
					$qb_type = 'Check';
				} else {
					$qb_type = 'ReceivePayment';
				}
			} else if($module == 'Invoice') {
				$qb_type = 'Invoice';
			} else if($module == 'CreditNotes') {
				if ($ph['base'] == 'ReceivePayment') {
					$lq = new ListQuery('QBTally');
					$lq->addSimpleFilter('qb_type', 'CreditMemo');
					$lq->addSimpleFilter('system_type', 'CreditNotes');
					$lq->addSimpleFilter('system_id', $row['id']);
					$lq->addSimpleFilter('server_id', $server_id);
					$lq->addSimpleFilter('first_sync', array('exported', 'imported'));
					$exported = $lq->runQuerySingle();
					if (!$exported->getField('id')) {
						$do_save = false;
					} else {
						if ($exported->getField('first_sync') == 'imported') {
							$status = '';
							$first = 'imported';
							$do_add = false;
						}
					}
					$qb_type = 'ReceivePayment';
				}
				else
					$qb_type = 'CreditMemo';
			}
			if ($do_save) {
				$seed = new QBTally();
				$seed->qb_type = $qb_type;
				$seed->system_type = $module;
				$seed->system_id = $row['id'];
				$seed->shortname = $row['prefix'].$row['number'];
				if($module != 'Payments' && $module != 'PaymentsOut')
					$seed->name = $row['name'];
				else
					$seed->name = $seed->shortname;
				$seed->first_sync = $first;
				$seed->sync_status = $status;
				$seed->status_msg = '';
				$seed->server_id = $server_id;
				$seed->save();
				if ($do_add) 
					$added[] = $seed->id;
			}
		}
		return true;
	}
	
	
	function &register_pending_exports($server_id, $max_register=-1) {
		$added = array();
		foreach($this->phases as $phase_id => $phase) {
	
			$version = QBConfig::get_server_setting($server_id, 'Server', 'qb_xml_version');
			if (isset($phase['version']) && $version < $phase['version']) {
				continue;
			}
	
			if(QBConfig::get_server_setting($server_id, 'Export', $phase['export_chk'])) {
				$added += $this->register_phase_exports($server_id, $max_register, $phase_id);
			}
		}
		return $added;
	}
	
	
	function &register_phase_exports($server_id, $max_register=-1, $phase) {
		$added = array();
		$remain = $max_register;
		if(! $remain)
			return;
		$this->register_export_list($this->phases[$phase], $added, $server_id, $remain);
		if($remain >= 0) $remain = max(0, $max_register - count($added));
		return $added;
	}
	
	
	function get_export_request(&$ret, &$errmsg, $update=false) {
		$base = $this->qb_type;
		$ret = array(
			'type' => $update ? 'update' : 'export',
			'base' => $base,
		);
		if(empty($this->system_object)) {
			if(empty($this->system_id) || ! $this->fetch_system_object()) {
				$errmsg = "No associated object";
				return false;
			}
		}
		$tally =& $this->system_object;
		$details = array();

		
		$cat = $tally->model->module_dir;
		
		if ($base == 'ReceivePayment' && $cat == 'CreditNotes') {
			return $this->addCreditNoteInvoices($ret, $errmsg, $update);
		}


		if ($cat == 'Payments' && $tally->getField('direction') == 'outgoing') {
			$cat = 'Bills';
		} elseif($cat == 'Invoice' || $cat == 'Payments' || $cat == 'CreditNotes') {
			$cat = 'Invoices';
		}
		if(! QBConfig::get_server_setting($this->server_id, 'Export', $cat)) {
			$errmsg = 'Export disabled';
			$this->retry_export_later = true;
			return false;
		}
		
		if($update) {
			$details['TxnID'] = $this->qb_id;
			$details['EditSequence'] = $this->qb_editseq;
		}
		
		if($tally->model_name == 'Payment')
			$acct_id_f = 'account_id';
		elseif($tally->model_name == 'Bill')
			$acct_id_f = 'supplier_id';
		else
			$acct_id_f = 'billing_account_id';
		
		//$currency = QBCurrency::from_iah_currency($this->server_id, $tally->currency_id);
		// tally currency must match QB account currency - check, and fail otherwise
		$ent = new QBEntity();
		if(! $ent->retrieve_for_account($tally->getField($acct_id_f), $this->server_id)
			|| empty($ent->qb_id)) {
			$errmsg = "Account not yet exported";
			$this->retry_export_later = true;
			return false;
		}
		if($tally->model_name == 'Bill') {
			$details['VendorRef'] = $ent->get_ref();
		} elseif($tally->model_name == 'Payment' && $tally->getField('direction') == 'outgoing') {
			$details['PayeeEntityRef'] = $ent->get_ref();
			if (QBPaymentMethod::is_check_method($this->server_id, $tally->getField('payment_type'))) {
				$key = 'checking_account';
			} else {
				$key = 'cc_account';
			}
			$bank_id = QBConfig::get_server_setting($this->server_id, 'Items', $key);
			if (empty($bank_id)) {
				$errmsg = "Bank account for payments is not set";
				$this->retry_export_later = true;
				return false;
			}
			if (QBPaymentMethod::is_check_method($this->server_id, $tally->getField('payment_type'))) {
				$details['BankAccountRef']['ListID'] = $bank_id;
			} else {
				$details['CreditCardAccountRef']['ListID'] = $bank_id;
			}
		} elseif($tally->model_name == 'Payment' && $tally->getField('direction') == 'credit') {
			if (QBPaymentMethod::is_check_method($this->server_id, $tally->getField('payment_type'))) {
				$key = 'checking_account';
			} else {
				$key = 'cc_account';
			}
			$bank_id = QBConfig::get_server_setting($this->server_id, 'Items', $key);
			if (empty($bank_id)) {
				$errmsg = "Bank account for payments is not set";
				$this->retry_export_later = true;
				return false;
			}
			if (1 || QBPaymentMethod::is_check_method($this->server_id, $tally->getField('payment_type'))) {
				$details['PayeeEntityRef'] = $ent->get_ref();
				$details['AccountRef']['ListID'] = $bank_id;
			} else {
				$details['CustomerRef'] = $ent->get_ref();
				$details['RefundFromAccountRef']['ListID'] = $bank_id;
			}
		} else {
			$details['CustomerRef'] = $ent->get_ref();
		}
		
		if(! $ent->fetch_account()) {
			$errmsg = "Error loading related account";
			return false;
		}
		
		if(! cmp_currency_ids($ent->account->currency_id, $tally->getField('currency_id'))) {
			///qb_log_error("NO MATCH {$ent->account->currency_id} != {$tally->currency_id}");
			$errmsg = "Currency does not match account currency";
			return false;
		}
		
		// ClassRef
		// ARAccountRef - Invoice only
		// TemplateRef

		// AP/AR
		// We only set AP/AR in ADD requests
		if (!$update) {
			if ($tally->model_name == 'Bill') {
				$ap = QBConfig::get_server_setting($this->server_id, 'Items', 'accounts_payable');
				if ($ap) {
					$details['APAccountRef']['ListID'] = $ap;
				}
			} elseif ($tally->model_name == 'Invoice') {
				$ar = QBConfig::get_server_setting($this->server_id, 'Items', 'accounts_receivable');
				if ($ar) {
					$details['ARAccountRef']['ListID'] = $ar;
				}
			}
		}

			
		if($tally->model_name == 'Bill')
			$txn = qb_export_date_time($tally->getField('bill_date'));
		else
			$txn = qb_export_date_time($tally->getField('date_entered'));
		$details['TxnDate'] = substr($txn, 0, 10);
		
		if($tally->model_name == 'Quote')
			$nf = 'quote_number';
		else if($tally->model_name == 'Invoice')
			$nf = 'invoice_number';
		else if($tally->model_name == 'CreditNote')
			$nf = 'credit_number';
		else if($tally->model_name == 'Payment')
			$nf = 'payment_id';
		else if($tally->model_name == 'Bill')
			$nf = 'bill_number';
		else {
			$errmsg = "Cannot export system object: {$tally->model_name}";
			return false;
		}
		$refno = $tally->getField('prefix') . $tally->getField($nf);



		if($tally->model_name == 'Payment') {

			if ($tally->getField('direction') == 'outgoing') {
				if($update) {
					$errmsg = "Cannot update payment in QuickBooks";
					return false;
				}
				$ap = QBConfig::get_server_setting($this->server_id, 'Items', 'accounts_payable');
				$details['APAccountRef']['ListID'] = $ap;
			}
			if ($tally->getField('direction') == 'incoming' && $tally->getField('refund')) {
				if($update) {
					$errmsg = "Cannot update payment in QuickBooks";
					return false;
				}
			}
			
			$details['RefNumber'] = substr(''.$tally->getField('customer_reference'), 0, 20);
		
			// may wish to set ARAccountRef, DepositToAccountRef
			
			$details['TotalAmount'] = sprintf('%0.2f', $tally->getField('amount'));
			
			$paymethod = QBPaymentMethod::from_iah_method($this->server_id, $tally->getField('payment_type'));
			if($paymethod) {
				// payment method is optional in QuickBooks, leave unspecified when value is not mapped
				$details['PaymentMethodRef'] = $paymethod->get_ref();
			}
			
			$memo = "[$refno]";
			if($tally->getField('notes'))
				$memo .= ' '.trim(preg_replace("~[\t\r\n]+~", ' ', $tally->getField('notes')));
			$details['Memo'] = substr($memo, 0, 4095);

			$lg_exp = $this->export_payment_lines($tally, $details, $update);
			if(! $lg_exp) {
				$errmsg = "Error exporting payment lines: {$this->status_msg}";
				return false;
			}
			
			if (!$update &&  ($base == 'BillPaymentCheck' || $base == 'Check')) {
				$details['IsToBePrinted'] = 'true';
				if ($base == 'BillPaymentCheck') {
					unset($details['RefNumber']);
				}
			}
		} else {
			if(! $update)
				$details['RefNumber'] = (strlen($refno) > 11) ? substr($refno, -11) : $refno;

			if ($base != 'Bill') {
				$details['BillAddress'] = qb_export_address($this->server_id, $tally, 'billing_address_');
			}
			
			if($base == 'Invoice')
				$details['ShipAddress'] = qb_export_address($this->server_id, $tally, 'shipping_address_');
			
			// IsPending
		
			if($poNum = $tally->getField('purchase_order_num'))
				$details['PONumber'] = $poNum;
			
			$terms = QBTerms::from_iah_terms($this->server_id, $tally->getField('terms'));
			if($terms)
				$details['TermsRef'] = $terms->get_ref();
			
			if($tally->model_name == 'Quote') {
				if(($vu = $tally->getField('valid_until')) && $vu != '0000-00-00')
					$details['DueDate'] = qb_export_date($vu);
			}
			else {
				if(($dd = $tally->getField('due_date')) && $dd != '0000-00-00') // may be null for some e-commerce invoices
					$details['DueDate'] = qb_export_date($dd);
			}
			
			// SalesRepRef (assigned user)
			// FOB (freight on board) - for Estimate only
			// ShipDate
			//

			if($base == 'Invoice') {
				$shipmeth = QBShipMethod::from_iah_method($this->server_id, $tally->getField('shipping_provider_id'));
				if($shipmeth)
					$details['ShipMethodRef'] = $shipmeth->get_ref();
			}
			
			if(! $update)
				$details['Memo'] = $tally->getField('name');
			else {
				$memo = $tally->getField('name');
				if($this->first_sync == 'imported' && preg_match('~^\[([^\]]+)\] (.*)$~', $memo, $m))
					$memo = $m[2];
				$details['Memo'] = $memo;
			}
	
			// CustomerMsgRef
			// IsToBePrinted - Invoice only
			// CustomerTaxCodeRef - Invoice only - copy of CustomerTaxCodeRef set on Customer object
	
			$lg_exp = $this->export_line_groups($base, $tally, $details, $update);
			if(! $lg_exp) {
				$errmsg = "Error exporting line groups: {$this->status_msg}";
				return false;
			}
			else if($lg_exp == 'update') {
				$this->sync_status = 'pending_update';
				$this->status_msg = '';
			}
		}
		
		//$details['ExchangeRate'] = $tally->exchange_rate;
		
		$this->date_last_sync = $tally->getField('date_modified');

		$details = $this->reorderDetails($details, $base);
		if($tally->model_name == 'Payment' && $tally->getField('direction') == 'outgoing') {
			unset($details['TotalAmount']);
			unset($details['PaymentMethodRef']);
		}
		
		if ($tally->model_name == 'Payment' && $tally->getField('direction') == 'credit') {
			unset($details['TotalAmount']);
			if (QBPaymentMethod::is_check_method($this->server_id, $tally->getField('payment_type'))) {
				unset($details['PaymentMethodRef']);
			}
		}

		$eop = $update ? 'Mod' : 'Add';
		$ret['params'][$base.$eop] =& $details;
		/*qb_log_debug("EXPORTREQ");
		qb_log_debug($ret);*/
		
		return true;
	}

	function addCreditNoteInvoices(&$ret, &$errmsg, $update)
	{
		if (!$update) {
			$inv_id = $this->system_object->getField('invoice_id');
			if ($inv_id &&  $this->system_object->getField('apply_credit_note')) {
				$inv = new QBTally;
				if (!$inv->retrieve_for_related($this->server_id, 'Invoice', $inv_id)) {
					$errmsg = 'Error applying credit note to invoice - related invoice not yet exported : ' . $inv_id;
					$this->retry_export_later = true;
					return false;
				}
				$ent = new QBEntity;
				$ent->retrieve_for_account($this->system_object->getField('billing_account_id'), $this->server_id);
				$cn = new QBTally;
				$cn->retrieve_for_related2($this->server_id, 'CreditNotes', $this->system_object->getPrimaryKeyValue(), 'CreditMemo');
				$cn->fetch_system_object();
				$ret['params'] = array(
					'ReceivePaymentAdd' => array(
					    'CustomerRef' => $ent->get_ref(),
						'AppliedToTxnAdd' => array(
							'TxnID' => $inv->qb_id,
							'SetCredit' => array(
								'CreditTxnID' => $cn->qb_id,
								'AppliedAmount' => sprintf("%0.2f", $cn->system_object->getField('amount')),
							),
						),
					),
				);
				return true;
			}
		}
		return false;
	}
	
	
	function &export_line_groups($type, &$tally, &$out, $update=false) {
		$c = 0;
		$grp_elts = array();
		$lc = 0;
		$lop = $update ? 'Mod' : 'Add';
		$fail_err = $update ? 'update_error' : 'export_error';
		$cstm_line_ref = null;
		$cstm_assm_ref = null;
		$hours_line_ref = null;
		$placeholder = false;
		$followup = false;
		$addlines = array();
		$taxes = array();
		$no_line_reuse = false; // cannot add recycled (existing in QB) lines after new ones
		$gidx = 0;
		$skipped = array();
		
		$grps = $this->get_sorted_line_groups($tally);

		if(! count($grps)) {
			/*$this->sync_status = 'export_error';
			$this->status_msg = "No line groups";
			return false;*/
			$placeholder = true;
		}
		
		$edition = QBServer::get_server_edition($this->server_id);
		$no_tax_support = false;
		if($edition == 'US') {
			$tax_code_key = 'SalesTaxCodeRef';
			$no_tax_support = QBConfig::get_server_setting($server_id, 'Server', 'sales_tax_disabled');
			if ($type == 'Bill') {
				$no_tax_support = true;
			}
		}
		else
			$tax_code_key = 'TaxCodeRef';
		
		if(! $placeholder)
		foreach($grps as $gid => $g) {
			$grp_first_tax = null;
			if($placeholder)
				break;
			$grp_tax_rates = array();
			if(! empty($g['adjusts'])) {
				foreach($g['adjusts'] as $adjidx => $adj) {
					if($adj['type'] == 'StandardTax' || $adj['type'] == 'CompoundedTax') {
						$grp_tax_rates[] = array($adj['related_id'], $adj['rate'], $adj['type'] == 'CompoundedTax');
					}
				}
			}
			if(empty($g['lines']))
				continue;
			foreach($g['lines'] as $lnidx => $ln) {
				$lnid = $ln['id'];
				if(empty($ln['is_comment']) && ! isset($ln['ext_quantity'])) {
					//$this->status_msg = "Line item(s) missing ext_quantity";
					//qb_log_debug($this->status_msg);
					//return false;
					$ln['ext_quantity'] = $ln['quantity']; // work around data error
				}
				$row = array(
					'TxnLineID' => $update ? '-1' : null,
					'ItemRef' => null,
					'ItemGroupRef' => null,
					'Desc' => $ln['name'],
					'Quantity' => array_get_default($ln, 'ext_quantity'), // null for comments
					'Rate' => null,
					//'RatePercent' => null,
					'AccountRef' => null,
					'Amount' => null,
					'Memo' => null,
					'Cost' => null,
					// ClassRef, ServiceDate
					$tax_code_key => null,
					// OverrideItemAccountRef
					'MarkupRate' => null, // Estimate only
					'MarkupRatePercent' => null, // Estimate only
				);
				if (!is_null($row['Quantity'])) {
					$row['Quantity'];
				}
				if(isset($this->txn_line_ids[$gid][$lnid]) && ((! $no_line_reuse) || $ln['sum_of_components']))
					$row['TxnLineID'] = $this->txn_line_ids[$gid][$lnid];
				else
					$no_line_reuse = true;
				if(empty($ln['is_comment'])) {
					if($type == 'Estimate' && ! $ln['sum_of_components']) {
						$ext_cost = $ln['cost_price'] * $ln['ext_quantity'];
						$row['Rate'] = sprintf('%0.5f', $ln['cost_price']);
						$diff = ($ln['ext_price'] - $ext_cost) ;
						if($ln['cost_price'] > 0.0) {
							$perc = $diff * 100 / $ext_cost;
							$rperc = round($perc, 3);
							if($perc == $rperc)
								$row['MarkupRatePercent'] = $rperc;
						}
						if(! isset($row['MarkupRatePercent']))
							$row['MarkupRate'] = sprintf('%0.5f', $diff);
					}
					else {
						if ($type == 'Bill') {
							$row['Cost'] = sprintf('%0.5f', $ln['unit_price']);
						} else {
							$row['Rate'] = sprintf('%0.5f', $ln['unit_price']);
							//$row['Amount'] = sprintf('%0.2f', $ln['unit_price'] * $ln['ext_quantity']);
						}
					}
					$rel = QBItem::lookup_item($ln['related_type'], $ln['related_id'], $this->server_id);
					if(! $rel && ! empty($ln['is_comment']))
						qb_log_debug('related obj not found. '.$ln['related_type'].'/'.$ln['related_id']);
					if($rel && empty($rel->qb_id)) {
						$this->status_msg = "{$rel->qb_type} component object is pending export";
						qb_log_debug($this->status_msg);
						$this->retry_export_later = true;
						return false;
					}
				}
				if($ln['sum_of_components']) {
					if(! $ln['quantity']) {
						$skipped[$ln['id']] = true;
						continue; // QB does not allow zero qty item groups
					}
					unset($row['Rate']);
					unset($row['Amount']);
					//if($update)
						unset($row['Desc']); // cannot set in update; also not allowed in CA2006
					if($ln['related_type'] == 'Assemblies') {
						if($rel) $ref = $rel->get_ref();
						else {
							if(! $cstm_assm_ref) {
								$qid = QBItem::get_custom_assembly_item_id($this->server_id);
								$rel = new QBItem();
								if($qid && $rel->qb_retrieve($qid, $this->server_id)) {
									$cstm_assm_ref = $rel->get_ref();
								}
								else {
									//$this->sync_status = $fail_err;
									$this->status_msg = "No 'Custom Assembly' ItemGroup";
									qb_log_debug($this->status_msg);
									$this->retry_export_later = true;
									return false;
								}
							}
							$ref = $cstm_assm_ref;
						}
						if(! $update || $row['TxnLineID'] == '-1')
							$row['ItemGroupRef'] = $ref;
					}
					$elt = $type . 'LineGroup'.$lop.'___' .$c++;
					$addlines[$elt] = $row;
					$grp_elts[$ln['id']] = $elt;
				}
				else {
					$qb_tax = QBTaxCode::from_iah_tax_code($this->server_id, $ln['tax_class_id']/*, $grp_tax_rates*/);
					if($qb_tax && ! $no_tax_support && (isset($row['Rate']) || isset($row['Amount']) || isset($row['Cost'])) ) {
						$row[$tax_code_key] = $qb_tax->get_ref();
						if (empty($grp_first_tax)) {
							$grp_first_tax = $row[$tax_code_key];
						}
					}
					if($qb_tax && ! $qb_tax->qb_id) {
						$this->status_msg = "Related tax code not yet exported (check tax rates)";
						qb_log_debug($this->status_msg);
						$this->retry_export_later = true;
						return false;
					}
					if(! empty($ln['is_comment'])) {
						if ($type != 'Bill' || $g['group_type'] != 'expenses') {
							$row['Desc'] = $ln['body'];
						} else {
							$row['Memo'] = $ln['body'];
							$row['Desc'] = null;
						}
					}
					else if($ln['related_type'] == 'Booking' || $ln['related_type'] == 'BookingCategories') {
						if ($type == 'Bill') {
							$expenseAccount = QBAccount::qb_account_from_expense_id($this->server_id, $ln['related_id']);
							if (!$expenseAccount) {
								$this->status_msg = "Cannot map expense category to QB account";
								qb_log_debug($this->status_msg);
								$this->retry_export_later = true;
								return false;
							}
							$row['AccountRef'] = $expenseAccount->get_ref();
							$row['Memo'] = $ln['description'];
							$row['Amount'] = sprintf("%0.2f", $ln['unit_price']);
							$row['Desc'] = null;
							$row['Quantity'] = null;
							$row['Cost'] = null;
						} else {
							if(! $hours_line_ref) {
								$qid = QBItem::get_booking_line_item_id($this->server_id);
								$rel = new QBItem();
								if($qid && $rel->qb_retrieve($qid, $this->server_id)) {
									$hours_line_ref = $rel->get_ref();
								}
								else {
									//$this->sync_status = $fail_err;
									$this->status_msg = "No 'Booked Hours' Item";
									qb_log_debug($this->status_msg);
									$this->retry_export_later = true;
									return false;
								}
							}
							$row['ItemRef'] = array('ListID' => $hours_line_ref['ListID']);
						}
					}
					else if($ln['related_type'] == 'ProductCatalog') {
						if($rel) $ref = $rel->get_ref();
						else {
							if(! $cstm_line_ref) {
								$qid = QBItem::get_custom_line_item_id($this->server_id);
								$rel = new QBItem();
								if($qid && $rel->qb_retrieve($qid, $this->server_id)) {
									$cstm_line_ref = $rel->get_ref();
								}
								else {
									//$this->sync_status = $fail_err;
									$this->status_msg = "No 'Custom Product' Item";
									qb_log_debug($this->status_msg);
									$this->retry_export_later = true;
									return false;
								}
							}
							$ref = array('ListID' => $cstm_line_ref['ListID']);
						}
						$row['ItemRef'] = $ref;
					}
					if ($type == 'Bill') {
						if ($g['group_type'] == 'expenses') {
							$key = 'ExpenseLine';
						} else {
							$key = 'ItemLine';
						}
					} else  {
						$key = $type.'Line';
					}
					$elt = $key.$lop.'___' .$c++;
					if($ln['depth']) {
						if(empty($grp_elts[$ln['parent_id']])) {
							if(! empty($skipped[$ln['parent_id']]))
								continue;
							$this->sync_status = $fail_err;
							$this->status_msg = "Could not interpret line items";
							qb_log_debug($this->status_msg);
							return false;
						}
						$g_elt = $grp_elts[$ln['parent_id']];
						
						if(! $update || $addlines[$g_elt]['TxnLineID'] == '-1') {
							// only add components when doing an update and assembly already exists
							//$placeholder = true;
							$followup = true;
							continue; // break;
						}

						$addlines[$g_elt][$elt] = $row;
					}
					else {
						$addlines[$elt] = $row;
					}
				}
				$lc ++;
			}

			if(! isset($std_subt_item)) {
				$subt_item_id = QBItem::get_standard_subtotal_item_id($this->server_id);
				$std_subt_item = new QBItem();
				if(! $std_subt_item->qb_retrieve($subt_item_id, $this->server_id)) {
					//$this->sync_status = $fail_err;
					$this->status_msg = "Standard subtotal item not found";
					qb_log_debug($this->status_msg);
					$this->retry_export_later = true;
					return false;
				}
			}
			if(isset($this->txn_line_ids[$gidx]['subtotal'])) {
				if(! $no_line_reuse)
					$subt_line_id = $this->txn_line_ids[$gidx]['subtotal'];
				else
					$subt_line_id = '-1';
				$subt_item_id = $this->txn_line_ids[$gidx]['subtotal_qbid'];
				$subt_item = new QBItem();
				if(! $subt_item->qb_retrieve($subt_item_id, $this->server_id))
					$subt_item = $std_subt_item;
			}
			else {
				$subt_line_id = '-1';
				$subt_item = $std_subt_item;
				$no_line_reuse = true;
			}
			// add subtotal
			$row = array(
				'TxnLineID' => $update ? $subt_line_id : null,
				'ItemRef' => $subt_item->get_ref(),
				'Desc' => '-',
				//'Quantity' => '',
				//'Amount' => $g->subtotal, // auto-calc
			);
			if ($type == 'Bill')
				$key = 'ItemLine';
			else 
				$key = $type.'Line';
			$elt = $key.$lop.'___' .$c++;
			// TODO remove !!!!!!

			$addlines[$elt] = $row;
						
			// add discounts, taxes etc
			// TODO For Bills, this should be modified
			//if($type == 'Bill')
			//	continue;
			if(empty($g['adjusts']))
				continue;
			foreach($g['adjusts'] as $adjidx => $adj) {
				$adjid = $adj['id'];
				$row = array(
					'TxnLineID' => $update ? '-1' : null,
					'ItemRef' => null,
					'ItemGroupRef' => null,
					'Desc' => $adj['name'],
					'Quantity' => null,
					'Rate' => null,
					'Cost' => null,
					'RatePercent' => null,
					// ClassRef, Amount, ServiceDate
					$tax_code_key => null,
					//OverrideItemAccountRef
					//'MarkupRate' => null, // Estimate only
					//'MarkupRatePercent' => null, // Estimate only
				);
				if(isset($this->txn_line_ids[$gid][$adjid]) && ! $no_line_reuse)
					$row['TxnLineID'] = $this->txn_line_ids[$gid][$adjid];
				else
					$no_line_reuse = true;
				if(! isset($std_tax_code)) {
					$std_tax_code = QBTaxCode::standard_qb_tax_code($this->server_id);
					if(! $std_tax_code) {
						$this->sync_status = $fail_err;
						$this->status_msg = "Error loading standard tax code";
						qb_log_debug($this->status_msg);
						return false;
					}
					$exempt_tax_code = QBTaxCode::exempt_qb_tax_code($this->server_id);
					if(! $exempt_tax_code) {
						$this->sync_status = $fail_err;
						$this->status_msg = "Error loading tax-exempt tax code";
						qb_log_debug($this->status_msg);
						return false;
					}
				}
				if(! $no_tax_support)
					$row[$tax_code_key] = $exempt_tax_code->get_ref();
				if(($adj['type'] == 'StandardDiscount' || $adj['type'] == 'StdPercentDiscount') && ! empty($adj['related_id']) && $adj['related_id'] != '-99') {
					if ($type == 'Bill') {
						$this->sync_status = $fail_err;
						$this->status_msg = 'QuickBooks would not accept discounts in Bills';
						qb_log_debug($this->status_msg);
						return false;
					}
					$rel = QBItem::lookup_item($adj['related_type'], $adj['related_id'], $this->server_id);
					if(! $rel) {
						qb_log_debug('Related discount not found. '.$adj['related_type'].'/'.$adj['related_id']);
						$this->sync_status = $fail_err;
						$this->status_msg = "Error linking discounts";
						return false;
					}
					$row['ItemRef'] = $rel->get_ref();
					$row['RatePercent'] = $adj['rate'];
					if (empty($tally->discount_before_taxes)) {
						$row[$tax_code_key] = $grp_first_tax;
					} else {
						$qb_code = QBTaxCode::from_iah_tax_code($this->server_id, $adj['tax_class_id']);
						if($qb_code && $qb_code->related_id) {
							$row[$tax_code_key] = $qb_code->get_ref();
						}
					}
					if(! $no_tax_support && empty($row[$tax_code_key]))
						$row[$tax_code_key] = $exempt_tax_code->get_ref();
					if ($type == 'Bill')
						$key = 'ItemLine';
					else 
						$key = $type.'Line';
					$elt = $key.$lop.'___' .$c++;
					$addlines[$elt] = $row;
				}
				else if($adj['type'] == 'StandardTax' || $adj['type'] == 'CompoundedTax') {
					$tax = QBTaxCode::from_iah_tax_rate($this->server_id, $adj['related_id']);
					if(! $tax) {
						qb_log_debug('Tax rate could not be synced. '.$adj['related_type'].'/'.$adj['related_id']);
						$this->sync_status = $fail_err;
						$this->status_msg = "Error linking taxes";
						return false;
					}
					if($edition == 'US') {
						// add ItemSalesTax item
						$row['ItemRef'] = $tax->get_ref();
						$row['RatePercent'] = $adj['rate'];
							//$row['Rate'] = $adj['amount'];
					}
					else {
						$tax['rate'] = sprintf('%0.2f', $adj['rate']);
						$tax['amount'] = sprintf('%0.2f', $adj['amount']);
						$taxes[] = $tax;
					}
				}
				else if($adj['type'] == 'TaxedShipping' || $adj['type'] == 'UntaxedShipping') {
					$qb_id = QBItem::get_standard_shipping_item_id($this->server_id);
					if(! $qb_id) {
						qb_log_debug('Standard shipping item not found');
						//$this->sync_status = $fail_err;
						$this->status_msg = "Error adding shipping";
						$this->retry_export_later = true;
						return false;
					}
					$row['Desc'] = 'Shipping'; // FIXME - look up name of shipping provider
					$row['ItemRef'] = array('ListID' => $qb_id);
					if ($type == 'Bill') {
						$row['Cost'] = sprintf('%0.2f', $adj['amount']);
					} else {
						$row['Rate'] = sprintf('%0.2f', $adj['amount']);
					}
					if($adj['type'] == 'UntaxedShipping' && ! $no_tax_support) {
						$row[$tax_code_key] = $exempt_tax_code->get_ref();
					} elseif ($adj['type'] == 'TaxedShipping') {
						if (!empty($adj['tax_class_id']) && $adj['tax_class_id'] != '-99') {
							$shipping_tax = QBTaxCode::from_iah_tax_code($this->server_id, $adj['tax_class_id']);
							if($shipping_tax && $shipping_tax->related_id) {
								$row[$tax_code_key] = $shipping_tax->get_ref();
							}
						}
					}
					if ($type == 'Bill')
						$key = 'ItemLine';
					else 
						$key = $type.'Line';
					$elt = $key.$lop.'___' .$c++;
					$addlines[$elt] = $row;
				}
			}
			
			$gidx ++;
		}
		if(! $lc) {
			/*$this->sync_status = 'export_error';
			$this->status_msg = "No line items";
			return false;*/
			$placeholder = true;
		}
		
		if($placeholder) {
			if ($tally->model_name == 'Bill') {
				$key = 'ItemLine'.$lop;
				$priceKey = 'Cost';
			} else {
				$key = $type.'Line'.$lop;
				$priceKey = 'Rate';
			}
			$out[$key] = array(
				array(
					'TxnLineID' => $update ? '-1' : null,
					'ItemRef' => null,
					'Desc' => $this->no_line_items_cmt,
					'Quantity' => '0',
					$priceKey => '0.00',
				),
			);
		}
		else {
			$out += $addlines;
			if($edition != 'US') {
				$tax1 = $tax2 = 0.0;
				foreach($taxes as $t) {
					if($t['qb_id'] == 1)
						$tax1 += $t['amount'];
					else
						$tax2 += $t['amount'];
				}
				$out['Tax1Total'] = sprintf('%0.2f', $tax1);
				if($tax2 || $edition == 'CA')
					$out['Tax2Total'] = sprintf('%0.2f', $tax2);
			}
		}
		if($followup)
			return 'update';
		return true;
	}
	
	
	function export_payment_lines(&$tally, &$out, $update=false) {
		$lc = 0;
		$lop = $update ? 'Mod' : 'Add';
		$fail_err = $update ? 'update_error' : 'export_error';

		$direction = $tally->getField('direction');
		$sysType = ($direction == 'incoming') ? 'Invoice' : ($direction  == 'credit' ? 'CreditNotes' : 'Bills');
		$lineType = ($direction == 'incoming') ? 'invoices' : ($direction == 'credit' ? 'credits' : 'bills');
		$relName = ($direction == 'incoming') ? 'invoice_id' : ($direction == 'credit' ? 'credit_id' : 'bill_id');


		$line_items = Payment::query_line_items($tally->getPrimaryKeyValue());
		if(! count($line_items[$lineType])) {
			$this->sync_status = $fail_err;
			$this->status_msg = "Payment has no line items";
			return false;
		}

		if ($direction == 'credit') {
			if (QBPaymentMethod::is_check_method($this->server_id, $tally->getField('payment_type'))) {
				$el_base = 'ApplyCheckToTxn';
				$amount_el_base = 'Amount';
			} else {
				$el_base = 'RefundAppliedToTxn';
				$amount_el_base = 'RefundAmount';
			}
			$el_base = 'ApplyCheckToTxn';
			$amount_el_base = 'Amount';

		} else {
			$el_base = 'AppliedToTxn';
			$amount_el_base = 'PaymentAmount';
		}
		if ($direction == 'incoming') {
			$ar = QBConfig::get_server_setting($this->server_id, 'Items', 'accounts_receivable');
			if ($ar) {
				$out['ARAccountRef']['ListID'] = $ar;
			}
		} elseif ($tally->model_name == 'Invoice') {
			$ap = QBConfig::get_server_setting($this->server_id, 'Items', 'accounts_payable');
			if ($ap) {
				$out['APAccountRef']['ListID'] = $ap;
			}
		}
		
		foreach($line_items as $iType => $lines) {
			if ($iType != $lineType) continue;
			foreach ($lines as $line) {
				$inv = new QBTally();
				$inv->retrieve_for_related($this->server_id, $sysType, $line[$relName]);
				if(! $inv) {
					//$this->sync_status = $fail_err;
					$this->status_msg = "Error retrieving associated invoice";
					qb_log_debug($this->status_msg);
					$this->retry_export_later = true;
					return false;
				}
				if(empty($inv->qb_id)) {
					//$this->sync_status = $fail_err;
					$this->status_msg = "Associated invoice not yet exported: $inv->name $inv->id";
					qb_log_debug($this->status_msg);
					$this->retry_export_later = true;
					return false;
				}
				$row = array(
					// 'TxnLineID' => $update ? '-1' : null,
					'TxnID' => $inv->qb_id,
					$amount_el_base => sprintf('%0.2f', $line['amount']),
					//'SetCredit' => null,
					//'DiscountAmount' => null,
					//'DiscountAccountRef' => null,
				);
				$elt = $el_base . $lop .'___' .($lc++);
				$out[$elt] = $row;
		
				if ($direction == 'credit' /*&& QBPaymentMethod::is_check_method($this->server_id, $tally->getField('payment_type'))*/) {
					$iah_inv = new CreditNote;
					$iah_inv->retrieve($inv->system_id);
					$ent = new QBEntity();
					if(! $ent->retrieve_for_account($iah_inv->billing_account_id, $this->server_id) || empty($ent->qb_id)) {
						$this->status_msg = "Account not yet exported";
						$this->retry_export_later = true;
						return false;
					};
					
					$account_id = QBConfig::get_server_setting($this->server_id, 'Items', 'accounts_receivable');
					$row = array(
						'AccountRef' => array('ListID' => $account_id),
						'Amount' => sprintf('%0.2f', $line['amount']),
					);
					$row['CustomerRef'] = $ent->get_ref();
					$elt = 'ExpenseLine' . $lop .'___' .($lc++);
					$out[$elt] = $row;
				}
			}
		}
		

		return true;
	}
	
	
	function register_pending_updates($server_id) {
		foreach($this->phases as $phase => $ph) {
	
			$version = QBConfig::get_server_setting($server_id, 'Server', 'qb_xml_version');
			if (isset($ph['version']) && $version < $ph['version']) {
				continue;
			}
		
			if(! $this->register_phase_updates($server_id, $phase)) {
				return false;
			}
		}
		return true;
	}
	
	
	function register_phase_updates($server_id, $phase) {
		if($phase == 'Estimates') {
			$tbl = 'quotes';
		} else if($phase == 'Invoices') {
			$tbl = 'invoice';
		} else if($phase == 'Payments') {
			$tbl = 'payments';
		} else if($phase == 'Bills') {
			$tbl = 'bills';
		} else if($phase == 'BillCheckPayments') {
			$tbl = 'payments';
		} else if ($phase == 'BillCCPayments')  {
			$tbl = 'payments';
		} else if ($phase == 'CreditMemosInvoices') {
			return false; //$tbl = 'credit_notes';
		} else if ($phase == 'CreditMemos') {
			$tbl = 'credit_notes';
		} else if ($phase == 'Checks') {
			$tbl = 'payments';
		} else if ($phase == 'ARRefundCreditCards') {
			$tbl = 'payments';
		}
		else
			return false;
		$rel_date = 'date_last_sync';
		$sys_type = $this->phases[$phase]['module'];
		$qb_type = $this->phases[$phase]['base'];
		$sid = $this->db->quote($server_id);
		$query = "UPDATE `{$this->table_name}` me ".
				"LEFT JOIN `$tbl` rel ON rel.id=me.system_id ".
				"SET me.sync_status='pending_update' ".
				"WHERE me.server_id='$sid' ".
				"AND me.first_sync IN ('imported','exported') ".
				"AND me.system_type='$sys_type' ".
				"AND me.qb_type='$qb_type' ".
				"AND (me.sync_status='' OR me.sync_status IS NULL) ".
				"AND me.$rel_date IS NOT NULL ".
				"AND me.$rel_date < rel.date_modified ".
				"AND rel.id IS NOT NULL AND NOT me.deleted";
		$result = $this->db->query($query, false);
		if(! $result) {
			qb_log_error("Error marking $phase updates");
			return false;
		}
		return true;
	}
		
	
	// -- SugarBean overrides and utility methods
	
	function fill_in_additional_list_fields() {
		$id = $this->db->quote($this->system_id);
		if($this->system_type == 'Payments') {
			$tbl = 'payments';
			$name = " CONCAT($tbl.prefix, $tbl.payment_id) ";
		}
		else {
			$tbl = ($this->system_type == 'Quotes' ? 'quotes' : ($this->system_type == 'Bills' ? 'bills' : 'invoice'));
			$name = "$tbl.name";
		}
		$query = "SELECT $name AS system_name FROM $tbl WHERE $tbl.id='$id' AND NOT $tbl.deleted LIMIT 1";
		$result = $this->db->query($query, true, "Error retrieving related object names");
		if($row = $this->db->fetchByAssoc($result))
			foreach($row as $k=>$v) $this->$k = $v;
	}
		
	function get_list_view_data() {
		$row_data = parent::get_list_view_data();
		
		$row_data['SYSTEM_LINK'] = $this->get_linked_icon('system', $this->system_type, 'LBL_'.strtoupper($this->system_type));
		
		return $row_data;
	}
	
	// fix for 1.3
	function repair_short_names() {
		$query = "SELECT id FROM `{$this->table_name}` WHERE NOT deleted ".
			"AND (shortname IS NULL OR shortname='') ".
			"AND (system_id IS NOT NULL AND system_id!='')";
		$r = $this->db->query($query, false);
		if(! $r)
			return false;
		$ids = array();
		while($row = $this->db->fetchByAssoc($r))
			$ids[] = $row['id'];
		foreach($ids as $qb_id)
			$this->qb_re_register($qb_id);
		return true;
	}
	
	function qb_re_register($qb_id) {
		// update short/long names; mark deleted if necessary; mark pending update if necessary
		$seed = new QBTally();
		if(! $seed->retrieve($qb_id))
			return false;
		$upd = false;
		$bean_deleted = false;
		if($seed->fetch_system_object()) {
			$tally =& $seed->system_object;
			if($tally->deleted)
				$bean_deleted = true;
			else {
				if($seed->set_update_status_if_modified($tally))
					$upd = true;
				if($tally->model_name == 'Quote') {
					$seed->shortname = $tally->getField('prefix') . $tally->getField('quote_number');
					$seed->name = $tally->getField('name');
					$upd = true;
				}
				else if($tally->model_name == 'Invoice') {
					$seed->shortname = $tally->getField('prefix') . $tally->getField('invoice_number');
					$seed->name = $tally->getField('name');
					$upd = true;
				}
				else if($tally->model_name == 'CreditNote') {
					$seed->shortname = $tally->getField('prefix') . $tally->getField('credit_number');
					$seed->name = $tally->getField('name');
					$upd = true;
				}
				else if($tally->model_name == 'Bill') {
					$seed->shortname = $tally->getField('prefix') . $tally->getField('bill_number');
					$seed->name = $tally->getField('name');
					$upd = true;
				}
				else if($tally->model_name == 'Payment') {
					$seed->shortname = $tally->getField('prefix') . $tally->getField('payment_id');
					$nm = $tally->getField('customer_reference');
					if(strlen($tally->getField('notes'))) {
						if($nm) $nm .= ': ';
						$nm .= $tally->getField('notes');
					}
					if(! $nm) $nm = $seed->shortname;
					$seed->name = $nm;
					$upd = true;
				}
			}
		}
		else
			$bean_deleted = true;
		if($bean_deleted) {
			$seed->deleted = 1;
			$upd = true;
		}
		if($upd)
			$seed->save();
		return $upd;
	}

	function reorderDetails($details, $base)
	{
		switch ($base) {
			case'Bill':
				$order = array(
					'TxnID',
					'EditSequence',
					'VendorRef',
					'APAccountRef',
					'TxnDate',
					'DueDate',
					'RefNumber',
					'TermsRef',
					'Memo',
					'IsTaxIncluded',
					'SalesTaxCodeRef',
					'ExchangeRate',
					'LinkToTxnId',
					'/^ExpenseLine(Add|Mod).*$/',
					'/^ItemLine(Add|Mod).*$/',
				);
				break;
			case 'BillPaymentCheck':
				$order = array(
					'PayeeEntityRef',
					'APAccountRef',
					'TxnDate',
					'BankAccountRef',
					'IsToBePrinted',
					'RefNumber',
					'Memo',
					'AppliedToTxnAdd',
				);
				break;
			case 'BillPaymentCreditCard':
				$order = array(
					'PayeeEntityRef',
					'APAccountRef',
					'TxnDate',
					'CreditCardAccountRef',
					'IsToBePrinted',
					'RefNumber',
					'Memo',
					'AppliedToTxnAdd',
				);
				break;
			case 'Check':
				$order = array(
					'TxnID',
					'EditSequence',
					'AccountRef',
					'PayeeEntityRef',
					'RefNumber',
					'TxnDate',
					'Memo',
					'IsToBePrinted',
					'AppliedToTxnAdd',
				);
				break;
			case 'ReceivePayment':
				$order = array(
					'TxnID',
					'EditSequence',
					'CustomerRef',
					'ARAccountRef',
					'TxnDate',
					'RefNumber',
					'TotalAmount',
					'ExchangeRate',
					'PaymentMethodRef',
					'Memo',
					'DepositToAccountRef',
					'IsToBePrinted',
					'AppliedToTxnAdd',
					'AppliedToTxnMod',
				);
				break;
			default:
				return $details;
		}

		$ret = array();

		foreach ($order as $k) {
			if ($k[0] == '/') {
				foreach ($details as $kk => $vv) {
					if (preg_match($k, $kk)) {
						$ret[$kk] = $vv;
						unset($details[$kk]);
					}
				}
			} elseif (isset($details[$k])) {
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
		$dom = $app_list_strings['qb_tally_types_dom'];
		return array(''=>'') + $dom;
	}
}


?>
