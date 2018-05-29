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

 if(!defined('sugarEntry'))define('sugarEntry', true);


require_once('include/entryPoint.php');

$site_url = AppConfig::site_url();
$status_url = $site_url . 'index.php?module=QBLink&action=Status&record=0';

global $current_language;
$mod_strings = return_module_language($current_language, 'QBLink');

echo <<<EOH
<html>
<head>
	<title>{$mod_strings['LBL_SUPPORT_TITLE']}</title>
	<meta http-equiv="Refresh" content="5;$status_url" />
</head>
<body>
	<p>{$mod_strings['LBL_REDIRECT_MESSAGE']}</p>
	<p><a href="$status_url">{$mod_strings['LBL_REDIRECT_MESSAGE_2']}</a></p>
</body>
</html>
EOH;

?>
