<?php return; /* no output */ ?>

detail
	table_name: qb_entities
	comment: ""
	type: bean
	bean_file: modules/QBLink/QBEntity.php
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
	name
		vname: LBL_CUSTOMER_NAME
		type: varchar
		len: 255
	qb_type
		vname: LBL_QB_TYPE
		vname_list: LBL_LIST_TYPE
		type: enum
		options: qb_entity_types_dom
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
	account_id
		vname: LBL_ACCOUNT_ID
		type: id
		reportable: false
	contact_id
		vname: LBL_CONTACT_ID
		type: id
		reportable: false
	opportunity_id
		vname: LBL_OPPORTUNITY_ID
		type: id
		reportable: false
	project_id
		vname: LBL_PROJECT_ID
		type: id
		reportable: false
	employee_id
		vname: LBL_EMPLOYEE_ID
		type: id
		reportable: false
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
	account_link
		vname: LBL_ACCOUNT
		type: link
		relationship: qb_entities_accounts
		link_type: one
		side: left
		module: Accounts
		bean_name: Account
		source: non-db
	account_name
		rname: name
		id_name: account_id
		vname: LBL_ACCOUNT_NAME
		type: relate
		link: account_link
		table: accounts
		isnull: "true"
		module: Accounts
		source: non-db
		len: 255
		massupdate: false
	account
		vname: LBL_LINKED_ACCOUNT
		type: ref
		bean_name: Account
		importable: false
		massupdate: false
relationships
	qb_entities_contacts
		lhs_module: Contacts
		lhs_table: contacts
		lhs_key: id
		rhs_module: QBSync
		rhs_table: qb_entities
		rhs_key: contact_id
		relationship_type: one-to-many
	qb_entities_opportunities
		lhs_module: Opportunities
		lhs_table: opportunities
		lhs_key: id
		rhs_module: QBSync
		rhs_table: qb_entities
		rhs_key: opportunity_id
		relationship_type: one-to-many
	qb_entities_accounts
		lhs_module: Accounts
		lhs_table: accounts
		lhs_key: id
		rhs_module: QBSync
		rhs_table: qb_entities
		rhs_key: account_id
		relationship_type: one-to-many
	qb_entities_employees
		lhs_module: HR
		lhs_table: employees
		lhs_key: id
		rhs_module: QBSync
		rhs_table: qb_entities
		rhs_key: employee_id
		relationship_type: one-to-many
indices
	qb_entities_name
		fields
			- name
	qb_entities_acct
		fields
			- account_id
	qb_entities_idx
		fields
			- server_id
			- qb_id
