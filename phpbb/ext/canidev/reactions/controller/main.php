<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\controller;

use canidev\core\Dom;
use \canidev\reactions\libraries\constants;

/**
 * Main controller
 */
class main
{
	protected $auth;
	protected $config;
	protected $db;
	protected $dispatcher;
	protected $language;
	protected $notifications;
	protected $path_helper;
	protected $phpbb_root_path;
	protected $php_ext;
	protected $reactions;
	protected $reactions_data_table;
	protected $request;
	protected $response;
	protected $template;
	protected $user;

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth 										$auth						Authentication object
	 * @param \phpbb\config\db 										$config						Config Object
	 * @param \phpbb\db\driver\driver_interface						$db							DB Object
	 * @param \phpbb\event\dispatcher_interface						$dispatcher					Event dispatcher
	 * @param \phpbb\language\language								$language					Language Object
	 * @param \canidev\reactions\libraries\notification_manager 	$notification_manager 		Notification Manager
	 * @param \phpbb\path_helper 									$path_helper 				Path Helper
	 * @param \canidev\reactions\libraries\reaction_manager 		$reactions 					Reaction Manager
	 * @param \phpbb\request\request 								$request					Request object
	 * @param \phpbb\template\template 								$template					Template object
	 * @param \phpbb\user											$user						User object
	 * @param string												$root_path					phpBB root path
	 * @param string												$php_ext					phpEx
	 * @param string 												$reactions_data_table 		Reactions Data Table
	 *
	 * @access public
	 */
	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\db $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\event\dispatcher_interface $dispatcher,
		\phpbb\language\language $language,
		\canidev\reactions\libraries\notification_manager $notification_manager,
		\phpbb\path_helper $path_helper,
		\canidev\reactions\libraries\reaction_manager $reactions,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		$root_path,
		$php_ext,
		$reactions_data_table
	)
	{
		$this->auth					= $auth;
		$this->config 				= $config;
		$this->db					= $db;
		$this->dispatcher 			= $dispatcher;
		$this->language				= $language;
		$this->notifications 		= $notification_manager;
		$this->path_helper 			= $path_helper;
		$this->reactions 			= $reactions;
		$this->reactions_data_table = $reactions_data_table;
		$this->request				= $request;
		$this->response 			= \canidev\core\JsonResponse::getInstance();
		$this->template 			= $template;
		$this->user					= $user;
		
		$this->phpbb_root_path	= $root_path;
		$this->php_ext			= $php_ext;
	}

	/**
	 * Display the controller
	 */
	public function display()
	{
		$mode = $this->request->variable('mode', '');

		if(!$this->request->is_ajax())
		{
			trigger_error('FORM_INVALID');
		}

		switch($mode)
		{
			case 'add_reaction':
			case 'remove_reaction':
				$reaction_id 	= $this->request->variable('reaction', 0);
				$post_id 		= $this->request->variable('post', 0);
				$user_id 		= $this->request->variable('user', 0);
				$score_amount 	= 0;
				$score_row 		= [];

				if(!(($user_id == $this->user->data['user_id'] && $this->auth->acl_get('u_reactions'))
					|| $this->auth->acl_get('m_reactions')))
				{
					trigger_error('NO_AUTH_OPERATION');
				}

				// Get post data
				$sql = 'SELECT p.post_id, p.topic_id, p.poster_id, p.post_text, p.bbcode_uid, p.bbcode_bitfield,
					t.topic_first_post_id, u.user_reaction_score AS current_score
					FROM ' . POSTS_TABLE . ' p
					LEFT JOIN ' . TOPICS_TABLE . ' t ON(t.topic_id = p.topic_id)
					LEFT JOIN ' . USERS_TABLE . ' u on(u.user_id = p.poster_id)
					WHERE p.post_id = ' . $post_id;
				$result = $this->db->sql_query($sql);
				$post_data = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				if(!$post_data)
				{
					trigger_error('FORM_INVALID');
				}

				// Get current reaction if exists
				$sql = 'SELECT rd.*
					FROM ' . $this->reactions_data_table . ' rd
					WHERE post_id = ' . $post_id . '
					AND user_id = ' . $user_id;
				$result = $this->db->sql_query($sql);
				$reaction_data = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				if($reaction_data && !$this->config['reactions_allow_change'])
				{
					trigger_error('NO_AUTH_OPERATION');
				}

				if($mode == 'add_reaction')
				{
					$reaction = $this->reactions->get($reaction_id);

					if($post_data['poster_id'] == $user_id && !$this->config['reactions_allow_myself'])
					{
						trigger_error('NO_AUTH_OPERATION');
					}

					if(!$reaction->has_data())
					{
						trigger_error('FORM_INVALID');
					}

					// Get username
					$sql = 'SELECT username
						FROM ' . USERS_TABLE . '
						WHERE user_id = ' . $user_id;
					$result = $this->db->sql_query($sql);
					$username = (string)$this->db->sql_fetchfield('username');
					$this->db->sql_freeresult($result);

					// New reaction of the user
					$sql_ary = [
						'reaction_id'	=> $reaction_id,
						'post_id'		=> $post_id,
						'user_id'		=> $user_id,
						'username'		=> $username,
						'reaction_time'	=> time(),
					];
					
					if($reaction_data)
					{
						// Update reaction
						$sql = 'UPDATE ' . $this->reactions_data_table . '
							SET ' . $this->db->sql_build_array('UPDATE', $sql_ary) . '
							WHERE post_id = ' . $post_id . '
							AND user_id = ' . $user_id;
						$this->db->sql_query($sql);

						// Remove the previous score from post/topic
						$score_amount -= $this->reactions->get($reaction_data['reaction_id'])->get_score();
					}
					else
					{
						// Add new reaction
						$sql = 'INSERT INTO ' . $this->reactions_data_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary);
						$this->db->sql_query($sql);

						if($post_data['poster_id'] != $this->user->data['user_id'])
						{
							$this->notifications->add_notification('canidev.reactions.notification.post', [
								'item_id'			=> $post_id,
								'item_parent_id'	=> $post_data['topic_id'],
								'user_id'			=> $user_id,
								'author_id'			=> (int)$post_data['poster_id'],
								'item_resume'		=> $this->notifications->generate_resume($post_data['post_text'], $post_data['bbcode_uid'], $post_data['bbcode_bitfield']),
							]);
						}
					}

					$score_row += [
						'REACTION_HAS_MINE'			=> true,
						'REACTION_LAUNCHER_ICON' 	=> $reaction->get_launcher_html(),
					];

					// Add the new score to post/topic
					$score_amount += $reaction->get_score();
				}
				else
				{
					if(!$reaction_data)
					{
						trigger_error('FORM_INVALID');
					}

					$sql = 'DELETE FROM ' . $this->reactions_data_table . '
						WHERE post_id = ' . $post_id . '
						AND user_id = ' . $user_id;
					$this->db->sql_query($sql);

					$score_row += [
						'REACTION_LAUNCHER_ICON' 	=> $this->reactions->get_launcher_html(false),
					];

					$this->response->add([
						'reactionID'	=> $reaction_data['reaction_id'],
					]);

					// Remove score to post/topic
					$score_amount -= $this->reactions->get($reaction_data['reaction_id'])->get_score();

					$this->notifications->delete_notification('canidev.reactions.notification.post', $post_id, $post_data['topic_id'], $user_id);
				}

				// Update user score
				if(!is_null($post_data['current_score']))
				{
					$sql = 'UPDATE ' . USERS_TABLE . '
						SET user_reaction_score = user_reaction_score + ' . $score_amount . '
						WHERE user_id = ' . (int)$post_data['poster_id'];
					$this->db->sql_query($sql);
				}

				// Update post score
				$sql = 'UPDATE ' . POSTS_TABLE . '
					SET post_reaction_score = post_reaction_score + ' . $score_amount . '
					WHERE post_id = ' . $post_id;
				$this->db->sql_query($sql);

				// Update topic score
				if($post_data['post_id'] == $post_data['topic_first_post_id'])
				{
					$sql = 'UPDATE ' . TOPICS_TABLE . '
						SET topic_reaction_score = topic_reaction_score + ' . $score_amount . '
						WHERE topic_id = ' . (int)$post_data['topic_id'];
					$this->db->sql_query($sql);
				}

				/**
				 * @event reactions.add_remove_after
				 * @var	string 		mode			add_reaction / remove_reaction
				 * @var	int			post_id 		Post ID
				 * @var int 		user_id 		User ID
				 * @var int 		reaction_id		Reaction ID (only for "add_reaction" mode) 
				 * @since 1.0.0
				 */
				$vars = ['mode', 'post_id', 'user_id', 'reaction_id'];
				extract($this->dispatcher->trigger_event('reactions.add_remove_after', compact($vars)));

				$score_row += [
					'CAN_USE_REACTIONS'		=> $this->auth->acl_get('u_reactions'),
					'POST_ID'				=> $post_id,
					'REACTION_LIST_ATTR'	=> Dom::arrayToAttr([
						'data-post-id'		=> $post_id,
						'data-title'		=> $this->language->lang('REACTIONS'),
					]),
					'U_REACTION' 			=> $this->reactions->get_user_link($post_id,  $user_id),
				];

				// Put reactions on template
				foreach($this->reactions->get_all() as $reaction)
				{
					if(!$reaction->is_enabled())
					{
						continue;
					}

					$this->template->assign_block_vars('reactions', $reaction->to_template());
				}

				if($this->auth->acl_get('u_reactions_view'))
				{
					$reaction_list = $this->reactions->get_posts_reactions($post_id);

					$score_row = array_merge($score_row, $this->reactions->parse_post_score_list($reaction_list[$post_id]));
				}

				$this->template->assign_block_vars('score_rows', $score_row);

				$this->template->set_filenames([
					'launcher'		=> '@canidev_reactions/launcher_button.html',
					'score_list'	=> '@canidev_reactions/score_list.html',
				]);

				$this->response->add([
					'postID'	=> $post_id,
					'userID'	=> $user_id,
					'button'	=> ($user_id == $this->user->data['user_id']) ? $this->template->assign_display('launcher') : null,
					'list'		=> $this->template->assign_display('score_list'),
					'reload'	=> $this->request->variable('reload', 0),
				]);
			break;

			case 'view':
				$post_id 			= $this->request->variable('post', 0);
				$reaction_active 	= $this->request->variable('reaction', 0);

				$reactions 		= $this->reactions->get_all();
				$reaction_list 	= $this->reactions->get_posts_reactions($post_id, true);
				
				$tabs = [
					0 	=> [
						'label' => $this->language->lang('REACTIONS_ALL'),
						'items'	=> [],
					],
				];

				// Build tabs base
				foreach($reactions as $id => $reaction)
				{
					if(!$reaction->is_enabled())
					{
						continue;
					}

					$tabs[$id] = [
						'items'		=> [],
						'color'		=> $reaction->get_color(),
						'label'		=> $reaction->get_title(),
						'image'		=> $reaction->get_image_url(),
					];
				}

				// Sort reactions
				$reactions_track = [];

				foreach($reaction_list[$post_id]['list'] as $reaction_id => $rows)
				{
					foreach($rows as $row)
					{
						$row['reaction_id'] = $reaction_id;
						$key 				= ($this->config['reactions_list_order'] == constants::ORDER_USERNAME) ? $row['username_clean'] : $row['reaction_time'];

						$reactions_track[$key . '-' . $reaction_id] = $row;
					}
				}

				unset($reaction_list);

				if($this->config['reactions_list_order'] == constants::ORDER_USERNAME)
				{
					ksort($reactions_track);
				}
				else
				{
					krsort($reactions_track);
				}

				// Output to template
				foreach($reactions_track as $row)
				{
					$user_rank_data = $this->get_user_rank($row);
					$can_delete 	= ($this->config['reactions_allow_change'] && $row['user_id'] == $this->user->data['user_id'] && $this->auth->acl_get('u_reactions')) || $this->auth->acl_get('m_reactions');
					
					$ary = [
						'S_ATTR'			=> \canidev\core\Dom::arrayToAttr([
							'data-reaction'		=> $row['reaction_id'],
							'data-user'			=> $row['user_id'],
							'data-post'			=> $row['post_id'],
						]),

						'S_REACTION_IMAGE'	=> $reactions[$row['reaction_id']]->get_image_url(),
						'S_REACTION_TIME'	=> $this->user->format_date($row['reaction_time']),
						'S_REACTION_TITLE'	=> $reactions[$row['reaction_id']]->get_title(),
						'S_USERNAME_FULL'	=> $this->fix_attr_path('href', get_username_string('full', $row['user_id'], $row['username'], $row['user_colour'])),
						'USER_AVATAR'		=> $this->fix_attr_path('src', phpbb_get_user_avatar($row)),
						'USER_RANK_TITLE'	=> $user_rank_data['title'],
						'USER_RANK_IMG'		=> $user_rank_data['img'],

						'U_DELETE'			=> !$can_delete ? '' : $this->reactions->get_user_link(
							$row['post_id'],
							$row['user_id'],
							['mode' => 'remove_reaction', 	'confirm' => 1]
						),
					];

					/**
					 * @event reactions.main_view_row
					 * @var	array	ary				Template block array of the reaction
					 * @var	array	row 			Array with original reaction data
					 * @since 1.0.0
					 */
					$vars = ['ary', 'row'];
					extract($this->dispatcher->trigger_event('reactions.main_view_row', compact($vars)));

					$tabs[0]['items'][] = $ary;
					$tabs[$row['reaction_id']]['items'][] = $ary;
				}

				foreach($tabs as $reaction_id => $data)
				{
					$item_count = count($data['items']);

					if(!$item_count)
					{
						continue;
					}

					$this->template->assign_block_vars('tab', [
						'IS_ACTIVE'		=> ($reaction_active == $reaction_id),
						'S_COLOR'		=> isset($data['color']) ? $data['color'] : '',
						'S_COUNT'		=> $item_count,
						'S_ID'			=> $reaction_id,
						'S_IMAGE'		=> isset($data['image']) ? $data['image'] : '',
						'S_TITLE'		=> $data['label'],
						'items'			=> $data['items'],
					]);
				}

				$this->template->set_filenames([
					'dialog'	=> $this->reactions->can_view_usernames() ? '@canidev_reactions/reactions_list.html' : '@canidev_reactions/reactions_list_simple.html'
				]);

				$this->response->addHtml($this->template->assign_display('dialog'));
			break;
		}

		$this->response->send();
	}

	/**
	 * Get user rank with fixed url
	 * 
	 * @param array 	$data 		User data
	 * 
	 * @return array
	 * @access private
	 */
	protected function get_user_rank($data)
	{
		if(!function_exists('phpbb_get_user_rank'))
		{
			include($this->phpbb_root_path . 'includes/functions_display.' . $this->php_ext);
		}
		
		$rank_data = phpbb_get_user_rank($data, $data['user_posts']);

		if($rank_data['img_src'])
		{
			$old_img_src = $rank_data['img_src'];
			
			$rank_data['img_src'] = $this->path_helper->remove_web_root_path($rank_data['img_src']);
			$rank_data['img_src'] = generate_board_url() . '/' . $rank_data['img_src'];
			$rank_data['img'] = str_replace($old_img_src, $rank_data['img_src'], $rank_data['img']);
		}

		return $rank_data;
	}

	/**
	 * Fix incorrect root path inside html attribute
	 * 
	 * @param string 	$attr_name		Name of the attribute
	 * @param string 	$string 		HTML string
	 * 
	 * @return string
	 * @access private
	 */
	protected function fix_attr_path($attr_name, $string)
	{
		return preg_replace('#' . $attr_name . '="' . $this->path_helper->get_web_root_path() . '#', $attr_name . '="' . generate_board_url() . '/', $string);
	}
}
