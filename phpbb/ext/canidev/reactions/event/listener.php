<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\event;

use canidev\core\Dom;
use canidev\reactions\libraries\constants;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	protected $auth;
	protected $cache;
	protected $config;
	protected $container;
	protected $db;
	protected $language;
	protected $notifications;
	protected $reactions;
	protected $reactions_table;
	protected $reactions_data_table;
	protected $template;
	protected $user;

	private $cache_ary 	= [];

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth 										$auth						Authentication object
	 * @param \phpbb\cache\driver\driver_interface 					$cache 						Cache instance
	 * @param \phpbb\config\config 									$config						Config Object
	 * @param \phpbb\db\driver\driver_interface						$db							DB Object
	 * @param \phpbb\language\language 								$language					Language object
	 * @param \canidev\reactions\libraries\notification_manager 	$notification_manager 		Notification Manager
	 * @param \canidev\reactions\libraries\reaction_manager 		$reactions 					Reaction Manager
	 * @param \canidev\core\template 								$template 					Canidev Template Object
	 * @param \phpbb\user											$user						User object
	 * @param string 												$reactions_table 			Reactions Table
	 * @param string 												$reactions_data_table 		Reactions Data Table
	 *
	 * @access public
	 */
	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\driver\driver_interface $cache,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\canidev\reactions\libraries\notification_manager $notification_manager,
		\canidev\reactions\libraries\reaction_manager $reactions,
		\canidev\core\template $template,
		\phpbb\user $user,
		$reactions_table,
		$reactions_data_table
	)
	{
		$this->auth 			= $auth;
		$this->cache 			= $cache;
		$this->config			= $config;
		$this->db 				= $db;
		$this->language 		= $language;
		$this->notifications 	= $notification_manager;
		$this->reactions 		= $reactions;
		$this->template			= $template;
		$this->user 			= $user;
		
		$this->reactions_table 			= $reactions_table;
		$this->reactions_data_table 	= $reactions_data_table;
	}

	/**
	* {@inheritDoc}
	*/
	static public function getSubscribedEvents()
	{
		return [
			'core.delete_posts_in_transaction_before'	=> 'delete_posts',
			'core.delete_user_after'					=> 'delete_user',
			'core.permissions'							=> 'add_permissions',
			'core.memberlist_view_profile' 				=> 'memberlist_view_profile',
			'core.modify_posting_auth'					=> 'posting_auth',
			'core.viewtopic_cache_user_data' 			=> 'viewtopic_user_data',
			'core.viewforum_modify_topic_ordering'		=> 'viewforum_gen_sort_selects', // phpBB >= 3.2.5
			'core.viewtopic_gen_sort_selects_before'	=> 'viewtopic_gen_sort_selects', // phpBB >= 3.2.8
			'core.viewtopic_modify_post_data'			=> 'viewtopic_modify_data',
			'core.viewtopic_modify_post_row'			=> 'viewtopic_modify_row',
			'core.update_username'						=> 'update_username',
			'core.user_setup'							=> 'load_language',
		];
	}

	/**
	 * Add Permissions to ACP
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function add_permissions(\phpbb\event\data $event)
	{
		$event['permissions'] = array_merge($event['permissions'], [
			'm_reactions'		=> ['lang'	=> 'ACL_M_REACTIONS',			'cat'	=> 'post_actions'],
			'u_reactions'		=> ['lang'	=> 'ACL_U_REACTIONS',			'cat'	=> 'post'],
			'u_reactions_view'	=> ['lang'	=> 'ACL_U_REACTIONS_VIEW',		'cat'	=> 'post'],
		]);
	}

	/**
	 * Delete reaction's data on post delete
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function delete_posts(\phpbb\event\data $event)
	{
		$table_ary = $event['table_ary'];
		$delete_notifications_types = $event['delete_notifications_types'];
		
		$table_ary[] = $this->reactions_data_table;
		$delete_notifications_types[] = 'canidev.reactions.notification.post';
		
		$event['table_ary'] = $table_ary;
		$event['delete_notifications_types'] = $delete_notifications_types;
	}

	/**
	 * Delete reaction's data on user delete
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function delete_user(\phpbb\event\data $event)
	{
		$post_ids = [];

		// Get affected posts
		$sql = 'SELECT post_id
			FROM ' . $this->reactions_data_table . '
			WHERE ' . $this->db->sql_in_set('user_id', $event['user_ids']);
		$result = $this->db->sql_query($sql);
		while($row = $this->db->sql_fetchrow($result))
		{
			$post_ids[] = (int)$row['post_id'];
		}
		$this->db->sql_freeresult($result);

		// Delete reactions
		$sql = 'DELETE FROM ' . $this->reactions_data_table . '
			WHERE ' . $this->db->sql_in_set('user_id', $event['user_ids']);
		$this->db->sql_query($sql);

		// Resync post score
		$this->reactions->resync_post_score($post_ids);

		// Delete user notifications
		if($event['mode'] == 'retain')
		{
			$this->notifications->delete_user_notifications('canidev.reactions.notification.post', $event['user_ids']);
		}
	}

	/**
	 * Load Reactions language
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function load_language(\phpbb\event\data $event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'canidev/reactions',
			'lang_set' => 'main',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Load profile stats
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function memberlist_view_profile(\phpbb\event\data $event)
	{
		$user_id = (int)$event['member']['user_id'];
		
		$sql = 'SELECT COUNT(*) as total
			FROM ' . $this->reactions_data_table . '
			WHERE user_id = ' . $user_id;
		$result = $this->db->sql_query($sql);
		$user_reaction_count = (int)$this->db->sql_fetchfield('total');
		$this->db->sql_freeresult($result);
		
		$this->template->assign_var('TOTAL_REACTIONS', $this->language->lang('TOTAL_REACTIONS_LABEL', $user_reaction_count));
		
		if($this->config['reactions_score_on_profile'])
		{
			$score = $event['member']['user_reaction_score'];

			/**
			 * Adjust score count for user.
			 * Correction of the error with the creation of the index in phpbb posts in large forums.
			 */
			if(is_null($score))
			{
				$score_ary = $this->reactions->get_users_score([$user_id]);

				$score = $score_ary[$user_id];

				$sql = 'UPDATE ' . USERS_TABLE . '
					SET user_reaction_score = ' . $score . '
					WHERE user_id = ' . $user_id;
				$this->db->sql_query($sql);
			}

			$this->template->assign_var('REACTIONS_USER_SCORE', $score);
		}
	}

	/**
	 * Determine if user can posting a reply
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function posting_auth(\phpbb\event\data $event)
	{
		if($event['mode'] == 'reply' &&
			$this->config['reactions_force_reply'] &&
			$event['post_data']['topic_poster'] != $this->user->data['user_id'] &&
			$this->is_valid_forum($event['forum_id']))
		{
			$sql = 'SELECT reaction_id
				FROM ' . $this->reactions_data_table . '
				WHERE post_id = ' . (int)$event['post_data']['topic_first_post_id'] . '
				AND user_id = ' . $this->user->data['user_id'];
			$result = $this->db->sql_query($sql);
			$user_reaction_id = (int)$this->db->sql_fetchfield('reaction_id');
			$this->db->sql_freeresult($result);

			if(!$user_reaction_id)
			{
				trigger_error('NO_POST_REACTION');
			}
		}
	}

	/**
	 * Add sort option to Viewforum
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function viewforum_gen_sort_selects(\phpbb\event\data $event)
	{
		$sort_by_text 	= $event['sort_by_text'];
		$sort_by_sql 	= $event['sort_by_sql'];

		$sort_by_text['rs'] 	= $this->language->lang('REACTIONS_SCORE');
		$sort_by_sql['rs'] 		= ['t.topic_reaction_score', 't.topic_last_post_id'];

		$event['sort_by_text'] 	= $sort_by_text;
		$event['sort_by_sql'] 	= $sort_by_sql;
	}

	/**
	 * Add sort option to Viewtopic
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function viewtopic_gen_sort_selects(\phpbb\event\data $event)
	{
		$sort_by_text 	= $event['sort_by_text'];
		$sort_by_sql 	= $event['sort_by_sql'];
		$join_user_sql	= $event['join_user_sql'];

		$sort_by_text['rs'] 	= $this->language->lang('REACTIONS_SCORE');
		$sort_by_sql['rs'] 		= ['p.post_reaction_score', 'p.post_id'];
		$join_user_sql['rs'] 	= false;

		$event['sort_by_text'] 	= $sort_by_text;
		$event['sort_by_sql'] 	= $sort_by_sql;
		$event['join_user_sql']	= $join_user_sql;
	}

	/**
	 * Assign variables on viewtopic page
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function viewtopic_modify_data(\phpbb\event\data $event)
	{
		if(!count($event['post_list']) || !$this->is_valid_forum($event['forum_id']))
		{
			return;
		}

		// Put reactions on template
		foreach($this->reactions->get_all() as $reaction)
		{
			if(!$reaction->is_enabled())
			{
				continue;
			}

			$this->template->assign_block_vars('reactions', $reaction->to_template());
		}

		// Load post's reactions
		$this->cache_ary = $this->reactions->get_posts_reactions($event['post_list']);

		/**
		 * Adjust score count for users.
		 * Correction of the error with the creation of the index in phpbb posts in large forums.
		 */
		if($this->config['reactions_score_on_profile'])
		{
			$user_cache = $event['user_cache'];
			$user_ary 	= [];

			foreach($user_cache as $user_id => $row)
			{
				if(array_key_exists('reactions_score', $row) && is_null($row['reactions_score']))
				{
					$user_ary[$user_id] = $user_id;
				}
			}

			if(count($user_ary))
			{
				$score_ary	= $this->reactions->get_users_score($user_ary);
				$user_ary 	= [];

				foreach($score_ary as $user_id => $user_score)
				{
					$user_cache[$user_id]['reactions_score'] = $user_score;
					
					$user_ary[$user_id] = [
						'user_reaction_score'	=> $user_score,
					];
				}

				foreach($user_ary as $user_id => $update_ary)
				{
					$sql = 'UPDATE ' . USERS_TABLE . '
						SET ' . $this->db->sql_build_array('UPDATE', $update_ary) . '
						WHERE user_id = ' . $user_id;
					$this->db->sql_query($sql);
				}

				$event['user_cache'] = $user_cache;
			}
		}

		// Template variables and assets
		$service_options = [
			'allowChange'		=> (bool)$this->config['reactions_allow_change'],
			'defaultID'			=> (int)$this->config['reactions_default'],
			'simpleList'		=> !$this->reactions->can_view_usernames(),
		];

		$this->template->assign_vars([
			'REACTIONS_BTN_POSITION'	=> $this->config['reactions_button_position'],
			'REACTIONS_OPTIONS'			=> json_encode($service_options),
		]);

		$this->template->append_asset('css', '@canidev_reactions/reactions.css');
		$this->template->append_asset('js', '@canidev_reactions/reactions.min.js');
	}

	/**
	 * Add reaction variables to post rows in viewtopic
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function viewtopic_modify_row(\phpbb\event\data $event)
	{
		$postrow 	= $event['post_row'];
		$row 		= $event['row'];
		$topic_data	= $event['topic_data'];
		$post_id 	= (int)$row['post_id'];
		$url_params = [];

		if(!$this->is_valid_forum($event['row']['forum_id']))
		{
			return;
		}

		$postrow['REACTION_HAS_MINE'] = false;

		if($this->reactions->get($this->cache_ary[$post_id]['mine'])->is_enabled())
		{
			$postrow['REACTION_HAS_MINE'] = true;
		}

		$postrow['REACTION_LAUNCHER_ICON'] 	= $this->reactions->get_launcher_html($this->cache_ary[$post_id]['mine']);
		$postrow['CAN_USE_REACTIONS'] 		= $this->auth->acl_get('u_reactions');

		// Current user is the author and can't react to their own post
		if($row['user_id'] == $this->user->data['user_id'] && !$this->config['reactions_allow_myself'])
		{
			$postrow['CAN_USE_REACTIONS'] = false;
		}

		// Current post don't match filter
		if(($this->config['reactions_zones'] == constants::ZONE_ONLY_REPLIES && $row['post_id'] == $topic_data['topic_first_post_id']) ||
			($this->config['reactions_zones'] == constants::ZONE_ONLY_FIRST_POST && $row['post_id'] != $topic_data['topic_first_post_id'])
		)
		{
			$postrow['CAN_USE_REACTIONS'] = false;
		}

		// Post is unapproved, reported or deleted
		if(in_array($row['post_visibility'], [ITEM_UNAPPROVED, ITEM_REAPPROVE, ITEM_DELETED]) || $row['post_reported'])
		{
			$postrow['CAN_USE_REACTIONS'] = false;
		}

		// Check if user can view attachments
		if($row['user_id'] != $this->user->data['user_id'] &&
			$this->config['reactions_force_attach'] &&
			(!empty($postrow['S_HAS_ATTACHMENTS']) || strpos($postrow['MESSAGE'], 'inline-attachment') !== false) &&
			!$postrow['REACTION_HAS_MINE'] &&
			$postrow['CAN_USE_REACTIONS'])
		{
			$postrow['S_HAS_ATTACHMENTS'] = false;
			$postrow['SHOW_REACTIONS_ATTACH_MESSAGE'] = true;

			// Remove inline attachments
			if(strpos($postrow['MESSAGE'], 'inline-attachment') !== false)
			{
				$finder = \canidev\core\Dom::stringToXpath($postrow['MESSAGE']);

				if($finder !== false)
				{
					$nodes = $finder->query("//*[contains(@class, 'inline-attachment')]");
					
					foreach($nodes as $node)
					{
						$node->parentNode->removeChild($node);
					}
					
					$postrow['MESSAGE'] = \canidev\core\Dom::xpathToString($finder);
				}
			}

			$url_params['reload'] = 1;
		}

		if($this->config['reactions_score_on_profile'])
		{
			$postrow['POSTER_REACTIONS_SCORE'] = isset($event['user_poster_data']['reactions_score']) ? $event['user_poster_data']['reactions_score'] : 0;
		}

		$postrow['U_REACTION'] = $this->reactions->get_user_link($post_id,  $this->user->data['user_id'], $url_params);

		$postrow['REACTION_LIST_ATTR'] = Dom::arrayToAttr([
			'data-post-id'		=> $post_id,
			'data-title'		=> $this->language->lang('REACTIONS'),
		]);

		if($this->cache_ary[$post_id]['length'] && $this->auth->acl_get('u_reactions_view'))
		{
			$postrow = array_merge($postrow, $this->reactions->parse_post_score_list($this->cache_ary[$post_id]));
		}

		$event['post_row'] = $postrow;
	}

	/**
	 * Assign data to user profile in viewtopic
	 * 
	 * @param \phpbb\event\data 	$event Event data
	 */
	public function viewtopic_user_data(\phpbb\event\data $event)
	{
		$user_cache_data = $event['user_cache_data'];
		$user_cache_data['reactions_score'] = $event['row']['user_reaction_score'];
		$event['user_cache_data'] = $user_cache_data;
	}

	/**
	 * Update reactions username when some username is changed
	 * 
	 * @param \phpbb\event\data $event Event data
	 */
	public function update_username(\phpbb\event\data $event)
	{
		$sql = 'UPDATE ' . $this->reactions_data_table . "
			SET username = '" . $this->db->sql_escape($event['new_name']) . "'
			WHERE username = '" . $this->db->sql_escape($event['old_name']) . "'";
		$this->db->sql_query($sql);
	}

	/**
	 * Check if reactions can be used in selected forum
	 * 
	 * @param int 		$forum_id
	 * @return bool
	 * @access private
	 */
	protected function is_valid_forum($forum_id)
	{
		if(!$this->config['reactions_forums'])
		{
			return true;
		}

		$forum_ary = explode(',', $this->config['reactions_forums']);

		return in_array($forum_id, $forum_ary);
	}
}
