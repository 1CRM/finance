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
require_once('modules/QBLink/QBServer.php');
require_once('modules/QBLink/qb_utils.php');

echo get_module_title('QBLink', $mod_strings['LBL_MODULE_TITLE'].': '.$mod_strings['LNK_MODULE_STATUS'], true);

if(isset($_REQUEST['save_ssl_url'])) {
	$upd = array('site.ssl_url' => $_REQUEST['save_ssl_url']);
	AppConfig::update_local($upd, true);
	AppConfig::save_local();
}

function add_config_link($str) {
	$str = preg_replace('/\{\{([^\}]+)\}\}/', '<a href="index.php?module=QBLink&action=EditConfig" class="tabDetailViewDFLink">\1</a>', $str);
	return $str;
}
function led($color) {
	$str = '<img src="include/images/iah/'.$color.'led.gif" title="" width="12" height="12" style="vertical-align: baseline">';
	return $str;
}
function last_sync_result(&$srv) {
	global $app_list_strings, $mod_strings;
	switch($srv->last_sync_result) {
		case '': $color = 'grey'; break;
		case 'aborted': $color = 'red'; break;
		case 'success': $color = 'green'; break;
		default: $color = 'yellow';
	}
	$status = $app_list_strings['qb_sync_result_dom'][$srv->last_sync_result];
	$str = '<p>'.led($color).' ';
	$str .= $mod_strings['LBL_LAST_CONNECT'].' '.$srv->last_connect;
	$str .= ", $status.";
	if($srv->last_sync_msg)
		$str .= '<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$srv->last_sync_msg;
	$str .= '</p>';
	return $str;
}
$cfg = new QBConfig();
$srv = new QBServer();

?>
<table width="100%" border="0" class="tabForm" cellpadding="0" cellspacing="0">
<tr><td class="dataLabel">
<?php

$srv_loaded = $srv->retrieve_primary();
if(! $srv_loaded)
	if( ($srv_alt_id = $srv->get_last_connect_server_id()) )
		$srv_alt = $srv->retrieve($srv_alt_id);
if(! $srv_loaded && empty($srv_alt)) {
	echo '<p>'.led('red').' ';
	echo add_config_link($mod_strings['LBL_STATUS_NOT_CONFIGURED']).'</p>';
}
else if(! $srv_loaded || ! QBServer::get_sync_enabled($srv->id)) {
	$val = QBConfig::get_server_setting($srv->id, 'Server', 'allow_sync');
	echo '<p>'.led('yellow').' ';
	echo add_config_link($mod_strings['LBL_STATUS_NO_SYNC']).'</p>';
	echo last_sync_result($srv);
}
else {
	echo '<p>'.led('green').' ';
	echo add_config_link($mod_strings['LBL_STATUS_CONFIGURED']).'</p>';
	echo last_sync_result($srv);
}

$ssl_url = AppConfig::setting('site.ssl_url');
if(empty($ssl_url)) {
	$ssl_url = AppConfig::setting('site.base_url');
	if(strtolower(substr($ssl_url, 0, 5)) == 'http:')
		$ssl_url = 'https:' . substr($ssl_url, 5);
	$unverif = '<span class="error">'.$mod_strings['LBL_STATUS_SSL_URL_UNVERIFIED'].'</span>';
}
else $unverif = '';

?>
</td></tr>
<tr><td class="dataLabel">
	<p><?php echo $mod_strings['LBL_STATUS_SSL_URL_DESC']; ?><br>
	<form action="index.php" method="POST">
	<input type="hidden" name="module" value="QBLink" />
	<input type="hidden" name="action" value="Status" />
	<?php echo $mod_strings['LBL_STATUS_SSL_URL']; ?>
	<input type="text" size="30" name="save_ssl_url" value="<?php echo htmlentities($ssl_url); ?>" /> <?php echo $unverif; ?>
	<br><input type="submit" name="submit" class="button" value="<?php echo $app_strings['LBL_UPDATE']; ?>" />
	</form>
	</p>
	<p><?php echo $mod_strings['LBL_QWC_DOWNLOAD_1']; ?><br>
	<a href="index.php?module=QBLink&action=QWC" class="tabDetailViewDFLink"><?php echo $mod_strings['LBL_QWC_DOWNLOAD_2']; ?></a>
	</p>
	<p><?php echo $mod_strings['LBL_QWC_DOWNLOAD_3']; ?>
	<a href="http://marketplace.intuit.com/webconnector/" target="_blank">http://marketplace.intuit.com/webconnector/</a>
	</p>
</td></tr>
</table>
