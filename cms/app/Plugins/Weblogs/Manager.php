<?php
namespace Kilvin\Plugins\Weblogs;

use Kilvin\Plugins\Base\Manager as BaseManager;

class Manager extends BaseManager
{
    protected $version	= '1.0.0';
    protected $name = 'Weblogs';
    protected $description = 'The primary engine for the CMS';
    protected $developer = 'Paul Burdick';
    protected $developer_url = 'https://arliden.com';
    protected $documentation_url = 'https://arliden.com';
    protected $has_cp = 'n';
}
