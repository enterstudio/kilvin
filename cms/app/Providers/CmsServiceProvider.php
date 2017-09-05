<?php

namespace Kilvin\Providers;

use Request;
use Carbon\Carbon;
use Kilvin\Libraries;
use Illuminate\Support\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (defined('REQUEST') && REQUEST === 'CP') {
            view()->composer('*', 'Kilvin\Http\ViewComposers\Cp');
        }

        $this->app->singleton('cms.statistics', function () {
            return new Libraries\Statistics;
        });

        $this->app->singleton('cms.cp', function () {
            return new Libraries\ControlPanel;
        });

        $this->app->singleton('cms.site', function () {
            return new Libraries\Site;
        });

        $this->app->singleton('cms.template', function () {
            return new Libraries\Template;
        });

        $this->app->singleton('cms.plugins', function () {
            return new Libraries\Plugins;
        });

        $this->app->singleton('cms.twig.plugin_variable', function () {
            return new Libraries\Twig\Templates\PluginVariable;
        });

        // --------------------------------------------------
        //  Determine system path and site name
        // --------------------------------------------------

        // We allow the renaming of the CMS folder, one day...
        $cms_folder  = config('cms.cms_folder', 'cms');
        $system_path = base_path().DIRECTORY_SEPARATOR;
        $app_path    = $system_path.'app'.DIRECTORY_SEPARATOR;

        // ----------------------------------------------
        //  Set base system constants
        // ----------------------------------------------

        define('CMS_NAME'               , 'Kilvin CMS');
        define('CMS_VERSION'            , '0.0.6');
        define('CMS_BUILD_DATE'         , '20170812');
        define('CMS_FILES_VERSION'      , CMS_VERSION);

        define('CMS_PATH'               , $system_path);

        define('CMS_PATH_CACHE'         , $system_path.'storage'.DIRECTORY_SEPARATOR);
        define('CMS_PATH_RESOURCES'     , $system_path.'resources'.DIRECTORY_SEPARATOR);
        define('CMS_PATH_TEMPLATES'     , $system_path.'templates'.DIRECTORY_SEPARATOR);

        define('CMS_PATH_THIRD_PARTY'   , $system_path.'plugins'.DIRECTORY_SEPARATOR);
        define('CMS_PATH_PLUGINS'       , $app_path.'Plugins'.DIRECTORY_SEPARATOR);

        define('AMP'                    , '&amp;');
        define('BR'                     , '<br />');
        define('NBS'                    , '&nbsp;');

        // ----------------------------------------------
        //  Determine the request type
        // ----------------------------------------------

        // There are FOUR possible request types:
        // 1. INSTALLer
        // 2. A CP request
        // 4. A SITE page (i.e. template or action) request

        if ( ! defined('REQUEST')) {
            exit('Unable to determine the type of request.');
        }

        if (REQUEST === 'SITE') {
            if (Request::filled('ACT')) {
                define('ACTION', Request::input('ACT'));
            }
        }

        if (REQUEST === 'CP') {
            define('BASE', SELF);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // ----------------------------------------------
        //  App Debugging
        // ----------------------------------------------

        if (config('app.debug') == true) {
            error_reporting(E_ALL);
        }

        // ----------------------------------------------
        //  Installer?
        //  - Stop here, no need to check if system is on
        // ----------------------------------------------

        if (in_array(REQUEST, ['INSTALL','CONSOLE'])) {
            return;
        }

        // ----------------------------------------------
        //  Check config file is ready
        // ----------------------------------------------

        if ( config()->get('cms.installed_version') === null) {
            exit(CMS_NAME." does not appear to be installed.");
        }
    }
}
