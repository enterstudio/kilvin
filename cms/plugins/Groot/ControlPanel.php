<?php

namespace Groot;

use Cp;
use DB;
use Request;
use Kilvin\Support\Plugins\ControlPanel as PluginControlPanel;

class ControlPanel extends PluginControlPanel
{

   	// --------------------------------------------------------------------

    /**
    * Build the Homepage CP Page
    *
    * @return string
    */
    public function homepage()
   	{
        Cp::$title = __('cp.homepage');
        Cp::$crumb = __('cp.homepage');

        $vars['list_url'] = $this->urlBase().'&M=list';
        $vars['header']   = Cp::header(__('cp.homepage'), []);

   		return view('homepage', $vars);
   	}
}
