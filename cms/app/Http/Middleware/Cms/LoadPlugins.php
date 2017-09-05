<?php

namespace Kilvin\Http\Middleware\Cms;

use DB;
use Request;
use Closure;


class LoadPlugins
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Not yet done

        // ----------------------------------------------
        //  Done for Now
        // ----------------------------------------------

        return $next($request);
    }
}
