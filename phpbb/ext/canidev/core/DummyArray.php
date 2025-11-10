<?php
/**
 * @package Ext Common Core
 * @version 1.1.4 26/01/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

/**
 * Creates an array that always have values,
 * never throws an error for a non-existent key
 */
class DummyArray extends \ArrayObject
{
	protected $data = [];

	const TOPICROW_KEYS = [
		'FORUM_ID',
		'TOPIC_ID',
		'TOPIC_AUTHOR',
		'TOPIC_AUTHOR_COLOUR',
		'TOPIC_AUTHOR_FULL',
		'FIRST_POST_TIME',
		'LAST_POST_SUBJECT',
		'LAST_POST_TIME',
		'LAST_VIEW_TIME',
		'LAST_POST_AUTHOR',
		'LAST_POST_AUTHOR_COLOUR',
		'LAST_POST_AUTHOR_FULL',
		'REPLIES',
		'VIEWS',
		'TOPIC_TITLE',
		'TOPIC_TYPE',
		'FORUM_NAME',
		'TOPIC_IMG_STYLE',
		'TOPIC_FOLDER_IMG',
		'TOPIC_FOLDER_IMG_ALT',
		'TOPIC_ICON_IMG',
		'TOPIC_ICON_IMG_WIDTH',
		'TOPIC_ICON_IMG_HEIGHT',
		'ATTACH_ICON_IMG',
		'UNAPPROVED_IMG',
		'S_TOPIC_TYPE',
		'S_USER_POSTED',
		'S_UNREAD_TOPIC',
		'S_TOPIC_REPORTED',
		'S_TOPIC_UNAPPROVED',
		'S_POSTS_UNAPPROVED',
		'S_TOPIC_DELETED',
		'S_HAS_POLL',
		'S_POST_ANNOUNCE',
		'S_POST_GLOBAL',
		'S_POST_STICKY',
		'S_TOPIC_LOCKED',
		'S_TOPIC_MOVED',
		'U_NEWEST_POST',
		'U_LAST_POST',
		'U_LAST_POST_AUTHOR',
		'U_TOPIC_AUTHOR',
		'U_VIEW_TOPIC',
		'U_VIEW_FORUM',
		'U_MCP_REPORT',
		'U_MCP_QUEUE',
		'S_TOPIC_TYPE_SWITCH',
	];

	/**
	 * Constructor
	 * @param array 	$data 	Dummy data
	 */
	public function __construct($data = [])
	{
		$this->data = $data;
	}

	/**
	 * {@inheritDoc}
	 */
	public function count()
	{
		return sizeof($this->data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getArrayCopy()
	{
		return $this->data;
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetExists($key)
	{
		return isset($this->data[$key]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetGet($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetSet($key, $value)
	{
		$this->data[$key] = $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetUnset($key)
	{
		if(isset($this->data[$key]))
		{
			unset($this->data[$key]);
		}
	}

	/**
	 * Fill an array with custom keys
	 * 
	 * @param array 		$data 			Original data
	 * @param array 		$keys 			Keys to push into array
	 * @return array
	 */
	static public function fill($data, $keys)
	{
		if(is_array($keys))
		{
			foreach($keys as $k)
			{
				if(!isset($data[$k]))
				{
					$data[$k] = '';
				}
			}
		}

		return $data;
	}
}
