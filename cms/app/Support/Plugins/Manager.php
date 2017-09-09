<?php

namespace Kilvin\Support\Plugins;

abstract class Manager implements ManagerInterface
{
    protected $version;
    protected $name;
    protected $description;
    protected $developer;
    protected $developer_url;
    protected $documentation_url;
    protected $has_cp;

    protected $icon_url; // Not implemented but a future thing for Plugins CP page

    // ------------------------------------

    /**
     * Name of Plugin
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    // ------------------------------------

    /**
     * Name of Developer
     *
     * @return string
     */
    public function description()
    {
        return $this->description;
    }

    // ------------------------------------

    /**
     * Current version of plugin files
     *
     * @return string
     */
    public function version()
    {
        return $this->version;
    }

    // ------------------------------------

    /**
     * Name of Developer
     *
     * @return string
     */
    public function developer()
    {
        return $this->developer;
    }

    // ------------------------------------

    /**
     * URL to website of developer
     *
     * @return string
     */
    public function developerUrl()
    {
        return $this->developer_url;
    }

    // ------------------------------------

    /**
     * URL for Plugin Documentation
     *
     * @return string
     */
    public function documentationUrl()
    {
        return $this->documentation_url;
    }

    // ------------------------------------

    /**
     * Has CP?
     *
     * @return boolean
     */
    public function hasCp()
    {
        return $this->has_cp;
    }

    // ------------------------------------

    /**
     * Install Plugin
     *
     * Method called after the Plugins CP adds the plugin to the DB
     * and runs any migrations.
     *
     * @return bool
     */
    public function install()
    {
        return true;
    }

    // ------------------------------------

    /**
     * Uninstall Plugin
     *
     * Method called after the Plugins CP removes the plugin to the DB
     * and resets any migrations.
     *
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    // ------------------------------------

    /**
     * Updates Plugin
     *
     * @param string $database_version Current version of plugin according to 'plugins' table
     * @return bool
     */
    public function updates($database_version)
    {
        $this->runMigrations();

        return true;
    }
}
