<?php return; /* no output */ ?>

detail
	table_name: qb_config
	comment: QuickBooks Link configuration table
	type: bean
	bean_file: modules/QBLink/QBConfig.php
	audit_enabled: false
	unified_search: false
	duplicate_merge: false
	optimistic_locking: true
	primary_key: id
	default_order_by: name
	reportable: false
fields
	app.id
	server_id
		vname: LBL_SERVER_ID
		type: varchar
		len: 36
		required: true
		default: ""
	category
		vname: LBL_CATEGORY
		type: varchar
		len: 60
		required: true
	name
		vname: LBL_NAME
		type: varchar
		len: 50
		required: true
	value
		vname: LBL_VALUE
		type: text
		required: true
indices
	qb_config_servcat
		fields
			- server_id
			- category
