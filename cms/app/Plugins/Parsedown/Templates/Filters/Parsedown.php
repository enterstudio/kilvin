<?php

namespace Kilvin\Plugins\Parsedown\Templates\Filters;

use Kilvin\Plugins\Base\TemplateFilter;
use Illuminate\Http\Request;

/**
 * Parsedown filter using Parsedown
 *
 *
 * @category   Plugin
 * @package    Parsedown
 * @author     Paul Burdick <paul@reedmaniac.com>
 */

class Parsedown extends TemplateFilter
{
    private $request;

    // ----------------------------------------------------

    /**
     * Constructor
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    // ----------------------------------------------------

    /**
     * Name of the Filter
     *
     * @return string
     */
    public function name()
    {
        return 'parsedown';
    }

    // ----------------------------------------------------

    /**
     * Perform the Filtering
     *
     * @return string
     */
    public function run($str)
    {
        return parsedown($str);
    }
}
