<?php return; /* no output */ ?>

detail
	table_name: qb_paymentmethods
	comment: QuickBooks payment methods
	type: bean
	bean_file: modules/QBLink/QBPaymentMethod.php
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
		vname: LBL_TERMS_NAME
		type: name
		len: 100
	type
		vname: LBL_METHOD_TYPE
		type: char
		len: 100
	iah_value
		vname: LBL_IAH_VALUE
		type: varchar
		len: 60
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
	qb_paymentmethods_idx
		fields
			- server_id
			- qb_id
