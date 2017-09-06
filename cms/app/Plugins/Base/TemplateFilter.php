<?php

namespace Kilvin\Plugins\Base;

abstract class TemplateFilter implements TemplateFilterInterface
{
    // --------------------------------------------------------------------

    /**
    * Run the Filter Request
    *
    * @param string
    * @return string
    */
    public function run($str)
    {
        return $str;
    }

    // --------------------------------------------------------------------

    /**
    * Options for Filter
    *
    * @return array
    */
    public function options()
    {
        return [];
    }
}
