<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\libraries;

use canidev\core\Dom;
use Symfony\Component\DependencyInjection\ContainerInterface;

class reaction_manager
{
	protected $auth;
	protected $cache;
	protected $config;
	protected $container;
	protected $db;
	protected $dispatcher;
	protected $language;
	protected $reactions_table;
	protected $reactions_data_table;
	protected $routing;
	protected $user;

	/** @var reaction[] */
	protected $data 			= [];
	
	protected $launcher_icon 	= null;

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth 									$auth 						Auth instance 
	 * @param \phpbb\cache\driver\driver_interface 				$cache 						Cache instance
	 * @param \phpbb\config\db 									$config 					Config object
	 * @param ContainerInterface 								$container					Service container interface
	 * @param \phpbb\db\driver\driver_interface					$db							DB Object
	 * @param \phpbb\event\dispatcher_interface					$dispatcher					Event dispatcher
	 * @param \phpbb\language\language 							$language					Language object
	 * @param \phpbb\routing\helper 							$routing 					Routing Helper
	 * @param \phpbb\user										$user						User object
	 * @param string 											$reactions_table 			Reactions Table
	 * @param string 											$reactions_data_table 		Reactions Data Table
	 *
	 * @access public
	 */
	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\driver\driver_interface $cache,
		\phpbb\config\db $config,
		ContainerInterface $container,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\event\dispatcher_interface $dispatcher,
		\phpbb\language\language $language,
		\phpbb\routing\helper $routing,
		\phpbb\user $user,
		$reactions_table,
		$reactions_data_table
	)
	{
		$this->auth 				= $auth;
		$this->cache 				= $cache;
		$this->config 				= $config;
		$this->container 			= $container;
		$this->db 					= $db;
		$this->dispatcher			= $dispatcher;
		$this->language 			= $language;
		$this->reactions_table 		= $reactions_table;
		$this->reactions_data_table = $reactions_data_table;
		$this->routing 				= $routing;
		$this->user 				= $user;

		$this->obtain_reactions();
	}

	/**
	 * Check if user can view usernames
	 * 
	 * @return bool
	 */
	public function can_view_usernames()
	{
		return !$this->config['reactions_anonymous'] || $this->auth->acl_get('m_reactions');
	}

	/**
	 * Get singular reaction
	 * 
	 * @param int 		$reaction_id 		Reaction ID
	 * @return reaction
	 */
	public function get($reaction_id = null)
	{
		if(is_numeric($reaction_id) && isset($this->data[$reaction_id]))
		{
			return $this->data[$reaction_id];
		}
		
		return new reaction($this->container);
	}

	/**
	 * Get all reactions
	 * @return reaction[]
	 */
	public function get_all()
	{
		return $this->data;
	}

	/**
	 * Get default launcher icon
	 * 
	 * @param int|false 	$reaction_id
	 * @return string
	 */
	public function get_launcher_html($reaction_id)
	{
		if(!$reaction_id && $this->config['reactions_default'])
		{
			return '<img src="' . $this->get($this->config['reactions_default'])->get_image_url() . '" alt="reaction" />';
		}
		
		return $this->get($reaction_id)->get_launcher_html();
	}

	/**
	 * Get Reactions of posts
	 * 
	 * @param array 	$post_ary 		Array with post IDs
	 * @param bool 		$full_data 		Define if the function return full user data
	 * @return array
	 */
	public function get_posts_reactions($post_ary, $full_data = false)
	{
		$reaction_ary = [];

		if(!is_array($post_ary))
		{
			$post_ary = [$post_ary];
		}

		foreach($post_ary as $post_id)
		{
			$reaction_ary[$post_id] = [
				'post_id'	=> $post_id,
				'list'		=> [],
				'length'	=> 0,
				'mine'		=> false,
			];
		}

		$sql_ary = [
			'SELECT'	=> 'rd.*',
			'FROM'		=> [$this->reactions_data_table => 'rd'],
			'WHERE' 	=> $this->db->sql_in_set('post_id', $post_ary),
			'ORDER_BY'	=> 'reaction_time',
		];

		if($full_data)
		{
			$sql_ary['SELECT'] .= ', u.*';
			$sql_ary['LEFT_JOIN'] = [
				[
					'FROM'	=> [USERS_TABLE => 'u'],
					'ON'	=> 'u.user_id = rd.user_id'
				]
			];
		}

		// Ignore disabled reactions
		foreach($this->data as $reaction_id => $reaction)
		{
			$ignore_ary = [];

			if(!$reaction->is_enabled())
			{
				$ignore_ary[] = $reaction_id;
			}

			if(sizeof($ignore_ary))
			{
				$sql_ary['WHERE'] .= ' AND ' . $this->db->sql_in_set('rd.reaction_id', $ignore_ary, true);
			}
		}

		$sql = $this->db->sql_build_query('SELECT', $sql_ary);
		$result = $this->db->sql_query($sql);
		while($row = $this->db->sql_fetchrow($result))
		{
			$post_id 		= (int)$row['post_id'];
			$reaction_id 	= (int)$row['reaction_id'];

			if(!isset($reaction_ary[$post_id]['list'][$reaction_id]))
			{
				$reaction_ary[$post_id]['list'][$reaction_id] = [];
			}

			$row['reaction_time'] = (int)$row['reaction_time'];

			$reaction_ary[$post_id]['list'][$reaction_id][] = $row;

			if($row['user_id'] == $this->user->data['user_id'])
			{
				$reaction_ary[$post_id]['mine'] = $reaction_id;
			}

			$reaction_ary[$post_id]['length']++;
		}
		$this->db->sql_freeresult($result);

		/**
		 * @event reactions.get_posts_reactions_after
		 * @var 	array 	reaction_ary 	Array with reaction's data
		 * @var 	array 	post_ary 		Array with post IDs
	 	 * @var 	bool 	full_data 		Define if the function return full user data
		 * @since 1.0.0
		 */
		$vars = ['reaction_ary', 'post_ary', 'full_data'];
		extract($this->dispatcher->trigger_event('reactions.get_posts_reactions_after', compact($vars)));

		return $reaction_ary;
	}

	/**
	 * Get total score of reactions give to users
	 * 
	 * @param array 	$user_ary 		User IDs
	 * @return array
	 */
	public function get_users_score($user_ary)
	{
		$score_ary = [];

		$sql = 'SELECT SUM(post_reaction_score) as score, poster_id
			FROM ' . POSTS_TABLE . '
			WHERE ' . $this->db->sql_in_set('poster_id', $user_ary) . '
			GROUP BY poster_id';
		$result = $this->db->sql_query($sql);
		while($row = $this->db->sql_fetchrow($result))
		{
			$score_ary[$row['poster_id']] = $row['score'];
		}
		$this->db->sql_freeresult($result);

		foreach($user_ary as $user_id)
		{
			if(!isset($score_ary[$user_id]))
			{
				$score_ary[$user_id] = 0;
			}
		}

		return $score_ary;
	}

	/**
	 * Parse post score
	 * 
	 * @param array 		$reactions_data 		Array with reactions
	 * @return array
	 */
	public function parse_post_score_list($reactions_data)
	{
		$output = $scores_usernames = [];

		// Current user is always the first
		if(isset($reactions_data['mine']) && $reactions_data['mine'] !== false && $this->get($reactions_data['mine'])->is_enabled())
		{
			$scores_usernames[] = $this->user->data['username'];
		}

		$output['reaction_scores'] = [];

		foreach($this->data as $reaction_id => $reaction)
		{
			if(!isset($reactions_data['list'][$reaction_id]) || !$reaction->is_enabled())
			{
				continue;
			}

			$reactions = $reactions_data['list'][$reaction_id];

			$output['reaction_scores'][] = [
				'S_REACTION_IMAGE'	=> $reaction->get_image_url(),
				'S_REACTION_TITLE'	=> $reaction->get_title(),
				'S_TITLE'			=> $reaction->get_title() . ' (' . count($reactions) . ')',
				'U_VIEW'			=> $this->routing->route('canidev_reactions_controller', [
					'mode'		=> 'view',
					'post'		=> $reactions_data['post_id'],
					'reaction'	=> $reaction->get_id(),
				]),
			];

			if(count($scores_usernames) < 2)
			{
				foreach($reactions as $row)
				{
					if(count($scores_usernames) >= 2)
					{
						break;
					}

					// Current user will be shown as first name, ignore here
					if($row['user_id'] != $this->user->data['user_id'])
					{
						$scores_usernames[] = $row['username'];
					}
				}
			}
		}

		if(!$this->can_view_usernames())
		{
			$output['REACTION_SCORE_LABEL'] = $this->language->lang('REACTION_SCORE_LABEL_ANONYMOUS', $reactions_data['length']);
		}
		else
		{
			if($reactions_data['length'] < 4)
			{
				$key = ($reactions_data['length'] == 3) ? 'REACTION_SCORE_LABEL_COUNT' : 'REACTION_SCORE_LABEL_SIMPLE';
				
				$output['REACTION_SCORE_LABEL'] = $this->language->lang($key, implode(', ', $scores_usernames), 1);
			}
			else
			{
				$output['REACTION_SCORE_LABEL'] = $this->language->lang(
					'REACTION_SCORE_LABEL_COUNT',
					implode(', ', $scores_usernames),
					$reactions_data['length'] - count($scores_usernames)
				);
			}

			$output['U_REACTION_SCORE_ALL'] = $this->routing->route('canidev_reactions_controller', [
				'mode'		=> 'view',
				'post'		=> $reactions_data['post_id'],
			]);
		}

		return $output;
	}

	/**
	 * Get user link for reaction
	 * 
	 * @param int 	$post_id 		ID of target post
	 * @param int	$user_id 		ID of the user
	 * @param array	$params 		Additional params for the url
	 * @return string
	 */
	public function get_user_link($post_id, $user_id, $params = null)
	{
		$query = [
			'post'		=> $post_id,
			'user'		=> $user_id,
		];

		if(is_array($params))
		{
			$query = array_merge($query, $params);
		}
		
		return $this->routing->route('canidev_reactions_controller', $query);
	}

	/**
	 * Resync the score of posts
	 * 
	 * @param int|array 	$post_ids 		Target Post IDs
	 */
	public function resync_post_score($post_ids)
	{
		if(!is_array($post_ids))
		{
			$post_ids = [$post_ids];
		}

		if(!count($post_ids))
		{
			return;
		}

		$count_ary 			= [];
		$posts_with_topic 	= [];

		$sql = 'SELECT reaction_id, post_id
			FROM ' . $this->reactions_data_table . '
			WHERE ' . $this->db->sql_in_set('post_id', $post_ids);
		$result = $this->db->sql_query($sql);
		while($row = $this->db->sql_fetchrow($result))
		{
			$post_id = (int)$row['post_id'];
			
			if(!isset($count_ary[$post_id]))
			{
				$count_ary[$post_id] = 0;
			}

			$count_ary[$post_id] += $this->get($row['reaction_id'])->get_score();
		}
		$this->db->sql_freeresult($result);

		// Get affected topics
		$sql = 'SELECT topic_id, topic_first_post_id
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->db->sql_in_set('topic_first_post_id', $post_ids);
		$result = $this->db->sql_query($sql);
		while($row = $this->db->sql_fetchrow($result))
		{
			$posts_with_topic[] = (int)$row['topic_first_post_id'];
		}
		$this->db->sql_freeresult($result);

		// Update scores
		$this->db->sql_transaction('begin');
		
		foreach($count_ary as $post_id => $score)
		{
			$sql = 'UPDATE ' . POSTS_TABLE . '
				SET post_reaction_score = ' . $score . '
				WHERE post_id = ' . $post_id;
			$this->db->sql_query($sql);

			if(in_array($post_id, $posts_with_topic))
			{
				$sql = 'UPDATE ' . TOPICS_TABLE . '
					SET topic_reaction_score = ' . $score . '
					WHERE topic_first_post_id = ' . $post_id;
				$this->db->sql_query($sql);
			}
		}

		$this->db->sql_transaction('commit');
	}

	/**
	 * Get reactions from cache or db
	 */
	protected function obtain_reactions()
	{
		if(($this->data = $this->cache->get('_reactions')) === false)
		{
			$this->data = [];

			$sql = 'SELECT *
				FROM ' . $this->reactions_table . '
				ORDER BY reaction_order';
			$result = $this->db->sql_query($sql);
			while($row = $this->db->sql_fetchrow($result))
			{
				$reaction_id = (int)$row['reaction_id'];
				$this->data[$reaction_id] = $row;
			}
			$this->db->sql_freeresult($result);

			$this->cache->put('_reactions', $this->data);
		}

		foreach($this->data as $reaction_id => $row)
		{
			if(is_array($row))
			{
				$this->data[$reaction_id] = new reaction($this->container, $row);
			}
		}
	}
}
