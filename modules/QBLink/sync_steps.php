<?php

$sync_phases = array(
	// 'setup' doubles as the list of stages in the Configuration UI
	// and as the set of steps performed at the beginning of a sync session
	'Setup' => array(
		'mgr' => 'QBConfig',
		'steps' => array(
			// these steps are allowed to proceed in parallel
			'Basic' => array(
				'label' => 'LBL_CFG_STEP_BASIC',
			),
			'Currencies' => array(
				'label' => 'LBL_CFG_STEP_CURRENCIES',
				'mgr' => 'QBCurrency',
			),
			'GetConfigItems' => array(
				'label' => 'LBL_CFG_STEP_ITEMS',
				'mgr' => 'QBItem',
				'config' => false,
			),
			'TaxRates' => array(
				'label' => 'LBL_CFG_STEP_TAXRATES',
				'mgr' => 'QBTaxCode',
			),
			'TaxCodes' => array(
				'label' => 'LBL_CFG_STEP_TAXCODES',
				'mgr' => 'QBTaxCode',
			),
			'Accounts' => array(
				'label' => 'LBL_CFG_STEP_ACCOUNTS',
				'mgr' => 'QBAccount',
				'config' => false,
			),
			'CustomerTypes' => array(
				'label' => 'LBL_CFG_STEP_CUSTOMER_TYPES',
				'mgr' => 'QBCustomerType',
			),
			'Terms' => array(
				'label' => 'LBL_CFG_STEP_TERMS',
				'mgr' => 'QBTerms',
			),
			'PaymentMethods' => array(
				'label' => 'LBL_CFG_STEP_PAYMENT_METHODS',
				'mgr' => 'QBPaymentMethod',
			),
			'ShipMethods' => array(
				'label' => 'LBL_CFG_STEP_SHIP_METHODS',
				'mgr' => 'QBShipMethod',
			),
			'ItemAccounts' => array(
				'label' => 'LBL_CFG_STEP_ITEM_ACCOUNTS',
				'mgr' => 'QBItem',
			),
			'ExpenseCategories' => array(
				'label' => 'LBL_CFG_STEP_EXPENSE_CATEGORIES',
				'mgr' => 'QBAccount',
			),
			'Sync_Opts' => array(
				'label' => 'LBL_CFG_STEP_SYNC_OPTS',
			),
		),
	),
	'Items' => array(
		'mgr' => 'QBItem',
	),
	'Customers' => array(
		'mgr' => 'QBEntity',
	),
	'Vendors' => array(
		'mgr' => 'QBEntity',
	),
	'Estimates' => array(
		'mgr' => 'QBTally',
	),
	'Invoices' => array(
		'mgr' => 'QBTally',
	),
	'Payments' => array(
		'mgr' => 'QBTally',
	),
	'Bills' => array(
		'mgr' => 'QBTally',
	),

	'BillCheckPayments' => array(
		'mgr' => 'QBTally',
	),
	'BillCCPayments' => array(
		'mgr' => 'QBTally',
	),

	'CreditMemos' => array(
		'mgr' => 'QBTally',
	),

	'CreditMemosInvoices' => array(
		'mgr' => 'QBTally',
	),

	'Checks' => array(
		'mgr' => 'QBTally',
	),

	'ARRefundCreditCards' => array(
		'mgr' => 'QBTally',
	),
);

foreach(array(
	'modules/QBLink/sync_steps.override.php',
	'custom/modules/QBLink/sync_steps.php',
) as $altpath)
	if(file_exists($altpath)) include($altpath);
