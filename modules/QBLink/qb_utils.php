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



global $qb_cfg_cache, $qb_cache;
$qb_cfg_cache = $qb_cache = array();

function qblink_module_version() {
	return '3.2.9';
}
function qblink_module_major_minor_version() {
	return substr(qblink_module_version(), 0, 3);
}

function qb_check_debug_enabled() {
	$enabled = AppConfig::setting('site.qblink_debug_enabled');
	if(! defined('QBLINK_DEBUG') && $enabled)
		define('QBLINK_DEBUG', true);
}

function qb_cfg_loaded($server_id, $category=null) {
	global $qb_cfg_cache;
	if(! isset($category))
		return isset($qb_cfg_cache[$server_id]);
	return isset($qb_cfg_cache[$server_id]) && isset($qb_cfg_cache[$server_id][$category]);
}
function qb_put_cfg_cache($server_id, $category, $name, &$val) {
	global $qb_cfg_cache;
	$qb_cfg_cache[$server_id][$category][$name] =& $val;
}
function qb_put_cache($server_id, $category, $name, &$val) {
	global $qb_cache;
	$qb_cache[$server_id][$category][$name] =& $val;
}
function &qb_get_cfg_cache($server_id, $category, $name) {
	global $qb_cfg_cache;
	if(isset($qb_cfg_cache[$server_id]) && isset($qb_cfg_cache[$server_id][$category]) && isset($qb_cfg_cache[$server_id][$category][$name]))
		return $qb_cfg_cache[$server_id][$category][$name];
	$ret = null;
	return $ret;
}
function qb_get_cache_group($server_id, $category=null) {
	global $qb_cfg_cache;
	if(! isset($qb_cfg_cache[$server_id]))
		return array();
	if(! isset($category))
		return $qb_cfg_cache[$server_id];
	if(isset($qb_cfg_cache[$server_id][$category]))
		return $qb_cfg_cache[$server_id][$category];
	return array();
}
function &qb_get_cache($server_id, $category, $name) {
	global $qb_cache;
	if(isset($qb_cache[$server_id]) && isset($qb_cache[$server_id][$category]) && isset($qb_cache[$server_id][$category][$name]))
		return $qb_cache[$server_id][$category][$name];
	$ret = null; //qb_get_cfg_cache($server_id, $category, $name);
	return $ret;
}
function qb_reset_cfg_cache($server_id=null, $category=null) {
	global $qb_cfg_cache;
	if(isset($server_id)) {
		if(isset($category))
			$qb_cfg_cache[$server_id][$category] = array();
		else
			$qb_cfg_cache[$server_id] = array();
	}
	else
		$qb_cfg_cache = array();
}
function qb_reset_cache($server_id=null, $category=null) {
	global $qb_cache;
	if(isset($server_id)) {
		if(isset($category))
			$qb_cache[$server_id][$category] = array();
		else
			$qb_cache[$server_id] = array();
	}
	else
		$qb_cache = array();
}

function qblink_app_id() {
	return 'info@hand-finance';
}

function iah_std_owner_id() {
	return '{CCFB6761-8558-76F0-92ED-D5D7846FACDF}';
}

function create_qb_guid() {
	// create guid in the format {XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX}
	$lens = array(8, 4, 4, 4, 12);
	$pcs = array_map('create_guid_section', $lens);
	return '{' . strtoupper(implode('-', $pcs)) . '}';
}

function qb_log_format($pfx, &$val) {
	if(is_array($val) || is_object($val)) {
		$lines = explode("\n", print_r($val, true));
		$ret = '';
		foreach($lines as $l)
			if(strlen($l)) {
				if($ret) $ret .= "\n";
				$ret .= $pfx . $l;
			}
		return $ret;
	}
	return $pfx . $val;
}

function qb_parse_bool($val) {
	if($val === 'true')
		return 1;
	if($val === 'false')
		return 0;
	return null;
}

function qb_format_price($val) {
	return sprintf('%.2f', $val);
}

function qb_export_address($server_id, &$focus, $prefix) {
	$edition = QBServer::get_server_edition($server_id);
	if($edition == 'US')
		$state_f = 'State';
	else if($edition == 'UK')
		$state_f = 'County';
	else
		$state_f = 'Province';
	$ret = array();
	$street_f = $prefix . 'street';
	$street = explode("\n", $focus->$street_f);
	$i = 1;
	foreach($street as $line) {
		$line = trim($line);
		if($line === '')
			continue;
		$ret['Addr' . $i] = $line;
		if(++$i > 4)
			break;
	}
	$map = array('city' => 'City', 'state' => $state_f, 'postalcode' => 'PostalCode', 'country' => 'Country');
	foreach($map as $f => $qb_f) {
		$f = $prefix . $f;
		$ret[$qb_f] = $focus->$f;
	}
	return $ret;
}

function qb_import_address($server_id, &$focus, $detail, $prefix) {
	$edition = QBServer::get_server_edition($server_id);
	if($edition == 'US')
		$state_f = 'State';
	else if($edition == 'UK')
		$state_f = 'County';
	else
		$state_f = 'Province';
	$street = '';
	for($i = 1; $i <= 5; $i++) {
		$line = trim(array_get_default($detail, 'Addr'.$i, ''));
		if(strlen($street))
			$street .= "\n";
		$street .= $line;
	}
	$f = $prefix.'street';
	if ($focus instanceof RowUpdate)
		$focus->set($f, $street);
	else
		$focus->$f = $street;
	$map = array('City' => 'city', $state_f => 'state', 'PostalCode' => 'postalcode', 'Country' => 'country');
	foreach($map as $qb_f => $f) {
		$f = $prefix . $f;
		if ($focus instanceof RowUpdate)
			$focus->set($f, array_get_default($detail, $qb_f, ''));
		else
			$focus->$f = array_get_default($detail, $qb_f, '');
	}
	return true;
}

function qb_cmp_names($a, $b) {
	$r = strcasecmp(qb_asciify($a), qb_asciify($b));
	if($r == 0) return strcasecmp($a, $b);
	return $r;
}

function qb_asciify($str) {
	static $accented, $ascii;
	if(! isset($accented)) {
		// thx http://www.randomsequence.com/articles/removing-accented-utf-8-characters-with-php/
		$from = "ç,æ,½,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u";
		$to = "c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u";
		$accented = explode(",","$from,".strtoupper($from));
		$ascii = explode(",","$to,".strtoupper($to));
	}
	$str = str_replace($accented, $ascii, $str);
	return $str;
}

function &qb_asciify_string($enc, $qbxml) {	
	$std_enc = 'iso-8859-1';
	if(function_exists('mb_convert_encoding'))
		$qbxml = mb_convert_encoding($qbxml, $std_enc, 'utf-8');
	
	$qbxml = qb_asciify($qbxml);

	$qbxml2 = '';
	for($i = 0; $i < strlen($qbxml); $i++) {
		$c = substr($qbxml, $i, 1);
		$qbxml2 .= (ord($c) > 125) ? '?' : $c;
	}
	$qbxml = $qbxml2;
	if(function_exists('mb_convert_encoding') && $enc != $std_enc)
		$qbxml = mb_convert_encoding($qbxml, $enc, $std_enc);
	return $qbxml;
}

function qb_import_date_time($dt, $to_display=true) {
	static $pat = '/(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:(\d{2}))(?:([+-]?\d{2}):(\d{2}))?/';
	if(preg_match($pat, $dt, $m)) {
		$db_dt = $m[1].' '.$m[2];
		$hours_offs = - (int)$m[4];
		$mins_offs = (int)$m[5];
		$offset = ($hours_offs * 60 + $mins_offs) * 60;
		if(date('I')) // server in DST
			$offset -= 3600;
		$seconds = (int)$m[3];
		if($seconds) // round down
			$offset -= $seconds;
		$tm = strtotime($db_dt) + $offset;
		return qb_date_time($tm, $to_display, true);
	}
	return null;
}

function qb_import_date($dt, $to_display=true) {
	global $timedate, $disable_date_format;
	if(! $to_display || ! empty($disable_date_format))
		$ret = $dt;
	else
		$ret = $timedate->to_display_date($dt, false);
	//qb_log_debug("QB_DATE_IMPORT $dt -> $ret");
	return $ret;
}

function qb_export_date($dt) {
	global $timedate, $disable_date_format;
	if(! empty($disable_date_format))
		$ret = $dt;
	else
		$ret = $timedate->to_db_date($dt, false);
	return $ret;
}

function qb_export_date_time($dt, $from_display=true) {
	if(! $dt)
		return '';
	global $timedate, $disable_date_format;
	if($from_display && empty($disable_date_format))
		$dt = $timedate->to_db($dt);
	list($day,$tm) = explode(' ', $dt);
	if(date('I')) // server in DST
		$offset = '-01:00';
	else
		$offset = '+00:00';
	$out = $day.'T'.$tm.$offset;
	return $out;
}

function qb_export_name(&$server, $str) {
	// colon is not allowed in QB object names
	$new_s = preg_replace('/:\s?/', ' - ', $new_s);
	if(! empty($server->asciify_text))
		$new_s = qb_asciify_string($server->qbxml_encoding, $new_s);
	return $new_s;
}

function cmp_currency_ids($id1, $id2) {
	if(empty($id1)) $id1 = '-99';
	if(empty($id2)) $id2 = '-99';
	return $id1 == $id2;
}

function qb_date_last_sync($display=true) {
	$now = time();
	// round up to next minute
	if( ($ext = $now % 60) ) {
		$now += 60 - $ext;
	}
	$dt = qb_date_time($now, $display);
	qb_log_info("LAST SYNC $dt");
	return $dt;
}

function qb_date_time($time=null, $display=true, $from_gmt=false) {
	global $disable_date_format, $timedate;
	if(is_null($time)) {
		$time = time();
		$from_gmt = false;
	}
	if($from_gmt)
		$tm = date($timedate->get_db_date_time_format(), $time);
	else
		$tm = gmdate($timedate->get_db_date_time_format(), $time);
	if(! $display || ! empty($disable_date_format)) {
		return $tm;
	}
	$dt = $timedate->to_display_date_time($tm);
	return $dt;
}

function qb_match_up_html($field_id, $src_items, $targ_items, $map_opts, $allow_none=true, $sel_attrs='') {
	global $mod_strings;
	$html = '<table width="100%" border="0" cellpadding="0" cellspacing="2">';
	$opts = array();
	if($allow_none) {
		$none = 'LBL_CFG_'.strtoupper($field_id).'_UNMAPPED';
		if(isset($mod_strings[$none]))
			$none = $mod_strings[$none];
		else
			$none = '';	
		$opts[''] = $none;
	}
	
	if(is_string($map_opts)) {
		$map = array();
		foreach($src_items as $k=>$bean) {
			if(is_object($bean))
				$map[$bean->id] = $bean->$map_opts;
			if(is_array($bean)) {
				$idx = isset($bean['id']) ? $bean['id'] : $k;
				$map[$idx] = array_get_default($bean, $map_opts);
			}
		}
	}
	else
		$map = $map_opts;
		
	foreach($targ_items as $k=>$opt) {
		if(is_object($opt)) {
			if(method_exists($opt, 'get_option_name'))
				$nm = $opt->get_option_name();
			else
				$nm = $opt->name;
			$opts[$opt->id] = $nm;
		}
		elseif(is_array($opt)) {
			$idx = isset($opt['id']) ? $opt['id'] : $k;
			$opts[$idx] = $opt['name'];
		}
		else
			$opts[$k] = $opt;
	}
	
	if(! count($src_items))
		return '';
	
	foreach($src_items as $k=>$bean) {
		$selid = '';
		if(is_object($bean)) {
			if(method_exists($bean, 'get_option_name'))
				$name = $bean->get_option_name();
			else
				$name = $bean->name;
			$idx = $bean->id;
			if(isset($map[$idx]))
				$selid = $map[$idx];
			elseif(isset($map[$bean->qb_id]))
				$selid = $map[$bean->qb_id];
		}
		else if(is_array($bean)) {
			$name = $bean['name'];
			if(isset($bean['id']))
				$idx = $bean['id'];
			else
				$idx = $k;
			$idx2 = array_get_default($bean, 'qb_id');
			if(isset($map[$idx]))
				$selid = $map[$idx];
			elseif(isset($idx2) && isset($map[$idx2]))
				$selid = $map[$idx2];
		}
		else {
			$name = $bean;
			$idx = $k;
			if(isset($map[$idx]))
				$selid = $map[$idx];
		}
		$html .= '<tr><td width="40%" class="tabDetailViewDL">'.$name.'</td>';
		$html .= '<td class="tabDetailViewDF">';
		$html .= '<select name="'.$field_id.'_map['.$idx.']" id="'.$field_id.'__'.$idx.'" '.$sel_attrs.'>';
		$html .= get_select_options_with_id($opts, $selid);
		$html .= '</select></td></tr>';
	}
	
	$html .= '</table>';
	return $html;
}

function make_listview_div($id, $msg) {
	$str = '<div id="'.$id.'" style="display: none; width: 200px; border: 1px solid darkgray; background-color: #ffb; position: absolute">';
	$str .= $msg;
	$str .= '</div>';
	return $str;
}

function update_selects_javascript($prefix, $new_value='create_new', $link_text='LBL_CFG_SEL_ALL_IMPORT') {
	global $mod_strings;
	$s = <<<EOS
<script type="text/javascript">
function update_sel(cls) {
	var elts = document.getElementsByTagName('select');
	for(var i = 0; i < elts.length; i++) {
		if(elts[i].id.substr(0, cls.length) == cls) {
			if(! elts[i].value) elts[i].value = '$new_value';}
	}
}
</script>
EOS;
	$s .= '<p align="right">'
		. '<a href="#" onclick="update_sel(\''.$prefix.'\'); return false;">'
		. $mod_strings[$link_text] . '</a></p>';
	return $s;
}


function qb_batch_size($server_id, $type, $direction)
{
	if ($direction != 'export')
		$direction = 'import';
	$def = ($direction == 'import') ? 20 : 20;
	switch ($type) {
		case 'Payments':
		case 'Products':
		case 'Accounts':
			$def = ($direction == 'import') ? 20 : 20;
			break;
	}
	$size = QBConfig::get_server_setting($server_id, "Batch", "$type/$direction", $def);
	if ((int)$size < 1)
		$size = $def;
	return (int)$size;
}

function qb_set_batch_size($server_id, $type, $direction, $size)
{
	if ($direction != 'export')
		$direction = 'import';
	QBConfig::put_server_setting($server_id, "Batch", "$type/$direction", $size);
}

function qb_base_to_batch_type($base)
{
	static $map = array(
		'Estimate' => 'Invoices',
		'Invoice' => 'Invoices',
		'ReceivePayment' => 'Payments',
		'Bill' => 'Invoices',
		'BillPaymentCheck' => 'Payments',
		'BillPaymentCreditCard' => 'Payments',
		'CreditMemo' => 'Invoices',
		'ReceivePayment' => 'Payments',
		'Check' => 'Payments',
		'ARRefundCreditCard' => 'Payments',
		'Customer' => 'Accounts',
		'Vendor' => 'Accounts',
	);

	return array_get_default($map, $base, 'Products');
}

if(! function_exists('qb_log_info')) {
	function qb_log_info($msg) {
		global $log;
		$log->info(qb_log_format('QBLINK ', $msg));
	}
	function qb_log_debug($msg) {
		global $log;
		$log->debug(qb_log_format('QBLINK ', $msg));
	}
	function qb_log_error($msg) {
		global $log;
		$log->fatal(qb_log_format('QBLINK ', $msg));
	}
}

?>
