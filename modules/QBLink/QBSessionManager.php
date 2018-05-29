<?php

require_once('modules/QBLink/QBSession.php');

class QBSessionManager {

	var $current_session;
	var $session_expire_mins = 4;
	var $cfg;
	
	// --
	
	function QBSessionManager() {
		require_once('modules/QBLink/QBConfig.php');
		$this->cfg = new QBConfig();
	}

	function start_session($session_id) {
		$this->terminate_expired_sessions();
		$this->terminate_active_sessions();
		$this->truncate_sessions();
		$this->current_session = new QBSession();
		$this->current_session->qb_session_id = $session_id;
		$this->current_session->status = 'authenticating';
		// FIXME: set company filename to configured one
		$id = $this->current_session->save();
		if($id) {
			$this->current_session = new QBSession();
			if($this->current_session->retrieve($id))
				return true;
		}
		return false;
	}
	
	function resume_session($session_id) {
		$this->terminate_expired_sessions();
		$this->current_session = new QBSession();
		$fail = false;
		if(! $this->current_session->retrieve_by_session_id($session_id))
			$fail = true;
		if(! $fail && $this->current_session->status != 'active') {
			$fail = true;
			$this->current_session = null;
			return false;
		}
		return true;
	}
	
	function end_session() {
		$message = $this->get_session_summary();
		$this->current_session->status = 'closed';
		$this->current_session->set_server_result('success', $message);
		return $message;
	}
	
	function save_session() {
		if($this->current_session) {
			$this->current_session->save_new_requests();
			$this->current_session->last_access = qb_date_time();
			$this->current_session->save();
			return true;
		}
	}
	
	function do_shutdown() {
		$this->save_session();
	}
	
	function terminate_expired_sessions() {
	}
	
	function terminate_active_sessions() {
		
	}
	
	function truncate_sessions() {
	}
	
	// --
	
	function init_server_request($params) {
		if(! $this->current_session)
			return false;
		list($status, $err) = $this->current_session->init_server_request($this->cfg, $params);
		if($status) {
			$this->set_status($status);
			$this->set_error_message($err);
			return false;
		}
		$this->current_session->set_server_result('pending');
		return true;
	}
	
	function set_session_user_id($uid) {
		if($this->current_session) {
			$this->current_session->created_by = $uid;
			$this->current_session->status = 'active';
			$id = $this->current_session->save();
			if($id) {
				$this->current_session = new QBSession();
				if($this->current_session->retrieve($id))
					return true;
			}
		}
		return false;
	}
		
	function get_session_user_id() {
		if($this->current_session)
			return $this->current_session->created_by;
		return null;
	}
	
	function failed_authentication($msg) {
		$this->set_status("failed_auth");
		$this->set_error_message("Authentication failed: $msg");
	}
	
	function get_session_summary() {
		$reqs = 0;
		if($this->current_session)
			$reqs = $this->current_session->requests_sent;
		return "Session ended successfully ($reqs individual requests)";
	}
	
	// --
	
	function load_requests($force_reload=false) {
		if(! $this->current_session)
			return false;
		$this->current_session->load_pending_requests($force_reload);
		if($this->current_session->count_pending_requests()) {
			qb_log_debug('Session resuming pending requests');
			return true;
		}
		$errs = array();
		$req_c = $this->current_session->load_new_requests($this->cfg, $errs);
		if(count($errs))
			foreach($errs as $e) qb_log_error($e);
		return $req_c;
	}
	
	function get_encoded_request() {
		if(! $this->current_session)
			return false;
		$this->current_session->load_pending_requests();
		$reqs = $this->current_session->batch_pending_requests($sent);
		qb_log_debug("sending pending requests ($sent)");
		//qb_log_info($reqs);
		return $reqs;
	}
	
	function handle_response(&$qbxml) {
		if(! $this->current_session)
			return false;
		$this->current_session->load_server();
		return $this->current_session->handle_response($this->cfg, $qbxml);
	}
	
	function handle_error_response($code, $message) {
		qb_log_debug("received error response, $message ($code)");
		$this->set_error_message("QuickBooks returned error: $message ($code)");
		return false;
	}
	
	function set_status($status) {
		if($this->current_session) {
			$this->current_session->status = $status;
			return true;
		}
	}
	
	function set_error_message($msg) {
		if($this->current_session) {
			$this->current_session->error_text = $msg;
			$this->current_session->set_server_result('aborted', $msg);
			return true;
		}
	}
	
	function get_error_message($ticket=null) {
		$message = '';
		if($ticket) {
			$sess = new QBSession();
			if($sess->retrieve_by_session_id($ticket))
				$message = $sess->error_text;
		}
		else if($this->current_session)
			$message = $this->current_session->error_text;
		return $message;
	}
	
	function get_percent_complete() {
		if($this->current_session)
			return $this->current_session->get_percent_complete();
		return -1;
	}
	
	function get_company_filename() {
		if(QBConfig::get_setting('Server', 'limit_filename')) {
			qb_log_debug('SSS limit_filename');
			$srv = new QBServer();
			if($srv->retrieve_primary()) {
				return $srv->company_filename;
			}
		}
		return '';
	}

}
