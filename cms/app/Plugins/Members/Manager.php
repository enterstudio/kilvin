<?php

namespace Kilvin\Plugins\Members;

use DB;
use Kilvin\Plugins\Base\Manager as BaseManager;

class Manager extends BaseManager
{
	protected $version	= '1.0.0';
    protected $name = 'Members';
    protected $description = 'Members module for site';
    protected $developer = 'Paul Burdick';
    protected $developer_url = 'https://arliden.com';
    protected $documentation_url = 'https://arliden.com';
    protected $has_cp = 'n';

}
