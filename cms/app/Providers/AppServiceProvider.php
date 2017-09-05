<?php

namespace Kilvin\Providers;

use App;
use Parsedown;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // --------------------------------------------------
        //  Locale for Carbon - Localizes strings
        //  - @todo: Use cookie value OR use config/app.php's locale value?
        // --------------------------------------------------

        App::setLocale('en_US');
        Carbon::setLocale('en');

        setlocale(
            LC_CTYPE,
            'C.UTF-8',     // libc >= 2.13
            'C.utf8',      // different spelling
            'en_US.UTF-8', // fallback to lowest common denominator
            'en_US.utf8'   // different spelling for fallback
        );

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('parsedown', function () {
            return Parsedown::instance();
        });
    }
}
