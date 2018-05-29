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
require_once('modules/QBLink/qb_utils.php');
use_soap_error_handler(false);

/*
 * set_error_handler(function ($errno , $errstr, $errfile, int $errline) {
	$GLOBALS['log']->fatal("$errno: $errstr in $errfile:$errline");
	return false;
 });
 */

ob_start();

// ignore notices
error_reporting((E_ALL | E_STRICT)^ E_NOTICE);

if(ini_get('max_execution_time') < 300) set_time_limit(300);

global $disable_date_format, $disable_num_format;
// because we work partly with a current_user, partly without
$disable_date_format = true;
$disable_num_format = true;

qb_check_debug_enabled();
$wsdl_path = 'QBWebConnectorSvc.wsdl';


class QBWCServer {
	static $session_mgr;
	
	function QBWCServer() {
		require_once('modules/QBLink/QBSessionManager.php');
		if(! isset(QBWCServer::$session_mgr))
			QBWCServer::$session_mgr = new QBSessionManager();
	}

	/**
		QBWebConnector 1.5+ calls this method to determine if its own
		version is sufficient to sync with the web service. A return
		value prefixed by 'E:' will be displayed as an error, 'W:' is
		used to indicate a warning, and a blank string means no error.
		New in 2.0: return 'O:' followed by the version to indicate
		accepted versions. 'O:2.0' for example.
	**/
	function clientVersion($productVersion) {
		qb_log_debug("validated client version '$productVersion'");
		if(version_compare($productVersion, '1.5') >= 0)
			return '';
		return 'E:';
	}


	function serverVersion($ticket='') {
		global $current_language;
		$mod_strings = return_module_language($current_language, 'QBLink');
		return $mod_strings['LBL_QWC_LONGNAME'] . qblink_module_version();
	}


	/**
		This method is called to authenticate the QBWebConnector user.
		The return value is a pair of (ticket, result).
		Possible result values are 'nvu' (invalid username/password),
		'none' (valid user, but no requests are pending), or
		a string identifying the company file to use (blank for the current one).
	**/
	function authenticate($userName, $password) {
		global $authController, $current_user;
		$authController = new AuthenticationController();
		// ----------------------------------
		qb_log_debug("authenticating user $userName");
		
		session_regenerate_id();
		session_start();
		qb_log_debug("new session started ".session_id());
		QBWCServer::$session_mgr->start_session(session_id());
		
		$encpass = strtolower(md5($password));
		
		$params = array(
			'encoded_password' => $encpass,
		);
		$auth_result = 'nvu'; // non-valid user

		if($authController->login($userName, $password, $params)) {
			if(QBWCServer::$session_mgr->set_session_user_id($current_user->id)) {
				if($this->_load_current_user($errmsg)) {
					$auth_result = QBWCServer::$session_mgr->get_company_filename();
				}
				else {
					unset($_SESSION['authenticated_user_id']);
					QBWCServer::$session_mgr->failed_authentication($errmsg);
				}
			}
		}
		
		if($auth_result == 'nvu') {
			qb_log_error("SECURITY: failed attempted login for $userName by QBLink SOAP interface");
		}
		
		return array(session_id(), $auth_result, null /*, 180 */);
	}


	function _load_current_user(&$errmsg) {
		global $current_user;
		global $current_language;
		global $app_list_strings, $app_strings, $mod_strings;
		
		if(empty($_SESSION['authenticated_user_id'])) {
			$errmsg = "Session expired";
			return false;
		}
		
		if(QBWCServer::$session_mgr->get_session_user_id() != $_SESSION['authenticated_user_id']) {
			$errmsg = "User ID does not match";
			return false;
		}
		
		if(isset($_SESSION['authenticated_user_language']) && $_SESSION['authenticated_user_language'] != '') {
			$current_language = $_SESSION['authenticated_user_language'];
		} else {
			$base_language = AppConfig::setting('locale.base_language', 'en_us');
			$current_language = AppConfig::setting('locale.defaults.language', $base_language);
		}
		qb_log_debug("set current language: $current_language");
		$app_strings = return_application_language($current_language);
		$app_list_strings = return_app_list_strings_language($current_language);
		$mod_strings = return_module_language($current_language, 'QBLink');
	
		require_once('modules/Users/User.php');
		$user = new User();
		if($user->retrieve($_SESSION['authenticated_user_id'])) {
			$user->authenticated = true;
			$current_user = $user;
			qb_log_debug('set current user: '.$current_user->user_name);
			return true;
		}
		
		$errmsg = "User no longer recognized";
		return false;
	}


	function _resume_session($sessionID) {
		if(! $sessionID)
			return false;
	
		qb_log_debug("resuming session $sessionID");	
		session_id($sessionID);
		session_start();
		
		$fail = false;
		if(! QBWCServer::$session_mgr->resume_session($sessionID))
			$fail = true;
		if(! $fail && ! $this->_load_current_user($errmsg)) {
			QBWCServer::$session_mgr->set_error_message($errmsg);
			$fail = true;
		}
		
		if($fail) {
			qb_log_error("failed to resume session ".session_id().": ".$errmsg);
			session_destroy();
			return false;
		}
		
		return true;
	}


	/**
		If authenticate succeeds then QBWebConnector calls this method
		expecting a qbXML-formatted request in reply.
		A blank value may be returned, indicating an error occurred.
		In this case getLastError() will be called to retrieve the error text.
	**/
	function sendRequestXML($ticket, $HCPResponse, $CompanyFileName, 
							  $qbXMLCountry, $qbXMLMajorVers, $qbXMLMinorVers) {
		qb_log_debug('sendRequestXML');
		if(! $this->_resume_session($ticket))
			return '';
		
		QBWCServer::$session_mgr->init_server_request(
			array(
				'ip_address' => array_get_default($_SERVER, 'REMOTE_ADDR'),
				'hcp_response' => $HCPResponse,
				'company_filename' => $CompanyFileName,
				'qb_edition' => $qbXMLCountry,
				'qb_xml_version' => $qbXMLMajorVers .'.'. $qbXMLMinorVers,
			)
		);
		QBWCServer::$session_mgr->load_requests();
		$qbxml = QBWCServer::$session_mgr->get_encoded_request();
		
		if(defined('QBLINK_DEBUG') && ($fp = fopen('qblink_sent.xml', 'w'))) {
			fwrite($fp, $qbxml);
			fclose($fp);
		}
		
		if(defined('QBLINK_DEBUG') && ($fp = fopen('qblink_all_sent.xml', 'a'))) {
			fwrite($fp, $qbxml);
			fwrite($fp, "\n\n");
			fclose($fp);
		}
		
		return $qbxml;
	}
	
	
	/**
		hresult and message are blank unless an error occurred
	  -> integer response (percent completed), negative if an error occurred
		 return 100 only when finished
	**/
	function receiveResponseXML($ticket, $response, $hresult, $message) {
		qb_log_debug('receiveResponseXML');
		if(! $this->_resume_session($ticket))
			return -1;
		
		if($hresult) {
			qb_log_debug('processing error response');
			if(! QBWCServer::$session_mgr->handle_error_response($hresult, $message))
				return -1;
		}
		
		QBWCServer::$session_mgr->handle_response($response);
		qb_log_debug('checking for more requests');
		QBWCServer::$session_mgr->load_requests();
		$perc = QBWCServer::$session_mgr->get_percent_complete();
		qb_log_debug("percent complete: $perc");
		return $perc;
	}
	
	
	/**
		This method is called by the Web Connector after sendRequestXML returns a blank
		string or receiveResponseXML returns a negative number, either indicating an error.
	**/
	function getLastError($ticket) {
		qb_log_debug("returning error message for ticket: $ticket");
		$message = QBWCServer::$session_mgr->get_error_message($ticket);
		return $message;
	}
	
	
	function closeConnection($ticket) {
		qb_log_debug('closeConnection');
		$message = "Error closing connection: session expired";
		if($this->_resume_session($ticket)) {
			$message = QBWCServer::$session_mgr->end_session();
			session_destroy();
			unset($current_user);
			qb_log_debug('session ended by client');
		}
		return $message;
	}
	

	/**
		This method is called when the WebConnector fails to connect to QB
		(because wrong company file open for instance). Return 'done' if we
		give up, or an alternate company file name to try again (blank for the current one).
	**/
	function connectionError($ticket, $hresult, $message) {
		qb_log_debug('connectionError');
		return 'done';
	}
}


ob_clean();
if(! isset($HTTP_RAW_POST_DATA))
	$HTTP_RAW_POST_DATA = file_get_contents('php://input');

if(defined('QBLINK_DEBUG')) {
	$out = fopen('qblink_input.txt', 'a');
	fwrite($out, $HTTP_RAW_POST_DATA);
	fwrite($out, "\n");
	fclose($out);
}

if(0 && class_exists('SoapServer')) {
	qb_log_debug('Using: SoapServer');
	if(defined('QBLINK_DEBUG'))
		ini_set('soap.wsdl_cache_enabled', 0);	
	$qbl_server = new SoapServer($wsdl_path, array('soap_version' => constant('SOAP_1_2'), 'encoding' => 'utf-8', 'send_errors' => false, 'trace' => 1, 'exceptions' => true));
	$qbl_server->setClass('QBWCServer');
	if(strlen($HTTP_RAW_POST_DATA))
		$qbl_server->handle($HTTP_RAW_POST_DATA);
	else if(array_get_default($_SERVER, 'QUERY_STRING') == 'wsdl') {
		header('Content-type: application/xml');
		readfile($wsdl_path);
	}
}
else {
	qb_log_debug('Using: nusoap');
	require_once('include/nusoap/nusoap.php');
	
	class NusoapCompat extends soap_server {
		var $updated_wsdl = false;
		function invoke_method() {
			if(! $this->updated_wsdl) {
				$fns = array();
				foreach($this->wsdl->bindings as $port => $binding) {
					foreach($binding['operations'] as $opname => $op) {
						$op['name'] = 'QBWCServer.'.$opname;
						$this->wsdl->bindings[$port]['operations'][$op['name']] = $op;
					}
				}
				$this->updated_wsdl = true;
			}
			$method = $this->methodname;
			if($method)
				$this->methodname = 'QBWCServer.'.$this->methodname;
			soap_server::invoke_method();
			$this->methodname = $method;
		}
	}
	
	$qbl_server = new NusoapCompat($wsdl_path);
	$qbl_server->service($HTTP_RAW_POST_DATA);
	
	if(defined('QBLINK_DEBUG')) {
		$out = fopen('qblink_soap_debug.txt', 'w');
		if($out) {
			fwrite($out, $qbl_server->getDebug());
			fclose($out);
		}
	}
}

if(isset(QBWCServer::$session_mgr))
	QBWCServer::$session_mgr->do_shutdown();


if(defined('QBLINK_DEBUG')) {
	$outp = ob_get_contents();
	$out = fopen('qblink_output.txt', 'a');
	if($out) {
		fwrite($out, $outp);
		fwrite($out, "\n");
		fclose($out);
	}
	$out = fopen('qblink_last_output.txt', 'w');
	if($out) {
		fwrite($out, $outp);
		fwrite($out, "\n(".strlen($s)." bytes)");
		fclose($out);
	}
}


ob_end_flush();
sugar_cleanup();

exit(0);

?>
