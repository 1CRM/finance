<?php return; /* no output */ ?>

detail
	table_name: qb_accounts
	comment: QuickBooks financial accounts
	type: bean
	bean_file: modules/QBLink/QBAccount.php
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
		vname: LBL_ACCOUNT_NAME
		type: name
		len: 255
	description
		vname: LBL_DESCRIPTION
		type: varchar
		len: 255
	acct_number
		vname: LBL_ACCOUNT_NUMBER
		type: varchar
		len: 10
	acct_type
		vname: LBL_ACCOUNT_TYPE
		type: enum
		options: qb_account_types_dom
		len: 40
		massupdate: false
	sublevel
		vname: LBL_SUBLEVEL
		type: int
	parent_qb_id
		vname: LBL_PARENT_QB_ID
		type: varchar
		len: 36
	currency_qb_id
		vname: LBL_CURRENCY_QB_ID
		type: varchar
		len: 36
	currency_id
		vname: LBL_CURRENCY_ID
		type: varchar
		len: 36
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
	qb_accounts_idx
		fields
			- server_id
			- qb_id
