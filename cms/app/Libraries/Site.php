<?php

namespace Kilvin\Libraries;

use DB;
use Cache;
use Plugins;
use Carbon\Carbon;
use Kilvin\Core\Session;
use Kilvin\Exceptions\CmsFatalException;

/**
 * Site Data and Functionality
 */
class Site
{
    private $config = [];
    private $original_config = [];

    // seven special TLDs for cookie domains
    private $special_tlds = [
        'com', 'edu', 'net', 'org', 'gov', 'mil', 'int'
    ];

    // --------------------------------------------------------------------

    /**
     * Set a Config value
     *
     * @param   string
     * @param   string
     * @return  void
     */
    public function setConfig($which, $value)
    {
        if ( ! isset($this->config[$which])) {
            return;
        }

        $this->config[$which] = $value;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch config value
     *
     * @param   string
     * @param   boolean
     * @return  mixed
     */
    public function config($which = '', $add_slash = false)
    {
       if ($which == '') {
            return null;
        }

        if ( ! isset($this->config[$which])) {
            return null;
        }

        $pref = $this->config[$which];

        if (is_string($pref)) {
            if ($add_slash !== false) {
                $pref = rtrim($pref, '/').'/';
            }

            $pref = str_replace('\\\\', '\\', $pref);
        }

        return $pref;
    }

    // --------------------------------------------------------------------

    /**
     * Fetch original config value (no paths or urls changed for Site/Domain)
     *
     * @param   string
     * @param   boolean
     * @return  mixed
     */
    public function originalConfig($which = '', $add_slash = false)
    {
       if ($which == '') {
            return null;
        }

        if ( ! isset($this->original_config[$which])) {
            return null;
        }

        $pref = $this->original_config[$which];

        if (is_string($pref)) {
            if ($add_slash !== false) {
                $pref = rtrim($pref, '/').'/';
            }

            $pref = str_replace('\\\\', '\\', $pref);
        }

        return $pref;
    }

    // --------------------------------------------------------------------

    /**
     * Determine Domain and Site based off request host + uri
     *
     * @return  void
     */
    public function loadDomainMagically()
    {
        $host       = request()->getHost();
        $http_host  = request()->getHttpHost(); // includes port
        $uri        = request()->getRequestUri(); // Hey, maybe there's a folder!

        try {
            $query = DB::table('domains')
                ->select('domain_id')
                ->where('site_url', 'LIKE', '%'.$host.'%')
                ->get();
        } catch (\InvalidArgumentException $e) {
            throw new CmsFatalException('Unable to Load CMS. Database is either not up or credentials are invalid.');
        }

        if ($query->count() == 0) {
            throw new CmsFatalException('Unable to Load Site; No Matching Site Domains Found');
        }

        if ($query->count() == 1) {
            $this->loadDomainPrefs($query->first()->domain_id);
        }

        // @todo - We have two matches? Figure out which one is the best based off domain + uri
        $this->loadDomainPrefs($query->first()->domain_id);
    }

    // --------------------------------------------------------------------

    /**
     * Load Domain Preferences
     *
     * @param   integer
     * @return  void
     */
    public function loadDomainPrefs($domain_id)
    {
        $query = DB::table('domains')
            ->join('sites', 'sites.site_id', '=', 'domains.site_id')
            ->join('site_preferences', 'site_preferences.site_id', '=', 'sites.site_id')
            ->where('domain_id', $domain_id)
            ->get();

        if ($query->count() === 0) {
            abort(500, 'Unable to Load Site Preferences. No Domain Found.');
        }

        $this->parseDomainPrefs($query);
    }

    // --------------------------------------------------------------------

    /**
     * Parse Domain Preferences from Query Result
     *
     * @param   object
     * @return  void
     */

    public function parseDomainPrefs($query)
    {
        // ------------------------------------
        //  Reset Preferences
        // ------------------------------------

        $this->config = $cms_config = config('cms');

        // ------------------------------------
        //  Fold in the Preferences in the Database
        // ------------------------------------

        foreach($query as $row) {
            $this->config[$row->handle] = $row->value;
        }

        // ------------------------------------
        //  A PATH, A PATH!!
        // ------------------------------------

        $cms_path    =
            (!empty($query->first()->cms_path)) ?
            $query->first()->cms_path :
            CMS_PATH;

        $public_path =
            (!empty($query->first()->public_path)) ?
            $query->first()->public_path :
            realpath(CMS_PATH.'../public');

        $this->config['CMS_PATH']    = rtrim($cms_path, '/').'/';
        $this->config['PUBLIC_PATH'] = rtrim($public_path, '/').'/';

        // ------------------------------------
        //  Few More Variables
        // ------------------------------------

        $this->config['site_id']         = (int) $query->first()->site_id;
        $this->config['site_name']       = (string) $query->first()->site_name;
        $this->config['site_short_name'] = $this->config['site_handle'] = (string) $query->first()->site_handle;
        $this->config['site_url']        = (string) $query->first()->site_url;

        $this->original_config           = $this->config;

        // ------------------------------------
        //  Paths and URL special vars!
        // ------------------------------------

        foreach($this->config as $key => &$value) {

            // Keep the booleans
            if (isset($cms_config[$key])) {
                continue;
            }

            $value = str_replace('{SITE_URL}', $this->config['site_url'], $value);
            $value = str_replace('{CMS_PATH}', $this->config['CMS_PATH'], $value);
            $value = str_replace('{PUBLIC_PATH}', $this->config['PUBLIC_PATH'], $value);
        }

        // If we just reloaded, then we reset a few things automatically
        if ($this->config('show_queries') == 'y' or REQUEST == 'CP') {
            DB::enableQueryLog();
        }
    }

    // ------------------------------------------------

    /**
     * List all plugins
     *
     * @return array
     */
    public static function pluginsList()
    {
        return Plugins::list();
    }

    // ------------------------------------------------

    /**
     * List all sites
     *
     * @return array
     */
    public static function sitesList()
    {
        $storeTime = Carbon::now()->addMinutes(1);

        $query = static function()
        {
            return DB::table('sites')
                ->select('site_id', 'site_name')
                ->orderBy('site_name')
                ->get();
        };

        // File and database storage stores do not support tags
        // And Laravel throws an exception if you even try ::rolls eyes::
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags('sites')->remember('cms.libraries.site.sitesList', $storeTime, $query);
        }

        return Cache::remember('cms.libraries.site.sitesList', $storeTime, $query);
    }

    // --------------------------------------------------------------------

    /**
     * Return preferences located in sites table's fields
     *
     * @param   string
     * @return  array
     */
    public function preferenceKeys()
    {
        return [
            'site_debug',
            'is_site_on',
            'cp_url',
            'site_index',
            'theme_folder_url',
            'theme_folder_path',
            'notification_sender_email',
            'show_queries',
            'template_debugging',
            'include_seconds',
            'cookie_domain',
            'cookie_path',
            'default_language',
            'date_format',
            'time_format',
            'site_timezone',
            'cp_theme',
            'enable_censoring',
            'censored_words',
            'censor_replacement',
            'banned_ips',
            'banned_emails',
            'banned_screen_names',
            'ban_action',
            'ban_message',
            'ban_destination',
            'recount_batch_total',
            'enable_throttling',
            'banish_masked_ips',
            'max_page_loads',
            'time_interval',
            'lockout_time',
            'banishment_type',
            'banishment_url',
            'banishment_message',

            'password_min_length',
            'default_member_group',
            'enable_photos',
            'photo_url',
            'photo_path',
            'photo_max_width',
            'photo_max_height',
            'photo_max_kb',

            'save_tmpl_revisions',
            'max_tmpl_revisions',

            'enable_image_resizing',
            'image_resize_protocol',
            'image_library_path',
            'thumbnail_prefix',
            'word_separator',
            'new_posts_clear_caches',
        ];
    }

    // ------------------------------------------------

    /**
     * All the Data for All Sites
     *
     * @return array
     */
    public static function sitesData()
    {
        $storeTime = Carbon::now()->addMinutes(1);

        $query = static function()
        {
            return DB::table('sites')
                ->orderBy('site_name')
                ->get();
        };

        // File and database storage stores do not support tags
        // And Laravel throws an exception if you even try ::rolls eyes::
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags('sites')->remember('cms.libraries.site.sitesData', $storeTime, $query);
        }

        return Cache::remember('cms.libraries.site.sitesData', $storeTime, $query);
    }

    // ------------------------------------------------

    /**
     * Flush all Site Caches
     *
     * @return void
     */
    public static function flushSiteCache()
    {
        // File and database storage stores do not support tags
        // And Laravel throws an exception if you even try ::rolls eyes::
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags('sites')->flush();
            return;
        }

        Cache::forget('cms.libraries.site.sitesList');
    }
}
