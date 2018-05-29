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

class QBRequest extends SugarBean {

	// Saved fields
	var $id;
	var $session_id;
	var $status;
	var $sync_phase;
	var $sync_step;
	var $sync_stage;
	var $action;
	var $sequence;
	var $params_id;
	var $request_type;
	var $related_id;
	var $send_count;
	var $last_import_name;
	var $last_import_count;
	var $iter_count;
	var $date_entered;
	var $date_modified;
	var $deleted;
	
	var $module_dir = 'QBLink';
	var $table_name = 'qb_requests';
	var $object_name = 'QBRequest';
	var $new_schema = true;
	var $processed = true; // disable all workflow for this object
	
	var $qbxml;
	var $params_arr;
	var $pending_resend;
	var $retrieve_encode_fields = false;
	
	function save($notify=false) {
		if($this->pending_resend) {
			$this->status = 'pending';
		}
		if($this->status == 'sent' && $this->init_status != 'sent') {
			$this->send_count ++;
		}
		$this->save_params();
		$ret = parent::save($notify);
		return $ret;
	}
	
	function save_params() {
		if(empty($this->params_arr) && empty($this->qbxml)) {
			$this->params_id = null;
			return;
		}
		if(empty($this->id)) {
			$this->id = create_guid();
			$this->new_with_id = true;
			$do_update = false;
		}
		else
			$do_update = ! empty($this->params_id);
		if(empty($this->params_arr) || ! is_array($this->params_arr))
			$this->params_arr = array();
		$params = serialize($this->params_arr);
		
		if($do_update) {
			//$now = gmdate("Y-m-d H:i:s");
			$query = sprintf("UPDATE qb_request_params SET request_id='%s', params='%s', qbxml='%s' WHERE id='%s'",
				$this->id,
				$this->db->quote($params),
				$this->db->quote($this->qbxml),
				$this->params_id);
		}
		else {
			$this->params_id = create_guid();
			$query = sprintf("INSERT INTO qb_request_params SET id='%s', request_id='%s', params='%s', qbxml='%s'",
				$this->params_id, $this->id,
				$this->db->quote($params),
				$this->db->quote($this->qbxml));
		}
		return $this->db->query($query, true);
	}
	
	function load_params() {
		$this->qbxml = null;
		$this->params_arr = array();
		if(! empty($this->params_id)) {
			$query = "SELECT qbxml, params FROM qb_request_params WHERE id='".$this->params_id."' LIMIT 1";
			$r = $this->db->query($query, false);
			if($r && $row = $this->db->fetchByAssoc($r, -1, false)) {
				$this->qbxml = $row['qbxml'];
				if(! empty($row['params']))
					$this->params_arr = unserialize($row['params']);
				if(! $this->params_arr)
					qb_log_error("Error decoding request parameters ({$this->id})");
			}
		}
	}
	
	// changed default to NOT encode fields
	function &retrieve($id = -1, $encode=null) {
		if($encode === 'true')
			$encode = true;
		else if($encode === 'false')
			$encode = false;
		else
			$encode = $this->retrieve_encode_fields;
		$ret = parent::retrieve($id, $encode);
		if($ret) {
			$this->load_params();
			$this->init_status = $this->status;
		}
		return $ret;
	}
	
	function mark_for_resend() {
		$this->pending_resend = true;
	}

}
