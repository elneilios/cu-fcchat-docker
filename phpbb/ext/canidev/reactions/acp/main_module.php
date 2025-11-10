<?php
/**
 * @package cBB Reactions
 * @version 1.0.4 01/04/2025
 *
 * @copyright (c) 2025 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\reactions\acp;

class main_module
{
	public $u_action;

	public $page_title;
	public $tpl_name;

	public function main($id, $mode)
	{
		global $phpbb_container;

		$controller = $phpbb_container->get('canidev.reactions.controller.admin.' . $mode);

		$controller
			->set_form_action($this->u_action)
			->display();
		
		$this->tpl_name		= $controller->get_template();
		$this->page_title	= $controller->get_title('ACP_REACTIONS');
	}
}
