<?php

namespace Kilvin\Plugins\Base;

interface FilterInterface
{
    // --------------------------------------------------------------------

    /**
    * Name of the filter - one word, lowercased
    *
    * @return string
    */
    public function name();

    // --------------------------------------------------------------------

    /**
    * Run the Filter Request
    *
    * @param string
    * @return string
    */
    public function filter($str);
}
