<?php ?>
detail
	type: table
	table_name: qb_request_params
	primary_key
		- id
fields
	app.id
	app.deleted
	request_id
		type: id
		required: true
	params
		type: text
		required: false
	qbxml
		type: text
		required: false

