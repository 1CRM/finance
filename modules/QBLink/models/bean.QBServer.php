<?php return; /* no output */ ?>

detail
	table_name: qb_servers
	comment: QuickBooks server metadata table
	type: bean
	bean_file: modules/QBLink/QBServer.php
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
	name
		vname: LBL_NAME
		type: name
		len: 100
		required: true
	last_connect
		vname: LBL_LAST_CONNECT
		type: datetime
	last_sync_result
		vname: LBL_LAST_SYNC_RESULT
		type: enum
		options: qb_sync_result_dom
		len: 20
	last_sync_msg
		vname: LBL_LAST_SYNC_MESSAGE
		type: varchar
		len: 255
	sync_options
		vname: LBL_SYNC_OPTIONS
		type: text
		reportable: false
	server_info
		vname: LBL_SERVER_INFO
		type: text
		reportable: false
	company_filename
		vname: LBL_COMPANY_FILENAME
		type: varchar
		len: 200
		required: true
	ip_address
		vname: LBL_IP_ADDRESS
		type: varchar
		len: 20
	qb_file_id
		vname: LBL_FILE_ID
		type: varchar
		len: 40
		default: ""
		required: true
	qb_owner_id
		vname: LBL_OWNER_ID
		type: varchar
		len: 40
		required: true
	qb_edition
		vname: LBL_QB_EDITION
		type: varchar
		len: 100
	qb_version
		vname: LBL_QB_VERSION
		type: varchar
		len: 10
	qb_xml_version
		vname: LBL_QB_XML_VERSION
		type: varchar
		len: 10
	qb_xml_supported
		vname: LBL_QB_XML_SUPPORTED
		type: varchar
		len: 255
	connector
		vname: LBL_CONNECTOR
		type: varchar
		len: 20
		default: qbwc
indices
