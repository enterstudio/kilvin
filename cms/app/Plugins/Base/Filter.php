<?php

namespace Kilvin\Plugins\Base;

abstract class Filter implements FilterInterface
{
    // --------------------------------------------------------------------

    /**
    * Run the Filter Request
    *
    * @param string
    * @return string
    */
    public function filter($str)
    {
        return $str;
    }
}
