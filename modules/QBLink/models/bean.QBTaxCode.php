<?php return; /* no output */ ?>

detail
	table_name: qb_taxcodes
	comment: QuickBooks tax codes
	type: bean
	bean_file: modules/QBLink/QBTaxCode.php
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
		vname: LBL_TAX_NAME
		type: name
		len: 255
	code
		vname: LBL_TAX_CODE
		type: name
		len: 10
	related_id
		vname: LBL_RELATED_ID
		type: varchar
		len: 36
	charge_tax_1
		vname: LBL_CHARGE_TAX_1
		type: bool
	tax_rate_1
		vname: LBL_TAX_RATE_1
		type: float
	charge_tax_2
		vname: LBL_CHARGE_TAX_2
		type: bool
	tax_rate_2
		vname: LBL_TAX_RATE_2
		type: float
	is_piggyback
		vname: LBL_IS_PIGGYBACK
		type: bool
	is_ec_vat
		vname: LBL_IS_EC_VAT
		type: bool
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
	taxes
		vname: LBL_TAXES
		type: link
		relationship: qb_tax_code_rates
		link_type: many
		side: left
		module: QBLink
		bean_name: QBItem
		source: non-db
indices
	qb_taxcodes_idx
		fields
			- server_id
			- qb_id
