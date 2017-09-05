<?php

namespace Kilvin\Plugins\Stats;

use DB;
use Kilvin\Plugins\Base\Manager as BaseManager;

class Manager extends BaseManager
{
    protected $version	= '1.0.0';
    protected $name = 'Stats';
    protected $description = 'Record stats for the CMS';
    protected $developer = 'Paul Burdick';
    protected $developer_url = 'https://arliden.com';
    protected $documentation_url = 'https://arliden.com';
    protected $has_cp = 'n';
}
