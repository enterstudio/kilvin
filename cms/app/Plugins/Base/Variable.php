<?php

namespace Kilvin\Plugins\Base;

abstract class Variable implements VariableInterface
{
    // --------------------------------------------------------------------

    /**
    * Output the Variable
    *
    * @return string|object|array
    */
    public function variable()
    {
        return null;
    }
}
