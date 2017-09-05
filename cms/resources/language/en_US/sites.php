<?php

return [

'site_id' =>
"Site ID",

'site_handle' =>
"Site Handle",

'handle' =>
"Handle",

'domains' =>
"Domains",

'Domain' =>
"Domain",

"ID" =>
"ID",

"Public Path" =>
"Public Path",

"CMS Path" =>
"CMS Path",

'Site URL' =>
"Site URL",

'required' =>
'required',

'site_name' =>
"Site Name",

'create_new_site' =>
"Create New Site",

'edit_site' =>
"Edit Site",

'site_configuration' =>
"Configuration",

'site_preferences' =>
"Preferences",

'site_description' =>
"Site Description",

'site_administration_set' =>
"Administration Set",

'site_members_set' =>
"Members Set",

'site_templates_set' =>
"Templates Set",

'site_weblogs_set' =>
"Weblogs Set",

'no_site_handle' =>
"No Site Handle",

'no_site_name' =>
"No Site Name",

'site_handle_taken' =>
"Site Handle Taken",

'new_set_missing_name' =>
"Your are missing a name for one of your new Sets.",

'site_created' =>
"Site Created",

'site_updated' =>
"Site Updated",

'unable_to_locate_specialty' =>
"Unable to locate the specialty templates.  Make sure you have uploaded all language files.",

'delete_site' =>
"Delete Site",

'delete_site_confirmation' =>
"Are you sure you want to permanently delete this site?",

'site_deleted' =>
"Site Deleted",

'set_management' =>
"Set Management",

'new_set' =>
"New Set",

'edit_set' =>
"Edit Set",

'create_new_set' =>
"Create New Set",

'set_created' =>
"Set Created",

'set_updated' =>
"Set Updated",

'delete_set' =>
"Delete Set",

'delete_set_confirmation' =>
"Are you sure you want to permanently delete this set?",

'set_deleted' =>
"Set Deleted",

'site_set_id' =>
"Set ID",

'site_set_name' =>
"Set Name",

'site_set_type' =>
"Set Type",

'site_set_name_taken' =>
"Set Name Taken",

'move_data' =>
"Move Data",

'do_nothing' =>
"Do Nothing",

'move_options' =>
"Move Options",

'move_weblog_move_data' =>
"Move Weblog Data and Weblog Entries and Comments",

'duplicate_weblog_no_data' =>
"Duplicate Weblog, Do Not Duplicate Weblog Entries or Comments",

'duplicate_weblog_all_data' =>
"Duplicate Weblog, Duplicate Weblog Entries and Comments",

'move_all_templates' =>
"Move All Templates Over",

'copy_all_templates' =>
"Copy All Templates Over",

'move_template_variables' =>
"Move Template Variables",

'duplicate_template_variables' =>
"Duplicate Template Variables",

'move_upload_destination' =>
"Move Upload Destination",

'duplicate_upload_destination' =>
"Duplicate Upload Destination",

'choose_a_domain' =>
"Choose a Domain",

'choose_a_domain_details' =>
"Since a control panel can control multiple domains, you will need to choose the domain for the site you wish to load.",


'timeout_warning' =>
"Duplicating large amounts of data can be an intensive process, and may cause the action to exceed the server's limitations for script execution and memory, causing loss of data.<br /><br />Always
backup your database before performing a duplication, and if you experience problems, check with your host to increase the server allowances to perform this action.",

'site_details' =>
"Site Details",

'unable_to_load_site_no_domains' =>
"Unable to Load Site. Please insure it has domains specified.",

'domains_explanation_first' =>
"<b>Domains</b> are used to determine what site to load on a frontend request or CP request.",

'domains_explanation_frontend' => "<b>Frontend:</b> The URL of the page request will be compared against the domains for all sites and the best match will be used to load a site. The CMS Path and Public Path values will be usable in your path and url settings as variables {CMS_PATH} and {PUBLIC_PATH}. These fields are <i>optional</i> and only necessary if you change the name of your <em>./cms</em> or <em>./public/</em> directories.",

'domains_explanation_backend' => "<b>CP:</b> The Sites pulldown will choose the site and domain in the CMS that best matches the current server and request. You can assist this matching by filling out the CMS Path or simply leaving it blank and let the system auto-detect the correct path.",

];
