<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\libraries;

class notification_manager
{
	protected $db;
	protected $manager;
	protected $notifications_table;
	protected $user;

	/**
	 * Constructor
	 *
	 * @param \phpbb\db\driver\driver_interface				$db							DB Object
	 * @param \phpbb\notification\manager 					$notification_manager 		phpBB Notification Manager
	 * @param \phpbb\user									$user						User object
	 * @param string 										$notifications_table 		Notifications Table
	 *
	 * @access public
	 */
	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\notification\manager $notification_manager,
		\phpbb\user $user,
		$notifications_table
	)
	{
		$this->db 					= $db;
		$this->manager 				= $notification_manager;
		$this->notifications_table 	= $notifications_table;
		$this->user 				= $user;
	}

	/**
	 * Add new notification or update usernames if exists
	 * 
	 * @param string 		$notification_type_name
	 * @param array 		$notification_data
	 */
	public function add_notification($notification_type_name, $notification_data)
	{
		$notification_type_id 	= $this->manager->get_notification_type_id($notification_type_name);
		$item_parent_id			= isset($notification_data['item_parent_id']) ? $notification_data['item_parent_id'] : 0;

		/** @var \canidev\reactions\notification\post */
		$notification = $this->manager->get_item_type_class($notification_type_name);
		
		$time = $this->user->create_datetime();
		$time->setTime(0, 0, 0);

		// Try to obtain already added notification (today)
		$sql = 'SELECT *
			FROM ' . $this->notifications_table . '
			WHERE notification_type_id = ' . (int)$notification_type_id . '
			AND item_id = ' . (int)$notification_data['item_id'] . '
			AND item_parent_id = ' . $item_parent_id . '
			AND notification_time >= ' . $time->getTimestamp();
		$result = $this->db->sql_query($sql);
		$notification_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		
		if(!$notification_row || !$notification->allow_multiple_users())
		{
			return $this->manager->add_notifications($notification_type_name, $notification_data);
		}
		
		// If notification allows multiple users, update it
		$data = unserialize($notification_row['notification_data']);
		unset($notification_row['notification_data'], $notification_row['user_id']);
		
		$notification_data['user_ids'] = $data['user_ids'];
		
		$notification_data = array_merge($notification_row, $notification_data);

		return $this->manager->update_notifications($notification_type_name, $notification_data, [
			'item_id'			=> $notification_data['item_id'],
			'item_parent_id'	=> $item_parent_id,
		]);	
	}
	
	/**
	 * Delete single notification 
	 * 
	 * @param string 		$notification_type_name
	 * @param int			$item_id
	 * @param int 			$item_parent_id
	 * @param int 			$user_id
	 */
	public function delete_notification($notification_type_name, $item_id, $item_parent_id = 0, $user_id = 0)
	{
		$notification_type_id = $this->manager->get_notification_type_id($notification_type_name);
		
		// Try to obtain already added notification
		$sql = 'SELECT *
			FROM ' . $this->notifications_table . '
			WHERE notification_type_id = ' . (int)$notification_type_id . '
			AND item_id = ' . (int)$item_id . '
			AND item_parent_id = ' . (int)$item_parent_id . '
			ORDER BY notification_time DESC';
		$result = $this->db->sql_query($sql);
		$notification_row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		
		if(!$notification_row)
		{
			return;
		}
		
		$data = unserialize($notification_row['notification_data']);
		unset($notification_row['notification_data'], $notification_row['user_id']);
		
		if(isset($data['user_ids']) && $user_id)
		{
			$user_ary = $data['user_ids'];
			
			if(($key = array_search($user_id, $user_ary)) === false)
			{
				return;
			}
			
			unset($user_ary[$key]);
				
			// If there are still users, update the notification
			if(count($user_ary))
			{
				$data['user_ids'] = array_values($user_ary);
				
				$data = array_merge($notification_row, $data);

				return $this->manager->update_notifications($notification_type_name, $data, [
					'item_id'			=> $item_id,
					'item_parent_id'	=> $item_parent_id,
				]);	
			}
		}

		// Delete the notification
		$sql = 'DELETE FROM ' . $this->notifications_table . '
			WHERE notification_id = ' . (int)$notification_row['notification_id'];
		$this->db->sql_query($sql);
	}

	/**
	 * Delete notifications
	 * 
	 * @param string 			$notification_type_name
	 * @param int 				$item_id
	 * @param int|false 		$parent_id
	 * @param int|false 		$user_id
	 */
	public function delete_notifications($notification_type_name, $item_id, $parent_id = false, $user_id = false)
	{
		$this->manager->delete_notifications($notification_type_name, $item_id, $parent_id, $user_id);
	}

	/**
	 * Delete notification of single users
	 * 
	 * @param string 		$notification_type_name
	 * @param array|int 	$user_ids
	 */
	public function delete_user_notifications($notification_type_name, $user_ids)
	{
		$notification_type_id = $this->manager->get_notification_type_id($notification_type_name);
		$user_ids = is_array($user_ids) ? $user_ids : [$user_ids];
		$user_ids = array_map('intval', $user_ids);

		$sql = 'DELETE FROM ' . $this->notifications_table . '
			WHERE notification_type_id = ' . $notification_type_id . '
			AND ' . $this->db->sql_in_set('user_id', $user_ids);
		$this->db->sql_query($sql);
	}

	/**
	 * Generate resume for notification
	 * 
	 * @param string 	$text 		Original notification text
	 * @param string	$uid 		BBCode UID
	 * @param string 	$bitfield	BBCode Bitfield
	 * @return string
	 */
	public function generate_resume($text, $uid, $bitfield)
	{
		$message = generate_text_for_display($text, $uid, $bitfield, OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES);
		$dom = \canidev\core\Dom::stringToXpath($message);

		// Fix XSS vulnerability with html in "code" bbcode
		foreach($dom->query('//div[contains(@class, "codebox")]|//code') as $node)
		{
			$new_node = $dom->document->createTextNode('[...]');
			$node->parentNode->replaceChild($new_node, $node);
		}

		$message = strip_tags(\canidev\core\Dom::xpathToString($dom));
		$message = \canidev\core\TextParser::truncate($message, 150, '...');

		return $message;
	}
}
