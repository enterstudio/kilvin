<?php

namespace Kilvin\Cp;

use Cp;
use DB;
use File;
use Request;
use Plugins as PluginsFacade;
use Kilvin\Core\Session;
use Kilvin\Exceptions\CmsFailureException;
use Kilvin\Support\Plugins\PluginMigrator;
use Kilvin\Support\Plugins\PluginMigrationRepository;
use Illuminate\Database\Migrations\Migrator;

class Plugins
{
    public $result;

    // --------------------------------------------------------------------

    /**
    * Constructor
    *
    * @return  void
    */
    public function __construct()
    {

    }

    // --------------------------------------------------------------------

    /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if (Request::input('action') === null && Request::input('plugin') === null) {
            return $this->homepage();
        }

        switch(Request::input('action'))
        {
            case 'install'	 :   return $this->pluginInstaller('install');
                break;
            case 'uninstall' :   return $this->pluginInstaller('uninstall');
                break;
            default     	 :   return $this->pluginControlPanel();
                break;
        }
    }

    // --------------------------------------------------------------------

    /**
    * Plugins Homepage
    *
    * @return string
    */
    public function homepage()
    {
        if ( ! Session::access('can_access_plugins')) {
            return Cp::unauthorizedAccess();
        }

		// ------------------------------------
		//  Assing page title
		// ------------------------------------

        $title = __('cp.homepage');

        Cp::$title = $title;
        Cp::$crumb = $title;

        // ------------------------------------
        //  Fetch all plugin names from "plugins" folder
        // ------------------------------------

        $plugins = [];

        foreach(File::directories(CMS_PATH_PLUGINS) as $dir) {
            $x = explode(DIRECTORY_SEPARATOR, $dir);
            $last = last($x);

            if (!preg_match("/[^A-Za-z0-9]/", $last) && $last != 'Base') {
                $plugins[] = $last;
            }
        }

        foreach(File::directories(CMS_PATH_THIRD_PARTY) as $dir) {
            $x = explode(DIRECTORY_SEPARATOR, $dir);
            $last = last($x);

            if (!preg_match("/[^A-Za-z0-9]/", $last)) {
                $plugins[] = $last;
            }
        }

        sort($plugins);

        // ------------------------------------
        //  Fetch allowed Plugins for a particular user
        // ------------------------------------

        if (!Session::access('can_admin_plugins')) {

            // Assigned plugins is plugin_id => plugin_name
            $plugin_ids = array_keys(Session::userdata('assigned_plugins'));

            if (empty($plugin_ids)) {
                return Cp::$body = Cp::quickDiv('', __('plugins.plugin_no_access'));
            }

            $allowed_plugins = DB::table('plugins')
                ->whereIn('plugin_id', $plugin_ids)
                ->orderBy('plugin_name')
                ->pluck('plugin_name')
                ->all();

            if (sizeof($allowed_plugins) == 0) {
                return Cp::$body = Cp::quickDiv('', __('plugins.plugin_no_access'));
            }
        }

        // ------------------------------------
        //  Fetch the installed plugins from DB
        // ------------------------------------

        $query = DB::table('plugins')
        	->orderBy('plugin_name')
        	->get();

        $installed_plugins = [];

        foreach ($query as $row) {
            $installed_plugins[$row->plugin_name] = $row;
        }

        // ------------------------------------
        //  Build page output
        // ------------------------------------

        $r = '';

        // -----------------------------------------
        //  CP Message?
        // -----------------------------------------

        $cp_message = session()->pull('cp-message');

        if (!empty($cp_message)) {
            $r .= Cp::quickDiv('success-message', $cp_message);
        }

        $r .= Cp::table('tableBorderNoTop', '0', '0', '100%').
              '<tr>'.PHP_EOL.
              Cp::tableCell(
                'tableHeading',
                [
                    __('plugins.plugin_name'),
                    __('plugins.plugin_description'),
                    __('plugins.plugin_version'),
                    __('plugins.plugin_status'),
                    __('plugins.plugin_action')
                ]).
              '</tr>'.PHP_EOL;


        $i = 0;

        foreach ($plugins as $plugin)
        {
			if (!Session::access('can_admin_plugins') && !in_array($plugin, $allowed_plugins)) {
				continue;
			}

            $manager = $this->loadManager($plugin);

            $r .= '<tr>'.PHP_EOL;

            $name = $manager->name();

            if (isset($installed_plugins[$plugin]) AND $manager->hasCp() == 'y') {
				$name = Cp::anchor(BASE.'?C=Plugins'.AMP.'plugin='.$plugin, $manager->name());
            }

            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', $name), '29%');

            // Plugin Description
            $r .= Cp::tableCell('', $manager->description(), '36%');

            // Plugin Version
            $r .= Cp::tableCell('', $manager->version(), '10%');


            // Plugin Status
            $status = ( ! isset($installed_plugins[$plugin]) ) ? 'not_installed' : 'installed';

			$in_status = str_replace(" ", "&nbsp;", __('plugins.'.$status));

            $show_status = ($status == 'not_installed') ?
                Cp::quickSpan('highlight', $in_status) :
                Cp::quickSpan('highlight_alt', $in_status);

            $r .= Cp::tableCell('', $show_status, '12%');

            // Plugin Action
            $action = ($status == 'not_installed') ? 'install' : 'uninstall';

            $show_action =
                (Session::access('can_admin_plugins')) ?
                Cp::anchor(BASE.'?C=Plugins'.AMP.'action='.$action.AMP.'plugin='.$plugin, __('plugins.'.$action)) :
                '--';

            $r .= Cp::tableCell('', $show_action, '10%');

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Load Plugin Views Directory as Top Most Option
    *
    * @param string $plugin
    * @return void
    */
    public function loadPluginViews($plugin)
    {
        $plugin  = filename_security($plugin);
        $details = PluginsFacade::findPluginLoadingDetails($plugin);

        $view_path = $details['path'].'views';

        app('view')->getFinder()->prependLocation($view_path);
    }

    // --------------------------------------------------------------------

    /**
    * Load a Plugin's Manager
    *
    * @param string $plugin
    * @return object
    */
    public function loadManager($plugin)
    {
        PluginsFacade::loadPluginLanguage($plugin);

        return PluginsFacade::loadPluginClass($plugin, 'Manager');
    }

    // --------------------------------------------------------------------

    /**
    * Load a Plugin's Control Panel class
    *
    * @param string $plugin
    * @return \Kilvin\Support\Plugins\ControlPanelInterface
    */
    public function loadControlPanel($plugin)
    {
        $details = PluginsFacade::findPluginLoadingDetails($plugin);

        PluginsFacade::loadPluginLanguage($plugin);
        $this->loadPluginViews($plugin);

        $CP = PluginsFacade::loadPluginClass($plugin, 'ControlPanel');
        $CP->setPluginDetails($details);

        return $CP;
    }

    // --------------------------------------------------------------------

    /**
    * Load Plugin's CP Pages
    *
    * @param string $plugin
    * @return object
    */
    function pluginControlPanel()
    {
        if ( ! Session::access('can_access_plugins')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $plugin = Request::input('plugin')) {
            return false;
        }

        // @todo - Check that it is installed first?

        if (Session::userdata('group_id') != 1)
        {
            $access = false;
            // Session::userdata('assigned_plugins')

			if ($access == false) {
				return Cp::unauthorizedAccess();
			}
		}

        $manager = $this->loadManager($plugin);

        Cp::$auto_crumb = Cp::breadcrumbItem(Cp::anchor('?C=Plugins&plugin='.$plugin, $manager->name()));

        return $this->loadControlPanel($plugin)->run();
    }

    // --------------------------------------------------------------------

    /**
    * Plugin Installer and Uninstaller
    *
    * @param string $type
    * @return string
    */
    function pluginInstaller($type)
    {
        if ( ! Session::access('can_admin_plugins')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $plugin = Request::input('plugin')) {
            return false;
        }

        // ------------------------------------------
        //  Load Manager (which checks existence)
        // ------------------------------------------

        $manager = $this->loadManager($plugin);

        // ------------------------------------------
        //  No funny business!
        // ------------------------------------------

        $query_count = DB::table('plugins')
        	->where('plugin_name', $plugin)
        	->count();

        if ($query_count == 0 && $type === 'uninstall') {
        	throw new CmsFailureException(__('plugins.plugin_is_not_installed'));
        }

        if ($query_count > 0 && $type === 'install') {
        	throw new CmsFailureException(__('plugins.plugin_is_already_installed'));
        }

        if($type === 'uninstall') {
			if ( ! Request::input('confirmed')) {
				return $this->uninstallConfirmation($plugin);
			}
        }

        // ------------------------------------------
        //  Run the Relevant Methods
        // ------------------------------------------

		if ($type === 'install') {
			$this->installPlugin($plugin, $manager);
		}

		if ($type === 'uninstall') {
            $this->uninstallPlugin($plugin, $manager);
		}

        // ------------------------------------------
        //  Finished, Create Message and Back to Homepage
        // ------------------------------------------

        $line = ($type == 'uninstall') ? __('plugins.plugin_has_been_uninstalled') : __('plugins.plugin_has_been_installed');

        $message = $line.$manager->name();

        session()->flash(
            'cp-message',
            $message
        );

         return redirect('?C=Plugins');
    }

    // --------------------------------------------------------------------

    /**
    * Install Plugin
    *
    * @param string $plugin The Plugin's name
    * @param object $manaer The already loaded plugin manager
    * @return bool
    */
    public function installPlugin($plugin, $manager)
    {
        DB::table('plugins')
            ->insert(
                [
                    'plugin_name' => $plugin,
                    'plugin_version' => $manager->version(),
                    'has_cp' => $manager->hasCp()
                ]
            );

        $this->runMigrations($plugin, 'up');

        $manager->install();
    }

    // --------------------------------------------------------------------

    /**
    * Uninstall Plugin
    *
    * @param string $plugin The Plugin's name
    * @param object $manaer The already loaded plugin manager
    * @return bool
    */
    public function uninstallPlugin($plugin, $manager)
    {
        $this->runMigrations($plugin, 'down');
        DB::table('plugins')->where('plugin_name', ucfirst($plugin))->delete();

        $manager->uninstall();
    }

    // --------------------------------------------------------------------

    /**
    * Run Migrations for Plugin
    *
    * @param string $plugin The Plugin's name
    * @param string $direction Up or down, yo ho!
    * @return bool
    */
    public function runMigrations($plugin, $direction)
    {
        $details   = PluginsFacade::findPluginLoadingDetails($plugin);

        $directory = $details['path'].'migrations';

        if (!File::isDirectory($directory)) {
            return true;
        }

        $repo     = new PluginMigrationRepository(app('db'), 'plugin_migrations');
        $repo->setPlugin($plugin);

        $migrator = new PluginMigrator($repo, app('db'), app('files'));
        $migrator->setPluginDetails($details);

        if ($direction == 'down') {
            $migrator->rollback([$directory]);
        } else {
            $migrator->run([$directory]);
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
    * Uninstall Confirmation
    *
    * @param string $plugin The Plugin's name
    * @return string
    */
    public function uninstallConfirmation($plugin = '')
    {
        if ( ! Session::access('can_admin_plugins')) {
            return Cp::unauthorizedAccess();
        }

        if ($plugin == '') {
            return Cp::unauthorizedAccess();
        }

        Cp::$title	= __('plugins.uninstall_plugin');
		Cp::$crumb	= __('plugins.uninstall_plugin');

        Cp::$body	= Cp::formOpen(
            ['action' => 'C=Plugins'.AMP.'action=uninstall'.AMP.'plugin='.$plugin],
            ['confirmed' => '1']
		);

        $MOD = $this->loadManager($plugin);
		$name = $MOD->name();

		Cp::$body .= Cp::quickDiv('alertHeading', __('plugins.uninstall_plugin'));
		Cp::$body .= Cp::div('box');
		Cp::$body .= Cp::quickDiv('defaultBold', __('plugins.uninstall_plugin_confirm'));
		Cp::$body .= Cp::quickDiv('defaultBold', BR.$name);
		Cp::$body .= Cp::quickDiv('alert', BR.__('plugins.data_will_be_lost')).BR;
		Cp::$body .= '</div>'.PHP_EOL;

		Cp::$body .= Cp::quickDiv('paddingTop', Cp::input_submit(__('plugins.uninstall_plugin')));
		Cp::$body .= '</form>'.PHP_EOL;
    }
}
