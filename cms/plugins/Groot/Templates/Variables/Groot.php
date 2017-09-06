<?php

namespace Groot\Templates\Variables;

use Illuminate\Http\Request;
use Kilvin\Plugins\Base\TemplateVariable;

class Groot extends TemplateVariable
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
		return 'groot';
	}

	// ----------------------------------------------------

	/**
     * Output the Variable
     *
     * @return string|object|array
     */
	public function run()
	{
		// Object Example
		// $output = new \stdClass();
		// $output->speak = 'I am Groot';

		// Array
		return [
			'speak' => 'I am Groot'
		];
	}
}
