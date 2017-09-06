<?php

namespace Kilvin\Plugins\Base;

abstract class TemplateVariable implements TemplateVariableInterface
{
    // --------------------------------------------------------------------

    /**
    * Output the Variable
    *
    * @return string|object|array
    */
    public function run()
    {
        return null;
    }
}
