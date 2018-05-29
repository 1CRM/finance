<?php ?>

detail
	type: link
	table_name: qb_tax_code_rates
	primary_key
		- taxcode_id
		- item_id
fields
	app.date_modified
	app.deleted
	taxcode
		type: ref
		bean_name: QBTaxCode
	item
		type: ref
		bean_name: QBItem
indices
	idx_code
		fields
			- taxcode_id
relationships
	qb_tax_code_rates
		lhs_key: id
		rhs_key: id
		relationship_type: one-to-many
		join_key_lhs: taxcode_id
		join_key_rhs: item_id
		lhs_bean: QBTaxCode
		rhs_bean: QBItem
