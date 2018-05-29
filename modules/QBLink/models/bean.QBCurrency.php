<?php return; /* no output */ ?>

detail
	table_name: qb_currencies
	comment: QuickBooks currencies
	type: bean
	bean_file: modules/QBLink/QBCurrency.php
	audit_enabled: false
	unified_search: false
	duplicate_merge: false
	optimistic_locking: true
	primary_key: id
	default_order_by: name
	reportable: false
fields
	app.id
	app.deleted
	app.date_entered
	app.date_modified
	qb_id
		vname: LBL_QB_ID
		type: id
		len: 36
		required: false
		reportable: false
	server_id
		vname: LBL_SERVER_ID
		type: varchar
		len: 36
	qb_editseq
		vname: LBL_EDIT_SEQUENCE
		type: int
		len: 16
		reportable: false
	name
		vname: LBL_CURRENCY_NAME
		type: name
		len: 100
	symbol
		vname: LBL_CURRENCY_SYMBOL
		type: varchar
		len: 36
		required: false
		default: ""
	iso4217
		vname: LBL_CURRENCY_ISO4217
		type: varchar
		len: 3
		required: false
		default: ""
	conversion_rate
		vname: LBL_CURRENCY_RATE
		type: float
		default: 0
		required: true
	country
		vname: LBL_COUNTRY
		type: varchar
		len: 60
		default: ""
	currency_id
		vname: LBL_CURRENCY_ID
		type: id
	qb_date_entered
		vname: LBL_QB_DATE_ENTERED
		type: datetime
		required: false
	qb_date_modified
		vname: LBL_QB_DATE_MODIFIED
		type: datetime
		required: false
	qb_is_active
		vname: LBL_IS_ACTIVE
		type: bool
		default: 1
indices
	qb_currencies_idx
		fields
			- server_id
			- qb_id
