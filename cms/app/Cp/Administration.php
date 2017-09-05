<?php

namespace Kilvin\Cp;

use Cp;
use DB;
use File;
use Site;
use Request;
use Carbon\Carbon;
use Kilvin\Cp\Logging;
use Kilvin\Core\Localize;
use Kilvin\Core\Session;
use Symfony\Component\Finder\Finder;

class Administration
{
	// --------------------------------------------------------------------

    /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
		switch(Request::input('M'))
		{
			case 'configManager' :
			case 'memberConfigManager' :
			case 'updateConfigPreferences' :

				if ( ! Session::access('can_admin_preferences')) {
					return Cp::unauthorizedAccess();
				}

				return $this->{Request::input('M')}();

			break;
			case 'utilities' :

				if ( ! Session::access('can_admin_utilities')) {
					return Cp::unauthorizedAccess();
				}

				$utilities = new Utilities;

				switch(Request::input('P'))
				{
					case 'view_logs'			: return (new Logging)->viewLogs();
						break;
					case 'clear_cplogs'		 	: return (new Logging)->clearCpLogs();
						break;
					case 'viewThrottleLog'		: return (new Logging)->viewThrottleLog();
						break;
					case 'clearCacheForm'		: return $utilities->clearCacheForm();
						break;
					case 'clearCaching'			: return $utilities->clearCaching();
						break;
					case 'recountStatistics'	: return $utilities->recountStatistics();
						break;
					case 'recountPreferences'	: return $utilities->recountPreferences();
						break;
					case 'updateRecountPreferences'	: return $utilities->updateRecountPreferences();
						break;
					case 'performRecount'		: return $utilities->performRecount();
						break;
					case 'performStatsRecount'	: return $utilities->performStatsRecount();
						break;
					case 'php_info'			 	: return $utilities->php_info();
						break;
					default					 	: return false;
						break;
					}

				break;
			default	:
				return $this->homepage();
				break;
		}
	}

	// --------------------------------------------------------------------

    /**
    * Main Administration homepage
    *
    * @return  string
    */
	public function homepage()
	{
		if ( ! Session::access('can_access_admin')) {
			return Cp::unauthorizedAccess();
		}

		Cp::$title = __('admin.system_admin');
		Cp::$crumb = __('admin.system_admin');

		$menu = [

			'site_preferences'	=>	[
				'general_preferences'			=> [
					AMP.'M=configManager'.AMP.'P=general_preferences',
					'system offline name index site new version auto check rename weblog section urls'
				],
				'localization_preferences' 		=> [
					AMP.'M=configManager'.AMP.'P=localization_preferences',
					'localize localization time zone'
				],

				// 'email_preferences'				=> [
				// 	AMP.'M=configManager'.AMP.'P=email_preferences',
				// 	'email SMTP sendmail PHP Mail batch webmaster tell-a-friend contact form'
				// ],

				'cookie_preferences'			=> [
					AMP.'M=configManager'.AMP.'P=cookie_preferences',
					'cookie cookies prefix domain site'
				],

				'space_1'				=> '-',

				'cp_preferences'				=> [
					AMP.'M=configManager'.AMP.'P=cp_preferences',
					'control panel display language encoding character publish tab'
				],
				'security_preferences'	 		=> [
					AMP.'M=configManager'.AMP.'P=security_preferences',
					'security session sessions cookie deny duplicate require agent ip password length'
				],
				'debugging_preferences'			=> [
					AMP.'M=configManager'.AMP.'P=debugging_preferences',
					'output debugging error message force query string HTTP headers redirect redirection'
				],


				'space_2'				=> '-',

				'censoring_preferences'			=> [
					AMP.'M=configManager'.AMP.'P=censoring_preferences',
					'censor censoring censored'
				],
			],


			'weblog_administration'	=> [
				'weblog_management'		=>	[
					'?C=WeblogAdministration'.AMP.'M=weblogsOverview',
					'weblog weblogs posting'
				],
				'categories'			=>	[
					'?C=WeblogAdministration'.AMP.'M=category_overview',
					'category categories'
				],
				'field_management'	 	=>	[
					'?C=WeblogAdministration'.AMP.'M=fields_overview',
					'custom fields relational date textarea formatting'
				],
				'status_management'		=>	[
					'?C=WeblogAdministration'.AMP.'M=status_overview',
					'status statuses open close'
				],
				'weblog_preferences'			=>	[
					AMP.'M=configManager'.AMP.'P=weblog_preferences',
					'category URL dynamic caching caches'
				]
			 ],

			'members_and_groups' 	=> [
				'register_member'		=> [
					'?C=Members'.AMP.'M=registerMember',
					'register new member'
				],
				'view_members'			=> [
					'?C=Members'.AMP.'M=listMembers',
					'view members memberlist email url join date'
				],
				'member_groups'		 	=> [
					'?C=Members'.AMP.'M=memberGroupManager',
					'member groups super admin admins superadmin pending guests banned'
				],

				'member_profile_fields' => [
					'?C=Members'.AMP.'M=profileFields',
					'custom member profile fields '
				],

				'member_preferences'			=> [
					AMP.'M=memberConfigManager',
					'membership members member private message messages messaging photos photo registration activation'
				],

				'space_1'				=> '-',

				'member_search'		 	=> [
					'?C=Members'.AMP.'M=memberSearch',
					'search members'
				],

				'user_banning'			=> [
					'?C=Members'.AMP.'M=memberBanning',
					'ban banning users banned'
				]
		 	],

		 	'image_preferences'	=> [

		 		'file_upload_prefs'		=>	[
					'?C=WeblogAdministration'.AMP.'M=uploadPreferences',
					'upload uploading paths images files directory'
				],

				'image_resizing'	 			=> [
					AMP.'M=configManager'.AMP.'P=image_resizing',
					'image resize resizing thumbnail thumbnails GD netPBM imagemagick magick'
				],
		 	],


			'utilities'				=> [
				'view_log_files'		=>	[
					AMP.'M=utilities'.AMP.'P=view_logs',
					'view CP control panel logs '
				],

				'view_throttle_log'		=>	[
					AMP.'M=utilities'.AMP.'P=viewThrottleLog',
					'throttle throttling log'
				],

				'space_1'				=> '-',

				'clear_caching'		 	=>	[
					AMP.'M=utilities'.AMP.'P=clearCacheForm',
					'clear empty cache caches'
				],
				'recount_statistics'		 	=>	[
					AMP.'M=utilities'.AMP.'P=recountStatistics',
					'stats statistics recount redo'
				],
				'php_info'				=>	[
					AMP.'M=utilities'.AMP.'P=php_info',
					'php info information settings paths'
				],
		 	]
		];

		// ----------------------------------------
		//  Set Initial Display + JS
		// ----------------------------------------

		if (Request::input('keywords')) {
			Cp::$body_props .= ' onload="showHideMenu(\'search_results\');"';
		}

		if (!Request::input('keywords')) {
			if ( Request::input('area') !== null and in_array(Request::input('area'), array_keys($menu)))
			{
				Cp::$body_props .= ' onload="showHideMenu(\''.Request::input('area').'\');"';
			}
			else
			{
				Cp::$body_props .= ' onload="showHideMenu(\'default_menu\');"';
			}
		}

        $js = <<<EOT
<script type="text/javascript">
function showHideMenu(contentId)
{
	$("#menu_contents").html($("#"+contentId).html());
}
</script>
EOT;
        Cp::$body  = $js;
		Cp::$body .= Cp::table('', '0', '', '100%');

		// Various sections of Admin area
		$left_menu = Cp::div('tableHeadingAlt').
			__('admin.system_admin').
			'</div>'.PHP_EOL.
			Cp::div('profileMenuInner');

		// ----------------------------------------
		//  Build Left Menu AND default content, which is also the menu
		// ----------------------------------------

		$content = PHP_EOL.'<ul>'.PHP_EOL;

		foreach($menu as $key => $value)
		{
			$left_menu .= Cp::quickDiv('navPad', Cp::anchor(BASE.'?C=Administration&area='.$key, __('admin.'.$key)));

			$content .= '<li>'.
						Cp::anchor(
							BASE.'?C=Administration&area='.$key,
							__('admin.'.$key)
						).
						'</li>'.PHP_EOL;
		}

		$content .= '</ul>'.PHP_EOL;

		$main_content = Cp::quickDiv('default', '', 'menu_contents').
			"<div id='default_menu' style='display:none;'>".
				Cp::heading(__('admin.system_admin'), 2).
				__('admin.system_admin_blurb').
				$content.
			'</div>'.PHP_EOL;

		// -------------------------------------
		//  Clean up Keywords
		// -------------------------------------

		$keywords = '';

		if (Request::filled('keywords'))
		{
			$keywords = Request::input('keywords');

			// Ooooo!
			$question = 'dGhlIGFuc3dlciB0byBsaWZlLCB0aGUgdW5pdmVyc2UsIGFuZCBldmVyeXRoaW5n';

			if (strtolower(Request::input('keywords')) == base64_decode($question))
			{
				return Cp::errorMessage('42');
			}

			$search_terms = preg_split("/\s+/", strtolower( Request::input('keywords')));
			$search_results = '';
		}

		// -------------------------------------
		//  Build Content
		// -------------------------------------

		foreach ($menu as $key => $val)
		{
			$content = PHP_EOL.'<ul>'.PHP_EOL;

			foreach($val as $k => $v)
			{
				// A space between items. Adds clarity
				if (substr($k, 0, 6) == 'space_')
				{
					$content .= '</ul>'.PHP_EOL.PHP_EOL.'<ul>'.PHP_EOL;
					continue;
				}

				$url = (substr($v[0], 0, 1) == '?') ? BASE.$v[0] : BASE.'?C=Administration'.$v[0];

				$content .= '<li>'.Cp::anchor($url, __('admin.'.$k)).'</li>'.PHP_EOL;

				// Find areas that match keywords, a bit simplisitic but it works...
				if (!empty($search_terms))
				{
					if (sizeof(array_intersect($search_terms, explode(' ', strtolower($v['1'])))) > 0)
					{
						$search_results .= '<li>'.__('admin.'.$key).' -> '.Cp::anchor(BASE.'?C=Administration'.$v[0], __('admin.'.$k)).'</li>';
					}
				}
			}

			$content .= '</ul>'.PHP_EOL;

			$blurb = ('admin.'.$key.'_blurb' == __('admin.'.$key.'_blurb')) ? '' : __('admin.'.$key.'_blurb');

			$main_content .=  "<div id='".$key."' style='display:none;'>".
								Cp::heading(__('admin.'.$key), 2).
								$blurb.
								$content.
							'</div>'.PHP_EOL;
		}

		// -------------------------------------
		//  Keywords Search
		// -------------------------------------

		if (!empty($search_terms))
		{
			if (strlen($search_results) > 0)
			{
				$search_results = PHP_EOL.'<ul>'.PHP_EOL.$search_results.PHP_EOL.'</ul>';
			}
			else
			{
				$search_results = __('admin.no_search_results');

				if (isset($search_terms[0]) && strtolower($search_terms[0]) === 'mufasa') {
					$search_results .= '<div style="font-size: 4em;">🦁</div>';
				}
			}

			$main_content .=  "<div id='search_results' style='display:none;'>".
								Cp::heading(__('admin.search_results'), 2).
								$search_results.
							  '</div>';
		}

		// -------------------------------------
		//  Display Page
		// -------------------------------------

		$left_menu .= '</div>'.PHP_EOL.BR;

		// Add in the Search Form
		$left_menu .=  Cp::quickDiv('tableHeadingAlt', __('admin.search'))
						.Cp::div('profileMenuInner')
						.	Cp::formOpen(array('action' => 'C=Administration'))
						.		Cp::input_text('keywords', $keywords, '20', '120', 'input', '98%')
						.		Cp::quickDiv('littlePadding', Cp::quickDiv('defaultRight', Cp::input_submit(__('admin.search'))))
						.	'</form>'.PHP_EOL
						.'</div>'.PHP_EOL;

		// Create the Table
		$table_row = [
			'first' 	=> ['valign' => "top", 'width' => "220px", 'text' => $left_menu],
			'second'	=> ['class' => "default", 'width'  => "15px"],
			'third'		=> ['valign' => "top", 'text' => $main_content]
		];

		Cp::$body .= Cp::tableRow($table_row).
					  '</table>'.PHP_EOL;

	}

	// --------------------------------------------------------------------

    /**
    * Configuration Data
    *
    * The list of all the config options and how to display them
    *
    * @return  array
    */
	private function config_data()
	{
		return [

			'general_preferences' =>	[
				'is_system_on'				=> array('r', array('y' => 'yes', 'n' => 'no')),
				'is_site_on'				=> array('r', array('y' => 'yes', 'n' => 'no')),
				'site_index'				=> '',
				'cp_url'					=> '',
				'theme_folder_url'			=> '',
				'theme_folder_path'			=> '',
				'notification_sender_email'	=> '',
			],

			'localization_preferences'	=>	[
				'site_timezone'			=> ['f', 'timezone'],
				'date_format'			=> ['s', ['Y-m-d' => Localize::format('Y-m-d', 'now')]],
				'time_format'			=> ['s', ['H:i'   => __('admin.24_hour_time'), 'g:i A' => __('admin.12_hour_time')]],
				'default_language'		=> ['f', 'language_menu'],
			],

			'cookie_preferences' => [
				'cookie_domain'				=> '',
				'cookie_path'				=> '',
			],

			'cp_preferences' =>	[
				'cp_theme'					=> array('f', 'theme_menu'),
			],

			'debugging_preferences'	=>	[
				'show_queries'				=> array('r', array('y' => 'yes', 'n' => 'no')),
				'template_debugging'		=> array('r', array('y' => 'yes', 'n' => 'no')),
				'site_debug'				=> ['s', ['0' => 'debug_zero', '1' => 'debug_one', '2' => 'debug_two']],
			],

			'weblog_preferences' =>	[
				'new_posts_clear_caches'	=> array('r', array('y' => 'yes', 'n' => 'no')),
				'word_separator'			=> array('s', array('dash' => 'dash', 'underscore' => 'underscore')),
			],

			'image_resizing' =>	[
				'enable_image_resizing' 	=> array('r', array('y' => 'yes', 'n' => 'no')),
				'image_resize_protocol'		=> ['s', ['gd2' => 'gd2', 'imagemagick' => 'imagemagick']],
				'image_library_path'		=> '',
				'thumbnail_prefix'			=> '',
			],

			'security_preferences' =>	[
				'password_min_length'		=> '',
				'enable_throttling'			=> array('r', array('y' => 'yes', 'n' => 'no')),
				'banish_masked_ips'			=> array('r', array('y' => 'yes', 'n' => 'no')),
				'max_page_loads'			=> '',
				'time_interval'				=> '',
				'lockout_time'				=> '',
				'banishment_type'			=> array('s', array('404' => '404', 'redirect' => 'url_redirect', 'message' => 'show_message')),
				'banishment_url'			=> '',
				'banishment_message'		=> ''
			],


			'template_preferences' => [
				'save_tmpl_revisions' 		=> array('r', array('y' => 'yes', 'n' => 'no')),
				'max_tmpl_revisions'		=> '',
			],

			'censoring_preferences' => [
				'enable_censoring' 			=> array('r', array('y' => 'yes', 'n' => 'no')),
				'censor_replacement'		=> '',
				'censored_words'			=> array('t', array('rows' => '20', 'kill_pipes' => TRUE)),
			],
		];
	}

	// --------------------------------------------------------------------

    /**
    * Subtext for Configruation options
    *
    * Secondary text for further explanations or details for an option
    *
    * @return  array
    */
	private function subtext()
	{
		return [
			'is_site_on'		    	=> array('is_site_on_explanation'),
			'is_system_on'		    	=> array('is_system_on_explanation'),
			'site_debug'				=> array('site_debug_explanation'),
			'show_queries'				=> array('show_queries_explanation'),
			'template_debugging'		=> array('template_debugging_explanation'),
			'default_member_group' 		=> array('group_assignment_defaults_to_two'),
			'notification_sender_email' => array('notification_sender_email_explanation'),
			'cookie_domain'				=> array('cookie_domain_explanation'),
			'cookie_path'				=> array('cookie_path_explain'),
			'censored_words'			=> array('censored_explanation', 'censored_wildcards'),
			'censor_replacement'		=> array('censor_replacement_info'),
			'enable_image_resizing'		=> array('enable_image_resizing_exp'),
			'image_resize_protocol'		=> array('image_resize_protocol_exp'),
			'image_library_path'		=> array('image_library_path_exp'),
			'thumbnail_prefix'			=> array('thumbnail_prefix_exp'),
			'save_tmpl_revisions'		=> array('template_rev_msg'),
			'max_tmpl_revisions'		=> array('max_revisions_exp'),
			'max_page_loads'			=> array('max_page_loads_exp'),
			'time_interval'				=> array('time_interval_exp'),
			'lockout_time'				=> array('lockout_time_exp'),
			'banishment_type'			=> array('banishment_type_exp'),
			'banishment_url'			=> array('banishment_url_exp'),
			'banishment_message'		=> array('banishment_message_exp'),
		];
	}

	// --------------------------------------------------------------------

    /**
    * Abstracted Configuration page
    *
    * Based on the request it loads the relevant config data and displays the form
    *
    * @return  string
    */
	public function configManager()
	{
		if ( ! Session::access('can_admin_preferences')) {
			return Cp::unauthorizedAccess();
		}

		if ( ! $type = Request::input('P')) {
			return false;
		}

		// No funny business with the URL
		$allowed = [
			'general_preferences',
			'localization_preferences',
			'cookie_preferences',
			'cp_preferences',
			'weblog_preferences',
			'member_preferences',
			'debugging_preferences',
			'security_preferences',
			'image_resizing',
			'template_preferences',
			'censoring_preferences',
		];

		if (!in_array($type, $allowed)) {
			return redirect('?');
		}

		$f_data = $this->config_data();
		$subtext = $this->subtext();

		// ------------------------------------
		//  Build the output
		// ------------------------------------

		Cp::$body	 =	'';

		if (Request::input('U') or Request::input('msg') == 'updated') {
			Cp::$body .= Cp::quickDiv('success-message', __('admin.preferences_updated'));
		}


		$return_loc = '?C=Administration&M=configManager&P='.$type.'&U=1';

		if ($type === 'template_preferences') {
			$return_loc = 'templates_manager';
		}

		Cp::$body	.=	Cp::formOpen(
			[
				'action' => 'C=Administration'.AMP.'M=updateConfigPreferences'
			],
			[
				'return_location' => $return_loc
			]
		);

		Cp::$body	.=	Cp::table('tableBorder', '0', '', '100%');
		Cp::$body	.=	'<tr>'.PHP_EOL;
		Cp::$body	.=	Cp::td('tableHeading', '', '2');
		Cp::$body	.=	__('admin.'.$type);
		Cp::$body	.=	'</td>'.PHP_EOL;
		Cp::$body	.=	'</tr>'.PHP_EOL;

		$i = 0;

		// ------------------------------------
		//  Blast through the array
		// ------------------------------------

		foreach ($f_data[$type] as $key => $val)
		{
			Cp::$body	.=	'<tr>'.PHP_EOL;

			// If the form type is a textarea, we'll align the text at the top, otherwise, we'll center it

			if (is_array($val) AND $val[0] == 't')
			{
				Cp::$body .= Cp::td('', '50%', '', '', 'top');
			}
			else
			{
				Cp::$body .= Cp::td('', '50%', '');
			}

			// ------------------------------------
			//  Preference heading
			// ------------------------------------

			Cp::$body .= Cp::div('defaultBold');

			$label = ( ! is_array($val)) ? $key : '';

			Cp::$body .= __('admin.'.$key);

			Cp::$body .= '</div>'.PHP_EOL;

			// ------------------------------------
			//  Preference sub-heading
			// ------------------------------------

			if (isset($subtext[$key]))
			{
				foreach ($subtext[$key] as $sub)
				{
					Cp::$body .= Cp::quickDiv('subtext', __('admin.'.$sub));
				}
			}

			Cp::$body .= '</td>'.PHP_EOL;

			// ------------------------------------
			//  Preference value
			// ------------------------------------

			Cp::$body .= Cp::td('', '50%', '');

			if (is_array($val))
			{
				// ------------------------------------
				//  Drop-down menus
				// ------------------------------------

				if ($val[0] == 's')
				{
					Cp::$body .= Cp::input_select_header($key);

					foreach ($val[1] as $k => $v)
					{
						$selected = ($k == Site::config($key)) ? 1 : '';

						$value = ($key == 'date_format' or $key == 'time_format') ? $v : __('admin.'.$v);

						Cp::$body .= Cp::input_select_option($k, $value, $selected);
					}

					Cp::$body .= Cp::input_select_footer();

				}
				elseif ($val[0] == 'r')
				{
					// ------------------------------------
					//  Radio buttons
					// ------------------------------------

					foreach ($val[1] as $k => $v)
					{

						if($k == 'y') {
							$selected = (Site::config($key) === true or Site::config($key) === 'y');
						} elseif($k == 'n') {
							$selected = (Site::config($key) === false or Site::config($key) === 'n');
						} else {
							$selected = ($k == Site::config($key)) ? 1 : '';
						}

						Cp::$body .= __('admin.'.$v).'&nbsp;';
						Cp::$body .= Cp::input_radio($key, $k, $selected).'&nbsp;';
					}
				}
				elseif ($val[0] == 't')
				{
					// ------------------------------------
					//  Textarea fields
					// ------------------------------------

					// The "kill_pipes" index instructs us to
					// turn pipes into newlines

					if (isset($val[1]['kill_pipes']) AND $val[1]['kill_pipes'] === TRUE)
					{
						$text	= '';

						foreach (explode('|', Site::originalConfig($key)) as $exp)
						{
							$text .= $exp.PHP_EOL;
						}
					}
					else
					{
						$text = stripslashes(Site::originalConfig($key));
					}

					$rows = (isset($val[1]['rows'])) ? $val[1]['rows'] : '20';

					$text = str_replace("\\'", "'", $text);

					Cp::$body .= Cp::input_textarea($key, $text, $rows);

				}
				elseif ($val[0] == 'f')
				{
					// ------------------------------------
					//  Function calls
					// ------------------------------------

					switch ($val[1])
					{
						case 'language_menu'		: 	Cp::$body .= $this->availableLanguages(Site::config($key));
							break;
						case 'theme_menu'			: 	Cp::$body .= $this->buildCpThemesPulldown(Site::config($key));
							break;
						case 'timezone'				: 	Cp::$body .= Localize::timezoneMenu(Site::config($key));
							break;
					}
				}
			}
			else
			{
				// ------------------------------------
				//  Text input fields
				// ------------------------------------

				$item = str_replace("\\'", "'", Site::originalConfig($key));

				Cp::$body .= Cp::input_text($key, $item, '20', '120', 'input', '100%');
			}

			Cp::$body .= '</td>'.PHP_EOL;
			Cp::$body .= '</tr>'.PHP_EOL;
		}

		Cp::$body .= '</table>'.PHP_EOL;

		Cp::$body .= Cp::quickDiv('littlePadding', Cp::input_submit(__('cp.update')));

		Cp::$body .= '</form>'.PHP_EOL;

		Cp::$title  = __('admin.'.$type);

		if (Request::input('P') == 'weblog_preferences')
		{
			Cp::$crumb  = Cp::anchor(BASE.'?C=Administration'.AMP.'area=weblog_administration', __('admin.weblog_administration'));
			Cp::$crumb .= Cp::breadcrumbItem(__('admin.'.$type));
		}
		elseif(Request::input('P') != 'template_preferences')
		{
			Cp::$crumb  = Cp::anchor(BASE.'?C=Administration'.AMP.'area=site_preferences', __('admin.site_preferences'));
			Cp::$crumb .= Cp::breadcrumbItem(__('admin.'.$type));
		}
		else
		{
			Cp::$crumb .= __('admin.'.$type);
		}
	}

	// --------------------------------------------------------------------

    /**
    * Members/Accounts General Config Manager
    *
    * @return  string
    */
	public function memberConfigManager()
	{
		if ( ! Session::access('can_admin_preferences')) {
			return Cp::unauthorizedAccess();
		}

		$f_data = [
			'general_preferences'		=>
			[
				'default_member_group'	=> ['f', 'member_groups'],
				'enable_photos'			=> ['r', ['y' => 'yes', 'n' => 'no']],
				'photo_url'				=> '',
				'photo_path'			=> '',
				'photo_max_width'		=> '',
				'photo_max_height'		=> '',
				'photo_max_kb'			=> ''
			]
		];

		$subtext = [
			'default_member_group' 		=> ['group_assignment_defaults_to_two'],
			'photo_path'				=> ['must_be_path']
		];

		if (Request::input('U')) {
			Cp::$body .= Cp::quickDiv('success-message', __('admin.preferences_updated'));
		}

		$r = Cp::formOpen(
			[
				'action' => 'C=Administration'.AMP.'M=updateConfigPreferences'
			],
			[
				'return_location' => '?C=Administration&M=memberConfigManager&U=1'
			]
		);

		$r .= Cp::quickDiv('default', '', 'menu_contents');

		// ------------------------------------
		//  Blast through the array
		// ------------------------------------

		foreach ($f_data as $menu_head => $menu_array)
		{
			$r .= '<div id="'.$menu_head.'" style="display: block; padding:0; margin: 0;">';
			$r .= Cp::table('tableBorder', '0', '', '100%');
			$r .= '<tr>'.PHP_EOL;

			$r .= "<td class='tableHeadingAlt' id='".$menu_head."2' colspan='2'>";
			$r .= NBS.__('admin.'.$menu_head).'</td>'.PHP_EOL;
			$r .= '</tr>'.PHP_EOL;

			foreach ($menu_array as $key => $val)
			{
				$r	.=	'<tr>'.PHP_EOL;

				// If the form type is a textarea, we'll align the text at the top, otherwise, we'll center it
				if (is_array($val) AND $val[0] == 't') {
					$r .= Cp::td('', '50%', '', '', 'top');
				} else {
					$r .= Cp::td('', '50%', '');
				}

				// ------------------------------------
				//  Preference heading
				// ------------------------------------

				$r .= Cp::div('defaultBold');

				$label = ( ! is_array($val)) ? $key : '';

				$r .= __('admin.'.$key);

				$r .= '</div>'.PHP_EOL;

				// ------------------------------------
				//  Preference sub-heading
				// ------------------------------------

				if (isset($subtext[$key])) {
					foreach ($subtext[$key] as $sub) {
						$r .= Cp::quickDiv('subtext', __('admin.'.$sub));
					}
				}

				$r .= '</td>'.PHP_EOL;
				$r .= Cp::td('', '50%', '');

					if (is_array($val))
					{
						// ------------------------------------
						//  Drop-down menus
						// ------------------------------------

						if ($val[0] == 's')
						{
							$r .= Cp::input_select_header($key);

							foreach ($val[1] as $k => $v)
							{
								$selected = ($k == Site::originalConfig($key)) ? 1 : '';

								$r .= Cp::input_select_option($k, ( ! __('admin.'.$v) ? $v : __('admin.'.$v)), $selected);
							}

							$r .= Cp::input_select_footer();

						}
						elseif ($val[0] == 'r')
						{
							// ------------------------------------
							//  Radio buttons
							// ------------------------------------

							foreach ($val[1] as $k => $v)
							{
								$selected = ($k == Site::originalConfig($key)) ? 1 : '';

								$r .= __('admin.'.$v).'&nbsp;';
								$r .= Cp::input_radio($key, $k, $selected).'&nbsp;';
							}
						}
						elseif ($val[0] == 'f')
						{
							// ------------------------------------
							//  Function calls
							// ------------------------------------

							switch ($val[1])
							{
								case 'member_groups' : $r .= $this->buildMemberGroupsPulldown();
									break;
							}
						}

					}
					else
					{
						// ------------------------------------
						//  Text input fields
						// ------------------------------------

						$item = str_replace("\\'", "'", Site::originalConfig($key));

						$r .= Cp::input_text($key, $item, '20', '120', 'input', '100%');
					}

				$r .= '</td>'.PHP_EOL;
			}

			$r .= '</tr>'.PHP_EOL;
			$r .= '</table>'.PHP_EOL;
			$r .= '</div>'.PHP_EOL;
		}

		$r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('cp.update')));

		$r .= '</form>'.PHP_EOL;

		// ------------------------------------
        //  Create Our All Encompassing Table of Member Goodness
        // ------------------------------------

        Cp::$body .= Cp::table('', '0', '', '100%');
		Cp::$body .= Cp::tableRow(['valign' => "top", 'text' => $r]).
					  '</table>'.PHP_EOL;

		Cp::$title = __('admin.member_preferences');
		Cp::$crumb =
			Cp::anchor(BASE.'?C=Administration'.AMP.'area=members_and_groups', __('admin.members_and_groups')).
			Cp::breadcrumbItem(__('admin.member_preferences'));
	}

	// --------------------------------------------------------------------

    /**
    * Builds a Member Groups Pulldown
    *
    * @return  string
    */
	private function buildMemberGroupsPulldown()
	{
    	$query = DB::table('member_groups')
    		->select('group_id', 'group_name')
    		->where('group_id', '!=', 1)
    		->orderBy('group_name')
    		->get();

		$r = Cp::input_select_header('default_member_group');

		foreach ($query as $row)
		{
			$group_name = $row->group_name;

			$selected = ($row->group_id == Site::config('default_member_group')) ? 1 : '';

			$r .= Cp::input_select_option($row->group_id, $group_name, $selected);
		}

		$r .= Cp::input_select_footer();

		return $r;
	}

	// --------------------------------------------------------------------

    /**
    * Update Config Options in DB
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
	function updateConfigPreferences()
	{
		if ( ! Session::access('can_admin_preferences')) {
			return Cp::unauthorizedAccess();
		}

		// @todo - Probably bogus, just set a default
		$loc = Request::input('return_location');

		// We'll format censored words if they happen to cross our path
		if (Request::filled('censored_words')) {
			$censored_words = Request::input('censored_words');
			$censored_words = str_replace(PHP_EOL, '|', $censored_words);
			$censored_words = preg_replace("#\s+#", "", $censored_words);
		}

		// ------------------------------------
		//  Do path checks if needed
		// ------------------------------------

		$paths = ['photo_path'];

		foreach ($paths as $val)
		{
			if (Request::filled($val)) {
				$fp = Request::input($val);

				$fp = str_replace('{CMS_PATH}', Site::config('CMS_PATH'), $fp);
				$fp = str_replace('{PUBLIC_PATH}', Site::config('PUBLIC_PATH'), $fp);

				if ( ! @is_dir($fp)) {
					$msg  = Cp::quickDiv('littlePadding', __('admin.invalid_path'));
					$msg .= Cp::quickDiv('highlight', $fp);

					return Cp::errorMessage($msg);
				}

				if ( ! @is_writable($fp)) {
					$msg  = Cp::quickDiv('littlePadding', __('admin.not_writable_path'));
					$msg .= Cp::quickDiv('highlight', $fp);

					return Cp::errorMessage($msg);
				}
			}
		}

		// ------------------------------------
		//  Preferences Stored in Database For Site
		// ------------------------------------

		$prefs = DB::table('site_preferences')
			->where('site_id', Site::config('site_id'))
			->value('value', 'handle');

		$update_prefs = [];

		foreach(Site::preferenceKeys() as $value) {
			if (Request::filled($value)) {
				$update_prefs[$value] = Request::input($value);
			}
		}

		if (!empty($update_prefs)) {
			foreach($update_prefs as $handle => $value) {
				DB::table('site_preferences')
					->where('site_id', Site::config('site_id'))
					->where('handle', $handle)
					->update(
						[
							'value' => $value
						]);
			}
		}

		// ------------------------------------
		//  Certain Preferences go in .env file
		// ------------------------------------

		$this->updateEnvironmentFile();

		// ------------------------------------
		//  Redirect
		// ------------------------------------

		if ($loc === 'templates_manager') {
			return redirect('?C=Administration&M=configManager&P=template_preferences&msg=updated');
		}

		return redirect($loc);
	}

	// --------------------------------------------------------------------

    /**
    * Update .env file
    *
    * There are two settings that go into .env instead of the DB
    *
    * @return bool
    */
	function updateEnvironmentFile()
	{
		// Convert the y/n to true/false

		$allowed = [
			'CMS_IS_SYSTEM_ON'      => 'is_system_on',
            'CMS_DISABLE_EVENTS'    => 'disable_events',
		];

		$data = [];

		foreach($allowed as $name => $value) {
			if (Request::filled($value)) {
				$data[$name] = (Request::input($value) == 'y') ? 'true' : 'false';
			}
		}

		if (empty($data)) {
			return;
		}

		$this->writeNewEnvironmentFileWith($data);
	}

	// --------------------------------------------------------------------

    /**
     * Write a new environment file with the given key.
     *
     * @param  array  $new
     * @return void
     */
    protected function writeNewEnvironmentFileWith($new)
    {
        // The example is what starts us off
        $string = file_get_contents(app()->environmentFilePath());

        foreach($new as $key => $value) {
            $string = preg_replace(
                $this->keyReplacementPattern($key),
                $key.'='.$this->protectEnvValue($value),
                $string
            );
        }

        file_put_contents(app()->environmentFilePath(), $string);
    }

    // --------------------------------------------------------------------

    /**
     * Protect an .env value if it has special chars
     *
     * @return string
     */
    protected function protectEnvValue($value)
    {
        // @todo
        return $value;
    }

    // --------------------------------------------------------------------

    /**
     * Get a regex pattern that will match env APP_KEY with any random key.
     *
     * @return string
     */
    protected function keyReplacementPattern($key)
    {
        return "/^".$key."\=.*$/m";
    }

	// --------------------------------------------------------------------

    /**
    * Build CP Themes pulldown
    *
    * @return string
    */
	function buildCpThemesPulldown($default = '')
	{
		$themes = [];

		foreach(File::directories(PATH_CP_THEME) as $dir) {
            $x = explode(DIRECTORY_SEPARATOR, $dir);
            $last = last($x);

            if (!preg_match("/[^A-Za-z0-9\_]/", $last)) {
                $themes[] = $last;
            }
        }

		$r = Cp::input_select_header('cp_theme');

		foreach ($themes as $theme)
		{
			$selected = ($theme == $default) ? 1 : '';

			$r .= Cp::input_select_option(
				$theme,
				ucwords(str_replace("_", " ", $theme)),
				($theme == $default) ? 1 : ''
			);
		}

		$r .= Cp::input_select_footer();

		return $r;
	}

	// --------------------------------------------------------------------

    /**
    * Build Available Languages Pulldown
    *
    * @return string
    */
    private function availableLanguages($default)
    {
        $source_dir = resource_path('language');

        foreach (Finder::create()->in($source_dir)->directories()->depth(0) as $dir) {
            $directories[] = $dir->getFilename();
        }

        $r  = "<div class='default'>";
        $r .= "<select name='default_language' class='select'>\n";

        foreach ($directories as $dir)
        {
            $selected = ($dir == $default) ? " selected='selected'" : '';
            $r .= "<option value='{$dir}'{$selected}>".$dir."</option>\n";
        }

        $r .= "</select>";
        $r .= "</div>";

        return $r;
    }
}

