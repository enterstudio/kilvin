<?php

namespace Kilvin\Libraries\Twig\Loaders;

use File;
use Plugins;
use Twig_Function;

/**
 * Extension to expose defined functions to the Twig templates.
 *
 * See the `extensions.php` config file, specifically the `functions` key
 * to configure those that are loaded.
 */
class Functions extends Loader
{
    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'Cms_Twig_Extension_Loader_Functions';
    }

    // ----------------------------------------------------

    /**
     * Get unctions in Plugins
     *
     * @return array
     */
    public function getPluginFunctions()
    {
        $functions = [];
        $plugins = Plugins::list();

        foreach($plugins as $plugin) {

            $plugin_functions = $this->getFunctionsForPlugin($plugin);

            $functions = array_merge($functions, $plugin_functions);
        }

        return $functions;
    }

    // ----------------------------------------------------

    /**
     * Get Functions for a Plugin
     *
     * @param object
     * @return array
     */
    private function getFunctionsForPlugin($plugin)
    {
        $functions_directory =
            $plugin->details->path.
            'Templates'.DIRECTORY_SEPARATOR.
            'Functions';

        if (!File::isDirectory($functions_directory)) {
            return [];
        }

        $namespace = $plugin->details->namespace.'Templates\\Functions\\';

        $functions = [];

        foreach(File::files($functions_directory) as $file_info) {
            if (($function = $this->getFunctionDetails($namespace, $file_info))) {
                $functions = array_merge($functions, $function);
            }
        }

        return $functions;
    }

    // ----------------------------------------------------

    /**
     * Get Functions for a Plugin
     *
     * @param string
     * @return array|boolean
     */
    private function getFunctionDetails($plugin_namespace, $file_info)
    {
        if (substr($file_info->getFilename(), -4) != '.php') {
            return false;
        }

        $class = substr($file_info->getFilename(), 0, -4);
        $full_class  = $plugin_namespace.$class;

        if (class_exists($full_class)) {
            $object = app($full_class);
            $name = $object->name();
            $options = $object->options();
            $options['callback'] = $full_class.'@run';

            return [$name => $options];
        }

        return false;
    }

    // ----------------------------------------------------

    /**
     * {@inheritDoc}
     */
    public function getFunctions()
    {
        $load      = array_merge($this->getPluginFunctions(), config('twig.functions', []));
        $functions = [];

        foreach ($load as $method => $callable) {
            list($method, $callable, $options) = $this->parseCallable($method, $callable);

            $function = new Twig_Function(
                $method,
                function () use ($callable) {

                    // Allows Dependency Injection via Laravel
                    if (is_array($callable) && isset($callable[0])) {
                        if (class_exists($callable[0])) {
                            $callable[0] = app($callable[0]);
                        }
                    }

                    return call_user_func_array($callable, func_get_args());
                },
                $options
            );

            $functions[] = $function;
        }

        return $functions;
    }
}
