<?php

return [

'total_entries' =>
"Total Entries",

// ---------------------------
//  Explanatory Blurbs
// ---------------------------

'system_admin_blurb' =>
"Most of the administrative aspects of Kilvin CMS are managed from one of the following five areas:",

'weblog_administration_blurb' =>
"Manage your weblogs, custom fields, categories, publish page preferences, etc.",

'members_and_groups_blurb' =>
"Manage members and member groups, including permissions.",

'site_preferences_blurb' =>
"Manage your site specific preferences. Everything from localization to security to debugging.",

'utilities_blurb' =>
"Utilities that help you manage Kilvin CMS' data.",

'image_preferences_blurb' =>
"File upload directories and image resizing preferences are managed here.",

'search' =>
"Search",

'search_preferences' =>
"Search Preferences",

'no_search_results' =>
"No Results Found",

'search_results' =>
"Search Results",


// ---------------------------
//  Extensions Stuff
// ---------------------------

"censor_replacement" =>
"Censoring Replacement Word",

"censor_replacement_info" =>
"If left blank censored words will be replaced with: #",

"censored_wildcards" =>
"Wild cards are allowed.  For example, the wildcard  test* would censor the words test, testing, tester, etc.",

'settings' =>
"Settings",

'documentation' =>
"Documentation",

'status' =>
"Status",

//----------------------------
// Admin Page
//----------------------------

"guest" =>
"Guest",

'wiki_search' =>
'Wiki',

"site_search" =>
"Site",

"searched_in" =>
"Searched In",

"search_terms" =>
"Search Terms",

"screen_name" =>
"Screen Name",

"throttling_cfg" =>
"Throttling Configuration",

"banish_masked_ips" =>
"Deny Access if No IP Address is Present",

"max_page_loads" =>
"Maximum Number of Page Loads",

"max_page_loads_exp" =>
"The total number of times a user is allowed to load any of your web pages (within the time interval below) before being locked out.",

"time_interval" =>
"Time Interval (in seconds)",

"time_interval_exp" =>
"The number of seconds during which the above number of page loads are allowed.",

"lockout_time" =>
"Lockout Time (in seconds)",

"lockout_time_exp" =>
"The length of time a user should be locked out of your site if they exceed the limits.",

"banishment_type" =>
"Action to Take",

"banishment_type_exp" =>
"The action that should take place if a user has exceeded the limits.",

"url_redirect" =>
"URL Redirect",

"404_page" =>
"Send 404 headers",

"show_message" =>
"Show custom message",

"banishment_url" =>
"URL for Redirect",

"banishment_url_exp" =>
"If you chose the URL Redirect option.",

"banishment_message" =>
"Custom Message",

"banishment_message_exp" =>
"If you chose the Custom Message option.",

"click" =>
"Click",

"hover" =>
"Hover",

"enable_throttling" =>
"Enable Throttling",

"enable_throttling_explanation" =>
"This feature generates a 404 header and message if a request to your site is made in which the template group does not exist in the URL. It is intended primarily to keep search engine crawlers from repeatedly requesting nonexistent pages.",

"standby_recount" =>
"Recounting... please stand by...",

"theme_folder_url" =>
"URL to your \"themes\" folder",

'image_preferences' =>
"Image Preferences",

"image_resizing" =>
"Image Resizing Preferences",

"debugging_preferences" =>
"Debugging",

"category_trigger_duplication" =>
"A template or template group with this name already exists.",

"invalid_path" =>
"The following path you submitted is not valid:",

"not_writable_path" =>
"The path you submitted is not writeable.  Please make sure the file permissions are set to 777.",

"notification_cfg" =>
"Notification Preferences",

"photo_cfg" =>
"Member Photo Preferences",

"enable_photos" =>
"Enable Member Photos",

"photo_url" =>
"URL to Photos Folder",

"photo_path" =>
"Server Path to Photo Folder",

"photo_max_width" =>
"Photo Maximum Width",

"photo_max_height" =>
"Photo Maximum Height",

"photo_max_kb" =>
"Photo Maximum Size (in Kilobytes)",

"must_be_path" =>
"Note: Must be a full server path, NOT a URL.  Folder permissions must be set to 777.",

"ignore_noncritical" =>
"Ignore non-essential data (recommended)",

"template_rev_msg" =>
"Note: Saving your revisions can use up a lot of database space so you are encouraged to set limits below.",

"max_tmpl_revisions" =>
"Maximum Number of Revisions to Keep",

"max_revisions_exp" =>
"The maximum number of revisions that should be kept for EACH template.  For example, if you set this to 5, only the most recent 5 revisions will be saved for any given template.",

"plugins" =>
"Plugins",

"none" =>
"None",

"auto_close" =>
"Auto",

"manual_close" =>
"Manual",

"new_posts_clear_caches" =>
"Clear all caches when new entries are posted?",

"weblog_preferences" =>
"Global Weblog Preferences",

"cp_preferences" =>
"Control Panel",

"debug_preferences" =>
"Debugging",

"word_separator" =>
"Word Separator for URL Titles",

"dash" =>
"Dash",

"underscore" =>
"Underscore",

"site_name" =>
"Name of your site",

"system_admin" =>
"System Administration",

"site_preferences" =>
"Site Preferences",

"is_system_on" =>
"Is system on?",

"is_system_on_explanation" =>
"CMS-wide setting! If system is off, all of your sites are turned off and only SuperAdmins can view your site(s).",

"system_off_msg" =>
"System Off Message",

"offline_template" =>
"System Offline Template",

"offline_template_desc" =>
"This template contains the page that is shown when your site is offline.",

"template_updated" =>
"Template Updated",

"preference_information" =>
"Preference Guide",

"preference" =>
"Preference",

"value" =>
"Value",

"general_preferences" =>
"General Preferences",

"member_preferences" =>
"Member Preferences",

"separate_emails" =>
"Separate multiple emails with a comma",

"default_member_group" =>
"Default Member Group Assigned to New Members",

"group_assignment_defaults_to_two" =>
"If you require account activation, members will be set to this once they are activated",

"view_email_logs" =>
"Email Console Logs",

"security_preferences" =>
"Security and Session",

"password_min_length" =>
"Minimum Password Length",

"image_path" =>
"Path to Images Directory",

"cp_url" =>
"URL to your Control Panel index page",

"with_trailing_slash" =>
"With trailing slash",

"site_index" =>
"Name of your site's index page",

"system_path" =>
"Absolute path to your %x folder",

"debug" =>
"Debug Preference",

"site_debug" =>
"CMS Debugging level",

"site_debug_explanation" =>
"Enables the display of error for THIS site ONLY. The Laravel debugging setting can override this and make errors display for ALL sites.",

"show_queries" =>
"Display SQL Queries?",

"show_queries_explanation" =>
"If enabled, Super Admins will see all SQL queries displayed at the bottom of the browser window.  Useful for debugging.",

"debug_zero" =>
"0: No PHP/SQL error messages generated",

"debug_one" =>
"1: PHP/SQL error messages shown only to Super Admins",

"debug_two" =>
"2: PHP/SQL error messages shown to anyone - NOT SECURE",

"default_language" =>
"Default Language",

"used_in_meta_tags" =>
"Used in control panel meta tags",

"charset" =>
"Default Character Set",

"localization_preferences" =>
"Localization",

"date_format" =>
"Default Date Format",

"time_format" =>
"Default Time Format",

"site_timezone" =>
"Site Timezone",

"cookie_preferences" =>
"Cookies",

"cookie_domain" =>
"Cookie Domain",

"cookie_domain_explanation" =>
"Use .yourdomain.com for site-wide cookies",

"cookie_path" =>
"Cookie Path",

"cookie_path_explain" =>
"Use only if you require a specific server path for cookies",

"enable_image_resizing" =>
"Enable Image Resizing",

"enable_image_resizing_exp" =>
"When enabled, you will be able to create thumbnails when you upload images for placement in your weblog entries.",

"image_resize_protocol" =>
"Image Resizing Protocol",

"image_resize_protocol_exp" =>
"Please check with your hosting provider to verify that your server supports the chosen protocol.",

"image_library_path" =>
"Image Converter Path",

"image_library_path_exp" =>
"If you chose either ImageMagick or NetPBM you must specify the server path to the program.",

"gd2" =>
"GD 2",

"imagemagick" =>
"ImageMagik",

"thumbnail_prefix" =>
"Image Thumbnail Suffix",

"thumbnail_prefix_exp" =>
"This suffix will be added to all auto-generated thumbnails.  Example: photo_thumb.jpg",

"email_preferences" =>
"Email Preferences",

"notification_sender_email" =>
"Notifications Email Sender",

'notification_sender_email_explanation' =>
"When notifications are sent automatically by the CMS, this will be the address in the From field.",

"cp_theme" =>
"Default Control Panel Theme",

"template_preferences" =>
"Template Preferences",

"save_tmpl_revisions" =>
"Save Template Revisions",

"censoring_preferences" =>
"Word Censoring",

"enable_censoring" =>
"Enable Word Censoring?",

"censored_words" =>
"Censored Words",

"censored_explanation" =>
"Place each word on a separate line.",

"weblog_administration" =>
"Weblog Administration",

"weblog_management" =>
"Weblogs",

"field_management" =>
"Weblog Fields",

"file_upload_prefs" =>
"File Upload Preferences",

"categories" =>
"Categories",

"status_management" =>
"Statuses",

"edit_preferences" =>
"Preferences",

"preferences_updated" =>
"Preferences Updated",

"edit_fields" =>
"Fields",

"edit_publish_layout" =>
"Layout",

"members_and_groups" =>
"Members and Groups",

"view_members" =>
"View Members",

"member_search" =>
"Member Search",

"user_banning" =>
"User Banning",

"member_profile_fields" =>
"Member Profile Fields",

"email_notification_template" =>
"Email Notification Templates",

"member_groups" =>
"Member Groups",

"utilities" =>
"Utilities",

"view_log_files" =>
"View Control Panel Log",

"clear_caching" =>
"Clear Cached Data",

"page_caching" =>
"Page (template) cache files",

"db_caching" =>
"Database cache files",

"all_caching" =>
"All caches",

"cache_deleted" =>
"Cache has been deleted",

'clear_cache_details' =>
"This will clear all of your cached data in the CMS. This will possibly make parts of your site load slower until the caches are rebuilt.",

"php_info" =>
"PHP Info",

"recount_statistics" =>
"Recount Statistics",

'stats_weblog_entries' =>
"Weblog Entries",

'stats_members' =>
"Members",

"preference_updated" =>
"Preference Updated",

"click_to_recount" =>
"Click to recount rows %x through %y",

"items_remaining" =>
"Records remaining:",

"recount_completed" =>
"Recount Completed",

"return_to_recount_overview" =>
"Return to Main Recount Page",

"recounting" =>
"Recounting",

"recount_info" =>
"The links below allow you to update various statistics, like how many entries each member has submitted.",

"source" =>
"Source",

"records" =>
"Database Records",

"total_records" =>
"Total Records:",

"recalculate" =>
"Recount Statistics",

"do_recount" =>
"Perform Recount",

"set_recount_prefs" =>
"Recount Preferences",

"recount_instructions" =>
"Total number of database rows processed per batch.",

"recount_instructions_cont" =>
"In order to prevent a server timeout, we recount the statistics in batches.  1000 is a safe number for most servers. If you run a high-performance or dedicated server you can increase the number.",

"exp_members" =>
"Members",

"weblog_entries" =>
"Weblog Entries",

"search_and_replace" =>
"Find and Replace",

"data_pruning" =>
"Data Pruning",

"title" =>
"Title",

"weblog_entry_title" =>
"Weblog Entry Titles",

"weblog_fields" =>
"Weblog Fields:",

"templates" =>
"Templates",

"site_statistics" =>
"Site Statistics",

"please_set_permissions" =>
"Please set the permissions to 666 or 777 on the following directory:",

"core_language_files" =>
"Core language files:",

"plugin_language_files" =>
"Plugin language files:",

"file_saved" =>
"The file has been saved",

'template_debugging' =>
"Display Template Debugging?",

"template_debugging_explanation" =>
"If enabled, Super Admins will see a list of details concerning the processing of the page.  Useful for debugging.",

"view_throttle_log" =>
"View Throttle Log",

"no_throttle_logs" =>
"No IPs are currently being throttled by the system.",

'throttling_disabled' =>
"Throttling Disabled. You can enable it %s.",

'hits' =>
"Hits",

'locked_out' =>
"Locked Out",

'last_activity' =>
"Last Activity",

"is_site_on" =>
"Is site on?",

"is_site_on_explanation" =>
"If site is off, only Super Admins will be able to see this site",

'theme_folder_path' =>
"Theme Folder Path",

'site_preferences' =>
"Site Preferences",

'sites_administration' =>
"Sites Administration",

'site_management' =>
"Site Management",

'yes' =>
"Yes",

'no' =>
"No",

"reserved_word" =>
"The field name you have chosen is a reserved word and can not be used.  Please see the user guide for more information.",

"list_edit_warning" =>
"If you have unsaved changes in this page they will be lost when you are transfered to the formatting editor.",

"fmt_has_changed" =>
"Note: You have selected a different field formatting choice than what was previously saved.",

"update_existing_fields" =>
"Update all existing weblog entries with your new formatting choice?",

"display_criteria" =>
"Select display criteria for PUBLISH page",

"limit" =>
"limit",

"orderby_title" =>
"Sort by Title",

"orderby_date" =>
"Sort by Date",

"sort_desc" =>
"Descending Order",

"in" =>
"in",

"sort_asc" =>
"Ascending Order",

"field_name_info" =>
"This is the name that will appear in the PUBLISH page",

"Date" =>
"Date",

"update_publish_cats" =>
"Close Window and Update Categories in PUBLISH Page",

"versioning" =>
"Versioning Preferences",

"enable_versioning" =>
"Enable Entry Versioning",

"clear_versioning_data" =>
"Delete all existing revision data in this weblog",

"enable_qucksave_versioning" =>
"Save Revisions During 'Quick Save'",

"quicksave_note" =>
"Quick Save allows you to save progress and continue editing. You may not want these saves to create revisions.",

"max_revisions" =>
"Maximum Number of Recent Revisions per Entry",

"max_revisions_note" =>
"Versioning can use up a lot of database space so it is recommended that you limit the number of revisions.",

"Populate dropdown manually" =>
"Populate dropdown manually",

"Populate dropdown from weblog field" =>
"Populate dropdown from weblog field",

"select_weblog_for_field" =>
"Select the weblog and field you wish to populate from:",

"field_val" =>
"You must choose a field name from this menu, not a weblog name.",

"weblog_notify" =>
"Enable recipient list below for weblog entry notification?",

"status_created" =>
"Status has been created",

"notification_settings" =>
"Notification Preferences",

"category_order_confirm_text" =>
"Are you sure you want to sort this category group alphabetically?",

"category_sort_warning" =>
"If you are using a custom sort order it will be replaced with an alphabetical one.",

"global_sort_order" =>
"Master Sort Order",

"custom" =>
"Custom",

"alpha" =>
"Alphabetical",

"weblog_id" =>
"Weblog ID",

"weblog_handle" =>
"Handle",

"group_required" =>
"You must submit a group name.",

"delete_category_confirmation" =>
"Are you sure you want to delete the following category?",

"category_description" =>
"Category Description",

"category_updated" =>
"Category Updated",

"new_category" =>
"Create a New Category",

"template_creation" =>
"Create New Templates For This Weblog?",

"use_a_theme" =>
"Use one of the default themes",

"duplicate_group" =>
"Duplicate an existing template group",

"template_group_name" =>
"New Template Group Name",

"new_group_instructions" =>
"Field is required if you are creating a new group",

"field_display_options" =>
"Field Display Options",

"show_url_title" =>
"Show URL Title Field",

"show_url_title_blurb" =>
"If not displayed, on submission a url title will automatically be created based off the Title field.",

"show_categories_tab" =>
"Show Categories Tab",

"paths" =>
"Path Settings",

"weblog_url_explanation" =>
"Used to create the permanent link for an entry for things like an Atom/RSS feed or search results",

"restrict_status_to_group" =>
"Restrict status to members of specific groups",

"no_publishing_groups" =>
"There are no Member Groups available that permit publishing",

"status_updated" =>
"Status has been updated",

"can_edit_status" =>
"Can access status",

"weblog_prefs" =>
"Weblog Preferences",

"weblog_settings" =>
"Weblog Posting Preferences",

"edit_weblog_prefs" =>
"Edit Weblog Preferences",

"edit_group_prefs" =>
"Edit Group Preferences",

"duplicate_weblog_prefs" =>
"Duplicate existing weblog's preferences",

"do_not_duplicate" =>
'Do Not Duplicate',

"no_weblogs_exist" =>
"There are currently no weblogs",

"create_new_weblog" =>
"Create a New Weblog",

"weblog_base_setup" =>
"Weblog Name",

"default_settings" =>
"Default Field Values",

"short_weblog_name" =>
"Short Name",

"weblog_url" =>
"Weblog Permament URL",

"blog_description" =>
"Weblog Description",

"illegal_characters" =>
"The name you submitted may only contain alpha-numeric characters, spaces, underscores, and dashes",

"convert_to_entities" =>
"Convert HTML into character entities",

"allow_safe_html" =>
"Allow only safe HTML",

"allow_all_html" =>
"Allow ALL HTML",

"allow_all_html_not_recommended" =>
"Allow all HTML (not recommended)",

"emails_of_notification_recipients" =>
"Email Address of Notification Recipient(s)",

"auto_link_urls" =>
"Automatically turn URLs and email addresses into links?",

"single_word_no_spaces_with_underscores" =>
"single word, no spaces, underscores allowed",

"full_weblog_name" =>
"Full Weblog Name",

"edit_weblog" =>
"Edit Weblog",

"weblog_name" =>
"Weblog Name",

"new_weblog" =>
"New Weblog",

"weblog_created" =>
"Weblog Created: ",

"weblog_updated" =>
"Weblog Updated: ",

"taken_weblog_name" =>
"This weblog name is already taken.",

"no_weblog_name" =>
"You must give your weblog a 'short' name.",

"no_weblog_title" =>
"You must give your weblog  a 'full' name.",

"invalid_short_name" =>
"Your weblog name must contain only alpha-numeric characters and no spaces.",

"delete_weblog" =>
"Delete Weblog",

"weblog_deleted" =>
"Weblog Deleted:",

"delete_weblog_confirmation" =>
"Are you sure you want to permanently delete this weblog?",

"be_careful" =>
"BE CAREFUL!",

"assign_group_to_weblog" =>
"Note: In order to use your new group, you must assign it to a weblog.",

"click_to_assign_group" =>
"Click here to assign it",

"default" =>
"Default",

"category" =>
"Category",

"default_status" =>
"Default Status",

"default_category" =>
"Default Category",

"no_field_group_selected" =>
"No field group available for this weblog",

"open" =>
"Open",

"closed" =>
"Closed",

"none" =>
"None",

"tag_name" =>
"Tag Name",

"tag_open" =>
"Opening Tag",

"tag_close" =>
"Closing Tag",

"accesskey" =>
"Shortcut",

"tag_order" =>
"Order",

"row" =>
"Row",

"server_name" =>
"Server Name",

"server_url" =>
"Server URL/Path",

"port" =>
"Port",

"protocol" =>
"Protocol",

"is_default" =>
"Default",

"server_order" =>
"Order",

"assign_weblogs" =>
"Choose which weblog(s) you want this group assigned to",

//----------------------------
// Category Administration
//----------------------------

"category_group" =>
"Category Group",

"category_groups" =>
"Category Groups",

"no_category_group_message" =>
"There are currently no categories",

"no_category_message" =>
"There are currently no categories assigned to this group",

"create_new_category_group" =>
"Create a New Category Group",

"edit_category_group" =>
"Edit Category Group",

"name_of_category_group" =>
"Name of category group",

"taken_category_group_name" =>
"This group name is already taken.",

"add_edit_categories" =>
"Add/Edit Categories",

"edit_group_name" =>
"Edit Group",

"delete_group" =>
"Delete Group",

"category_group_created" =>
"Category Group Created: ",

"category_group_updated" =>
"Group Updated: ",

"delete_category_group_confirmation" =>
"Are you sure you want to permanently delete this category group?",

"category_group_deleted" =>
"Category Group Deleted:",

"create_new_category" =>
"Create a New Category",

"add_new_category" =>
"Add a New Category",

"edit_category" =>
"Edit Category",

"delete_category" =>
"Delete Category",

'category_url_title' =>
'Category URL Title',

'category_url_title_is_numeric' =>
'Numbers cannot be used as Category URL Titles',

'unable_to_create_category_url_title' =>
'Unable to create valid Category URL Title for your Category',

'duplicate_category_url_title' =>
'A Category with the submitted Category URL Title already exists in this Category Group',

"category_name" =>
"Category Name",

"category_image" =>
"Category Image URL",

"category_img_blurb" =>
"This is an optional field that enables you to assign an image to your categories.",

"category_parent" =>
"Category Parent",

'can_edit_categories' =>
'Can Edit Categories',

'missing_required_fields' =>
'You Are Missing Required Field(s):',

//----------------------------
// Custom field Administration
//----------------------------

"field_group" =>
"Custom Field Group",

"field_groups" =>
"Field Groups",

"field_group_name" =>
"Field Group Name",

"custom_fields" =>
"Custom Fields",

"no_field_group_message" =>
"There are currently no custom weblog fields",

"create_new_field_group" =>
"Create a New Weblog Field Group",

"new_field_group" =>
"New Field Group",

"add_edit_fields" =>
"Add/Edit Fields",

"edit_field_group_name" =>
"Edit Field Group",

"delete_field_group" =>
"Delete Field Group",

"create_new_field" =>
"Create a New Field",

"edit_field" =>
"Edit Field",

"no_field_groups" =>
"There are no custom fields in this group",

"delete_field" =>
"Delete Field",

"field_deleted" =>
"Custom Field Deleted:",

"create_new_custom_field" =>
"Create a New Custom Field",

"field_name" =>
"Field Label",

"field_handle" =>
"Field Handle",

"field_handle_explanation" =>
"Single word, no spaces, underscores are allowed",

"field_type" =>
"Field Type",

"field_max_length" =>
"Maxlength",

"field_max_length_cont" =>
"If you are using a \"text\" field type",

"Number of Rows" =>
"Number of Rows",

"dropdown_sub" =>
"If you are using a \"drop-down\" field type",

"field_list_items" =>
"Select Options",

"field_list_items_cont" =>
"If you chose drop-down menu",

"field_list_instructions" =>
"Put each item on a single line. Use a colon to separate the form value from displayed value.",

"edit_list" =>
"Edit List",

"field_order" =>
"Field Display Order",

"is_field_searchable" =>
"Is field searchable?",

"is_field_required" =>
"Is this a required field?",

"Text Input" =>
"Text Input",

"textarea" =>
"Textarea",

"Textarea" =>
"Textarea",

"Dropdown" =>
"Dropdown",

'site_id_mismatch' =>
'You are not logged into the correct Site to perform this action',

"no_field_handel" =>
"You must submit a field handel",

"no_field_name" =>
"You must submit a field name",

"invalid_characters" =>
"The field name you submitted contains invalid characters",

"duplicate_field_handel" =>
"The field handle you chose is already taken",

"taken_field_group_name" =>
"The name you have chosen is already taken",

"field_group_created" =>
"Field Group Created: ",

"field_group_updated" =>
"Field Group Updated: ",

"field_group_deleted" =>
"Field Group Deleted: ",

"field_group" =>
"Field Group",

'Field Updated' =>
"Field Updated",

"delete_field_group_confirmation" =>
"Are you sure you want to permanently delete this custom field group?",

"delete_field_confirmation" =>
"Are you sure you want to permanently delete this custom field?",

"weblog_entries_will_be_deleted" =>
"All weblog entries contained in the above field(s) will be permanently deleted.",


//----------------------------
// Status Administration
//----------------------------

"status_group" =>
"Status Group",

"status_groups" =>
"Status Groups",

"no_status_group_message" =>
"There are currently no custom statuses",

"create_new_status_group" =>
"Create New Status Group",

"edit_status_group" =>
"Edit Status Group",

"name_of_status_group" =>
"Name of Status Group",

"taken_status_group_name" =>
"This status group name is already taken.",

"invalid_status_name" =>
"Status names can only have alpha-numeric characters, as well as spaces, underscores and hyphens.",

"duplicate_status_name" =>
"A status already exists with the same name.",

"status_group_created" =>
"Status Group Created: ",

"new_status" =>
"New Status",

"status_group_updated" =>
"Status Group Updated: ",

"add_edit_statuses" =>
"Add/Edit Statuses",

"edit_status_group_name" =>
"Edit Status Group",

"delete_status_group" =>
"Delete Status Group",

"delete_status_group_confirmation" =>
"Are you sure you want to permanently delete this status group?",

"status_group_deleted" =>
"Status Group Deleted:",

"create_new_status" =>
"Create a New Status",

"status_name" =>
"Status Name",

"status_order" =>
"Status Order",

"change_status_order" =>
"Change Status Order",

"highlight" =>
"Highlight Color (optional)",

"statuses" =>
"Statuses",

"edit_status" =>
"Edit Status",

"delete_status" =>
"Delete Status",

"delete_status_confirmation" =>
"Are you sure you want to delete the following status?",

"edit_file_upload_preferences" =>
"Edit File Upload Preferences",

"new_file_upload_preferences" =>
"New File Upload Preferences",

"file_upload_preferences" =>
"File Upload Preferences",

"no_upload_prefs" =>
"There are currently no file upload preferences",

"create_new_upload_pref" =>
"Create New Upload Destination",

"upload_pref_name" =>
"Descriptive name of upload directory",

"new_file_upload_preferences" =>
"New File Upload Destination",

"server_path" =>
"Server Path to Upload Directory",

"url_to_upload_dir" =>
"URL of Upload Directory",

"allowed_types" =>
"Allowed File Types",

"max_size" =>
"Maximum File Size (in bytes)",

"max_height" =>
"Maximum Image Height (in pixels)",

"max_width" =>
"Maximum Image Width",

"properties" =>
"Image Properties",

"pre_format" =>
"Image Pre Formatting",

"post_format" =>
"Image Post Formatting",

"no_upload_dir_name" =>
"You must submit a name for your upload directory",

"no_upload_dir_path" =>
"You must submit the path to your upload directory",

"no_upload_dir_url" =>
"You must submit the URL to your upload directory",

"duplicate_dir_name" =>
"The name of your directory is already taken",

"delete_upload_preference" =>
"Delete Upload Preference",

"delete_upload_pref_confirmation" =>
"Are you sure you want to permanently delete this preference?",

"upload_pref_deleted" =>
"Upload Preference Deleted:",

"current_upload_prefs" =>
"Current Preferences",

"restrict_to_group" =>
"Restrict file uploading to select member groups",

"restrict_notes_1" =>
"These radio buttons let you to specify which Member Groups are allowed to upload files.",

"restrict_notes_2" =>
"Super Admins can always upload files",

"restrict_notes_3" =>
"Note: File uploading is currently only allowed via the control panel",

"member_group" =>
"Member Group",

"can_upload_files" =>
"Can upload files",

"images_only" =>
"Images only",

"all_filetypes" =>
"All file types",

'file_properties' =>
"File Properties",

'file_pre_format' =>
"File Pre Formatting",

'file_post_format' =>
"File Post Formatting",

'url_title_prefix' =>
"URL Title Prefix",

'live_look_template' =>
'Live Look Template',

'no_live_look_template' =>
'- No Live Look Template -',

'invalid_url_title_prefix' =>
"Invalid URL Title Prefix",

'multiple_category_group_preferences' =>
"Multiple Category Group Preferences",

'integrate_category_groups' =>
"Integrate Category Groups",

'text_direction' =>
"Text Direction",

'ltr' =>
"Left to Right",

"rtl" =>
"Right to Left",

'field_instructions' =>
"Field Instructions",

'field_instructions_info' =>
"Instructions for authors on how or what to enter into this custom field when submitting an entry.",

'unable_to_change_to_date_field_type' =>
"Sorry, you are unable to change an existing field to the 'date' field type.",

'clear_logs' =>
"Clear Logs",

'register_member' =>
"Register Member",

'sort_order' =>
"Sort Order",

'fields' =>
"Fields",

'custom_field_group' =>
"Custom Fields Group",

"order" =>
"Order",

'24_hour_time' =>
"24 Hour Time (ex: 22:01)",

'12_hour_time' =>
"12 Hour Time (ex: 10:01 PM)",

'edit_weblog_layout' =>
"Edit Publish Form Layout",

'Add Tab' =>
"Add Tab",

'Remove' =>
"Remove",

'Move Up' =>
"Move Up",

'Move Down' =>
"Move Down",

'Field Type' =>
"Field Type",

'Add Field' =>
"Add Field",

'Choose Field' =>
"Choose Field",

'Please enter tab name' =>
"Please enter tab name:",

'Please enter new tab name' =>
"Please enter new tab name:",

'You have chosen an invalid or existing tab name' =>
"You have chosen an invalid or existing tab name.",

'Please choose a shorter tab name' =>
"Please choose a shorter tab name.",

'Do you wish to delete this tab' =>
"Do you wish to delete this tab?",

'Move Left' =>
"Move Left",

'Move Right' =>
"Move Right",

'Delete Tab' =>
"Delete Tab",

'Edit Tab' =>
"Edit Tab",

'Tabs cannot be named integers' =>
"Tabs cannot be named integers.",

'Layout Updated' =>
"Layout Updated!",

'Choose Field Type' =>
"Choose Field Type",

'Field Settings' =>
"Field Settings",

'Invalid Field Type' =>
"Invalid Field Type",

'Unable to convert current field data over to new field type' =>
"Unable to convert current field data over to new field type."

];
