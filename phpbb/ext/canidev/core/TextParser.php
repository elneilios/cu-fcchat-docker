<?php
/**
 * @package Ext Common Core
 * @version 1.1.4 26/01/2024
 *
 * @copyright (c) 2024 CaniDev
 * @license https://creativecommons.org/licenses/by-nc/4.0/
 */

namespace canidev\core;

use \Symfony\Component\DependencyInjection\ContainerInterface;

class TextParser
{
	const FLAG_ALLOW_HTML		= 1;
	const FLAG_CENSOR_TEXT		= 2;
	const FLAG_PARSE_LANGUAGE	= 4;

	/** @var \phpbb\language\language */
	protected $language;

	/**
	 * Constructor
	 *
	 * @param ContainerInterface 	$container		Service container interface
	 *
	 * @access public
	 */
	public function __construct(ContainerInterface $container)
	{
		$this->language = $container->get('language');
	}

	/**
	 * Generates text to be displayed in frontend
	 * 
	 * @param string 		$text 				Saved text
	 * @param int 			$flags 				Flags to be applied
	 * @param string 		$bbcode_uid			BBcode UID (not necessary anymore, kept for compatibility)
	 * @param string 		$bbcode_bitfield	BBcode Bitfield (not necessary anymore, kept for compatibility)
	 * 
	 * @return string 		Formatted string
	 */
	public function generateForDisplay($text, $flags = self::FLAG_CENSOR_TEXT, $bbcode_uid = '', $bbcode_bitfield = '', $parse_flags = 0)
	{
		$is_rich_text 	= preg_match('#^<r>#', $text);
		$parse_flags 	= OPTION_FLAG_BBCODE | OPTION_FLAG_SMILIES | $parse_flags;
		$text 			= generate_text_for_display($text, $bbcode_uid, $bbcode_bitfield, $parse_flags, ($flags & self::FLAG_CENSOR_TEXT));

		if(!$is_rich_text && ($flags & self::FLAG_ALLOW_HTML))
		{
			$text = htmlspecialchars_decode($text);
		}

		// Parse language
		if(($flags & self::FLAG_PARSE_LANGUAGE) && strpos($text, '{') !== false)
		{
			$text = preg_replace_callback('#\{L_([A-Z0-9_\-]+)\}#', function($matches) {
				return $this->language->is_set($matches[1]) ? $this->language->lang($matches[1]) : '';
			}, $text);
		}

		return $text;
	}

	/**
	 * Set Full URL to smilies in text
	 * 
	 * @param string $text 		Original text
	 * @return string
	 */
	public static function fixSmiliesPath($text)
	{
		if(defined('PHPBB_USE_BOARD_URL_PATH') && PHPBB_USE_BOARD_URL_PATH)
		{
			return $text;
		}

		$board_url = generate_board_url();

		return preg_replace_callback('#class\="smilies" src\="(.*?)(/images/.*?)"#', function($matches) use ($board_url) {
			return 'class="smilies" src="' . $board_url . $matches[2] . '"';
		}, $text);
	}

	/**
	 * Truncates html string while retaining special characters if going over the max length
	 *
	 * @param string	$input			The text to truncate to the given length.
	 * @param int		$limit			Maximum length of string
	 * @param string	$append			String to be appended
	 * @param bool		$count_all		Defines if count all or only plain text
	 * 
	 * @return string	Truncated text
	 */
	public static function truncate($input = '', $limit = 0, $append = '', $count_all = false)
	{
		$check_len = ($count_all) ? utf8_strlen($input) : utf8_strlen(strip_tags($input));

		if(!$input || $limit <= 0 || $check_len <= $limit)
		{
			return $input;
		}

		$splits = preg_split('#(</?[^>]*/?>)#', $input, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

		$text		= '';
		$open_tags 	= [];
		$ignore_count = 0;

		foreach($splits as $split)
		{
			// Open new tag
			if(preg_match('#^<([a-zA-Z0-9]+).*>#', $split, $match))
			{
				if(utf8_substr($match[0], -2) !== '/>' && !in_array($match[1], ['img', 'br', 'hr', 'input', 'source']))
				{
					$open_tags[] = $match[1];

					// Don't cut inline attachment
					if($ignore_count || strpos($split, 'inline-attachment') !== false)
					{
						$ignore_count++;
					}
				}
			}
			
			// Close tag, if the current
			if(count($open_tags) && $split === "</" . end($open_tags) . ">")
			{
				array_splice($open_tags, -1, 1);

				if($ignore_count)
				{
					$ignore_count--;
				}
			}

			// Adjust the string
			if(count($open_tags) && $split[0] != '<')
			{ 
				$length = ($count_all) ? utf8_strlen($text) : utf8_strlen(strip_tags($text));

				if(($length + utf8_strlen($split)) > $limit)
				{
					$split = utf8_substr($split, 0, $limit - $length);
				}
			}
			
			$text .= $split;

			// If exceed the limit and no open tags finish here,
			// and it is looking backwards to remove the tag open.
			$check_len = ($count_all) ? utf8_strlen($text) : utf8_strlen(strip_tags($text));
			if($check_len >= $limit && !$ignore_count)
			{
				// Try to cut the text before close the open tags
				if($split[0] != '<' && $check_len > $limit)
				{
					$chars_to_remove = max(0, utf8_strlen($split) - $limit);
					
					if($chars_to_remove)
					{
						$text = utf8_substr($text, 0, utf8_strlen($text) - $chars_to_remove);
					}
				}

				$tags_count = count($open_tags);

				for($i = $tags_count; $i > 0; $i--)
				{
					$text .= '</' . $open_tags[$i-1] . '>';
				}
		
				break;
			}
		}

		return $text . (($append) ? $append : '');
	}
}
