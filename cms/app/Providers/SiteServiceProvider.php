<?php

namespace Kilvin\Providers;

use Site;
use Cookie;
use Request;
use Closure;
use Carbon\Carbon;
use Kilvin\Core\Url;
use Kilvin\Core\Output;
use Kilvin\Core\Language;
use Kilvin\Core\Functions;
use Illuminate\Http\Response;
use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;

class SiteServiceProvider extends ServiceProvider
{
        /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (defined('REQUEST') && in_array(REQUEST, ['INSTALL','CONSOLE'])) {
            return;
        }

        // ----------------------------------------------
        //  Set Site Preferences class
        // ----------------------------------------------

        try {
            if (defined('DOMAIN_ID')) {
                Site::loadDomainPrefs(DOMAIN_ID);
            }
            elseif (
                REQUEST == 'CP' &&
                Request::hasCookie('cp_last_domain_id') &&
                is_numeric(decrypt(Request::cookie('cp_last_domain_id'))))
            {
                Site::loadDomainPrefs(decrypt(Request::cookie('cp_last_domain_id')));
            } else {
                Site::loadDomainMagically();
            }

        } catch(\Illuminate\Database\QueryException $e) {
            exit('Unable to load the CMS. Please check your database settings.');
        }

        if (!empty(Site::config('cookie_path'))) {
            $sconfig = config()->get('session');
            Cookie::setDefaultPathAndDomain(Site::config('cookie_path'), $sconfig['domain'], $sconfig['secure']);
        }

        if (Site::config('site_debug') == 2) {
            error_reporting(E_ALL);
        }

        // ----------------------------------------------
        //  Theme Paths
        // ----------------------------------------------

        $cms_folder  = config('cms.cms_folder', 'cms');

        if (Site::config('theme_folder_path') !== FALSE && Site::config('theme_folder_path') != '') {
            $theme_path = preg_replace("#/+#", "/", Site::config('theme_folder_path').'/');

            if (!is_dir($theme_path)) {
                unset($theme_path);
            }
        }

        if (!isset($theme_path)) {
            $theme_path = substr(CMS_PATH, 0, - strlen($cms_folder.'/')).'/public/themes/';
            $theme_path = preg_replace("#/+#", "/", $theme_path);
        }

        define('PATH_THEMES',       $theme_path);
        define('PATH_SITE_THEMES',  PATH_THEMES.'site_themes/');
        define('PATH_CP_IMG',       Site::config('theme_folder_url', 1).'cp_global_images/');

        if (REQUEST == 'CP')
        {
            define('PATH_CP_THEME', PATH_THEMES.'cp_themes/');
        }
    }
}
