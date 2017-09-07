<?php

namespace Groot\Templates\Elements;

use Kilvin\Libraries\Twig\Templates\Element as TemplateElement;
use Groot\Models\Groot as BaseModel;


class Groot extends BaseModel implements \IteratorAggregate
{
    use TemplateElement;
}
