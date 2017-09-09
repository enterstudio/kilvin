<?php

namespace Kilvin\Support\Plugins;

use Illuminate\Support\Str;
use Illuminate\Database\Migrations\Migrator;

class PluginMigrator extends Migrator
{
	private $plugin_details;

	/**
     * Resolve a migration instance from a file.
     *
     * @param  string  $file
     * @return object
     */
    public function resolve($file)
    {
    	$details = $this->pluginDetails();

        $class = $details['namespace'].'Migrations'.'\\'.
        	Str::studly(
        		implode('_', array_slice(explode('_', $file), 4)
        		)
        	);

        return new $class;
    }

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

    /**
     * Get the Plugin Details
     *
     * @return string
     */
    public function pluginDetails()
    {
        return $this->plugin_details;
    }
}
