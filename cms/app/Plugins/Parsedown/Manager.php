<?php

namespace Kilvin\Plugins\Parsedown;

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
    protected $documentation_url = 'http://parsedown.org';
    protected $has_cp = 'n';

    public function __construct()
    {
        $this->name = __('parsedown.name');
        $this->description = __('parsedown.description');
    }
}
