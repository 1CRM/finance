<?php return; /* no output */ ?>

detail
	table_name: qb_sessions
	comment: ""
	type: bean
	bean_file: modules/QBLink/QBSession.php
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
	qb_session_id
		vname: LBL_SESSION_ID
		type: varchar
		len: 40
	server_id
		vname: LBL_SERVER_ID
		type: varchar
		len: 36
	last_access
		vname: LBL_LAST_ACCESS
		type: datetime
	requests_sent
		vname: LBL_REQUESTS_SENT
		type: int
	name
		vname: LBL_SESSION_NAME
		type: name
		len: 50
	sync_phase
		vname: LBL_SYNC_PHASE
		type: char
		len: 30
		massupdate: false
	sync_step
		vname: LBL_SYNC_STEP
		type: char
		len: 30
		massupdate: false
	sync_stage
		vname: LBL_SYNC_STAGE
		type: char
		len: 30
		massupdate: false
	redo_stage
		vname: LBL_REDO_STAGE
		type: bool
		default: 0
		massupdate: false
	status
		vname: LBL_STATUS
		type: enum
		options: qb_session_status_dom
		len: 40
		massupdate: false
	percent_done
		vname: LBL_PERCENT_DONE
		type: int
		default: 0
		massupdate: false
	error_text
		vname: LBL_ERROR_TEXT
		type: varchar
		len: 255
	created_by
		rname: created_by_name
		id_name: created_by
		vname: LBL_CREATED
		type: assigned_user_name
		table: users
		isnull: "false"
indices
	qb_sessions_sessid
		fields
			- qb_session_id
links
	requests
		relationship: requests
		module: QBLink
		bean_name: QBRequest
relationships
	requests
		lhs_module: QBLink
		lhs_table: qb_sessions
		lhs_bean: QBSession
		lhs_key: id
		rhs_module: QBLink
		rhs_table: qb_requests
		rhs_bean: QBRequest
		rhs_key: session_id
		relationship_type: one-to-many
