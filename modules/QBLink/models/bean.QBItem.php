<?php return; /* no output */ ?>

detail
	table_name: qb_items
	comment: ""
	type: bean
	bean_file: modules/QBLink/QBItem.php
	audit_enabled: false
	unified_search: false
	duplicate_merge: false
	optimistic_locking: true
	primary_key: id
	default_order_by: name
	reportable: false
audited: false
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
	parent_qb_id
		vname: LBL_PARENT_QB_ID
		type: id
		len: 36
		required: false
		reportable: false
	qb_editseq
		vname: LBL_EDIT_SEQUENCE
		type: int
		len: 16
		reportable: false
	shortname
		vname: LBL_ITEM_SHORT_NAME
		type: varchar
		len: 50
		required: false
		reportable: false
	name
		vname: LBL_CUSTOMER_NAME
		type: varchar
		len: 4096
	qb_type
		vname: LBL_QB_TYPE
		type: enum
		options: qb_item_types_dom
		len: 40
		massupdate: false
	first_sync
		vname: LBL_FIRST_SYNC
		type: enum
		options: qb_first_sync_dom
		len: 40
		default: ""
		massupdate: false
	sync_status
		vname: LBL_SYNC_STATUS
		type: enum
		options: qb_sync_status_dom
		len: 40
		default: ""
		massupdate: false
	status_msg
		vname: LBL_STATUS_MESSAGE
		type: varchar
		len: 4095
	system_type
		vname: LBL_SYSTEM_TYPE
		type: varchar
		len: 40
	system_id
		vname: LBL_SYSTEM_ID
		type: id
		reportable: false
	assoc_rate
		vname: LBL_ASSOC_RATE
		type: float
	not_reimbursable
		vname: LBL_NOT_REIMBURSABLE
		type: bool
		default: 0
		massupdate: false
	date_last_sync
		vname: LBL_DATE_LAST_SYNC
		type: datetime
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
relationships
indices
	qb_items_shortname
		fields
			- shortname
	qb_items_sys
		fields
			- system_type
			- system_id
	qb_items_idx
		fields
			- server_id
			- qb_id
