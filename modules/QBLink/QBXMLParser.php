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


require_once('include/nusoap/nusoap.php'); // XMLCompat

class QBXMLParser {
	var $node;
	var $stack;
	var $parser;
	var $cur_elt;
	var $attrs;
	var $responses;
	var $in_response = false;
	var $retnum;
	var $parse_error;
	var $use_compat_parser = true;
	
	var $repeated_elts = array(
		'SupportedQBXMLVersion',
		'ItemGroupLine',
	);

	function QBXMLParser() {
	}

	function parse(&$data) {
		if(! empty($this->parser))
			// not sure why it's necessary to recreate the parser
			xml_parser_free($this->parser);
		$this->parse_error = '';
		
		if(class_exists('XMLCompat') && $this->use_compat_parser) {
			// XMLCompat defined in nusoap.php, info@hand 6.0+
			$compat_parser = new XMLCompat('_start_element', '_end_element', '_cdata');
			$compat_parser->set_object($this);
		}
		else {
			$this->parser = xml_parser_create("UTF-8");
			xml_set_object($this->parser, $this);
			xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
			xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 1);
			//xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, "ISO-8859-1");
			xml_set_element_handler($this->parser, "_start_element", "_end_element");
			xml_set_character_data_handler($this->parser, "_cdata");
		}

		$this->in_response = false;
		$this->responses = array();
		$this->retnum = 0;
		
		if(defined('QBLINK_DEBUG')) {
			$fp = fopen('qb_parse_in.xml', 'w');
			if($fp) {
				fwrite($fp, $data);
				fclose($fp);
			}
		}
		
		//$this->logfp = fopen('qb_parse_log.txt', 'w');
		
		if(isset($compat_parser)) {
			$ret = $compat_parser->do_parse($data);
		}
		else {
			$ret = xml_parse($this->parser, $data, true);
		}
		//fclose($this->logfp);
		if(! $ret) {
			if(isset($compat_parser)) {
				$this->parse_error = $compat_parser->get_error_desc();
			}
			else {
				$code = xml_get_error_code($this->parser);
				$ln = xml_get_current_line_number($this->parser);
				$col = xml_get_current_column_number($this->parser);
				$this->parse_error = "XML error $code: ".xml_error_string($code)." ($ln:$col)";
			}
			if(1 || defined('QBLINK_DEBUG')) {
				$fp = fopen('qb_parse_error.xml', 'w');
				if($fp) {
					fwrite($fp, "<!-- {$this->parse_error} -->\n\n");
					fwrite($fp, $data);
					fclose($fp);
				}
			}
			return false;
		}

		if(defined('QBLINK_DEBUG')) {
			$fp = fopen('qb_parse_out.xml', 'w');
			if($fp) {
				ob_start();
				var_dump($this->responses);
				fwrite($fp, ob_get_contents());
				ob_end_clean();
				fclose($fp);
			}
		}

		return true;
	}
	
	function &get_data() {
		return $this->responses;
	}
	
	function get_error() {
		return $this->parse_error;
	}

	function _start_element(&$parser, $tag, $attrs) {
		if($tag == 'QBXML')
			return;
		if($tag == 'QBXMLMsgsRs') {
			// parsing a new response
			$this->in_response = true;
			$this->node = '';
			$this->attrs = array();
			$this->stack = array();
			$this->cur_elt = null;
			return;
		}
		if(! $this->in_response)
			// log error?
			return;
		if(! empty($attrs))
			$this->attrs = $attrs; // attributes only occur on query response node
		$this->stack[] = array($this->cur_elt => $this->node);
		$this->cur_elt = $tag;
		$this->node = '';
	}
	
	function _cdata(&$parser, $cdata) {
		if(preg_match('/^\s+$/', $cdata))
			// ignore whitespace
			return;
		if(is_array($this->node)) {
			// $this->conn->logError("..");
			//echo "error: assigning text ($cdata) to element with children<br>";
			return;
		}
		if($this->cur_elt == null) {
			//echo "error: null current element<br>";
			return;
		}
		$this->node .= $cdata;
	}
	
	function _end_element(&$parser, $tag) {
		if($tag == 'QBXML')
			return;
		if($tag == 'QBXMLMsgsRs') {
			$this->in_response = false;
			return;
		}
		$up = array_pop($this->stack);
		$prev_elt = key($up);
		$prev_node =& $up[$prev_elt];
		if(!is_array($prev_node))
			$prev_node = array();
		if(preg_match('/Ret$/', $this->cur_elt) || in_array($this->cur_elt, $this->repeated_elts)) {
			if(is_array($this->node))
				$this->node['_pos_'] = $this->retnum ++;
			$prev_node[$this->cur_elt][] = $this->node;
		}
		else
			$prev_node[$this->cur_elt] = $this->node;
		$this->cur_elt = $prev_elt;
		$this->node =& $prev_node;
		
		if(! count($this->stack)) {
			$this->responses[] = array('attrs' => $this->attrs, 'root' => $this->node);
			$this->node = array();
		}
	}
	
	function xml_escape($val) {
		$val = str_replace(array('&', '<', '>', chr(27)), array('&amp;', '&lt;', '&gt;', ''), $val);
		return $val;
	}
	
	function encode_params(&$params) {
	//qb_log_error($params);
		$ret = '';
		foreach($params as $idx => $value) {
			if($idx == 'idx') // used by export routines - ignore
				continue;
			if(is_array($value)) {
				if(!count($value))
					continue;
				if(is_int(key($value))) {
					foreach($value as $subvalue) {
						$p = array($idx => $subvalue);
						$ret .= $this->encode_params($p);
					}
					continue;
				}
				else
					$attrval = $this->encode_params($value);
			}
			else if($value === null)
				continue; // ignore placeholder
			else
				$attrval = $this->xml_escape($value);
			if(($p = strpos($idx, '___')) !== false) {
				$idx = substr($idx, 0, $p);
			}
			$ret .= "\r\n<$idx>$attrval</$idx>";
		}
		return $ret;
	}

}

?>
