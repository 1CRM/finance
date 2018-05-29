<?php return; /* no output */ ?>

detail
	table_name: qb_requests
	comment: Requests sent to the QuickBooks server
	type: bean
	bean_file: modules/QBLink/QBRequest.php
	audit_enabled: false
	unified_search: false
	duplicate_merge: false
	optimistic_locking: true
	primary_key: id
	default_order_by: id
	reportable: false
fields
	app.id
	app.deleted
	app.date_entered
	app.date_modified
	session_id
		vname: LBL_SESSION_ID
		type: id
		required: true
		reportable: false
	sync_phase
		vname: LBL_SYNC_PHASE
		type: varchar
		len: 30
	sync_step
		vname: LBL_SYNC_STEP
		type: varchar
		len: 30
	sync_stage
		vname: LBL_SYNC_STAGE
		type: varchar
		len: 30
	status
		vname: LBL_STATUS
		type: enum
		options: qb_request_status_dom
		len: 40
	sequence
		vname: LBL_SEQUENCE
		type: varchar
		len: 20
	params_id
		vname: LBL_PARAMS
		type: id
	request_type
		vname: LBL_REQUEST_TYPE
		type: varchar
		len: 40
	related_id
		vname: LBL_RELATED_ID
		type: varchar
		len: 36
	send_count
		vname: LBL_SEND_COUNT
		type: int
		default: 0
		massupdate: false
	iter_remain
		vname: LBL_ITER_REMAIN
		type: int
		default: 0
		massupdate: false
	last_import_name
		vname: LBL_LAST_IMPORT_NAME
		type: varchar
		len: 255
		massupdate: false
	last_import_count
		vname: LBL_LAST_IMPORT_COUNT
		type: int
		len: 0
		massupdate: false
indices
	qb_requests_sess_id
		fields
			- session_id
