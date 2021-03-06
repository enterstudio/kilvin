<?php

namespace Kilvin\Libraries\Twig\Templates;

/**
 * Model Element for Templates
 */
trait Element
{
	// ALL SEARCH CRITERIA SHOULD BE SCOPES!!!

	// ----------------------------------------------------

	/**
	 * Required by the IteratorAggregate interface.
	 * Returns a Illuminate Collection which has the necessary array implementations
	 *
	 * @return \Illuminate\Support\Collection
	 */
	public function getIterator()
	{
		return $this->find();
	}

	// ----------------------------------------------------

	/**
	 * Returns all elements that match the criteria.
	 *
	 * @param array $attributes Any last-minute parameters that should be added.
	 * @return array The matched elements.
	 */
	public function find($attributes = null)
	{
		//$this->setAttributes($attributes);

		// This is the place where we would use something like a Type/Transformer on the results

		return $this->get();
	}


	/**
     * Create a new (modified) Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }
}
