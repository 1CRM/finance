{* * 
 * 
 * Copyright 2004-2012 1CRM Systems Corp.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *     http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
*}


<script type="text/javascript" src="modules/QBLink/edit_config.js"></script>

{if $TITLE}
<div id="title">{$TITLE}</div>
{/if}

{if $ALERT}
<div id="alert">{$ALERT}</div>
{/if}

<div id="nav">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td style="padding-bottom: 2px;">
<form action="index.php" method="post" name="QBLConfigForm" id="form">
	<input type="hidden" name="module" value="QBLink">
	<input type="hidden" name="action" value="EditConfig">
	<input type="hidden" name="from_step" value="{$NAV.current}">
	<input type="hidden" name="save_step" value="0">
	<input type="hidden" name="step" value="{$NAV.current}">
	
		{if ! $HIDE_BACK}
			<input	title		= "{$MOD.LBL_BUTTON_BACK}" 
					class		= "button"
					onclick		= "this.form.step.value='{$NAV.back}';" 
					type		= "submit"
					{if ! $NAV.back}disabled= "disabled"{/if}
					value		= "  {$MOD.LBL_BUTTON_BACK}  "
					id			= "back_button" >
		{/if}
		{if $NAV.last == $NAV.current}
			<input	title		= "{$MOD.LBL_BUTTON_FINISH}" 
					class		= "button"
					onclick		= "this.form.step.value=''; if(!checkCurrentStep(this.form)) return false; this.form.save_step.value='1';" 
					type		= "submit"
					value		= "  {$MOD.LBL_BUTTON_FINISH}  "
					id			= "next_button" >
		{elseif ! $HIDE_NEXT}
			<input	title		= "{$MOD.LBL_BUTTON_NEXT}" 
					class		= "button"
					onclick		= "this.form.step.value='{$NAV.next}'; if(!checkCurrentStep(this.form)) return false; this.form.save_step.value='1';" 
					type		= "submit"
					value		= "  {$MOD.LBL_BUTTON_NEXT}  "
					{if ! $NAV.next}disabled= "disabled"{/if}
					id			= "next_button" >
		{/if}
		<input	title		= "{$APP.LBL_CANCEL_BUTTON_TITLE}" 
					class		= "button"
					onclick		= "this.form.step.value=''; this.form.action.value='Status';" 
					type		= "submit"
					value		= "  {$APP.LBL_CANCEL_BUTTON_LABEL}  "
					id			= "cancel_button" >
		</td>
	</tr>
</table>
</div>
<br />
<div id="main">
<table width="100%" border="0" cellpadding="0" cellpadding="0" class="tabDetailView">
{if $ERRORS}
	<tr>
		<td colspan="2" class="tabDetailViewDF">
			{foreach name=errIter from=$ERRORS item=err}
			<p class="error"><b>{$err}</b></p>
			{/foreach}
		</td>
	</tr>
{/if}

	<tr>
		<td width="25%" class="tabDetailViewDL" rowspan="10"><slot>
			<table cellpadding="3" cellspacing="0" border="0">
			<tr><th colspan="3" style="text-align: left">
				{$MOD.LBL_CFG_STEPS_TITLE}
			</th></tr>
			{counter start=1 name="stepCounter" print=false assign="stepCounter"}
			{foreach name=stepIter from=$STEPS key=stepId item=step}
			{if ! isset($step.config) || $step.config }
				<tr><td>&nbsp;</td><td style="text-align: left">
				{if $NAV.current == $stepId}<b>{/if}{if $step.status != ''}<a href="index.php?module=QBLink&action=EditConfig&step={$stepId}" class="tabDetailViewLink">{/if}{$stepCounter}: {$step.name}</a></b>
				</td>
				<td><img src="include/images/iah/{$step.color}led.gif" title="{$step.status}" width="12" height="12" style="vertical-align: baseline"></td></tr>
			{/if}
			{counter name="stepCounter"}
			{/foreach}
			</table>
		</slot></td>
		<td width="75%" class="tabDetailViewDF"><slot>
			{if $NAV.current == 'Basic'}				
				{if $PRIM_SERV}
					{$PRIM_SERVER_INFO_TITLE}
					<ul>
					  <li>{$MOD.LBL_QB_EDITION} {$PRIM_SERV.name}</li>
					  <li>{$MOD.LBL_IP_ADDRESS} {$PRIM_SERV.ip_address}</li>
					  <li>{$MOD.LBL_COMPANY_NAME} {$PRIM_SERV.company_name}</li>
					  <li>{$MOD.LBL_COMPANY_FILENAME} {$PRIM_SERV.company_filename}</li>
					  {if $NEW_SERV && $NEW_SERV.company_filename == $PRIM_SERV.company_filename}
					  	<li>{$MOD.LBL_QB_FILE_ID} {$PRIM_SERV.qb_file_id}</li>
					  {/if}
					  <li>{$MOD.LBL_LAST_CONNECT} {$PRIM_SERV.last_connect}</li>
					  {if $PRIM_SERV.warnings}
					  	{foreach name=warnIter from=$PRIM_SERV.warnings item=warning}
					  		<li class="error">{$warning}</li>
					  	{/foreach}
					  {/if}
					  <li><a href="index.php?module=QBLink&action=QWC&server_id={$PRIM_SERV.id}&return_action=EditConfig">{$MOD.LBL_QWC_DOWNLOAD_AGAIN}</a></li>
					</ul>
					
					<input type="hidden" name="limit_filename" value="0" />
					<input type="checkbox" name="limit_filename" class="checkbox" value="1" {if $LIMIT_FILENAME}checked="checked"{/if} />
						<label for="limit_filename">{$MOD.LBL_CFG_LIMIT_FILENAME}</label>
				{/if}
				
				{if $NEW_SERV}
					{$ALT_SERVER_INFO_TITLE}
					<ul>
					  <li>{$MOD.LBL_QB_EDITION} {$NEW_SERV.name}</li>
					  <li>{$MOD.LBL_IP_ADDRESS} {$NEW_SERV.ip_address}</li>
					  <li>{$MOD.LBL_COMPANY_NAME} {$NEW_SERV.company_name}</li>
					  <li>{$MOD.LBL_COMPANY_FILENAME} {$NEW_SERV.company_filename}</li>
					  {if $PRIM_SERV && $NEW_SERV.company_filename == $PRIM_SERV.company_filename}
					  	<li>{$MOD.LBL_QB_FILE_ID} {$NEW_SERV.qb_file_id}</li>
					  {/if}
					  <li>{$MOD.LBL_CONNECT_TIME} {$NEW_SERV.last_connect}</li>
					</ul>
					{if $PRIM_SERV}
						<input type="hidden" name="alt_server_id" value="{$NEW_SERV.id}">
						<input type="radio" class="radio" name="alt_server_action" value="" checked="checked">
						{$MOD.LBL_CFG_ACTION_NONE}<br>
						<input type="radio" class="radio" name="alt_server_action" value="ignore">
						{$MOD.LBL_CFG_ACTION_IGNORE_ALT}<br>
						<input type="radio" class="radio" name="alt_server_action" value="update">
						{$MOD.LBL_CFG_ACTION_UPDATE_PRIMARY}<br>
						<input type="radio" class="radio" name="alt_server_action" value="replace">
						{$MOD.LBL_CFG_ACTION_NEW_PRIMARY}
					{else}
						<input type="checkbox" class="checkbox" name="set_primary_server_id" value="{$NEW_SERV.id}">
						<b>{$MOD.LBL_CFG_SET_PRIMARY_SERVER}</b>
					{/if}
				{/if}
				
				{if ! $PRIM_SERV && ! $NEW_SERV}
					<p><b>{$MOD.LBL_CFG_NTC_NO_SERVER}</b></p>
				{/if}
			{elseif $NAV.current == 'Sync_Opts'}
				<p>{$MOD.LBL_CFG_NTC_SYNC_OPTS_1}</p>
				<p>{$MOD.LBL_CFG_NTC_SYNC_OPTS_2}</p>
				<input type="hidden" name="save_sync_opts" value="1">
				{$IMPORT_TITLE}
				<table width="100%" border="0" cellpadding="2" cellspacing="0">
				<tr><td class="tabDetailViewDF" width="5%">
					<input type="hidden" name="Import_Customers" value="0">
					<input type="checkbox" class="checkbox" name="Import_Customers" id="chkImport_Customers" value="1" onclick="updateSyncOpts(this.form);" {$IMPORT_CUSTOMERS_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_IMPORT_QB_CUSTOMERS}
				</td></tr>
				<tr><td class="tabDetailViewDF" width="5%">
					<input type="hidden" name="Import_Vendors" value="0">
					<input type="checkbox" class="checkbox" name="Import_Vendors" id="chkImport_Vendors" value="1" onclick="updateSyncOpts(this.form);" {$IMPORT_VENDORS_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_IMPORT_QB_VENDORS}
				</td></tr>
				<tr><td width="2%" class="tabDetailViewDF">
					<input type="hidden" name="Import_Products" value="0">
					<input type="checkbox" class="checkbox" name="Import_Products" id="chkImport_Products" value="1" onclick="updateSyncOpts(this.form);" {$IMPORT_PRODUCTS_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_IMPORT_QB_PRODUCTS}
				</td></tr>
				<tr><td class="tabDetailViewDF">
					<input type="hidden" name="Import_Estimates" value="0">
					<input type="checkbox" class="checkbox" name="Import_Estimates" id="chkImport_Estimates" value="1" {$IMPORT_ESTIMATES_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_IMPORT_QB_ESTIMATES}
				</td></tr>
				<tr><td class="tabDetailViewDF">
					<input type="hidden" name="Import_Invoices" value="0">
					<input type="checkbox" class="checkbox" name="Import_Invoices" id="chkImport_Invoices" value="1" {$IMPORT_INVOICES_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_IMPORT_QB_INVOICES}
				</td></tr>
				<tr><td class="tabDetailViewDF">
					<input type="hidden" name="Import_Bills" value="0">
					<input type="checkbox" class="checkbox" name="Import_Bills" id="chkImport_Bills" value="1" {$IMPORT_BILLS_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_IMPORT_QB_BILLS}
				</td></tr>
				</table>
				
				{$EXPORT_TITLE}
				<table width="100%" border="0" cellpadding="2" cellspacing="0">
				<tr><td class="tabDetailViewDF" width="5%">
					<input type="hidden" name="Export_Customers" value="0">
					<input type="checkbox" class="checkbox" name="Export_Customers" id="chkExport_Customers" value="1" onclick="updateSyncOpts(this.form);" {$EXPORT_CUSTOMERS_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_EXPORT_IAH_CUSTOMERS}
				</td></tr>
				<tr><td class="tabDetailViewDF" width="5%">&nbsp;</td>
					<td class="tabDetailViewDF" width="5%">
					<input type="hidden" name="Export_OnlyInvoicedAccounts" value="0">
					<input type="checkbox" class="checkbox" name="Export_OnlyInvoicedAccounts" id="chkExport_Export_OnlyInvoicedAccounts" value="1" onclick="updateSyncOpts(this.form);" {$EXPORT_ONLYINVOICEDACCOUNTS_CHECKED}>
				</td><td class="tabDetailViewDF">
					{$MOD.LBL_EXPORT_IAH_ASSOC_ACCOUNTS_ONLY}
				</td></tr>
				<tr><td class="tabDetailViewDF" width="5%">
					<input type="hidden" name="Export_Vendors" value="0">
					<input type="checkbox" class="checkbox" name="Export_Vendors" id="chkExport_Vendors" value="1" onclick="updateSyncOpts(this.form);" {$EXPORT_VENDORS_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_EXPORT_IAH_VENDORS}
				</td></tr>
				<tr><td class="tabDetailViewDF" width="5%">&nbsp;</td>
					<td class="tabDetailViewDF" width="5%">
					<input type="hidden" name="Export_OnlyBilledAccounts" value="0">
					<input type="checkbox" class="checkbox" name="Export_OnlyBilledAccounts" id="chkExport_Export_OnlyBilledAccounts" value="1" onclick="updateSyncOpts(this.form);" {$EXPORT_ONLYBILLEDACCOUNTS_CHECKED}>
				</td><td class="tabDetailViewDF">
					{$MOD.LBL_EXPORT_IAH_ASSOC_VENDORS_ONLY}
				</td></tr>
				<tr><td width="2%" class="tabDetailViewDF">
					<input type="hidden" name="Export_Products" value="0">
					<input type="checkbox" class="checkbox" name="Export_Products" id="chkExport_Products" value="1" onclick="updateSyncOpts(this.form);" {$EXPORT_PRODUCTS_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_EXPORT_IAH_PRODUCTS}
				</td></tr>
				<tr><td class="tabDetailViewDF">
					<input type="hidden" name="Export_Quotes" value="0">
					<input type="checkbox" class="checkbox" name="Export_Quotes" id="chkExport_Quotes" value="1" {$EXPORT_QUOTES_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_EXPORT_IAH_QUOTES}
				</td></tr>
				<tr><td class="tabDetailViewDF">
					<input type="hidden" name="Export_Invoices" value="0">
					<input type="checkbox" class="checkbox" name="Export_Invoices" id="chkExport_Invoices" value="1" {$EXPORT_INVOICES_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_EXPORT_IAH_INVOICES}
				</td></tr>
				<tr><td class="tabDetailViewDF">
					<input type="hidden" name="Export_Bills" value="0">
					<input type="checkbox" class="checkbox" name="Export_Bills" id="chkExport_Bills" value="1" {$EXPORT_BILLS_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					{$MOD.LBL_EXPORT_IAH_BILLS}
				</td></tr>
				</table>
				
				{$BATCH_TITLE}
				<table width="100%" border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td class="tabDetailViewDF" width="45%" align="right">
					{$MOD.LBL_BATCH_INVOICES_EXPORT}
					</td>
					<td class="tabDetailViewDF" width="5%">
						<input type="text" size="5"  name="Batch_Invoices/export" value="{$BATCH.Invoices.export}">
					</td>
					<td class="tabDetailViewDF" width="45%" align="right">
					{$MOD.LBL_BATCH_INVOICES_IMPORT}
					</td>
					<td class="tabDetailViewDF" width="5%">
						<input type="text" size="5"  name="Batch_Invoices/import" value="{$BATCH.Invoices.import}">
					</td>
				</tr>
				<tr>
					<td class="tabDetailViewDF" width="45%" align="right">
					{$MOD.LBL_BATCH_PAYMENTS_EXPORT}
					</td>
					<td class="tabDetailViewDF" width="5%">
						<input type="text" size="5"  name="Batch_Payments/export" value="{$BATCH.Payments.export}">
					</td>
					<td class="tabDetailViewDF" width="45%" align="right">
					{$MOD.LBL_BATCH_PAYMENTS_IMPORT}
					</td>
					<td class="tabDetailViewDF" width="5%">
						<input type="text" size="5"  name="Batch_Payments/import" value="{$BATCH.Payments.import}">
					</td>
				</tr>
				<tr>
					<td class="tabDetailViewDF" width="45%" align="right">
					{$MOD.LBL_BATCH_ACCOUNTS_EXPORT}
					</td>
					<td class="tabDetailViewDF" width="5%">
						<input type="text" size="5"  name="Batch_Accounts/export" value="{$BATCH.Accounts.export}">
					</td>
					<td class="tabDetailViewDF" width="45%" align="right">
					{$MOD.LBL_BATCH_ACCOUNTS_IMPORT}
					</td>
					<td class="tabDetailViewDF" width="5%">
						<input type="text" size="5"  name="Batch_Accounts/import" value="{$BATCH.Accounts.import}">
					</td>
				</tr>
				<tr>
					<td class="tabDetailViewDF" width="45%" align="right">
					{$MOD.LBL_BATCH_PRODUCTS_EXPORT}
					</td>
					<td class="tabDetailViewDF" width="5%">
						<input type="text" size="5"  name="Batch_Products/export" value="{$BATCH.Products.export}">
					</td>
					<td class="tabDetailViewDF" width="45%" align="right">
					{$MOD.LBL_BATCH_PRODUCTS_IMPORT}
					</td>
					<td class="tabDetailViewDF" width="5%">
						<input type="text" size="5"  name="Batch_Products/import" value="{$BATCH.Products.import}">
					</td>
				</tr>
				</table>

				{$CONFIRM_TITLE}
				<table width="100%" border="0" cellpadding="2" cellspacing="0">
				<tr><td class="tabDetailViewDF" width="5%">
					<input type="hidden" name="Server_allow_sync" value="0">
					<input type="checkbox" class="checkbox" name="Server_allow_sync" id="chkServer_allow_sync" value="1" {$SERVER_ALLOW_SYNC_CHECKED}>
				</td><td colspan="2" class="tabDetailViewDF">
					<b>{$MOD.LBL_CFG_ALLOW_SYNC}</b>
				</td></tr>
				</table>
			{elseif $NAV.current == 'Currencies'}
				<p>{$MOD.LBL_CFG_NTC_CURRENCIES_1}</p>
			{/if}
			{$BODY}
			{$FOOT}
			&nbsp;
		</slot></td>
	</tr>
</form>
</table>
</div>

<!--
<script type="text/javascript" language="JavaScript">
    Calendar.setup ({ldelim}
	inputField : "jscal_field", ifFormat : "%Y-%m-%d", showsTime : false, button : "jscal_trigger", singleClick : true, step : 1
    {rdelim});
    Calendar.setup ({ldelim}
	inputField : "jscal_field2", ifFormat : "%Y-%m-%d", showsTime : false, button : "jscal_trigger2", singleClick : true, step : 1
    {rdelim});
</script>
-->

<script type="text/javascript"><!--
	updateSyncOpts(document.forms.QBLConfigForm);
// --></script>
