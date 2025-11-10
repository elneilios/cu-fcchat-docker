<?php
/**
 * @package Ext Common Core
 * @version 1.1.2 20/09/2023
 *
 * @copyright (c) 2023 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 * 
 */

namespace canidev\core;

class dom
{
	static $selfclosing_tags = 'img|br|hr|input|iframe';

	/**
	 * Load xml string
	 * 
	 * @param string 	$string 		Text to be load
	 * @return \DomDocument
	 */
	public static function load_xml($string)
	{
		$dom 	= new \DOMDocument('1.0', 'utf-8');

		$string = preg_replace('#(<(' . self::$selfclosing_tags . ')[^>]*)(?<!/)>(</\\2>)?#i', '$1 />', $string);
		$string = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $string);
		$flags 	= \LIBXML_NOCDATA | \LIBXML_NOWARNING | \LIBXML_NOERROR;

		if(LIBXML_VERSION >= 20700)
		{
			$flags |= \LIBXML_PARSEHUGE;
		}
	
		if(!$dom->loadXML('<div>' . $string . '</div>', $flags))
		{
			return false;
		}

		return $dom;
	}
	
	/**
	 * Convert string into xPath variable
	 * 
	 * @param string 	$string 		Text to be load
	 * @return \DomXPath
	 */
	public static function string_to_xpath($string)
	{
		if(($dom = self::load_xml($string)) === false)
		{
			return false;
		}

		return new \DomXPath($dom);
	}

	/**
	 * Convert xPath variable into HTML string
	 * 
	 * @param \DomXPath 	$xpath
	 * @return string
	 */
	public static function xpath_to_string($xpath)
	{
		return preg_replace(
			array(
				'#(^<div>|</div>$)#',
				'#<((' . str_replace('|iframe', '', self::$selfclosing_tags) . ').*?)></\\2>#',
			),
			array(
				'',
				'<$1 />',
			),
			$xpath->document->saveXML($xpath->document->documentElement, \LIBXML_NOEMPTYTAG)
		);
	}

	/**
	 * Load xml filename
	 * 
	 * @param string 	$filename
	 * @return \SimpleXMLElement
	 */
	public static function simple_xml_file($filename)
	{
		$err_status = \libxml_use_internal_errors(true);

		$xml = \simplexml_load_file($filename);

		\libxml_clear_errors();
		\libxml_use_internal_errors($err_status);

		return $xml;
	}

	/**
	 * Check if a node or their parents math with specific node name
	 * 
	 * @param \DomElement 	$node 		Node to be checked
	 * @param string 		$node_name 	Match node name
	 * @return bool
	 */
	public static function node_closest($node, $node_name)
	{
		if(!is_array($node_name))
		{
			$node_name = [$node_name];
		}

		if(in_array($node->nodeName, $node_name))
		{
			return true;
		}
		
		if($node->parentNode !== null)
		{
			return self::node_closest($node->parentNode, $node_name);
		}
		
		return false;
	}

	/**
	 * Remove all attributes of specific node
	 * 
	 * @param \DomElement 	$node			Node
	 * @param array|false 	$ignore 		Array with attributes to preserve
	 * 
	 * @return \DomElement
	 */
	public static function remove_attributes($node, $ignore = false)
	{
		$ignore 	= ($ignore !== false && !is_array($ignore)) ? [$ignore] : $ignore;
		$attr_ary 	= [];
		
		foreach($node->attributes as $name => $attrNode)
		{
			if($ignore === false || !in_array($name, $ignore))
			{
				$attr_ary[] = $name;
			}
		}

		foreach($attr_ary as $name)
		{
		    $node->removeAttribute($name);
		}

		return $node;
	}
}
