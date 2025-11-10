<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\controller;

class admin_manage extends \canidev\core\controller\acp
{
	protected $db;
	protected $dispatcher;
	protected $json;
	protected $media;
	protected $reactions_table;
	protected $reactions_data_table;

	/**
	 * Constructor
	 *
	 * @param \Symfony\Component\DependencyInjection\ContainerInterface 	$container				Service container interface
	 * @param \phpbb\db\driver\driver_interface								$db						DB Object
	 * @param \phpbb\event\dispatcher_interface								$dispatcher				Event dispatcher
	 * @param string 														$reactions_table 		Reactions Table
	 * @param string 														$reactions_data_table 	Reactions Data Table
	 *
	 * @access public
	 */
	public function __construct(
		\Symfony\Component\DependencyInjection\ContainerInterface $container,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\event\dispatcher_interface $dispatcher,
		$reactions_table,
		$reactions_data_table)
	{
		$this->db 			= $db;
		$this->dispatcher	= $dispatcher;
		$this->json 		= \canidev\core\JsonResponse::getInstance();
		$this->media 		= \canidev\core\Media::getInstance($container, 'reactions');

		$this->reactions_table 			= $reactions_table;
		$this->reactions_data_table 	= $reactions_data_table;

		$this->media
			->setRefererExt('reactions')
			->setUploadPath('files/reactions/')
			->preload()
			->performActions();
	}

	/**
	 * Display the controller
	 */
	public function display()
	{
		$this->pageHeader('canidev/reactions');

		$this->language->add_lang('acp', 'canidev/reactions');

		$this->tpl_name		= 'acp_reactions_manage';
		$this->page_title	= 'ACP_REACTIONS_MANAGE';

		$action 		= $this->request->variable('action', '');
		$submit 		= $this->request->is_set_post('submit');
		$reaction_id 	= $this->request->variable('id', 0);
		$form_key 		= 'acp_reactions';
		$error			= [];

		add_form_key($form_key);

		switch($action)
		{
			case 'edit':
				$sql = 'SELECT *
					FROM ' . $this->reactions_table . '
					WHERE reaction_id = ' . $reaction_id;
				$result = $this->db->sql_query($sql);
				$cfg_array = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				if(!$cfg_array)
				{
					trigger_error('ERROR_REACTION_NO_EXISTS');
				}

			// no break

			case 'add':
				if($action == 'add')
				{
					$cfg_array = [
						'reaction_enabled'	=> 1,
					];
				}
				
				$display_vars = [
					'legend1'					=> 'OPTIONS',
					'reaction_title'			=> ['lang' => 'REACTION_TITLE',			'validate' => 'string:1:250',		'type' => 'text:40:250'],
					'reaction_enabled'			=> ['lang' => 'REACTION_ENABLED',		'validate' => 'bool',				'type' => 'radio:yes_no'],
					'reaction_score'			=> ['lang' => 'REACTION_SCORE',			'type' => 'custom',					'function' => [$this, 'build_template'],	'params' => ['{KEY}', '{CONFIG_VALUE}'],		'default' => ''],
					'reaction_color'			=> ['lang' => 'REACTION_COLOR', 		'validate' => 'string:0:10', 		'type' => 'custom',							'function' => [$this, 'build_template'],		'params' => ['{KEY}', '{CONFIG_VALUE}'],				'default' => ''],
					'reaction_image'			=> ['lang' => 'REACTION_IMAGE',			'validate' => 'string:1:250', 		'type' => 'custom',							'function' => [$this->media, 'buildSelector'],	'params' => ['{KEY}', 'image', '{CONFIG_VALUE}', 1],	'default' => ''],
				];

				if($submit)
				{
					$cfg_array = $this->request->variable('config', ['' => ''], true);

					// We validate the complete config if whished
					validate_config_vars($display_vars, $cfg_array, $error);
					
					if(!check_form_key($form_key))
					{
						$error[] = $this->language->lang('FORM_INVALID');
					}

					if(!is_numeric($cfg_array['reaction_score']))
					{
						$error[] = $this->language->lang('ERROR_SCORE_INVALID');
					}
				}

				/**
				 * @event reactions.acp_manage_edit_before
				 * @var	array	display_vars	Array of config values to display and process
				 * @var	boolean	submit			Do we display the form or process the submission
				 * @var	array	cfg_array 		Array with data
				 * @var	array 	error 			Array with data errors 
				 * @since 1.0.0
				 */
				$vars 	= ['display_vars', 'submit', 'cfg_array', 'error'];
				extract($this->dispatcher->trigger_event('reactions.acp_manage_edit_before', compact($vars)));

				// Do not write values if there is an error
				if(count($error))
				{
					$submit = false;
				}

				if($submit)
				{
					if($action == 'add')
					{
						// Set the correct reaction position
						$sql = 'SELECT MAX(reaction_order) AS max_order
							FROM ' . $this->reactions_table;
						$result = $this->db->sql_query($sql);
						$cfg_array['reaction_order'] = (int)$this->db->sql_fetchfield('max_order') + 1;
						$this->db->sql_freeresult($result);
						
						$sql = 'INSERT INTO ' . $this->reactions_table . ' ' . $this->db->sql_build_array('INSERT', $cfg_array);
					}
					else
					{
						$sql = 'UPDATE ' . $this->reactions_table . '
							SET ' . $this->db->sql_build_array('UPDATE', $cfg_array) . '
							WHERE reaction_id = ' . $reaction_id;	
					}

					$this->db->sql_query($sql);
					$this->cache->destroy('_reactions');

					/**
					 * @event reactions.acp_manage_edit_after
					 * @var	array	display_vars	Array of config values to display and process
					 * @var	array	cfg_array 		Array with data
					 * @since 1.0.0
					 */
					$vars 	= ['display_vars', 'cfg_array'];
					extract($this->dispatcher->trigger_event('reactions.acp_manage_edit_after', compact($vars)));
					
					trigger_error($this->language->lang('CONFIG_UPDATED') . adm_back_link($this->form_action));
				}

				$this->template->assign_vars([
					'IN_REACTION'		=> true,
					'S_ERROR'			=> count($error) ? implode('<br />', $error) : '',
					'S_HIDDEN_FIELDS'	=> build_hidden_fields([
						'action'	=> $action,
						'id'		=> $reaction_id,
					]),

					'U_BACK'	=> $this->form_action,
				]);

				$this->new_config = $cfg_array;

				$this->display_vars($display_vars);
			break;

			case 'change_status':
				$status = $this->request->variable('status', 0);

				$sql = 'UPDATE ' . $this->reactions_table . '
					SET reaction_enabled = ' . $status . '
					WHERE reaction_id = ' . $reaction_id;
				$this->db->sql_query($sql);

				$this->cache->destroy('_reactions');

				/**
				 * @event reactions.acp_manage_change_status
				 * @var	int 	reaction_id 	ID of the reaction
				 * @var	boolean	status			New state for the reaction
				 * @since 1.0.0
				 */
				$vars = ['reaction_id', 'status'];
				extract($this->dispatcher->trigger_event('reactions.acp_manage_change_status', compact($vars)));

				$this->json->send();
			break;

			case 'delete':
				if(confirm_box(true))
				{
					$this->db->sql_transaction('begin');
					
					$sql = 'DELETE FROM ' . $this->reactions_table . '
						WHERE reaction_id = ' . $reaction_id;
					$this->db->sql_query($sql);

					$sql = 'DELETE FROM ' . $this->reactions_data_table . '
						WHERE reaction_id = ' . $reaction_id;
					$this->db->sql_query($sql);

					$this->db->sql_transaction('commit');

					$this->cache->destroy('_reactions');

					/**
					 * @event reactions.acp_manage_delete
					 * @var	int 	reaction_id 	ID of the reaction
					 * @since 1.0.0
					 */
					$vars = ['reaction_id'];
					extract($this->dispatcher->trigger_event('reactions.acp_manage_delete', compact($vars)));

					$this->json->send([
						'action'		=> 'delete',
						'reactionID' 	=> $reaction_id
					]);
				}
				else
				{
					confirm_box(
						false,
						$this->language->lang('CONFIRM_OPERATION'),
						build_hidden_fields([
							'id' 	=> $reaction_id,
						]),
						'@canidev_core/confirmbox.html'
					);
				}
			break;

			case 'move':
				$items = $this->request->variable('item', [0]);
				$order = 1;

				foreach($items as $reaction_id)
				{
					$sql = 'UPDATE ' . $this->reactions_table . '
						SET reaction_order = ' . $order . '
						WHERE reaction_id = ' . $reaction_id;
					$this->db->sql_query($sql);

					$order++;
				}

				$this->cache->destroy('_reactions');

				$this->json->send();
			break;

			case 'set_default':
				if($this->config['reactions_default'] == $reaction_id)
				{
					$reaction_id = 0;
				}
				
				$this->config->set('reactions_default', $reaction_id);
				$this->json->send([
					'reactionID' 	=> $reaction_id
				]);
			break;
		}

		$sql = 'SELECT *
			FROM ' . $this->reactions_table . '
			ORDER BY reaction_order';
		$result = $this->db->sql_query($sql);
		while($row = $this->db->sql_fetchrow($result))
		{
			$title 			= $this->language->lang($row['reaction_title']);
			$score 			= intval($row['reaction_score']);
			$score_label 	= '';

			if($row['reaction_color'])
			{
				$title = '<span style="color:' . $row['reaction_color'] . ';">' . $title . '</span>';
			}

			if($this->language->is_set(['scores', $score]))
			{
				$score_label = $this->language->lang(['scores', $score]);
			}
			else
			{
				$score_label = $this->language->lang('SCORE_CUSTOM_VALUE', (($score > 0) ? '+' : '') . $score);
			}

			$this->template->assign_block_vars('reactions', [
				'ID'				=> $row['reaction_id'],
				'IS_DEFAULT'		=> ($row['reaction_id'] == $this->config['reactions_default']),
				'IS_ENABLED'		=> (bool)$row['reaction_enabled'],
				'S_HIDDEN_FIELDS'	=> build_hidden_fields([
					'item[]'	=> $row['reaction_id'],
				]),
				'S_IMAGE_URL'	=> $this->media->getFullPath($row['reaction_image'], true),
				'S_SCORE'		=> $score_label,
				'S_TITLE'		=> $title,
				'S_TITLE_RAW'	=> $this->language->lang($row['reaction_title']),
				'U_DEFAULT'		=> $this->form_action . '&amp;action=set_default&amp;id=' . $row['reaction_id'],
				'U_DELETE'		=> $this->form_action . '&amp;action=delete&amp;id=' . $row['reaction_id'],
				'U_EDIT'		=> $this->form_action . '&amp;action=edit&amp;id=' . $row['reaction_id'],
				'U_STATUS'		=> $this->form_action . '&amp;action=change_status&amp;id=' . $row['reaction_id'],
			]);
		}
		$this->db->sql_freeresult($result);

		$this->template->assign_vars([
			'U_REACTION_ADD'	=> $this->form_action . '&amp;action=add',
		]);

		// Assets
		$this->template->append_asset('js', '@canidev_core/js/jquery-ui.min.js');
		$this->template->append_asset('js', '@canidev_reactions/reactions.min.js');
		$this->template->append_asset('css', '@canidev_reactions/reactions-acp.css', false, 'admin');
	}

	/**
	 * Build template for inputs
	 * 
	 * @param string 	$key
	 * @param string 	$value
	 * 
	 * @return string
	 */
	public function build_template($key, $value)
	{
		switch($key)
		{
			case 'reaction_color':
				return '<div class="cbb-group reaction-color-selector">
					<input type="text" class="cbb-inputbox" size="20" maxlength="20" name="config[reaction_color]" value="' . $value . '" />
					<button class="cbb-btn" title="' . $this->language->lang('SELECT_COLOUR') . '"><span class="color-preview"></span></button>
				</div>';

			case 'reaction_score':
				$output 		= '';
				$option_checked = false;

				$options = $this->language->lang_raw('scores');
				$options['custom']	= $this->language->lang('SCORE_CUSTOM');

				$output .= '<ul class="reaction-score-selector">';

				foreach($options as $option_value => $option_title)
				{
					$checked = '';

					if($value != '' && (
						($option_value == $value) ||
						($option_value === 'custom' && !$option_checked)
					))
					{
						$checked = ' checked = "checked"';
						$option_checked = true;
					}

					$output .= '<li><label><input type="radio" name="score" value="' . $option_value . '"' . $checked . ' /> ' . $option_title . '</label>';

					if($option_value === 'custom')
					{
						$output .= '<br /><input type="number" name="score_custom" ' . (($checked != '') ? 'value="' . $value . '"' : 'class="cbb-helper-hidden" value="1"') . ' size="20" />';
					}
					
					$output .= '</li>';
				}

				$output .= '</ul>
					<input type="hidden" name="config[reaction_score]" value="' . $value . '" />';

				return $output;

			default:
				return '';
		}
	}
}
