<?php

namespace Kilvin\Libraries\Twig\Templates;

use DB;
use Plugins;
use Carbon\Carbon;

/**
 * Site Data and Functionality
 */
class PluginVariable
{
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
     * Load a Plugin Element Type, which is a fancy Eloquent Model
     *
     * @return array
     */
    public function Element($element)
    {
        $class = $this->findElementClass($element);

        return new $class;
    }

    // ------------------------------------------------

    /**
     * Find the Element
     *
     * @param string $element
     * @param string|null $plugin_name
     * @return array
     */
    private function findElementClass($element, $plugin_name = null)
    {
        if(stristr($element, '.')) {
            $x = explode('.', $element);

            if (sizeof($x) > 2) {
                throw new \Twig_Error(sprintf('The %s element name is not allowed to have multiple periods.', $element));
            }

            $plugin_name = $x[0];
            $element     = $x[1];
        }

        if (!empty($plugin_name)) {
            $plugin = Plugins::list()->first(function($item, $key) use ($plugin_name) {
                return $plugin_name == $item->plugin_name;
            });

            if(empty($plugin)) {
                throw new \Twig_Error(sprintf('The %s Plugin does not exist or is not installed.', $plugin_name));
            }

            $class = '\Kilvin\Plugins\\'.$plugin->plugin_name.'\\Templates\\Elements\\'.$element;

            if ( ! class_exists($class)) {
                throw new \Twig_Error(sprintf('The %s Plugin does not have an Element named %s.', $plugin->plugin_name, $element));
            }

            return $class;
        }


        foreach(Plugins::list() as $plugin) {
            $class = '\Kilvin\Plugins\\'.$plugin->plugin_name.'\\Templates\\Elements\\'.$element;

            if (class_exists($class)) {
                return $class;
            }

            $class = '\\'.$plugin->plugin_name.'\\Templates\\Elements\\'.$element;

            if (class_exists($class)) {
                return $class;
            }
        }

        throw new \Twig_Error(sprintf('Unable to find an Element named %s.', $element));
    }

    // ------------------------------------------------

 	/**
     * Call the method, which is actually a plugin
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, array $arguments)
    {
    	$plugin = Plugins::list()->first(function($item, $key) use ($method) {
    		return $method == $item->plugin_name;
    	});

        if ($plugin !== null) {

        	// Load up the class
        	// Maybe have each Plugin have a methods() function to list all available methods?

        	return new class {
				function mostRecent()
				{
					return new class {
						function title()
						{
							return 'Welcome to Kilvin CMS!';
						}
					};
				}

                function recentEntries()
                {
                    return new class {
                        function limit() {
                            return [
                                [
                                    'title' => 'Another Entry',
                                    'slug'  => 'another_entry',
                                    'content' => PluginVariable::latin(),
                                    'entry_date' => Carbon::now()->subHours(rand(1,5))->subMinutes(rand(1,30))
                                ],
                                [
                                    'title' => 'Welcome to Kilvin CMS!',
                                    'slug'  => 'welcome_to_groot_cms',
                                    'content' => PluginVariable::latin(),
                                    'entry_date' => Carbon::now()->subDays(rand(1,5))->subHours(rand(1,5))->subMinutes(rand(1,30))
                                ]
                            ];
                        }
                    };
                }
			};
        }

        throw new \Twig_Error(sprintf('The %s Plugin does not exist or is not installed.', $method));
    }

    // ------------------------------------------------

    /**
     * Loads a plugin for usage
     *
     * @return array
     */
    private function loadPlugin($plugin_name)
    {
        $plugin = Plugins::list()->first(function($item, $key) use ($plugin_name) {
            return $plugin_name == $item->plugin_name;
        });

        $class = '\Kilvin\Plugins\\'.$plugin->plugin_name.'\\Tags';

        if ( ! class_exists($class)) {
            throw new \Twig_Error(sprintf('The %s Plugin does not exist or is not installed.', $plugin->plugin_name));
        }

        return new $class;
    }

     // ------------------------------------------------

    /**
     * Temporary Method for testing
     *
     * @return string
     */
    public static function latin()
    {
        return <<<EOT
Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam aliquet vitae dui at faucibus. Morbi faucibus mollis purus, vitae suscipit massa pharetra sit amet. Quisque lacinia sed nulla id efficitur. Mauris pharetra pharetra venenatis. Mauris aliquam nisl ac mi pellentesque, a auctor leo ultrices. Etiam vel metus ante. Vivamus lacinia, augue sed tincidunt ultrices, nisl nisi lobortis ex, sed pulvinar eros elit a tellus. Integer sagittis mi vitae sem iaculis, quis placerat felis sagittis. Vivamus pharetra odio sed felis laoreet, quis rhoncus sem venenatis. Sed id turpis feugiat, auctor tortor dignissim, ornare neque. Donec in diam sapien.

Aliquam mollis pretium ullamcorper. Mauris et finibus mi. Sed viverra eget orci non imperdiet. Duis nec pellentesque orci. Integer quis risus lectus. Proin non urna gravida odio ullamcorper bibendum. Aliquam aliquam tempus risus eu scelerisque. Vivamus aliquam tellus elit, vitae vulputate massa sollicitudin quis. Etiam sagittis molestie tristique. Fusce viverra enim quis eros pulvinar mattis. Cras eleifend nisl vitae ipsum laoreet, quis condimentum enim tincidunt. Ut pretium tincidunt libero sit amet ornare.

Morbi efficitur at augue sit amet vehicula. Ut semper cursus nibh, posuere aliquam metus faucibus eget. Praesent finibus augue in elit consequat accumsan. Donec bibendum arcu ut laoreet cursus. Nullam vitae fermentum sapien. Etiam dictum orci eu risus fringilla placerat. Nulla vitae vestibulum arcu. Praesent pulvinar nulla a dui venenatis, et semper eros semper. Vestibulum quis nibh vel mi mattis porttitor at ut dolor. Donec rutrum porta hendrerit. Praesent vehicula, nisl eget fringilla condimentum, justo orci aliquet urna, molestie suscipit ante nulla quis quam.
EOT;
    }
}
