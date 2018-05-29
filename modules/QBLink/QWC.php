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
require_once('modules/QBLink/qb_utils.php');

$site_url = AppConfig::setting('site.base_url');
$ssl_url = AppConfig::setting('site.ssl_url');
if (!empty($ssl_url)) // allow override
	$site_url = $ssl_url;
if(! preg_match('/^https:/', $site_url)) {
	sugar_die($mod_strings['LBL_QWC_NO_SSL_SUPPORT']);
}

$qb_config = new QBConfig();

$qwc_app_id = qblink_app_id();
$qwc_filename = 'InfoAtHand-QBLink.qwc';
$qwc_app_url = $site_url.'/qblink.php';
$qwc_support = $site_url.'/qbsupport.php';
$qwc_username = '';
$qwc_owner_id = $qb_config->get_owner_id();
$qwc_file_id = create_qb_guid();

$qwc_server_id = '';
if(! empty($_REQUEST['server_id'])) {
	require_once('modules/QBLink/QBServer.php');
	$qbs = new QBServer();
	if($qbs->retrieve($_REQUEST['server_id'])) {
		if(! empty($qbs->qb_owner_id))
			$qwc_owner_id = $qbs->qb_owner_id;
		if(! empty($qbs->qb_file_id))
			$qwc_file_id = $qbs->qb_file_id;
		$qwc_server_id = $qbs->id;
	}
}

if(empty($_REQUEST['user_name'])) {
	global $db;
	$r = $db->query("SELECT id,user_name FROM users WHERE is_admin AND NOT deleted AND status='Active'", true);
	$unames = array();
	while($row = $db->fetchByAssoc($r)) {
		$unames[$row['user_name']] = $row['user_name'];
	}
	if(count($unames) == 1) {
		$qwc_username = current($unames);
	}
	else {
		$ret_act = array_get_default($_REQUEST, 'return_action', 'Status');
		echo '<table class="detailView"><tr><td>'.
		'<h3 class="detailView">'.$mod_strings['LBL_QWC_SELECT_USER'].'</h3><br>'.
		'<form action="index.php" method="GET">'.
		'<input name="module" value="QBLink" type="hidden">'.
		'<input name="action" value="QWC" type="hidden">'.
		'<input name="server_id" value="'.$qwc_server_id.'" type="hidden">'.
		'<select name="user_name">'.
			get_select_options_with_id($unames, 'admin').
		'</select><br>'.
		'<input type="submit" value="'.$mod_strings['LBL_QWC_SELECT_DOWNLOAD'].'">'.
		'</form><br>'.
		'<a href="index.php?module=QBLink&action='.$ret_act.'">'.$app_strings['LBL_RETURN'].'</a>'.
		'</td></tr></table>';
		return;
	}
}
else
	$qwc_username = $_REQUEST['user_name'];

if(! strlen($qwc_username))
	sugar_die('No user name provided, or could not locate an admin user.');

$params = array(
	'AppName' => $mod_strings['LBL_QWC_APPNAME'] . ' ' . qblink_module_major_minor_version(),
	'AppID' => $qwc_app_id,
	'AppDescription' => $mod_strings['LBL_QWC_APPDESCRIPTION'],
	'AppSupport' => $qwc_support,
	'UserName' => $qwc_username,
	'AppURL' => $qwc_app_url,
	'OwnerID' => $qwc_owner_id,
	'FileID' => $qwc_file_id,
	'QBType' => 'QBFS',
	'Style' => 'rpc',
	// auto-run not desired before configuration has been performed
	//'Scheduler' => array('RunEveryNMinutes' => 5),
);

require_once('modules/QBLink/QBXMLParser.php');
$parser = new QBXMLParser();

$params_xml = $parser->encode_params($params);

$qwc_data = '<' . <<<EOX
?xml version="1.0" encoding="utf-8" ?>
<QBWCXML>
	$params_xml
</QBWCXML>
EOX;

header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Type: application/force-download");
header("Content-Length: " . strlen($qwc_data));
header("Content-Disposition: attachment; filename=\"".$qwc_filename."\";");
header("Expires: 0");

@ob_end_clean();
print $qwc_data;
exit(0);

?>
