<?php

namespace Groot;

use DB;
use Schema;
use Kilvin\Plugins\Base\Manager as BaseManager;

class Manager extends BaseManager
{
    protected $version	= '1.0.0';
    protected $name;
    protected $description;
    protected $developer = 'Paul Burdick';
    protected $developer_url = 'https://arliden.com';
    protected $documentation_url = 'https://arliden.com';
    protected $has_cp = 'y';

    public function __construct()
    {
        $this->name = __('groot.name');
        $this->description = __('groot.description');
    }
}
