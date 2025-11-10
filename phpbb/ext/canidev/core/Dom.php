<?php
/**
 * @package Ext Common Core
 * @version 1.1.4 26/01/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

class Dom
{
	static $selfclosing_tags = 'img|br|hr|input|iframe|source';

	/**
	 * Convert array in string with html attributes
	 * 
	 * @param array 	$attributes
	 * @return string
	 */
	public static function arrayToAttr($attributes)
	{
		return implode(
			' ',
			array_map(
				function($key) use ($attributes)
				{
					if($attributes[$key] === null)
					{
						return '';
					}

					if(is_bool($attributes[$key]))
					{
						return ($attributes[$key]) ? $key : '';
					}

					return $key . '="' . $attributes[$key] . '"';
				},
				array_keys($attributes)
			)
		);
	}

	/**
	 * Convert string with html attributes into array
	 * 
	 * @param string 	$string
	 * @return array
	 */
	public static function attrToArray($string)
	{
		$attributes = [];

		preg_match_all('#([\w\d\-]+)(="([^"]*)")?#', $string, $matches, PREG_SET_ORDER);

		foreach($matches as $match)
		{
			$attributes[$match[1]] = ($match[2]) ? $match[3] : true;
		}

		return $attributes;
	}

	/**
	 * Load xml string
	 * 
	 * @param string 	$string 		Text to be load
	 * @return \DomDocument|false
	 */
	public static function loadXML($string)
	{
		$dom 	= new \DOMDocument('1.0', 'utf-8');
		$flags 	= \LIBXML_NOCDATA | \LIBXML_NOWARNING | \LIBXML_NOERROR;

		if(LIBXML_VERSION >= 20700)
		{
			$flags |= \LIBXML_PARSEHUGE;
		}
	
		if(!$dom->loadXML('<div>' . self::prepareXML($string) . '</div>', $flags))
		{
			return false;
		}

		return $dom;
	}

	/**
	 * Prepare raw XML to be processed by PHP Dom classes
	 * 
	 * @param string 		$string 		Raw XML
	 * @return string
	 */
	public static function prepareXML($string)
	{
		$string = preg_replace('#(<(' . self::$selfclosing_tags . ')[^>]*)(?<!/)>(</\\2>)?#i', '$1 />', $string);
		$string = preg_replace('/(<(?:audio|video)[^>]+controls) /', '$1="true" ', $string);

		$string = preg_replace_callback(
			'(&(?!quot;|amp;|apos;|lt;|gt;)\\w+;)',
			function($m)
			{
				return html_entity_decode($m[0], \ENT_HTML5 | \ENT_NOQUOTES, 'UTF-8');
			},
			str_replace('&AMP;', '&amp;', $string)
		);

		$string = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $string);

		return $string;
	}
	
	/**
	 * Convert string into xPath variable
	 * 
	 * @param string 	$string 		Text to be load
	 * @return \DomXPath|false
	 */
	public static function stringToXpath($string)
	{
		if(($dom = self::loadXML($string)) === false)
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
	public static function xpathToString($xpath)
	{
		return preg_replace(
			[
				'#(^<div>|</div>$)#',
				'#<((' . str_replace('|iframe', '', self::$selfclosing_tags) . ').*?)></\\2>#',
			],
			[
				'',
				'<$1 />',
			],
			$xpath->document->saveXML($xpath->document->documentElement, \LIBXML_NOEMPTYTAG)
		);
	}

	/**
	 * Load xml filename
	 * 
	 * @param string 	$filename
	 * @return \SimpleXMLElement
	 */
	public static function simpleXmlFile($filename)
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
	 * @param \DomElement 		$node 		Node to be checked
	 * @param array|string 		$selector 	Match node name or class
	 * @return bool
	 */
	public static function nodeClosest($node, $selector)
	{
		if(!is_array($selector))
		{
			$selector = [$selector];
		}

		if($node->nodeType == XML_ELEMENT_NODE)
		{
			foreach($selector as $s)
			{
				// Class selector
				if(substr($s, 0, 1) == '.')
				{
					if(strpos($node->getAttribute('class'), substr($s, 1)) !== false)
					{
						return true;
					}
					
					continue;
				}

				// Name selector
				if($node->nodeName == $s)
				{
					return true;
				}
			}
		}
		
		if($node->parentNode !== null)
		{
			return self::nodeClosest($node->parentNode, $selector);
		}
		
		return false;
	}

	/**
	 * Remove all attributes of specific node
	 * 
	 * @param \DomElement 			$node		Node
	 * @param string|array|false 	$ignore 	Array with attributes to preserve
	 * 
	 * @return \DomElement
	 */
	public static function removeAttributes($node, $ignore = false)
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

	/**
	 * @deprecated 		To be removed in v1.2.0
	 */
	public static function load_xml($string)
	{
		return static::loadXML($string);
	}

	/**
	 * @deprecated 		To be removed in v1.2.0
	 */
	public static function string_to_xpath($string)
	{
		return static::stringToXpath($string);
	}

	/**
	 * @deprecated 		To be removed in v1.2.0
	 */
	public static function xpath_to_string($xpath)
	{
		return static::xpathToString($xpath);
	}

	/**
	 * @deprecated 		To be removed in v1.2.0
	 */
	public static function simple_xml_file($filename)
	{
		return static::simpleXmlFile($filename);
	}

	/**
	 * @deprecated 		To be removed in v1.2.0
	 */
	public static function node_closest($node, $node_name)
	{
		return static::nodeClosest($node, $node_name);
	}

	/**
	 * @deprecated 		To be removed in v1.2.0
	 */
	public static function remove_attributes($node, $ignore = false)
	{
		return static::removeAttributes($node, $ignore);
	}
}
