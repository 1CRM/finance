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
require_once('modules/QBLink/QBAccount.php');
require_once('modules/QBLink/QBEntity.php');
require_once('modules/QBLink/QBCurrency.php');
require_once('modules/QBLink/QBTaxCode.php');
require_once('modules/Assemblies/Assembly.php');
require_once('modules/ProductCatalog/Product.php');
require_once('modules/ProductCategories/ProductCategory.php');
require_once('modules/ProductTypes/ProductType.php');
require_once('modules/Discounts/Discount.php');
require_once('modules/Booking/BookedHours.php');

define('MAX_ITEM_ID_LENGTH', 31);


class QBItem extends QBBean {
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $name;
	var $shortname;
	var $qb_type;
	var $assoc_rate;
	var $not_reimbursable;
	var $parent_qb_id;
	
	var $first_sync;
	var $sync_status;
	var $status_msg;

	var $system_type;
	var $system_id;
	
	var $object_name = 'QBItem';
	var $module_dir = 'QBLink';
	var $new_schema = true;
	var $table_name = 'qb_items';


	var $_sync_date;
	
	var $qb_cfg_category = 'Items';
	var $listview_template = "ItemListView.html";
	var $search_template = "ItemSearchForm.html";
	

	static $custom_item_names = array(
		'custom_product_partno' => 'Custom Product',
		'custom_product_name' => 'info@hand ad-hoc product',
		'custom_assembly_partno' => 'Custom Assembly',
		'custom_assembly_name' => 'info@hand ad-hoc assembly',
		'standard_booked_hours_partno' => 'Booked Hours',
		'standard_booked_hours_name' => 'info@hand generic booked hours',
		'standard_shipping_item_partno' => 'Shipping',
		'standard_shipping_item_name' => 'info@hand standard shipping charge',
		'standard_subtotal_item_partno' => 'Subtotal',
		'standard_subtotal_item_name' => 'info@hand standard subtotal item',
	);
	

	var $update_field_map = array(
		'Name' => 'shortname',
		'TimeCreated' => 'qb_date_entered',
		'TimeModified' => 'qb_date_modified',
		'IsActive' => 'qb_is_active',
	);
	
	var $item_accounts = array(
		'accounts_receivable' => array(
			'type' => array('AccountsReceivable'),
		),
		'accounts_payable' => array(
			'type' => array('AccountsPayable'),
		),
		'income_account' => array(
			'type' => array('Income', 'OtherIncome'),
		),
		'cost_goods_account' => array(
			'type' => 'CostOfGoodsSold',
		),
		'asset_account' => array(
			'type' => array('FixedAsset', 'OtherAsset', 'OtherCurrentAsset'),
		),
		'expense_account' => array(
			'type' => array('Expense', 'OtherExpense'),
		),
		'shipping_account' => array(
			'type' => array('Income', 'OtherIncome'),
		),
		'discount_account' => array(
			'type' => array('Expense', 'OtherExpense'),
		),
		'checking_account' => array(
			'type' => array('Bank'),
		),
		'cc_account' => array(
			'type' => array('Bank'),
		),
	);
	
	var $import_from = array(
		'ItemInventory', 'ItemNonInventory', 'ItemGroup',
		'ItemSubtotal', 'ItemOtherCharge',
	);
	
	static function &lookup_item($sys_type, $sys_id, $server_id) {
		global $db;
		$ret = null;
		if(! $sys_type || ! $sys_id || ! $server_id)
			return $ret;
		$q = sprintf("SELECT id FROM qb_items items WHERE system_type='%s' AND system_id='%s' AND server_id='%s' AND NOT deleted",
			$db->quote($sys_type), $db->quote($sys_id), $db->quote($server_id));
		$r = $db->query($q, true, "Error looking up related QB item");
		if($row = $db->fetchByAssoc($r)) {
			$ret = new QBItem();
			if(! $ret->retrieve($row['id']))
				$ret = null;
		}
		return $ret;
	}
	
	function phase_can_begin($server_id, $phase) {
		global $mod_strings;
		$err = '';
		if($phase == 'Items') {
			$sync_enabled = QBServer::get_sync_enabled($server_id);
			$cat = $this->get_import_category_id($server_id);
			$req_wh = $this->get_warehouse_required();
			$wh = $this->get_inventory_warehouse_id($server_id);
			if(! $sync_enabled)
				$err = $mod_strings['ERR_SYNC_DISABLED'];
			else if(! $cat)
				$err = $mod_strings['ERR_NO_ITEM_CATEGORY'];
			else if($req_wh && ! $wh)
				$err = $mod_strings['ERR_NO_ITEM_WAREHOUSE'];
		}
		if($err) {
			qb_log_error($err);
			return array('allow' => false, 'error' => $err);
		}
		return true;
	}
	
	function &get_pending_requests($server_id, $stage, $phase, $step) {
		$reqs = array();
		if($phase == 'Setup' && $step == 'GetConfigItems') {
			if($stage == 'import') {
				$edition = QBServer::get_server_edition($server_id);
				if($edition == 'US')
					$reqs[] = array(
						'type' => 'import',
						'base' => 'ItemSalesTax',
						'optimize' => 'auto',
					);
				$reqs[] = array(
					'type' => 'import',
					'base' => 'ItemDiscount',
					'optimize' => 'auto',
				);
			}
			else if($stage == 'export') {
				// export sales tax records?
				// export shipping etc. records
			}
			return $reqs;
		}
		else if($phase != 'Items')
			return $reqs;
		
		if(QBConfig::get_server_setting($server_id, 'Import', 'Products')) {
			if($stage == 'import') {
				$cats = array(
					'ItemInventory',
					'ItemNonInventory',
					'ItemGroup',
					'ItemSubtotal',
					'ItemOtherCharge',
					'ItemService',
					'ItemFixedAsset',
					'ItemInventoryAssembly',
					'ItemPayment',
					//'ItemReceipt',
				);
				foreach($cats as $cat)
					$reqs[] = array(
						'type' => 'import',
						'base' => $cat,
						'optimize' => 'auto',
					);
				$reqs[] = array(
					'type' => 'import',
					'base' => 'Item',
					'optimize' => 'auto',
					'params' => array(
						'ActiveStatus' => 'InactiveOnly',
					),
				);
			}
			else if($stage == 'ext_import') {
				foreach($this->import_from as $qbt)
					$this->add_import_requests($server_id, $reqs, qb_batch_size($server_id, 'Products', 'import'), false, $qbt);
			}
		}
		
		if(QBConfig::get_server_setting($server_id, 'Export', 'Products')) {
			if($stage == 'export') {
				$this->register_pending_exports($server_id, 500);
				$this->qb_re_register_blocked($server_id);
				$this->add_export_requests($server_id, $reqs, qb_batch_size($server_id, 'Products', 'export'));
			}
			else if($stage == 'reg_update') {
				$this->register_pending_updates($server_id);
			}
			else if($stage == 'pre_update') {
				// we always grab object details if available
				// these are especially needed if import is disabled
				foreach($this->import_from as $qbt)
					$this->add_import_requests($server_id, $reqs, qb_batch_size($server_id, 'Products', 'export'), true, $qbt);
			}
			else if($stage == 'update') {
				$this->add_export_requests($server_id, $reqs, qb_batch_size($server_id, 'Products', 'export'), true);
			}
			else if($stage == 'delete') {
				$this->add_delete_requests($server_id, $reqs, qb_batch_size($server_id, 'Products', 'export'));
			}
		}
		return $reqs;
	}
	
	
	function register_import_without_id(&$bean) {
		$this->pre_import_by_name[$bean->system_type][$bean->id] = $bean->shortname;
	}
	
	function get_extra_import_requests(&$reqs) {
		if(! isset($this->pre_import_by_name) || ! is_array($this->pre_import_by_name)) {
			return;
		}
		$names = array();
		foreach($this->pre_import_by_name as $cat => $ns) {
			foreach(array_values($ns) as $n) {
				// avoid requesting the same item over and over
				if(isset($_SESSION['item_import_byname'][$n]))
					continue;
				$_SESSION['item_import_byname'][$n] = 1;
				$names[] = $n;
			}
		}
		unset($this->pre_import_by_name);
		if($names) {
			$import_req = array(
				'type' => 'import',
				'base' => 'Item',
				'params' => array(
					'FullName' => $names,
				),
			);
			$reqs[] = $import_req;
		}
	}
	
	
	function handle_import_response($server_id, &$req, &$data, &$newreqs) {
		$ret = parent::handle_import_response($server_id, $req, $data, $newreqs);
		if($ret && $req->sync_step == 'GetConfigItems')
			QBConfig::update_setup_status($req->sync_step, 'ok', $server_id);
		return $ret;
	}
	
	
	function perform_sync($mode, $qb_type, &$details, &$errmsg, &$newreqs) {
		$update = ! empty($this->id) && ! empty($this->system_id);
		$override = $this->sync_status == 'pending_update';
		$this->qb_type = $qb_type;
		$this->shortname = $details['Name'];
		$this->_sync_date = null;

		// determines method of updating (seems we can't switch between SalesOrPurchase and SalesAndPurchase)
		$this->not_reimbursable = isset($details['SalesOrPurchase']) ? 1 : 0;

		if($mode == 'post_iah_update')
			return true; // nothing to do
		
		if($update && $override) {
			// do nothing
		}
		else if($this->qb_type == 'ItemInventory') {
			$this->system_type = 'ProductCatalog';
			$this->system_id = $this->import_product($details, $update);
		}
		else if($this->qb_type == 'ItemNonInventory') {
			$this->system_type = 'ProductCatalog';
			$this->system_id = $this->import_product($details, $update);
		}
		else if($this->qb_type == 'ItemDiscount') {
			$this->system_type = 'Discounts';
			$this->system_id = $this->import_discount($details, $update);
		}
		else if($this->qb_type == 'ItemSalesTax') {
			$this->system_type = 'TaxRates';
			$this->system_id = $this->import_taxrate($details, $update);
		}
		else if($this->qb_type == 'ItemGroup') {
			$this->system_type = 'Assemblies';
			$this->system_id = $this->import_assembly($details, $update);
		}
		else if($this->qb_type == 'ItemSubtotal') {
			if($this->shortname == self::get_custom_item_name($this->server_id, 'standard_subtotal_item_partno'))
				self::set_standard_subtotal_item_id($this->server_id, $this->qb_id);
			else
				$skip = true;
		}
		else if($this->qb_type == 'ItemOtherCharge') {
			$charge_name = $this->get_description($details);
			$this->name = $charge_name;
			// note: this is only triggered on export, not update - could be more robust
			if($this->shortname == self::get_custom_item_name($this->server_id, 'standard_shipping_item_partno'))
				self::set_standard_shipping_item_id($this->server_id, $this->qb_id);
			else if($this->shortname == self::get_custom_item_name($this->server_id, 'standard_booked_hours_partno'))
				self::set_booking_line_item_id($this->server_id, $this->qb_id);
			else
				$skip = true;
		}
		else
			$skip = true;
		
		if(isset($skip)) {
			if(! $update) {
				$nm = $this->get_description($details);
				$this->name = $nm;
				$this->sync_status = 'reg_only';
				$this->system_id = true;
			}
		}
		
		if($this->system_id === true) // placeholder products
			$this->system_id = '';
		else {

			if (empty($this->_sync_date)) {
				$this->_sync_date = qb_date_last_sync();
			}
			$this->date_last_sync = $this->_sync_date;
			if ($this->first_sync) $this->sync_status = '';
		}
			
		return true;
	}
	
	
	function get_description(&$details, $type = 'sales') {
		$salesDesc = array_get_default($details, 'Desc', $details['Name']);
		$purchaseDesc = '';

		if(! empty($details['SalesDesc']))
			$salesDesc = $details['SalesDesc'];
		else if(! empty($details['SalesOrPurchase']) && ! empty($details['SalesOrPurchase']['Desc']))
			$salesDesc = $details['SalesOrPurchase']['Desc'];
		else if(! empty($details['SalesAndPurchase']) && ! empty($details['SalesAndPurchase']['SalesDesc']))
			$salesDesc = $details['SalesAndPurchase']['SalesDesc'];

		if(! empty($details['PurchaseDesc']))
			$purchaseDesc = $details['PurchaseDesc'];
		else if(! empty($details['SalesOrPurchase']) && ! empty($details['SalesOrPurchase']['Desc']))
			$purchaseDesc = $details['SalesOrPurchase']['Desc'];
		else if(! empty($details['SalesAndPurchase']) && ! empty($details['SalesAndPurchase']['PurchaseDesc']))
			$purchaseDesc = $details['SalesAndPurchase']['PurchaseDesc'];

		$desc = ($type == 'sales') ? $salesDesc : $purchaseDesc;
		if (empty($desc) && $type != 'sales') {
			$desc = $salesDesc;
		}
		$desc = trim($desc);
		return $desc;
	}
	
	
	function import_product(&$details, $update=false) {
		$part_no = $details['Name'];
		$product_name = $this->get_description($details);
		$purchase_name = $this->get_description($details, 'purchase');
		$this->shortname = $part_no;
		$this->name = $product_name;
		
		if($part_no == self::get_custom_item_name($this->server_id, 'custom_product_partno')) {
			self::set_custom_line_item_id($this->server_id, $this->qb_id);
			return true;
		}
		else if($part_no == self::get_custom_item_name($this->server_id, 'standard_booked_hours_partno')) {
			self::set_booking_line_item_id($this->server_id, $this->qb_id);
			return true;
		}
		
		$focus = new Product();
		if($update) {
			if(! $focus->retrieve($this->system_id))
				return false;
		}
		else {
			$query = "SELECT id FROM products WHERE manufacturers_part_no='".$this->db->quote($part_no)."' AND NOT deleted";
			$result = $this->db->query($query, true);
			if($row = $this->db->fetchByAssoc($result)) {
				$focus->retrieve($row['id']);
				// TODO: update product record or QB side?
				// note we haven't linked these two previously
				$this->name = $focus->name;
				// check for another row with this product ID
				$existing = self::lookup_item('ProductCatalog', $focus->id, $this->server_id);
				if($existing) {
					$existing->qb_id = $this->qb_id;
					$existing->qb_type = $this->qb_type;
					$existing->qb_editseq = $this->qb_editseq;
					$existing->save();
					$this->prevent_save = true;
					return;
				}
				return $focus->id;
			}
			$focus->product_category_id = $this->get_import_category_id($this->server_id);
			$focus->product_type_id = $this->get_import_type_id($this->server_id);
			$focus->date_entered = qb_import_date_time($details['TimeCreated'], false);
			$focus->pricing_formula = 'Fixed Price';
		}
		
		$focus->name = $product_name;
		$focus->purchase_name = $purchase_name;
		$focus->manufacturers_part_no = $part_no;
		if(! isset($focus->is_available))
			$focus->is_available = 'yes';
		if($this->qb_type == 'ItemInventory') {
			$stock_qty = array_get_default($details, 'QuantityOnHand', '');
			$focus->in_stock = $stock_qty;
			if($this->get_warehouse_required())
				$focus->all_stock = (float)$stock_qty;
			$focus->track_inventory = 'semiauto';
			
			$focus->cost = array_get_default($details, 'PurchaseCost', 0.0);
			$focus->list_price = array_get_default($details, 'SalesPrice', 0.0);
			if(! isset($focus->purchase_price))
				// TODO: if updating, recalculate according to pricing formula
				$focus->purchase_price = $focus->list_price;
			
			if(isset($details['PrefVendorRef'])) {
				$qb_ent = new QBEntity();
				if($qb_ent->qb_retrieve($details['PrefVendorRef']['ListID'], $this->server_id)) {
					$focus->supplier_id = $qb_ent->account_id;
				}
			}
		}
		else if($this->qb_type == 'ItemNonInventory') {
			$focus->track_inventory = 'untracked';
			
			if(isset($details['SalesOrPurchase'])) {
				$sp =& $details['SalesOrPurchase'];
				if(isset($sp['Price'])) {
					$focus->cost = $sp['Price'];
					$focus->list_price = $focus->cost;
					$focus->purchase_price = $focus->cost;
				}
				else {
					$this->sync_status = 'import_error';
					$this->status_msg = 'Non-inventory item has no fixed price.';
					return '';
				}
			}
			else if(isset($details['SalesAndPurchase'])) {
				$sp =& $details['SalesAndPurchase'];
				$focus->cost = array_get_default($sp, 'PurchaseCost', 0.0);
				$focus->list_price = array_get_default($sp, 'SalesPrice', 0.0);
				$focus->purchase_price = $focus->list_price;
				if(isset($sp['PrefVendorRef'])) {
					$qb_ent = new QBEntity();
					if($qb_ent->qb_retrieve($sp['PrefVendorRef']['ListID'], $this->server_id)) {
						$focus->supplier_id = $qb_ent->account_id;
					}
				}
			}
		}

		$conv = QBCurrency::convert_qb_iah($this->server_id, $focus->cost);
		if(! $conv) {
			$this->sync_status = 'import_error';
			$this->status_msg = 'Home currency unknown';
			return '';
		}
		$_REQUEST['override_exchange_rate']['exchange_rate'] = true;
		$focus->currency_id = $conv['currency_id'];
		$focus->exchange_rate = $conv['exchange_rate'];
		
		$id = $focus->save();
		
		if($id && $this->qb_type == 'ItemInventory' && $this->get_warehouse_required()) {
			$wh_id = $this->get_inventory_warehouse_id($this->server_id);
			$focus->load_relationship('warehouses');
			$focus->warehouses->add($wh_id, array('in_stock' => (float)$stock_qty));
		}
		
		$this->_sync_date = $focus->date_modified;
		return $id;
	}
	
	
	function import_assembly(&$details) {
		$part_no = $details['Name'];
		$assembly_name = $this->name;
		if(! empty($details['ItemDesc']))
			$assembly_name = $details['ItemDesc'];
		$assembly_name = trim($assembly_name);
		$this->shortname = $part_no;
		$this->name = $assembly_name;
		
		if($part_no == self::get_custom_item_name($this->server_id, 'custom_assembly_partno')) {
			self::set_custom_assembly_item_id($this->server_id, $this->qb_id);
			return true;
		}
		
		$focus = new Assembly();
		$query = "SELECT id FROM assemblies WHERE manufacturers_part_no='".$this->db->quote($part_no)."' AND NOT deleted";
		$result = $this->db->query($query, true);
		if($row = $this->db->fetchByAssoc($result)) {
			$focus->retrieve($row['id']);
			// TODO: update assembly record or QB side?
			// note we haven't linked these two previously
			$this->name = $focus->name;
			return $focus->id;
		}
		
		if($update) {
			static $srv;
			if(! isset($srv)) {
				$srv = new QBServer();
				$srv->retrieve_primary();
			}
			$old_name = qb_export_name($srv, $focus->name);
			if($old_name != $assembly_name)
				$focus->name = $assembly_name;
		}
		else {
			$focus->name = $assembly_name;
			$focus->product_category_id = $this->get_import_category_id($this->server_id);
			$focus->product_type_id = $this->get_import_type_id($this->server_id);
			$focus->date_entered = qb_import_date_time($details['TimeCreated'], false);
		}
		$focus->manufacturers_part_no = $part_no;
		
		$parts = array();
		if(isset($details['ItemGroupLine'])) {
			foreach($details['ItemGroupLine'] as $line) {
				if (!is_array($line)) continue; // empty line
				$pid = $line['ItemRef']['ListID'];
				$prod = new QBItem();
				if(! $prod->qb_retrieve($pid, $this->server_id)) {
					$this->sync_status = 'import_error';
					$this->status_msg = 'Component product not imported';
					qb_log_error($this->status_msg);
					qb_log_error($line);
					return '';
				}
				$parts[$prod->system_id] = $line['Quantity'];
			}
		}
		
		$id = $focus->save();
		$this->_sync_date = $focus->date_modified;
		
		$focus->load_relationship('products');
		foreach($parts as $pid => $qty)
			$focus->products->add($pid, array('quantity' => $qty));
		
		return $id;
	}
	
	function import_discount(&$details) {		
		$focus = new Discount();
		$query = "SELECT id FROM {$focus->table_name} WHERE name='".$this->db->quote($this->name)."' AND NOT deleted";
		$result = $this->db->query($query, true);
		if($row = $this->db->fetchByAssoc($result)) {
			$focus->retrieve($row['id']);
			return $focus->id;
		}
		
		$part_no = $details['Name'];
		$description = $this->name;
		if(! empty($details['ItemDesc']))
			$description = $details['ItemDesc'];
		$description = trim($description);
		$this->shortname = $part_no;
		$this->name = $description;

		$focus->name = $part_no;
		$focus->description = $description;
		$focus->status = 'Active';
		
		if(isset($details['DiscountRate'])) {
			$focus->discount_type = 'fixed';
			$focus->fixed_amount = $details['DiscountRate'];
			$conv = QBCurrency::convert_qb_iah($this->server_id, $focus->fixed_amount);
			if(! $conv) {
				$this->sync_status = 'import_error';
				$this->status_msg = 'Home currency unknown';
				return '';
			}
			$_REQUEST['override_exchange_rate']['exchange_rate'] = true;
			$focus->currency_id = $conv['currency_id'];
			$focus->exchange_rate = $conv['exchange_rate'];
		}
		else {
			$focus->discount_type = 'percentage';
			$focus->rate = $details['DiscountRatePercent'];
			$this->assoc_rate = $focus->rate;
		}
		
		$id = $focus->save();
		$this->_sync_date = $focus->date_modified;
		return $id;
	}
	
	
	function import_taxrate(&$details) {
		$part_no = $details['Name'];
		$description = $this->name;
		if(! empty($details['ItemDesc']))
			$description = $details['ItemDesc'];
		$description = trim($description);
		$this->shortname = $part_no;
		$this->name = $description;
		
		$this->assoc_rate = (float)array_get_default($details, 'TaxRate', 0.0);
		
		require_once('modules/TaxRates/TaxRate.php');
		$focus = new TaxRate();
		$query = "SELECT id FROM {$focus->table_name} WHERE name='".$this->db->quote($this->name)."' ".
			" AND rate='".$this->assoc_rate."' AND NOT deleted";
		$result = $this->db->query($query, true);
		if($row = $this->db->fetchByAssoc($result)) {
			$focus->retrieve($row['id']);
			$this->_sync_date = $focus->date_modified;
			return $focus->id;
		}
		
		return '';
	}
	
	
	static function get_custom_line_item_id($server_id) {
		return QBConfig::get_server_setting($server_id, 'Items', 'custom_line_item_id', '');
	}
	static function set_custom_line_item_id($server_id, $val) {
		return QBConfig::save_server_setting($server_id, 'Items', 'custom_line_item_id', $val);
	}
	
	static function get_booking_line_item_id($server_id) {
		return QBConfig::get_server_setting($server_id, 'Items', 'booking_line_item_id', '');
	}
	
	static function set_booking_line_item_id($server_id, $val) {
		return QBConfig::save_server_setting($server_id, 'Items', 'booking_line_item_id', $val);
	}	

	static function get_custom_assembly_item_id($server_id) {
		return QBConfig::get_server_setting($server_id, 'Items', 'custom_assembly_item_id', '');
	}
	
	static function set_custom_assembly_item_id($server_id, $val) {
		return QBConfig::save_server_setting($server_id, 'Items', 'custom_assembly_item_id', $val);
	}
	
	static function get_standard_shipping_item_id($server_id) {
		return QBConfig::get_server_setting($server_id, 'Items', 'standard_shipping_item_id', '');
	}
	static function set_standard_shipping_item_id($server_id, $val) {
		return QBConfig::save_server_setting($server_id, 'Items', 'standard_shipping_item_id', $val);
	}
	
	static function get_standard_subtotal_item_id($server_id) {
		return QBConfig::get_server_setting($server_id, 'Items', 'standard_subtotal_item_id', '');
	}
	static function set_standard_subtotal_item_id($server_id, $val) {
		return QBConfig::save_server_setting($server_id, 'Items', 'standard_subtotal_item_id', $val);
	}
	
	static function check_standard_items_synced($server_id) {
		if(! self::get_custom_line_item_id($server_id))
			return false;
		if(! self::get_custom_assembly_item_id($server_id))
			return false;
		if(! self::get_standard_subtotal_item_id($server_id))
			return false;
		if(! self::get_standard_shipping_item_id($server_id))
			return false;
		return true;
	}
	
	
	function fill_in_additional_list_fields() {
		if($this->system_type == 'ProductCatalog')
			$tbl = 'products';
		else if($this->system_type == 'Assemblies')
			$tbl = 'assemblies';
		else if($this->system_type == 'Discounts')
			$tbl = 'discounts';
		else if($this->system_type == 'TaxRates')
			$tbl = 'taxrates';
		if(isset($tbl)) {
			$id = $this->db->quote($this->system_id);
			$query = "SELECT $tbl.name AS system_name FROM $tbl WHERE $tbl.id='$id' AND NOT $tbl.deleted LIMIT 1";
			$result = $this->db->query($query, true, "Error retrieving related object names");
			if($row = $this->db->fetchByAssoc($result))
				foreach($row as $k=>$v) $this->$k = $v;
		}
	}
	
	
	function get_list_view_data() {
		$row_data = parent::get_list_view_data();
		
		$row_data['SYSTEM_LINK'] = $this->get_linked_icon('system', $this->system_type);
		if(strlen(from_html($this->shortname)) > MAX_ITEM_ID_LENGTH)
			$row_data['SHORTNAME'] = '<span class="error">'.$this->shortname.'</span>';
		
		return $row_data;
	}
	
	
	function register_export_products(&$added, $server_id, $max_register=-1) {
		$sid = $this->db->quote($server_id);
		$query = "SELECT prod.id, prod.name, prod.manufacturers_part_no, prod.track_inventory FROM products prod ".
				"LEFT JOIN {$this->table_name} items ".
					"ON (items.server_id='$sid' AND NOT items.deleted ".
					"AND items.system_type='ProductCatalog' AND items.system_id=prod.id) ".
				"WHERE items.id IS NULL AND NOT prod.deleted ".
				"ORDER BY prod.name";
		if($max_register > 0) $query .= " LIMIT $max_register";
		$result = $this->db->query($query, true, "Error retrieving Product IDs for export");
		while($row = $this->db->fetchByAssoc($result)) {
			if($max_register >= 0 && count($added) >= $max_register)
				break;
			$seed = new QBItem();
			$seed->system_type = 'ProductCatalog';
			$seed->system_id = $row['id'];
			$seed->shortname = $row['manufacturers_part_no'];
			$seed->name = $row['name'];
			if(empty($row['track_inventory']) || $row['track_inventory'] == 'untracked')
				$seed->qb_type = 'ItemNonInventory';
			else
				$seed->qb_type = 'ItemInventory';
			$seed->sync_status = 'pending_export';
			$seed->status_msg = '';
			$seed->server_id = $server_id;
			$seed->save();
			$added[] = $seed->id;
		}
		$cstm_prod_id = self::get_custom_line_item_id($server_id);
		if(empty($cstm_prod_id)) {
			$partno = self::get_custom_item_name($server_id, 'custom_product_partno');
			$q = "SELECT id FROM {$this->table_name} items ".
				"WHERE items.system_type='ProductCatalog' AND items.shortname='$partno' ".
				"AND items.server_id='$sid' AND NOT deleted";
			$r = $this->db->query($q, true, "Error looking up custom product item");
			if( ($row = $this->db->fetchByAssoc($r)) ) {
				self::set_custom_line_item_id($server_id, $row['qb_id']);
			}
			else {
				$seed = new QBItem();
				$seed->system_type = 'ProductCatalog';
				$seed->system_id = '';
				$seed->shortname = $partno;
				$seed->name = self::get_custom_item_name($server_id, 'custom_product_name');
				$seed->qb_type = 'ItemNonInventory';
				$seed->sync_status = 'pending_export';
				$seed->status_msg = '';
				$seed->server_id = $server_id;
				$seed->save();
			}
		}
		$bkd_hours_id = self::get_booking_line_item_id($server_id);
		if(empty($bkd_hours_id)) {
			$partno = self::get_custom_item_name($server_id, 'standard_booked_hours_partno');
			$q = "SELECT id FROM {$this->table_name} items ".
				"WHERE items.system_type='Booking' AND items.shortname='$partno' ".
				"AND items.server_id='$sid' AND NOT deleted";
			$r = $this->db->query($q, true, "Error looking up booked hours item");
			if( ($row = $this->db->fetchByAssoc($r)) ) {
				self::set_booking_line_item_id($server_id, $row['qb_id']);
			}
			else {
				$seed = new QBItem();
				$seed->system_type = 'Booking';
				$seed->system_id = '';
				$seed->shortname = $partno;
				$seed->name = self::get_custom_item_name($server_id, 'standard_booked_hours_name');
				$seed->qb_type = 'ItemOtherCharge';
				$seed->sync_status = 'pending_export';
				$seed->status_msg = '';
				$seed->server_id = $server_id;
				$seed->save();
			}
		}
		return true;
	}
	
	
	function register_export_assemblies(&$added, $server_id, $max_register=-1) {
		$sid = $this->db->quote($server_id);
		$query = "SELECT asm.id, asm.name, asm.manufacturers_part_no FROM assemblies asm ".
				"LEFT JOIN {$this->table_name} items ".
					"ON (items.server_id='$sid' AND NOT items.deleted ".
					"AND items.system_type='Assemblies' AND items.system_id=asm.id) ".
				"WHERE items.id IS NULL AND NOT asm.deleted ".
				"ORDER BY asm.name";
		if($max_register > 0) $query .= " LIMIT $max_register";
		$result = $this->db->query($query, true, "Error retrieving Assembly IDs for export");
		while($row = $this->db->fetchByAssoc($result)) {
			if($max_register >= 0 && count($added) >= $max_register)
				break;
			$seed = new QBItem();
			$seed->system_type = 'Assemblies';
			$seed->system_id = $row['id'];
			$seed->shortname = $row['manufacturers_part_no'];
			$seed->name = $row['name'];
			$seed->qb_type = 'ItemGroup';
			$seed->sync_status = 'pending_export';
			$seed->status_msg = '';
			$seed->server_id = $server_id;
			$seed->save();
			$added[] = $seed->id;
		}
		$cstm_asm_id = self::get_custom_assembly_item_id($server_id);
		if(empty($cstm_asm_id)) {
			$partno = self::get_custom_item_name($server_id, 'custom_assembly_partno');
			$q = "SELECT id,qb_id FROM {$this->table_name} items ".
				"WHERE items.system_type='Assemblies' AND items.shortname='$partno' ".
				"AND items.server_id='$sid' AND NOT deleted";
			$r = $this->db->query($q, true, "Error looking up custom assembly item");
			if( ($row = $this->db->fetchByAssoc($r)) ) {
				self::set_custom_assembly_item_id($server_id, $row['qb_id']);
			}
			else {
				$seed = new QBItem();
				$seed->system_type = 'Assemblies';
				$seed->system_id = '';
				$seed->shortname = $partno;
				$seed->name = self::get_custom_item_name($server_id, 'custom_assembly_name');
				$seed->qb_type = 'ItemGroup';
				$seed->sync_status = 'pending_export';
				$seed->status_msg = '';
				$seed->server_id = $server_id;
				$seed->save();
			}
		}
		return true;
	}
	
	
	function register_export_discounts(&$added, $server_id, $max_register=-1) {
		$sid = $this->db->quote($server_id);
		$query = "SELECT ds.id, ds.name FROM discounts ds ".
				"LEFT JOIN {$this->table_name} items ".
					"ON (items.server_id='$sid' AND NOT items.deleted ".
					"AND items.system_type='Discounts' AND items.system_id=ds.id) ".
				"WHERE items.id IS NULL AND NOT ds.deleted ".
				"ORDER BY ds.name";
		if($max_register > 0) $query .= " LIMIT $max_register";
		$result = $this->db->query($query, true, "Error retrieving Discount IDs for export");
		while($row = $this->db->fetchByAssoc($result)) {
			if($max_register >= 0 && count($added) >= $max_register)
				break;
			$seed = new QBItem();
			$seed->system_type = 'Discounts';
			$seed->system_id = $row['id'];
			$seed->shortname = $row['name'];
			$seed->name = $row['name'];
			$seed->qb_type = 'ItemDiscount';
			$seed->sync_status = 'pending_export';
			$seed->status_msg = '';
			$seed->server_id = $server_id;
			$seed->save();
			$added[] = $seed->id;
		}
		return true;
	}
	
	
	function check_standard_items(&$added, $server_id, $max_register=-1) {
		$subtot_id = self::get_standard_subtotal_item_id($server_id);
		$qbitem = new QBItem();
		$sid = $this->db->quote($server_id);
		if($subtot_id && ! $qbitem->qb_retrieve($subtot_id, $server_id))
			$subtot_id = self::set_standard_subtotal_item_id($server_id, '');
		if(empty($subtot_id)) {
			$partno = self::get_custom_item_name($server_id, 'standard_subtotal_item_partno');
			$q = "SELECT id,qb_id FROM {$this->table_name} items ".
				"WHERE items.qb_type='ItemSubtotal' AND items.shortname='$partno' ".
				"AND items.server_id='$sid' AND NOT deleted";
			$r = $this->db->query($q, true, "Error looking up standard subtotal item");
			if( ($row = $this->db->fetchByAssoc($r)) ) {
				self::set_standard_subtotal_item_id($server_id, $row['qb_id']);
			}
			else if(empty($_SESSION['std_subtotal_export'])) {
				$seed = new QBItem();
				$seed->shortname = $partno;
				$seed->name = self::get_custom_item_name($server_id, 'standard_subtotal_item_name');
				$seed->qb_type = 'ItemSubtotal';
				$seed->sync_status = 'pending_export';
				$seed->status_msg = '';
				$seed->server_id = $server_id;
				$seed->save();
				$_SESSION['std_subtotal_export'] = 1; // prevent odd double export
			}
		}
		$ship_id = self::get_standard_shipping_item_id($server_id);
		$qbitem = new QBItem();
		if($ship_id && ! $qbitem->qb_retrieve($ship_id, $server_id))
			$ship_id = self::get_standard_shipping_item_id($server_id, '');
		if(empty($ship_id)) {
			$partno = self::get_custom_item_name($server_id, 'standard_shipping_item_partno');
			$q = "SELECT id,qb_id FROM {$this->table_name} items ".
				"WHERE items.qb_type IN ('ItemOtherCharge', 'ItemNonInventory') AND items.shortname='$partno' ".
				"AND items.server_id='$sid' AND NOT deleted";
			$r = $this->db->query($q, true, "Error looking up standard shipping item");
			if( ($row = $this->db->fetchByAssoc($r)) ) {
				self::set_standard_shipping_item_id($server_id, $row['qb_id']);
			}
			else if(empty($_SESSION['std_shipping_export'])) {
				$seed = new QBItem();
				$seed->shortname = $partno;
				$seed->name = self::get_custom_item_name($server_id, 'standard_shipping_item_name');
				$seed->qb_type = 'ItemOtherCharge';
				$seed->sync_status = 'pending_export';
				$seed->status_msg = '';
				$seed->server_id = $server_id;
				$seed->save();
				$_SESSION['std_shipping_export'] = 1; // prevent odd double export
			}
		}

		return true;
	}
	
	
	function &register_pending_exports($server_id, $max_register=-1) {
		$added = array();
		$fns = array('register_export_products',
			'register_export_discounts',
			'register_export_assemblies',
			'check_standard_items');
		$remain = $max_register;
		if(QBConfig::get_server_setting($server_id, 'Export', 'Products')) {
			foreach($fns as $method) {
				if(! $remain)
					break;
				$this->$method($added, $server_id, $remain);
				if($remain >= 0) $remain = max(0, $max_register - count($added));
			}
		}
		return $added;
	}
	
	
	function register_pending_updates($server_id) {
		$cats = array('ProductCatalog', 'Assemblies', 'Discounts');
		$ok = true;
		foreach($cats as $category) {
			$ok &= $this->register_pending_updates2($server_id, $category);
		}
		return $ok;
	}
	
	
	function register_pending_updates2($server_id, $category) {
		$rel_date = 'date_last_sync';
		$sys_type = $category;
		if($category == 'ProductCatalog') {
			$tbl = 'products';
		}
		else if($category == 'Assemblies') {
			$tbl = 'assemblies';
		}
		else if($category == 'Discounts') {
			$tbl = 'discounts';
		}
		else
			return false;
		
		$sid = $this->db->quote($server_id);
		$query = "UPDATE `{$this->table_name}` me ".
				"LEFT JOIN `$tbl` rel ON rel.id=me.system_id ".
				"SET me.sync_status='pending_update' ".
				"WHERE me.server_id='$sid' ".
				"AND me.first_sync IN ('imported','exported') ".
				"AND me.system_type='$sys_type' ".
				"AND (me.sync_status='' OR me.sync_status IS NULL) ".
				"AND me.$rel_date IS NOT NULL ".
				"AND me.$rel_date < rel.date_modified ".
				"AND rel.id IS NOT NULL AND NOT me.deleted";
		//qb_log_info($query);
		$result = $this->db->query($query, false);
		if(! $result) {
			qb_log_error("Error marking items for update");
			return false;
		}
		return true;
	}


	function &get_export_request(&$req, &$errmsg, $update=false) {
		$this->_sync_date = null;
		$req = array(
			'type' => $update ? 'update' : 'export',
			'params' => array(),
		);
		if($this->system_type == 'ProductCatalog') {
			$ret = $this->get_product_export_request($req, $errmsg, $update);
		}
		else if($this->system_type == 'Assemblies') {
			$ret = $this->get_assembly_export_request($req, $errmsg, $update);
		}
		else if($this->system_type == 'Discounts') {
			$ret = $this->get_discount_export_request($req, $errmsg, $update);
		}
		else if($this->qb_type == 'ItemSubtotal') {
			$ret = $this->get_subtotal_export_request($req, $errmsg, $update);
		}
		else if($this->qb_type == 'ItemOtherCharge') {
			$ret = $this->get_other_export_request($req, $errmsg, $update);
		}

		if($ret) {
			if (empty($this->_sync_date)) {
				$this->_sync_date = qb_date_last_sync();
			}
			$this->date_last_sync = $this->_sync_date;
			if($update) {
				$k = key($req['params']);
				$req['params'][$k] = array(
					'ListID' => $this->qb_id,
					'EditSequence' => $this->qb_editseq,
				) + $req['params'][$k];
			}
			//qb_log_debug($req);
		}
		return $ret;
	}
	
	
	// call with properly corresponding $this and $bean
	function ok_for_export(&$bean, &$errmsg) {
		if(! $bean) {
			$errmsg = "Related object could not be found";
			return false;
		}
		if($bean->deleted) {
			$errmsg = "Related object is marked deleted";
			return false;
		}
		switch($bean->object_name) {
			case 'Product':
				if(empty($bean->track_inventory) || $bean->track_inventory == 'untracked')
					$inf_qb_type = 'ItemNonInventory';
				else
					$inf_qb_type = 'ItemInventory';
				if($inf_qb_type != $this->qb_type) {
					$errmsg = "Inventory tracking status has changed - please revert";
					return false;
				}
				// fall through
			case 'Assembly':
				if(! strlen($bean->manufacturers_part_no)) {
					$errmsg = $bean->object_name." is missing a manufacturer's part number";
					return false;
				}
				if(strlen($bean->manufacturers_part_no) > constant('MAX_ITEM_ID_LENGTH')) {
					$errmsg = "Manufacturer's part number is too long";
					return false;
				}
				$short = $bean->manufacturers_part_no;
				break;
			case 'Discount':
				if(strlen($bean->name) > constant('MAX_ITEM_ID_LENGTH')) {
					$errmsg = "Discount name is too long to export";
					return false;
				}
				$short = $bean->name;
				break;
			//case 'TaxRate':
		}
		$short = $this->db->quote($short);
		$sid = $this->db->quote($this->server_id);
		$oid = $this->db->quote($this->id);
		$q = "SELECT id FROM {$this->table_name} WHERE shortname='$short' "
			. "AND id != '$oid' "
			. "AND first_sync IN ('imported', 'exported') AND first_sync IS NOT NULL "
			. "AND ((sync_status NOT LIKE '%_error' AND sync_status NOT LIKE '%_blocked') "
				. "OR sync_status IS NULL) "
			. "AND server_id='$sid' AND NOT deleted";
		$r = $this->db->query($q, false);
		if($r && ($row = $this->db->fetchByAssoc($r))) {
			$errmsg = "Another part has the same manufacturer part number as this one";
			return false;
		}
		return true;
	}
	
	
	function get_product_export_request(&$ret, &$errmsg, $update=false) {
		$ret['base'] = 'ItemInventory';
		if($this->qb_type == 'ItemNonInventory')
			$ret['base'] = $this->qb_type;
		$sales_op = $update ? 'Mod' : '';
		
		$product = new Product();
		if(! $this->system_id || ! $product->retrieve($this->system_id, false)) {
			if($this->shortname == self::get_custom_item_name($this->server_id, 'custom_product_partno')) {
				$product->manufacturers_part_no = self::get_custom_item_name($this->server_id, 'custom_product_partno');
				$product->name = self::get_custom_item_name($this->server_id, 'custom_product_name');
				$ret['base'] = 'ItemNonInventory';
				$skip_export_check = true;
			}
			else {
				$errmsg = "Error retrieving related product";
				return false;
			}
		}

		$this->_sync_date = $product->date_modified;	
		// reset fields potentially replaced with QB data
		$this->shortname = $product->manufacturers_part_no;
		$this->name = $product->name;
		
		if(empty($skip_export_check) && ! $this->ok_for_export($product, $errmsg)) {
			$this->export_blocked = true;
			return false;
		}

		/*foreach($this->product_field_map as $qb_f => $f) {
			if(isset($acct->$f) && $acct->$f !== '') {
				$details[$qb_f] = $acct->$f;
			}
		}*/
		
		$edition = QBServer::get_server_edition($this->server_id);
		$tax_code_key = ($edition == 'US') ? 'SalesTaxCodeRef' : 'TaxCodeForSaleRef';
		$qb_tax_code = QBTaxCode::from_iah_tax_code($this->server_id, $product->tax_code_id);
		
		$qb_home = QBCurrency::get_qb_home_currency($this->server_id);
		if(! $qb_home) {
			$errmsg = "Error retrieving home currency";
			$this->retry_export_later = true;
			return false;
		}
		$iah_cur = QBCurrency::to_iah_currency($this->server_id, $qb_home->qb_id);
		if(! $iah_cur) {
			$errmsg = "Error converting home currency";
			$this->retry_export_later = true;
			return false;
		}
		if($iah_cur->id != $product->currency_id) {
			if($base_cur->id == '-99') {
				$sales_amt = $product->list_usdollar;
				$purch_amt = $product->cost_usdollar;
			}
			else {
				$base_cur = new Currency();
				$base_cur->retrieve($product->currency_id);
				if($product->exchange_rate)
					$base_cur->conversion_rate = $product->exchange_rate;
				$sales_amt = $iah_cur->convertFromDollar($base_cur->convertToDollar($product->list_price));
				$purch_amt = $iah_cur->convertFromDollar($base_cur->convertToDollar($product->cost));
			}
		}
		else {
			$sales_amt = $product->list_price;
			$purch_amt = $product->cost;
		}
		
		if(! $update)
			$accs = $this->get_item_accounts($this->server_id);
		
		if($this->get_warehouse_required()) {
			$product->calc_stock($warehouse_id);
		}
		
		if($ret['base'] == 'ItemInventory') {
			$details = array(
				'Name' => $product->manufacturers_part_no,
				$tax_code_key => null,
				'SalesDesc' => $product->name,
				'SalesPrice' => qb_format_price($sales_amt),
				'IncomeAccountRef' => null, // Uncategorized Income, for instance
				'PurchaseDesc' => empty($product->purchase_name) ? $product->name : $product->purchase_name,
				'PurchaseCost' => qb_format_price($purch_amt),
				//'PrefVendorRef' => array(), // supplier
				'COGSAccountRef' => null, // Cost of Goods Sold
				'AssetAccountRef' => null, // Inventory Asset
				'QuantityOnHand' => null,
			);
			if($qb_tax_code)
				$details[$tax_code_key] = $qb_tax_code->get_ref();
			if(! $update) {
				$map = array(
					'IncomeAccountRef' => 'income_account',
					'COGSAccountRef' => 'cost_goods_account',
					'AssetAccountRef' => 'asset_account',
				);
				foreach($map as $fld => $an) {
					if(empty($accs[$an]['qb_id'])) {
						$this->status_msg = "Inventory account not set up: $an";
						qb_log_debug($this->status_msg);
						$this->retry_export_later = true;
						return false;
					}
					$details[$fld] = array('ListID' => $accs[$an]['qb_id']);
				}
				$details['QuantityOnHand'] = sprintf('%0.2f', $product->in_stock);
			}
		}
		else {
			$details = array(
				'Name' => $product->manufacturers_part_no,
				$tax_code_key => null,
				'SalesAndPurchase'.$sales_op => null,
				'SalesOrPurchase'.$sales_op => null,
			);
			if($update && ! empty($this->not_reimbursable)) {
				$andor = 'SalesOrPurchase';
				$details[$andor.$sales_op] = array(
					'Desc' => $product->name,
					'Price' => qb_format_price($sales_amt),
					'AccountRef' => null, // Uncategorized Income, for instance
				);
			}
			else {
				$andor = 'SalesAndPurchase';
				$details[$andor.$sales_op] = array(
					'SalesDesc' => $product->name,
					'SalesPrice' => qb_format_price($sales_amt),
					'IncomeAccountRef' => null, // Uncategorized Income, for instance
					'PurchaseDesc' => empty($product->purchase_name) ? $product->name : $product->purchase_name,
					'PurchaseCost' => qb_format_price($purch_amt),
					'ExpenseAccountRef' => null, // Miscellaneous
					//'PrefVendorRef' => array(), // supplier
				);
				if(! $update) {
					$map = array(
						'IncomeAccountRef' => 'income_account',
						'ExpenseAccountRef' => 'expense_account',
					);
					foreach($map as $fld => $an) {
						if(empty($accs[$an]['qb_id'])) {
							$this->status_msg = "Non-Inventory account not set up: $an";
							qb_log_debug($this->status_msg);
							$this->retry_export_later = true;
							return false;
						}
						$details[$andor.$sales_op][$fld] = array('ListID' => $accs[$an]['qb_id']);
					}
				}
			}
			if($qb_tax_code)
				$details[$tax_code_key] = $qb_tax_code->get_ref();
		}
		
		$op = $update ? 'Mod' : 'Add';
		$ret['params'][$ret['base'].$op] =& $details;
		return true;
	}
	
	
	function get_assembly_export_request(&$ret, &$errmsg, $update=false) {
		$ret['base'] = 'ItemGroup';
		
		$assembly = new Assembly();
		if(! $this->system_id || ! $assembly->retrieve($this->system_id, false)) {
			if($this->shortname == self::get_custom_item_name($this->server_id, 'custom_assembly_partno')) {
				$assembly->manufacturers_part_no = self::get_custom_item_name($this->server_id, 'custom_assembly_partno');
				$assembly->name = self::get_custom_item_name($this->server_id, 'custom_assembly_name');
			}
			else {
				$errmsg = "Error retrieving related assembly";
				return false;
			}
		}
		
		if(! $this->ok_for_export($assembly, $errmsg)) {
			$this->export_blocked = true;
			return false;
		}
		
		$details = array(
			'Name' => $assembly->manufacturers_part_no,
			'ItemDesc' => $assembly->name,
			'IsPrintItemsInGroup' => 'true',
		);
		
		$sid = $this->db->quote($this->server_id);
		$c = 0;
		$parts = $assembly->get_products_list($assembly->id);
		for($i = 0; $i < count($parts); $i++) {
			$pid = $parts[$i]['id'];
			$q = "SELECT id,qb_id,qb_is_active FROM {$this->table_name} items ".
				"WHERE system_type='ProductCatalog' ".
				"AND system_id='$pid' AND server_id='$sid' AND NOT deleted";
			$r = $this->db->query($q, true, "Error retrieving assembly component");
			$row = $this->db->fetchByAssoc($r);
			if(! $row || empty($row['qb_id']) || empty($row['qb_is_active'])) {
				$errmsg = "One or more components not exported or marked inactive";
				$this->retry_export_later = true;
				return false;
			}
			$item = new QBItem();
			if(! $item->retrieve($row['id'], false)) {
				$errmsg = "Error retrieving component sync info";
				return false;
			}
			$details['ItemGroupLine___' . $c++] = array(
				'ItemRef' => $item->get_ref(),
				'Quantity' => $parts[$i]['quantity'],
			);
		}
		
		$op = $update ? 'Mod' : 'Add';
		$ret['params']['ItemGroup'.$op] =& $details;
		return true;
	}
	
	
	function get_discount_export_request(&$ret, &$errmsg, $update=false) {
		$ret['base'] = 'ItemDiscount';
		
		$discount = new Discount();
		$discount->retrieve($this->system_id, false);
		if(! $discount->id || $discount->id == '-99') {
			$errmsg = "Error retrieving related discount";
			return false;
		}		

		$edition = QBServer::get_server_edition($this->server_id);
		$tax_code_key = ($edition == 'US') ? 'SalesTaxCodeRef' : 'TaxCodeRef';
		$qb_tax_code = QBTaxCode::standard_qb_tax_code($this->server_id);
		if(! $qb_tax_code) {
			$errmsg = "No standard tax code";
			$this->retry_export_later = true;
			return false;
		}
		
		if(! $this->ok_for_export($discount, $errmsg)) {
			$this->export_blocked = true;
			return false;
		}
		
		$details = array(
			'Name' => $discount->name,
			'ItemDesc' => $discount->name,
			$tax_code_key => $qb_tax_code->get_ref(),
		);
				
		if($discount->discount_type == 'fixed') {
			$qb_home = QBCurrency::get_qb_home_currency($this->server_id);
			if(! $qb_home) {
				$errmsg = "Error retrieving home currency";
				return false;
			}
			$iah_cur = QBCurrency::to_iah_currency($this->server_id, $qb_home->qb_id);
			if(! $iah_cur) {
				$errmsg = "Error retrieving home currency";
				return false;
			}
			if($iah_cur->id != $discount->currency_id) {
				if($base_cur->id == '-99') {
					$disc_amt = $discount->fixed_amount_usdollar;
				}
				else {
					$base_cur = new Currency();
					$base_cur->retrieve($discount->currency_id);
					if($discount->exchange_rate)
						$base_cur->conversion_rate = $discount->exchange_rate;
					$disc_amt = $iah_cur->convertFromDollar($base_cur->convertToDollar($discount->fixed_amount));
				}
			}
			else {
				$disc_amt = $discount->fixed_amount;
			}
			$details['DiscountRate'] = qb_format_price($disc_amt);
		}
		else {
			$details['DiscountRatePercent'] = sprintf('%0.2f', $discount->rate);
		}
		if(! $update) {
			$accs = $this->get_item_accounts($this->server_id);
			if(empty($accs['discount_account']['qb_id'])) {
				$this->status_msg = "Discount account not set";
				qb_log_debug($this->status_msg);
				$this->retry_export_later = true;
				return false;
			}
			$acc_id = $accs['discount_account']['qb_id'];
			$details['AccountRef'] = array('ListID' => $acc_id);
		}
		$op = $update ? 'Mod' : 'Add';
		$ret['params']['ItemDiscount'.$op] =& $details;
		return true;
	}
	
	
	function get_subtotal_export_request(&$ret, &$errmsg, $update=false) {
		$ret['base'] = 'ItemSubtotal';
				
		$details = array(
			'Name' => $this->shortname,
			'ItemDesc' => $this->name,
		);
		
		$op = $update ? 'Mod' : 'Add';
		$ret['params']['ItemSubtotal'.$op] =& $details;
		return true;
	}
	
	
	function get_other_export_request(&$ret, &$errmsg, $update=false) {
		$ret['base'] = 'ItemOtherCharge';
		$sales_or_purchase = 'SalesOrPurchase' . ($update ? 'Mod' : '');
		
		$details = array(
			'Name' => $this->shortname,
			$sales_or_purchase => array(
				'Desc' => $this->name,
				'Price' => (float)$this->assoc_rate,
			),
		);
		
		if($this->system_type == 'Booking') {
			$details[$sales_or_purchase]['Desc'] = self::get_custom_item_name($this->server_id, 'standard_booked_hours_name');
			if(! $update) {
				$accs = $this->get_item_accounts($this->server_id);
				if(empty($accs['income_account']['qb_id'])) {
					$this->status_msg = "Income account not set";
					qb_log_debug($this->status_msg);
					$this->retry_export_later = true;
					return false;
				}
				$acc_id = $accs['income_account']['qb_id'];
				$details[$sales_or_purchase]['AccountRef'] = array('ListID' => $acc_id);
			}
		}
		else if($this->shortname == self::get_custom_item_name($this->server_id, 'standard_shipping_item_partno')) {
			$details[$sales_or_purchase]['Desc'] = self::get_custom_item_name($this->server_id, 'standard_shipping_item_name');
			if(! $update) {
				$accs = $this->get_item_accounts($this->server_id);
				if(empty($accs['shipping_account']['qb_id'])) {
					$this->status_msg = "Shipping account not set";
					qb_log_debug($this->status_msg);
					$this->retry_export_later = true;
					return false;
				}
				$acc_id = $accs['shipping_account']['qb_id'];
				$details[$sales_or_purchase]['AccountRef'] = array('ListID' => $acc_id);
			}
		}
		
		$op = $update ? 'Mod' : 'Add';
		$ret['params']['ItemOtherCharge'.$op] =& $details;
		return true;
	}
	
	// -- Setup
	
	function get_item_accounts($server_id, $add_opts=false) {
		global $mod_strings;
		$accs = $this->item_accounts;
		foreach($accs as $an => $acc) {
			$accs[$an]['label'] = $mod_strings[strtoupper('LBL_ITEM_'.$an)];
			$accs[$an]['qb_id'] = QBConfig::get_server_setting($server_id, 'Items', $an, '');
			if($add_opts) {
				$opts = QBAccount::get_accounts_by_type($server_id, $acc['type']);
				if($opts) foreach($opts as $k=>$o)
					$opts[$k]->id = $k; // use qb_id for option list
				$accs[$an]['options'] = $opts;
			}
		}
		return $accs;
	}
	
	function get_import_category_id($server_id) {
		return QBConfig::get_server_setting($server_id, 'Items', 'import_category', '');
	}

	function get_import_type_id($server_id) {
		return QBConfig::get_server_setting($server_id, 'Items', 'import_type', '');
	}

	// rudiment
	function get_warehouse_required() {
		return true;
	}
	
	function get_inventory_warehouse_id($server_id) {
		return QBConfig::get_server_setting($server_id, 'Items', 'inventory_warehouse_id', '');
	}
	
	function setup_template_step(&$cfg, &$tpl, $step) {
		if($step != 'ItemAccounts')
			return false;
		$server_id = QBServer::get_primary_server_id();
		if(! $server_id) {
			$tpl->assign('NO_SERVER', '1');
			return 'no_server';
		}
		global $mod_strings;
		$html = '';
		$source = $this->get_item_accounts($server_id, true);
		foreach($source as $an => $acc) {
			$left_opt = array($an => $acc['label']);
			$map = array($an => $acc['qb_id']);
			//if($c) $html .= '<hr>';
			$html .= qb_match_up_html('account', $left_opt, $acc['options'], $map);
		}
		
		$html .= '<hr>';

		foreach (self::$custom_item_names as $name => $default) {
			if (substr($name, -5, 5) == '_name') continue;
			$value = self::get_custom_item_name($server_id, $name);
			$html .= '<table border="0" cellpadding="0" cellspacing="2" width="100%"><tr>';
			$html .= '<td class="tabDetailViewDL" width="40%">' . $mod_strings['LBL_' . strtoupper($name)]  . '</td>';
			$html .= '<td class="tabDetailViewDF" width="60%">';
			$html .= '<input name="items_map[' . $name . ']" value="' . $value . '">';
		   	$html .= '</td>';
			$html .= '</tr><table border="0" cellpadding="0" cellspacing="2">';
		}
		
		$html .= '<hr>';
		$etc = '?s=' . AppConfig::version() . '&c=' . AppConfig::js_custom_version();
		$html .= '<script type="text/javascript" src="modules/ProductCatalog/products.js'.$etc.'"></script>';
		$sel_attrs = 'onchange="fill_product_types(this.value, \'default__type_id\');"';
		$cats = get_product_categories_list();
		$cats[''] = $mod_strings['LBL_DD_REQUIRED'];
		$left = array('category_id' => $mod_strings['LBL_ITEM_IMPORT_CATEGORY']);
		$current_cat = $this->get_import_category_id($server_id);
		$map = array('category_id' => $current_cat);
		$html .= qb_match_up_html('default', $left, $cats, $map, true, $sel_attrs);
		$productType = new ProductType;
		$types = $productType->get_for_category($current_cat, true);
		$left = array('type_id' => $mod_strings['LBL_ITEM_IMPORT_TYPE']);
		$current_type = QBConfig::get_server_setting($server_id, 'Items', 'import_type', '');
		$map = array('type_id' => $current_type);
		$html .= qb_match_up_html('default', $left, $types, $map);
		
		if($this->get_warehouse_required()) {
			$query = "SELECT id,name,main_warehouse FROM company_addresses WHERE is_warehouse AND deleted = 0 ORDER BY name";
			$res = $this->db->query($query, true);
			$addrs = array('' => $mod_strings['LBL_DD_REQUIRED']);
			while ($row = $this->db->fetchByAssoc($res)) {
				$nm = $row['name'];
				if($row['main_warehouse']) $nm .= ' **';
				$addrs[$row['id']] = $nm;
			}
			$left = array('warehouse_id' => $mod_strings['LBL_ITEM_WAREHOUSE']);
			$current_wh = QBConfig::get_server_setting($server_id, 'Items', 'inventory_warehouse_id', '');
			$map = array('warehouse_id' => $current_wh);
			$html .= '<hr>';
			$html .= qb_match_up_html('inventory', $left, $addrs, $map);
		}
		$tpl->assign('BODY', $html);
	}
	
	function update_setup_config(&$cfg, $step, &$errs) {
		if($step != 'ItemAccounts')
			return false;
		$map = array_get_default($_REQUEST, 'account_map');
		$server_id = QBServer::get_primary_server_id();
		$status = 'ok';
		$source = $this->item_accounts;
		$configKey = 'Items';
		foreach($source as $an => $acc) {
			$newval = array_get_default($map, $an, '');
			QBConfig::save_server_setting($server_id, $configKey, $an, $newval);
		}
		
		$map = array_get_default($_REQUEST, 'default_map', array());
		if(isset($map['category_id'])) {
			QBConfig::save_server_setting($server_id, 'Items', 'import_category', $map['category_id']);
			$type = array_get_default($map, 'type_id', '');
			QBConfig::save_server_setting($server_id, 'Items', 'import_type', $type);
		}
		
		$map = array_get_default($_REQUEST, 'inventory_map', array());
		if(isset($map['warehouse_id'])) {
			QBConfig::save_server_setting($server_id, 'Items', 'inventory_warehouse_id', $map['warehouse_id']);
		}

		
		$map = array_get_default($_REQUEST, 'items_map', array());
		$source = self::$custom_item_names;
		foreach($source as $name => $default) {
			$newval = array_get_default($map, $name, $default);
			if (!empty($newval)) {
				QBConfig::save_server_setting($server_id, 'Items', $name, $newval);
			}
		}


		return $status;
	}
	
	function &get_system_object() {
		$seed = null;
		global $beanList;
		if($this->system_type && $this->system_id && isset($beanList[$this->system_type])) {
			$bean_name = $beanList[$this->system_type];
			$seed = new $bean_name;
			if(! $seed->retrieve($this->system_id))
				$seed = null;
		}
		return $seed;
	}
	
	// fix for 1.3
	function repair_short_names() {
		$query = "SELECT id FROM `{$this->table_name}` WHERE NOT deleted ".
			"AND (shortname IS NULL OR shortname='') ";
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

	function repair_inactive_products()
	{
		$query = "SELECT i1.*, i2.id AS dup_id, i2.system_id AS product_id 
			FROM qb_items i1
			LEFT JOIN qb_items i2
			ON i1.shortname=i2.shortname and i1.id != i2.id AND i1.server_id=i2.server_id
			WHERE i1.system_type='ProductCatalog' and NOT i1.qb_is_active and not i1.deleted and not i2.deleted
				AND i2.system_type='ProductCatalog' and i2.sync_status='export_blocked'
			";
		$res = $this->db->query($query, false);
		if (!$res) return false;
		while ($row = $this->db->fetchByAssoc($res)) {
			$query = sprintf(
				"UPDATE qb_items SET sync_status = '', system_id='%s' WHERE id='%s'",
				$row['product_id'], $row['id']
			);
			$this->db->query($query, false);
			$query = sprintf(
				"UPDATE qb_items SET deleted=1 WHERE id='%s'",
				$row['dup_id']
			);
			$this->db->query($query, false);
		}
		return true;
	}
	
	function qb_re_register($qb_id) {
		// update short/long names; mark deleted if necessary; mark pending update if necessary
		$seed = new QBItem();
		if(! $seed->retrieve($qb_id))
			return false;
		$upd = false;
		$bean = $seed->get_system_object();
		$bean_deleted = false;
		if($bean) {
			if($bean->deleted || $bean->id == '-99') // deleted discounts are returned with this ID
				$bean_deleted = true;
			else {
				if($seed->set_update_status_if_modified($bean))
					$upd = true;
				switch($bean->object_name) {
					case 'Product': case 'Assembly':
						$seed->shortname = $bean->manufacturers_part_no;
						$seed->name = $bean->name;
						$upd = true;
						break;
					case 'Discount': case 'TaxRate':
						if(empty($seed->shortname))
							$seed->shortname = $bean->name;
						$seed->name = $bean->name;
						$upd = true;
						break;
				}
				// move to generic function?
				if($seed->sync_status == 'pending_export' || $seed->sync_status == 'pending_update') {
					if(! $seed->ok_for_export($bean, $errmsg)) {
						$seed->status_msg = $errmsg;
						$seed->sync_status = ($seed->sync_status == 'pending_export') ? 'export_blocked' : 'update_blocked';
						$upd = true;
					}
				}
				else if($seed->sync_status == 'export_blocked' || $seed->sync_status == 'update_blocked') {
					if($seed->ok_for_export($bean, $errmsg)) {
						$seed->status_msg = '';
						$seed->sync_status = ($seed->sync_status == 'export_blocked') ? 'pending_export' : 'pending_update';
						$upd = true;
					}
				}
			}
		}
		else if($seed->qb_id && empty($seed->shortname)) {
			if($seed->system_type == 'Booking' && $seed->qb_id == self::get_booking_line_item_id($seed->server_id)) {
				$seed->shortname = self::get_custom_item_name($seed->server_id, 'standard_booked_hours_partno');
				$seed->name = self::get_custom_item_name($seed->server_id, 'standard_booked_hours_name');
				$upd = true;
			}
			else if($seed->system_type == 'ProductCatalog' && $seed->qb_id == self::get_custom_line_item_id($seed->server_id)) {
				$seed->shortname = self::get_custom_item_name($seed->server_id, 'custom_product_partno');
				$seed->name = self::get_custom_item_name($seed->server_id, 'custom_product_name');
				$upd = true;
			}
			else if($seed->system_type == 'Assemblies' && $seed->qb_id == self::get_custom_assembly_item_id($seed->server_id)) {
				$seed->shortname = self::get_custom_item_name($seed->server_id, 'custom_assembly_partno');
				$seed->name = self::get_custom_item_name($seed->server_id, 'custom_assembly_name');
				$upd = true;
			}
			else if($seed->qb_type == 'ItemOtherCharge' && $seed->qb_id == self::get_standard_shipping_item_id($seed->server_id)) {
				$seed->shortname = self::get_custom_item_name($seed->server_id, 'standard_shipping_item_partno');
				$seed->name = self::get_custom_item_name($seed->server_id, 'standard_shipping_item_name');
				$upd = true;
			}
			else if($seed->qb_type == 'ItemSubtotal' && $seed->qb_id == self::get_standard_subtotal_item_id($seed->server_id)) {
				$seed->shortname = self::get_custom_item_name($seed->server_id, 'standard_subtotal_item_partno');
				$seed->name = self::get_custom_item_name($seed->server_id, 'standard_subtotal_item_name');
				$upd = true;
			}
		}
		if(! $bean && ! $upd && $seed->system_id) {
			$bean_deleted = true; // object no longer exists
		}
		if($bean_deleted) {
			//if($seed->first_sync == 'imported' || $seed->first_sync == 'exported')
			//	$seed->sync_status = 'pending_delete'; // delete in QB
			//else
			// if it still exists in QB, the object will likely be re-imported
			// note we can't delete Items in QB unless in single-mode AND they have never been used in a txn
				$seed->deleted = 1;
			$upd = true;
		}
		if($upd)
			$seed->save();
		return true;
	}
	
	// test me (selecting tax rate on US server)
	function get_option_name() {
		$nm = $this->shortname;
		return $nm;
	}

	function get_qb_type_dom()
	{
		global $app_list_strings;
		$dom = $app_list_strings['qb_item_types_dom'];
		return array(''=>'') + $dom;
	}

	static function get_custom_item_name($server_id, $name)
	{
		$default = array_get_default(self::$custom_item_names, $name, '');
		return QBConfig::get_server_setting($server_id, 'Items', $name, $default);
	}

}

	
?>
