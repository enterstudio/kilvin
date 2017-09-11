<?php

namespace Kilvin\Cp;

use Cp;
use DB;
use Site;
use Stats;
use Cache;
use Cookie;
use Schema;
use Storage;
use Request;
use Validator;
use Carbon\Carbon;
use Kilvin\Core\Session;

class SitesAdministration
{
    // --------------------------------------------------------------------

    /**
    * Constructor
    *
    * @return  void
    */
    public function __construct()
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }
    }

    // --------------------------------------------------------------------

    /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        if (Request::filled('M')) {
            if (method_exists($this, Request::input('M'))) {
                return $this->{Request::input('M')}();
            }
        }

        return $this->listSites();
    }

    // --------------------------------------------------------------------

    /**
    * Sites Manager
    *
    * @param string $message
    * @return  void
    */
    public function listSites($message = '')
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Messaging for when Site Created or Updated
        // ------------------------------------

        if (isset($_GET['created_id']))
        {
            $query = DB::table('sites')
                ->where('site_id', $_GET['created_id'])
                ->select('site_name')
                ->first();

            $message = __('sites.site_created').':'.NBS.'<b>'.$query->site_name.'</b>';
        }
        elseif(isset($_GET['updated_id']))
        {
            $query = DB::table('sites')
                ->where('site_id', $_GET['updated_id'])
                ->select('site_name')
                ->first();

            $message = __('sites.site_updated').':'.NBS.'<b>'.$query->site_name.'</b>';
        }

        // ------------------------------------
        //  Basic Page Elements
        // ------------------------------------

        Cp::$title = __('admin.site_management');
        Cp::$crumb = __('admin.site_management');

        $right_links[] = [
            BASE.'?C=SitesAdministration'.
                AMP.'M=siteConfiguration',
            __('sites.create_new_site')
        ];

        $r = Cp::header(__('admin.site_management'), $right_links);

        // ------------------------------------
        //  Fetch and Display Sites
        // ------------------------------------

        $query = Site::sitesData();

        $domains = DB::table('domains')
            ->orderBy('domain')
            ->pluck('site_id', 'domain')
            ->all();

        if ($message != '') {
            $r .= Cp::quickDiv('success-message', $message);
        }

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $r .= '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '50px').__('sites.site_id').'</td>'.PHP_EOL.
              Cp::td('tableHeading').__('sites.site_name').'</td>'.PHP_EOL.
              Cp::td('tableHeading').__('sites.handle').'</td>'.PHP_EOL.
              Cp::td('tableHeading').__('sites.domains').'</td>'.PHP_EOL.
              Cp::td('tableHeading').'</td>'.PHP_EOL.
              Cp::td('tableHeading').'</td>'.PHP_EOL.
              Cp::td('tableHeading').'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach($query as $row)
        {
            $row = (array) $row;

            $site_domains = array_filter(
                $domains,
                function($v, $k) use ($row) { return ($row['site_id'] == $v); },
                ARRAY_FILTER_USE_BOTH
            );

            $config_url = BASE.'?C=SitesAdministration'.AMP.'M=editConfiguration'.AMP.'site_id='.$row['site_id'];
            $prefs_url  = BASE.'?C=Sites&site_id='.$row['site_id'].'&M=loadSite&location=preferences';
            $delete_url = BASE.'?C=SitesAdministration'.AMP.'M=deleteSiteConfirm'.AMP.'site_id='.$row['site_id'];

            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', Cp::quickSpan('default',      $row['site_id']));
            $r .= Cp::tableCell('', Cp::quickSpan('defaultBold',  $row['site_name']));
            $r .= Cp::tableCell('', Cp::quickSpan('default',      $row['site_handle']));
            $r .= Cp::tableCell('', Cp::quickSpan('default',      implode(', ', array_keys($site_domains))));
            $r .= Cp::tableCell('', Cp::anchor($config_url, __('sites.site_configuration')));
            $r .= Cp::tableCell('', Cp::anchor($prefs_url, __('sites.site_preferences')));

            // Cannot delete default site
            if ($row['site_id'] == 1) {
                $r .= Cp::tableCell('', '----');
            }

            if ($row['site_id'] != 1) {
                $r .= Cp::tableCell('', Cp::anchor($delete_url, __('cp.delete')));
            }

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$body = $r;
    }

    // --------------------------------------------------------------------

    /**
    * New/Edit Site Form
    *
    * @param integer $site_id The editSite method is simply this form with the site_id set.
    * @return string
    */
    public function siteConfiguration($site_id = '')
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }

        $values = [
            'site_id'            => '',
            'site_name'          => '',
            'site_handle'        => '',
            'site_description'   => ''
        ];

        $domains = [];

        if (!empty($site_id)) {
            $query = DB::table('sites')
                ->where('site_id', $site_id)
                ->first();

            if (empty($query)) {
                return false;
            }

            $values = array_merge($values, (array) $query);

            $domains = DB::table('domains')
                ->where('site_id', $site_id)
                ->get();
        }

        $r = Cp::formOpen(
            ['action' => 'C=SitesAdministration'.AMP.'M=updateConfiguration'],
            ['site_id' => $site_id]
        );

        $page_title = (!empty($site_id)) ? __('sites.edit_site') : __('sites.create_new_site');

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL
              .Cp::td('tableHeading', '', '2').__('sites.site_details').'</td>'.PHP_EOL
              .'</tr>'.PHP_EOL;

        // ------------------------------------
        //  Site Name
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::required().Cp::quickSpan('defaultBold', __('sites.site_name')), '40%').
              Cp::tableCell('', Cp::input_text('site_name', $values['site_name'], '20', '100', 'input', '260px'), '60%').
              '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Site Handle
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell(
                '',
                Cp::required().
                    Cp::quickSpan('defaultBold', __('sites.site_handle')).
                    Cp::quickDiv('', __('admin.single_word_no_spaces_with_underscores')),
                '40%'
              ).
              Cp::tableCell('', Cp::input_text('site_handle', $values['site_handle'], '20', '50', 'input', '260px'), '60%').
              '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Site Description
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', Cp::quickSpan('defaultBold', __('sites.site_description')), '40%', 'top');
        $r .= Cp::tableCell('', Cp::input_textarea('site_description', $values['site_description'], '6', 'textarea', '99%'), '60%', 'top');
        $r .= '</tr>'.PHP_EOL;
        $r .= '</table>'.PHP_EOL;
        $r .= Cp::quickDiv('littlePadding', Cp::required(1));

        // ------------------------------------
        // Domains!
        // ------------------------------------

        $r .= '<br><div class="tableHeading">Domains</div>';

        $r .= '<table class="tableBorder" cellpadding="0" cellspacing="0" style="width:100%">';

        $r .= '<tr>';
        $r .= '<th class="tableHeadingAlt" style="width: 5%;">'.__('sites.ID').'</th>';
        $r .= '<th class="tableHeadingAlt" style="width: 15%;">'.__('sites.Domain').' <small>('.__('sites.required').')</small></th>';
        $r .= '<th class="tableHeadingAlt" style="width: 20%;">'.__('sites.Site URL').' <small>('.__('sites.required').')</small></th>';
        $r .= '<th class="tableHeadingAlt" style="width: 30%;">'.__('sites.CMS Path').'</th>';
        $r .= '<th  class="tableHeadingAlt" style="width: 30%;">'.__('sites.Public Path').'</th>';
        $r .= '</tr>';

        $domain_placeholder      = 'example.com';
        $site_placeholder        = 'https://example.com';
        $cms_path_placeholder    = base_path().DIRECTORY_SEPARATOR;
        $public_path_placeholder = public_path().DIRECTORY_SEPARATOR;

        foreach($domains as $d) {
            $r .= '<tr>';

            $r .= '<td>'.$d->domain_id.'</td>';
            $r .= '<td><input type="text" style="width: 100%;" name="domains['.$d->domain_id.'][domain]" value="'.$d->domain.'"></td>';
            $r .= '<td><input type="text" style="width: 100%;" name="domains['.$d->domain_id.'][site_url]" value="'.$d->site_url.'"></td>';
            $r .= '<td><input type="text" style="width: 100%;" name="domains['.$d->domain_id.'][cms_path]" value="'.$d->cms_path.'"></td>';
            $r .= '<td><input type="text" style="width: 100%;" name="domains['.$d->domain_id.'][public_path]" value="'.$d->public_path.'"></td>';

            $r .= '</tr>';
        }

        $r .= '<tr>';
        $r .= '<td><em>New!</em></td>';
        $r .= '<td><input type="text" style="width: 100%;" name="domains[new][domain]" placeholder="'.$domain_placeholder.'" value=""></td>';
        $r .= '<td><input type="text" style="width: 100%;" name="domains[new][site_url]" placeholder="'.$site_placeholder.'" value=""></td>';
        $r .= '<td><input type="text" style="width: 100%;" name="domains[new][cms_path]" placeholder="'.$cms_path_placeholder.' "value=""></td>';
        $r .= '<td><input type="text" style="width: 100%;" name="domains[new][public_path]" placeholder="'.$public_path_placeholder.'" value=""></td>';
        $r .= '</tr>';

        $r .= '</table>';

        $r .= '<p>'.__('sites.domains_explanation_first').'</p>';
        $r .= '<p>'.__('sites.domains_explanation_frontend').'</p>';
        $r .= '<p>'.__('sites.domains_explanation_backend').'</p><br>';

        // ------------------------------------
        //  New Site?  Allow Moving/Copying of Existing Data
        // ------------------------------------

        if ($values['site_id'] == '')
        {
            $r .= Cp::table('tableBorder', '0', '', '100%');
            $r .=
                '<tr>'.PHP_EOL.
                    Cp::td('tableHeading', '', '2').__('sites.move_data').'</td>'.PHP_EOL.
                '</tr>'.PHP_EOL.
                '<tr>'.PHP_EOL.
                    Cp::td('', '', '2').BR.Cp::quickDiv('bigPad alert', __('sites.timeout_warning')).BR.'</td>'.PHP_EOL.
                '</tr>'.PHP_EOL.
                '<tr>'.PHP_EOL.
                    Cp::td('tableHeadingAlt', '', '1').__('publish.weblogs').'</td>'.PHP_EOL.
                    Cp::td('tableHeadingAlt', '', '1').__('sites.move_options').'</td>'.PHP_EOL.
                '</tr>'.PHP_EOL;

            // ------------------------------------
            //  Weblogs
            // ------------------------------------

            $query = DB::table('weblogs')
                ->orderBy('weblog_title')
                ->select('weblog_title', 'weblog_id')
                ->get();

            $i = 0;

            foreach($query as $row)
            {
                $row = (array) $row;

                $r .=  '<tr>'.PHP_EOL.
                    Cp::tableCell('', $row['weblog_title']).
                    Cp::tableCell(
                        '',
                        Cp::input_select_header('weblog_'.$row['weblog_id']).
                            Cp::input_select_option('nothing', __('sites.do_nothing')).
                            Cp::input_select_option('move', __('sites.move_weblog_move_data')).
                            Cp::input_select_option('duplicate', __('sites.duplicate_weblog_no_data')).
                            Cp::input_select_option('duplicate_all', __('sites.duplicate_weblog_all_data')).
                        Cp::input_select_footer()
                    ).
                    '</tr>'.PHP_EOL;
            }

            // ------------------------------------
            //  Upload Directories
            // ------------------------------------

            $r .=  '<tr>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('admin.file_upload_preferences').'</td>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('sites.move_options').'</td>'.PHP_EOL
                  .'</tr>'.PHP_EOL;

            $query = DB::table('upload_prefs')
                ->orderBy('upload_prefs.name')
                ->select('name', 'id')
                ->get();

            $i = 0;

            foreach($query as $row)
            {
                $row = (array) $row;

                $r .=  '<tr>'.PHP_EOL.
                    Cp::tableCell('', $row['name']).
                    Cp::tableCell(
                        '',
                        Cp::input_select_header('upload_'.$row['id']).
                            Cp::input_select_option('nothing', __('sites.do_nothing')).
                            Cp::input_select_option('move', __('sites.move_upload_destination')).
                            Cp::input_select_option('duplicate', __('sites.duplicate_upload_destination')).
                        Cp::input_select_footer()
                    ).
                    '</tr>'.PHP_EOL;
            }

            // ------------------------------------
            //  Move/Copy Templates
            // ------------------------------------

            $r .=  '<tr>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('cp.templates').'</td>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('sites.move_options').'</td>'.PHP_EOL
                  .'</tr>'.PHP_EOL;

            $sites = Site::sitesList();

            $i = 0;

            foreach($sites as $row)
            {
                $row = (array) $row;

                $r .=
                    '<tr>'.PHP_EOL.
                    Cp::tableCell('', $row['site_name']).
                    Cp::tableCell(
                        '',
                        Cp::input_select_header(
                            'templates_site_'.base64_encode($row['site_id'])
                        ).
                            Cp::input_select_option('nothing', __('sites.do_nothing')).
                            Cp::input_select_option('move', __('sites.move_all_templates')).
                            Cp::input_select_option('copy', __('sites.copy_all_templates')).
                        Cp::input_select_footer()).
                    '</tr>'.PHP_EOL;
            }

            // ------------------------------------
            //  Template Variables
            // ------------------------------------

            $r .=  '<tr>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('templates.template_variables').'</td>'.PHP_EOL
                  .     Cp::td('tableHeadingAlt', '', '1').__('sites.move_options').'</td>'.PHP_EOL
                  .'</tr>'.PHP_EOL;

            $i = 0;

            foreach(Site::sitesList() as $row)
            {
                $row = (array) $row;

                $r .=  '<tr>'.PHP_EOL.
                    Cp::tableCell('', $row['site_name'].NBS.'-'.NBS.__('sites.template_variables')).
                    Cp::tableCell(
                        '',
                        Cp::input_select_header('template_variables_'.$row['site_id']).
                            Cp::input_select_option('nothing', __('sites.do_nothing')).
                            Cp::input_select_option('move', __('sites.move_template_variables')).
                            Cp::input_select_option('duplicate', __('sites.duplicate_template_variables')).
                        Cp::input_select_footer()
                    ).
                    '</tr>'.PHP_EOL;
            }

            $r .= '</table>'.PHP_EOL.BR;
        }

        // ------------------------------------
        //  Submit + Form Close
        // ------------------------------------

        $r .= BR.Cp::quickDiv('', Cp::input_submit(__('cp.submit')));

        $r .= '</form>'.PHP_EOL;

        // ------------------------------------
        //  Output page details to Display class
        // ------------------------------------

        Cp::$title = (empty($site_id)) ? __('sites.create_new_site') : __('sites.edit_site');
        Cp::$crumb = (empty($site_id)) ? __('sites.create_new_site') : __('sites.edit_site');
        Cp::$body  = $r;
    }
    // --------------------------------------------------------------------

    /**
    * Displays Edit Site Form
    * - Simply calls siteConfiguration() with $site_id variable
    *
    * @return string
    */
    function editConfiguration()
    {
        if (Request::input('site_id') === null or ! is_numeric(Request::input('site_id'))) {
            return false;
        }

        return $this->siteConfiguration(Request::input('site_id'));
    }

    // --------------------------------------------------------------------

    /**
    * Update/Create Site Configuration
    * - Updating a site is mostly just a DB update and things like templates directory change
    * - New Site allows copy/moving of content + templates
    *
    * @return  void
    */
    public function updateConfiguration()
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }

        if (!request()->filled('site_handle')) {
            return $this->siteConfiguration();
        }

        // If the $site_id variable is present we are editing
        $edit = (request()->has('site_id') && request()->get('site_id', null));

        // Validate Site Exists
        if ($edit === true) {
            $existing = DB::table('sites')
                ->where('site_id', request()->get('site_id'))
                ->first();

            if(!$existing) {
                return $this->siteConfiguration();
            }
        }

        // ------------------------------------
        //  Validation - Details
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'site_handle' => 'required|regex:/^[\pL\pM\pN_]+$/u',
            'site_name'   => 'required',
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        // Short Name Taken Already?
        $query = DB::table('sites')
            ->where('site_handle', Request::input('site_handle'));

        if ($edit === true) {
            $query->where('site_id', '!=', Request::input('site_id'));
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('sites.site_handle_taken'));
        }

        // ------------------------------------
        //  Validation - Domains
        // ------------------------------------

        if (Request::filled('domains') && is_array(Request::input('domains'))) {
            $validator = Validator::make(Request::all(), [
                'domains.*.domain'      => '',
                'domains.*.site_url'    => 'required_with:domains.*.domain|url',
                'domains.*.cms_path'    => 'required_with:domains.*.public_path',
                'domains.*.public_path' => 'required_with:domains.*.cms_path',
            ]);

            if ($validator->fails()) {
                return Cp::errorMessage(implode(BR, $validator->errors()->all()));
            }
        }

        // ------------------------------------
        //  Create/Update Site
        // ------------------------------------

        $data = [
            'site_handle'        => Request::input('site_handle'),
            'site_name'          => Request::input('site_name'),
            'site_description'   => Request::input('site_description')
        ];

        if ($edit == false) {
            $insert_id = $site_id = DB::table('sites')->insertGetId($data);

            $success_msg = __('sites.site_created');
        }


        if ($edit == true) {
            DB::table('sites')
                ->where('site_id', Request::input('site_id'))
                ->update($data);

            $site_id = Request::input('site_id');

            $success_msg = __('sites.site_updated');
        }

        // ------------------------------------
        //  Create/Update Domains
        // ------------------------------------

        if (Request::filled('domains') && is_array(Request::input('domains'))) {
            foreach(Request::input('domains') as $key => $values) {

                if (!empty($values['cms_path'])) {
                    $values['cms_path'] = rtrim($values['cms_path'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                }

                if (!empty($values['public_path'])) {
                    $values['public_path'] = rtrim($values['public_path'], DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                }


                if ($key != 'new' && is_numeric($key)) {
                    if (empty($values['domain']) && empty($values['site_url'])) {
                        DB::table('domains')
                            ->where('domain_id', $key)
                            ->delete();
                    } else {
                        DB::table('domains')
                            ->where('domain_id', $key)
                            ->update($values);
                    }
                }

                if ($key == 'new' && !empty($values['domain'])) {
                    $values['site_id'] = $site_id;

                    DB::table('domains')
                        ->where('domain_id', $key)
                        ->insert($values);
                }
            }
        }

        // ------------------------------------
        //  Log Update
        // ------------------------------------

        Cp::log($success_msg.'&nbsp;'.$data['site_name']);

        // ------------------------------------
        //  Rename or Create Site Templates Folder for Site
        // ------------------------------------

        if ($edit === true && $data['site_handle'] != $existing->site_handle) {
            Storage::disk('templates')->rename($existing->site_handle, $data['site_handle']);
        }

        if ($edit === false) {
            Storage::disk('templates')->makeDirectory($data['site_handle']);
        }

        // ------------------------------------
        //  Site Specific Stats Created
        // ------------------------------------

        if ($edit === false) {
            $query = DB::table('stats')->where('site_id', 1)->get();

            foreach ($query as $row) {
                $data = (array) $row;

                $data['stat_id'] = null;
                $data['site_id'] = $site_id;
                $data['last_entry_date'] = null;
                $data['last_cache_clear'] = null;

                DB::table('stats')->insert($data);
            }
        }

        // ------------------------------------
        //  Prefs Creation
        // ------------------------------------

        if ($edit === false) {

            $update_prefs = [];

            foreach(Site::preferenceKeys() as $value) {
                $update_prefs[$value] = Site::config($value);
            }

            if (!empty($update_prefs)) {
                foreach($update_prefs as $handle => $value) {
                    DB::table('site_preferences')
                        ->insert(
                            [
                                'site_id' => $site_id,
                                'handle'  => $handle,
                                'value'   => $value
                            ]);
                }
            }
        }

        // ------------------------------------
        //  Sites DB table updated, so clear cache!
        // ------------------------------------

        Site::flushSiteCache();

        // ------------------------------------
        //  Moving of Data?
        // ------------------------------------

        if ($edit === false) {
            $this->movingSiteData($site_id, request()->all());
        }

        // ------------------------------------
        //  Refreshes Site Specific Preference for User
        // ------------------------------------

        Session::fetchMemberData();

        // ------------------------------------
        //  Update site stats
        // ------------------------------------

        $original_site_id = Site::config('site_id');
        Site::setConfig('site_id', $site_id);

        Stats::update_member_stats();
        Stats::update_weblog_stats();

        Site::setConfig('site_id', $original_site_id);

        // ------------------------------------
        //  View Sites List
        // ------------------------------------

        if ($edit === true) {
            return redirect(BASE.'?C=SitesAdministration&updated_id='.$site_id);
        } else {
            return redirect(BASE.'?C=SitesAdministration&created_id='.$site_id);
        }
    }

    // --------------------------------------------------------------------

    /**
    * Delete Site Confirmation Form
    *
    * @return  void
    */
    public function deleteSiteConfirm()
    {
        if ( ! $site_id = Request::input('site_id')) {
            return false;
        }

        if ( ! Session::access('can_admin_sites') OR $site_id == 1) {
            return Cp::unauthorizedAccess();
        }

        $site_name = DB::table('sites')
            ->where('site_id', $site_id)
            ->value('site_name');

        if (empty($site_name)) {
            return $this->sitesList();
        }

        Cp::$title = __('sites.delete_site');
        Cp::$crumb = Cp::breadcrumbItem(__('sites.delete_site'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'C=SitesAdministration'.AMP.'M=deleteSite'.AMP.'site_id='.$site_id,
                'heading'   => 'delete_site',
                'message'   => 'delete_site_confirmation',
                'item'      => $site_name,
                'extra'     => '',
                'hidden'    => ['site_id' => $site_id]
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete the Site!  KABLOOEY!!
    *
    * @return  void
    */
    public function deleteSite()
    {
        if ( ! Session::access('can_admin_sites')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $site_id = Request::input('site_id') or ! is_numeric($site_id)) {
            return false;
        }

        if ($site_id == 1) {
            return Cp::unauthorizedAccess();
        }

        $site_name = DB::table('sites')
            ->where('site_id', $site_id)
            ->value('site_name');

        if (empty($site_name)) {
            return $this->sitesList();
        }

        // ------------------------------------
        //  Delete Category Posts for Entries
        // ------------------------------------

        $entry_ids = DB::table('weblog_entries')
            ->where('site_id', $site_id)
            ->pluck('entry_id')
            ->all();

        if (count($entry_ids) > 0) {
            DB::table('weblog_entry_categories')
                ->whereIn('entry_id', $entry_ids)
                ->delete();
        }

        // ------------------------------------
        //  Delete Weblog Custom Field Columns for Site
        // ------------------------------------

        $fields = DB::table('weblog_fields')
            ->where('site_id', $site_id)
            ->pluck('field_handle')
            ->all();

        foreach($fields as $field_handle) {
            Schema::table('weblog_entry_data', function($table) use ($field_handle)
            {
                $table->dropColumn('field_'.$field_handle);
            });
        }
        // ------------------------------------
        //  Delete Upload Permissions for Site
        // ------------------------------------

        $upload_ids = DB::table('upload_prefs')
            ->where('site_id', $site_id)
            ->pluck('id')
            ->all();

        if (!empty($upload_ids)) {
            DB::table('upload_no_access')
                ->whereIn('upload_id', $upload_ids);
        }

        // ------------------------------------
        //  Delete Every DB Row Having to Do with the Site
        // ------------------------------------

        $tables = [
            'categories',
            'category_groups',
            'cp_log',
            'field_groups',
            'template_variables',
            'member_groups',
            'member_search',
            'online_users',
            'ping_servers',
            'referrers',
            'search',
            'sessions',
            'stats',
            'statuses',
            'status_groups',
            'templates',
            'upload_prefs',
            'weblogs',
            'weblog_entry_data',
            'weblog_fields',
            'weblog_entries',
            'domains',
            'sites',
        ];

        foreach($tables as $table)
        {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('site_id', $site_id)->delete();
            }
        }

        // ------------------------------------
        //  Log it!
        // ------------------------------------

        Cp::log(__('sites.site_deleted').': '.$site_name);

        // ------------------------------------
        //  Refreshes Site Specific Member Group Preferences for us
        // ------------------------------------

        Session::loadAssignedSites();

        // ------------------------------------
        //  Reload to Site Admin
        // ------------------------------------

        return redirect(BASE.'?C=SitesAdministration');
    }

    // --------------------------------------------------------------------

    /**
    * Create, copy, or move other sites' data
    *
    * @param integer $site_id The new site's site_id
    * @param array $input
    * @return  void
    */
    public function movingSiteData($site_id, $input)
    {

        $weblog_ids         = [];
        $moved              = [];
        $entries            = [];

        foreach($input as $key => $value)
        {
            // ------------------------------------
            //  Weblogs Moving
            // ------------------------------------

            if (substr($key, 0, strlen('weblog_')) == 'weblog_' && $value != 'nothing' && is_numeric(substr($key, strlen('weblog_'))))
            {
                $old_weblog_id = substr($key, strlen('weblog_'));

                // SO SIMPLE!
                if ($value == 'move')
                {
                    $moved[$old_weblog_id] = '';

                    DB::table('weblogs')
                        ->where('weblog_id', $old_weblog_id)
                        ->update(['site_id' => $site_id]);

                    DB::table('weblog_entries')
                        ->where('weblog_id', $old_weblog_id)
                        ->update(['site_id' => $site_id]);

                    DB::table('weblog_entry_data')
                        ->where('weblog_id', $old_weblog_id)
                        ->update(['site_id' => $site_id]);

                    $weblog_ids[$old_weblog_id] = $old_weblog_id; // Stats, Groups, For Later
                }



                if($value == 'duplicate' OR $value == 'duplicate_all')
                {
                    $query = DB::table('weblogs')
                        ->where('weblog_id', $old_weblog_id)
                        ->first();

                    if (!$query) {
                        continue;
                    }

                    $query = (array) $query;

                    // Uniqueness checks
                    foreach(['weblog_name', 'weblog_title'] AS $check)
                    {
                        $count = DB::table('weblogs')
                            ->where('site_id', $site_id)
                            ->where($check, 'LIKE', $query[$check].'%')
                            ->count();

                        if ($count > 0) {
                            $query[$check] = $query[$check].'-'.($count + 1);
                        }
                    }

                    $query['site_id']   = $site_id;
                    $query['weblog_id'] = null;

                    // No entries copied over, so set to 0
                    if ($value == 'duplicate') {
                        $query['total_entries']       = 0;
                        $query['last_entry_date']     = null;
                    }

                    $new_weblog_id = DB::table('weblogs')->insertGetId($query);
                    $weblog_ids[$old_weblog_id] = $new_weblog_id;

                    // ------------------------------------
                    // Duplicating Entries Too
                    //  - Duplicates Entries + Data + Comments
                    //  - Pages are NOT duplicated
                    // ------------------------------------

                    if ($value == 'duplicate_all')
                    {
                        $moved[$old_weblog_id] = '';

                        // ------------------------------------
                        //  Entries
                        // ------------------------------------

                        $query = DB::table('weblog_entries')
                            ->where('weblog_id', $old_weblog_id)
                            ->get()
                            ->toArray();

                        $entries[$old_weblog_id] = [];

                        foreach($query as $row)
                        {
                            $old_entry_id       = $row['entry_id'];
                            unset($row['entry_id']); // Null so new entry_id on INSERT

                            $row['site_id']     = $site_id;
                            $row['weblog_id']   = $weblog_ids[$old_weblog_id];

                            $new_entry_id = DB::table('weblog_entries')->insertGetId($row);

                            $entries[$old_weblog_id][$old_entry_id] = $new_entry_id;
                        }

                        // ------------------------------------
                        //  Entry Data
                        // ------------------------------------

                        $query = DB::table('weblog_entry_data')
                            ->where('weblog_id', $old_weblog_id)
                            ->get()
                            ->toArray();

                        foreach($query as $row)
                        {
                            $row['site_id']     = $site_id;
                            $row['entry_id']    = $entries[$old_weblog_id][$row['entry_id']];
                            $row['weblog_id']   = $weblog_ids[$old_weblog_id];

                            DB::table('weblog_entry_data')->insert($row);
                        }

                        // ------------------------------------
                        //  Category Posts
                        // ------------------------------------

                        $query = DB::table('weblog_entry_categories')
                            ->whereIn('entry_id', array_flip($entries[$old_weblog_id]))
                            ->get()
                            ->toArray();

                        foreach($query as $row) {
                            $row['entry_id'] = $entries[$old_weblog_id][$row['entry_id']];

                            DB::table('weblog_entry_categories')->insert($row);
                        }
                    }
                }
            }

            // ------------------------------------
            //  Upload Directory Moving
            // ------------------------------------

            if (substr($key, 0, strlen('upload_')) == 'upload_' && $value != 'nothing' && is_numeric(substr($key, strlen('upload_'))))
            {
                $upload_id = substr($key, strlen('upload_'));

                if ($value == 'move')
                {
                    DB::table('upload_prefs')
                        ->where('id', $upload_id)
                        ->update(['site_id' => $site_id]);
                }
                else
                {
                    $query = (array) DB::table('upload_prefs')
                        ->where('id', $upload_id)
                        ->first();

                    if (empty($query)) {
                        continue;
                    }

                    // Uniqueness checks
                    foreach(['name'] AS $check) {
                        $count = DB::table('upload_prefs')
                            ->where('site_id', $site_id)
                            ->where($check, 'LIKE', $query[$check].'%')
                            ->count();

                        if ($count > 0) {
                            $count++;
                            $query[$check] = $query[$check].'-'.$count;
                        }
                    }

                    $query['site_id'] = $site_id;
                    $query['id'] = null;

                    $new_upload_id = DB::table('upload_prefs')->insertGetId($query);

                    $disallowed_query = DB::table('upload_no_access')
                        ->where('upload_id', $upload_id)
                        ->get()
                        ->toArray();

                    foreach($disallowed_query as $row) {
                        DB::table('upload_no_access')
                            ->insert(
                                [
                                    'upload_id'    => $new_upload_id,
                                    'upload_loc'   => $row['upload_loc'],
                                    'member_group' => $row['member_group']
                                ]);
                    }
                }
            }

            // ------------------------------------
            //  Global Template Variables
            // ------------------------------------

            if (substr($key, 0, strlen('template_variables_')) == 'template_variables_' &&
                $value != 'nothing' &&
                is_numeric(substr($key, strlen('template_variables_'))))
            {
                $move_site_id = substr($key, strlen('template_variables_'));

                if ($value == 'move')
                {
                    DB::table('template_variables')
                        ->where('site_id', $move_site_id)
                        ->update(['site_id' => $site_id]);
                }
                else
                {
                    $query = DB::table('template_variables')
                        ->where('site_id', $move_site_id)
                        ->get()
                        ->toArray();

                    if (empty($query)) {
                        continue;
                    }

                    foreach($query as $row) {
                        // Uniqueness checks
                        foreach(['variable_name'] AS $check)
                        {
                            $count = DB::table('template_variables')
                                ->where('site_id', $site_id)
                                ->where($check, 'LIKE', $row[$check].'%')
                                ->count();

                            if ($count > 0)
                            {
                                $count++;
                                $row[$check] = $row[$check].'-'.$count;
                            }
                        }

                        $row['site_id']     = $site_id;
                        unset($row['variable_id']);

                        DB::table('template_variables')->insert($row);
                    }
                }
            }

            // ------------------------------------
            //  Template Moving
            // ------------------------------------

            // @todo - Need to move the files over instead
            // Will either need to do uniqueness checks OR only allow one site's templates to be moved over
            // that would require changing the form a bit.  Yeah, let's do that...

            if (substr($key, 0, strlen('folder_')) == 'folder_' && $value != 'nothing')
            {

            }
        }

        // ------------------------------------
        //  Additional Weblog Moving Work - Stats/Groups
        // ------------------------------------

        if (sizeof($weblog_ids) > 0)
        {
            $status           = [];
            $fields           = [];
            $categories       = [];
            $category_groups  = [];
            $field_match      = [];
            $cat_field_match  = [];

            foreach($weblog_ids as $old_weblog => $new_weblog)
            {
                $query = DB::table('weblogs')
                    ->where('weblog_id', $new_weblog)
                    ->select('cat_group', 'status_group', 'field_group')
                    ->first();

                // ------------------------------------
                //  Duplicate Status Group
                // ------------------------------------

                if (!empty($query->status_group))
                {
                    if (!isset($status[$query->status_group]))
                    {
                        $group_name = DB::table('status_groups')
                            ->where('group_id', $query->status_group)
                            ->select('group_name')
                            ->value('group_name');

                        // Uniqueness checks
                        foreach(['group_name'] AS $check)
                        {
                            $count = DB::table('status_groups')
                                ->where('site_id', $site_id)
                                ->where($check, 'LIKE', $group_name.'%')
                                ->count();

                            if ($count > 0) {
                                $count++;
                                $group_name .= '-'.$count;
                            }
                        }

                        $new_group_id = DB::table('status_groups')
                            ->insertGetId([
                                'site_id'       => $site_id,
                                'group_name'    => $group_name
                            ]);

                        $squery = DB::table('statuses')
                            ->where('group_id', $query->status_group)
                            ->get();

                        foreach($squery as $row)
                        {
                            $row                = (array) $row;
                            $row['site_id']     = $site_id;
                            $row['status_id']   = null;
                            $row['group_id']    = $new_group_id;

                            DB::table('statuses')->insert($row);
                        }

                        // Prevent Duplication
                        $status[$query->status_group] = $new_group_id;
                    }

                    // ------------------------------------
                    //  Update Weblog With New Group ID
                    // ------------------------------------

                    DB::table('weblogs')
                        ->where('weblog_id', $new_weblog)
                        ->update(['status_group' => $status[$query->status_group]]);
                }


                // ------------------------------------
                //  Duplicate Field Group
                // ------------------------------------

                if ( ! empty($query->field_group))
                {
                    if ( ! isset($fields[$query->field_group]))
                    {
                        $group_name = DB::table('field_groups')
                            ->where('group_id', $query->field_group)
                            ->value('group_name');

                        // Uniqueness checks
                        $count = DB::table('field_groups')
                            ->where('site_id', $site_id)
                            ->where($check, 'LIKE', $group_name.'%')
                            ->count();

                        if ($count > 0) {
                            $count++;
                            $group_name .= '-'.$count;
                        }

                        $new_group_id = DB::table('field_groups')
                            ->insert([
                                'site_id'    => $site_id,
                                'group_name' => $group_name
                            ]);


                        // ------------------------------------
                        //  New Fields Created for New Field Group
                        // ------------------------------------

                        $fquery = DB::table('weblog_fields')
                            ->where('group_id', $query->field_group)
                            ->get();

                        foreach($fquery as $row)
                        {
                            $row                = (array) $row;
                            $old_field_handle   = $row['field_handle'];

                            $row['site_id']     = $site_id;
                            $row['field_id']    = null;
                            $row['group_id']    = $new_group_id;

                            // Uniqueness checks
                            foreach(['field_name', 'field_handle'] AS $check) {
                                $count = DB::table('weblog_fields')
                                    ->where('site_id', $site_id)
                                    ->where($check, 'LIKE', $row[$check].'%')
                                    ->count();

                                if ($count > 0) {
                                    $count++;
                                    $row[$check] .= '-'.$count;
                                }
                            }

                            $new_field_id = DB::table('weblog_fields')->insert($row);

                            $field_handle = $row['field_handle'];
                            $field_match[$old_field_handle] = $row['field_handle'];

                            // ------------------------------------
                            //  Weblog Data Field Creation, Whee!
                            // ------------------------------------

                            switch($row['field_type'])
                            {
                                case 'date' :
                                    Schema::table('weblog_entry_data', function($table) use ($field_handle) {
                                        $table->timestamp('field_'.$field_handle)->nullable(true);
                                    });
                                break;
                                default:
                                    Schema::table('weblog_entry_data', function($table) use ($field_handle) {
                                        $table->text('field_'.$field_handle)->nullable(true);
                                    });
                                break;
                            }
                        }

                        // Prevents duplication of field group creation
                        $fields[$query->field_group] = $new_group_id;
                    }

                    // ------------------------------------
                    //  Update New Weblog With New Group ID
                    // ------------------------------------

                    DB::table('weblogs')
                        ->where('weblog_id', $new_weblog)
                        ->update(['field_group' => $fields[$query->field_group]]);

                    // ------------------------------------
                    //  Moved Weblog?  Need Old Field Group
                    // ------------------------------------

                    if (isset($moved[$old_weblog])) {
                        $moved[$old_weblog] = $query->field_group;
                    }
                }

                // ------------------------------------
                //  Duplicate Category Group(s)
                // ------------------------------------

                $new_cat_group = '';

                if (!empty($query->cat_group))
                {
                    $new_insert_group = [];

                    foreach(explode('|', $query->cat_group) as $cat_group)
                    {
                        if (isset($category_groups[$cat_group])) {
                            $new_insert_group[] = $category_groups[$cat_group];

                            continue;
                        }

                        $gquery = (array) DB::table('category_groups')
                            ->where('group_id', $cat_group)
                            ->first();

                        if (empty($gquery)) {
                            continue;
                        }

                        // Uniqueness checks
                        $count = DB::table('category_groups')
                            ->where('site_id', $site_id)
                            ->where('group_name', $gquery['group_name'])
                            ->count();

                        if ($count > 0) {
                            $count++;
                            $gquery['group_name'] .= '-'.$count;
                        }

                        $gquery['site_id']  = $site_id;
                        $gquery['group_id'] = null;

                        $new_group_id = DB::table('category_groups')->insertGetId($gquery);

                        $category_groups[$cat_group] = $new_group_id;
                        $new_insert_group[]          = $new_group_id;

                        // ------------------------------------
                        //  New Categories Created for New Category Group
                        // ------------------------------------

                        $cquery = DB::table('categories')
                            ->where('group_id', $cat_group)
                            ->orderBy('parent_id') // Important, insures we get parents in first
                            ->get()
                            ->toArray();

                        foreach($cquery as $row)
                        {
                            // Uniqueness checks
                            foreach(['category_url_title'] AS $check) {
                                $count = DB::table('categories')
                                    ->where('site_id', $site_id)
                                    ->where($check, 'LIKE', $row[$check].'%')
                                    ->count();

                                if ($count > 0) {
                                    $count++;
                                    $row[$check] .= '-'.$count;
                                }
                            }

                            $old_cat_id         = $row['category_id'];

                            $row['site_id']     = $site_id;
                            $row['category_id'] = null;
                            $row['group_id']    = $new_group_id;
                            $row['parent_id']   =
                                ($row['parent_id'] == '0' OR ! isset($categories[$row['parent_id']])) ?
                                0 :
                                $categories[$row['parent_id']];

                            $categories[$old_cat_id] = DB::table('categories')->insertGetId($row);
                        }
                    }

                    $new_cat_group = implode('|', $new_insert_group);
                }

                // ------------------------------------
                //  Update Weblog With New Group ID
                // ------------------------------------

                DB::table('weblogs')
                    ->where('weblog_id', $weblog_id)
                    ->update(['cat_group' => $new_cat_group]);
            }


            // ------------------------------------
            //  Move Data Over For Moved Weblogs/Entries
            //  - Find Old Fields from Old Site Field Group, Move Data to New Fields, Zero Old Fields
            //  - Reassign Categories for New Weblogs Based On $categories array
            // ------------------------------------

            if (sizeof($moved) > 0)
            {
                // ------------------------------------
                //  Moving Field Data for Moved Entries
                // ------------------------------------

                foreach($moved as $weblog_id => $field_group)
                {
                    $query = DB::table('weblog_fields')
                        ->select('field_id', 'field_name', 'field_handle', 'field_type')
                        ->where('group_id', $field_group)
                        ->get()
                        ->toArray();

                    if (isset($entries[$weblog_id])) {
                        $weblog_id = $weblog_ids[$weblog_id]; // Moved Entries, New Weblog ID Used
                    }

                    if ($query->count() > 0)
                    {
                        foreach($query as $row)
                        {
                            if ( ! isset($field_match[$row['field_handle']])) {
                                continue;
                            }

                            // Move data over
                            DB::table('weblog_entry_data')
                                ->where('weblog_id', $weblog_id)
                                ->update(
                                    [
                                        DB::raw(
                                            "`field_".$field_match[$row['field_handle']]."`".
                                            " = ".
                                            "`field_".$row['field_handle']."`"
                                        )
                                    ]
                                );

                            // Clear old data
                            DB::table('weblog_entry_data')
                                ->where('weblog_id', $weblog_id)
                                ->update(
                                    [
                                        'field_'.$row['field_handle'] = ''
                                    ]
                                );

                        }
                    }

                    // ------------------------------------
                    //  Category Reassignment
                    // ------------------------------------

                    $query = DB::table('weblog_entry_categories')
                        ->join('weblog_entries', 'weblog_entries.entry_id', '=', 'weblog_entry_categories.entry_id')
                        ->select('weblog_entry_categories.entry_id')
                        ->get();

                    $entry_ids = [];

                    foreach($query as $row) {
                        $entry_ids[] = $row->entry_id;
                    }

                    foreach($categories as $old_cat => $new_cat)
                    {
                        DB::table('weblog_entry_categories')
                            ->whereIn('entry_id', $entry_ids)
                            ->where('category_id', $old_cat)
                            ->update(
                                [
                                    'category_id' => $new_cat
                                ]
                            );
                    }
                }
            }
        }
    }
}
