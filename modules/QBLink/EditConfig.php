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


require_once('modules/QBLink/QBConfig.php');
require_once('include/Sugar_Smarty.php');

global $app_strings, $mod_strings;

if(!is_admin($current_user))
	sugar_die("Only administrator users are allowed to access this page.");

$cfg = new QBConfig();
$tpl = new Sugar_Smarty();

$tpl->assign('MOD', $mod_strings);
$tpl->assign('APP', $app_strings);
$tpl->assign('GRIDLINE', $current_user->getPreference('gridline'));

$cur_step = array_get_default($_REQUEST, 'step', '');

$errs = array();

if(! empty($_REQUEST['save_step'])) {
	$from_step = array_get_default($_REQUEST, 'from_step', '');
	if(! $from_step)
		sugar_die("Missing step ID");
	$status = $cfg->save_setup_step($from_step);
	if($status != 'ok') {
		$errs += $cfg->errors;
		$cur_step = $from_step;
	}
	if(empty($cur_step)) {
		header('Location: index.php?module=QBLink&action=Status');
		return;
	}
}

if(! $cfg->init_setup_step($cur_step))
	$errs += $cfg->errors;

$tpl->assign('TITLE', get_module_title($mod_strings['LBL_CFG_TITLE'], $mod_strings['LBL_CFG_TITLE'].": ".$cfg->get_step_name(), true));

if(! $cfg->setup_template($tpl))
	$errs += $cfg->errors;
	
$tpl->assign("THEME", $GLOBALS['theme']);
$tpl->assign("USER_DATEFORMAT", '('. $timedate->get_user_date_format().')');
$tpl->assign("CALENDAR_DATEFORMAT", $timedate->get_cal_date_format());

$tpl->assign('ERRORS', $errs);
$tpl->display('modules/QBLink/config.tpl');

?>
