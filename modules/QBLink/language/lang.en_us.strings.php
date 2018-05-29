<?php ?>
LBL_MODULE_TITLE : QuickBooks Link
LBL_SEARCH_FORM_TITLE : Search
LBL_LIST_FORM_TITLE : List
LNK_MODULE_CONFIG : Configuration
LNK_MODULE_STATUS : Status
LNK_ENTITY_LIST : Entity Sync
LNK_ITEM_LIST : Item Sync
LNK_ESTIMATE_LIST : Estimate/Invoice Sync
LNK_INVOICE_LIST : Invoice List
LBL_STATUS_CONFIGURED : The system is configured for synchronization with QuickBooks. To change system settings proceed to the {{Configuration}} screen.
LBL_STATUS_NO_SYNC : The QuickBooks server has been identified but is not yet enabled for synchronization. To enable it please finish the {{Configuration}} process.
LBL_STATUS_NOT_CONFIGURED : The system has not yet been paired with a QuickBooks server. After downloading the QWC configuration file and attempting a connection using the QuickBooks Web Connector, you can proceed to the {{Configuration}} screen to begin synchronization.
LBL_STATUS_SSL_URL_DESC : In order to synchronize with QuickBooks, the info@hand server must be available via HTTPS (SSL encrypted transport). If your primary server address does not use SSL, please enter a secure address for the server before downloading the configuration file.
LBL_STATUS_SSL_URL : HTTPS Server Address:
LBL_STATUS_SSL_URL_UNVERIFIED : "[Unverified]"
LBL_NAME : Name:
LBL_TYPE : Type:
LBL_EDIT_SEQUENCE : Edit Sequence:
LBL_IS_ACTIVE : Is Active:
LBL_FIRST_SYNC : First Sync:
LBL_SYNC_STATUS : Sync Status:
LBL_INACTIVE : (Inactive)
LBL_ACCOUNT : Account:
LBL_CONTACT : Contact:
LBL_OPPORTUNITY : Opportunity:
LBL_PROJECT : Project:
LBL_EMPLOYEE : Employee:
LBL_PRODUCT : Product:
LBL_QUOTES : Quote:
LBL_INVOICE : Invoice:
LBL_PAYMENTS : Payment:
LBL_REGISTER_BUTTON_LABEL : Register Local Changes
LBL_RESET_DATES_BUTTON_LABEL : Force Re-Import
LBL_DELETE : Delete from QB
NTC_DELETE_CONFIRMATION_MULTIPLE : Are you sure you wish to mark the selected objects for deletion?
LBL_RE_REGISTER_BUTTON_LABEL : Re-Register
LBL_ENABLE_SYNC_BUTTON_LABEL : Enable Sync
LBL_DISABLE_SYNC_BUTTON_LABEL : Disable Sync
LBL_FORCE_UPDATE_BUTTON_LABEL : Force Update in QB
NTC_NO_PRIMARY_SERVER : There is currently no primary QuickBooks server.
LBL_LIST_NAME : Name
LBL_LIST_SHORT_NAME : Short Name
LBL_LIST_NUMBER : Number
LBL_LIST_TYPE : Type
LBL_LIST_LINKAGES : Linkages
LBL_LIST_FIRST_SYNC : First Sync.
LBL_LIST_SYNC_STATUS : Sync. Status
LBL_LIST_ENTERED : Date Entered
LBL_LIST_MODIFIED : Date Modified
LBL_QWC_APPNAME : info@hand Finance (QB)
LBL_QWC_LONGNAME : info@hand Finance: QuickBooks Edition 
LBL_QWC_APPDESCRIPTION : Synchronizes with info@hand installation
LBL_QWC_NO_SSL_SUPPORT : SSL support must be enabled. Please update the site_url parameter in config.php to use an https:// URL, or add an ssl_url parameter if you don't wish to use SSL exclusively.
LBL_SUPPORT_TITLE : QB Link Status
LBL_REDIRECT_MESSAGE : You should now be redirected to the QB Link status page. You will need to be authenticated with info@hand.
LBL_REDIRECT_MESSAGE_2 : Go to status page
LBL_CFG_TITLE : QuickBooks Link Configuration
LBL_CFG_STEPS_TITLE : Configuration Steps:
LBL_CFG_STEP_BASIC : Server Selection
LBL_QWC_DOWNLOAD_1 : Once the server's HTTPS address has been configured, download this configuration file for the QuickBooks Web Connector:
LBL_QWC_DOWNLOAD_2 : Download Web Connector configuration file (.qwc)
LBL_QWC_DOWNLOAD_3 : The web connector application is available at the following address: 
LBL_QWC_DOWNLOAD_AGAIN : Re-download Web Connector configuration file (.qwc) for this server
LBL_QWC_SELECT_USER : Select the info@hand user QuickBooks Web Connector will use to authenticate:
LBL_QWC_SELECT_DOWNLOAD : Download
LBL_CFG_SERVER_INFO_TITLE : Server Information:
LBL_CFG_SET_PRIMARY_SERVER : Accept connections by this server
LBL_CFG_ALT_SERVER_INFO_TITLE : Last connection attempt:
LBL_CFG_ACTION_NONE : Take no action
LBL_CFG_ACTION_IGNORE_ALT : Ignore this connection attempt
LBL_CFG_ACTION_UPDATE_PRIMARY : Use these new connection details (same company file, but location has changed)
LBL_CFG_ACTION_NEW_PRIMARY : Replace the primary QuickBooks server with this one (not the same company file)
LBL_CFG_NTC_NO_SERVER : No connection attempts by a QuickBooks server have been detected.
LBL_CFG_LIMIT_FILENAME : Always request this company file when initiating a connection (required for automatic QuickBooks login)
LBL_QB_EDITION : Server Edition:
LBL_IP_ADDRESS : IP Address:
LBL_COMPANY_NAME : Company Name:
LBL_COMPANY_FILENAME : Filename:
LBL_LAST_CONNECT : Last Connection:
LBL_CONNECT_TIME : Connection Time:
LBL_QB_FILE_ID : File Owner ID:
LBL_CFG_STEP_CURRENCIES : Currencies
LBL_CFG_STEP_TAXRATES : Tax Rates
LBL_CFG_STEP_TAXCODES : Tax Codes
LBL_CFG_CURRENCY_UNMAPPED :  -- Not mapped -- 
LBL_CFG_NTC_CURRENCIES_1 : QuickBooks currencies are on the left, and info@hand currencies on the right. Mapping has been performed automatically for matching ISO4217 codes, but other currencies may need to be matched up by hand. Please note that Canadian and UK versions of QuickBooks are expected to have Multi-Currency support enabled. Objects which rely on currencies that have not been mapped cannot be synchronized.
LBL_CFG_STEP_TERMS : Payment Terms
LBL_CFG_TERMS_DEFAULT_IMPORT : Fallback Terms for Imports
LBL_CFG_PAYMENTMETHODS_DEFAULT_IMPORT : Fallback Payment Method for Imports
LBL_CFG_STEP_ACCOUNTS : Financial Accounts
LBL_CFG_STEP_SYNC_OPTS : Go Live
LBL_CFG_NTC_SYNC_OPTS_1 : Now you may select which information is transferred between QuickBooks and info@hand.
LBL_CFG_NTC_SYNC_OPTS_2 : Batch size specifies how many records are synchronized per request. Provided defaults are suitable in most cases. If you expierence timeout errors during sync, try using smaller batch sizes.
LBL_CFG_ALLOW_SYNC : Enable synchronization between QuickBooks and info@hand
LBL_CFG_IMPORT_TITLE : Import Options
LBL_IMPORT_QB_CUSTOMERS : Import QuickBooks Customers
LBL_IMPORT_QB_VENDORS : Import QuickBooks Vendors
LBL_IMPORT_QB_PRODUCTS : Import QuickBooks Inventory Items
LBL_IMPORT_QB_ESTIMATES : Import QuickBooks Estimates
LBL_IMPORT_QB_INVOICES : Import QuickBooks Invoices and Incoming Payments
LBL_IMPORT_QB_BILLS : Import QuickBooks Bills and Outgoing Payments
LBL_EXPORT_IAH_CUSTOMERS : Export info@hand Customers
LBL_EXPORT_IAH_VENDORS : Export info@hand Vendors
LBL_EXPORT_IAH_ASSOC_ACCOUNTS_ONLY : Limit exports to Accounts with associated Invoices
LBL_EXPORT_IAH_ASSOC_VENDORS_ONLY : Limit exports to Accounts with associated Bills
LBL_EXPORT_IAH_PRODUCTS : Export info@hand Product Catalog
LBL_EXPORT_IAH_QUOTES : Export info@hand Quotes
LBL_EXPORT_IAH_INVOICES : Export info@hand Invoices and Incoming Payments
LBL_EXPORT_IAH_BILLS : Export info@hand Bills and Outgoing Payments
LBL_CFG_EXPORT_TITLE : Export Options
LBL_CFG_BATCH_TITLE : Batch Size
LBL_CFG_CONFIRM_TITLE : Confirmation
LBL_CFG_STEP_SHIP_METHODS : Shipping Methods
LBL_CFG_STEP_PAYMENT_METHODS : Payment Methods
LBL_CFG_STEP_ITEM_ACCOUNTS : Item Accounts
LBL_CFG_STEP_EXPENSE_CATEGORIES : Expense Categories
LBL_CFG_STEP_CUSTOMER_TYPES : Customer Types
LBL_CFG_NO_CUSTOMER_TYPES : Customer Types have not yet been downloaded from QuickBooks, or none are defined.
LBL_CFG_NO_TERMS : Payment Terms have not yet been downloaded from QuickBooks, or none are defined.
LBL_CFG_NO_CURRENCIES : Currencies have not yet been downloaded from QuickBooks, or none are defined.
LBL_CFG_NO_PAYMENT_METHODS : Payment Methods have not yet been downloaded from QuickBooks, or none are defined.
LBL_CFG_NO_SHIP_METHODS : Shipping Methods have not yet been downloaded from QuickBooks, or none are defined.
LBL_CFG_NO_TAX_CODES : Tax Codes have not yet been downloaded from QuickBooks, or none are defined.
LBL_CFG_EXPORT_ALL_ACCOUNT_TYPES : To register all info@hand Account Types as QuickBooks Customer Types, press the button below and they will be exported to QuickBooks on the next sync.
LBL_CFG_EXPORT_ACCOUNT_TYPES_BUTTON : Register Customer Types
LBL_CFG_SEL_ALL_IMPORT : Set "Add to List" for all unmapped QuickBooks values
LBL_BUTTON_BACK : Back
LBL_BUTTON_NEXT : Next
LBL_BUTTON_FINISH : Finish
LBL_TAX_US_GENERAL : Sales Tax
LBL_TAX_CA_GST : General Sales Tax (GST)
LBL_TAX_CA_PST : Provincial Sales Tax (PST)
LBL_TAX_UK_VAT : Value Added Tax (VAT)
LBL_CFG_MAP_CREATE_NEW :  * Add to List * 
LBL_CFG_MAP_CREATE_UNSPEC :  * Add and Use "Unspecified" *
LBL_DD_REQUIRED : (Required)
LBL_DD_UNSPECIFIED : Unspecified
LBL_SALES_TAX_DISABLED : Sales tax is disabled for this company file
LBL_ESTIMATES_DISABLED : Estimates are disabled for this company file
LBL_INVENTORY_DISABLED : Inventory and purchase orders are disabled for this company file
LBL_ALL_PENDING : (All Pending)
LBL_ALL_BLOCKED : (All Blocked)
LBL_ALL_ERRORED : (All Errored)
LBL_TAB_OVERVIEW : Overview
LBL_TAB_ACTIVITY : Activity
LBL_SESSION_ID : Session ID
LBL_ITEM_ACCOUNTS_PAYABLE : Accounts Payable
LBL_ITEM_ACCOUNTS_RECEIVABLE : Accounts Receivable
LBL_ITEM_INCOME_ACCOUNT : Default Product Income Account
LBL_ITEM_COST_GOODS_ACCOUNT : Default Cost-of-Goods Account
LBL_ITEM_ASSET_ACCOUNT : Default Inventory Assets Account
LBL_ITEM_EXPENSE_ACCOUNT : Default Product Expense Account
LBL_ITEM_SHIPPING_ACCOUNT : Default Shipping Income Account
LBL_ITEM_DISCOUNT_ACCOUNT : Default Discount Expense Account
LBL_ITEM_CHECKING_ACCOUNT : Check Payments Bank Account
LBL_ITEM_CC_ACCOUNT : Credit Card Payments Bank Account
LBL_ITEM_IMPORT_CATEGORY : Default Category for Imported Products
LBL_ITEM_IMPORT_TYPE : Default Type for Imported Products
LBL_ITEM_WAREHOUSE : Warehouse for Inventory Items
LBL_CUSTOM_PRODUCT_PARTNO : Custom Product Part Number
LBL_CUSTOM_ASSEMBLY_PARTNO : Custom Assembly Part Number
LBL_STANDARD_BOOKED_HOURS_PARTNO : Booked Hours Part Number
LBL_STANDARD_SHIPPING_ITEM_PARTNO : Shipping Part Number
LBL_STANDARD_SUBTOTAL_ITEM_PARTNO : Subtotal Item Part Number
ERR_SYNC_DISABLED : Cannot sync items - syncing is not enabled.
ERR_NO_ITEM_CATEGORY : Cannot sync items - Default Category for Imported Products is not set.
ERR_NO_ITEM_WAREHOUSE : Cannot sync items - Warehouse for Inventory Items is not set.
ERR_NO_SALES_TAX : Quote/Invoice export postponed - Sales Tax disabled in QuickBooks.
ERR_NO_STANDARD_ITEMS : Quote/Invoice export postponed - Standard Items not synced.
LBL_QB_TYPE: Type
LBL_TALLY_NAME: Name
LBL_TALLY_SHORT_NAME: Number
LBL_ITEM_SHORT_NAME: Number
LBL_CUSTOMER_NAME: Name
LBL_LINKED_ACCOUNT: Linked Account
LBL_BATCH_PRODUCTS_IMPORT: Products Import
LBL_BATCH_PRODUCTS_EXPORT: Products Export
LBL_BATCH_INVOICES_IMPORT: Invoices/Quotes/Bills Import
LBL_BATCH_INVOICES_EXPORT: Invoices/Quotes/Bills Export
LBL_BATCH_PAYMENTS_IMPORT: Payments Import
LBL_BATCH_PAYMENTS_EXPORT: Payments Export
LBL_BATCH_ACCOUNTS_IMPORT: Customers/Vendors Import
LBL_BATCH_ACCOUNTS_EXPORT: Customers/Vendors Export

