<?php

namespace Groot\Templates\Filters;

use Kilvin\Plugins\Base\Filter;
use Illuminate\Http\Request;

/**
 * Example Filter for Groot Plugin
 *
 * A very simple Twig filter for you to peruse. Essentially you give
 * the name of the filter and then use the filter() method to do the filtering.
 * The integration of Filters into Kilvin allows dependency-injection for the constructor
 *
 *
 * @category   Plugin
 * @package    Groot
 * @author     Paul Burdick <paul@reedmaniac.com>
 */

class IAmGroot extends Filter
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
		return 'iamgroot';
	}

	// ----------------------------------------------------

	/**
     * Perform the Filtering
     *
     * @return string
     */
	public function run($str)
	{
		return preg_replace('/[\w\s]+([\.;\–\!]\s*)/', 'I am Groot$1', $str);
	}
}
