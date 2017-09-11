<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCmsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Sites
        Schema::create('sites', function($table)
        {
            $table->increments('site_id');
            $table->string('site_name', 100);
            $table->string('site_handle', 50)->index();
            $table->text('site_description')->nullable();
        });

        // Site Preferences
        Schema::create('site_preferences', function($table)
        {
            $table->increments('site_preference_id');
            $table->integer('site_id')->unsigned();
            $table->string('handle', 30)->index();
            $table->string('value')->nullable();
        });

        // Domains
        Schema::create('domains', function($table)
        {
            $table->increments('domain_id');
            $table->integer('site_id')->unsigned();
            $table->string('domain', 100)->index();
            $table->string('site_url', 100);
            $table->string('cms_path', 150)->nullable();
            $table->string('public_path', 150)->nullable();
        });

        // Throttle
        Schema::create('throttle', function($table)
        {
            $table->increments('throttle_id');
            $table->string('ip_address', 50)->index();
            $table->timestamp('last_activity')->index();
            $table->unsignedInteger('hits')->default(0);
            $table->char('locked_out')->default('n');
        });


        // Cache
        // - file is default, database is an option, Redis would be Grrrrrreat!
        // - MySQL 5.7 will allow a full string index length but anything
        // under that version will have a problem, so we limit the key length
        // Honestly, 191 chars SHOULD be plenty.
        Schema::create('cache', function ($table) {
            $table->string('key', 191)->unique();
            $table->text('value');
            $table->integer('expiration');
        });

        // System and site stats
        Schema::create('stats', function($table)
        {
            $table->increments('stat_id');
            $table->unsignedInteger('weblog_id')->nullable()->default(null)->index();
            $table->unsignedInteger('site_id')->default(1)->index();
            $table->unsignedMediumInteger('total_members');
            $table->unsignedInteger('recent_member_id')->nullable();
            $table->string('recent_member', 50)->nullable();
            $table->unsignedMediumInteger('total_entries');
            $table->timestamp('last_entry_date')->nullable();
            $table->timestamp('last_cache_clear')->nullable();
        });

        // Plugins table
        Schema::create('plugins', function($table)
        {
            $table->increments('plugin_id');
            $table->string('plugin_name', 70)->index();
            $table->string('plugin_version', 15)->index();
            $table->char('has_cp', 1)->default('n');
        });

        // Plugin migrations table
        Schema::create('plugin_migrations', function($table)
        {
            $table->string('plugin', 70)->index();
            $table->string('migration')->index();
            $table->integer('batch');
        });

        // Reset password
        // If a user looses their password, this table holds the reset code.
        Schema::create('password_resets', function($table)
        {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Member table - Contains the member info
        Schema::create('members', function($table)
        {
            $table->increments('member_id');
            $table->unsignedSmallInteger('group_id')->nullable()->index();

            $table->string('email', 70)->unique();
            $table->rememberToken();
            $table->string('screen_name', 50);
            $table->string('password');
            $table->string('unique_id', 50);
            $table->string('authcode', 30)->nullable()->index();

            $table->string('url')->nullable();
            $table->string('location')->nullable();
            $table->string('occupation')->nullable();
            $table->string('interests')->nullable();
            $table->text('bio')->nullable();
            $table->string('ip_address')->index();

            $table->unsignedInteger('bday_d')->nullable();;
            $table->unsignedInteger('bday_m')->nullable();;
            $table->unsignedInteger('bday_y')->nullable();;

            $table->string('photo_filename')->nullable();
            $table->string('photo_width')->nullable();
            $table->string('photo_height')->nullable();

            $table->timestamp('join_date')->nullable();
            $table->timestamp('last_activity')->nullable();

            $table->unsignedInteger('total_entries')->default(0);
            $table->timestamp('last_entry_date')->nullable();
            $table->timestamp('last_email_date')->nullable();

            $table->char('in_authorlist', 1)->default('n');

            $table->char('accept_admin_email', 1)->default('y');
            $table->char('accept_user_email', 1)->default('y');
            $table->char('notify_by_default', 1)->default('y');
            $table->char('smart_notifications', 1)->default('y');

            $table->string('language')->default('en_US');
            $table->string('timezone')->nullable();
            $table->string('date_format', 10)->nullable();
            $table->string('time_format', 10)->nullable();

            $table->string('cp_theme')->default('default');

            $table->unsignedSmallInteger('template_size')->default(28);
            $table->text('notepad')->nullable();
            $table->unsignedSmallInteger('notepad_size')->default(18);

            $table->text('quick_links')->nullable();
            $table->text('quick_tabs')->nullable();

            $table->timestamps();
        });

        // CP homepage layout
        // Each member can have their own control panel layout.
        // We store their preferences here.
        Schema::create('homepage_widgets', function($table)
        {
            $table->increments('homepage_widget_id');
            $table->unsignedInteger('member_id')->index();
            $table->string('name', 100);
            $table->char('column', 1)->default('l');
            $table->unsignedSmallInteger('order')->default(1);
        });

        // Member Groups table
        Schema::create('member_groups', function($table)
        {
            $table->increments('group_id');
            $table->string('group_name', 100);
            $table->text('group_description');
        });

        // Member Group Preferencess table
        Schema::create('member_group_preferences', function($table)
        {
            $table->increments('group_preference_id');
            $table->unsignedInteger('group_id')->index();
            $table->string('handle', 30)->index();
            $table->string('value')->nullable();
        });

        // Member Custom Fields
        // Stores the defenition of each field
        Schema::create('member_fields', function($table)
        {
            $table->increments('member_field_id');
            $table->string('field_name', 32);
            $table->string('field_label', 50);
            $table->text('field_description');
            $table->string('field_type', 12)->default('text');
            $table->text('field_list_items');
            $table->unsignedSmallInteger('textarea_num_rows')->default(8);
            $table->unsignedSmallInteger('field_maxlength')->nullable();
            $table->string('field_width', 6); // Should this be an integer?
            $table->char('is_field_searchable')->default('y');
            $table->char('is_field_required', 1)->default('n');
            $table->char('is_field_public')->default('y');
            $table->unsignedSmallInteger('field_order')->default(1);
        });

        // Member Data - stores the actual data
        Schema::create('member_data', function($table)
        {
            $table->increments('member_data_id');
            $table->unsignedInteger('member_id')->index();
        });

        // Weblog Table
        Schema::create('weblogs', function($table)
        {
            $table->increments('weblog_id');

            $table->string('weblog_name', 40);
            $table->string('weblog_title', 100);
            $table->string('weblog_url', 100);
            $table->string('weblog_description')->default('');

            $table->string('cat_group')->nullable()->index();
            $table->string('default_category', 60)->nullable();
            $table->unsignedSmallInteger('status_group')->nullable()->index();
            $table->string('default_status', 100)->default('open');
            $table->unsignedSmallInteger('field_group')->nullable()->index();

            $table->unsignedMediumInteger('total_entries')->default(0);
            $table->timestamp('last_entry_date')->nullable();

            $table->unsignedSmallInteger('weblog_max_chars')->nullable();

            $table->char('weblog_notify', 1)->default('n');
            $table->string('weblog_notify_emails')->default('');

            $table->char('show_url_title', 1)->default('y');
            $table->char('show_categories_tab', 1)->default('y');

            $table->char('enable_versioning', 1)->default('n');
            $table->char('enable_qucksave_versioning', 1)->default('n');

            $table->unsignedSmallInteger('max_revisions')->default(20);

            $table->string('url_title_prefix', 80)->default('');

            $table->unsignedSmallInteger('live_look_template')->nullable();

        });

        // Weblog Titles
        // We store weblog titles separately from weblog data
        Schema::create('weblog_entries', function($table)
        {
            $table->increments('entry_id');
            $table->unsignedInteger('weblog_id')->index();
            $table->unsignedInteger('author_id')->index();
            $table->string('url_title')->index();
            $table->string('status')->default('closed')->index();
            $table->char('versioning_enabled', 1)->default('n');
            $table->char('sticky', 1)->default('n');

            $table->timestamp('entry_date')->nullable()->index();
            $table->timestamp('expiration_date')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('entry_versioning', function($table)
        {
            $table->increments('version_id');
            $table->unsignedInteger('entry_id')->index();
            $table->unsignedInteger('weblog_id')->index();
            $table->unsignedInteger('author_id')->index();
            $table->timestamp('version_date');
            $table->mediumText('version_data');
        });

        // Weblog Custom Field Groups
        Schema::create('field_groups', function($table)
        {
            $table->increments('group_id');
            $table->string('group_name', 50);
        });

        // Weblog Custom Field Definitions
        Schema::create('weblog_fields', function($table)
        {
            $table->increments('field_id');
            $table->unsignedInteger('group_id')->index();
            $table->string('field_name', 60);
            $table->string('field_handle', 40);
            $table->text('field_instructions');
            $table->string('field_type', 50)->index();
            $table->boolean('is_field_required')->default(false);
            $table->text('settings')->nullable(true);
        });

        // Field data for weblog entry
        Schema::create('weblog_entry_data', function($table)
        {
            $table->increments('entry_data_id');
            $table->unsignedInteger('entry_id')->index();
            $table->unsignedInteger('weblog_id')->index();
            $table->string('locale', 12)->index();
            $table->string('title')->index();
            $table->text('field_excerpt')->nullable();
            $table->text('field_body')->nullable();
            $table->text('field_extended')->nullable();
            $table->timestamps();
        });

        // Status Groups
        Schema::create('status_groups', function($table)
        {
            $table->increments('group_id');
            $table->string('group_name', 50);
        });

        // Status data
        Schema::create('statuses', function($table)
        {
            $table->increments('status_id');
            $table->unsignedInteger('group_id')->index();
            $table->string('status', 50)->index();
            $table->unsignedTinyInteger('status_order')->default(1);
        });

        // Status "no access"
        // Stores groups that cannot access certain statuses
        Schema::create('status_no_access', function($table)
        {
            $table->increments('status_no_access_id');
            $table->unsignedInteger('status_id')->index();
            $table->unsignedInteger('member_group')->index(); // Probably should be group_id
        });

        // Category Groups
        Schema::create('category_groups', function($table)
        {
            $table->increments('group_id');
            $table->string('group_name', 50)->index();
            $table->char('sort_order')->default('c');
        });

        // Category data
        Schema::create('categories', function($table)
        {
            $table->increments('category_id');
            $table->unsignedInteger('group_id')->index();
            $table->unsignedInteger('parent_id')->index();

            $table->string('category_name', 100)->index();
            $table->string('category_url_title', 100)->index();
            $table->text('category_description');
            $table->string('category_image', 120)->nullable();
            $table->unsignedTinyInteger('category_order')->index();
        });

        // Category posts
        // Stores weblog entry ID and category IDs for entries
        Schema::create('weblog_entry_categories', function($table)
        {
            $table->increments('weblog_entry_category_id');
            $table->unsignedInteger('entry_id')->index();
            $table->unsignedInteger('category_id')->index();
        });

        // Control Panel log
        // Separate from Laravel App Log.
        Schema::create('cp_log', function($table)
        {
            $table->increments('id');
            $table->unsignedInteger('site_id')->nullable();
            $table->unsignedInteger('member_id')->nullable()->index();
            $table->string('screen_name', 50);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('act_date');
            $table->string('action');
        });

        // Template data
        Schema::create('templates', function($table)
        {
            $table->increments('template_id');
            $table->unsignedInteger('site_id')->default(1)->index();
            $table->string('folder', 100)->index();
            $table->string('template_name', 50)->index();
            $table->string('template_type', 20)->default('html');
            $table->mediumText('template_data'); // With files being saved, use pretty much for searching
            $table->text('template_notes')->nullable();
            $table->unsignedInteger('last_author_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        // Global variables
        // These are user-definable variables
        Schema::create('template_variables', function($table)
        {
            $table->increments('variable_id');
            $table->unsignedInteger('site_id')->default(1)->index();
            $table->string('variable_name', 50)->index();
            $table->text('variable_data');
        });

        // Revision tracker
        // This is our versioning table, used to store each
        // change that is made to a template.
        Schema::create('revision_tracker', function($table)
        {
            $table->increments('tracker_id');
            $table->unsignedInteger('item_id')->index();
            $table->string('item_table', 50);
            $table->string('item_field', 50);
            $table->timestamp('item_date');
            $table->unsignedInteger('item_author_id');
            $table->mediumText('item_data');
        });

        // Upload preferences
        Schema::create('upload_prefs', function($table)
        {
            $table->increments('id');
            $table->string('name', 50);
            $table->string('server_path');
            $table->string('url');
            $table->char('allowed_types', 10)->default('img');

            $table->string('max_size', 16)->nullable();
            $table->string('max_height', 6)->nullable();
            $table->string('max_width', 6)->nullable();
            $table->string('properties')->default('');
            $table->string('pre_format')->default('');
            $table->string('post_format')->default('');
            $table->string('file_properties')->default('');
            $table->string('file_pre_format')->default('');
            $table->string('file_post_format')->default('');
        });

        // Upload "no access"
        // We store the member groups that can not access various upload destinations
        Schema::create('upload_no_access', function($table)
        {
            $table->increments('upload_no_access_id');
            $table->unsignedInteger('upload_id')->index();
            $table->string('upload_loc', 10);
            $table->unsignedInteger('member_group')->index();
        });

        Schema::create('weblog_layout_tabs', function($table)
        {
            $table->increments('weblog_layout_tab_id');
            $table->integer('weblog_id')->index();
            $table->string('tab_name', 20)->index();
            $table->integer('tab_order')->index()->default(1);
            $table->timestamps();
        });

        Schema::create('weblog_layout_fields', function($table)
        {
            $table->increments('weblog_layout_field_id');
            $table->integer('tab_id')->index()->unsigned();
            $table->string('field_name', 50)->index();
            $table->integer('field_order')->index()->default(1);
            $table->timestamps();

            $table->foreign('tab_id')->references('weblog_layout_tab_id')->on('weblog_layout_tabs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $D[] = "sites";
        $D[] = "site_preferences";
        $D[] = "domains";
        $D[] = 'throttle';
        $D[] = 'cache';
        $D[] = 'stats';
        $D[] = 'plugins';
        $D[] = 'plugin_migrations';
        $D[] = 'password_resets';
        $D[] = 'members';
        $D[] = 'homepage_widgets';
        $D[] = 'member_groups';
        $D[] = 'member_group_preferences';
        $D[] = 'member_fields';
        $D[] = 'member_data';
        $D[] = 'weblogs';
        $D[] = 'weblog_entries';
        $D[] = 'entry_versioning';
        $D[] = 'field_groups';
        $D[] = 'weblog_fields';
        $D[] = 'weblog_entry_data';
        $D[] = 'status_groups';
        $D[] = 'statuses';
        $D[] = 'status_no_access';
        $D[] = 'category_groups';
        $D[] = 'categories';
        $D[] = 'weblog_entry_categories';
        $D[] = 'cp_log';
        $D[] = 'templates';
        $D[] = 'template_no_access';
        $D[] = 'template_variables';
        $D[] = 'revision_tracker';
        $D[] = 'upload_prefs';
        $D[] = 'upload_no_access';
        $D[] = 'member_search';

        $D[] = 'weblog_layout_fields'; // Goes first because of foreign keys
        $D[] = 'weblog_layout_tabs';



        foreach($D as $deadTableWalking) {
            Schema::dropIfExists($deadTableWalking);
        }
    }
}
