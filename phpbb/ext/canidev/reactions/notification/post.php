<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\notification;

class post extends \phpbb\notification\type\base
{
	protected $notifications_table;
	protected $user_loader;

	protected $language_key = 'REACTIONS_NOTIFICATION_POST';
	
	/**
	 * Setup notifications table
	 * 
	 * @param string 	$notifications_table
	 */
	public function set_notifications_table($notifications_table)
	{
		$this->notifications_table = $notifications_table;
	}

	/**
	 * Setup user loader
	 * 
	 * @param \phpbb\user_loader 	$user_loader
	 */
	public function set_user_loader(\phpbb\user_loader $user_loader)
	{
		$this->user_loader = $user_loader;
	}
	
	/**
	 * Determine ir notification is available
	 * 
	 * @return bool
	 */
	public function is_available()
	{
		return true;
	}
	
	/**
	 * Determine if notification allows multiuser format
	 * 
	 * @return bool
	 */
	public function allow_multiple_users()
	{
		return true;
	}

	/**
	* Get notification type name
	*
	* @return string
	*/
	public function get_type()
	{
		return 'canidev.reactions.notification.post';
	}

	/**
	 * Notification option data (for outputting to the user)
	 *
	 * @var bool|array False if the service should use it's default data
	 * 					Array of data (including keys 'id', 'lang', and 'group')
	 */
	static public $notification_option = [
		'lang'	=> 'REACTIONS_NOTIFICATION_TYPE_POST',
		'group'	=> 'NOTIFICATION_GROUP_POSTING',
	];

	/**
	* Get the id of the item
	*
	* @param array 	$data 		Data from submitted element
	* @return int
	*/
	static public function get_item_id($data)
	{
		return (int)$data['item_id'];
	}

	/**
	* Get the id of the parent
	*
	* @param array 	$data 		Data from submitted element
	* @return int
	*/
	static public function get_item_parent_id($data)
	{
		return (int)$data['item_parent_id'];
	}

	/**
	* Find the users who want to receive notifications
	*
	* @param array 	$data 		Data from submitted element
	* @param array 	$options 	Options for finding users for notification
	*
	* @return array
	*/
	public function find_users_for_notification($data, $options = [])
	{
		$options = array_merge(array(
			'ignore_users'		=> [],
		), $options);
		
		$options['ignore_users'][$this->user->data['user_id']] = [''];

		$users = [$data['author_id']];
		
		return $this->check_user_notification_options($users, $options);
	}
	
	/**
	* Users needed to query before this notification can be displayed
	*
	* @return array Array of user_ids
	*/
	public function users_to_query()
	{
		return $this->get_data('user_ids');
	}

	/**
	 * Get the user's avatar
	 *
	 * @return string
	 */
	public function get_avatar()
	{
		$user_ids = $this->get_data('user_ids');
		return $this->user_loader->get_avatar($user_ids[0], false, true);
	}

	/**
	 * Get the HTML formatted title of this notification
	 *
	 * @return string
	 */
	public function get_title()
	{
		return $this->obtain_title_str();
	}

	/**
	 * Get the HTML formatted reference of the notification
	 *
	 * @return string
	 */
	public function get_reference()
	{
		if($this->get_data('item_resume'))
		{
			return $this->language->lang(
				'NOTIFICATION_REFERENCE',
				censor_text($this->get_data('item_resume'))
			);
		}
		
		return '';
	}

	/**
	 * Get email template
	 *
	 * @return string|bool
	 */
	public function get_email_template()
	{
		return '@canidev_reactions/reaction_post_notify';
	}

	/**
	 * Get email template variables
	 *
	 * @return array
	 */
	public function get_email_template_variables()
	{
		$user_ids 	= $this->get_data('user_ids');
		$username 	= $this->user_loader->get_username($user_ids[0], 'username');

		return [
			'USERNAME'		=> htmlspecialchars_decode($username),
			'U_LINK'		=> $this->get_url(true),
		];
	}

	/**
	 * Get the url to this item
	 *
	 * @return string URL
	 */
	public function get_url($absolute_url = false)
	{
		$base_url = ($absolute_url) ? generate_board_url() . '/' : $this->phpbb_root_path;
		return append_sid($base_url . 'viewtopic.' . $this->php_ext, "p={$this->item_id}#p{$this->item_id}");
	}

	/**
	 * {@inheritdoc}
	 */
	public function create_insert_array($data, $pre_create_data = [])
	{
		$this->set_data('item_resume', 	$data['item_resume']);
		
		$user_ids = (isset($data['user_ids'])) ? $data['user_ids'] : [];
		
		if(isset($data['user_id']))
		{
			$user_ids[] = $data['user_id'];
		}
		
		$this->set_data('user_ids', $user_ids);

		parent::create_insert_array($data, $pre_create_data);
	}
	
	/**
	 * Format notification title with usernames
	 * 
	 * @return string
	 */
	protected function obtain_title_str()
	{
		$user_ids = $this->get_data('user_ids');
		
		if($user_ids === null)
		{
			$user_ids = [$this->get_data('user_id')];
		}
		
		$usernames	= [];
		$users_cnt	= sizeof($user_ids);
		
		// Always show the last users
		$user_ids = array_reverse($user_ids);
		
		// Reduce number of users
		$user_ids 		= $this->trim_user_ary($user_ids);
		$trimmed_cnt	= $users_cnt - sizeof($user_ids);

		foreach($user_ids as $user_id)
		{
			$usernames[] = $this->user_loader->get_username($user_id, 'no_profile');
		}

		if($trimmed_cnt > 20)
		{
			$usernames[] = $this->language->lang('NOTIFICATION_MANY_OTHERS');
		}
		else if($trimmed_cnt)
		{
			$usernames[] = $this->language->lang('NOTIFICATION_X_OTHERS', $trimmed_cnt);
		}

		return $this->language->lang(
			$this->language_key,
			phpbb_generate_string_list($usernames, $this->user),
			$users_cnt
		);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function create_update_array($data)
	{
		// If not user added, prevent mark as unread an already read notification
		if(!isset($data['user_id']))
		{
			$this->__set('notification_read', $data['notification_read']);
		}

		$this->create_insert_array($data);
		return $this->get_insert_array();
	}
	
	/**
	 * Update related notification
	 * 
	 * @return bool
	 */
	public function update_notifications(array $data)
	{
		if(isset($data['notification_id']))
		{
			$update_array = $this->create_update_array($data);
			
			$sql = 'UPDATE ' . $this->notifications_table . '
				SET ' . $this->db->sql_build_array('UPDATE', $update_array) . '
				WHERE notification_id = ' . (int)$data['notification_id'];
			$this->db->sql_query($sql);
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Trim the user array passed down to 3 users if the array contains
	 * more than 4 users.
	 *
	 * @param array $users Array of users
	 * @return array Trimmed array of user_ids
	 */
	protected function trim_user_ary($users)
	{
		if(sizeof($users) > 4)
		{
			array_splice($users, 3);
		}

		return $users;
	}
}
