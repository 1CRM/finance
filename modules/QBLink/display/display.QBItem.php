<?php return; /* no output */ ?>

list
	show_tabs: false
    show_create_button: false
	default_order_by: name
    mass_update
        disabled: false
	layouts
		QBItem
			vname: LBL_ITEMS
			view_name: QBItem
			filter_name: QBItem
fields
    linked_item
        vname: LBL_QB_TYPE
        vname_module: QBLink
        type: varchar
        widget: QBBeanLinks
    status
		vname: LBL_SYNC_STATUS
        vname_module: QBLink
        type: varchar
        widget: QBBeanStatus
filters
    sync_status_
        type: enum
		options_function 
			class: QBBean
			class_function: get_sync_status_dom
			file: modules/QBLink/QBBean.php
		filter_clause_source
			file: modules/QBLink/QBBean.php
			class: QBBean
			class_function: get_sync_status_where
        name: sync_status_
        vname: LBL_SYNC_STATUS
    qb_type_
        type: enum
		options_function 
			class: QBBean
			class_function: get_qb_item_types_dom
			file: modules/QBLink/QBBean.php
		filter_clause_source
			file: modules/QBLink/QBBean.php
			class: QBBean
			class_function: get_qb_type_where
        name: qb_type_
        vname: LBL_QB_TYPE
    first_sync_
        type: enum
		options_function 
			class: QBBean
			class_function: get_first_sync_dom
			file: modules/QBLink/QBBean.php
		filter_clause_source
			file: modules/QBLink/QBBean.php
			class: QBBean
			class_function: get_first_sync_where
        name: first_sync_
        vname: LBL_FIRST_SYNC
