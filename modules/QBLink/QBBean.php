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


require_once('data/SugarBean.php');
require_once('include/TimeDate.php');
require_once('modules/QBLink/qb_utils.php');
require_once('modules/QBLink/QBServer.php');



/*abstract*/ class QBBean extends SugarBean {
	var $id;
	var $qb_id;
	var $qb_editseq;
	var $server_id;
	var $qb_is_active;
	var $qb_date_entered;
	var $qb_date_modified;
	
	//
	var $export_error;
	var $last_import_name;
	var $date_last_sync;
	
	var $std_import_field_map = array(
		'Name' => 'name',
		'FullName' => 'name',
		'TimeCreated' => 'qb_date_entered',
		'TimeModified' => 'qb_date_modified',
		'IsActive' => 'qb_is_active',
	);
	var $qb_field_map = array();
	
	var $processed = true; // disable all workflow for this object
	
	var $qb_temporary_errors = array(
		3175, // Object is in use
		3176, // Related object is in use
		3200, // Outdated edit sequence
	);
	
	function __construct() {
		parent::__construct();
		//$this->conn =& QBConnector::get_instance();
	}
	
	// based on SugarBean::retrieve() - can't reuse standard implementation
	function &qb_retrieve($qb_id, $server_id, $encode=true, $require_synced=false) {
		$query = "SELECT {$this->table_name}.* FROM $this->table_name ".
			"WHERE qb_id='". $this->db->quote($qb_id) ."' ".
			"AND server_id='". $this->db->quote($server_id) ."' ".
			"AND NOT deleted ";
		if($require_synced)
			$query .= " AND (first_sync IN ('imported', 'exported') OR sync_status IN ('pending_import', 'import_error')) ";
		$result = $this->db->limitQuery($query,0,1,true, "Retrieving record by id {$this->table_name}:$qb_id found ");
		if(empty($result)) {
			$ret = null;
			return $ret;
		}

		$row = $this->db->fetchByAssoc($result, -1, $encode);
		if(empty($row)) {
			$ret = null;
			return $ret;
		}

		//make copy of the fetched row for construction of audit record and for business logic/workflow
		$this->fetched_row=$row;

		$this->populateFromRow($row);

		$this->processed_dates_times = array();
		$this->check_date_relationships_load();

		$this->fill_in_additional_detail_fields();

		//make a copy of fields in the relationship_fields array. these field values will be used to
		//clear relationship.
    	if (isset($this->relationship_fields) && is_array($this->relationship_fields)) {
    		foreach ($this->relationship_fields as $rel_id=>$rel_name) {
    			if (isset($this->$rel_id))
					$this->rel_fields_before_value[$rel_id]=$this->$rel_id;
				else
					$this->rel_fields_before_value[$rel_id]=null;
    		}
    	}
    	
    	return $this;
	}
	
	
	function check_unique() {
		if(empty($this->qb_id) || empty($this->server_id)) {
			return false;
		}
		$qb_id = $this->db->quote($this->qb_id);
		$sv_id = $this->db->quote($this->server_id);
		$query = "SELECT qb_id FROM $this->table_name WHERE qb_id='$qb_id' AND server_id='$sv_id' AND NOT deleted LIMIT 1";
		$result = $this->db->query($query, true);
		if($this->db->getRowCount($result)) {
			$t = isset($this->qb_type) ? $this->qb_type : $this->object_name;
			qb_log_debug("object previously retrieved: $t $qb_id");
			return false;
		}
		return true;
	}
	
	
	function phase_can_begin($server_id, $phase) {
		return true;
	}
	
	function phase_get_percent_complete($server_id, $phase, $stage, $all_stages) {
		// may wish to calculate percentage for each stage
		$pos = array_search($stage, $all_stages);
		if($pos === false) return 0;
		return $pos * 100 / count($all_stages);
	}
	
	function handle_response($server_id, &$req, &$data, &$newreqs) {
		switch($req->request_type) {
			case 'import':
			case 'import_all':
			case 'import_updated':
				$pass = 'handle_import_response';
				break;
			case 'export':
			case 'update':
				$pass = 'handle_export_update_response';
				break;
			case 'delete':
				$pass = 'handle_delete_response';
				break;
			default:
				qb_log_error("UNHANDLED ".$req->request_type);
				return false;				
		}
		return $this->$pass($server_id, $req, $data, $newreqs);
	}
	
	
	function handle_import_response($server_id, &$req, &$data, &$newreqs) {
		$old = array();
		if($req->request_type == 'import_all') {
			$q = "SELECT id, qb_id FROM {$this->table_name} ".
					' WHERE server_id="'. $this->db->quote($server_id).'" '.
					' AND NOT deleted AND qb_is_active';
			$r = $this->db->query($q, true, "Error retrieving existing object IDs");
			
			while($row = $this->db->fetchByAssoc($r)) {
				$old[$row['qb_id']] = $row['id'];
			}
		}
		
		$tname = $this->object_name;
		foreach(array_keys($data) as $k) {
			qb_log_debug("handle_import_response $k");
			if(! is_array($data[$k]) || ! preg_match('/(.*)Ret$/', $k, $m)) {
				qb_log_error("Unexpected element in {$req->handler} data: $k");
				return false;
			}
			$rows =& $data[$k];
			$qb_type = $m[1];
			qb_log_debug('processing '.count($rows).' rows of type '.$qb_type);
			for($i = 0; $i < count($rows); $i++) {
				$req->last_import_count ++; // count of returned rows, not just successful imports
				$bean = new $this->object_name();
				$ok = $bean->handle_import_row($server_id, $qb_type, $req, $rows[$i], $newreqs);
				if($ok) {
					if(isset($old[$bean->qb_id]))
						unset($old[$bean->qb_id]);
				}
				if(! empty($bean->last_import_name))
					$this->update_last_import_name($bean->last_import_name);
				if(($ok || $bean->sync_status) && empty($bean->prevent_save))
					$id = $bean->save(); // save anyway
			}
		}
		
		if($req->request_type == 'import_all' && count($old) && empty($this->disable_import_cleanup)) {
			$q = "UPDATE {$this->table_name} SET qb_is_active=0, deleted=1 WHERE id IN ('".implode("','", array_values($old))."')";
			$r = $this->db->query($q, true, "Error marking objects inactive");
		}
		return true;
	}
	
	
	function handle_export_update_response($server_id, &$req, &$data, &$newreqs) {
		$k = key($data);
		qb_log_debug("handle_export_update_response $k");
		if(! is_array($data[$k]) || ! preg_match('/(.*)Ret$/', $k, $m)) {
			qb_log_error("Unexpected element in {$req->handler} data: $k");
			return false;
		}
		$qb_type = $m[1];
		$bean = new $this->object_name();
		$row =& $data[$k][0];
		//qb_log_debug($req);
		if($req->related_id && $bean->retrieve($req->related_id, false)) {
			//qb_log_debug("loaded bean: $req->related_id $bean->name");
			if(! ($qb_id = $this->get_detail_qb_id($row))) {
				qb_log_error("Could not determine ID of exported/updated object");
				return false;
			}
			$bean->qb_id = $qb_id;
			$ok = $bean->qb_handle_update($qb_type, $req, $row, $newreqs);
			if($ok) {
				if($bean->sync_status == 'pending_export'
				|| (($bean->sync_status == 'pending_update' || !$bean->sync_status) && ! $bean->first_sync)) {
					$bean->first_sync = 'exported';
				}
				$bean->sync_status = '';
			}
			else {
				if(defined('QBLINK_DEBUG') && file_exists("qblink_sent.xml"))
					copy("qblink_sent.xml", "qblink_error_upd.xml");
			}
			$id = $bean->save();
			return $ok;
		}
		return false;
	}
	
	
	function handle_delete_response($server_id, &$req, &$data, &$newreqs) {
		$bean = new $this->object_name();
		if($req->related_id && $bean->retrieve($req->related_id)) {
			qb_log_debug("Confirmed delete for {$bean->object_name} object: {$bean->name}");
			$bean->first_sync = 'deleted';
			$bean->sync_status = 'disabled';
			$bean->status_msg = '';
			$bean->save();
			return true;
		}
		return false;
	}
	
	
	function get_detail_qb_id(&$row) {
		if(isset($row['ListID']))
			$qb_id = $row['ListID'];
		else if(isset($row['TxnID']))
			$qb_id = $row['TxnID'];
		else
			$qb_id = '';
		return $qb_id;
	}
	
	function get_detail_qb_name(&$row) {
		if(isset($row['FullName']))
			$qb_nm = $row['FullName'];
		else if(isset($row['Name']))
			$qb_nm = $row['Name'];
		else
			$qb_nm = '';
		return $qb_nm;
	}
	
	function update_last_import_name($last_name) {
		if(! isset($last_name) || ! strlen($last_name))
			return false;
		//if(empty($this->last_import_name) || qb_cmp_names($last_name, $this->last_import_name) >= 0) {
			qb_log_debug("last import name: $qb_nm");
			$this->last_import_name = $last_name;
		//}
		return true;
	}
	
	
	function handle_import_row($server_id, $qb_type, &$req, &$row, &$newreqs) {
		$qb_nm = $this->get_detail_qb_name($row);
		$this->update_last_import_name($qb_nm);
		if(! ($qb_id = $this->get_detail_qb_id($row))) {
			qb_log_error("Could not determine object ID for import");
			return false;
		}
		qb_log_debug("handle_import_row $qb_type");
		$fdefs = AppConfig::setting("model.fields.{$this->object_name}", array());
		$synced_only = isset($fdefs['first_sync']);
		$found = $this->qb_retrieve($qb_id, $server_id, false, $synced_only);
		$no_sync = array('import_error', 'delete_error', 'disabled');
		if($found) {
			if(in_array($this->sync_status, $no_sync))
				return true;
			$ret = $this->qb_handle_update($qb_type, $req, $row, $newreqs);
		}
		else {
			$ret = $this->qb_handle_import($server_id, $qb_id, $qb_type, $req, $row, $newreqs);
		}
		return $ret;
	}
	
	
	function qb_handle_import($server_id, $qb_id, $qb_type, &$req, &$row, &$newreqs) {
		$new_editseq = array_get_default($row, 'EditSequence');
		$this->server_id = $server_id;
		$this->qb_id = $qb_id;
		$this->qb_editseq = $new_editseq;
		$this->first_sync = 'imported';
		$this->status_msg = '';
		if(! qb_parse_bool(array_get_default($row, 'IsActive', 'true'))) {
			$this->sync_status = 'reg_only';
		} else {
			$this->sync_status = '';
		}
		$addreqs = array();
		$mode = 'import';
		if(! $this->init_sync($mode, $qb_type, $row)) {
			qb_log_error("Failed initializing import for object: $qb_type/$qb_id");
			return false;
		}
		else if(! $this->perform_sync($mode, $qb_type, $row, $errmsg, $addreqs)) {
			$this->first_sync = '';
			$this->sync_status = 'import_error';
			if(isset($errmsg))
				$this->status_msg = $errmsg;
			else if(empty($this->status_msg))
				$this->status_msg = 'Failed extended sync import';
			qb_log_error($this->status_msg);
			return false;
		}
		else if(count($addreqs))
			$newreqs += $addreqs;
		return true;
	}
	
	
	function qb_handle_update($qb_type, &$req, &$row, &$newreqs) {
		$new_editseq = array_get_default($row, 'EditSequence');
		$pend_stat = array('pending_import', 'pending_export',/* 'pending_update'*/);

		if (empty($this->ignore_editseq)) {
			if(! in_array($this->sync_status, $pend_stat) && $new_editseq == $this->qb_editseq) {
				qb_log_debug("Skipping update for object, no change: {$qb_type}/{$this->qb_id}");
				if(isset($row['TimeModified'])) // temp. fix for previous bad imports due to date formatting issues
					$this->qb_date_modified = qb_import_date_time($row['TimeModified']);
				return true;
			}
		}
		$this->old_qb_editseq = $this->qb_editseq;
		$this->qb_editseq = $new_editseq;
		$this->status_msg = '';
		$addreqs = array();
		$mode = ($req->request_type == 'import') ? 'import' :
			(($req->request_type == 'export') ? 'post_export' :
			 ($req->request_type == 'update' ? 'post_iah_update' : 'qb_update'));
		$fail_err = $req->request_type == 'import' ? 'import_error' :
			($req->request_type == 'export' ? 'export_error' : 'update_error');
		if(! $this->init_sync($mode, $qb_type, $row, true)) {
			$this->sync_status = $fail_err;
			$this->status_msg = 'Failed initial sync update';
			qb_log_error($this->status_msg . ' ' . $this->qb_id);
			return false;
		}
		else if(! $this->perform_sync($mode, $qb_type, $row, $errmsg, $addreqs)) {
			$this->sync_status = $fail_err;
			if(isset($errmsg))
				$this->status_msg = $errmsg;
			else if(empty($this->status_msg))
				$this->status_msg = 'Failed extended sync update';
			qb_log_error($this->status_msg . ' ' . $this->qb_id);
			return false;
		}
		else if(count($addreqs)) {
			qb_log_debug("got post-update requests (".count($addreqs).")");
			$newreqs += $addreqs;
		}
		if($this->sync_status == 'pending_import' && $mode == 'import') {
			$this->first_sync = 'imported';
			$this->sync_status = '';
			$this->status_msg = '';
		}
		return true;
	}
	
	
	function init_sync($mode, $qb_type, &$details, $update=false) {
		if($update && isset($this->update_field_map))
			$fieldmap = $this->update_field_map;
		else
			$fieldmap = $this->std_import_field_map;
		if(is_array($this->qb_field_map))
			$fieldmap += $this->qb_field_map;
		return $this->update_from_array($details, $fieldmap);
	}
	
	function perform_sync($mode, $qb_type, &$details, &$errmsg, &$newreqs) {
		return true;
	}
	
	
	function update_from_array(&$details, &$fieldmap) {
		if(! is_array($details) || ! is_array($fieldmap))
			return false;
		foreach($fieldmap as $k => $v) {
			if(isset($details[$k])) {
				$val = $details[$k];
				if(is_array($val) && isset($val['ListID'])) // ParentRef etc.
					$val = $val['ListID'];
				else if($k == 'FullName') {
					if(($p = strrpos($val, ':')) !== false) {
						$val = substr($val, $p + 1);
					}
				}
				else if($k == 'TimeCreated' || $k == 'TimeModified')
					$val = qb_import_date_time($val);
				else if($k == 'IsActive')
					$val = qb_parse_bool($val);
				$this->$v = $val;
			}
		}
		return true;
	}
	
	
	function get_import_request_params($qb_ids, $qb_type) {
		return array(
			'ListID' => $qb_ids,
		);
	}
	
	
	// qb_type is not really optional here
	function add_import_requests($server_id, &$reqs, $max_import=-1, $for_update=false, $qb_type=null) {
		if($for_update) {
			$rows = $this->retrieve_pending_export($server_id, $max_import, true, $qb_type);
		}
		else {
			$rows = $this->retrieve_pending_import($server_id, $max_import, $qb_type);
		}		
		qb_log_debug("adding import requests ($qb_type)".($for_update ? ' for update' : ''));
		$ids = array();
		foreach(array_keys($rows) as $k) {
			$req = null;
			if($rows[$k]->qb_id) {
				if(! $this->session_check_imported_id($rows[$k]->qb_id)) {
					$this->session_log_imported_id($rows[$k]->qb_id);
					$ids[] = $rows[$k]->qb_id;
					//qb_log_debug('importing object: '.$rows[$k]->name);
					//$rows[$k]->save();  no changes performed yet
				}
				// otherwise skip, previously tried to import this session
			}
			else if(method_exists($this, 'register_import_without_id')) {
				$this->register_import_without_id($rows[$k]);
			}
			else {
				$rows[$k]->sync_status = ($for_update ? 'update_error' : 'import_error');
				$msg = 'Cannot import without QuickBooks ID';
				$rows[$k]->status_msg = $msg;
				qb_log_error($msg . ' ('.$rows[$k]->id.')');
				$rows[$k]->save();
			}
		}
		if($c = count($ids)) {
			qb_log_debug("requesting import for $c $qb_type details");
			$import_req = array(
				'type' => 'import',
				'base' => $qb_type,
				'params' => $this->get_import_request_params($ids, $qb_type),
			);
			$reqs[] = $import_req;
		}
		if(method_exists($this, 'get_extra_import_requests'))
			$this->get_extra_import_requests($reqs);
	}
	
	
	function add_export_requests($server_id, &$reqs, $max_export=-1, $update=false, $qb_type=null) {
		$rows = $this->retrieve_pending_export($server_id, $max_export, $update, $qb_type);
		qb_log_debug("adding export requests ($this->object_name)");
		foreach(array_keys($rows) as $k) {
			if($update) {
				if(isset($_SESSION['disable_update']) && in_array($rows[$k]->id, $_SESSION['disable_update'])) {
					qb_log_debug('Skipping update, disabled for this object');
					continue;
				}
			}
			else {
				if(isset($_SESSION['disable_export']) && in_array($rows[$k]->id, $_SESSION['disable_export'])) {
					// this is a backup check for objects (like QBCustomerType) with no sync status
					qb_log_debug('Skipping export, disabled for this object');
					continue;
				}
			}
			if($update && empty($rows[$k]->qb_id)) {
				// we probably tried to request details for this one after getting a duplicate name error
				// but for some reason there is still no QB ID associated with the object
				$_SESSION['disable_export'][] = $rows[$k]->id;
				$rows[$k]->sync_status = 'export_error';
				$rows[$k]->save();
				continue;
			}
			$req = null;
			$err = '';
			if($rows[$k]->get_export_request($req, $err, $update)) {
				$req['related_id'] = $rows[$k]->id;
				$reqs[] = $req;
				if($update)
					qb_log_debug('updating object: '.$rows[$k]->name);
				else
					qb_log_debug('exporting object: '.$rows[$k]->name);
				// set sync_status to 'sending export'?
				// sync_status may be changed to pending_update by export routine
				$rows[$k]->save();
			}
			else {
				if(! empty($rows[$k]->export_blocked))
					$rows[$k]->sync_status = $update ? 'update_blocked' : 'export_blocked';
				else if(empty($rows[$k]->retry_export_later))
					$rows[$k]->sync_status = $update ? 'update_error' : 'export_error';
				$msg = 'Error exporting '.$this->object_name.'['.$rows[$k]->id.']';
				if($err) {
					$rows[$k]->status_msg = $err;
					$msg .= ' - '.$err;
				}
				qb_log_error($msg);
				$rows[$k]->save();
			}
		}
	}
	
	function self_get_export_request(&$ret, $update=false) {
		$ret = $req = null;
		// behaviour should be consistent with add_export_requests
		if($this->get_export_request($req, $err, $update)) {
			$req['related_id'] = $this->id;
			if($update && empty($req['sync_stage']))
				$req['sync_stage'] = 'update';
			$ret = $req;
			return true;
		}
		return false;
	}

	
	function add_delete_requests($server_id, &$reqs, $max_delete=-1, $qb_type=null) {
		$rows = $this->retrieve_pending_delete($server_id, $max_delete, $qb_type);
		foreach(array_keys($rows) as $k) {
			if(isset($_SESSION['disable_delete']) && in_array($rows[$k]->id, $_SESSION['disable_delete'])) {
				qb_log_debug('Skipping delete, disabled for this object');
				continue;
			}
			if($rows[$k]->get_delete_request($req, $err)) {
				$req['related_id'] = $rows[$k]->id;
				$reqs[] = $req;
			}
			else {
				$rows[$k]->sync_status = 'delete_error';
				$msg = 'Error deleting '.$this->object_name.'['.$rows[$k]->id.']';
				if($err) {
					$rows[$k]->status_msg = $err;
					$msg .= ' - '.$err;
				}
				qb_log_error($msg);
				$rows[$k]->save();
			}
		}
	}

	
	function get_ref() {
		return array(
			'ListID' => $this->qb_id,
			'FullName' => $this->name,
		);
	}
	
	function get_list_view_data() {
		global $mod_strings;
		$row_data = $this->get_list_view_array();
		if(! empty($this->status_msg)) {
			global $image_path;
			$div = make_listview_div($this->id.'_note_div', $this->status_msg);
			$lid = $this->id . '_note';
			$lnk = '<a href="#" onclick="return false;" id="'.$lid.'"  onmouseover="show_note(event, this)" onmouseout="show_note(event, this, 1)">';
			$img = '<img src="'.$image_path.'/Notes.gif" border="0" align="absmiddle" width="16" height="16" alt="" />';
			$row_data['SYNC_STATUS_MSG'] = $div.$lnk.$img.'</a>';
		}
		if(preg_match('~(_blocked|_error)~', $this->sync_status))
			$row_data['SYNC_STATUS'] = '<span class="error">'.$row_data['SYNC_STATUS'].'</span>';
		if(! $this->qb_is_active)
			$row_data['INACTIVE_MSG'] = ' <em>'.$mod_strings['LBL_INACTIVE'].'</em>';
		if (isset($row_data['SYNC_STATUS_MSG'])) {
			$row_data['SYNC_STATUS'] .= ' ' . $row_data['SYNC_STATUS_MSG'];
		}
		return $row_data;
	}
	
	
	/**
		Query QBBeans pending export and perform the export.
	**/
	function &retrieve_pending_export($server_id, $max_export=-1, $update=false, $qb_type=null) {
		$qb_type_f = $qb_type ? " AND qb_type='".$this->db->quote($qb_type)."' " : '';
		$status = $update ? 'pending_update' : 'pending_export';
		$sid = $this->db->quote($server_id);
		$query = "SELECT id FROM {$this->table_name} WHERE sync_status='$status' $qb_type_f ".
			"AND server_id='$sid' AND NOT deleted ORDER BY name";
		if($max_export >= 0)
			$query .= " LIMIT $max_export";
		$result = $this->db->query($query, true, "Error retrieving objects pending export");
		$qb_beans = array();
		while($row = $this->db->fetchByAssoc($result)) {
			$seed = new $this->object_name;
			if($seed->retrieve($row['id'], false) !== null) // do not HTML-encode fields
				$qb_beans[] = $seed;
		}
		return $qb_beans;
	}
		
	function &qb_export_details() {
		return $this->export_details;
	}

	
	function &retrieve_pending_import($server_id, $max_import=-1, $qb_type=null) {
		$qb_type_f = $qb_type ? " AND qb_type='".$this->db->quote($qb_type)."' " : '';
		$sid = $this->db->quote($server_id);
		$query = "SELECT id FROM {$this->table_name} WHERE sync_status='pending_import' $qb_type_f ".
			"AND server_id='$sid' AND NOT deleted ORDER BY name";
		if($max_import >= 0)
			$query .= " LIMIT $max_import";
		$result = $this->db->query($query, true, "Error retrieving objects pending import");
		$qb_beans = array();
		while($row = $this->db->fetchByAssoc($result)) {
			$seed = new $this->object_name;
			if($seed->retrieve($row['id'], false) !== null) // do not HTML-encode fields
				$qb_beans[] = $seed;
		}
		return $qb_beans;
	}

	
	function &retrieve_pending_delete($server_id, $max_delete=-1, $qb_type=null) {
		$qb_type_f = $qb_type ? " AND qb_type='".$this->db->quote($qb_type)."' " : '';
		$sid = $this->db->quote($server_id);
		$query = "SELECT id FROM {$this->table_name} WHERE sync_status='pending_delete' $qb_type_f ".
			"AND server_id='$sid' AND NOT deleted ORDER BY name";
		if($max_delete >= 0)
			$query .= " LIMIT $max_delete";
		$result = $this->db->query($query, true, "Error retrieving objects pending deletion");
		$qb_beans = array();
		while($row = $this->db->fetchByAssoc($result)) {
			$seed = new $this->object_name;
			if($seed->retrieve($row['id'], false) !== null) // do not HTML-encode fields
				$qb_beans[] = $seed;
		}
		return $qb_beans;
	}
	
	
	function get_delete_request(&$ret, &$err) {
		$pfx = empty($this->qb_is_transaction_type) ? 'List' : 'Txn';
		$id = $this->qb_id;
		if(method_exists($this, 'qb_get_type'))
			$base = $this->qb_get_type();
		else
			$base = $this->qb_type;
		if(! $id || ! $base)
			return false;
		$ret = array(
			'type' => 'delete',
			'base' => $pfx,
			//'action' => 'post_delete_update',
			'params' => array(
				$pfx.'DelType' => $base,
				$pfx.'ID' => $id,
			),
			'related_id' => $id,
		);
		return true;
	}
	
	// used in ListView generation
	function get_linked_icon($type, $module, $lbl=null) {
		global $app_list_strings, $mod_strings;
		global $image_path;
		if(! isset($lbl)) $lbl = 'LBL_'.strtoupper($type);
		$name = $type.'_name';
		$id = $type.'_id';
		if(! empty($this->$id)) {
			$esc_name = htmlentities($this->$name);
			$real_module = ($module=='PaymentsOut') ? 'Payments' : $module;
			$lbltxt = isset($mod_strings[$lbl]) ? $mod_strings[$lbl] : $app_list_strings['moduleList'][$module];
			$alt = htmlentities($lbltxt);
			$img = get_image($image_path.$real_module, "align='absmiddle' border='0' alt='$alt' title='$esc_name' style='margin: 0 2pt'");
			if($module == 'Discounts' || $module == 'TaxRates')
				$action = 'index';
			else
				$action = 'DetailView';
			$link = '<a href="index.php?module='.$module.'&action='.$action.'&record='.$this->$id.'">'.$img.'</a>';
			return $link;
		}
		return '';
	}
	
	
	function handle_error_response($server_id, &$req, $resp) {
		$attrs =& $resp['attrs'];
		$statusCode = (int)$attrs['statusCode'];
		$qb_msg = $attrs['statusSeverity'] . " $statusCode: " . $attrs['statusMessage'];
		if($statusCode < 500) { // Info message
			qb_log_debug('Received info message from QuickBooks: '.$qb_msg);
			return true;
		}
		qb_log_error('Received error from QuickBooks: '.$qb_msg);
		
		$bean = new $this->object_name();
		if($req->related_id && $bean->retrieve($req->related_id)) {
			if(in_array($statusCode, $this->qb_temporary_errors)) {
				$msg = "A temporary error occurred, $statusCode: ".$attrs['statusMessage'];
				$bean->status_msg = $msg;
			}
			else {
				if(defined('QBLINK_DEBUG') && file_exists("qblink_sent.xml"))
					copy("qblink_sent.xml", "qblink_error_req.xml");
				if($req->request_type == 'export') {
					$msg = "Disabled export for {$bean->object_name} object: {$bean->name} {$bean->id}";
					$bean->sync_status = 'export_error';
					$_SESSION['disable_export'][] = $bean->id;
					if($statusCode == 3100) {
						if($bean->object_name == 'QBItem') {
							$bean->sync_status = 'pending_update';
						} else if($bean->object_name == 'QBEntity' && ! $bean->qb_id) {
							$bean->sync_status = 'pending_export';
						}
					}
				}
				else if($req->request_type == 'update') {
					$msg = "Disabled update for {$bean->object_name} object: {$bean->name} {$bean->id}";
					$bean->sync_status = 'update_error';
					$_SESSION['disable_update'][] = $bean->id;
				}
				else if($req->request_type == 'delete') {
					$msg = "An error occurred in deleting {$bean->object_name} object: {$bean->name} {$bean->id}";
					$bean->sync_status = 'delete_error';
					$_SESSION['disable_delete'][] = $bean->id;
				}
				else
					return false;
				$bean->status_msg = 'QB '.$qb_msg;
			}
			qb_log_error($msg);
			$bean->save();
		}
		return false;
	}
	
	
	/*function post_export_update($server_id, &$req, &$details) {
		$bean = new $this->object_name();
		if($bean->retrieve($req->related_id)) {
			qb_log_debug("Confirmed export for {$bean->object_name} object: {$bean->name}");
			
			$fieldmap = $this->std_import_field_map;
			if(is_array($this->qb_field_map))
				$fieldmap += $this->qb_field_map;
			if(is_array($this->qb_post_export_map))
				$fieldmap += $this->qb_post_export_map;
			if(! $bean->update_from_array($details, $fieldmap))
				qb_log_error("Error updating export record");
			$bean->first_sync = 'exported';
			//if(! empty($bean->qb_update_required))
			//	$bean->sync_status = 'pending_update';
			//else
				$bean->sync_status = '';
			$bean->status_msg = '';
			$bean->save();
			return true;
		}
		return true; // keep processing
	}*/

	
	function qb_mass_change_status($mass, $status) {
		$q_status = $this->db->quote($status);
		$ids = $mass->uids;
		if (empty($ids))
			return;
		if ($ids === 'all')
			$q_ids = '1';
		else
			$q_ids = "id IN('".implode("','", $ids)."')";
		$qs = array();
		if($status === '') {
			$qs[] = "UPDATE `{$this->table_name}` tbl SET tbl.sync_status=IF(sync_status='update_error','pending_update','') WHERE $q_ids AND first_sync IN ('imported','exported')";
			$qs[] = "UPDATE `{$this->table_name}` tbl SET tbl.sync_status='pending_export', first_sync='' WHERE $q_ids AND (first_sync IN ('','deleted') OR first_sync IS NULL)";
		}
		else {
			if($status == 'pending_delete') {
				$and_where = " AND first_sync='exported' ";
			}
			else if($status == 'pending_update') {
				$qs[] = "UPDATE `{$this->table_name}` tbl SET tbl.sync_status='pending_export' WHERE $q_ids AND tbl.sync_status='export_error'";
				$and_where = " AND (sync_status != 'pending_export' OR sync_status IS NULL) ";
			}
			else
				$and_where = '';
			$qs[] = "UPDATE `{$this->table_name}` tbl SET tbl.sync_status='$q_status' WHERE $q_ids $and_where";
		}
		foreach($qs as $q)
			$this->db->query($q, true, "Error updating sync status");
		qb_log_debug("updated sync status for ($q_ids)");
	}
	
	function get_first_sync_dom() {
		global $app_list_strings;
		$dom = $app_list_strings['qb_first_sync_dom'];
		$dom[''] = '';
		return $dom;
	}

	function get_qb_entity_types_dom()
	{
		global $mod_strings, $app_list_strings;
		$dom = $app_list_strings['qb_entity_types_dom'];
		$dom = array(
			'' => '',
		) + $dom;
		return $dom;
	}
	
	function get_qb_item_types_dom()
	{
		global $mod_strings, $app_list_strings;
		$dom = $app_list_strings['qb_item_types_dom'];
		$dom = array(
			'' => '',
		) + $dom;
		return $dom;
	}
	
	function get_qb_tally_types_dom()
	{
		global $mod_strings, $app_list_strings;
		$dom = $app_list_strings['qb_tally_types_dom'];
		$dom = array(
			'' => '',
		) + $dom;
		return $dom;
	}
	
	static function get_sync_status_dom() {
		global $mod_strings, $app_list_strings;
		$dom = $app_list_strings['qb_sync_status_dom'];
		$lbl = $dom[''];
		unset($dom['']);
		$dom = array(
			'' => '',
			'ENABLED' => $lbl,
			'PENDING' => $mod_strings['LBL_ALL_PENDING'],
			'BLOCKED' => $mod_strings['LBL_ALL_BLOCKED'],
			'ERRORED' => $mod_strings['LBL_ALL_ERRORED'],
		) + $dom;
		return $dom;
	}

	function qb_mass_re_register($mass) {
		if (is_array($mass))
			$ids = $mass;
		else
			$ids = $mass->uids;
		if (empty($ids))
			return;
		if ($ids === 'all') {
			$lq = new ListQuery($this->object_name, array('id'));
			$all = $lq->fetchAllRows();
			$ids = array();
			foreach ($all as $row)
				$ids[] = $row['id'];
		}
		foreach($ids as $id) {
			$ok = $this->qb_re_register($id);
			//qb_log_debug(($ok ? "re-registered: " : "could not re-register: ") . $id);
		}
	}
	
	function qb_re_register($qb_id) {
		return true;
	}
	
	function set_update_status_if_modified(&$bean, $date_f='date_last_sync') {
		if($this->first_sync != 'imported' && $this->first_sync != 'exported')
			return false;
		if($this->sync_status != '' || $this->$date_f == '')
			return false;
		global $timedate, $disable_date_format;
		if(! empty($disable_date_format)) {
			$last = $this->date_last_sync;
			$upd = $bean->date_modified;
		}
		else {
			$last = $timedate->to_db($this->date_last_sync);
			$upd = $timedate->to_db($bean->date_modified);
		}
		if($last < $upd)
			$this->sync_status = 'pending_update';
		return true;
	}
	
	function register_local_objects($server_id) {
		$ret = array();
		$ret['new'] = $this->register_pending_exports($server_id, -1);
		$ret['upd'] = $this->register_pending_updates($server_id);
		$ret['blk'] = $this->qb_re_register_blocked($server_id);
		return $ret;
	}
	
	function register_pending_exports($server_id, $max_register=-1) {
		return array();
	}
	
	function register_pending_updates($server_id) {
		return true;
	}
	
	function qb_re_register_blocked($server_id) {
		$sid = $this->db->quote($server_id);
		$query = "SELECT id FROM {$this->table_name} ".
			"WHERE sync_status IN ('export_blocked', 'update_blocked') ".
			"AND server_id='$sid' AND NOT deleted";
		$r = $this->db->query($query, false);
		if(! $r) return false;
		$ids = array();
		while( ($row = $this->db->fetchByAssoc($r)) ) {
			$ids[] = $row['id'];
		}
		$this->qb_mass_re_register($ids);
		return $ids;
	}
	
	function session_log_imported_id($id, $category=null) {
		if(! isset($category)) $category = $this->object_name;
		$_SESSION['imported_ids'][$category][$id] = 1;
		return true;
	}

	function session_check_imported_id($id, $category=null) {
		if(! isset($category)) $category = $this->object_name;
		if(isset($_SESSION['imported_ids']) && isset($_SESSION['imported_ids'][$category]))
			if(array_get_default($_SESSION['imported_ids'][$category], $id))
				return true;
		return false;
	}

	function reset_all_qb_time($server_id, $mass)
	{
		$ids = $mass->uids;
		if (empty($ids))
			return;
		if ($ids === 'all')
			$q_ids = '1';
		else
			$q_ids = "id IN('".implode("','", $ids)."')";
		$query = "UPDATE {$this->table_name} SET qb_date_modified = NULL, qb_editseq=NULL ".
			"WHERE $q_ids AND server_id='$server_id' AND NOT deleted";
		$this->db->query($query, true);
	}
	
	function ACLAccess($view,$is_owner='not_set',$in_group='not_set'){
		switch ($view){
			case 'list':
			case 'index':
			case 'ListView':
				return true;
			default:
				return false;
		}
	}
	
	function get_sync_status_where($sparam, $filter, $prefix)
	{
		$value = array_get_default($filter, $prefix . 'sync_status_');
		if (!$value) return '(1)';
		switch ($value) {
			case 'ENABLED':
				return "(sync_status = '' OR sync_status IS NULL)";
			case 'PENDING':
				return "(sync_status LIKE 'pending%')";
			case 'BLOCKED':
				return "(sync_status LIKE '%_blocked')";
			case 'ERRORED':
				return "(sync_status LIKE '%_error')";
		}
		return "(sync_status = '$value')";
	}

	function get_first_sync_where($sparam, $filter, $prefix)
	{
		$value = array_get_default($filter, $prefix . 'first_sync_');
		if ($value) {
			return "(first_sync = '$value')";
		}
		return '(1)';
	}

	function get_qb_type_where($sparam, $filter, $prefix)
	{
		$value = array_get_default($filter, $prefix . 'qb_type_');
		if ($value) {
			return "(qb_type = '$value')";
		}
		return '(1)';
	}

	function get_server_id_where($param)
	{
		$server_id = QBServer::get_primary_server_id();
		return "({$this->table_name}.server_id = '{$server_id}')";
	}
	
}
