<?php

namespace Kilvin\Libraries\Twig\Loaders;

use File;
use Plugins;
use Twig_SimpleFilter;

/**
 * Extension to expose defined filters to the Twig templates.
 *
 * See the `extensions.php` config file, specifically the `filters` key
 * to configure those that are loaded.
 */
class Filters extends Loader
{
    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getName()
    {
        return 'Cms_Twig_Extension_Loader_Filters';
    }

    // ----------------------------------------------------

    /**
     * Get Filters in Plugins
     *
     * @return array
     */
    public function getPluginFilters()
    {
        $filters = [];
        $plugins = Plugins::list();

        foreach($plugins as $plugin) {

            $plugin_filters = $this->getFiltersForPlugin($plugin);

            $filters = array_merge($filters, $plugin_filters);
        }

        return $filters;
    }

    // ----------------------------------------------------

    /**
     * Get Filters for a Plugin
     *
     * @param object
     * @return array
     */
    private function getFiltersForPlugin($plugin)
    {
        $filters_directory =
            $plugin->details->path.
            'Templates'.DIRECTORY_SEPARATOR.
            'Filters';

        if (!File::isDirectory($filters_directory)) {
            return [];
        }
        $namespace = $plugin->details->namespace.'Templates\\Filters\\';

        $filters = [];

        foreach(File::files($filters_directory) as $file_info) {
            if (($filter = $this->getFilterDetails($namespace, $file_info))) {
                $filters = array_merge($filters, $filter);
            }
        }

        return $filters;
    }

    // ----------------------------------------------------

    /**
     * Get Filters for a Plugin
     *
     * @param string
     * @return array|boolean
     */
    private function getFilterDetails($plugin_namespace, $file_info)
    {
        if (substr($file_info->getFilename(), -4) != '.php') {
            return false;
        }

        $class = substr($file_info->getFilename(), 0, -4);
        $full_class  = $plugin_namespace.$class;

        if (class_exists($full_class)) {
            $filter_name = app($full_class)->name();
            return [$filter_name => ['callback' => $full_class.'@run']];
        }

        return false;
    }

    // ----------------------------------------------------

    /**
     * {@inheritDoc}
     *
     * @return Twig_Filter[]
     */
    public function getFilters()
    {
        // Our config filters go last as they take priority.
        $load    = array_merge($this->getPluginFilters(), config('twig.filters', []));
        $filters = [];

        foreach ($load as $filter_name => $callable) {
            list($filter_name, $callable, $options) = $this->parseCallable($filter_name, $callable);

            $filter = new Twig_SimpleFilter(
                $filter_name,
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

            $filters[] = $filter;
        }

        return $filters;
    }
}
