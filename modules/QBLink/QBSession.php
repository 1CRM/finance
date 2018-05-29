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
require_once('modules/QBLink/QBServer.php');

define('MAX_SESSION_REQUESTS', 5000);

class QBSession extends SugarBean {

	// Saved fields
	var $id;
	var $qb_session_id;
	var $server_id;
	var $date_entered; // start time
	var $last_access;
	var $created_by; // the QB session user
	var $requests_sent;
	var $sync_phase; // Setup, Items, Entities, Tallies
	var $sync_stage; // import, export, update, ..
	var $sync_step;  // if phase has multiple steps
	var $redo_stage;
	var $percent_done;
	var $error_text;
	var $requests;
	
	var $date_modified;
	var $deleted;

	// Runtime only
	var $server;
	var $HCP_response;
	var $qbxml_major_ver;
	var $qbxml_minor_ver;
	var $new_requests;
	
	var $module_dir = 'QBLink';
	var $table_name = 'qb_sessions';
	var $object_name = 'QBSession';
	var $new_schema = true;
	var $processed = true; // disable all workflow for this object
	
	
	function retrieve($id = -1, $enc = true) {
		$ret = parent::retrieve($id, $enc);
		$this->new_requests = array();
		return $ret;
	}

	function retrieve_by_session_id($sess_id) {
		$q = sprintf("SELECT id FROM `$this->table_name` t WHERE t.qb_session_id='%s'",
			$this->db->quote($sess_id));
		$ret = $this->db->query($q, true, "Error retrieving session record");
		if($row = $this->db->fetchByAssoc($ret)) {
			$status = $this->retrieve($row['id']);
			if($status && $this->server_id) {
				$this->load_server();
			}
		}
		else {
			$status = null;
		}
		return $status;
	}
	
	function init_server_request(&$cfg, $params) {
		if(empty($this->server_id)) {
			$this->server = new QBServer();
			$this->server_id = $this->server->on_connect_server($cfg, $params);
		}
		else {
			if(empty($this->server) && ! $this->load_server()) {
				return array('missing_server', "An error occurred in loading server information");
			}
			if(! $this->server->consistency_check($cfg, $params)) {
				$err = "Consistency check failed: Server parameters changed";
				return array('failed_consistency', $err);
			}
		}
		$this->server->update_info_cache();
	}
	
	function load_server() {
		$this->server = new QBServer();
		$ret = $this->server->retrieve($this->server_id);
		if(! $ret)
			unset($this->server);
		return $ret;
	}
	
	function get_server_id() {
		return $this->server_id;
	}
		
	function load_new_requests(&$cfg, &$errs) {
		if($this->requests_sent >= MAX_SESSION_REQUESTS) {
			// FIXME - send any pending requests from later stages & phases?
			// (ie. update requests)
			$this->set_server_result('partial', "Hit maximum number of session requests (".MAX_SESSION_REQUESTS.')');
			qb_log_error("Ending session: hit max session requests (".MAX_SESSION_REQUESTS.")");
			$this->sync_stage = 'done';
			return 0;
		}
		$this->clear_pending_requests();
		$phases = $cfg->get_sync_phases();
		$stages = $this->server->sync_stages;
		$new_phase = false;
		if(! $this->sync_phase) {
			$this->sync_phase = key($phases);
			$new_phase = true;
		}
		$phases_done = 0;
		$phase_perc = 0;
		$redo_while_requests = array(
			'ext_import', 'export', 'ext_export', 'pre_update',
		);
		$req_c = 0;
		foreach($phases as $phase => $ph) {
			if($this->sync_phase == $phase || $new_phase) {
				$mgr = $ph['mgr'];
				if(! $cfg->load_handler($mgr, $phase_mgr, $errs)) {
					$this->set_server_result('aborted', "Error loading handler '$mgr'");
					qb_log_error("Error loading handler '$mgr', aborting");
					break;
				}
				if($new_phase) {
					$can_begin = $phase_mgr->phase_can_begin($this->server_id, $phase);
					if($can_begin !== true && empty($can_begin['allow'])) {
						if(is_array($can_begin))
							$msg = array_get_default($can_begin, 'error', '');
						if(! $msg)
							$msg = "Could not begin phase '$phase'";
						$this->set_server_result('aborted', $msg);
						qb_log_error("Cannot begin phase '$phase', aborting");
						break;
					}
					qb_log_debug("Begin phase '$phase'");
					$this->sync_phase = $phase;
					$this->sync_stage = '';
				}
				if($this->sync_phase == $phase) {
					$exec_stage = false;
					foreach($stages as $s) {
						if(! $exec_stage) {
							if($s == $this->sync_stage || ! $this->sync_stage) {
								$exec_stage = true;
								if($this->sync_stage && ! $this->redo_stage)
									continue;
							}
						}
						if(! $exec_stage)
							continue;
						$this->sync_stage = $s;
						$this->redo_stage = 0;
						$this->load_pending_requests(true);
						$req_c = $this->count_pending_requests();
						if($req_c) {
							qb_log_debug("found $req_c pre-inserted request(s) [stage: $this->sync_stage]");
							$this->redo_stage = 1;
						}
						else {
							$req_c = $this->add_stage_requests($cfg, $errs);
							if($req_c) {
								if(in_array($this->sync_stage, $redo_while_requests))
									$this->redo_stage = 1;
							}
						}
						$phase_perc = $phase_mgr->phase_get_percent_complete($this->server_id, $phase, $s, $stages);
						if($req_c)
							break;
					}
					$new_phase = true;
				}
			}
			if($req_c)
				break;
			$phases_done ++;
		}
		if(! $req_c)
			$this->percent_done = 100;
		else
			$this->percent_done = ($phases_done * 100 + $phase_perc) / count($phases);
		return $req_c;
	}
	
	function add_stage_requests(&$cfg, &$errs) {
		//qb_log_debug("Adding stage requests ({$this->sync_phase}, {$this->sync_stage})");
		$phases = $cfg->get_sync_phases();
		$ph = $phases[$this->sync_phase];
		if(empty($ph['steps']))
			$ph['steps'] = array('' => array());
		$req_c = 0;
		foreach($ph['steps'] as $key => $step) {
			$mgr = array_get_default($step, 'mgr', $ph['mgr']);
			if(! $cfg->load_handler($mgr, $mgr_obj, $errs)) {
				qb_log_error("Phase {$this->sync_phase} step $key could not be loaded");
				continue;
			}
			if(! method_exists($mgr_obj, 'get_pending_requests')) {
				qb_log_error("Phase {$this->sync_phase} step $key missing get_pending_requests");
				continue;
			}
			$this->sync_step = $key;
			$server_id = $this->get_server_id();
			$add_reqs = $mgr_obj->get_pending_requests($server_id, $this->sync_stage, $this->sync_phase, $this->sync_step);
			if(is_array($add_reqs) && count($add_reqs)) {
				$add_c = $this->add_pending_requests($add_reqs, $mgr_obj);
				qb_log_debug("Added $add_c requests for {$this->sync_phase}/{$this->sync_step}/{$this->sync_stage}");
				$req_c += $add_c;
			}
		}
		return $req_c;
	}
	
	function count_pending_requests() {
		$c = 0;
		$reqs = $this->get_pending_requests();
		for($i = 0; $i < count($reqs); $i++)
			if($reqs[$i]->status == 'pending')
				$c ++;
		return $c;
	}
	
	function get_percent_complete() {
		return floor($this->percent_done);
	}
	
	function add_pending_request($req, &$handler) {
		foreach(array('sync_phase', 'sync_step', 'sync_stage') as $f) {
			if(isset($req[$f])) break;
			$req[$f] = $this->$f;
		}
		$req_obj = $this->server->create_request($this, $req, $handler);
		if(! $req_obj)
			return false;
		$this->new_requests[] = $req_obj;
		return true;
	}
	
	function add_pending_requests(&$arr, &$handler) {
		if(! count($arr))
			return 0;
		qb_log_debug('adding new pending requests: '.count($arr));
		$ok = 0;
		foreach($arr as $r) {
			$ok += $this->add_pending_request($r, $handler) ? 1 : 0;
		}
		return $ok;
	}
	
	function &get_pending_requests() {
		return $this->new_requests;
	}
	
	function clear_pending_requests() {
		$this->new_requests = array();
	}
	
	function &batch_pending_requests(&$send_count) {
		$reqs = $this->get_pending_requests();
		if(! $reqs)
			return false;
		$ret = $this->server->encode_requests($reqs, $send_count);
		for($i = 0; $i < count($reqs); $i++) {
			if($i < $send_count && $reqs[$i]->status == 'pending')
				$reqs[$i]->status = 'sent';
			$reqs[$i]->save();
		}
		$this->requests_sent += $send_count;
		$this->clear_pending_requests();
		return $ret;
	}
	
	function save_new_requests() {
		//qb_log_debug("saving new requests");
		$reqs = $this->get_pending_requests();
		if(! $reqs)
			return false;
		for($i = 0; $i < count($reqs); $i++) {
			$reqs[$i]->save();
		}
		return count($reqs);
	}
	
	function &load_requests($status=null) {
		if(! $this->sync_phase) {
			$reqs = array();
			return $reqs;
		}
		if(isset($status))
			$where = array('operator' => '=', 'lhs_field' => 'status', 'rhs_value' => $status);
		else
			$where = '';
		$this->load_relationship('requests');
		$seed = new QBRequest();
		$query = $this->requests->getQuery(false, array('sequence'), 0);
		$query .= sprintf(" AND sync_phase='%s' AND sync_stage='%s' ",
			$this->db->quote($this->sync_phase),
			$this->db->quote($this->sync_stage));
		if(isset($status))
			$query .= " AND status='".$this->db->quote($status)."'";
		//qb_log_debug($query);
		qb_log_debug("loading $status requests: {$this->sync_phase}:{$this->sync_stage}");
		$reqs = $this->build_related_list($query, $seed);
		return $reqs;
	}
	
	function &load_sent_requests() {
		$this->sent_requests = $this->load_requests('sent');
		return $this->sent_requests;
	}
	
	function &load_pending_requests($force_reload=false) {
		/*if($postponed && $this->next_sync_phase) {
			$this->sync_phase = $this->next_sync_phase;
			$this->sync_stage = $this->next_sync_stage;
			$this->next_sync_phase = '';
			$this->next_sync_stage = '';
		}*/
		if($force_reload || ! is_array($this->new_requests) || ! count($this->new_requests)) {
			$reqs = $this->load_requests('pending');
			if($reqs)
				$this->new_requests =& $reqs;
			else
				$this->new_requests = array();
		}
		return $this->new_requests;
	}
	
	function handle_response(&$cfg, &$qbxml) {
		$resps = $this->server->parse_response($qbxml);
		if(! $resps) {
			$msg = $this->server->parse_error;
			qb_log_error($msg);
			$this->error_text = $msg;
			return false;
		}
		$reqs = $this->load_sent_requests();
		qb_log_debug('loaded sent requests: '.count($reqs));
		$reqmap = array();
		
		/* FIXME - occasionally we can an error response and a data response
			for the same request, for example when using ReceivePaymentAdd
			with an unknown invoice ID. Process errors first, then the rest
			of the messages, and allow error notices to be handled for completed
			requests.
		*/
		
		for($j = 0; $j < count($reqs); $j++) {
			$reqmap[$reqs[$j]->sequence] = $j;
		}
		$errs = array();
		for($i = 0; $i < count($resps); $i++) {
			$attrs =& $resps[$i]['attrs'];
			$rid = $attrs['requestID'];
			if(! isset($reqmap[$rid])) {
				$msg = "Response does not correspond to known request ($rid)";
				$errs[] = $msg;
				continue;
			}
			$req =& $reqs[$reqmap[$rid]];
			$req->last_import_name = null;
			$req->last_import_count = 0;
			if(! $cfg->load_request_handler($req, $handler, $errs)) {
				$req->status = 'error';
				$req->save();
				continue;
			}
			if(! empty($attrs['statusCode']) && method_exists($handler, 'handle_error_response')) {
				$ok = $handler->handle_error_response($this->server_id, $req, $resps[$i]);
				if(! $ok) {
					$req->status = 'error';
					$req->save();
					continue;
				}
			}
			if(! empty($attrs['iteratorID'])) {
				$req->params_arr['attrs']['iteratorID'] = $attrs['iteratorID'];
				$req->iter_remain = array_get_default($attrs, 'iteratorRemainingCount', 0);
			}
			$method = $req->action;
			if(empty($method)) $method = 'handle_response';
			if(! method_exists($handler, $method)) {
				$cls = class_name($handler);
				$errs[] = "Method undefined: $cls.$method";
				$req->status = 'error';
				$req->save();
				continue;
			}
			$root =& $resps[$i]['root'];
			$val = $root;
			if(is_array($req->params_arr) && isset($req->params_arr['preparse']))
				$req_preparse = $req->params_arr['preparse'];
			else
				$req_preparse = '';
			if($req_preparse) {
				$ok = $this->server->format_response($val, $req, $errs);
				if(! $ok) {
					$req->status = 'error';
					$req->save();
					continue;
				}
			}
			$newreqs = array();
			if($handler->$method($this->server_id, $req, $val, $newreqs)) {
				if(count($newreqs))
					$this->add_pending_requests($newreqs, $handler);
			}
			else {
				$req->status = 'error';
				$req->save();
				continue;
			}
			if(isset($handler->last_import_name)) {
				$req->last_import_name = $handler->last_import_name;
			}
			if(isset($handler->last_import_count)) {
				$req->last_import_count = $handler->last_import_count;
			}
			$req->status = 'complete';
			if(array_get_default($req->params_arr, 'optimize') == 'byname' && $req->last_import_count)
				$resend = true;
			else if(! empty($req->iter_remain))
				$resend = true;
			else
				$resend = false;
			if($resend) {
				if($this->server->optimize_process_request($req))
					$req->mark_for_resend();
				else
					$resend = false;
			}
			if(method_exists($handler, 'post_handle_response')) {
				$handler->post_handle_response($this->server_id, $req);
			}
			/*if(! empty($req->iter_remain)) {
				$next_req = $req->params_arr;
				$this->add_pending_request($next_req, $handler);
			}
			*/
			if(! $resend) {
				// enable filtering by modification date for this query
				$step = "{$req->sync_phase}/{$req->sync_step}";
				$prep = "{$req->sync_stage}:$req_preparse";
				if(! QBConfig::get_server_setting($this->server_id, $step, $prep)) {
					//qb_log_debug("save server setting ($this->server_id) ($step) ($prep)");
					QBConfig::save_server_setting($this->server_id, $step, $prep, '1');
				}
			}
			$req->save();
		}
		if(count($errs)) {
			foreach($errs as $e) {
				qb_log_error($e);
				if($this->error_text)
					$this->error_text .= "\n";
				$this->error_text .= $e;
			}
			return false;
		}
		return true;
	}
	
	function set_server_result($status, $msg='') {
		if($this->server) {
			$this->server->set_sync_result($status, $msg);
		}
	}
	
	function save($chech_notify = false) {
		if($this->server && $this->server->status_updated())
			$this->server->save();
		return parent::save($chech_notify);
	}

}
