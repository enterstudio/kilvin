<?php

namespace Groot\Migrations;

use Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGrootTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Domains
        Schema::create('groot', function($table)
        {
            $table->increments('groot_id');
            $table->string('field_name', 100)->index();
            $table->string('field_value', 100);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $D[] = "groot";

        foreach($D as $deadTableWalking) {
            Schema::dropIfExists($deadTableWalking);
        }
    }
}
