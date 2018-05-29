<?php  ?>

qb_entity_types_dom
	Customer : Customer:Job
	Job : Job
	Employee : Employee
	OtherName : Other Name
	Vendor : Vendor
qb_item_types_dom
	ItemService : Service
	ItemNonInventory : Non-Inventory Part
	ItemOtherCharge : Other Charge
	ItemInventory : Inventory Part
	ItemInventoryAssembly : Inventory Assembly
	ItemFixedAsset : Fixed Asset
	ItemSubtotal : Subtotal
	ItemDiscount : Discount
	ItemPayment : Payment
	ItemSalesTax : Sales Tax
	ItemSalesTaxGroup : Sales Tax Group
	ItemGroup : Group
qb_first_sync_dom
	"": "Not Synced"
	imported : Imported
	exported : Exported
	deleted : Deleted
qb_sync_status_dom
	"": Enabled
	disabled : Disabled
	pending_import : Pending Import
	pending_export : Pending Export
	pending_update : Pending Update
	pending_delete : Pending Delete
	import_blocked : Import Blocked
	export_blocked : Export Blocked
	update_blocked : Update Blocked
	delete_blocked : Deletion Blocked
	import_error : Import Error
	export_error : Export Error
	update_error : Update Error
	delete_error : Deletion Error
	reg_only : Registered Only


qb_account_types_dom
	AccountsPayable : Accounts Payable
	AccountsReceivable : Accounts Receivable
	Bank : Bank
	CostOfGoodsSold : Cost of Goods Sold
	CreditCard : Credit Card
	Equity : Equity
	Expense : Expense
	FixedAsset : Fixed Asset
	Income : Income
	LongTermLiability : Long-Term Liability
	NonPosting : Non-Posting
	OtherAsset : Other Asset
	OtherCurrentAsset : Other Current Asset
	OtherCurrentLiability : Other Current Liability
	OtherExpense : Other Expense
	OtherIncome : Other Income


qb_request_status_dom
	pending : Pending
	sent : Sent
	complete : Complete
	error : Error


qb_tally_types_dom
	Estimate : Estimate
	Invoice : Invoice
	ReceivePayment : Payment
	Bill : Bill
	BillPaymentCheck : Bill Payment (Check)
	BillPaymentCreditCard : Bill Payment (Credit Card)
	CreditMemo : Credit Memo
	Check : Check Refund
	ARRefundCreditCard : Credit Card Refund


qb_sync_result_dom
	"": No sync attempt logged
	pending : Sync. session in progress
	# time or other limit
	partial : Sync. session ended early
	# error condition
	aborted : Aborted sync. session
	success : Successfully completed sync


moduleList
	QBLink:  Finance (QB)

