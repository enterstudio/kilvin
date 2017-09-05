<?php

namespace Kilvin\Libraries;

use DB;
use File;
use Site;
use View;
use Request;
use Plugins;
use Carbon\Carbon;
use Kilvin\Core\Url;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;
use Illuminate\Http\Response;
use League\Flysystem\Util\MimeType;

/**
 * Templates Functionality
 */
class Template
{
    /**
     * Does the Template Exist?
     *
     * @param  string  $template
     * @return boolean
     */
    public function exists($template)
    {
        if (View::exists($template)) {
            return true;
        }

        return false;
    }

    // ----------------------------------------------------

    /**
     * Finds a template on the file system and returns its path.
     *
     * All of the following files will be searched for, in this order:
     *
     * - {folderName}/{templateName}
     * - {folderName}/{templateName}.twig.html
     * - {folderName}/{templateName}.twig.css
     * - {folderName}/{templateName}.twig.xml
     * - {folderName}/{templateName}.twig.atom
     * - {folderName}/{templateName}.twig.rss
     *
     * @param string $uri The uri being used
     *
     * @return string|false The path to the template if it exists, or `false`.
     */
    public function find($view)
    {
        $normalized = \Illuminate\View\ViewName::normalize($view);

        try {
            return View::getFinder()->find($normalized);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    // ----------------------------------------------------

    /**
     * Renders a template returns it.
     *
     * @param mixed $template      The full path of the template to load
     * @param array $variables     The variables that should be available to the template.
     *
     * @throws HttpException
     * @return \Illuminate\Http\Response
     */
    public function render($template, $variables = [])
    {
        // ------------------------------------
        //  Meta
        // ------------------------------------

        $path = $this->find($template);

        if(empty($path)) {
            return response()->view('_errors.404', [], 404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        // ------------------------------------
        //  Site Globals like Template Variables
        // ------------------------------------

        $this->loadGlobals();

        // ------------------------------------
        //  Set the Headers
        // ------------------------------------

        $headers = $this->getHeaders($extension);

        // ------------------------------------
        //  Output
        // ------------------------------------

        return response()->view($template, $variables, 200, $headers);
    }

    // ----------------------------------------------------

    /**
     * Sets the Headers for Template Output
     *
     * @param string $type The type of template being outputted (css,html,js,xml,atom)
     * @return void
     */
    private function getHeaders($type, $variables = [])
    {
        $mime = MimeType::detectByFileExtension($type);

        $headers = [
            'X-Powered-By'  => CMS_NAME,
            'Content-Type'  => $mime.'; charset=utf-8'
        ];

        return $headers;
    }

    // ----------------------------------------------------

    /**
     * Load Global Variables into Twig Engine
     *
     * @todo - Finish this
     *
     * @return void
     */
    private function loadGlobals()
    {
        $core_globals = [
            'now' => Localize::createHumanReadableDateTime(),
            'cms' => [
                'version' => CMS_VERSION
            ]
        ];

        $plugin_globals = $this->getPluginVariables();

        View::share(array_merge($plugin_globals, $core_globals));
    }

    // ----------------------------------------------------

    /**
     * Get all of the segments for the uri.
     *
     * @return array
     */
    public function segments($uri)
    {
        $segments = explode('/', $uri);

        return array_values(array_filter($segments, function ($v) {
            return $v != '';
        }));
    }

    // ----------------------------------------------------

    /**
     * Take a URI string and find the right template to display
     *
     * @param string $uri The URI string to parse
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory
     */
    function discover($uri)
    {
        $segments = $this->segments($uri);

        // Homepage, change this once we handle folders vs template groups
        if (empty($segments[0])) {
            return $this->render('index');
        }

        // Homepage with pagination
        if(count($segments) == 2 && preg_match("#^(\/page\/\d+\/)$#", $uri, $match)) {
            Url::$QSTR = $match[1];
            return $this->render('index');
        }

        // ------------------------------------
        //  Two Options, Folder with Template or Folder with Index
        //  - Folder with Template is PRIMARY
        // ------------------------------------

        $original_segments = $segments;
        $last              = array_pop($segments);

        $suffixes = array_map(function($val) {
            return str_replace('twig.', '', $val);
        }, app('cms.twig.suffixes'));

        if(stristr($last, '.')) {
            $x = explode('.', $last);
            // Only allow one period
            if (sizeof($x) == 2) {
                $suffix = array_pop($x);

                if(in_array($suffix, $suffixes)) {
                    $last = $x[0];
                }
            }
        }

        // ------------------------------------
        //  Template within Folder
        // ------------------------------------

        $check  =
            rtrim(empty($segments) ? '' : implode('/', $segments), '/').
            '/'.
            $last;

        if($this->exists($check)) {
            return $this->render($check);
        }

        // ------------------------------------
        //  Folder Request, so look for index
        // ------------------------------------

        $check =
            rtrim(implode('/', $original_segments), '/').
            '/'.
            'index';

        if($this->exists($check)) {
            return $this->render($check);
        }

        // ------------------------------------
        //  Single Segment? 404
        //  - Otherwise it will go to the site index
        // ------------------------------------

        if(sizeof($original_segments) == 1) {
            return $this->output404();
        }

        // ------------------------------------
        //  No Results? Dynamic URL?
        // ------------------------------------

        $result = $this->withDynamicUri($segments, $last);

        if (!empty($result)) {
            return $result;
        }

        return $this->output404();
    }

    // ---------------------------------------

    /**
     *  Find Template when we have Dynamic URI
     *
     * @param array $segments The segments (not including last) from our previous search
     * @param string $last The last segment from our previous search
     * @return string|bool
     */
    private function withDynamicUri($segments, $last, $last_uri = '')
    {
        $dynamic_uri = (empty($last_uri)) ? $last : $last.'/'.$last_uri;

        $last = array_pop($segments);

        $check =
            rtrim(empty($segments) ? '/' : implode('/', $segments), '/').
            '/'.
            $last;

        if($this->exists($check)) {
            Url::$QSTR = $dynamic_uri;
            return $this->render($check);
        }

        $check = $last.'/'.'index';

        if($this->exists($check)) {
            Url::$QSTR = $dynamic_uri;
            return $this->render($check);
        }

        if (empty($segments)) {
            return false;
        }

        // This gets a bit repetitive...
        return $this->withDynamicUri($segments, $last, $dynamic_uri);
    }

    // ------------------------------------
    //  404 page
    // ------------------------------------

    private function output404()
    {
        // if (config('app.debug') !== false) {
        //     throw new \Symfony\Component\HttpKernel\Exception\HttpException(404);
        // }

        return response()->view('_errors.404', [], 404);
    }

    // ----------------------------------------------------

    /**
     * Get Variables in Plugins
     *
     * @return array
     */
    public function getPluginVariables()
    {
        $variables = [];
        $plugins = Plugins::list();

        foreach($plugins as $plugin) {

            $plugin_variables = $this->getVariablesForPlugin($plugin);

            $variables = array_merge($variables, $plugin_variables);
        }

        return $variables;
    }

    // ----------------------------------------------------

    /**
     * Get Variables for a Plugin
     *
     * @param object
     * @return array
     */
    private function getVariablesForPlugin($plugin)
    {
        $variables_directory =
            $plugin->details->path.
            'Templates'.DIRECTORY_SEPARATOR.
            'Variables';

        if (!File::isDirectory($variables_directory)) {
            return [];
        }

        $namespace = $plugin->details->namespace.'Templates\\Variables\\';

        $variables = [];

        foreach(File::files($variables_directory) as $file_info) {
            if (($variable = $this->getVariableDetails($namespace, $file_info))) {
                $variables = array_merge($variables, $variable);
            }
        }

        return $variables;
    }

    // ----------------------------------------------------

    /**
     * Get Variable for a Plugin
     *
     * @param string
     * @return array|boolean
     */
    private function getVariableDetails($plugin_namespace, $file_info)
    {
        if (substr($file_info->getFilename(), -4) != '.php') {
            return false;
        }

        $class = substr($file_info->getFilename(), 0, -4);
        $full_class  = $plugin_namespace.$class;

        if (class_exists($full_class)) {
            $obj = app($full_class);
            $filter_name = $obj->name();
            return [$filter_name => $obj->run()];
        }

        return false;
    }
}
