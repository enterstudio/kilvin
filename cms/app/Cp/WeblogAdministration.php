<?php

namespace Kilvin\Cp;

use Cp;
use DB;
use Site;
use Stats;
use Schema;
use Plugins;
use Request;
use Validator;
use Carbon\Carbon;
use Kilvin\Core\Regex;
use Kilvin\Core\Session;

class WeblogAdministration
{
    // Category arrays
    public $categories = [];
    public $cat_update = [];

    public $temp;

    // --------------------------------------------------------------------

    /**
    * Constructor
    *
    * @return void
    */
    public function __construct()
    {
        $publish_access = [
            'category_editor',
            'edit_category',
            'update_category',
            'del_category_conf',
            'del_category',
            'category_order'
        ];

        // This flag determines if a user can edit categories from the publish page.
        $category_exception =
            (
                in_array(Request::input('M'), $publish_access)
                and
                Request::input('Z') == 1
            ) ?
            true :
            false;

        if (
            $category_exception === false
            and
            (
                ! Session::access('can_admin_weblogs')
                or
                ! Session::access('can_access_admin')
            )
        ) {
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


        return Cp::unauthorizedAccess();
    }

    // --------------------------------------------------------------------

    /**
    * Weblogs Management
    *
    * @param string $message
    * @return string
    */
    public function weblogsOverview($message = '')
    {
        Cp::$title  = __('admin.weblog_management');
        Cp::$crumb .= __('admin.weblog_management');

        $right_links[] = [
            BASE.'?C=WeblogAdministration'.AMP.'M=newWeblogForm',
            __('admin.create_new_weblog')
        ];

        $r = Cp::header(__('admin.weblog_management'), $right_links);

        // Fetch weblogs
        $query = DB::table('weblogs')
            ->select('weblog_id', 'weblog_name', 'weblog_title')
            ->orderBy('weblog_title')
            ->get();

        if ($query->count() == 0)
        {
            $r .= Cp::div('box');
            $r .= Cp::quickDiv('littlePadding', Cp::heading(__('admin.no_weblogs_exist'), 6));
            $r .= Cp::quickDiv(
                'littlePadding',
                Cp::anchor(
                    BASE.'?C=WeblogAdministration'.
                        AMP.'M=newWeblogForm',
                    __('admin.create_new_weblog')
                )
            );
            $r .= '</div>'.PHP_EOL;

            return Cp::$body = $r;
        }

        if ($message != '')
        {
            $r .= Cp::quickDiv('success-message', stripslashes($message));
        }

        $r .= Cp::table('tableBorder', '0', '', '100%');

        $r .= '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt').__('admin.weblog_name').'</td>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '', 6).__('admin.weblog_handle').'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach($query as $row)
        {

            $r .= '<tr>'.PHP_EOL;

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    BASE.'?C=WeblogAdministration'.
                        AMP.'M=editWeblog'.
                        AMP.'weblog_id='.$row->weblog_id,
                    $row->weblog_title,
                    'class="defaultBold"'
                )
            );

            $r .= Cp::tableCell('', Cp::quickSpan('default', $row->weblog_name).' &nbsp; ');


            $r .= Cp::tableCell('',
                  Cp::anchor(
                                BASE.'?C=WeblogAdministration'.AMP.'M=editWeblogFields'.AMP.'weblog_id='.$row->weblog_id,
                                __('admin.edit_fields')
                              ));

            $r .= Cp::tableCell('',
                  Cp::anchor(
                                BASE.'?C=WeblogAdministration'.AMP.'M=editWeblogLayout'.AMP.'weblog_id='.$row->weblog_id,
                                __('admin.edit_publish_layout')
                              ));

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    BASE.'?C=WeblogAdministration'.
                        AMP.'M=deleteWeblogConfirm'.
                        AMP.'weblog_id='.$row->weblog_id,
                    __('cp.delete'),
                    'class="delete-link"'
                )
            );

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        // Assign output data

        Cp::$body = $r;

    }

    //--------------------------------------------------------------

    /**
     * New Weblog Form
     *
     * @return string
     */
    public function newWeblogForm()
    {
        $r = <<<EOT
<script type="text/javascript">

$(function() {
    $('input[name=edit_group_prefs]').click(function(e){
        $('#group_preferences').toggle();
    });
});

</script>
EOT;

        $r .= Cp::formOpen(['action' => 'C=WeblogAdministration'.AMP.'M=updateWeblog']);

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL
            .Cp::td('tableHeading', '', '2').__('admin.create_new_weblog').'</td>'.PHP_EOL
            .'</tr>'.PHP_EOL;


        // Weblog "full name" field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::required().NBS.Cp::quickSpan('defaultBold', __('admin.full_weblog_name'))).
              Cp::tableCell('', Cp::input_text('weblog_title', '', '20', '100', 'input', '260px')).
              '</tr>'.PHP_EOL;

        // Weblog "short name" field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::required().NBS.Cp::quickSpan('defaultBold', __('admin.short_weblog_name')).Cp::quickDiv('', __('admin.single_word_no_spaces_with_underscores')), '40%').
              Cp::tableCell('', Cp::input_text('weblog_name', '', '20', '40', 'input', '260px'), '60%').
              '</tr>'.PHP_EOL;

        // Duplicate Preferences Select List
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.duplicate_weblog_prefs')));

        $w  = Cp::input_select_header('duplicate_weblog_prefs');
        $w .= Cp::input_select_option('', __('admin.do_not_duplicate'));

        $wquery = DB::table('weblogs')
            ->select('weblog_id', 'weblog_name', 'weblog_title')
            ->orderBy('weblog_title')
            ->get();

        foreach($wquery as $row) {
            $w .= Cp::input_select_option($row->weblog_id, $row->weblog_title);
        }

        $w .= Cp::input_select_footer();

        $r .= Cp::tableCell('', $w).
              '</tr>'.PHP_EOL;

        // Edit Group Preferences option

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.edit_group_prefs')), '40%').
              Cp::tableCell('', Cp::input_radio('edit_group_prefs', 'y').
                                                NBS.__('admin.yes').
                                                NBS.
                                                Cp::input_radio('edit_group_prefs', 'n', 1).
                                                NBS.__('admin.no'), '60%').
              '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL.BR;

        // GROUP FIELDS
        $g = '';
        $i = 0;
        $cat_group = '';
        $status_group = '';
        $field_group = '';

        $r .= Cp::div('', '', 'group_preferences', '', 'style="display:none;"');
        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '100%', 2).__('admin.edit_group_prefs').'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Category group select list
        $query = DB::table('category_groups')
            ->orderBy('group_name')
            ->select('group_id', 'group_name')
            ->get();

        $g .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.category_group')), '40%', 'top');

        $g .= Cp::td().
              Cp::input_select_header('cat_group[]', ($query->count() > 0) ? 'y' : '');

        $selected = '';

        $g .= Cp::input_select_option('', __('admin.none'), $selected);

        if ($query->count() > 0)
        {
            foreach ($query as $row)
            {
                $g .= Cp::input_select_option($row->group_id, $row->group_name);
            }
        }

        $g .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Status group select list
        $query = DB::table('status_groups')
            ->orderBy('group_name')
            ->select('group_id', 'group_name')
            ->get();

        $g .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.status_group')));

        $g .= Cp::td().
              Cp::input_select_header('status_group');

        $selected = '';

        $g .= Cp::input_select_option('', __('admin.none'), $selected);

        if ($query->count() > 0)
        {
            foreach ($query as $row)
            {
                $selected = ($status_group == $row->group_id) ? 1 : '';

                $g .= Cp::input_select_option($row->group_id, $row->group_name, $selected);
            }
        }

        $g .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Field group select list
        $query = DB::table('field_groups')
            ->orderBy('group_name')
            ->select('group_id', 'group_name')
            ->get();

        $g .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.field_group')));

        $g .= Cp::td().
              Cp::input_select_header('field_group');

        $selected = '';

        $g .= Cp::input_select_option('', __('admin.none'), $selected);

        if ($query->count() > 0)
        {
            foreach ($query as $row)
            {
                $selected = ($field_group == $row->group_id) ? 1 : '';

                $g .= Cp::input_select_option($row->group_id, $row->group_name, $selected);
            }
        }

        $g .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL.BR.
              '</div>'.PHP_EOL;

        $r .= $g;
        // Table end

        // Submit button
        $r .= Cp::quickDiv('littlePadding', Cp::required(1));
        $r .= Cp::quickDiv('', Cp::input_submit(__('cp.submit')));

        $r .= '</form>'.PHP_EOL;

        // Assign output data
        Cp::$title = __('admin.create_new_weblog');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=weblogsOverview', __('admin.weblog_management')).
            Cp::breadcrumbItem(__('admin.new_weblog'));

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Update or Create Weblog Preferences
    *
    * @return string
    */
    public function updateWeblog()
    {
        $edit    = (bool) Request::filled('weblog_id');
        $return  = (bool) Request::filled('return');
        $dupe_id = Request::input('duplicate_weblog_prefs');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'weblog_name'        => 'required|regex:/^[\pL\pM\pN_]+$/u',
            'weblog_title'       => 'required',
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        // Is the weblog name taken?
        $query = DB::table('weblogs')
            ->where('weblog_name', Request::input('weblog_name'));

        if ($edit === true) {
            $query->where('weblog_id', '!=', Request::input('weblog_id'));
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('admin.taken_weblog_name'));
        }

        $data = [];
        if ($edit === true) {
            $data = (array) DB::table('weblogs')
                ->where('weblog_id', Request::input('weblog_id'))
                ->first();
        }

        $fields = [
            'weblog_id',
            'weblog_title',
            'weblog_name',
            'weblog_description',
            'weblog_url',
            'live_look_template',
            'enable_versioning',
            'enable_qucksave_versioning',
            'max_revisions',
            'weblog_notify',
            'weblog_notify_emails',
        ];

        foreach($fields as $field) {
            if (Request::filled($field)) {
                $data[$field] = Request::input($field);
            }
        }

        $strings = [
            'weblog_description',
            'weblog_notify_emails',
            'weblog_url'
        ];

        foreach($strings as $field) {
            if(empty($data[$field])) {
                $data[$field] = '';
            }
        }

        // Let DB defaults handle these if empty
        $unsettable = [
            'enable_versioning',
            'enable_qucksave_versioning',
            'max_revisions',
            'weblog_notify',
        ];

        foreach($unsettable as $field) {
            if(empty($data[$field])) {
                unset($data[$field]);
            }
        }

        // ------------------------------------
        //  Template Error Trapping
        // ------------------------------------

        if ($edit === false) {
            $old_group_id       = Request::input('old_group_id');
            $group_name         = strtolower(Request::input('group_name'));
            $template_theme     = filename_security(Request::input('template_theme'));
        }

        // ------------------------------------
        //  Conversion
        // ------------------------------------

        if (Request::filled('weblog_notify_emails') && is_array(Request::input('weblog_notify_emails'))) {
            $data['weblog_notify_emails'] = implode(',', Request::input('weblog_notify_emails'));
        }

        // ------------------------------------
        //  Create Weblog
        // ------------------------------------

        if ($edit === false) {
            // Assign field group if there is only one
            if ( ! isset($data['field_group']) or ! is_numeric($data['field_group'])) {
                $query = DB::table('field_groups')
                        ->select('group_id')
                        ->get();

                if ($query->count() == 1) {
                    $data['field_group'] = $query->first()->group_id;
                }
            }

            // --------------------------------------
            //  Duplicate Preferences
            // --------------------------------------

            if ($dupe_id !== false AND is_numeric($dupe_id))
            {
                $wquery = DB::table('weblogs')
                    ->where('weblog_id', $dupe_id)
                    ->first();

                if ($wquery)
                {
                    $exceptions = [
                        'weblog_id',
                        'weblog_name',
                        'weblog_title',
                        'total_entries',
                        'last_entry_date',
                    ];

                    foreach($wquery as $key => $val)
                    {
                        // don't duplicate fields that are unique to each weblog
                        if (in_array($key, $exceptions)) {
                            continue;
                        }

                        if (empty($data[$key])) {
                            $data[$key] = $val;
                        }
                    }
                }
            }

            $insert_id = $weblog_id = DB::table('weblogs')->insertGetId($data);

            // ------------------------------------
            //  Create First Tab
            // ------------------------------------

            DB::table('weblog_layout_tabs')
                ->insert([
                    'weblog_id' => $insert_id,
                    'tab_name' => 'Publish',
                    'tab_order' => 1
                ]);

            $success_msg = __('admin.weblog_created');
        }

        // ------------------------------------
        //  Updating Weblog
        // ------------------------------------

        if ($edit === true) {
            if (isset($data['clear_versioning_data'])) {
                DB::table('entry_versioning')
                    ->where('weblog_id', $data['weblog_id'])
                    ->delete();
            }

            DB::table('weblogs')
                ->where('weblog_id', $data['weblog_id'])
                ->update($data);

            $weblog_id = $data['weblog_id'];

            $success_msg = __('admin.weblog_updated');
        }

        // ------------------------------------
        //  Messages and Return
        // ------------------------------------

        Cp::log($success_msg.$data['weblog_title']);

        $message = $success_msg.'<strong>'.$data['weblog_title'].'</strong>';

        if ($edit === false OR $return === true) {
            return $this->weblogsOverview($message);
        } else {
            return $this->editWeblog($message, $weblog_id);
        }
    }

    // --------------------------------------------------------------------

    /**
    * Update Weblog Layout Preferences
    *
    * @return string
    */
    public function updateWeblogFields()
    {
        $return = (bool) Request::filled('return');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'url_title_prefix'   => 'alpha_dash',
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = (array) DB::table('weblogs')
            ->where('weblog_id', Request::input('weblog_id'))
            ->first();

        if (empty($data)) {
            return Cp::unauthorizedAccess();
        }

        $fields = [
            'cat_group',
            'status_group',
            'field_group',
            'live_look_template',
            'default_status',
            'default_category',
            'show_url_title',
            'show_categories_tab',
            'url_title_prefix'
        ];

        foreach($fields as $field) {
            if (Request::input($field) !== null) {
                $data[$field] = Request::input($field);
            }
        }

        if (isset($data['cat_group']) && is_array($data['cat_group'])) {
            $data['cat_group'] = implode('|', $data['cat_group']);
        }

        $nullable = [
            'cat_group',
            'status_group',
            'field_group'
        ];

        foreach($nullable as $field) {
            if(empty($data[$field])) {
                $data[$field] = null;
            }
        }

        $strings = [
            'default_status',
            'url_title_prefix'
        ];

        foreach($strings as $field) {
            if(empty($data[$field])) {
                $data[$field] = '';
            }
        }

        // Let DB defaults handle these if empty
        $unsettable = [
            'show_categories_tab',
        ];

        foreach($unsettable as $field) {
            if(empty($data[$field])) {
                unset($data[$field]);
            }
        }

        // ------------------------------------
        //  Updating Weblog
        // ------------------------------------

        DB::table('weblogs')
            ->where('weblog_id', $data['weblog_id'])
            ->update($data);

        $weblog_id = $data['weblog_id'];

        $success_msg = __('admin.weblog_updated');

        // ------------------------------------
        //  Messages and Return
        // ------------------------------------

        Cp::log($success_msg.$data['weblog_title']);

        $message = $success_msg.'<strong>'.$data['weblog_title'].'</strong>';

        if ($return === true) {
            return $this->weblogsOverview($message);
        } else {
            return $this->editWeblogFields($message, $weblog_id);
        }
    }

    //--------------------------------------------------------------

    /**
     * Edit Weblog Preferences
     *
     * @param string $msg A message of success
     * @param $weblog_id Load this weblog's preferences
     * @return void
     */
    public function editWeblog($msg='', $weblog_id='')
    {
        // Default values
        $i            = 0;
        $weblog_name  = '';
        $weblog_title = '';
        $cat_group    = '';
        $status_group = '';

        if (empty($weblog_id)) {
            if ( ! $weblog_id = Request::input('weblog_id')) {
                return false;
            }
        }

        $query = DB::table('weblogs')->where('weblog_id', $weblog_id)->first();

        if (!$query) {
            return $this->weblogsOverview();
        }

        foreach ($query as $key => $val) {
            $$key = $val;
        }

        if ($msg != '') {
            Cp::$body .= Cp::quickDiv('box', $msg);
        }

        // New blog so set default
        if (empty($weblog_url)) {
           $weblog_url = Site::config('site_url');
        }

        //------------------------------------
        // Build the output
        //------------------------------------

        $r  = Cp::formOpen(['action' => 'C=WeblogAdministration'.AMP.'M=updateWeblog']);
        $r .= Cp::input_hidden('weblog_id', $weblog_id);

        // ------------------------------------
        //  General settings
        // ------------------------------------

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' id='weblog2' colspan='2'>";
        $r .= __('admin.weblog_base_setup').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // Weblog "full name" field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::required().Cp::quickSpan('defaultBold', __('admin.full_weblog_name')), '50%').
              Cp::tableCell('', Cp::input_text('weblog_title', $weblog_title, '20', '100', 'input', '260px'), '50%').
              '</tr>'.PHP_EOL;

        // Weblog "short name" field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::required().Cp::quickSpan('defaultBold', __('admin.short_weblog_name')).'&nbsp;'.'-'.'&nbsp;'.__('admin.single_word_no_spaces_with_underscores'), '50%').
              Cp::tableCell('', Cp::input_text('weblog_name', $weblog_name, '20', '40', 'input', '260px'), '50%').
              '</tr>'.PHP_EOL;

        // Weblog descriptions field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.blog_description')), '50%').
              Cp::tableCell('', Cp::input_text('weblog_description', $weblog_description, '50', '225', 'input', '100%'), '50%').
              '</tr>'.PHP_EOL;

         // Weblog URL field
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell(
                '',
                Cp::quickSpan(
                    'defaultBold',
                    __('admin.weblog_url')
                ).
                Cp::quickDiv('default', __('admin.weblog_url_explanation')),
                '50%').
              Cp::tableCell('', Cp::input_text('weblog_url', $weblog_url, '50', '80', 'input', '100%'), '50%').
              '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;

        // Text: * Indicates required fields
        $r .= Cp::quickDiv('littlePadding', Cp::required(1)).'<br>';

        // ------------------------------------
        //  Versioning settings
        // ------------------------------------

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' id='versioning2' colspan='2'>";
        $r .= __('admin.versioning').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;


        // Enable Versioning?
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.enable_versioning')), '50%')
             .Cp::td('', '50%');

              $selected = ($enable_versioning == 'y') ? 1 : '';

        $r .= Cp::qlabel(__('admin.yes'))
             .Cp::input_radio('enable_versioning', 'y', $selected).'&nbsp;';

              $selected = ($enable_versioning == 'n') ? 1 : '';

        $r .= Cp::qlabel(__('admin.no'))
             .Cp::input_radio('enable_versioning', 'n', $selected)
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;


        // Enable Quicksave versioning
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.enable_qucksave_versioning')).BR.__('admin.quicksave_note'), '50%')
             .Cp::td('', '50%');

              $selected = ($enable_qucksave_versioning == 'y') ? 1 : '';

        $r .= Cp::qlabel(__('admin.yes'))
             .Cp::input_radio('enable_qucksave_versioning', 'y', $selected).'&nbsp;';

              $selected = ($enable_qucksave_versioning == 'n') ? 1 : '';

        $r .= Cp::qlabel(__('admin.no'))
             .Cp::input_radio('enable_qucksave_versioning', 'n', $selected)
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        // Max Revisions
        $x = Cp::quickDiv('littlePadding', Cp::input_checkbox('clear_versioning_data', 'y', 0).' '.Cp::quickSpan('highlight', __('admin.clear_versioning_data')));

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.max_revisions')).BR.__('admin.max_revisions_note'), '50%').
              Cp::tableCell('', Cp::input_text('max_revisions', $max_revisions, '30', '4', 'input', '100%').$x, '50%').
              '</tr>'.PHP_EOL;


        $r .= '</table><br>'.PHP_EOL;


        // ------------------------------------
        //  Notifications
        // ------------------------------------

        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' id='not2' colspan='2'>";
        $r .= __('admin.notification_settings').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.weblog_notify')), '50%')
             .Cp::td('', '50%');

        $selected = ($weblog_notify == 'y') ? 1 : '';

        $r .= Cp::qlabel(__('admin.yes'))
             .Cp::input_radio('weblog_notify', 'y', $selected).'&nbsp;';

        $selected = ($weblog_notify == 'n') ? 1 : '';

        $r .= Cp::qlabel(__('admin.no'))
             .Cp::input_radio('weblog_notify', 'n', $selected)
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        $users = DB::table('members')
            ->distinct()
            ->select('members.screen_name', 'members.member_id', 'members.email')
            ->leftJoin('member_group_preferences', function ($join) use ($weblog_id) {
                $join->on('member_group_preferences.group_id', '=', 'members.group_id')
                     ->where('member_group_preferences.handle', 'weblog_id_'.$weblog_id);
            })
            ->where('members.group_id', 1)
            ->orWhere('member_group_preferences.value', 'y')
            ->get();

        $weblog_notify_emails = explode(',', $weblog_notify_emails);

        $s = '<select name="weblog_notify_emails[]" multiple="multiple" size="8" style="width:100%">'.PHP_EOL;

        foreach($users as $row) {

            $selected = (in_array($row->member_id, $weblog_notify_emails)) ? 'selected="selected"' : '';

            $s .= '<option value="'.$row->member_id.'" '.$selected.'>'.$row->screen_name.' &lt;'.$row->email.'&gt;</option>'.PHP_EOL;
        }

        $s .= '</select>'.PHP_EOL;

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell(
                '',
                Cp::quickSpan('defaultBold', __('admin.emails_of_notification_recipients')), '50%', 'top').
              Cp::tableCell('', $s, '50%').
              '</tr>'.PHP_EOL;


        $r .= '</table>'.PHP_EOL;



        // Update button and form close
        $r .= Cp::div('littlePadding');
        $r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('cp.update'),'return'));
        $r .= '</div>'.PHP_EOL.'</form>'.PHP_EOL;

        // ------------------------------------
        //  Create Table
        // ------------------------------------

        Cp::$body .=
            Cp::table('', '0', '', '100%').
                Cp::tableRow(['valign' => "top", 'text' => $r]).
            '</table>'.
            PHP_EOL;


        Cp::$title = __('admin.edit_weblog_prefs');

        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=weblogsOverview', __('admin.weblog_management')).
            Cp::breadcrumbItem(__('admin.edit_weblog_prefs'));
    }

    //--------------------------------------------------------------

    /**
     * Edit Layout for Weblog
     *
     * @return string
     */
    public function editWeblogLayout()
    {
        if ( ! $weblog_id = Request::input('weblog_id')) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch Weblog
        // ------------------------------------

        $weblog_query = DB::table('weblogs')
                ->where('weblog_id', $weblog_id)
                ->first();

        if (!$weblog_query) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch Layout
        // ------------------------------------

        $layout_query = DB::table('weblog_layout_tabs')
            ->leftJoin('weblog_layout_fields', 'weblog_layout_tabs.weblog_layout_tab_id', '=', 'weblog_layout_fields.tab_id')
            ->where('weblog_layout_tabs.weblog_id', $weblog_id)
            ->orderBy('weblog_layout_tabs.tab_order')
            ->orderBy('weblog_layout_fields.field_order')
            ->orderBy('weblog_layout_fields.field_name')
            ->get();

        // ------------------------------------
        //  Fetch Custom Fields
        // ------------------------------------

        $available_fields = DB::table('weblog_fields')
            ->select('field_name', 'field_label', 'field_type')
            ->where('group_id', $weblog_query->field_group)
            ->orderBy('field_label')
            ->get()
            ->keyBy('field_name')
            ->map(function ($item, $key) {
                $item->used = false;
                return $item;
            })
            ->toArray();

        // ------------------------------------
        //  Layout Array
        // ------------------------------------

        foreach($layout_query as $row) {

            $handle = $row->tab_id;

            if (!isset($layout[$handle])) {
                $layout[$handle] = [];
                $publish_tabs[$handle] = $row->tab_name;
            }

            if (isset($available_fields[$row->field_name])) {
                $layout[$row->tab_id][$row->field_name] = $available_fields[$row->field_name];
                $available_fields[$row->field_name]->used = true;
            }
        }

        // ------------------------------------
        //  Build Vars
        // ------------------------------------

        $vars = [
            'calendar_image'       => Cp::calendarImage(),
            'weblog_id'            => $weblog_id,
            'publish_tabs'         => $publish_tabs,
            'layout'               => $layout,
            'fields'               => $available_fields,
            'url_title_javascript' => url_title_javascript('_'),
            'svg_icon_gear'        => svg_icon_gear()
        ];

        // -----------------------------------------
        //  CP Message?
        // -----------------------------------------

        $cp_message = session()->pull('cp_message');

        if (!empty($cp_message)) {
            $vars['cp_message'] = $cp_message;
        }

        // ------------------------------------
        //  Build Page
        // ------------------------------------

        Cp::$title = $vars['page_name'] = __('admin.edit_weblog_layout');

        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=weblogsOverview', $weblog_query->weblog_title).
            Cp::breadcrumbItem(__('admin.edit_weblog_layout'));


        return view('cp.administration.weblogs.layout', $vars);
    }

    //--------------------------------------------------------------

    /**
     * Update Layout for Weblog
     *
     * @return string|\Illuminate\Http\RedirectResponse
     */
    public function updateWeblogLayout()
    {
        if ( ! $weblog_id = Request::input('weblog_id')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $tabs = Request::input('tabs')) {
            return Cp::unauthorizedAccess();
        }

        if (!is_array($tabs)) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $fields = Request::input('fields')) {
            return Cp::unauthorizedAccess();
        }

        if (!is_array($fields)) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch Weblog
        // ------------------------------------

        $weblog_query = DB::table('weblogs')
                ->where('weblog_id', $weblog_id)
                ->first();

        if (!$weblog_query) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Fetch Custom Fields
        // ------------------------------------

        $field_query = DB::table('weblog_fields')
            ->select('field_name', 'field_label', 'field_type')
            ->where('group_id', $weblog_query->field_group)
            ->orderBy('field_label')
            ->get();

        $available_fields = $field_query
            ->keyBy('field_name')
            ->toArray();

        // ------------------------------------
        // Clear Out Existing (Ruthless!)
        // - Foreign keys will take care of weblog_layout_fields
        // ------------------------------------

        $tab_ids = DB::table('weblog_layout_tabs')
            ->where('weblog_id', $weblog_id)
            ->delete();

        // ------------------------------------
        //  Add in Tabs
        // ------------------------------------

        $order = 0;

        $db_tabs = [];

        foreach($tabs as $handle => $tab) {

            $id = DB::table('weblog_layout_tabs')
                ->insertGetId(
                    [
                        'weblog_id' => $weblog_id,
                        'tab_name'  => $tab,
                        'tab_order' => $order++
                    ]
                );

            $db_tabs[$handle] = $id;
        }

        // ------------------------------------
        //  Add in Fields
        // ------------------------------------

        foreach($fields as $handle => $tab_fields) {

            if (!isset($db_tabs[$handle])) {
                continue;
            }

            $order = 0;

            foreach($tab_fields as $field_name) {

                if (!isset($available_fields[$field_name])) {
                    continue;
                }

                $id = DB::table('weblog_layout_fields')
                    ->insertGetId(
                        [
                            'tab_id'     => $db_tabs[$handle],
                            'field_name' => $field_name,
                            'field_order'  => $order++
                        ]
                    );
            }
        }

        // ------------------------------------
        //  Redirect with Save
        // ------------------------------------

        session()->flash(
            'cp_message',
            __('admin.Layout Updated')
        );

        return redirect('?C=WeblogAdministration&M=editWeblogLayout&weblog_id='.$weblog_id);
    }

    //--------------------------------------------------------------

    /**
     * Edit Weblog Fields
     *
     * @param string $msg A message of success
     * @param $weblog_id Load this weblog's preferences
     * @return void
     */
    public function editWeblogFields($msg='', $weblog_id='')
    {
        // Default values
        $i            = 0;
        $cat_group    = '';
        $status_group = '';

        if (empty($weblog_id)) {
            if ( ! $weblog_id = Request::input('weblog_id')) {
                return false;
            }
        }

        $query = DB::table('weblogs')->where('weblog_id', $weblog_id)->first();

        if (!$query) {
            return $this->weblogsOverview();
        }

        foreach ($query as $key => $val) {
            $$key = $val;
        }

        if ($msg != '') {
            Cp::$body .= Cp::quickDiv('success-message', $msg);
        }

        // New blog so set default
        if (empty($weblog_url)) {
           $weblog_url = Site::config('site_url');
        }

        //------------------------------------
        // Build the output
        //------------------------------------

        $js = <<<EOT
<script type="text/javascript">

var lastShownObj = '';
var lastShownColor = '';
function showHideMenu(objValue)
{
    if (lastShownObj)
    {
        $('#' + lastShownObj+'_pointer a').first().css('color', lastShownColor);
        $('#' + lastShownObj+'_on').css('display', 'none');
    }

    lastShownObj = objValue;
    lastShownColor = $('#' + objValue+'_pointer a').first().css('color');

    $('#' + objValue + '_on').css('display', 'block');
    $('#' + objValue+'_pointer a').first().css('color', '#000');
}

$(function() {
    showHideMenu('fields');
});

</script>

EOT;
        Cp::$body .= $js;

        $r  = Cp::formOpen(['action' => 'C=WeblogAdministration'.AMP.'M=updateWeblogFields']);
        $r .= Cp::input_hidden('weblog_id', $weblog_id);

        $r .= Cp::quickDiv('none', '', 'menu_contents');

        // ------------------------------------
        //  Fields
        // ------------------------------------

        $cat_groups = explode('|', $cat_group);

        $r .= '<div id="fields_on" style="display: none; padding:0; margin: 0;">';
        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' colspan='2'>";
        $r .= NBS.__('admin.fields').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // Category group select list
        $query = DB::table('category_groups')
            ->orderBy('group_name')
            ->select('group_id', 'group_name')
            ->get();

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.category_group')), '40%', 'top');

        $r .= Cp::td().
              Cp::input_select_header('cat_group[]', ($query->count() > 0) ? 'y' : '');

        $selected = (empty($cat_groups)) ? 1 : '';

        $r .= Cp::input_select_option('', __('admin.none'), $selected);

        if ($query->count() > 0)
        {
            foreach ($query as $row)
            {
                $selected = (in_array($row->group_id, $cat_groups)) ? 1 : '';

                $r .= Cp::input_select_option($row->group_id, $row->group_name, $selected);
            }
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Status group select list
        $query = DB::table('status_groups')
            ->orderBy('group_name')
            ->select('group_id', 'group_name')
            ->get();

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.status_group')));

        $r .= Cp::td().
              Cp::input_select_header('status_group');

        $selected = '';

        $r .= Cp::input_select_option('', __('admin.none'), $selected);

        foreach ($query as $row)
        {
            $selected = ($status_group == $row->group_id) ? 1 : '';

            $r .= Cp::input_select_option($row->group_id, $row->group_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;


        // Field Groups
        $query = DB::table('field_groups')
            ->orderBy('group_name')
            ->select('group_id', 'group_name')
            ->get();

        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.custom_field_group')));

        $r .= Cp::td().
              Cp::input_select_header('field_group');

        $selected = '';

        $r .= Cp::input_select_option('', __('admin.none'), $selected);

        foreach ($query as $row) {
            $selected = ($field_group == $row->group_id) ? 1 : '';

            $r .= Cp::input_select_option($row->group_id, $row->group_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;


        $r .= '</table>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Default Values
        // ------------------------------------

        $r .= '<div id="defaults_on" style="display: none; padding:0; margin: 0;">';
        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' colspan='2'>";
        $r .= NBS.__('admin.default_settings').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;


        // Default status menu
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.default_status')), '50%').
              Cp::td('', '50%').
              Cp::input_select_header('default_status');

        $query = DB::table('statuses')
            ->where('group_id', $status_group)
            ->orderBy('status')
            ->get();

        foreach ($query as $row) {
            $selected = ($default_status == $row->status) ? 1 : '';

            $status_name =
                ($row->status == 'open' OR $row->status == 'closed') ?
                __($row->status) :
                $row->status;

            $r .= Cp::input_select_option($row->status, $status_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // Default category menu
        $r .= '<tr>'.PHP_EOL.
              Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.default_category')), '50%');

        $r .= Cp::td('', '50%').
              Cp::input_select_header('default_category');

        $selected = '';

        $r .= Cp::input_select_option('', __('admin.none'), $selected);

        $query = DB::table('categories')
            ->join('category_groups', 'category_groups.group_id', '=', 'categories.group_id')
            ->whereIn('categories.group_id', $cat_groups)
            ->select(
                'categories.category_id',
                'categories.category_name',
                'category_groups.group_name'
            )
            ->orderBy('category_groups.group_name')
            ->orderBy('categories.category_name')
            ->get();

        foreach ($query as $row)
        {
            $row->display_name = $row->group_name.': '.$row->category_name;

            $selected = ($default_category == $row->category_id) ? 1 : '';

            $r .= Cp::input_select_option($row->category_id, $row->display_name, $selected);
        }

        $r .= Cp::input_select_footer().
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;


        // ------------------------------------
        //  Layout Options
        // ------------------------------------

        $r .= '<div id="options_on" style="display: none; padding:0; margin: 0;">';
        $r .= Cp::table('tableBorder', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL;

        $r .= "<td class='tableHeadingAlt' colspan='2'>";
        $r .= NBS.__('admin.field_display_options').'</td>'.PHP_EOL;
        $r .= '</tr>'.PHP_EOL;

        // Live Look Template
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.live_look_template')))
             .Cp::td('', '50%')
             .Cp::input_select_header('live_look_template')
             .Cp::input_select_option('0', __('admin.no_live_look_template'), ($live_look_template == 0) ? '1' : 0);

        $tquery = DB::table('templates AS t')
            ->join('sites', 'sites.site_id', '=', 't.site_id')
            ->orderBy('t.template_name')
            ->select('t.folder', 't.template_id', 't.template_name', 'sites.site_name')
            ->get();

        foreach ($tquery as $template)
        {
            $r .= Cp::input_select_option(
                $template->template_id,
                $template->site_name.': '.$template->folder.'/'.$template->template_name,
                (($template->template_id == $live_look_template) ? 1 : ''));
        }

        $r .= Cp::input_select_footer()
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;


        // url_title_prefix
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.url_title_prefix')).'&nbsp;'.'-'.'&nbsp;'.__('admin.single_word_no_spaces_with_underscores'))
             .Cp::td('', '50%')
             .Cp::input_text('url_title_prefix', $url_title_prefix, '50', '255', 'input', '100%')
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        // show_url_title
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell(
                '',
                Cp::quickSpan(
                    'defaultBold',
                    __('admin.show_url_title').
                    '<div class="subtext">'.__('admin.show_url_title_blurb').'</div>'
                ),
                '50%')
             .Cp::td('', '50%');
        $r .= Cp::qlabel(__('admin.yes'))
             .Cp::input_radio('show_url_title', 'y', ($show_url_title == 'y') ? 1 : '').'&nbsp;';
        $r .= Cp::qlabel(__('admin.no'))
             .Cp::input_radio('show_url_title', 'n', ($show_url_title == 'n') ? 1 : '')
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        // show_categories_tab
        $r .= '<tr>'.PHP_EOL
             .Cp::tableCell('', Cp::quickSpan('defaultBold', __('admin.show_categories_tab')), '50%')
             .Cp::td('', '50%');
        $r .= Cp::qlabel(__('admin.yes'))
             .Cp::input_radio('show_categories_tab', 'y', ($show_categories_tab == 'y') ? 1 : '').'&nbsp;';
        $r .= Cp::qlabel(__('admin.no'))
             .Cp::input_radio('show_categories_tab', 'n', ($show_categories_tab == 'n') ? 1 : '')
             .'</td>'.PHP_EOL
             .'</tr>'.PHP_EOL;

        $r .= '</table>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        // Update Buttons
        // ------------------------------------

        $r .= Cp::div('littlePadding');
        $r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('cp.update')).NBS.Cp::input_submit(__('cp.update_and_return'),'return'));
        $r .= '</div>'.PHP_EOL.'</form>'.PHP_EOL;

        // ------------------------------------
        //  Finish up the Layout
        // ------------------------------------

        Cp::$body .= Cp::table('', '0', '', '100%');

        // Menu areas
        $areas = [
            "fields"   => "admin.fields",
            "defaults" => "admin.default_settings",
            "options"  => "admin.field_display_options",
        ];

        $menu = '';

        foreach($areas as $area => $area_lang) {
            $menu .= Cp::quickDiv(
                'navPad',
                '<span id="'.$area.'_pointer">&#8226; '.
                    Cp::anchor("#", __($area_lang), 'onclick="showHideMenu(\''.$area.'\');"').
                '</span>');
        }

        $first_text =   Cp::div('tableHeadingAlt')
                        .   $weblog_title
                        .'</div>'.PHP_EOL
                        .Cp::div('profileMenuInner')
                        .   $menu
                        .'</div>'.PHP_EOL;

        // Create the Table
        $table_row = [
            'first'     => ['valign' => "top", 'width' => "220px", 'text' => $first_text],
            'second'    => ['class' => "default", 'width'  => "8px"],
            'third'     => ['valign' => "top", 'text' => $r]
        ];

        Cp::$body .= Cp::tableRow($table_row).'</table>'.PHP_EOL;

        Cp::$title = __('admin.edit_fields');

        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=weblogsOverview', $weblog_title).
            Cp::breadcrumbItem(__('admin.edit_fields'));
    }

    // --------------------------------------------------------------------

    /**
    * Delete Weblog Confirm form
    *
    * @return string
    */
    public function deleteWeblogConfirm()
    {
        if ( ! $weblog_id = Request::input('weblog_id')) {
            return false;
        }

        $query = DB::table('weblogs')
            ->select('weblog_title')
            ->where('weblog_id', $weblog_id)
            ->first();

        Cp::$title = __('admin.delete_weblog');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=weblogsOverview', __('admin.weblog_administration')).
            Cp::breadcrumbItem(__('admin.delete_weblog'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'C=WeblogAdministration'.AMP.'M=delete_weblog'.AMP.'weblog_id='.$weblog_id,
                'heading'   => 'admin.delete_weblog',
                'message'   => 'admin.delete_weblog_confirmation',
                'item'      => $query->weblog_title,
                'extra'     => '',
                'hidden'    => ['weblog_id' => $weblog_id]
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete Weblog
    *
    * @return string
    */
    public function delete_weblog()
    {
        if ( ! $weblog_id = Request::input('weblog_id')) {
            return false;
        }

        $weblog_title = DB::table('weblogs')
            ->where('weblog_id', $weblog_id)
            ->value('weblog_title');

        if (empty($weblog_title)) {
            return false;
        }

        Cp::log(__('admin.weblog_deleted').NBS.$weblog_title);

        $query = DB::table('weblog_entries')
            ->where('weblog_id', $weblog_id)
            ->select('entry_id', 'author_id')
            ->get();

        $entries = [];
        $authors = [];

        if ($query->count() > 0)
        {
            foreach ($query as $row)
            {
                $entries[] = $row->entry_id;
                $authors[] = $row->author_id;
            }
        }

        $authors = array_unique($authors);

        DB::table('weblog_layout_tabs')->where('weblog_id', $weblog_id)->delete();
        DB::table('weblog_entry_data')->where('weblog_id', $weblog_id)->delete();
        DB::table('weblog_entries')->where('weblog_id', $weblog_id)->delete();
        DB::table('weblogs')->where('weblog_id', $weblog_id)->delete();


        // ------------------------------------
        //  Clear catagories
        // ------------------------------------

        if (!empty($entries)) {
            DB::table('weblog_entry_categories')->whereIn('entry_id', $entries)->delete();
        }

        // ------------------------------------
        //  Update author stats
        // ------------------------------------

        foreach ($authors as $author_id)
        {
            $total_entries = DB::table('weblog_entries')->where('author_id', $author_id)->count();

            DB::table('members')
                ->where('member_id', $author_id)
                ->update(['total_entries' => $total_entries]);
        }

        // ------------------------------------
        //  McFly, update the stats!
        // ------------------------------------

        Stats::update_weblog_stats();

        return $this->weblogsOverview(__('admin.weblog_deleted').NBS.'<b>'.$weblog_title.'</b>');
    }

//=====================================================================
//  Category Administration
//=====================================================================

    // --------------------------------------------------------------------

    /**
    * Category Overview page
    *
    * @param string $message
    * @return string
    */
    public function category_overview($message = '')
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        Cp::$title  = __('admin.categories');
        Cp::$crumb  = __('admin.categories');

        $right_links[] = [
            BASE.'?C=WeblogAdministration'.AMP.'M=edit_category_group_form',
            __('admin.create_new_category_group')
        ];

        $r = Cp::header(__('admin.categories'), $right_links);

        // Fetch category groups
        $query = DB::table('category_groups')
            ->orderBy('group_name')
            ->select('group_id', 'group_name')
            ->get();

        if ($query->count() == 0)
        {
            $r .= stripslashes($message);
            $r .= Cp::div('box');
            $r .= Cp::quickDiv('littlePadding', Cp::heading(__('admin.no_category_group_message'), 5));
            $r .= Cp::quickDiv('littlePadding',
                Cp::anchor(
                    BASE.'?C=WeblogAdministration'.AMP.'M=edit_category_group_form',
                    __('admin.create_new_category_group')));
            $r .= '</div>'.PHP_EOL;

            return Cp::$body = $r;
        }

        if ($message != '') {
            $r .= $message;
        }

        $i = 0;

        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading').'</td>'.PHP_EOL.
              Cp::td('tableHeading', '', '4').
              __('admin.category_groups').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        foreach($query as $row)
        {
            // It is not efficient to put this query in the loop.
            // Originally I did it with a join above, but there is a bug on OS X Server
            // that I couldn't find a work-around for.  So... query in the loop it is.
            $count = DB::table('categories')
                ->where('group_id', $row->group_id)
                ->count();


            $r .= '<tr>'.PHP_EOL.
                  Cp::td('', '5%').
                  Cp::quickSpan('defaultBold', $row->group_id).
                  '</td>'.PHP_EOL;

            $r .= Cp::tableCell('',
                  Cp::anchor(
                                BASE.'?C=WeblogAdministration'.AMP.'M=edit_category_group_form'.AMP.'group_id='.$row->group_id,
                                $row->group_name,
                                'class="defaultBold"'
                              ));

            $r .= Cp::tableCell('',
                  Cp::anchor(
                        BASE.'?C=WeblogAdministration'.AMP.'M=category_manager'.AMP.'group_id='.$row->group_id,
                        __('admin.add_edit_categories')
                    ).
                   ' ('.$count.')'
                );


            $r .= Cp::tableCell('',
                  Cp::anchor(
                        BASE.'?C=WeblogAdministration'.AMP.'M=delete_category_group_conf'.AMP.'group_id='.$row->group_id,
                        __('cp.delete'),
                        'class="delete-link"'
                    )).
                  '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;
        Cp::$body = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Edit Category Group form
    *
    * @return string
    */
    public function edit_category_group_form()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        // default values
        $edit       = false;
        $group_id   = '';
        $group_name = '';
        $can_edit   = [];
        $can_delete = [];

        // If we have the group_id variable, it's an edit request, so fetch the category data
        if ($group_id = Request::input('group_id')) {
            $edit = true;

            if ( ! is_numeric($group_id)) {
                return false;
            }

            $query = DB::table('category_groups')
                ->where('group_id', $group_id)
                ->first();

            if (empty($query)) {
                return $this->category_overview();
            }

            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        // ------------------------------------
        //  Opening Outpu
        // ------------------------------------

        $title = ($edit == false) ? __('admin.create_new_category_group') : __('admin.edit_category_group');

        // Build our output
        $r = Cp::formOpen(
            [
                'action' => 'C=WeblogAdministration'.AMP.'M=update_category_group'
            ]
        );

        if ($edit == true) {
            $r .= Cp::input_hidden('group_id', $group_id);
        }

        $r .= Cp::quickDiv('tableHeading', $title);

        $r .= Cp::div('box').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('admin.name_of_category_group'))).
              Cp::quickDiv('littlePadding', Cp::input_text('group_name', $group_name, '20', '50', 'input', '300px'));

        $r .= '</div>'.PHP_EOL; // main box

        $r .= Cp::div('paddingTop');

        if ($edit == FALSE)
            $r .= Cp::input_submit(__('cp.submit'));
        else
            $r .= Cp::input_submit(__('cp.update'));

        $r .= '</div>'.PHP_EOL;

        $r .= '</form>'.PHP_EOL;

        Cp::$title = $title;
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=category_overview', __('admin.category_groups')).
            Cp::breadcrumbItem($title);

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Update Category Group
    *
    * @return string
    */
    public function update_category_group()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        $edit = (bool) request()->filled('group_id');

        if (! request()->has('group_name')) {
            return $this->edit_category_group_form();
        }

        $group_id   = request()->input('group_id');
        $group_name = request()->input('group_name');

        // check for bad characters in group name

        if ( ! preg_match("#^[a-zA-Z0-9_\-/\s]+$#i", $group_name)) {
            return Cp::errorMessage(__('admin.illegal_characters'));
        }

        // Is the group name taken?
        $query = DB::table('category_groups')
            ->where('group_name', $group_name);

        if ($edit === true) {
            $query->where('group_id', '!=', $group_id);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('admin.taken_category_group_name'));
        }

        // Construct the query based on whether we are updating or inserting
        if ($edit === false)
        {
            $data['site_id'] = Site::config('site_id');

            DB::table('category_groups')->insert(['group_name' => $group_name]);

            $success_msg = __('admin.category_group_created');

            Cp::log(__('admin.category_group_created').$group_name);
        }
        else
        {
            DB::table('category_groups')
                ->where('group_id', $group_id)
                ->update(['group_name' => $group_name]);

            $success_msg = __('admin.category_group_updated');

            Cp::log(__('admin.category_group_updated').$group_name);
        }

        $message  = Cp::div('success-message');
        $message .= $success_msg.$group_name;

        if ($edit === false)
        {
            $query = DB::table('weblogs')
                ->select('weblog_id')
                ->get();

            if ($query->count() > 0)
            {
                $message .= Cp::quickDiv('littlePadding', Cp::quickDiv('alert', __('admin.assign_group_to_weblog')));

                if ($query->count() == 1)
                {
                    $link = 'C=WeblogAdministration'.AMP.'M=editWeblogFields'.AMP.'weblog_id='.$query->first()->weblog_id;
                }
                else
                {
                    $link = 'C=WeblogAdministration'.AMP.'M=weblogsOverview';
                }

                $message .= Cp::quickDiv('littlePadding', Cp::anchor(BASE.'?'.$link, __('admin.click_to_assign_group')));
            }
        }

        $message .= '</div>'.PHP_EOL;

        return $this->category_overview($message);
    }

    // --------------------------------------------------------------------

    /**
    * Delete Category Group confirmation form
    *
    * @return string
    */
    public function delete_category_group_conf()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($group_id = Request::input('group_id')) === FALSE OR ! is_numeric($group_id)) {
            return false;
        }

        $group_name = DB::table('category_groups')->where('group_id', $group_id)->value('group_name');

        if(empty($group_name)) {
            return false;
        }

        Cp::$title = __('admin.delete_group');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=category_overview', __('admin.category_groups')).
            Cp::breadcrumbItem(__('admin.delete_group'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'C=WeblogAdministration'.AMP.'M=delete_category_group'.AMP.'group_id='.$group_id,
                'heading'   => 'admin.delete_group',
                'message'   => 'admin.delete_cat_group_confirmation',
                'item'      => $group_name,
                'extra'     => '',
                'hidden'    => ['group_id' => $group_id]
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete Category Group (and all categories)
    *
    * @return string
    */
    public function delete_category_group()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($group_id = Request::input('group_id')) === false or ! is_numeric($group_id)) {
            return false;
        }

        $query = DB::table('category_groups')
            ->where('group_id', $group_id)
            ->select('group_id', 'group_name')
            ->first();

        if (!$query) {
            return false;
        }

        $name = $query->group_name;
        $group_id = $query->group_id;

        // ------------------------------------
        //  Delete from weblog_entry_categories
        // ------------------------------------

        $cat_ids = DB::table('categories')
            ->where('group_id', $group_id)
            ->pluck('category_id')
            ->all();

        if (!empty($cat_ids)) {
            DB::table('weblog_entry_categories')
                ->whereIn('category_id', $cat_ids)
                ->delete();
        }

        DB::table('category_groups')
            ->where('group_id', $group_id)
            ->delete();

        DB::table('categories')
            ->where('group_id', $group_id)
            ->delete();

        $message = Cp::quickDiv('success-message', __('admin.category_group_deleted').NBS.'<b>'.$name.'</b>');

        Cp::log(__('admin.category_group_deleted').'&nbsp;'.$name);

        cms_clear_caching('all');

        return $this->category_overview($message);
    }

    // --------------------------------------------------------------------

    /**
    * Build Category Tree for Display in Edit Categories page
    *
    * @param string $type
    * @param integer $group_id
    * @param integer $p_id
    * @param string $sort_order
    * @return string
    */
    public function category_tree($type = 'text', $group_id = '', $p_id = '', $sort_order = 'a')
    {
        // Fetch category group ID number
        if ($group_id == '') {
            if (($group_id = Request::input('group_id')) === FALSE) {
                return false;
            }
        }

        if ( ! is_numeric($group_id)) {
            return false;
        }

        // Fetch category groups
        $query = DB::table('categories')
            ->where('group_id', $group_id)
            ->select('category_id', 'category_name', 'parent_id')
            ->orderBy('parent_id')
            ->orderBy(($sort_order == 'a') ? 'category_name' : 'category_order')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        // Assign the query result to a multi-dimensional array
        foreach($query as $row) {
            $cat_array[$row->category_id]  = [$row->parent_id, $row->category_name];
        }

        if ($type == 'data')  {
            return $cat_array;
        }

        $up     = '<img src="'.PATH_CP_IMG.'arrow_up.png" border="0"  width="16" height="16" alt="" title="" />';
        $down   = '<img src="'.PATH_CP_IMG.'arrow_down.png" border="0"  width="16" height="16" alt="" title="" />';

        // Build our output...
        $can_delete = true;
        if (Request::input('Z') == 1)
        {
            if (Session::access('can_edit_categories'))
            {
                $can_delete = true;
            }
            else
            {
                $can_delete = false;
            }
        }


        $zurl  = (Request::input('Z') == 1) ? AMP.'Z=1' : '';
        $zurl .= (Request::input('cat_group') !== null) ? AMP.'cat_group='.Request::input('cat_group') : '';
        $zurl .= (Request::input('integrated') !== null) ? AMP.'integrated='.Request::input('integrated') : '';

        foreach($cat_array as $key => $val)
        {
            if (0 == $val[0])
            {
                if ($type == 'table')
                {
                    if ($can_delete == TRUE)
                        $delete = Cp::anchor(
                            BASE.'?C=WeblogAdministration'.
                                AMP.'M=delete_category_confirm'.
                                AMP.'category_id='.$key.
                                $zurl,
                            __('cp.delete'),
                            'class="delete-link"');
                    else {
                        $delete = __('cp.delete');
                    }

                    $this->categories[] =
                        Cp::tableQuickRow(
                            '',
                            [
                                $key,
                                Cp::anchor(
                                    BASE.'?C=WeblogAdministration'.
                                        AMP.'M=change_category_order'.
                                        AMP.'category_id='.$key.
                                        AMP.'group_id='.$group_id.
                                        AMP.'order=up'.$zurl,
                                    $up).
                                NBS.
                                Cp::anchor(
                                    BASE.'?C=WeblogAdministration'.
                                        AMP.'M=change_category_order'.
                                        AMP.'category_id='.$key.
                                        AMP.'group_id='.$group_id.
                                        AMP.'order=down'.$zurl,
                                    $down),
                                Cp::quickDiv('defaultBold', NBS.$val[1]),
                                Cp::anchor(
                                    BASE.'?C=WeblogAdministration'.
                                        AMP.'M=edit_category_form'.
                                        AMP.'category_id='.$key.
                                        AMP.'group_id='.$group_id.$zurl,
                                    __('cp.edit')),
                                $delete
                            ]
                        );
                }
                else
                {
                    $this->categories[] = Cp::input_select_option($key, $val[1], ($key == $p_id) ? '1' : '');
                }

                $this->category_subtree($key, $cat_array, $group_id, $depth=0, $type, $p_id);

            }
        }
    }

    // --------------------------------------------------------------------

    /**
    * Helps build Category Tree for Display in Edit Categories page
    *
    * @param integer $cat_id
    * @param array $cat_array
    * @param integer $group_id
    * @param integer $depth
    * @param string $type
    * @param integer $p_id
    * @return string
    */
    public function category_subtree($cat_id, $cat_array, $group_id, $depth, $type, $p_id)
    {
        if ($type == 'table')
        {
            $spcr = '<span style="display:inline-block; margin-left:10px;"></span>';
            $indent = $spcr.'<img src="'.PATH_CP_IMG.'category_indent.png" border="0" width="12" height="12" title="indent" style="vertical-align:top; display:inline-block;"  />';
        }
        else
        {
            $spcr = '&nbsp;';
            $indent = $spcr.$spcr.$spcr.$spcr;
        }

        $up   = '<img src="'.PATH_CP_IMG.'arrow_up.png" border="0"  width="16" height="16" alt="" title="" />';
        $down = '<img src="'.PATH_CP_IMG.'arrow_down.png" border="0"  width="16" height="16" alt="" title="" />';


        if ($depth == 0)
        {
            $depth = 1;
        }
        else
        {
            $indent = str_repeat($spcr, $depth+1).$indent;
            $depth = ($type == 'table') ? $depth + 1 : $depth + 4;
        }

        $can_delete = true;
        if (Request::input('Z') == 1)
        {
            if (Session::access('can_edit_categories'))
            {
                $can_delete = true;
            }
            else
            {
                $can_delete = false;
            }
        }
        $zurl = (Request::input('Z') == 1) ? AMP.'Z=1' : '';
        $zurl .= (Request::input('cat_group') !== null) ? AMP.'cat_group='.Request::input('cat_group') : '';
        $zurl .= (Request::input('integrated') !== null) ? AMP.'integrated='.Request::input('integrated') : '';

        foreach ($cat_array as $key => $val)
        {
            if ($cat_id == $val[0])
            {
                $pre = ($depth > 2) ? "&nbsp;" : '';

                if ($type == 'table')
                {
                    if ($can_delete == true)
                        $delete = Cp::anchor(
                            BASE.'?C=WeblogAdministration'.
                                AMP.'M=delete_category_confirm'.
                                AMP.'category_id='.$key.$zurl,
                            __('cp.delete'),
                            'class="delete-link"');
                    else {
                        $delete = __('cp.delete');
                    }

                    $this->categories[] =

                    Cp::tableQuickRow(
                        '',
                        [
                            $key,
                            Cp::anchor(
                                BASE.'?C=WeblogAdministration'.
                                    AMP.'M=change_category_order'.
                                    AMP.'category_id='.$key.
                                    AMP.'group_id='.$group_id.
                                    AMP.'order=up'.$zurl,
                                $up).
                        NBS.
                            Cp::anchor(
                                BASE.'?C=WeblogAdministration'.
                                    AMP.'M=change_category_order'.
                                    AMP.'category_id='.$key.
                                    AMP.'group_id='.$group_id.
                                    AMP.'order=down'.$zurl,
                                $down),
                            Cp::quickDiv('defaultBold', $pre.$indent.NBS.$val[1]),
                            Cp::anchor(
                                BASE.'?C=WeblogAdministration'.
                                    AMP.'M=edit_category_form'.
                                    AMP.'category_id='.$key.
                                    AMP.'group_id='.$group_id.$zurl,
                                __('cp.edit')),
                            $delete
                        ]
                    );
                }
                else
                {
                    $this->categories[] = Cp::input_select_option($key, $pre.$indent.NBS.$val[1], ($key == $p_id) ? '1' : '');
                }

                $this->category_subtree($key, $cat_array, $group_id, $depth, $type, $p_id);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
    * Change order of categories form
    *
    * @return string
    */
    public function change_category_order()
    {
        if (! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        // Fetch required globals
        foreach (['category_id', 'group_id', 'order'] as $val)
        {
            if ( ! isset($_GET[$val])) {
                return false;
            }

            $$val = $_GET[$val];
        }

        $zurl  = (Request::input('Z') == 1) ? '&Z=1' : '';
        $zurl .= (Request::input('cat_group') !== null) ? '&cat_group='.Request::input('cat_group') : '';
        $zurl .= (Request::input('integrated') !== null) ? '&integrated='.Request::input('integrated') : '';

        // Return Location
        $return = '?C=WeblogAdministration&M=category_manager&group_id='.$group_id.$zurl;

        // Fetch the parent ID
        $parent_id = DB::table('categories')
            ->where('category_id', $category_id)
            ->value('parent_id');

        // Is the requested category already at the beginning/end of the list?

        $dir = ($order == 'up') ? 'asc' : 'desc';

        $query = DB::table('categories')
            ->select('category_id')
            ->where('group_id', $group_id)
            ->where('parent_id', $parent_id)
            ->orderBy('category_order', $dir)
            ->first();

        if ($query->category_id == $category_id) {
            return redirect($return);
        }

        // Fetch all the categories in the parent
        $query = DB::table('categories')
            ->select('category_id', 'category_order')
            ->where('group_id', $group_id)
            ->where('parent_id', $parent_id)
            ->orderBy('category_order', 'asc')
            ->get();

        // If there is only one category, there is nothing to re-order
        if ($query->count() <= 1) {
            return redirect($return);
        }

        // Assign category ID numbers in an array except the category being shifted.
        // We will also set the position number of the category being shifted, which
        // we'll use in array_shift()

        $flag   = '';
        $i      = 1;
        $cats   = [];

        foreach ($query as $row)
        {
            if ($category_id == $row->category_id)
            {
                $flag = ($order == 'down') ? $i+1 : $i-1;
            }
            else
            {
                $cats[] = $row->category_id;
            }

            $i++;
        }

        array_splice($cats, ($flag -1), 0, $category_id);

        // Update the category order for all the categories within the given parent
        $i = 1;

        foreach ($cats as $val) {
            DB::table('categories')
                ->where('category_id', $val)
                ->update(['category_order' => $i]);

            $i++;
        }

        // Switch to custom order
        DB::table('category_groups')
            ->where('group_id', $group_id)
            ->update(['sort_order' => 'c']);

        return redirect($return);
    }

    // --------------------------------------------------------------------

    /**
    * List Categories for a Category Group
    *
    * @param integer $group_id
    * @param bool $update
    * @return string
    */
    public function category_manager($group_id = '', $update = false)
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if ($group_id == '') {
            if (($group_id = Request::input('group_id')) === false OR ! is_numeric($group_id)) {
                return false;
            }
        }

        $zurl  = (Request::input('Z') == 1) ? AMP.'Z=1' : '';
        $zurl .= (Request::input('cat_group') !== null) ? AMP.'cat_group='.Request::input('cat_group') : '';
        $zurl .= (Request::input('integrated') !== null) ? AMP.'integrated='.Request::input('integrated') : '';

        $query = DB::table('category_groups')
            ->where('group_id', $group_id)
            ->select('group_name', 'sort_order')
            ->first();

        $group_name = $query->group_name;
        $sort_order = $query->sort_order;

        $r = '';
        $r .= Cp::quickDiv('tableHeading', $group_name);

        if ($update != FALSE)
        {
            $r .= Cp::quickDiv('success-message', __('admin.category_updated'));
        }

        // Fetch the category tree

        $this->category_tree('table', $group_id, '', $sort_order);

        if (count($this->categories) == 0)
        {
            $r .= Cp::quickDiv('box', Cp::quickDiv('highlight', __('admin.no_category_message')));
        }
        else
        {
            $r .= Cp::table('tableBorder', '0', '0').
                  '<tr>'.PHP_EOL.
                  Cp::tableCell('tableHeadingAlt', 'ID', '2%').
                  Cp::tableCell('tableHeadingAlt', __('admin.order'), '8%').
                  Cp::tableCell('tableHeadingAlt', __('admin.category_name'), '50%').
                  Cp::tableCell('tableHeadingAlt', __('cp.edit'), '20%').
                  Cp::tableCell('tableHeadingAlt', __('cp.delete'), '20%');
            $r .= '</tr>'.PHP_EOL;

            foreach ($this->categories as $val)
            {
                $prefix = (strlen($val[0]) == 1) ? NBS : NBS;
                $r .= $val;
            }

            $r .= '</table>'.PHP_EOL;

            $r .= Cp::quickDiv('defaultSmall', '');

            // Category order

            if (Request::input('Z') == null)
            {
                $r .= Cp::formOpen(
                    [
                        'action' => 'C=WeblogAdministration'.AMP.'M=global_category_order'.AMP.'group_id='.$group_id.$zurl
                    ]
                );

                $r .= Cp::div('box box320');
                $r .= Cp::quickDiv('defaultBold', __('admin.global_sort_order'));
                $r .= Cp::div('littlePadding');
                $r .= '<label>'.Cp::input_radio('sort_order', 'a', ($sort_order == 'a') ? 1 : '').__('admin.alpha').'</label>';
                $r .= NBS.NBS.'<label>'.Cp::input_radio('sort_order', 'c', ($sort_order != 'a') ? 1 : '').__('admin.custom').'</label>';
                $r .= NBS.NBS.Cp::input_submit(__('cp.update'));
                $r .= '</div>'.PHP_EOL;
                $r .= '</div>'.PHP_EOL;
                $r .= '</form>'.PHP_EOL;
            }
        }

        // Build category tree for javascript replacement

        if (Request::input('Z') == 1)
        {
            $PUB = new Publish;
            $PUB->category_tree(
                (Request::input('cat_group') !== null) ? Request::input('cat_group') : Request::input('group_id'),
                '',
                '',
                (Request::input('integrated') == 'y') ? 'y' : 'n');

            $cm = "";
            foreach ($PUB->categories as $val)
            {
                $cm .= $val;
            }
            $cm = addslashes(preg_replace("/(\r\n)|(\r)|(\n)/", '', $cm));

            Cp::$extra_header = <<<EOT
            <script type="text/javascript">

                $( document ).ready(function() {
				 	$('#update_publish_cats').click(function(e) {
				 		e.preventDefault();

						opener.swap_categories("{$cm}");
						window.close();
				 	});
				});

            </script>
EOT;

            $r .= '<form>';
            $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('defaultCenter', '<input type="submit" id="update_publish_cats" value="'.NBS.__('admin.update_publish_cats').NBS.'"/>'  ));
            $r .= '</form>';
        }


       // Assign output data

        Cp::$title = __('admin.categories');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=category_overview', __('admin.category_groups')).
            Cp::breadcrumbItem(__('admin.categories'));


        $right_links[] = [
            BASE.'?C=WeblogAdministration'.AMP.'M=edit_category_form'.AMP.'group_id='.$group_id,
            __('admin.new_category')
        ];

        $r = Cp::header(__('admin.categories'), $right_links).$r;

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Set a Category Order
    *
    * @return string
    */
    public function global_category_order()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($group_id = Request::input('group_id')) === FALSE OR ! is_numeric($group_id))
        {
            return false;
        }

        $order = (Request::input('sort_order') == 'a') ? 'a' : 'c';

        $query = DB::table('sort_order')->select('sort_order')->where('group_id', $group_id);

        if ($order == 'a')
        {
            if (Request::input('override')) {
                return $this->global_category_order_confirm();
            }

            $this->reorder_cats_alphabetically();
        }

        DB::table('category_groups')
            ->where('group_id', $group_id)
            ->update(['sort_order' => $order]);

        return redirect('?C=WeblogAdministration&M=category_manager&group_id='.$group_id);
    }

    // --------------------------------------------------------------------

    /**
    * Confirmation form for a category order change
    *
    * @return string
    */
    public function global_category_order_confirm()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($group_id = Request::input('group_id')) === FALSE OR ! is_numeric($group_id))
        {
            return false;
        }

        Cp::$title = __('admin.global_sort_order');
        Cp::$crumb =
            Cp::anchor(
                BASE.'?C=WeblogAdministration'.
                    AMP.'M=category_overview',
                __('admin.category_groups')
            ).
            Cp::breadcrumbItem(
                Cp::anchor(
                    BASE.'?C=WeblogAdministration'.
                        AMP.'M=category_manager'.
                        AMP.'group_id='.$group_id,
                    __('admin.categories')
                )
            ).
            Cp::breadcrumbItem(__('admin.global_sort_order'));

        Cp::$body = Cp::formOpen(['action' => 'C=WeblogAdministration'.AMP.'M=global_category_order'.AMP.'group_id='.$group_id])
                    .Cp::input_hidden('sort_order', Request::input('sort_order'))
                    .Cp::input_hidden('override', 1)
                    .Cp::quickDiv('tableHeading', __('admin.global_sort_order'))
                    .Cp::div('box')
                    .Cp::quickDiv('defaultBold', __('admin.category_order_confirm_text'))
                    .Cp::quickDiv('alert', BR.__('admin.category_sort_warning').BR.BR)
                    .'</div>'.PHP_EOL
                    .Cp::quickDiv('littlePadding', Cp::input_submit(__('cp.update')))
                    .'</form>'.PHP_EOL;
    }

    // --------------------------------------------------------------------

    /**
    * Reorder a Category Group's categories alphabetically
    *
    * @return boolean
    */
    public function reorder_cats_alphabetically()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($group_id = Request::input('group_id')) === FALSE OR ! is_numeric($group_id))
        {
            return false;
        }

        $data = $this->process_category_group($group_id);

        if (count($data) == 0) {
            return false;
        }

        foreach($data as $cat_id => $cat_data)
        {
            DB::table('categories')
                ->where('category_id', $cat_id)
                ->update(['category_order' => $cat_data[1]]);
        }

        return true;
    }

    // --------------------------------------------------------------------

    /**
    * Process category group for alphabetically ordering
    *
    * @param integer $group_id
    * @return array
    */
    public function process_category_group($group_id)
    {
        $query = DB::table('categories')
            ->where('group_id', $group_id)
            ->orderBy('parent_id')
            ->orderBy('category_name')
            ->select('category_name', 'category_id', 'parent_id')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        foreach($query as $row) {
            $this->cat_update[$row->category_id]  = [$row->parent_id, '1', $row->category_name];
        }

        $order = 0;

        foreach($this->cat_update as $key => $val)
        {
            if (0 == $val[0])
            {
                $order++;
                $this->cat_update[$key][1] = $order;
                $this->process_subcategories($key);
            }
        }

        return $this->cat_update;
    }

    // --------------------------------------------------------------------

    /**
    * Process subcategories of category group for alphabetically ordering
    *
    * @param integer $parent_id
    * @return array
    */
    public function process_subcategories($parent_id)
    {
        $order = 0;

        foreach($this->cat_update as $key => $val) {
            if ($parent_id == $val[0]) {
                $order++;
                $this->cat_update[$key][1] = $order;
                $this->process_subcategories($key);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
    * Create/Edit Category Form
    *
    * @return string
    */
    public function edit_category_form()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($group_id = Request::input('group_id')) === null OR ! is_numeric($group_id)) {
            return Cp::unauthorizedAccess();
        }

        $cat_id = Request::input('category_id');

        // Get the category sort order for the parent select field later on

        $sort_order = DB::table('category_groups')
            ->where('group_id', $group_id)
            ->value('sort_order');

        $default = ['category_name', 'category_url_title', 'category_description', 'category_image', 'category_id', 'parent_id'];

        if ($cat_id)
        {
            $query = DB::table('categories')
                ->where('category_id', $cat_id)
                ->select(
                    'category_id',
                    'category_name',
                    'category_url_title',
                    'category_description',
                    'category_image',
                    'group_id',
                    'parent_id')
                ->first();

            if (!$query) {
                return Cp::unauthorizedAccess();
            }

            foreach ($default as $val) {
                $$val = $query->$val;
            }
        }
        else
        {
            foreach ($default as $val) {
                $$val = '';
            }
        }

        // Build our output

        $title = ( ! $cat_id) ? 'new_category' : 'edit_category';

        $zurl  = (Request::input('Z') == 1) ? AMP.'Z=1' : '';
        $zurl .= (Request::input('cat_group') !== null) ? AMP.'cat_group='.Request::input('cat_group') : '';
        $zurl .= (Request::input('integrated') !== null) ? AMP.'integrated='.Request::input('integrated') : '';

        Cp::$title = __($title);

        Cp::$crumb =
            Cp::anchor( BASE.'?C=WeblogAdministration'.AMP.'M=category_overview', __('admin.category_groups')).
            Cp::breadcrumbItem(Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=category_manager'.AMP.'group_id='.$group_id, __('admin.categories'))).
            Cp::breadcrumbItem(__($title));

        $word_separator = Site::config('word_separator') != "dash" ? '_' : '-';

        // ------------------------------------
        //  Create Foreign Character Conversion JS
        // ------------------------------------

        $r = url_title_javascript($word_separator);

        $r .= Cp::quickDiv('tableHeading', __($title));

        $r .= Cp::formOpen(
            [
                'id'     => 'category_form',
                'action' => 'C=WeblogAdministration'.AMP.'M=update_category'.$zurl
            ]
        ).
        Cp::input_hidden('group_id', $group_id);

        if ($cat_id) {
            $r .= Cp::input_hidden('category_id', $cat_id);
        }

        $r .= Cp::div('box');
        $r .= Cp::div('littlePadding').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', Cp::required().NBS.__('admin.category_name'))).
              Cp::input_text(
                    'category_name',
                    $category_name,
                    '20',
                    '100',
                    'input',
                    '400px',
                    ((!$cat_id) ? 'onkeyup="liveUrlTitle(\'#category_name\', \'#category_url_title\');"' : ''),
                    true
                ).
                '</div>'.PHP_EOL;

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('admin.category_url_title'))).
              Cp::input_text('category_url_title', $category_url_title, '20', '75', 'input', '400px', '', TRUE).
              '</div>'.PHP_EOL;

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('admin.category_description'))).
              Cp::input_textarea('category_description', $category_description, 4, 'textarea', '400px').
              '</div>'.PHP_EOL;

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('defaultBold', __('admin.category_image')).
              Cp::quickDiv('littlePadding', Cp::quickDiv('', __('admin.category_img_blurb'))).
              Cp::input_text('category_image', $category_image, '40', '120', 'input', '400px').
              '</div>'.PHP_EOL;

        $r .= Cp::div('littlePadding').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('admin.category_parent'))).
              Cp::input_select_header('parent_id').
              Cp::input_select_option('0', __('admin.none'));

        $this->category_tree('list', $group_id, $parent_id, $sort_order);

        foreach ($this->categories as $val)
        {
            $prefix = (strlen($val[0]) == 1) ? NBS : NBS;
            $r .= $val;
        }

        $r .= Cp::input_select_footer().
              '</div>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Submit Button
        // ------------------------------------

        $r .= Cp::div('paddingTop');
        $r .= ( ! $cat_id) ? Cp::input_submit(__('cp.submit')) : Cp::input_submit(__('cp.update'));
        $r .= '</div>'.PHP_EOL;

        $r .= '</form>'.PHP_EOL;

        Cp::$body = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Update Category
    *
    * @return string
    */
    public function update_category()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if (($group_id = Request::input('group_id')) === null or ! is_numeric($group_id)) {
            return Cp::unauthorizedAccess();
        }

        $edit = Request::filled('category_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'category_url_title' => 'regex:/^[\pL\pM\pN_]+$/u',
            'category_name'      => 'required',
            'parent_id'          => 'numeric',
            'group_id'           => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only(
            [
                'category_name',
                'category_url_title',
                'category_description',
                'category_image',
                'parent_id',
                'group_id',
                'category_id'
            ]
        );

        if(empty($data['category_description'])) {
            $data['category_description'] = '';
        }

        if(empty($data['parent_id'])) {
            $data['parent_id'] = 0;
        }

        // ------------------------------------
        //  Create Category URL Title
        // ------------------------------------

        if (empty($data['category_url_title'])) {
            $data['category_url_title'] = create_url_title($data['category_name'], true);

            // Integer? Not allowed, so we show an error.
            if (is_numeric($data['category_url_title'])) {
                return Cp::errorMessage(__('admin.category_url_title_is_numeric'));
            }

            if (trim($data['category_url_title']) == '') {
                return Cp::errorMessage(__('admin.unable_to_create_category_url_title'));
            }
        }

        // ------------------------------------
        //  Cat URL Title must be unique within the group
        // ------------------------------------

        $query = DB::table('categories')
            ->where('category_url_title', $data['category_url_title'])
            ->where('group_id', $group_id);

        if ($edit === true) {
            $query->where('category_id', '!=', $data['category_id']);
        }

        $query = $query->get();

        if ($query->count() > 0) {
            return Cp::errorMessage(__('admin.duplicate_category_url_title'));
        }

        // ------------------------------------
        //  Finish data prep for insertion
        // ------------------------------------

        $data['category_name'] = str_replace(['<','>'], ['&lt;','&gt;'], $data['category_name']);

        // ------------------------------------
        //  Insert
        // ------------------------------------

        if ($edit == FALSE)
        {
            $data['category_order'] = 0; // Temp
            $field_cat_id = DB::table('categories')->insertGetId($data);

            $update = false;

            // ------------------------------------
            //  Re-order categories
            // ------------------------------------

            // When a new category is inserted we need to assign it an order.
            // Since the list of categories might have a custom order, all we
            // can really do is position the new category alphabetically.

            // First we'll fetch all the categories alphabetically and assign
            // the position of our new category
            $query = DB::table('categories')
                ->where('group_id', $group_id)
                ->where('parent_id', $data['parent_id'])
                ->orderBy('category_name', 'asc')
                ->select('category_id', 'category_name')
                ->get();

            $position = 0;
            $cat_id = '';

            foreach ($query as $row) {
                if ($data['category_name'] == $row->category_name) {
                    $cat_id = $row->category_id;
                    break;
                }

                $position++;
            }

            // Next we'll fetch the list of categories ordered by the custom order
            // and create an array with the category ID numbers
            $cat_array = DB::table('categories')
                ->where('group_id', $group_id)
                ->where('parent_id', $data['parent_id'])
                ->where('category_id', '!=', $cat_id)
                ->orderBy('category_order')
                ->pluck('category_id')
                ->all();

            // Now we'll splice in our new category to the array.
            // Thus, we now have an array in the proper order, with the new
            // category added in alphabetically

            array_splice($cat_array, $position, 0, $cat_id);

            // Lastly, update the whole list

            $i = 1;
            foreach ($cat_array as $val)
            {
                DB::table('categories')
                    ->where('category_id', $val)
                    ->update(['category_order' => $i]);

                $i++;
            }
        }
        else
        {

            if ($data['category_id'] == $data['parent_id']) {
                $data['parent_id'] = 0;
            }

            // ------------------------------------
            //  Check for parent becoming child of its child...oy!
            // ------------------------------------

            $query = DB::table('categories')
                ->where('category_id', Request::input('category_id'))
                ->select('parent_id', 'group_id')
                ->first();

            if (Request::input('parent_id') !== 0 && $query && $query->parent_id !== Request::input('parent_id'))
            {
                $children  = [];
                $cat_array = $this->category_tree('data', $query->group_id);

                foreach($cat_array as $key => $values)
                {
                    if ($values['0'] == Request::input('category_id'))
                    {
                        $children[] = $key;
                    }
                }

                if (sizeof($children) > 0)
                {
                    if (($key = array_search(Request::input('parent_id'), $children)) !== FALSE)
                    {
                        DB::table('categories')
                            ->where('category_id', $children[$key])
                            ->update(['parent_id' => $query->parent_id]);
                    }
                    // ------------------------------------
                    //  Find All Descendants
                    // ------------------------------------
                    else
                    {
                        while(sizeof($children) > 0)
                        {
                            $now = array_shift($children);

                            foreach($cat_array as $key => $values)
                            {
                                if ($values[0] == $now)
                                {
                                    if ($key == Request::input('parent_id'))
                                    {
                                        DB::table('categories')
                                            ->where('category_id', $key)
                                            ->update(['parent_id' => $query->parent_id]);
                                        break 2;
                                    }

                                    $children[] = $key;
                                }
                            }
                        }
                    }
                }
            }

            DB::table('categories')
                ->where('category_id', Request::input('category_id'))
                ->where('group_id', Request::input('group_id'))
                ->update(
                    [
                        'category_name'         => Request::input('category_name'),
                        'category_url_title'    => Request::input('category_url_title'),
                        'category_description'  => Request::input('category_description'),
                        'category_image'        => Request::input('category_image'),
                        'parent_id'             => Request::input('parent_id')
                    ]
                );

            $update = true;

            // need this later for custom fields
            $field_cat_id = Request::input('category_id');
        }

        return $this->category_manager($group_id, $update);
    }

    // --------------------------------------------------------------------

    /**
    * Delete Category confirmation form
    *
    * @return string
    */
    public function delete_category_confirm()
    {
        if ( ! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $cat_id = Request::input('category_id')) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('categories')
            ->where('category_id', $cat_id)
            ->select('category_name', 'group_id')
            ->first();

        if (!$query) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Check privileges
        // ------------------------------------

        if (Request::input('Z') == 1 and Session::userdata('group_id') != 1 and ! Session::access('can_edit_categories'))
        {
            return Cp::unauthorizedAccess();
        }

        Cp::$title = __('admin.delete_category');

        Cp::$crumb =
            Cp::anchor( BASE.'?C=WeblogAdministration'.AMP.'M=category_overview', __('admin.category_groups')).
            Cp::breadcrumbItem(Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=category_manager'.AMP.'group_id='.$query->group_id, __('admin.categories'))).
            Cp::breadcrumbItem(__('admin.delete_category'));

        $zurl = (Request::input('Z') == 1) ? AMP.'Z=1' : '';
        $zurl .= (Request::input('cat_group') !== null) ? AMP.'cat_group='.Request::input('cat_group') : '';
        $zurl .= (Request::input('integrated') !== null) ? AMP.'integrated='.Request::input('integrated') : '';

        Cp::$body = Cp::deleteConfirmation(
            [
                'url' => 'C=WeblogAdministration'.
                    AMP.'M=delete_category'.
                    AMP.'group_id='.$query->group_id.
                    AMP.'category_id='.$cat_id.
                    $zurl,
                'heading'   => 'admin.delete_category',
                'message'   => 'admin.delete_category_confirmation',
                'item'      => $query->category_name,
                'extra'     => '',
                'hidden'    => ''
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete Category
    *
    * @return string
    */
    public function delete_category()
    {
        if (! Session::access('can_edit_categories')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! $cat_id = Request::input('category_id')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! is_numeric($cat_id)) {
            return Cp::unauthorizedAccess();
        }

        $query = DB::table('categories')
            ->select('group_id')
            ->where('category_id', $cat_id)
            ->first();

        if (!$query) {
            return Cp::unauthorizedAccess();
        }

        $group_id = $query->group_id;

        DB::table('weblog_entry_categories')->where('category_id', $cat_id)->delete();
        DB::table('categories')->where('parent_id', $cat_id)->where('group_id', $group_id)->update(['parent_id' => 0]);
        DB::table('categories')->where('category_id', $cat_id)->where('group_id', $group_id)->delete();

        $this->category_manager($group_id);
    }

//=====================================================================
//  Status Functions
//=====================================================================

    // --------------------------------------------------------------------

    /**
    * Status Groups Listing
    *
    * @param string $message
    * @return string
    */
    public function status_overview($message = '')
    {
        Cp::$title  = __('admin.status_groups');
        Cp::$crumb  = __('admin.status_groups');

        $right_links[] = [
            BASE.'?C=WeblogAdministration'.AMP.'M=edit_status_group_form',
            __('admin.create_new_status_group')
        ];

        $r = Cp::header(__('admin.status_groups'), $right_links);

        // Fetch category groups
        $query = DB::table('status_groups')
            ->groupBy('status_groups.group_id')
            ->orderBy('status_groups.group_name')
            ->select(
                'status_groups.group_id',
                'status_groups.group_name'
            )->get();

        if ($query->count() == 0)
        {
            if ($message != '') {
                Cp::$body .= Cp::quickDiv('success-message', $message);
            }

            $r .= Cp::div('box');
            $r .= Cp::quickDiv('littlePadding', Cp::heading(__('admin.no_status_group_message'), 5));
            $r .= Cp::quickDiv('littlePadding', Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=edit_status_group_form', __('admin.create_new_status_group')));
            $r .= '</div>'.PHP_EOL;

            return Cp::$body = $r;
        }

        if ($message != '') {
            $r .= Cp::quickDiv('success-message', $message);
        }

        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '4').
              __('admin.status_groups').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;


        $i = 0;

        foreach($query as $row)
        {

            $r .= '<tr>'.PHP_EOL;

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    BASE.'?C=WeblogAdministration'.AMP.'M=edit_status_group_form'.AMP.'group_id='.$row->group_id,
                    $row->group_name,
                    'class="defaultBold"'
                )
            );

            $field_count = $query = DB::table('statuses')
                ->where('statuses.group_id', $row->group_id)
                ->count();

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    BASE.'?C=WeblogAdministration'.AMP.'M=status_manager'.AMP.'group_id='.$row->group_id,
                    __('admin.add_edit_statuses').
                     ' ('.$field_count.')'
                )
            );


            $r .= Cp::tableCell('',
                Cp::anchor(
                    BASE.'?C=WeblogAdministration'.AMP.'M=delete_status_group_conf'.AMP.'group_id='.$row->group_id,
                    __('cp.delete'),
                    'class="delete-link"'
                )
            );

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Edit Status Group form
    *
    * @return string
    */
    public function edit_status_group_form()
    {
        // Set default values
        $edit       = false;
        $group_id   = '';
        $group_name = '';

        // If we have the group_id variable it's an edit request, so fetch the status data

        if ($group_id = Request::input('group_id'))
        {
            $edit = true;

            if ( ! is_numeric($group_id))
            {
                return false;
            }

            $query = DB::table('status_groups')
                ->where('group_id', $group_id)
                ->first();

            foreach ($query as $key => $val)
            {
                $$key = $val;
            }
        }


        if ($edit == false) {
            $title = __('admin.create_new_status_group');
        }
        else {
            $title = __('admin.edit_status_group');
        }

        // Build our output

        $r  = Cp::formOpen(['action' => 'C=WeblogAdministration'.AMP.'M=update_status_group']);

        if ($edit == true) {
            $r .= Cp::input_hidden('group_id', $group_id);
        }


        $r .= Cp::quickDiv('tableHeading', $title);

        $r .= Cp::div('box').
              Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('admin.name_of_status_group'))).
              Cp::quickDiv('littlePadding', Cp::input_text('group_name', $group_name, '20', '50', 'input', '260px'));

        $r .= '</div>'.PHP_EOL;

        $r .= Cp::div('paddingTop');
        if ($edit == FALSE)
            $r .= Cp::input_submit(__('cp.submit'));
        else
            $r .= Cp::input_submit(__('cp.update'));

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        Cp::$title = $title;
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=status_overview', __('admin.status_groups')).
            Cp::breadcrumbItem($title);

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Update Status Group
    *
    * @return string
    */
    public function update_status_group()
    {
        $edit = Request::filled('group_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'group_name'      => 'required|regex:#^[a-zA-Z0-9_\-/\s]+$#i',
            'group_id'        => 'integer'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only(
            [
                'group_id',
                'group_name'
            ]
        );

        // Group Name taken?
        $query = DB::table('status_groups')
            ->where('group_name', $data['group_name']);

        if ($edit === true) {
            $query->where('group_id', '!=', $data['group_id']);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('admin.taken_status_group_name'));
        }

        // ------------------------------------
        //  Insert/Update
        // ------------------------------------

        if ($edit == false)
        {
            $group_id = DB::table('status_groups')->insertGetId($data);

            // Add open/closed by default!
            DB::table('statuses')
                ->insert(
                    [
                        [
                            'group_id'      => $group_id,
                            'status'        => 'open',
                            'status_order'  => 1
                        ],
                        [
                            'group_id'      => $group_id,
                            'status'        => 'closed',
                            'status_order'  => 2
                        ]
                    ]);

            $success_msg = __('admin.status_group_created');

            Cp::log(__('admin.status_group_created').'&nbsp;'.$data['group_name']);
        }
        else
        {
            DB::table('status_groups')
                ->where('group_id', $data['group_id'])
                ->update($data);

            $success_msg = __('admin.status_group_updated');
        }

        $message = $success_msg.$data['group_name'];

        if ($edit === false)
        {
            $query = DB::table('weblogs')
                ->select('weblog_id')
                ->get();

            if ($query->count() > 0)
            {
                $message .= Cp::div('littlePadding').Cp::span('alert').__('admin.assign_group_to_weblog').'</span>'.PHP_EOL.'&nbsp;';

                if ($query->count() == 1)
                {
                    $link = 'C=WeblogAdministration'.AMP.'M=editWeblogFields'.AMP.'weblog_id='.$query->first()->weblog_id;
                }
                else
                {
                    $link = 'C=WeblogAdministration'.AMP.'M=weblogsOverview';
                }

                $message .= Cp::anchor(BASE.'?'.$link, __('admin.click_to_assign_group')).'</div>'.PHP_EOL;
            }
        }

        return $this->status_overview($message);
    }

    // --------------------------------------------------------------------

    /**
    * Delete Status Group Confirmation form
    *
    * @return string
    */
    public function delete_status_group_conf()
    {
        if (($group_id = Request::input('group_id')) === FALSE OR ! is_numeric($group_id)) {
            return false;
        }

        $group_name = DB::table('status_groups')->where('group_id', $group_id)->value('group_name');

        if (empty($group_name)) {
            return false;
        }

        Cp::$title = __('admin.delete_group');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=status_overview', __('admin.status_groups')).
            Cp::breadcrumbItem(__('admin.delete_group'));


        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'C=WeblogAdministration'.AMP.'M=delete_status_group'.AMP.'group_id='.$group_id,
                'heading'   => 'admin.delete_group',
                'message'   => 'admin.delete_status_group_confirmation',
                'item'      => $group_name,
                'extra'     => '',
                'hidden'    => ['group_id' => $group_id]
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete Status Group
    *
    * @return string
    */
    public function delete_status_group()
    {
        if (($group_id = Request::input('group_id')) === FALSE OR ! is_numeric($group_id)) {
            return false;
        }

        $group_name = DB::table('status_groups')->where('group_id', $group_id)->value('group_name');

        if (empty($group_name)) {
            return false;
        }

        DB::table('status_groups')->where('group_id', $group_id)->delete();
        DB::table('statuses')->where('group_id', $group_id)->delete();

        Cp::log(__('admin.status_group_deleted').'&nbsp;'.$group_name);

        $message = __('admin.status_group_deleted').'&nbsp;'.'<b>'.$group_name.'</b>';

        return $this->status_overview($message);
    }

    // --------------------------------------------------------------------

    /**
    * List Statuses for a Status Group (include new status form)
    *
    * @param integer $group_id
    * @param boolean $update
    * @return string
    */
    public function status_manager($group_id = '', $update = false)
    {
        if ($group_id == '') {
            if (($group_id = Request::input('group_id')) === FALSE) {
                return false;
            }
        }

        if ( ! is_numeric($group_id)) {
            return false;
        }

        $i = 0;
        $r = '';

        if ($update === true)
        {
            if (!Request::filled('status_id'))
            {
                $r .= Cp::quickDiv('success-message', __('admin.status_created'));
            }
            else
            {
                $r .= Cp::quickDiv('success-message', __('admin.status_updated'));
            }
        }

        $r .= Cp::table('', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('', '55%', '', '', 'top');


        $query = DB::table('status_groups')
            ->select('group_name')
            ->where('group_id', $group_id)
            ->first();

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '3').
              __('admin.status_group').':'.'&nbsp;'.$query->group_name.
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $query = DB::table('statuses')
            ->where('group_id', $group_id)
            ->orderBy('status_order')
            ->select('status_id', 'status')
            ->get();

        $total = $query->count() + 1;

        if ($query->count() > 0)
        {
            foreach ($query as $row)
            {

                $del =
                    ($row->status != 'open' AND $row->status != 'closed')
                    ?
                    Cp::anchor(
                        BASE.'?C=WeblogAdministration'.
                            AMP.'M=delete_status_confirm'.
                            AMP.'status_id='.$row->status_id,
                        __('cp.delete'),
                        'class="delete-link"'
                    )
                    :
                    '--';

                $status_name = ($row->status == 'open' OR $row->status == 'closed') ? __($row->status) : $row->status;

                $r .= '<tr>'.PHP_EOL.
                      Cp::tableCell('', Cp::quickSpan('defaultBold', $status_name)).
                      Cp::tableCell('', Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=edit_status_form'.AMP.'status_id='.$row->status_id, __('cp.edit'))).
                      Cp::tableCell('', $del).
                      '</tr>'.PHP_EOL;
            }
        }
        else
        {
            $r .= '<tr>'.PHP_EOL.
                      Cp::tableCell('', '<em>No statuses yet.</em>').
                  '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('littlePadding', Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=edit_status_order'.AMP.'group_id='.$group_id, __('admin.change_status_order')));

        $r .= '</td>'.PHP_EOL.
              Cp::td('rightCel', '45%', '', '', 'top');

        // Build the right side output

        $r .= Cp::formOpen([
                'action' => 'C=WeblogAdministration'.AMP.'M=update_status'.AMP.'group_id='.$group_id
                ]
            ).
            Cp::input_hidden('group_id', $group_id);

        $r .= Cp::quickDiv('tableHeading', __('admin.create_new_status'));

        $r .= Cp::div('box');

        $r .= Cp::quickDiv('', Cp::quickDiv('littlePadding', __('admin.status_name')).Cp::input_text('status', '', '30', '60', 'input', '260px'));

        $r .= Cp::quickDiv('',  Cp::quickDiv('littlePadding', __('admin.status_order')).Cp::input_text('status_order', $total, '20', '3', 'input', '50px'));

        $r .= '</div>'.PHP_EOL;


        if (Session::userdata('group_id') == 1)
        {
            $query = DB::table('member_group_preferences')
                ->join('member_groups', 'member_groups.group_id', '=', 'member_group_preferences.group_id')
                ->whereNotIn('member_groups.group_id', [1,2])
                ->where('member_group_preferences.value', 'y')
                ->where('member_group_preferences.handle', 'can_access_publish')
                ->orderBy('member_groups.group_name')
                ->select('member_groups.group_id', 'member_groups.group_name')
                ->get();

            $table_end = true;

            if ($query->count() == 0) {
                $table_end = false;
            }
            else
            {
                $r .= Cp::quickDiv('paddingTop', Cp::heading(__('admin.restrict_status_to_group'), 5));

                $r .= Cp::table('tableBorder', '0', '', '100%').
                      '<tr>'.PHP_EOL.
                      Cp::td('tableHeading', '', '').
                      __('admin.member_group').
                      '</td>'.PHP_EOL.
                      Cp::td('tableHeading', '', '').
                      __('admin.can_edit_status').
                      '</td>'.PHP_EOL.
                      '</tr>'.PHP_EOL;

                $i = 0;

                $group = [];

                foreach ($query as $row)
                {
                    $r .= '<tr>'.PHP_EOL.
                          Cp::td('', '50%').
                          $row->group_name.
                          '</td>'.PHP_EOL.
                          Cp::td('', '50%');

                    $selected = ( ! isset($group[$row->group_id])) ? 1 : '';

                    $r .= Cp::qlabel(__('admin.yes')).NBS.
                          Cp::input_radio('access_'.$row->group_id, 'y', $selected).'&nbsp;';

                    $selected = (isset($group[$row->group_id])) ? 1 : '';

                    $r .= Cp::qlabel(__('admin.no')).NBS.
                          Cp::input_radio('access_'.$row->group_id, 'n', $selected).'&nbsp;';

                    $r .= '</td>'.PHP_EOL
                         .'</tr>'.PHP_EOL;
                }
            }
        }

        if ($table_end == TRUE) {
            $r .= '</table>'.PHP_EOL;
        }

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('cp.submit')));

        $r .= '</form>'.PHP_EOL;

        $r .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL;


        Cp::$title = __('admin.statuses');

        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=status_overview', __('admin.status_groups')).
            Cp::breadcrumbItem(__('admin.statuses'));

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create or Update a Status
    *
    * @return string
    */
    public function update_status()
    {
        $edit = Request::filled('status_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'status'       => 'required|regex:#^([-a-z0-9_\+ ])+$#i',
            'status_order' => 'integer'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only(
            [
                'group_id',
                'status',
                'status_id',
                'status_order',
            ]
        );

        if (empty($data['status_order'])) {
            $data['status_order'] = 0;
        }

        if ($edit === false)
        {
            $count = DB::table('statuses')
                ->where('status', $data['status'])
                ->where('group_id', $data['group_id'])
                ->count();

            if ($count > 0) {
                return Cp::errorMessage(__('admin.duplicate_status_name'));
            }

            $status_id = DB::table('statuses')->insertGetId($data);
        }

        if ($edit === true)
        {
            $status_id = $data['status_id'];

            $count = DB::table('statuses')
                ->where('status', $data['status'])
                ->where('group_id', $data['group_id'])
                ->where('status_id', '!=', $data['status_id'])
                ->count();

            if ($count > 0) {
                return Cp::errorMessage(__('admin.duplicate_status_name'));
            }

            DB::table('statuses')
                ->where('status_id', $data['status_id'])
                ->where('group_id', Request::input('group_id'))
                ->update($data);

            DB::table('status_no_access')->where('status_id', $data['status_id'])->delete();

            // If the status name has changed, we need to update weblog entries with the new status.
            if (Request::filled('old_status') && Request::input('old_status') != $data['status'])
            {
                $query = DB::table('weblogs')
                    ->where('status_group', $data['group_id'])
                    ->get();

                foreach ($query as $row)
                {
                    DB::table('weblog_entries')
                        ->where('status', $data['old_status'])
                        ->where('weblog_id', $row->weblog_id)
                        ->update(['status' => $data['status']]);
                }
            }
        }


        // Set access privs
        foreach (Request::all() as $key => $val)
        {
            if (substr($key, 0, 7) == 'access_' AND $val == 'n')
            {
                DB::table('status_no_access')
                    ->insert(
                        [
                            'status_id' => $status_id,
                            'member_group' => substr($key, 7)
                        ]);
            }
        }

        return $this->status_manager($data['group_id'], true);
    }

    // --------------------------------------------------------------------

    /**
    * Edit Status Form
    *
    * @return string
    */
    public function edit_status_form()
    {
        if (($status_id = Request::input('status_id')) === FALSE OR ! is_numeric($status_id))
        {
            return false;
        }

        $query = DB::table('statuses')->where('status_id', $status_id)->first();

        $group_id       = $query->group_id;
        $status         = $query->status;
        $status_order   = $query->status_order;
        $status_id      = $query->status_id;

        // Build our output
        $r  = Cp::formOpen(array('action' => 'C=WeblogAdministration'.AMP.'M=update_status')).
            Cp::input_hidden('status_id', $status_id).
            Cp::input_hidden('old_status',  $status).
            Cp::input_hidden('group_id',  $group_id);

        $r .= Cp::quickDiv('tableHeading', __('admin.edit_status'));
        $r .= Cp::div('box');

        if ($status == 'open' OR $status == 'closed')
        {
            $r .= Cp::input_hidden('status', $status);

            $r .= Cp::quickDiv(
                    'littlePadding',
                    Cp::quickSpan('defaultBold', __('admin.status_name').':').
                        NBS.
                        __($status));
        }
        else
        {
            $r .= Cp::quickDiv('', Cp::quickDiv('littlePadding', __('admin.status_name')).Cp::input_text('status', $status, '30', '60', 'input', '260px'));
        }

        $r .= Cp::quickDiv('', Cp::quickDiv('littlePadding', __('admin.status_order')).Cp::input_text('status_order', $status_order, '20', '3', 'input', '50px'));

        $r .= '</div>'.PHP_EOL;

        if (Session::userdata('group_id') == 1)
        {
            $query = DB::table('member_groups')
                ->whereNotIn('group_id', [1,2,3,4])
                ->orderBy('group_name')
                ->select('group_id', 'group_name')
                ->get();

            $table_end = true;

            if ($query->count() == 0) {
                $table_end = false;
            }
            else
            {
                $r .= Cp::quickDiv('paddingTop', Cp::heading(__('admin.restrict_status_to_group'), 5));

                $r .= Cp::table('tableBorder', '0', '', '100%').
                      '<tr>'.PHP_EOL.
                      Cp::td('tableHeadingAlt', '', '').
                      __('admin.member_group').
                      '</td>'.PHP_EOL.
                      Cp::td('tableHeadingAlt', '', '').
                      __('admin.can_edit_status').
                      '</td>'.PHP_EOL.
                      '</tr>'.PHP_EOL;

                    $i = 0;

                $group = [];

                $result = DB::table('status_no_access')
                    ->select('member_group')
                    ->where('status_id', $status_id)
                    ->get();

                if ($result->count() != 0)
                {
                    foreach($result as $row)
                    {
                        $group[$row->member_group] = true;
                    }
                }

                foreach ($query as $row)
                {

                        $r .= '<tr>'.PHP_EOL.
                              Cp::td('', '50%').
                              $row->group_name.
                              '</td>'.PHP_EOL.
                              Cp::td('', '50%');

                        $selected = ( ! isset($group[$row->group_id])) ? 1 : '';

                        $r .= Cp::qlabel(__('admin.yes')).NBS.
                              Cp::input_radio('access_'.$row->group_id, 'y', $selected).'&nbsp;';

                        $selected = (isset($group[$row->group_id])) ? 1 : '';

                        $r .= Cp::qlabel(__('admin.no')).NBS.
                              Cp::input_radio('access_'.$row->group_id, 'n', $selected).'&nbsp;';

                        $r .= '</td>'.PHP_EOL
                             .'</tr>'.PHP_EOL;
                }

            }
        }

        if ($table_end == TRUE)
            $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('cp.update')));
        $r .= '</form>'.PHP_EOL;

        Cp::$title = __('admin.edit_status');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=status_overview', __('admin.status_groups')).
            Cp::breadcrumbItem(Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=status_manager'.AMP.'group_id='.$group_id, __('admin.statuses'))).
            Cp::breadcrumbItem(__('admin.edit_status'));

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Delete Status Confirmation Form
    *
    * @return string
    */
    public function delete_status_confirm()
    {
        if (($status_id = Request::input('status_id')) === false OR ! is_numeric($status_id)) {
            return false;
        }

        $query = DB::table('statuses')->where('status_id', $status_id)->first();

        Cp::$title = __('admin.delete_status');
        Cp::$crumb =
            Cp::anchor(
                BASE.'?C=WeblogAdministration'.
                    AMP.'M=status_manager'.
                    AMP.'group_id='.$query->group_id,
                __('admin.status_groups')
            ).
            Cp::breadcrumbItem(__('admin.delete_status'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'C=WeblogAdministration'.AMP.'M=delete_status'.AMP.'status_id='.$status_id,
                'heading'   => 'admin.delete_status',
                'message'   => 'admin.delete_status_confirmation',
                'item'      => $query->status,
                'extra'     => '',
                'hidden'    => ''
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete Status
    *
    * @return string
    */
    public function delete_status()
    {
        if (($status_id = Request::input('status_id')) === null OR ! is_numeric($status_id)) {
            return false;
        }

        $query = DB::table('statuses')->where('status_id', $status_id)->first();

        if (!$query) {
            return $this->status_overview();
        }

        $group_id = $query->group_id;
        $status   = $query->status;

        $query = DB::table('weblogs')
            ->select('weblog_id')
            ->where('status_group', $group_id)
            ->get();

        if ($query->count() > 0)
        {
            foreach($query as $row) {
                DB::table('weblog_entries')
                    ->where('status', $status)
                    ->where('weblog_id', $row->weblog_id)
                    ->update(['status' => 'closed']);
            }
        }

        if ($status != 'open' AND $status != 'closed')
        {
            DB::table('statuses')
                ->where('status_id', $status_id)
                ->where('group_id', $group_id)
                ->delete();
        }

        $this->status_manager($group_id);
    }

    // --------------------------------------------------------------------

    /**
    * Edit Status Group Ordering
    *
    * @return string
    */
    public function edit_status_order()
    {
        if (($group_id = Request::input('group_id')) === null OR ! is_numeric($group_id)) {
            return false;
        }

        $query = DB::table('statuses')
            ->where('group_id', $group_id)
            ->orderBy('status_order')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        $r  = Cp::formOpen(array('action' => 'C=WeblogAdministration'.AMP.'M=update_status_order'));
        $r .= Cp::input_hidden('group_id', $group_id);

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '2').
              __('admin.change_status_order').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        foreach ($query as $row)
        {
            $status_name = ($row->status == 'open' OR $row->status == 'closed') ? __($row->status) : $row->status;

            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', $status_name);
            $r .= Cp::tableCell('', Cp::input_text('status_'.$row->status_id, $row->status_order, '4', '3', 'input', '30px'));
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv('littlePadding', Cp::input_submit(__('cp.update')));

        $r .= '</form>'.PHP_EOL;

        Cp::$title = __('admin.change_status_order');


        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=status_overview', __('admin.status_groups')).
            Cp::breadcrumbItem(Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=status_manager'.AMP.'group_id='.$group_id, __('admin.statuses'))).
            Cp::breadcrumbItem(__('admin.change_status_order'));

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Update Status Order
    *
    * @return string
    */
    public function update_status_order()
    {
        if ( ! $group_id = Request::input('group_id')) {
            return false;
        }

        foreach (Request::all() as $key => $val)
        {
            if (!preg_match('/^status\_([0-9]+)$/', $key, $match)) {
                continue;
            }

            DB::table('statuses')
                ->where('status_id', $match[1])
                ->update(['status_order' => $val]);
        }

        return $this->status_manager($group_id);
    }


//=====================================================================
//  Custom Fields
//=====================================================================


    // --------------------------------------------------------------------

    /**
    * List of Custom Field Groups
    *
    * @param string $message
    * @return string
    */
    public function fields_overview($message = '')
    {
        // Fetch field groups
        $query = DB::table('field_groups')
            ->groupBy('field_groups.group_id')
            ->orderBy('field_groups.group_name')
            ->select(
                'field_groups.group_id',
                'field_groups.group_name'
            )->get();

        if ($query->count() == 0)
        {
			$r = Cp::heading(__('admin.field_groups')).
				Cp::quickDiv('success-message', $message).
				Cp::quickDiv('littlePadding', __('admin.no_field_group_message')).
				Cp::quickDiv('itmeWrapper',
					Cp::anchor(
						BASE.'?C=WeblogAdministration'.AMP.'M=edit_field_group_form',
						__('admin.create_new_field_group')
					 )
				);

			Cp::$title = __('admin.field_groups');
			Cp::$body  = $r;
			Cp::$crumb = __('admin.field_groups');

			return;
        }

        $r = '';

        if ($message != '') {
            $r .= Cp::quickDiv('success-message', $message);
        }

        $r .= Cp::table('tableBorder', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '4').
              __('admin.field_group').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $i = 0;

        foreach($query as $row)
        {
            $field_count = DB::table('weblog_fields')
                ->where('weblog_fields.group_id', $row->group_id)
                ->count();


            $r .= '<tr>'.PHP_EOL;

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    BASE.'?C=WeblogAdministration'.AMP.'M=edit_field_group_form'.AMP.'group_id='.$row->group_id,
                    $row->group_name,
                    'class="defaultBold"'
               ));

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    BASE.'?C=WeblogAdministration'.AMP.'M=field_manager'.AMP.'group_id='.$row->group_id,
                    __('admin.add_edit_fields')
                   ).
                  ' ('.$field_count.')'
                );

            $r .= Cp::tableCell('',
                  Cp::anchor(
                    BASE.'?C=WeblogAdministration'.AMP.'M=delete_field_group_conf'.AMP.'group_id='.$row->group_id,
                    __('cp.delete'),
                    'class="delete-link"'
               ));

            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$title  = __('admin.field_groups');
        Cp::$crumb  = __('admin.field_groups');

        $right_links[] = [
            BASE.'?C=WeblogAdministration'.AMP.'M=edit_field_group_form',
            __('admin.create_new_field_group')
        ];

        $r = Cp::header(__('admin.field_groups'), $right_links).$r;

        Cp::$body = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Edit Custom Field group form
    *
    * @return string
    */
    public function edit_field_group_form()
    {
        // Default values
        $edit       = false;
        $group_id   = '';
        $group_name = '';

        if ($group_id = Request::input('group_id')) {
            $edit = true;

            if ( ! is_numeric($group_id)) {
                return false;
            }

            $query = DB::table('field_groups')
                ->where('group_id', $group_id)
                ->select('group_id', 'group_name')
                ->first();

            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        if ($edit == FALSE) {
            $title = __('admin.new_field_group');
        }
        else {
            $title = __('admin.edit_field_group_name');
        }

        // Build our output
        $r = Cp::formOpen(array('action' => 'C=WeblogAdministration'.AMP.'M=update_field_group'));

        if ($edit == TRUE) {
            $r .= Cp::input_hidden('group_id', $group_id);
        }

        $r .= Cp::quickDiv('tableHeading', $title);

        $r .= Cp::div('box');
        $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('admin.field_group_name')));
        $r .= Cp::input_text('group_name', $group_name, '20', '50', 'input', '300px');
        $r .= '<br><br>';
        $r .= '</div>'.PHP_EOL;

        $r .= Cp::div('paddingTop');

        $r .= Cp::input_submit(($edit == FALSE) ? __('cp.submit') : __('cp.update'));

        $r .= '</form>'.PHP_EOL;

        Cp::$title = $title;
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=fields_overview', __('admin.field_groups')).
            Cp::breadcrumbItem($title);

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Update Custom Field group
    *
    * @return string
    */
    public function update_field_group()
    {
        $edit = Request::filled('group_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'group_name'       => 'required|regex:#^[a-zA-Z0-9_\-/\s]+$#i',
            'group_id'         => 'numeric'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only(
            [
                'group_name',
                'group_id'
            ]
        );

        $query = DB::table('field_groups')
            ->where('group_name', $data['group_name']);

        if ($edit === true) {
            $query->where('group_id', '!=', $data['group_id']);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('admin.taken_field_group_name'));
        }

        // ------------------------------------
        //  Create!
        // ------------------------------------

        if ($edit === false)
        {
            DB::table('field_groups')->insert($data);

            $success_msg = __('admin.field_group_created');

            Cp::log(__('admin.field_group_created').'&nbsp;'.$data['group_name']);
        }

        // ------------------------------------
        //  Update!
        // ------------------------------------

        if ($edit === true) {
            DB::table('field_groups')->where('group_id', $data['group_id'])->update($data);

            $success_msg = __('admin.field_group_updated');
        }

        $message = $success_msg.' '. $data['group_name'];

        // ------------------------------------
        //  Message
        // ------------------------------------

        if ($edit === false)
        {
            $query = DB::table('weblogs')
                ->select('weblog_id')
                ->get();

            if ($query->count() > 0)
            {
                $message .= Cp::div('littlePadding').Cp::quickSpan('highlight', __('admin.assign_group_to_weblog')).'&nbsp;';

                if ($query->count() == 1)
                {
                    $link = 'C=WeblogAdministration'.AMP.'M=editWeblogFields'.AMP.'weblog_id='.$query->first()->weblog_id;
                }
                else
                {
                    $link = 'C=WeblogAdministration'.AMP.'M=weblogsOverview';
                }

                $message .= Cp::anchor(BASE.'?'.$link, __('admin.click_to_assign_group'));

                $message .= '</div>'.PHP_EOL;
            }
        }

        return $this->fields_overview($message);
    }

    // --------------------------------------------------------------------

    /**
    * Delete Custom Field group confirmation form
    *
    * @return string
    */
    public function delete_field_group_conf()
    {
        if (($group_id = Request::input('group_id')) === null OR ! is_numeric($group_id)) {
            return false;
        }

        $group_name = DB::table('field_groups')
            ->where('group_id', $group_id)
            ->value('group_name');

        if ( ! $group_name) {
            return false;
        }

        Cp::$title = __('admin.delete_group');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=fields_overview', __('admin.field_groups')).
            Cp::breadcrumbItem(__('admin.delete_group'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'C=WeblogAdministration'.AMP.'M=delete_field_group'.AMP.'group_id='.$group_id,
                'heading'   => 'admin.delete_field_group',
                'message'   => 'admin.delete_field_group_confirmation',
                'item'      => $group_name,
                'extra'     => '',
                'hidden'    => ['group_id' => $group_id]
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete Custom Field group
    *
    * @return string
    */
    public function delete_field_group()
    {
        if (($group_id = Request::input('group_id')) === null OR ! is_numeric($group_id)) {
            return false;
        }

        $name = DB::table('field_groups')->where('group_id', $group_id)->value('group_name');

        $query = DB::table('weblog_fields')
            ->where('group_id', $group_id)
            ->select('field_id', 'field_type', 'field_name')
            ->get();

        foreach ($query as $row)
        {
            Schema::table('weblog_entry_data', function($table) use ($row) {
                $table->dropColumn('field_'.$row->field_name);
            });
        }

        DB::table('field_groups')->where('group_id', $group_id)->delete();
        DB::table('weblog_fields')->where('group_id', $group_id)->delete();

        Cp::log(__('admin.field_group_deleted').$name);

        $message = __('admin.field_group_deleted').'<b>'.$name.'</b>';

        cms_clear_caching('all');

        return $this->fields_overview($message);
    }

    // --------------------------------------------------------------------

    /**
    * List Custom Fields for a Group
    *
    * @param integer $group_id
    * @param string $msg
    * @return string
    */
    public function field_manager($group_id = '', $msg = false)
    {
        $message = ($msg == true) ? __('admin.preferences_updated') : '';

        if ($group_id == '')
        {
            if (($group_id = Request::input('group_id')) === null OR ! is_numeric($group_id))
            {
                return false;
            }
        }

        if ( ! is_numeric($group_id)) {
            return false;
        }

        // Fetch the name of the field group
        $query = DB::table('field_groups')->select('group_name')->where('group_id', $group_id)->first();

        $r  = Cp::quickDiv('tableHeading', __('admin.field_group').':'.'&nbsp;'.$query->group_name);

        if ($message != '')
        {
            $r .= Cp::quickDiv('success-message', $message);
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '40%', '1').__('admin.field_label').'</td>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '20%', '1').__('admin.field_name').'</td>'.PHP_EOL.
              Cp::td('tableHeadingAlt', '40%', '2').__('admin.field_type').'</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $query = DB::table('weblog_fields')
            ->where('group_id', $group_id)
            ->orderBy('field_label')
            ->select(
                'field_id',
                'field_name',
                'field_label',
                'field_type'
            )->get();


        if ($query->count() == 0) {
            $r .= '<tr>'.PHP_EOL.
                  Cp::td('', '', 4).
                  '<b>'.__('admin.no_field_groups').'</br>'.
                  '</td>'.PHP_EOL.
                  '</tr>'.PHP_EOL;
        }

        // FieldTypes!
        $field_types = Plugins::fieldTypes();

        // dd($field_types);

        $i = 0;

        if ($query->count() > 0) {
            foreach ($query as $row) {

                $r .= '<tr>'.PHP_EOL;

                $r .= Cp::tableCell(
                    '',
                    Cp::quickDiv(
                        'defaultBold',
                        Cp::anchor(
                            BASE.'?C=WeblogAdministration'.
                                AMP.'M=editField'.
                                AMP.'field_id='.$row->field_id,
                            $row->field_label
                        )
                    )
                );

                $r .= Cp::tableCell('', $row->field_name);

                $field_type = (__($row->field_type) === FALSE) ? '' : __($row->field_type);

                switch ($row->field_type)
                {
                    case 'text' :  $field_type = __('admin.Text Input');
                        break;
                    case 'textarea' :  $field_type = __('admin.Textarea');
                        break;
                    case 'dropdown' :  $field_type = __('admin.Dropdown');
                        break;
                    case 'date' :  $field_type = __('admin.Date');
                        break;
                }

                $r .= Cp::tableCell('', $field_type);
                $r .= Cp::tableCell(
                    '',
                    Cp::anchor(
                        BASE.'?C=WeblogAdministration'.
                            AMP.'M=delete_field_confirm'.
                            AMP.'field_id='.$row->field_id,
                        __('cp.delete'),
                        'class="delete-link"'
                    )
                );
                $r .= '</tr>'.PHP_EOL;
            }
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$title = __('admin.custom_fields');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=fields_overview', __('admin.field_groups')).
            Cp::breadcrumbItem(__('admin.custom_fields'));

        $right_links[] = [
            BASE.'?C=WeblogAdministration'.AMP.'M=editField'.AMP.'group_id='.$group_id,
            __('admin.create_new_custom_field')
        ];

        $r = Cp::header(__('admin.custom_fields'), $right_links).$r;

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Edit Custom Field form
    *
    * @return string
    */
    public function editField()
    {
        $field_id = Request::input('field_id');

        $type = ($field_id) ? 'edit' : 'new';

        // ------------------------------------
        //  Variables
        // ------------------------------------

        $field_id            = '';
        $field_handle        = '';
        $field_name          = '';
        $field_instructions  = '';
        $field_type          = '';

        $is_field_required   = false;

        $group_id            = '';
        $group_name          = '';

        $total_fields = '';

        if ($type == 'new') {
            $total_fields = 1 + DB::table('weblog_fields')->count();
        }

        if ($field_id) {
            $query = DB::table('weblog_fields AS f')
                ->join('field_groups AS g', 'f.group_id', '=', 'g.group_id')
                ->where('f.field_id', $field_id)
                ->select(
                    'f.*',
                    'g.group_name'
                )
                ->firstOrFail();

            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        if (empty($group_id)) {
            $group_id = Request::input('group_id');
        }

        if (empty($group_name)) {
            $group_name = DB::table('field_groups')
                ->where('group_id', $group_id)
                ->value('group_name');
        }

        // ------------------------------------
        //  JavaScript
        // ------------------------------------

        $js = <<<EOT
	<script type="text/javascript">

        function displayFieldTypeOptions(id)
        {
        	var field_type = $('select[name=field_type]').val();

            $('.field-option').css('display', 'none');

            $('#field_type_settings_'+field_type).css('display', 'block');
        }
	</script>
EOT;


        $r = $js;

        // ------------------------------------
        //  Form Opening
        // ------------------------------------

        $r .= Cp::formOpen([
        	'action' => 'C=WeblogAdministration'.AMP.'M=updateField',
        	'name' => 'field_form'
        ]);

        $r .= Cp::input_hidden('group_id', $group_id);
        $r .= Cp::input_hidden('field_id', $field_id);
        $r .= Cp::input_hidden('site_id', Site::config('site_id'));

        $title = __(($type == 'edit') ? 'admin.edit_field' : 'admin.create_new_custom_field');

        $r .= Cp::table('tableBorder', '0', '10', '100%').
			'<tr>'.PHP_EOL.
				Cp::td('tableHeading', '', '2').
					$title.' ('.__('admin.field_group').": {$group_name})".
				'</td>'.PHP_EOL.
			'</tr>'.PHP_EOL;

        $i = 0;

        // ------------------------------------
        //  Field Label
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell(
            '',
            Cp::quickSpan(
                'defaultBold',
                Cp::required().__('admin.field_label')
            ).
            Cp::quickDiv(
                '',
                __('admin.field_label_info')
            ),
            '50%'
        );

        $r .= Cp::tableCell(
            '',
            Cp::input_text(
                'field_name',
                $field_name,
                '20',
                '60',
                'input',
                '260px'
            ),
            '50%'
        );

        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Field name
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell(
            '',
            Cp::quickSpan(
                'defaultBold',
                Cp::required().__('admin.field_name')
            ).
            Cp::quickDiv(
                'littlePadding',
                __('admin.field_name_explanation')
            ),
            '50%'
        );

        $r .= Cp::tableCell(
            '',
            Cp::input_text(
                'field_handle',
                $field_handle,
                '20',
                '60',
                'input',
                '260px'
            ),
            '50%'
        );

        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Field Instructions
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell(
            '',
            Cp::quickSpan(
                'defaultBold',
                __('admin.field_instructions')
            ).
            Cp::quickDiv(
                '',
                __('admin.field_instructions_info')
            ),
            '50%',
            'top'
        );

        $r .= Cp::tableCell(
            '',
            Cp::input_textarea(
                'field_instructions',
                $field_instructions,
                '6',
                'textarea',
                '99%'
            ),
            '50%',
            'top'
        );

        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Is field required?
        // ------------------------------------

        if ($is_field_required == '') {
        	$is_field_required = 'n';
        }

        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell(
            '',
            Cp::quickSpan(
                'defaultBold',
                __('admin.is_field_required')
            ),
            '50%'
        );

        $r .= Cp::tableCell(
            '',
                __('admin.yes').
                ' '.
                Cp::input_radio('is_field_required', 'y', ($is_field_required == 'y') ? 1 : '').
                ' '.
                __('admin.no').' '.
                Cp::input_radio('is_field_required', 'n', ($is_field_required == 'n') ? 1 : ''),
            '50%'
        );

        $r .= '</tr>'.PHP_EOL;

        // ------------------------------------
        //  Create the Field Type pull-down menu
        // ------------------------------------

        $r .= '<tr>'.PHP_EOL.'<td><strong>'.__('admin.Field Type').'</strong></td>';

        $r .= '<td>';
        $r .= "<select name='field_type' class='select'>";


        $field_types = Plugins::fieldTypes();

        foreach($field_types as $name => $details) {
            $r .= Cp::input_select_option($details['class'], $name);
        }

        $r .= Cp::input_select_footer();

        $r .= '</td></tr>'.PHP_EOL;

        // ------------------------------------
        //  Close Default Fields
        // ------------------------------------

        $r .= '</table>'.PHP_EOL;


        // ------------------------------------
        //  Submit
        // ------------------------------------

        if ($type == 'edit') {
            $r .= Cp::input_submit(__('cp.update'));
        }
        else {
            $r .= Cp::input_submit(__('cp.submit'));
        }

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        Cp::$title = $title.' | '.__('admin.custom_fields');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=fields_overview', __('admin.field_groups')).
            Cp::breadcrumbItem($title);
        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Update Custom Field
    *
    * @return string
    */
    public function updateField()
    {
        $edit = Request::filled('field_id');

        // ------------------------------------
        //  Validation
        // ------------------------------------

        $validator = Validator::make(request()->all(), [
            'field_name'       => 'regex:/^[\pL\pM\pN_]+$/u',
            'field_label'      => 'required|not_in:'.implode(',',Cp::unavailableFieldNames()),
            'group_id'         => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return Cp::errorMessage(implode(BR, $validator->errors()->all()));
        }

        $data = Request::only(
            [
                'field_label',
                'field_name',
                'field_instructions',
                'field_type',
                'field_pre_populate',
                'field_maxlength',
                'textarea_num_rows',
                'field_list_items',
                'field_pre_populate_id',
                'is_field_required',
                'is_field_searchable',
                'group_id',
                'field_id'
            ]
        );

        $stringable = [
            'field_instructions',
            'field_list_items'
        ];

        foreach($stringable as $field) {
            if(empty($data[$field])) {
                $data[$field] = '';
            }
        }

        // Let DB defaults handle these if empty
        $unsettable = [
            'field_pre_populate',
            'field_maxlength',
            'textarea_num_rows',
            'field_pre_populate_id',
            'is_field_required',
        ];

        foreach($unsettable as $field) {
            if(empty($data[$field])) {
                unset($data[$field]);
            }
        }

        $group_id = Request::input('group_id');

        // ------------------------------------
        //  Field Name or Label Taken?
        // ------------------------------------

        $query = DB::table('weblog_fields')
            ->where('field_name', $data['field_name']);

        if ($edit === true) {
            $query->where('field_id', '!=', $data['field_id']);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('admin.duplicate_field_name'));
        }

        $query = DB::table('weblog_fields')
            ->where('field_label', $data['field_label']);

        if ($edit === true) {
            $query->where('field_id', '!=', $data['field_id']);
        }

        if ($query->count() > 0) {
            return Cp::errorMessage(__('admin.duplicate_field_name'));
        }

        // ------------------------------------
        //  Data ch-ch-changes
        // ------------------------------------

        if (!empty($data['field_list_items'])) {
            $data['field_list_items'] = convert_quotes($data['field_list_items']);
        }

        if ($data['field_pre_populate'] == 'y') {
            $x = explode('_', $data['field_pre_populate_id']);

            $data['field_pre_blog_id']    = $x[0];
            $data['field_pre_field_name'] = $x[1];
        }

        unset($data['field_pre_populate_id']);

        // ------------------------------------
        //  Updating!
        // ------------------------------------

        if ($edit === true)
        {
            if ( ! is_numeric($data['field_id'])) {
                return false;
            }

            unset($data['group_id']);

            $query = DB::table('weblog_fields')
                ->select('field_type', 'field_name')
                ->where('field_id', $data['field_id'])
                ->first();

            if ($query->field_type != $data['field_type'] && $data['field_type'] == 'date') {
                return Cp::errorMessage(__('admin.unable_to_change_to_date_field_type'));
            }

            // Ch-ch-ch-changing
            if ($query->field_type != $data['field_type']) {
                switch($data['field_type'])
                {
                    case 'date' :
                        Schema::table('weblog_entry_data', function($table) use ($query)
                        {
                            $table->timestamp('field_'.$query->field_name)->nullable(true)->change();
                        });
                    break;
                    default     :
                        Schema::table('weblog_entry_data', function($table) use ($query)
                        {
                            $table->text('field_'.$query->field_name)->nullable(true)->change();
                        });
                    break;
                }
            }

            if ($query->field_name != $data['field_name']) {
                Schema::table('weblog_entry_data', function($table) use ($query, $data)
                {
                    $table->renameColumn('field_'.$query->field_name, 'field_'.$data['field_name']);
                });

                DB::table('weblog_layout_fields')
                    ->where('field_name', $query->field_name)
                    ->update([
                        'field_name' => $data['field_name'],
                    ]);
            }

            DB::table('weblog_fields')
                ->where('field_id', $data['field_id'])
                ->where('group_id', $group_id)
                ->update($data);
        }

        // ------------------------------------
        //  Creation
        // ------------------------------------

        if ($edit !== true)
        {
            unset($data['field_id']);

            $insert_id = DB::table('weblog_fields')->insertGetId($data);

            if ($data['field_type'] == 'date') {
                Schema::table('weblog_entry_data', function($table) use ($data)
                {
                    $table->timestamp('field_'.$data['field_name'])->nullable(true);
                });
            } else {
                Schema::table('weblog_entry_data', function($table) use ($data)
                {
                    $table->text('field_'.$data['field_name'])->nullable(true);
                });
            }

            $weblog_ids = DB::table('weblogs')
                ->where('field_group', $group_id)
                ->pluck('weblog_id')
                ->all();

            foreach($weblog_ids as $weblog_id) {
                $tab_id = DB::table('weblog_layout_tabs')
                    ->where('weblog_id', $weblog_id)
                    ->orderBy('tab_order')
                    ->value('weblog_layout_tab_id');

                $max = DB::table('weblog_layout_fields')
                    ->where('tab_id', $tab_id)
                    ->max('field_order');

                if ($tab_id) {
                    DB::table('weblog_layout_fields')
                    ->insert([
                        'tab_id' => $tab_id,
                        'field_name' => $data['field_name'],
                        'field_order' => $max+1
                    ]);
                }
            }

       }

        cms_clear_caching('all');

        return $this->field_manager($group_id, $edit);
    }

    // --------------------------------------------------------------------

    /**
    * Delete Custom Field confirmation form
    *
    * @return string
    */
    public function delete_field_confirm()
    {
        if ( ! $field_id = Request::input('field_id')) {
            return false;
        }

        $query = DB::table('weblog_fields')
            ->select('field_label')
            ->where('field_id', $field_id)
            ->first();

        Cp::$title = __('admin.delete_field');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=fields_overview', __('admin.field_groups')).
            Cp::breadcrumbItem(__('admin.delete_field'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'C=WeblogAdministration'.AMP.'M=delete_field'.AMP.'field_id='.$field_id,
                'heading'   => 'admin.delete_field',
                'message'   => 'admin.delete_field_confirmation',
                'item'      => $query->field_label,
                'extra'     => '',
                'hidden'    => array('field_id' => $field_id)
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete Custom Field
    *
    * @return string
    */
    public function delete_field()
    {
        if ( ! $field_id = Request::input('field_id')) {
            return false;
        }

        if ( ! is_numeric($field_id)) {
            return false;
        }

        $query = DB::table('weblog_fields')
            ->where('field_id', $field_id)
            ->select('group_id', 'field_type', 'field_label', 'field_name')
            ->first();

        $group_id = $query->group_id;
        $field_label = $query->field_label;
        $field_type = $query->field_type;
        $field_name = $query->field_name;

        Schema::table('weblog_entry_data', function($table) use ($field_name)
        {
            if (!Schema::hasColumn('weblog_entry_data', 'field_'.$field_name)) {
                return;
            }

            $table->dropColumn('field_'.$field_name);
        });

        DB::table('weblog_fields')
            ->where('field_id', $field_id)
            ->delete();

        DB::table('weblog_layout_fields')
            ->where('field_name', $field_name)
            ->delete();

        Cp::log(__('admin.field_deleted').'&nbsp;'.$field_label);

        cms_clear_caching('all');

        return $this->field_manager($group_id);
    }

    // --------------------------------------------------------------------

    /**
    * List File Upload Directories
    *
    * @param string $update
    * @return string
    */
    public function uploadPreferences($update = false)
    {
        $right_links[] = [
            BASE.'?C=WeblogAdministration'.AMP.'M=editUploadPreferences',
            __('admin.create_new_upload_pref')
        ];

        $r = Cp::header(__('admin.file_upload_preferences'), $right_links);

        if ($update === true) {
            $r .= Cp::quickDiv('success-message', __('admin.preferences_updated'));
        }

        $r .= Cp::table('tableBorder', '0', '10', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('tableHeading', '', '3').
              __('admin.current_upload_prefs').
              '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        $query = DB::table('upload_prefs')
            ->orderBy('name')
            ->get();

        if ($query->count() == 0) {
            $r .= '<tr>'.PHP_EOL.
                  Cp::td('', '', '3').
                  '<b>'.__('admin.no_upload_prefs').'</b>'.
                  '</td>'.PHP_EOL.
                  '</tr>'.PHP_EOL;
        }

        $i = 0;

        foreach ($query as $row)
        {
            $r .= '<tr>'.PHP_EOL;
            $r .= Cp::tableCell('', '&nbsp;'.Cp::quickSpan('defaultBold', $row->name), '40%');
            $r .= Cp::tableCell('', Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=editUploadPreferences'.AMP.'id='.$row->id, __('cp.edit')), '30%');
            $r .= Cp::tableCell('', Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=deleteUploadPreferencesConfirm'.AMP.'id='.$row->id, __('cp.delete')), '30%');
            $r .= '</tr>'.PHP_EOL;
        }

        $r .= '</table>'.PHP_EOL;

        Cp::$title = __('admin.file_upload_preferences');
        Cp::$crumb = __('admin.file_upload_preferences');

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Edit Upload Preferences form
    *
    * @return string
    */
    public function editUploadPreferences()
    {
        $id = Request::input('id');

        $type = (!empty($id)) ? 'edit' : 'new';

        $site_id = Site::config('site_id');
        $name = '';
        $server_path = '';
        $url = '';
        $allowed_types = 'img';
        $max_size = '';
        $max_width = '';
        $max_height = '';
        $properties = '';
        $pre_format = '';
        $post_format = '';
        $file_properties = '';
        $file_pre_format = '';
        $file_post_format = '';

        if ($type === 'edit') {
            $query = DB::table('upload_prefs')
                ->where('id', $id)
                ->first();

            if (empty($query)) {
                return Cp::unauthorizedAccess();
            }

            foreach ($query as $key => $val) {
                $$key = $val;
            }
        }

        // Form declaration
        $r  = Cp::formOpen(['action' => 'C=WeblogAdministration'.AMP.'M=updateUploadPreferences']);
        $r .= Cp::input_hidden('id', $id);
        $r .= Cp::input_hidden('cur_name', $name);

        $r .= Cp::table('tableBorder', '0', '', '100%').
              Cp::td('tableHeading', '', '2');

        if ($type == 'edit') {
            $r .= __('admin.edit_file_upload_preferences');
        }
        else {
            $r .= __('admin.new_file_upload_preferences');

            $r .= '</td>'.PHP_EOL.
                  '</tr>'.PHP_EOL;
        }

        $i = 0;

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', Cp::required().NBS.__('admin.upload_pref_name')),
                Cp::input_text('name', $name, '50', '50', 'input', '100%')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', Cp::required().NBS.__('admin.server_path')),
                Cp::input_text('server_path', $server_path, '50', '100', 'input', '100%')
            ]
        );

        if ($url == '') {
            $url = 'https://';
        }

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', Cp::required().NBS.__('admin.url_to_upload_dir')),
                Cp::input_text('url', $url, '50', '100', 'input', '100%')
            ]
        );

        if ($allowed_types == '') {
            $allowed_types = 'img';
        }

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', Cp::required().NBS.__('admin.allowed_types')),
                Cp::input_radio('allowed_types', 'img', ($allowed_types == 'img') ? 1 : '').NBS.__('admin.images_only')
                .NBS.Cp::input_radio('allowed_types', 'all', ($allowed_types == 'all') ? 1 : '').NBS.__('admin.all_filetypes')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.max_size')),
                Cp::input_text('max_size', $max_size, '15', '16', 'input', '90px')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.max_height')),
                Cp::input_text('max_height', $max_height, '10', '6', 'input', '60px')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.max_width')),
                Cp::input_text('max_width', $max_width, '10', '6', 'input', '60px')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.properties')),
                Cp::input_text('properties', $properties, '50', '120', 'input', '100%')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.pre_format')),
                Cp::input_text('pre_format', $pre_format, '50', '120', 'input', '100%')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.post_format')),
                Cp::input_text('post_format', $post_format, '50', '120', 'input', '100%')
            ]
        );


        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.file_properties')),
                Cp::input_text('file_properties', $file_properties, '50', '120', 'input', '100%')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.file_pre_format')),
                Cp::input_text('file_pre_format', $file_pre_format, '50', '120', 'input', '100%')
            ]
        );

        $r .= Cp::tableQuickRow('',
            [
                Cp::quickSpan('defaultBold', __('admin.file_post_format')),
                Cp::input_text('file_post_format', $file_post_format, '50', '120', 'input', '100%')
            ]
        );

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::quickDiv(
            'paddingTop',
            Cp::heading(
                __('admin.restrict_to_group'),
                5
            ).
            __('admin.restrict_notes_1').
            Cp::quickDiv(
                'littlePadding',
                Cp::quickDiv('highlight', __('admin.restrict_notes_2'))
            )
        );

        $query = DB::table('member_groups')
            ->whereNotIn('group_id',  [1,2,3,4])
            ->select('group_id', 'group_name')
            ->orderBy('group_name')
            ->get();

        if ($query->count() > 0)
        {
            $r .= Cp::table('tableBorder', '0', '', '100%').
                  '<tr>'.PHP_EOL.
                      Cp::td('tableHeading', '', '').
                          __('admin.member_group').
                      '</td>'.PHP_EOL.
                      Cp::td('tableHeading', '', '').
                          __('admin.can_upload_files').
                      '</td>'.PHP_EOL.
                  '</tr>'.PHP_EOL;

            $i = 0;

            $group = [];

            $result = DB::table('upload_no_access');

            if ($id != '') {
                $result->where('upload_id', $id);
            }

            $result = $result->get();

            foreach($result as $row) {
                $group[$row->member_group] = true;
            }

            foreach ($query as $row)
            {
                $r .= '<tr>'.PHP_EOL.
                      Cp::td('', '50%').$row->group_name.'</td>'.PHP_EOL.
                      Cp::td('', '50%');

                $selected = ( ! isset($group[$row->group_id])) ? 1 : '';

                $r .= Cp::qlabel(__('admin.yes')).NBS.
                      Cp::input_radio('access_'.$row->group_id, 'y', $selected).'&nbsp;';

                $selected = (isset($group[$row->group_id])) ? 1 : '';

                $r .= Cp::qlabel(__('admin.no')).NBS.
                      Cp::input_radio('access_'.$row->group_id, 'n', $selected).'&nbsp;';

                $r .= '</td>'.PHP_EOL.'</tr>'.PHP_EOL;
            }
            $r .= '</table>'.PHP_EOL;
        }

        $r .= Cp::div('littlePadding')
             .Cp::quickDiv('littlePadding', Cp::required(1));

        if ($type == 'edit') {
            $r .= Cp::input_submit(__('cp.update'));
        }
        else {
            $r .= Cp::input_submit(__('cp.submit'));
        }

        $r .= '</div>'.PHP_EOL;
        $r .= '</form>'.PHP_EOL;

        $lang_line = ($type == 'edit') ? 'admin.edit_file_upload_preferences' : 'admin.create_new_upload_pref';

        Cp::$title = __($lang_line);
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=uploadPreferences', __('admin.file_upload_prefs')).
            Cp::breadcrumbItem(__($lang_line));

        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create/Update Upload Preferences
    *
    * @return string
    */
    public function updateUploadPreferences()
    {
        $edit = (Request::input('id') && is_numeric(Request::input('id'))) ? true : false;

        exit('Hi. File uploads are in the process of being redone.');
    }

    // --------------------------------------------------------------------

    /**
    * Delete Upload Preferences confirmation form
    *
    * @return string
    */
    public function deleteUploadPreferencesConfirm()
    {
        if ( ! $id = Request::input('id')) {
            return false;
        }

        if ( ! is_numeric($id)) {
            return false;
        }

        $query = DB::table('upload_prefs')->select('name')->where('id', $id)->first();

        Cp::$title = __('admin.delete_upload_preference');
        Cp::$crumb =
            Cp::anchor(BASE.'?C=WeblogAdministration'.AMP.'M=uploadPreferences', __('admin.file_upload_prefs')).
            Cp::breadcrumbItem(__('admin.delete_upload_preference'));

        Cp::$body = Cp::deleteConfirmation(
            [
                'url'       => 'C=WeblogAdministration'.AMP.'M=deleteUploadPreferences'.AMP.'id='.$id,
                'heading'   => 'admin.delete_upload_preference',
                'message'   => 'admin.delete_upload_pref_confirmation',
                'item'      => $query->name,
                'extra'     => '',
                'hidden'    => ['id' => $id]
            ]
        );
    }

    // --------------------------------------------------------------------

    /**
    * Delete Upload Preferences
    *
    * @return string
    */
    public function deleteUploadPreferences()
    {
        if ( ! $id = Request::input('id')) {
            return false;
        }

        if ( ! is_numeric($id)) {
            return false;
        }

        DB::table('upload_no_access')->where('upload_id', $id)->delete();

        $name = DB::table('upload_prefs')->where('id', $id)->value('name');

        DB::table('upload_prefs')->where('id', $id)->delete();

        Cp::log(__('admin.upload_pref_deleted').'&nbsp;'.$name);

        return $this->uploadPreferences();
    }
}
