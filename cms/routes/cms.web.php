<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Routing\ControllerDispatcher;

Route::any('/{any}', function ($any='') {
    if (REQUEST === 'SITE') {
    	if (defined('ACTION')) {
    		$this->current->action['uses'] = 'Kilvin\Http\Controllers\SiteController@action';
	    }

	    $this->current->action['uses'] = 'Kilvin\Http\Controllers\SiteController@all';
    }

    if (REQUEST === 'CP') {
    	$this->current->action['uses'] = 'Kilvin\Http\Controllers\Cp\Controller@all';
    }

    if (REQUEST === 'INSTALL') {
        $this->current->action['uses'] = 'Kilvin\Http\Controllers\InstallController@all';
    }

    return $this->current->run();
})->where('any', '.*');