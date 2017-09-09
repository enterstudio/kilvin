<?php

namespace Kilvin\Support\Plugins;

use Request;
use Kilvin\Exceptions\CmsCpPageNotFound;

abstract class ControlPanel implements ControlPanelInterface
{
    protected $plugin_details;

    // --------------------------------------------------------------------

    /**
    * The URL Base for this plugin
    *
    * @return string
    */
    public function urlBase()
    {
        return BASE.'?C=Plugins&plugin='.$this->plugin_details['plugin'];
    }

    // --------------------------------------------------------------------

    /**
     * Set the Plugin Details
     *
     * @param array $details
     * @return void
     */
    public function setPluginDetails($details)
    {
        $this->plugin_details = $details;
    }

    // --------------------------------------------------------------------

    /**
     * Get the Plugin Details
     *
     * @return string
     */
    public function pluginDetails()
    {
        return $this->plugin_details;
    }

    // --------------------------------------------------------------------

    /**
    * Run the CP Request Engine
    *
    * @return string
    */
    public function run()
    {
        if (Request::input('M')) {
            if (! method_exists($this, Request::input('M'))) {
                throw new CmsCpPageNotFound;
            }

            return $this->{Request::input('M')}();
        }

        return $this->homepage();
    }

}
