<?php

namespace Kilvin\Plugins\Parsedown\Templates\Functions;

use Kilvin\Plugins\Base\Filter;
use Illuminate\Http\Request;

/**
 * Markdown function
 *
 * @category   Plugin
 * @package    Parsedown
 * @author     Paul Burdick <paul@reedmaniac.com>
 */

class Markdown extends Filter
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
		return 'markdown';
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
