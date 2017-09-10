<?php

namespace Kilvin\Libraries;

use DB;
use File;
use Carbon\Carbon;
use Kilvin\Core\Session;
use Kilvin\Exceptions\CmsFailureException;

/**
 * Site Data and Functionality
 */
class Plugins
{
    private $plugins;

    // ---------------------------------------------------

    /**
     * The Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->loadPlugins();
    }

    // ---------------------------------------------------

    /**
     * List installed plugins
     *
     * @return array
     */
    public function list()
    {
        return $this->plugins;
    }

    // ---------------------------------------------------

    /**
     * Load Plugins
     *
     * @return array
     */
    public function loadPlugins()
    {
        if (!empty($this->plugins)) {
            return;
        }

        $query = DB::table('plugins')
            ->orderBy('plugin_name')
            ->get();

        $plugins = [];

        foreach($query as $row) {
            $row->details = (object) $this->findPluginLoadingDetails($row->plugin_name);
        }

        return $this->plugins = $query;
    }

    // --------------------------------------------------------------------

    /**
    * Find Details for Loading Plugin
    *
    * @param string $plugin
    * @return array
    */
    public function findPluginLoadingDetails($plugin)
    {
        $plugin = filename_security($plugin);

        $core_namespace  = '\\Kilvin\\Plugins\\'.$plugin.'\\';
        $core_path       = $this->cmsPluginPath($plugin);
        $third_namespace = $plugin.'\\';
        $third_path      = $this->thirdPartyPath($plugin);

        if (class_exists($core_namespace.'Manager')) {
            return [
                'core'      => true,
                'path'      => $core_path,
                'namespace' => $core_namespace,
                'plugin'    => $plugin
            ];
        }

        // dd($third_path.'Manager.php');

        if (file_exists($third_path.'Manager.php')) {
            if (class_exists($third_namespace.'Manager')) {
                return [
                    'core'      => false,
                    'path'      => $third_path,
                    'namespace' => $third_namespace,
                    'plugin'    => $plugin
                ];
            }
        }

        throw new CmsFailureException(sprintf(__('plugins.plugin_cannot_be_found'), $plugin));
    }

    // --------------------------------------------------------------------

    /**
    * Load Plugin Class
    *
    * @param string $plugin
    * @param string $class
    * @return string The full class path
    */
    public function loadPluginClass($plugin, $class)
    {
        if (preg_match('/[^A-Za-z\_]/', $class)) {
            throw new CmsFailureException(__('plugins.invalid_class_for_plugins'));
        }

        $details = $this->findPluginLoadingDetails($plugin);

        extract($details);

        $class = $namespace.$class;

        if (class_exists($class)) {
            return new $class;
        }

        if (file_exists($path.$class.'.php')) {
            require_once $path.$class.'.php';

            if (class_exists($class)) {
                return new $class;
            }
        }

        throw new CmsFailureException(sprintf(__('plugins.plugin_cannot_be_found'), $plugin));
    }

    // --------------------------------------------------------------------

    /**
    * Find Third Party Plugin's Path
    *
    * @param string $plugin
    * @return string
    */
    public function thirdPartyPath($plugin)
    {
        $plugin = filename_security($plugin);

        return CMS_PATH_THIRD_PARTY.$plugin.DIRECTORY_SEPARATOR;
    }

    // --------------------------------------------------------------------

    /**
    * Find Core Plugin's Path
    *
    * @param string $plugin
    * @return string
    */
    public function cmsPluginPath($plugin)
    {
        $plugin = filename_security($plugin);

        return CMS_PATH_PLUGINS.$plugin.DIRECTORY_SEPARATOR;
    }

    // --------------------------------------------------------------------

    /**
    * Load Plugin Language
    *
    * @param string $plugin
    * @return void
    */
    public function loadPluginLanguage($plugin)
    {
        $plugin  = filename_security($plugin);
        $details = $this->findPluginLoadingDetails($plugin);

        extract($details);

        $locale = app('translator')->locale();

        if(stristr($locale, '_')) {
            $x = explode('_', $locale);
            if (sizeof($x) == 2) {
                $backup_locale = $x[0];
            }
        }

        // -----------------------------------
        //  We allow language in form of 'en_US' or a backup of just 'en'
        // -----------------------------------

        $lang_path =
            $path.
            'language'.DIRECTORY_SEPARATOR.
            $locale.DIRECTORY_SEPARATOR.
            strtolower($plugin).'.php';

        if (file_exists($lang_path)) {
            $messages = require $lang_path;
        }
        elseif (!empty($backup_locale)) {
            $lang_path =
                $path.
                'language'.DIRECTORY_SEPARATOR.
                $backup_locale.DIRECTORY_SEPARATOR.
                strtolower($plugin).'.php';

            if (file_exists($lang_path)) {
                $messages = require $lang_path;
            }
        }

        // -----------------------------------
        //  Messages Exist? Add to Translator
        // -----------------------------------

        if (!empty($messages) && is_array($messages)) {

            foreach($messages as $key => $message) {
                $prefixed[strtolower($plugin).'.'.$key] = $message;
            }

            app('translator')->addLines($prefixed, $locale, '*');
        }
    }


    // --------------------------------------------------------------------

    /**
    * FieldTypes
    *
    * @return array
    */
    public function fieldTypes()
    {
        $field_types = $this->getFieldTypesForCore();

        $plugins = $this->list();
        foreach($plugins as $plugin) {
            $plugin_filters = $this->getFieldTypesForPlugin($plugin);
            $field_types = array_merge($field_types, $plugin_filters);
        }

        return $field_types;
    }

    // ----------------------------------------------------

    /**
     * Get FieldTypes for the Core System
     *
     * @param object
     * @return array
     */
    private function getFieldTypesForCore()
    {
        $field_types_directory = app_path('FieldTypes');

        if (!File::isDirectory($field_types_directory)) {
            return [];
        }
        $namespace = 'Kilvin\\FieldTypes\\';

        $field_types = [];

        foreach(File::files($field_types_directory) as $file_info) {
            if (($field_type = $this->getFieldTypeDetails($namespace, $file_info))) {
                $field_types = array_merge($field_types, $field_type);
            }
        }

        return $field_types;
    }

    // ----------------------------------------------------

    /**
     * Get FieldTypes for a Plugin
     *
     * @param object
     * @return array
     */
    private function getFieldTypesForPlugin($plugin)
    {
        $field_types_directory =
            $plugin->details->path.
            'Templates'.DIRECTORY_SEPARATOR.
            'FieldTypes';

        if (!File::isDirectory($field_types_directory)) {
            return [];
        }
        $namespace = $plugin->details->namespace.'Templates\\FieldTypes\\';

        $field_types = [];

        foreach(File::files($field_types_directory) as $file_info) {
            if (($field_type = $this->getFieldTypeDetails($namespace, $file_info))) {
                $field_types = array_merge($field_types, $field_type);
            }
        }

        return $field_types;
    }

    // ----------------------------------------------------

    /**
     * Get FieldType Details
     *
     * @param string
     * @return array|boolean
     */
    private function getFieldTypeDetails($plugin_namespace, $file_info)
    {
        if (substr($file_info->getFilename(), -4) != '.php') {
            return false;
        }

        $class = substr($file_info->getFilename(), 0, -4);
        $full_class  = $plugin_namespace.$class;

        if (class_exists($full_class)) {
            $object = app($full_class);
            $name = $object->name();

            $info['class_name'] = $class;
            $info['class'] = $plugin_namespace.$class;
            $info['path']  = $file_info->getPathname();

            return [$name => $info];
        }

        return false;
    }

}
