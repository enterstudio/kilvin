<?php

namespace Kilvin\Http\Controllers;

use DB;
use File;
use Site;
use Schema;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\Finder\Finder;

class InstallController extends Controller
{
    private $request;
    public $system_path;
    public $cms_folder;
    public $app_path;
    private $variables = [];

    public $data = [
        'domain'                     => '',
        'ip'                         => '',
        'db_connection'              => 'mysql',
        'db_hostname'                => '127.0.0.1',
        'db_username'                => '',
        'db_password'                => '',
        'db_name'                    => '',
        'site_name'                  => '',
        'site_url'                   => '',
        'site_index'                 => '',
        'cp_url'                     => '',
        'password'                   => '',
        'screen_name'                => '',
        'email'                      => '',
        'notification_sender_email'  => '',
        'default_language'           => 'english',
        'template'                   => '01',
        'site_timezone'              => '',
        'uploads_path'               => '{PUBLIC_PATH}images/uploads/',
        'uploads_url'                => '{SITE_URL}images/uploads/',
    ];

    /**
     * Viggo, the Constructor!
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->cms_folder  = config('cms.cms_folder', 'cms');
        $this->system_path = base_path().DIRECTORY_SEPARATOR;

        $this->variables['version'] = CMS_VERSION;
    }

    // --------------------------------------------------------------------

    /**
     *  Run Installer
     *
     *  @return string
     */
    public function all()
    {
        if (!is_dir($this->system_path)) {
            exit('Unable to find CMS folder.');
        }

        foreach ($this->request->all() as $key => $val) {
            if (isset($this->data[$key])) {
                $this->data[$key] = trim($val);
            }
        }

        $page = (!empty($_GET['page'])) ? $_GET['page'] : 1;

        switch($page) {
            case 2:
                return $this->settingsForm();
            break;

            case 3:
                return $this->performInstall();
            break;

            default:
                return $this->homepage();
            break;
        }
    }

    // --------------------------------------------------------------------

    /**
     *  Homepage of Installer
     *
     *  @return string
     */
    private function homepage()
    {
        $requirements_failure = false;
        $results = $this->installationTests();

        // Show PreFlight Errors Page
        if ($results !== true) {
            $this->variables['errors'] = $results;
            return view('errors', $this->variables);
        }

        // Show Homepage!
        return view('homepage', $this->variables);
    }


    // --------------------------------------------------------------------

    /**
     *  Settings Form
     *
     *  @return string
     */
    function settingsForm($errors = [])
    {
        // ----------------------------------------
        //  Help Them with a few Vars
        // ----------------------------------------

        $host       = ( ! isset($_SERVER['HTTP_HOST'])) ? '' : $_SERVER['HTTP_HOST'];
        $phpself    = ( ! isset($_SERVER['PHP_SELF'])) ? '' : trim($_SERVER['PHP_SELF'], '/');

        $url = (
            isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ?
            'https://' :
            'http://'
            ).
            $host.'/'.$phpself;

        $url = substr($url, 0, -strlen($phpself));

        $cp_url     = ($this->data['cp_url'] == '') ? $url.'admin.php' : $this->data['cp_url'];
        $site_url   = ($this->data['site_url'] == '' OR $this->data['site_url'] == '/') ? $url : $this->data['site_url'];
        $site_index = ''; // For now we assume no index.php in URL

        $db_hostname        = ($this->data['db_hostname'] == '')          ? 'localhost'   : $this->data['db_hostname'];
        $db_username        = ($this->data['db_username'] == '')          ? ''            : $this->data['db_username'];
        $db_password        = ($this->data['db_password'] == '')          ? ''            : $this->data['db_password'];
        $db_name            = ($this->data['db_name'] == '')              ? ''            : $this->data['db_name'];
        $password           = ($this->data['password'] == '')             ? ''            : $this->data['password'];
        $email              = ($this->data['email'] == '')                ? ''            : $this->data['email'];
        $screen_name        = ($this->data['screen_name'] == '')          ? ''            : $this->data['screen_name'];
        $notification_sender_email    = ($this->data['email'] == '')      ? ''            : $this->data['email'];
        $template           = ($this->data['template'] == '')             ? '01'          : $this->data['template'];
        $site_name          = ($this->data['site_name'] == '')            ? ''            : $this->data['site_name'];
        $default_language   = ($this->data['default_language'] == '')     ? 'en_US'       : $this->data['default_language'];
        $timezone           = ($this->data['site_timezone'] == '')        ? 'UTC'         : $this->data['site_timezone'];

        // ----------------------------------------
        //  Themes
        // ----------------------------------------

        $themes = [];

        $source_dir = $this->system_path.'resources/site_themes';

        if (File::isDirectory($source_dir)) {
            foreach (Finder::create()->in($source_dir)->directories()->depth(0) as $dir) {
                $themes[] = $dir->getFilename();
            }
        }

        $theme_options = [];

        foreach ($themes as $folder) {
            $theme_options[] = [
                'value' => $folder,
                'name'  => ucwords(str_replace('_', ' ', $folder)),
                'selected' => ($template == $folder) ? 'selected' : ''

            ];
        }

        // ----------------------------------------
        //  Set up Vars
        // ----------------------------------------

        $this->variables['errors']     = $errors;
        $this->variables['themes']     = $theme_options;
        $this->variables['cp_url']     = (!empty($this->data['cp_url'])) ? $this->data['cp_url'] : $cp_url;
        $this->variables['site_url']   = (!empty($this->data['site_url'])) ? $this->data['site_url'] : $site_url;
        $this->variables['site_index'] = (!empty($this->data['site_index'])) ? $this->data['site_index'] : $site_index;

        // Show Settings Form!
        return view('form', array_merge($this->data, $this->variables));
    }


    // --------------------------------------------------------------------

    /**
     *  Existing Installation Form
     *
     *  @return string
     */
    function existingInstallForm()
    {
        $fields = '';
        foreach($this->request->all() as $key => $value)
        {
            $fields .= '<input
                type="hidden"
                name="'.str_replace("'", "&#39;", htmlspecialchars($key)).'"
                value="'.str_replace("'", "&#39;", htmlspecialchars($value)).'">'.PHP_EOL;
        }

        $this->variables['fields'] = $fields;

        return view('existingInstall', array_merge($this->data, $this->variables));
    }

    // --------------------------------------------------------------------

    /**
     *  Perform the Install
     *
     *  @return string
     */
    public function performInstall()
    {
        // -----------------------------------
        //  Validation
        // ------------------------------------

        $errors = $this->validateSettings();

        if (!empty($errors)) {
            return $this->settingsForm($errors);
        }

        $this->data['site_url'] = rtrim($this->data['site_url'], '/').'/';

        // -----------------------------------
        //  Set up Connection to DB
        // -----------------------------------

        $connections = config('database.connections');

        $connections['mysql'] = [
            'driver' => 'mysql',
            'host' => $this->data['db_hostname'],
            'port' => '3306',
            'database' => $this->data['db_name'],
            'username' => $this->data['db_username'],
            'password' => $this->data['db_password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => 'cms_',
            'strict' => true,
        ];

        config()->set('database.connections', $connections);
        config()->set('database.default', 'mysql');

        // -----------------------------------
        //  Test DB Connection
        // -----------------------------------

        try {
            $sites_exists = Schema::hasTable('sites');
        }
        catch(\Exception $e) {
            return $this->settingsForm([$e->getMessage()]);
        }

        // -----------------------------------
        //  Existing Install?
        // -----------------------------------

        if ($sites_exists && ! $this->request->input('install_override'))
        {
            $fields = '';

            foreach($this->request->all() as $key => $value)
            {
                if($key === '_token') {
                    continue;
                }

                $fields .=
                    '<input type="hidden" name="'.
                        str_replace("'", "&#39;", htmlspecialchars($key)).'" value="'.
                        str_replace("'", "&#39;", htmlspecialchars($value)).'" />'.PHP_EOL;
            }

            // Existing Installer
            return $this->existingInstallForm();
        }

        // --------------------------------
        //  Write DB Creds to .env file
        // --------------------------------

        $this->writeDatabaseCredsToEnvFile();

        // -----------------------------------
        //  Migration
        // -----------------------------------

        $this->runMigration();

        // -----------------------------------
        //  Seeder
        // -----------------------------------

        $this->setUsersIpAddress();
        $this->runSeeder();

        // -----------------------------------
        //  Write DB Config
        // -----------------------------------

        $this->writeDatabaseConfig();

        // --------------------------------
        //  Install Complete, Write CMS
        // --------------------------------

        $this->writeCmsVariablesToEnvFile();

        // -----------------------------------
        //  Success!
        // -----------------------------------

        return view('success', array_merge($this->data, $this->variables));
    }

    // --------------------------------------------------------------------

    /**
     *  Validate Settings Form
     *
     *  @return array
     */
    public function validateSettings()
    {
         $errors = [];

        // -----------------------------------
        //  Required Fields
        // ------------------------------------

        if (
            $this->data['db_hostname'] == '' or
            $this->data['db_username'] == '' or
            $this->data['db_name']     == '' or
            $this->data['site_name']   == '' or
            $this->data['screen_name'] == '' or
            $this->data['password']    == '' or
            $this->data['email']       == ''
           )
        {
            $errors[] = "You left some form fields empty";
            return $errors;
        }

        // -----------------------------------
        //  Required Fields
        // ------------------------------------

        if (strlen($this->data['screen_name']) < 4)
        {
            $errors[] = "Your screen name must be at least 4 characters in length";
        }

        if (strlen($this->data['password']) < 10)
        {
            $errors[] = "Your password must be at least 10 characters in length.";
        }

        $installed_version = config('cms.installed_version');

        if (!empty($installed_version))
        {
            $errors[] = "Your installation lock is set. Locate the <strong>.env</strong> file and set CMS_INSTALLED_VERSION to empty.";
        }

        // -----------------------------------
        //  Username and Password based off each other?
        // ------------------------------------

        $lowercase_user = strtolower($this->data['email']);
        $lowercase_password = strtolower($this->data['password']);

        if ($lowercase_user == $lowercase_password or $lowercase_user == strrev($lowercase_password))
        {
            $errors[] = "Your password can not be based on your email address.";
        }

        if (strpos($this->data['db_password'], '$') !== FALSE)
        {
            $errors[] = "Your MySQL password can not contain a dollar sign (\$)";
        }

        if ( ! preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $this->data['email']))
        {
            $errors[] = "The email address you submitted is not valid";
        }

        $themes_path = $this->system_path.'resources'.DIRECTORY_SEPARATOR.'site_themes'.DIRECTORY_SEPARATOR;

        if ( ! file_exists($themes_path.$this->data['template'].DIRECTORY_SEPARATOR.$this->data['template'].'.php'))
        {
            // @todo - Themes not available yet
            // $errors[] = "Error: Unable to load the theme you have selected.";
        }

        return $errors;
    }

    // --------------------------------------------------------------------

    /**
     *  Set User's IP Address
     *
     *  @return void
     */
    private function setUsersIpAddress()
    {
        $CIP = ( ! isset($_SERVER['HTTP_CLIENT_IP']))       ? FALSE : $_SERVER['HTTP_CLIENT_IP'];
        $FOR = ( ! isset($_SERVER['HTTP_X_FORWARDED_FOR'])) ? FALSE : $_SERVER['HTTP_X_FORWARDED_FOR'];
        $RMT = ( ! isset($_SERVER['REMOTE_ADDR']))          ? FALSE : $_SERVER['REMOTE_ADDR'];

        if ($CIP)
        {
            $cip = explode('.', $CIP);

            $this->data['ip'] = ($cip['0'] != current(explode('.', $RMT))) ? implode('.', array_reverse($cip)) : $CIP;
        }
        elseif ($FOR)
        {
            $this->data['ip'] = (strstr($FOR, ',')) ? end(explode(',', $FOR)) : $FOR;
        }
        else {
            $this->data['ip'] = $RMT;
        }
    }

    // --------------------------------------------------------------------

    /**
     *  Run the Migration
     *
     *  @return void
     */
    public function runMigration()
    {
        require_once CMS_PATH.'database/migrations/2017_06_12_000000_create_cms_tables.php';

        $class = new \CreateCmsTables;

        $class->down(); // Kill existing tables
        $class->up();   // Put in Fresh tables
    }

    // --------------------------------------------------------------------

    /**
     *  Run the Seeder
     *
     *  @return void
     */
    public function runSeeder()
    {
        require_once CMS_PATH.'database/seeds/CmsSeeder.php';

        $class = new \CmsSeeder;
        $class->data = $this->data;
        $class->system_path = $this->system_path;
        $themes_path = $this->system_path.'resources'.DIRECTORY_SEPARATOR.'site_themes'.DIRECTORY_SEPARATOR;
        $class->theme_path = $themes_path.$this->data['template'].DIRECTORY_SEPARATOR.$this->data['template'].'.php';
        $class->run();
    }

    // --------------------------------------------------------------------

    /**
     *  Write the Database Config Values
     *
     *  @return void
     */
    public function writeDatabaseConfig()
    {
        // ---------------------------------------
        //  Site Prefs, Defaults
        // ---------------------------------------

        $config = [
            'installed_version'             =>  CMS_VERSION, // What version is installed in DB/config, opposed to version of files
            'site_debug'                    =>  1,
            'cp_url'                        =>  $this->data['cp_url'],
            'site_index'                    =>  $this->data['site_index'],
            'site_name'                     =>  $this->data['site_name'],
            'theme_folder_url'              =>  '{SITE_URL}themes/',
            'notification_sender_email'     =>  $this->data['email'],
            'show_queries'                  =>  'n',
            'template_debugging'            =>  'n',
            'include_seconds'               =>  'n',
            'cookie_domain'                 =>  '',
            'cookie_path'                   =>  '',
            'allow_email_change'            =>  'y',
            'allow_multi_emails'            =>  'n',
            'default_language'              =>  $this->data['default_language'],
            'date_format'                   =>  'Y-m-d',
            'time_format'                   =>  'H:i',
            'site_timezone'                 =>  $this->data['site_timezone'],
            'cp_theme'                      =>  'default',
            'un_min_len'                    =>  '5',
            'password_min_length'           =>  '10',
            'default_member_group'          =>  '5',
            'enable_photos'                 => 'y',
            'photo_url'                     => '{SITE_URL}images/member_photos/',
            'photo_path'                    => '{PUBLIC_PATH}images/member_photos/',
            'photo_max_width'               => '100',
            'photo_max_height'              => '100',
            'photo_max_kb'                  => '50',
            'save_tmpl_revisions'           =>  'n',
            'max_tmpl_revisions'            =>  '5',
            'enable_censoring'              =>  'n',
            'censored_words'                =>  '',
            'censor_replacement'            =>  '',
            'banned_ips'                    =>  '',
            'banned_emails'                 =>  '',
            'banned_screen_names'           =>  '',
            'ban_action'                    =>  'restrict',
            'ban_message'                   =>  'This site is currently unavailable',
            'ban_destination'               =>  'https://google.com/',
            'recount_batch_total'           =>  '1000',
            'enable_image_resizing'         =>  'y',
            'image_resize_protocol'         =>  'gd2',
            'image_library_path'            =>  '',
            'thumbnail_prefix'              =>  'thumb',
            'word_separator'                =>  'underscore',
            'new_posts_clear_caches'        =>  'y',
            'enable_throttling'             => 'n',
            'banish_masked_ips'             => 'y',
            'max_page_loads'                => '10',
            'time_interval'                 => '8',
            'lockout_time'                  => '30',
            'banishment_type'               => 'message',
            'banishment_url'                => '',
            'banishment_message'            => 'You have exceeded the allowed page load frequency.',
            'disable_events'                => 'n',
            'is_site_on'                    => 'y',
            'theme_folder_path'             => '{PUBLIC_PATH}themes/',
        ];

        // ---------------------------------------
        //  Add the Prefs
        // ---------------------------------------

        $update_prefs = [];

        foreach(Site::preferenceKeys() as $value) {
            $update_prefs[$value] = $config[$value];
        }

        if (!empty($update_prefs)) {
            foreach($update_prefs as $handle => $value) {
                DB::table('site_preferences')
                    ->insert(
                        [
                            'site_id' => 1,
                            'handle'  => $handle,
                            'value'   => $value
                        ]);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
     *  Write the .env file
     *
     *  @return void
     */
    private function writeDatabaseCredsToEnvFile()
    {
        $new = [
            'DB_CONNECTION' => $this->data['db_connection'],
            'DB_HOST'       => $this->data['db_hostname'],
            'DB_PASSWORD'   => $this->data['db_password'],
            'DB_USERNAME'   => $this->data['db_username'],
            'DB_DATABASE'   => $this->data['db_name']
        ];

        $encryption_key = $this->generateEncryptionKey();

        $new['APP_KEY'] = $encryption_key;

        $this->writeNewEnvironmentFileWith($new, true);

        return true;
    }

    // --------------------------------------------------------------------

    /**
     *  Write the .env file
     *
     *  @return void
     */
    private function writeCmsVariablesToEnvFile()
    {
        $new = [
            'CMS_IS_SYSTEM_ON'      => 'true',
            'CMS_DISABLE_EVENTS'    => 'false',
            'CMS_INSTALLED_VERSION' => CMS_VERSION
        ];

        $this->writeNewEnvironmentFileWith($new);

        return true;
    }

    // --------------------------------------------------------------------

    /**
     * Generate a random key for the application.
     *
     * @return string
     */
    protected function generateEncryptionKey()
    {
        return 'base64:'.base64_encode(random_bytes(
            config('app.cipher') == 'AES-128-CBC' ? 16 : 32
        ));
    }

    // --------------------------------------------------------------------

    /**
     * Write a new environment file with the given key.
     *
     * @param  array  $new
     * @return void
     */
    protected function writeNewEnvironmentFileWith($new, $example = false)
    {
        // The example is what starts us off
        if ($example === true) {
            $string = file_get_contents(app()->environmentFilePath().'.example');
        } else {
            $string = file_get_contents(app()->environmentFilePath());
        }

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
     *  Create Cache Directories
     *
     *  @return void
     */
    public function createCacheDirectories()
    {
        $cache_path = $this->system_path.'/cache/';
        $cache_dirs = ['page_cache', 'tag_cache'];
        $errors = [];

        foreach ($cache_dirs as $dir)
        {
            if ( ! @is_dir($cache_path.$dir))
            {
                if ( ! @mkdir($cache_path.$dir, 0777))
                {
                    $errors[] = $dir;

                    continue;
                }

                @chmod($cache_path.$dir, 0777);
            }
       }
    }

    // -----------------------------------------
    //  Fetch names of installed languages
    // -----------------------------------------

    private function language_pack_names($default)
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

    // --------------------------------------------------------------------

    /**
     *  Run System Tests Prior to Installation
     *
     *  @return boolean|array
     */
    private function installationTests()
    {
        $errors = [];

        if (version_compare(phpversion(), '7.1') == -1)
        {
            $errors[] =
                [
                    'Unsupported PHP version',
                    sprintf(
                        'In order to install Kilvin CMS, your server must be running PHP version 7.1 or newer.
                         Your server is running PHP version <em>%s</em>',
                        phpversion()
                    )
                ];
        }

        // ---------------------------------------
        //  Writeable Files + Directories
        // ---------------------------------------

        $writable_things = [
            // $this->system_path.'../.env',
            $this->system_path.'templates/',
            $this->system_path.'storage/',
            './images/uploads',
        ];


        $not_writable = [];

        foreach ($writable_things as $val)
        {
            if (!is_writable($val))
            {
                $not_writable[] = $val;
            }
        }

        if (count($not_writable) > 0)
        {
            $title =  "Error: Incorrect Permissions";

            $d = (count($not_writable) > 1) ? 'directories or files' : 'directory or file';

            $message = "The following {$d} cannot be written to:<p>";

            foreach ($not_writable as $bad)
            {
                $message .= '<em>'.$bad.'</em><br >';
            }

            $message .= '
            </p><p>In order to install Kilvin CMS, the file permissions on the above must be set as indicated in the instructions.
            If you are not sure how to set permissions, <a href="#">click here</a>.</p>';

            $errors[] = [$title, $message];
        }

        // ---------------------------------------
        //  Already Installed?
        // ---------------------------------------

        $installed_version = config('cms.installed_version');

        if (!empty($installed_version))
        {
            $errors[]  = [
                "Warning: Your installation is locked!",
                "<p>There already appears to be an instance of Kilvin CMS installed!</p>
                <p>To continue locate the <strong>.env</strong> file and set CMS_INSTALLED_VERSION to empty.</p>"
            ];
        }

        return (empty($errors)) ? true : $errors;
    }
}
