<?php
/* * 
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
*/

if(!defined('sugarEntry') || !sugarEntry) die('Not A Valid Entry Point'); 


global $mod_strings;
$module_menu = array();

$module_menu[] = array(
	"index.php?module=QBLink&action=Status",
	$mod_strings['LNK_MODULE_STATUS'], "Administration");

$module_menu[] = array(
	"index.php?module=QBLink&action=EditConfig",
	$mod_strings['LNK_MODULE_CONFIG'], "Administration");

/*if (ACLController::checkAccess('Quotes', 'edit', true))*/
$module_menu[] = array(
	"index.php?module=QBLink&action=index&list_type=Entity",
	$mod_strings['LNK_ENTITY_LIST'], "Accounts");

$module_menu[] = array(
	"index.php?module=QBLink&action=index&list_type=Item",
	$mod_strings['LNK_ITEM_LIST'], "ProductCatalog");

$module_menu[] = array(
	"index.php?module=QBLink&action=index&list_type=Tally",
	$mod_strings['LNK_ESTIMATE_LIST'], "Quotes");

?>
