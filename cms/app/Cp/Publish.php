<?php

namespace Kilvin\Cp;

use Cp;
use DB;
use Site;
use Stats;
use Plugins;
use Request;
use Notification;
use Carbon\Carbon;
use Kilvin\Core\Regex;
use Kilvin\Core\Session;
use Kilvin\Core\Localize;
use Kilvin\Models\Member;
use Kilvin\Core\JsCalendar;
use Kilvin\Notifications\NewEntryAdminNotify;

class Publish
{
    public $assign_cat_parent   = true;
    public $categories          = [];
    public $cat_parents         = [];
    public $nest_categories     = 'y';
    public $cat_array           = [];

    public $url_title_error      = false;

    // --------------------------------------------------------------------

    /**
     * Constructor!
     */
    public function __construct()
    {
    }

    // --------------------------------------------------------------------

    /**
    * Request Handler
    *
    * @return mixed
    */
    public function run()
    {
        $allowed = [
            'submitNewEntry',
            'entryForm',
            'editEntry',
            'listEntries',
            'entriesEditForm',
            'updateMultipleEntries',
            'entriesCategoryUpdate',
            'deleteEntriesConfirm',
            'deleteEntries',
            'uploadFileForm',
            'uploadFile',
            'fileBrowser'
        ];

        if (in_array(Request::input('M'), $allowed)) {
            return $this->{Request::input('M')}();
        }

        if (Request::input('C') == 'publish')
        {
            $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

            if (count($assigned_weblogs) == 0) {
                return Cp::unauthorizedAccess(__('publish.unauthorized_for_any_blogs'));
            }

            if (count($assigned_weblogs) == 1) {
                return $this->entryForm();
            }

            return $this->weblogSelectList();
        }

        return $this->editEntries();
    }

    // --------------------------------------------------------------------

    /**
    * List of Available Weblogs for Publishing
    *
    * @return string
    */
    public function weblogSelectList()
    {
        $links = [];

        foreach (Session::userdata('assigned_weblogs') as $weblog_id => $weblog_title)
        {
            $links[] = Cp::tableQuickRow(
                '',
                Cp::quickDiv(
                    'defaultBold',
                    Cp::anchor(
                        BASE.'?C=publish'.AMP.'M=entryForm'.
                            AMP.'weblog_id='.$weblog_id,
                        $weblog_title
                    )
                )
            );
        }

        // If there are no allowed blogs, show a message
        if (empty($links)){
            return Cp::unauthorizedAccess(__('publish.unauthorized_for_any_blogs'));
        }

        Cp::$body .= Cp::table('tableBorder', '0', '', '100%').
            Cp::tableQuickRow('tableHeading', __('publish.select_blog_to_post_in'));

        foreach ($links as $val) {
            Cp::$body .= $val;
        }

        Cp::$body .= '</table>'.PHP_EOL;

        Cp::$title = __('publish.publish');
        Cp::$crumb =  __('publish.publish');
    }

    // --------------------------------------------------------------------

    /**
    * Edit Entry Form
    *
    * @return string
    */
    public function editEntry($submission_error = null)
    {
        return $this->entryForm($submission_error);
    }

    // --------------------------------------------------------------------

    /**
    * Create/Edit Entry Form
    *
    * @param string
    * @return string
    */
    public function entryForm($submission_error = null)
    {
        $title                      = '';
        $status                     = '';
        $sticky                     = '';
        $author_id                  = '';
        $version_id                 = Request::input('version_id');
        $weblog_id                  = Request::input('weblog_id');
        $entry_id                   = Request::input('entry_id');
        $which                      = 'new';
        $incoming                   = (is_null($submission_error)) ? [] : Request::all(); // Submission fail
        $revision                   = false;

        // ------------------------------------
        //  Fetch Revision if Necessary
        // ------------------------------------

        if (is_numeric($version_id)) {
            $entry_id = Request::input('entry_id');

            $revquery = DB::table('entry_versioning')
                ->select('version_data')
                ->where('entry_id', $entry_id)
                ->where('version_id', $version_id)
                ->first();

            if ($revquery) {
                $incoming = array_merge(unserialize($revquery->version_data), $incoming);
                $incoming['entry_id'] = $entry_id;
                $which = 'edit';
                $revision = true;
            }

            unset($revquery);
        }

        // ------------------------------------
        //  Remove that blased Token to insure it does not cause issues
        // ------------------------------------

        unset($incoming['_token']);

        // ------------------------------------
        //  We need to first determine which weblog to post the entry into.
        // ------------------------------------

        $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

        // if it's an edit, we just need the entry id and can figure out the rest
        if (!empty($entry_id)) {
            $query = DB::table('weblog_entries')
                ->select('weblog_id', 'entry_id')
                ->where('entry_id', Request::input('entry_id'))
                ->first();

            if ($query) {
                $weblog_id = $query->weblog_id;
                $which = 'edit';
            }
        }

        if (empty($weblog_id)) {
            if (is_numeric(Request::input('weblog_id'))) {
                $weblog_id = Request::input('weblog_id');
            } elseif (sizeof($assigned_weblogs) == 1) {
                $weblog_id = $assigned_weblogs[0];
            }
        }

        if ( empty($weblog_id) or ! is_numeric($weblog_id)) {
            return false;
        }

        // ------------------------------------
        //  Security check
        // ------------------------------------

        if ( ! in_array($weblog_id, $assigned_weblogs)) {
            return Cp::unauthorizedAccess(__('publish.unauthorized_for_this_blog'));
        }

        // ------------------------------------
        //  Fetch weblog preferences
        // ------------------------------------

        $query = DB::table('weblogs')->where('weblog_id', $weblog_id)->first();

        if (!$query) {
            return Cp::errorMessage(__('publish.no_weblog_exists'));
        }

        extract((array) $query);

        // --------------------------------------------------------------------
        //  Editing entry, if not valid Revision we load up the data fresh from DB
        //  - The $incoming variable takes precedent over the database data
        // --------------------------------------------------------------------

        if ($which == 'edit' && $revision === false)
        {
            // Fetch the weblog data
            $result = DB::table('weblog_entries')
                ->join('weblog_entry_data', 'weblog_entry_data.entry_id', '=', 'weblog_entries.entry_id')
                ->where('weblog_entries.entry_id', $entry_id)
                ->where('weblog_entries.weblog_id', $weblog_id)
                ->select('weblog_entries.*', 'weblog_entry_data.*')
                ->first();

            if (!$result) {
                return Cp::errorMessage(__('publish.no_weblog_exists'));
            }

            if ($result->author_id != Session::userdata('member_id')) {
                if ( ! Session::access('can_edit_other_entries')) {
                    return Cp::unauthorizedAccess();
                }
            }

            $incoming = array_merge((array) $result, $incoming);

            unset($result);
        }

        // ------------------------------------
        //  Categories
        // ------------------------------------

        if ($which == 'edit' && $revision === false && !Request::input('category'))
        {
            $query = DB::table('categories')
                ->join('weblog_entry_categories', 'weblog_entry_categories.category_id', '=', 'categories.category_id')
                ->whereIn('categories.group_id', explode('|', $cat_group))
                ->where('weblog_entry_categories.entry_id', $entry_id)
                ->select('categories.category_name', 'weblog_entry_categories.*')
                ->get();

            foreach ($query as $row) {
                $incoming['category'][] = $row->category_id;
            }
        }

        if ($which == 'new' && !Request::input('category')) {
            $incoming['category'][] = $default_category;
        }

        // ------------------------------------
        //  Extract $incoming
        // ------------------------------------

        extract($incoming);

        // ------------------------------------
        //  Versioning Enabled?
        // ------------------------------------

        $show_revision_cluster = ($enable_versioning == 'y') ? 'y' : 'n';

        $versioning_enabled = ($enable_versioning == 'y') ? 'y' : 'n';

        if ($submission_error) {
            $versioning_enabled = (Request::input('versioning_enabled')) ? 'y' : 'n';
        }

        // ------------------------------------
        //  Insane Idea to Have Defaults and Prefixes
        // ------------------------------------

        if ($which == 'edit') {
            $url_title_prefix = '';
        }

        if ($which == 'new' && empty($submission_error)) {
            $title      = '';
            $url_title  = $url_title_prefix;
        }

        // ------------------------------------
        //  Assign page title based on type of request
        // ------------------------------------

        Cp::$title = __('publish.'.$which.'_entry');

        Cp::$crumb = Cp::$title.Cp::breadcrumbItem($weblog_title);

        $CAL = new JsCalendar;
        Cp::$extra_header .= $CAL->calendar();

        // -------------------------------------
        //  Publish Page Title Focus
        // -------------------------------------

        if ($which == 'new') {
            $load_events = "$('#title').focus();displayCatLink();";
        } else {
            $load_events = 'displayCatLink();';
        }

        Cp::$body_props .= ' onload="activate_calendars();'.$load_events.'"';

        $r = '';

        // ------------------------------------
        //  Submission Error
        // ------------------------------------

        if (!empty($submission_error)) {
            $r .= '<h1 class="alert-heading">'.__('core.error').'</h1>'.PHP_EOL;
            $r .= '<div class="box alertBox" style="text-align:left">'.$submission_error.'</div>';
        }

        // ------------------------------------
        //  Saved Message
        // ------------------------------------

        if (Request('U') == 'saved') {
            $r .= '<div class="success-message" id="success-message" style="text-align:left">'.__('publish.entry_saved').'</div>';
        }

        // ------------------------------------
        //  Form header and hidden fields
        // ------------------------------------

        // http://kilvin.app/admin.php?C=WeblogAdministration&M=weblogsOverview

        $right_links[] = [BASE.'?C=WeblogAdministration&M=weblogsOverview', __('publish.Edit Layout')];
        $r  .= Cp::header('', $right_links);

        $r .= Cp::formOpen(
            [
                'action' => 'C=publish'.AMP.'M=submitNewEntry',
                'name'  => 'entryform',
                'id'    => 'entryform'
            ]
        );

        $r .= Cp::input_hidden('weblog_id', $weblog_id);

        if (!empty($entry_id)) {
            $r .= Cp::input_hidden('entry_id', $entry_id);
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

        $field_query = DB::table('weblog_fields')
                ->where('group_id', $field_group)
                ->orderBy('field_label')
                ->get()
                ->keyBy('field_name')
                ->toArray();

        // ------------------------------------
        //  Layout Array
        // ------------------------------------

        foreach($layout_query as $row) {

            if (!isset($layout[$row->tab_id])) {
                $layout[$row->tab_id] = [];
                $publish_tabs[$row->tab_id] = $row->tab_name;
            }

            if (isset($field_query[$row->field_name])) {
                $layout[$row->tab_id][$row->field_name] = $field_query[$row->field_name];
            }
        }

        $publish_tabs['meta']       = __('publish.meta');
        $publish_tabs['categories'] = __('publish.categories');
        $publish_tabs['revisions']  = __('publish.revisions');

        // ------------------------------------
        //  Javascript stuff
        // ------------------------------------

        $word_separator = Site::config('word_separator') != "dash" ? '_' : '-';

        // ------------------------------------
        //  Various Bits of JS
        // ------------------------------------

        $js = <<<EOT

<script type="text/javascript">

    // ------------------------------------
    //  Swap out categories
    // ------------------------------------

    function displayCatLink()
    {
        $('#cateditlink').css('display', 'block');
    }

    function swap_categories(str)
    {
        $('#categorytree').html(str);
    }

    $( document ).ready(function() {

        // ------------------------------------
        // Publish Option Tabs Open/Close
        // ------------------------------------
        $('.publish-tab-link').click(function(e){
            e.preventDefault();
            var active_tab = $(this).data('tab');

            $('.publish-tab-block').css('display', 'none');
            $('#publish_block_'+active_tab).css('display', 'block');

            $('.publish-tab-link').removeClass('selected');
            $(this).addClass('selected');
        });

        $('.publish-tab-link').first().trigger('click');

        // ------------------------------------
        // Toggle element hide/show (calendar mostly)
        // ------------------------------------
        $('.toggle-element').click(function(e){
            e.preventDefault();
            var id = $(this).data('toggle');

            id.split('|').forEach(function (item) {
                $('#' + item).toggle();
            });
        });

        // Quick Save
        $(window).keydown(function (e){
            if ((e.metaKey || e.ctrlKey) && e.keyCode == 83) { /*ctrl+s or command+s*/
                $('button[name=save]').click();
                e.preventDefault();
                return false;
            }
        });
    });

</script>
EOT;

        $r .=
            url_title_javascript($word_separator, $url_title_prefix).
            $js.
            PHP_EOL.
            PHP_EOL;

        // ------------------------------------
        //  NAVIGATION TABS
        // ------------------------------------

        if ($show_categories_tab != 'y') {
            unset($publish_tabs['categories']);
        }

        if ($show_revision_cluster != 'y') {
            unset($publish_tabs['revisions']);
        }

        $r .= '<ul class="publish-tabs">';

        foreach($publish_tabs as $short => $long)
        {
            $selected = ($short == 'form') ? 'selected' : '';

            $r .= PHP_EOL.
                '<li class="publish-tab">'.
                    '<a href="#" class="'.$selected.' publish-tab-link" data-tab="'.$short.'">'.
                         $long.
                    '</a>'.
                '</li>';
        }

        $r .= "</ul>";

        // ------------------------------------
        //  Title, URL Title, Save buttons
        //  - Always at top
        // ------------------------------------

        $r .= PHP_EOL.'<div class="publish-box">';
        $r .= $this->publishFormTitleCluster($entry_id, $title, $url_title, $field_group, $show_url_title);
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Meta TAB
        // ------------------------------------

        $r .= '<div id="publish_block_meta" class="publish-tab-block" style="display: none; padding:0; margin:0;">';
        $r .= PHP_EOL.'<div class="publish-box">';

        $r .= $this->publishFormDateBlock($which, $submission_error, $incoming);
        $r .= $this->publishFormOptionsBlock($which, $weblog_id, $author_id, $status, $sticky);

        $r .= '</div>'.PHP_EOL;
        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Categories TAB
        // ------------------------------------

        if ($show_categories_tab == 'n' && !empty($incoming['category'])) {
            foreach ($incoming['category'] as $cat_id) {
                $r .= Cp::input_hidden('category[]', $cat_id);
            }
        }

        if ($show_categories_tab == 'y') {
            $r .= '<div id="publish_block_categories" class="publish-tab-block" style="display: none; padding:0; margin:0;">';
            $r .= PHP_EOL.'<div class="publish-box">';

            $r .= $this->publishFormCategoriesBlock($cat_group, $incoming);

            $r .= '</div>'.PHP_EOL;
            $r .= '</div>'.PHP_EOL;
        }

        if ($show_categories_tab !== 'y') {
            if ($which == 'new' and $default_category != '') {
                $r .= Cp::input_hidden('category[]', $default_category);
            }
        }

        // ------------------------------------
        //  Revisions TAB
        // ------------------------------------

        if ($show_revision_cluster == 'y') {
            $r .= '<div id="publish_block_revisions" class="publish-tab-block" style="display: none; padding:0; margin:0;">';
            $r .= PHP_EOL.'<div class="publish-box">';

            $r .= $this->publishFormVersioningBlock($version_id, $entry_id, $versioning_enabled);

            $r .= '</div>'.PHP_EOL;
            $r .= '</div>'.PHP_EOL;
        }

        // ------------------------------------
        //  Layout/Field TABs
        // ------------------------------------

        // $layout[$row->tab][$frow->field_name] = $frow;

        foreach($layout as $tab => $fields) {

            $r .= '<div id="publish_block_'.$tab.'" class="publish-tab-block" style="display: none; padding:0; margin:0;">';
            $r .= PHP_EOL."<table border='0' cellpadding='0' cellspacing='0' style='width:100%'><tr><td class=''>";
            $r .= PHP_EOL.'<div class="publish-box">';

            // ------------------------------------
            //  Custom Fields for Tab
            // -----------------------------------

            foreach ($fields as $row) {
                $r .= $this->publishFormCustomField($which, $submission_error, $row, $incoming);
            }

            $r .= '</div>'.PHP_EOL;
            $r .= "</td></tr></table></div>";
        }

        // ------------------------------------
        //  End Form
        // ------------------------------------

        $r .= '</form>'.PHP_EOL;

        Cp::$body = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Entry Versioning Block
    *
    * @param integer $version_id
    * @param integer $entry_id
    * @param string $versioning_enabled
    * @return string
    */
    private function publishFormVersioningBlock($version_id, $entry_id, $versioning_enabled)
    {
        $r  = PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";
        $r .= PHP_EOL.'<td class="publishItemWrapper">'.BR;

        $revs_exist = false;

        if (is_numeric($entry_id))
        {
            $revquery = DB::table('entry_versioning AS v')
                ->select('v.author_id', 'v.version_id', 'v.version_date', 'm.screen_name')
                ->join('members AS m', 'v.author_id', '=', 'm.member_id')
                ->orderBy('v.version_id', 'desc')
                ->get();

            if ($revquery->count() > 0)
            {
                $revs_exist = true;

                $r .= Cp::tableOpen(['class' => 'tableBorder', 'width' => '100%']);
                $r .= Cp::tableRow([
                        ['text' => __('publish.revision'), 'class' => 'tableHeading', 'width' => '25%'],
                        ['text' => __('publish.rev_date'), 'class' => 'tableHeading', 'width' => '25%'],
                        ['text' => __('publish.rev_author'), 'class' => 'tableHeading', 'width' => '25%'],
                        ['text' => __('publish.load_revision'), 'class' => 'tableHeading', 'width' => '25%']
                    ]
                );

                $i = 0;
                $j = $revquery->count();

                foreach($revquery as $row)
                {
                    if ($row->version_id == $version_id) {
                        $revlink = Cp::quickDiv('highlight', __('publish.current_rev'));
                    } else {
                        $warning = "onclick=\"if(!confirm('".__('publish.revision_warning')."')) return false;\"";

                        $revlink = Cp::anchor(
                            BASE.'?C=edit'.AMP.
                                'M=editEntry'.AMP.
                                'entry_id='.$entry_id.AMP.
                                'version_id='.$row->version_id,
                                '<b>'.__('publish.load_revision').'</b>',
                                $warning);
                    }

                    $r .= Cp::tableRow([
                        ['text' => '<b>'.__('publish.revision').' '.$j.'</b>'],
                        ['text' => Localize::createHumanReadableDateTime($row->version_date)],
                        ['text' => $row->screen_name],
                        ['text' => $revlink]
                    ]
                );

                    $j--;
                } // End foreach

                $r .= '</table>'.PHP_EOL;
            }
        }

        if ($revs_exist == false) {
            $r .= Cp::quickDiv('highlight', __('publish.no_revisions_exist'));
        }

        $r .= Cp::quickDiv(
            'paddingTop',
            '<label>'.
                Cp::input_checkbox(
                    'versioning_enabled',
                    'y',
                    $versioning_enabled
                ).
            ' '.
            __('publish.versioning_enabled').
            '</label>'
        );

        $r .= "</tr></table>";

        return $r;
    }

    // --------------------------------------------------------------------

    /**
    * Categories Block for the Categories Tab
    *
    * @param string $cat_group
    * @param array $incoming
    * @return string
    */
    private function publishFormCategoriesBlock($cat_group, $incoming)
    {
        $r  = PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";
        $r .= PHP_EOL.'<td class="publishItemWrapper">'.BR;
        $r .= Cp::heading(__('publish.categories'), 5);

        // Normal Category Display
        $this->category_tree(
            $cat_group,
            (empty($incoming['category'])) ?
                [] :
                $incoming['category']
        );

        if (count($this->categories) == 0)
        {
            $r .= Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', __('publish.no_categories')), 'categorytree');
        }
        else
        {
            $r .= "<div id='categorytree'>";

            foreach ($this->categories as $val)
            {
                $r .= $val;
            }

            $r .= '</div>';
        }

        if ($cat_group != '' && (Session::access('can_edit_categories')))
        {
            $r .= '<div id="cateditlink" style="display: none; padding:0; margin:0;">';

            if (stristr($cat_group, '|'))
            {
                $catg_query = DB::table('category_groups')
                    ->whereIn('group_id', explode('|', $cat_group))
                    ->select('group_name', 'group_id')
                    ->get();

                $links = '';

                foreach($catg_query as $catg_row)
                {
                    $links .= Cp::anchorpop(
                        BASE.'?C=WeblogAdministration'.
                             AMP.'M=category_manager'.
                             AMP.'group_id='.$catg_row->group_id.
                             AMP.'cat_group='.$cat_group.
                             AMP.'Z=1',
                        '<b>'.$catg_row->group_name.'</b>'
                    ).', ';
                }

                $r .= Cp::quickDiv('littlePadding', '<b>'.__('publish.edit_categories').': </b>'.substr($links, 0, -2), '750');
            }
            else
            {
                $r .= Cp::quickDiv(
                    'littlePadding',
                    Cp::anchorpop(
                        BASE.'?C=WeblogAdministration'.
                            AMP.'M=category_manager'.
                            AMP.'group_id='.$cat_group.
                            AMP.'Z=1',
                        '<b>'.__('publish.edit_categories').'</b>',
                        '750'
                    )
                );
            }

            $r .= '</div>';
        }

        $r .= '</td>';
        $r .= "</tr></table>";

        return $r;
    }

    // --------------------------------------------------------------------

    /**
    * The Title and URL Title cluster for Publish Form
    * - Includes the save buttons
    *
    * @param integer $entry_id
    * @param string $title
    * @param string $url_title
    * @param string $field_group
    * @param string $show_url_title
    * @return string
    */
    private function publishFormTitleCluster($entry_id, $title, $url_title, $field_group, $show_url_title)
    {
        // Table + URL Title + Publish Buttons Table
        $r  = PHP_EOL."<table border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr><td>";

        $r .= Cp::quickDiv(
                'littlePadding',
                Cp::quickDiv('itemTitle', Cp::required().
                    NBS.
                    __('publish.title')).
                    Cp::input_text(
                        'title',
                        $title,
                        '20',
                        '100',
                        'input',
                        '100%',
                        (($entry_id == '') ? 'onkeyup="liveUrlTitle(\'#title\', \'#url_title\');"' : ''),
                        false
                )
            );

        // ------------------------------------
        //  "URL title" input Field
        //  - url_title_error triggers the showing of the field, even if supposed to be hidden
        // ------------------------------------

        if ($show_url_title == 'n' and $this->url_title_error === false) {
            $r .= Cp::input_hidden('url_title', $url_title);
        } else {
            $r .= Cp::quickDiv('littlePadding',
                  Cp::quickDiv('itemTitle', __('publish.url_title')).
                  Cp::input_text('url_title', $url_title, '20', '75', 'input', '100%')
            );
        }

        $r .= '</div>'.PHP_EOL;
        $r .= '</td>'.PHP_EOL;
        $r .= '<td style="width:350px;padding-top: 4px;" valign="top">'; // <--- someone is a GREAT developer

        // ------------------------------------
        //  Save
        // ------------------------------------

        $r .= Cp::div('publishButtonBox').
            '<button name="save" type="submit" value="save" class="option">'.
                'Quick Save <span style="font-size: 0.8em; letter-spacing: 1px;" class="shortcut">⌘S</span>'.
            '</button>'.
            NBS;

        $r .= (Request::input('C') == 'publish') ?
            Cp::input_submit(__('publish.save_and_finish'), 'submit') :
            Cp::input_submit(__('publish.save_and_finish'), 'submit');

        $r .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Upload link
        // ------------------------------------

        $r .= Cp::div('publishButtonBox');

        $r .= Cp::buttonpop(
                BASE.
                    '?C=publish'.
                    AMP.'M=uploadFileForm'.
                    AMP.'field_group='.$field_group.
                    AMP.'Z=1',
                    '⇪&nbsp;'.__('publish.upload_file'),
                '520',
                '600',
                'upload');

        $r .= "</td></tr></table>";

        return $r;
    }

    // --------------------------------------------------------------------

    /**
    * Create a Custom Field for Publish Form
    *
    * @param string $which
    * @param integer $weblog_id
    * @param object $row
    * @return string
    */
    private function publishFormCustomField($which, $submission_error, $row, $incoming)
    {
        $r = '';
        $field_data = '';

        if (isset($incoming['field_'.$row->field_name])) {
            $field_data = $incoming['field_'.$row->field_name];
        }

        $required = ($row->is_field_required == 'n') ? '' : Cp::required().NBS;

        // Enclosing DIV for each row
        $r .= Cp::div('publishRows');

        // ------------------------------------
        //  Instructions for Field
        // ------------------------------------

        if (trim($row->field_instructions) != '')
        {
            $r .= Cp::quickDiv(
                'littlePadding',
                '<h5>'.$required.$row->field_label.'</h5>'.
                 Cp::quickSpan(
                    'defaultBold',
                    __('publish.instructions')
                ).
                $row->field_instructions
            );
        } else {
             $r .= Cp::quickDiv(
                'littlePadding',
                '<h5>'.$required.$row->field_label.'</h5>'
            );
        }

        // @todo @stop
        // Custom Field Types - Create by Plugins, not figured out yet

        // Close outer DIV
        $r .= '</div>'.PHP_EOL;

        return $r;
    }


    // --------------------------------------------------------------------

    /**
    * The Options Block for Publish Form (sticky, weblog, status, author)
    *
    * @param string $which
    * @param integer $weblog_id
    * @param integer $author_id
    * @param string $status
    * @param string $sticky
    * @return string
    */
    private function publishFormOptionsBlock($which, $weblog_id, $author_id, $status, $sticky)
    {
        $query = DB::table('weblogs')->where('weblog_id', $weblog_id)->first();

        extract((array) $query);

        // ------------------------------------
        //  Author pull-down menu
        // ------------------------------------

        $menu_author = '';

        // First we'll assign the default author.
        if ($author_id == '') {
            $author_id = Session::userdata('member_id');
        }

        $menu_author .= Cp::input_select_header('author_id');
        $query = DB::table('members')
            ->where('member_id', $author_id)
            ->select('screen_name')
            ->first();

        $menu_author .= Cp::input_select_option($author_id, $query->screen_name);

        // Next we'll gather all the authors that are allowed to be in this list
        $query = DB::table('members')
            ->select('member_id', 'members.group_id', 'screen_name', 'members.group_id')
            ->join('member_group_preferences', 'member_group_preferences.group_id', '=', 'members.group_id')
            ->where('members.member_id', '!=', $author_id)
            ->where('member_group_preferences.value', 'y')
            ->whereIn('member_group_preferences.handle', ['in_authorlist', 'include_in_authorlist'])
            ->orderBy('screen_name', 'asc')
            ->get()
            ->unique();

        foreach ($query as $row) {
            if (Session::access('can_assign_post_authors')) {
                if (isset(Session::userdata('assigned_weblogs')[$weblog_id])) {
                    $selected = ($author_id == $row->member_id) ? 1 : '';
                    $menu_author .= Cp::input_select_option($row->member_id, $row->screen_name, $selected);
                }
            }
        }

        $menu_author .= Cp::input_select_footer();

        // ------------------------------------
        //  Weblog pull-down menu
        // ------------------------------------

        $menu_weblog = '';

        if($which == 'edit') {
            $query = DB::table('weblogs')
                ->select('weblog_id', 'weblog_title')
                ->where('status_group', $status_group)
                ->where('cat_group', $cat_group)
                ->where('field_group', $field_group)
                ->orderBy('weblog_title')
                ->get();

            if ($query->count() > 0) {
                foreach ($query as $row) {
                    if (in_array($row->weblog_id, Session::userdata('assigned_weblogs')))
                    {
                        if (isset($incoming['new_weblog']) && is_numeric($incoming['new_weblog'])) {
                            $selected = ($incoming['new_weblog'] == $row->weblog_id) ? 1 : '';
                        } else {
                            $selected = ($weblog_id == $row->weblog_id) ? 1 : '';
                        }

                        $menu_weblog .= Cp::input_select_option($row->weblog_id, escape_attribute($row->weblog_title), $selected);
                    }
                }

                if ($menu_weblog != '') {
                    $menu_weblog = Cp::input_select_header('new_weblog').$menu_weblog.Cp::input_select_footer();
                }
            }
        }

        // ------------------------------------
        //  Status pull-down menu
        // ------------------------------------

        $menu_status = '';

        if ($default_status == '') {
            $default_status = 'open';
        }

        if ($status == '') {
            $status = $default_status;
        }

        $menu_status .= Cp::input_select_header('status');

        // ------------------------------------
        //  Fetch disallowed statuses
        // ------------------------------------

        $no_status_access = [];

        if (Session::userdata('group_id') != 1) {
            $query = DB::table('status_id')
                ->select('status_id')
                ->where('member_group', Session::userdata('group_id'))
                ->get();

            foreach ($query as $row) {
                $no_status_access[] = $row->status_id;
            }
        }

        // ------------------------------------
        //  Create status menu
        //  - if no status group assigned, only Super Admins can create 'open' entries
        // ------------------------------------

        $query = DB::table('statuses')
            ->where('group_id', $status_group)
            ->orderBy('status_order')
            ->get();

        if ($query->count() == 0) {
            if (Session::userdata('group_id') == 1) {
                $menu_status .= Cp::input_select_option('open', __('publish.open'), ($status == 'open') ? 1 : '');
            }

            $menu_status .= Cp::input_select_option('closed', __('publish.closed'), ($status == 'closed') ? 1 : '');
        }  else {
            $no_status_flag = true;

            foreach ($query as $row) {
                $selected = ($status == $row->status) ? 1 : '';

                if (in_array($row->status_id, $no_status_access)) {
                    continue;
                }

                $no_status_flag = false;
                $status_name = ($row->status == 'open' OR $row->status == 'closed') ? __('publish.'.$row->status) : $row->status;
                $menu_status .= Cp::input_select_option(escape_attribute($row->status), escape_attribute($status_name), $selected);
            }

            // ------------------------------------
            //  No statuses?
            // ------------------------------------

            // If the current user is not allowed to submit any statuses
            // we'll set the default to closed

            if ($no_status_flag == true) {
                $menu_status .= Cp::input_select_option('closed', __('publish.closed'));
            }
        }

        $menu_status .= Cp::input_select_footer();

        // ------------------------------------
        //  Author, Weblog, Status, Sticky
        // ------------------------------------

        $meta  = PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";

        $meta .= PHP_EOL.'<td class="publishItemWrapper" valign="top">'.BR;
        $meta .= Cp::div('clusterLineR');
        $meta .= Cp::heading(NBS.__('publish.author'), 5);
        $meta .= $menu_author;
        $meta .= '</div>'.PHP_EOL;
        $meta .= '</td>';

        if ($menu_weblog != '')
        {
            $meta .= PHP_EOL.'<td class="publishItemWrapper" valign="top">'.BR;
            $meta .= Cp::div('clusterLineR');
            $meta .= Cp::heading(NBS.__('publish.weblog'), 5);
            $meta .= $menu_weblog;
            $meta .= '</div>'.PHP_EOL;
            $meta .= '</td>';
        }

        $meta .= PHP_EOL.'<td class="publishItemWrapper" valign="top">'.BR;
        $meta .= Cp::div('clusterLineR');
        $meta .= Cp::heading(NBS.__('publish.status'), 5);
        $meta .= $menu_status;
        $meta .= '</div>'.PHP_EOL;
        $meta .= '</td>';

        $meta .= PHP_EOL.'<td class="publishItemWrapper" valign="top">'.BR;
        $meta .= Cp::heading(NBS.__('publish.sticky'), 5);
        $meta .= '<label style="display:inline-block;margin-top:3px;">'.
                    Cp::input_checkbox('sticky', 'y', $sticky).' '.__('publish.make_entry_sticky').
                '</label>'.
            '</td>';

        $meta .= "</tr></table>";

        return $meta;
    }

    // --------------------------------------------------------------------

    /**
    * The Entry and Expiration Date Block for Publish Form
    *
    * @param string $which
    * @param string $submission_error
    * @param array $incoming
    * @return string
    */
    private function publishFormDateBlock($which, $submission_error, $incoming)
    {
        // ------------------------------------
        //  Entry and Expiration Date Calendars
        // ------------------------------------

        Cp::$extra_header .= '<script type="text/javascript">
        // depending on timezones, local settings and localization prefs, its possible for js to misinterpret the day,
        // but the humanized time is correct, so we activate the humanized time to sync the calendar

        function activate_calendars() {
            update_calendar(\'entry_date\', $(\'#entry_date\').val());
            update_calendar(\'expiration_date\', $(\'#expiration_date\').val());';

        Cp::$extra_header .= "\n\t\t\t\t"."current_month   = '';
            current_year    = '';
            last_date       = '';";

        Cp::$extra_header .= "\n".'}
        </script>';


        // ------------------------------------
        //  DATE BLOCK
        //  $entry_date - Always UTC
        //  $expiration_date - Always UTC
        //  $entry_date_string - Localized and formatted
        //  $expiration_date_string - empty OR Localized and formatted
        // ------------------------------------

        if (!empty($submission_error)) {
            // From POST!
            $entry_date_string      = $incoming['entry_date'];
            $expiration_date_string = $incoming['expiration_date'];

            $entry_date      =
                (empty($incoming['entry_date'])) ?
                Carbon::now() :
                Localize::humanReadableToUtcCarbon($incoming['entry_date']);

            $expiration_date =
                (empty($incoming['expiration_date'])) ?
                '' :
                Localize::humanReadableToUtcCarbon($incoming['expiration_date']);
        }
        elseif ($which == 'new')
        {
            $entry_date        = (empty($entry_date)) ? Carbon::now() : Carbon::parse($entry_date);
            $entry_date_string = Localize::createHumanReadableDateTime($entry_date);

            $expiration_date_string =
                (empty($expiration_date)) ?
                '' :
                Localize::createHumanReadableDateTime($expiration_date);
        }
        else
        {
            $entry_date        = Carbon::parse($incoming['entry_date']);
            $entry_date_string = Localize::createHumanReadableDateTime($entry_date);

            $expiration_date =
                (empty($incoming['expiration_date'])) ?
                '' :
                Carbon::parse($incoming['expiration_date']);

            $expiration_date_string =
                (empty($expiration_date)) ?
                '' :
                Localize::createHumanReadableDateTime($expiration_date);
        }

        $date_object     = $entry_date->copy();
        $date_object->tz = Site::config('site_timezone');
        $cal_entry_date  = $date_object->timestamp * 1000;

        $date_object     = (empty($expiration_date)) ? Carbon::now() : $expiration_date->copy();
        $date_object->tz = Site::config('site_timezone');
        $cal_expir_date  = $date_object->timestamp * 1000;

        // ------------------------------------
        //  Meta Tab
        //  - Entry Date + Expiration Date Calendar
        //  - Weblog, Status, and Author Pulldowns
        //  - Sticky Checkbox
        // ------------------------------------

        $meta = PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";

        // ------------------------------------
        //  Entry Date Field
        // ------------------------------------

        $meta .= '<td class="publishItemWrapper">'.BR;
        $meta .= Cp::div('clusterLineR');
        $meta .= Cp::div('defaultCenter');

        $meta .= Cp::heading(__('publish.entry_date'), 5);

        $cal_img =
            '<a href="#" class="toggle-element" data-toggle="calendar_entry_date|calendar_expiration_date">
                <span style="display:inline-block; height:25px; width:25px; vertical-align:top;">
                    '.Cp::calendarImage().'
                </span>
            </a>';

        $meta .= Cp::quickDiv(
            'littlePadding',
            Cp::input_text(
                'entry_date',
                $entry_date_string,
                '18',
                '23',
                'input',
                '150px',
                'onkeyup="update_calendar(\'entry_date\', this.value);" '
            ).
            $cal_img
        );

        $meta .= '<div id="calendar_entry_date" style="display:none;margin:4px 0 0 0;padding:0;">';
        $meta .= PHP_EOL.'<script type="text/javascript">

                var entry_date  = new calendar(
                                        "entry_date",
                                        new Date('.$cal_entry_date.'),
                                        true
                                        );

                document.write(entry_date.write());
                </script>';

        $meta .= '</div>';

        $meta .=
            Cp::div('littlePadding').
                '<a href="javascript:void(0);" onclick="set_to_now(\'entry_date\')" >'.
                    __('publish.today').
                '</a>'.
                NBS.'|'.NBS.
                '<a href="javascript:void(0);" onclick="clear_field(\'entry_date\');" >'.
                    __('cp.clear').
                '</a>'.
            '</div>'.PHP_EOL;

        $meta .= '</div>'.PHP_EOL;
        $meta .= '</div>'.PHP_EOL;
        $meta .= '</td>';

        // ------------------------------------
        //  Expiration Date Field
        // ------------------------------------

        $meta .= '<td class="publishItemWrapper">'.BR;
        $meta .= Cp::div('clusterLineR');
        $meta .= Cp::div('defaultCenter');

        $meta .= Cp::heading(__('publish.expiration_date'), 5);

        $cal_img =
            '<a href="#" class="toggle-element" data-toggle="calendar_entry_date|calendar_expiration_date">
                <span style="display:inline-block; height:25px; width:25px; vertical-align:top;">
                    '.Cp::calendarImage().'
                </span>
            </a>';

        $meta .= Cp::quickDiv(
            'littlePadding',
            Cp::input_text(
                'expiration_date',
                $expiration_date_string,
                '18',
                '23',
                'input',
                '150px',
                'onkeyup="update_calendar(\'expiration_date\', this.value);" '
            ).
            $cal_img
        );

        $meta .= '<div id="calendar_expiration_date" style="display:none;margin:4px 0 0 0;padding:0;">';
        $meta .= PHP_EOL.'<script type="text/javascript">

                var expiration_date  = new calendar(
                                        "expiration_date",
                                        new Date('.$cal_entry_date.'),
                                        true
                                        );

                document.write(expiration_date.write());
                </script>';

        $meta .= '</div>';

        $meta .=
            Cp::div('littlePadding').
                '<a href="javascript:void(0);" onclick="set_to_now(\'expiration_date\')" >'.
                    __('publish.today').
                '</a>'.
                NBS.'|'.NBS.
                '<a href="javascript:void(0);" onclick="clear_field(\'expiration_date\');" >'.
                    __('cp.clear').
                '</a>'.
            '</div>'.PHP_EOL;

        $meta .= '</div>'.PHP_EOL;
        $meta .= '</div>'.PHP_EOL;
        $meta .= '</td>';

        // END Calendar Table
        $meta .= "</tr></table>";

        return $meta;
    }

    // --------------------------------------------------------------------

    /**
    * Process an Entry Form submission
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function submitNewEntry()
    {
        $url_title  = '';
        $incoming   = Request::all();

        if ( ! $weblog_id = Request::input('weblog_id') OR ! is_numeric($weblog_id)) {
            return Cp::unauthorizedAccess();
        }

        $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

        // ------------------------------------
        //  Security check
        // ------------------------------------

        if ( ! in_array($weblog_id, $assigned_weblogs)) {
            return Cp::unauthorizedAccess();
        }

        // ------------------------------------
        //  Does entry ID exist?  And is valid for this weblog?
        // ------------------------------------

        if (($entry_id = Request::input('entry_id')) !== FALSE && is_numeric($entry_id)) {
            // we grab the author_id now as we use it later for author validation
            $query = DB::table('weblog_entries')
                ->select('entry_id', 'author_id')
                ->where('entry_id', $entry_id)
                ->where('weblog_id', $weblog_id)
                ->first();

            if (!$query)
            {
                return Cp::unauthorizedAccess();
            }

            $entry_id = $query->entry_id;
            $orig_author_id = $query->author_id;
        } else {
            $entry_id = '';
        }

        // ------------------------------------
        //  Weblog Switch?
        // ------------------------------------

        $old_weblog = '';

        if (($new_weblog = Request::input('new_weblog')) !== false && $new_weblog != $weblog_id)
        {
            $query = DB::table('weblogs')
                ->whereIn('weblog_id', [$weblog_id, $new_weblog])
                ->select('status_group', 'cat_group', 'field_group', 'weblog_id')
                ->get();

            if ($query->count() == 2)
            {
                if ($query->first()->status_group == $query->last()->status_group &&
                    $query->first()->cat_group == $query->last()->cat_group &&
                    $query->first()->field_group == $query->last()->field_group)
                {
                    if (Session::userdata('group_id') == 1) {
                        $old_weblog = $weblog_id;
                        $weblog_id = $new_weblog;
                    }
                    else
                    {
                        $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

                        if (in_array($new_weblog, $assigned_weblogs))
                        {
                            $old_weblog = $weblog_id;
                            $weblog_id = $new_weblog;
                        }
                    }
                }
            }
        }


        // ------------------------------------
        //  Fetch Weblog Prefs
        // ------------------------------------

        $query = DB::table('weblogs')
            ->where('weblog_id', $weblog_id)
            ->first();

        $weblog_title               = $query->weblog_title;
        $weblog_url                 = $query->weblog_url;
        $default_status             = $query->default_status;
        $enable_versioning          = $query->enable_versioning;
        $enable_qucksave_versioning = $query->enable_qucksave_versioning;
        $max_revisions              = $query->max_revisions;

         $notify_address            =
            ($query->weblog_notify == 'y' and !empty($query->weblog_notify_emails) )?
            $query->weblog_notify_emails :
            '';

        // ------------------------------------
        //  Error trapping
        // ------------------------------------

        $error = [];

        // ------------------------------------
        //  No entry title or title too long? Assign error.
        // ------------------------------------

        if ( ! $title = strip_tags(trim(Request::input('title')))) {
            $error[] = __('publish.missing_title');
        }

        if (strlen($title) > 100) {
            $error[] = __('publish.title_too_long');
        }

        // ------------------------------------
        //  No date? Assign error.
        // ------------------------------------

        if ( ! Request::input('entry_date')) {
            $error[] = __('publish.missing_date');
        }

        // ------------------------------------
        //  Convert the date to a Unix timestamp
        // ------------------------------------

        $entry_date = Localize::humanReadableToUtcCarbon(Request::input('entry_date'));

        if ( ! $entry_date instanceof Carbon) {
            if ($entry_date !== FALSE) {
                $error[] = $entry_date.NBS.'('.__('publish.entry_date').')';
            } else {
                $error[] = __('publish.invalid_date_formatting');
            }
        }

        // ------------------------------------
        //  Convert expiration date to a Unix timestamp
        // ------------------------------------

        if ( ! Request::input('expiration_date')) {
            $expiration_date = 0;
        } else {
            $expiration_date = Localize::humanReadableToUtcCarbon(Request::input('expiration_date'));

            if ( ! $expiration_date instanceof Carbon)
            {
                // Localize::humanReadableToUtcCarbon() returns verbose errors
                if ($expiration_date !== FALSE)
                {
                    $error[] = $expiration_date.NBS.'('.__('publish.expiration_date').')';
                }
                else
                {
                    $error[] = __('publish.invalid_date_formatting');
                }
            }
        }

        // ------------------------------------
        //  Are all requred fields filled out?
        // ------------------------------------

         $query = DB::table('weblog_fields')
            ->where('is_field_required', 'y')
            ->select('field_name', 'field_label')
            ->get();

         if ($query->count() > 0)
         {
            foreach ($query as $row)
            {
                if (empty($incoming['field_'.$row->field_name])) {
                    $error[] = __('publish.The following field is required').NBS.$row->field_label;
                }
            }
         }

        // ------------------------------------
        //  Are there any custom date fields?
        // ------------------------------------

        $query = DB::table('weblog_fields')
            ->where('field_type', 'date')
            ->select('field_name', 'field_name', 'field_label')
            ->get();

        foreach ($query as $row) {
            if (!empty($incoming['field_'.$row->field_name])) {
                $custom_date = Localize::humanReadableToUtcCarbon($incoming['field_'.$row->field_name]);

                // Localize::humanReadableToUtcCarbon() returns either a
                // valid Carbon object or a verbose error
                if ( ! $custom_date instanceof Carbon) {
                    if ($custom_date !== false) {
                        $error[] = $custom_date.NBS.'('.$row->field_label.')';
                    } else {
                        $error[] = __('publish.invalid_date_formatting');
                    }
                } else {
                    $incoming['field_'.$row->field_name] = $custom_date;
                }
            }
        }

        // ------------------------------------
        //  Is the title unique?
        // ------------------------------------

        if ($title != '') {
            // Do we have a URL title?
            $url_title = Request::input('url_title');

            if (!$url_title) {
                // Forces a lowercased version
                $url_title = create_url_title($title, true);
            }

            // Kill all the extraneous characters.
            // We want the URL title to be pure alpha text
            if ($entry_id != '') {
                $url_query = DB::table('weblog_entries')
                    ->select('url_title')
                    ->where('entry_id', $entry_id)
                    ->first();

                if ($url_query->url_title != $url_title) {
                    $url_title = create_url_title($url_title);
                }
            } else {
                $url_title = create_url_title($url_title);
            }

            // Is the url_title a pure number?  If so we show an error.
            if (is_numeric($url_title)) {
                $this->url_title_error = true;
                $error[] = __('publish.url_title_is_numeric');
            }

            // ------------------------------------
            //  Is the URL Title empty? Error!
            // ------------------------------------

            if (trim($url_title) == '')  {
                $this->url_title_error = true;
                $error[] = __('publish.unable_to_create_url_title');

                $msg = '';

                foreach($error as $val) {
                    $msg .= Cp::quickDiv('littlePadding', $val);
                }

                return $this->entryForm($msg);
            }

            // Is the url_title too long?  Warn them
            if (strlen($url_title) > 75)
            {
                $this->url_title_error = true;
                $error[] = __('publish.url_title_too_long');
            }

            // ------------------------------------
            //  Is URL title unique?
            // ------------------------------------

            // Field is limited to 75 characters, so trim url_title before querying
            $url_title = substr($url_title, 0, 75);

            $query = DB::table('weblog_entries')
                ->where('url_title', $url_title)
                ->where('weblog_id', $weblog_id)
                ->where('entry_id', '!=', $entry_id);

            $count = $query->count();

            if ($count > 0)
            {
                // We may need some room to add our numbers- trim url_title to 70 characters
                // Add hyphen separator
                $url_title = substr($url_title, 0, 70).'-';

                $recent = DB::table('weblog_entries')
                    ->select('url_title')
                    ->where('weblog_id', $weblog_id)
                    ->where('entry_id', '!=', $entry_id)
                    ->where('url_title', 'LIKE', $url_title.'%')
                    ->orderBy('url_title', 'desc')
                    ->first();

                $next_suffix = 1;

                if ($recent && preg_match("/\-([0-9]+)$/", $recent->url_title, $match)) {
                    $next_suffix = sizeof($match) + 1;
                }

                // Is the appended number going to kick us over the 75 character limit?
                if ($next_suffix > 9999) {
                    $url_create_error = true;
                    $error[] = __('publish.url_title_not_unique');
                }

                $url_title .= $next_suffix;

                $double_check = DB::table('weblog_entries')
                    ->where('url_title', $url_title)
                    ->where('weblog_id', $weblog_id)
                    ->where('entry_id', '!=', $entry_id)
                    ->count();

                if ($double_check > 0) {
                    $url_create_error = true;
                    $error[] = __('publish.unable_to_create_url_title');
                }
            }
        }

        // Did they name the URL title "index"?  That's a bad thing which we disallow
        if ($url_title == 'index') {
            $this->url_title_error = true;
            $error[] = __('publish.url_title_is_index');
        }

        // ------------------------------------
        //  Validate Author ID
        // ------------------------------------

        $author_id = ( ! Request::input('author_id')) ? Session::userdata('member_id'): Request::input('author_id');

        if ($author_id != Session::userdata('member_id') && ! Session::access('can_edit_other_entries'))
        {
            $error[] = __('core.not_authorized');
        }

        if (
            isset($orig_author_id) &&
            $author_id != $orig_author_id &&
            (! Session::access('can_edit_other_entries') OR ! Session::access('can_assign_post_authors'))
        )
        {
            $error[] = __('core.not_authorized');
        }

        if ($author_id != Session::userdata('member_id') && Session::userdata('group_id') != 1)
        {
            // we only need to worry about this if the author has changed
            if (! isset($orig_author_id) OR $author_id != $orig_author_id)
            {
                if (! Session::access('can_assign_post_authors'))
                {
                    $error[] = __('core.not_authorized');
                }
                else
                {
                    $allowed_authors = [];

                    $query = DB::table('members')
                        ->select('members.member_id')
                        ->join('member_groups', 'member_groups.group_id', '=', 'member.group_id')
                        ->where(function($q)
                        {
                            $q->where('members.in_authorlist', 'y')->orWhere('member_groups.include_in_authorlist', 'y');
                        })
                        ->get();

                    if ($query->count() > 0)
                    {
                        foreach ($query as $row)
                        {
                            // Is this a "user blog"?  If so, we'll only allow
                            // authors if they are assigned to this particular blog

                            if (Session::userdata('weblog_id') != 0)
                            {
                                if ($row->weblog_id == $weblog_id)
                                {
                                    $allowed_authors[] = $row->member_id;
                                }
                            }
                            else
                            {
                                $allowed_authors[] = $row->member_id;
                            }
                        }
                    }

                    if (! in_array($author_id, $allowed_authors))
                    {
                        $error[] = __('publish.invalid_author');
                    }
                }
            }
        }

        // ------------------------------------
        //  Validate status
        // ------------------------------------

        $status = (Request::input('status') == null) ? $default_status : Request::input('status');

        if (Session::userdata('group_id') != 1)
        {
            $disallowed_statuses = [];
            $valid_statuses = [];

            $query = DB::table('statuses AS s')
                ->select('s.status_id', 's.status')
                ->join('status_groups AS sg', 'sg.group_id', '=', 's.group_id')
                ->leftJoin('weblogs AS w', 'w.status_group', '=', 'sg.group_id')
                ->where('w.weblog_id', $weblog_id)
                ->get();

            if ($query->count() > 0)
            {
                foreach ($query as $row)
                {
                    $valid_statuses[$row->status_id] = strtolower($row->status); // lower case to match MySQL's case-insensitivity
                }
            }

            $query = DB::table('status_no_access')
                ->join('statuses', 'statuses.status_id', '=', 'status_no_access.status_id')
                ->where('status_no_access.member_group', Session::userdata('group_id'))
                ->select('status_no_access', 'statuses')
                ->get();

            if ($query->count() > 0)
            {
                foreach ($query as $row)
                {
                    $disallowed_statuses[$row->status_id] = strtolower($row->status); // lower case to match MySQL's case-insensitivity
                }

                $valid_statuses = array_diff_assoc($valid_statuses, $disallowed_statuses);
            }

            if (! in_array(strtolower($status), $valid_statuses))
            {
                // if there are no valid statuses, set to closed
                $status = 'closed';
            }
        }

        // ------------------------------------
        //  Do we have an error to display?
        // ------------------------------------

         if (count($error) > 0)
         {
            $msg = '';

            foreach($error as $val)
            {
                $msg .= Cp::quickDiv('littlePadding', $val);
            }

            return $this->entryForm($msg);
         }

        // ------------------------------------
        //  Fetch Categories
        // ------------------------------------

        if (isset($incoming['category']) and is_array($incoming['category']))
        {
            foreach ($incoming['category'] as $cat_id) {
                $this->cat_parents[] = $cat_id;
            }

            if ($this->assign_cat_parent == true) {
                $this->fetch_category_parents($incoming['category']);
            }
        }

        // $this->cat_parents will be used for saving
        unset($incoming['category']);

        // ------------------------------------
        //  Build our query data
        // ------------------------------------

        if ($enable_versioning == 'n')
        {
            $version_enabled = 'y';
        }
        else
        {
            $version_enabled = (Request::input('versioning_enabled')) ? 'y' : 'n';
        }

        $data = [
            'entry_id'                  => null,
            'weblog_id'                 => $weblog_id,
            'author_id'                 => $author_id,
            'url_title'                 => $url_title,
            'entry_date'                => $entry_date,
            'updated_at'                => Carbon::now(),
            'versioning_enabled'        => $version_enabled,
            'expiration_date'           => (empty($expiration_date)) ? null : $expiration_date,
            'sticky'                    => (Request::input('sticky') == 'y') ? 'y' : 'n',
            'status'                    => $status,
        ];

        // ------------------------------------
        //  Insert the entry
        // ------------------------------------

        if ($entry_id == '')
        {
            $data['created_at'] = Carbon::now();
            $entry_id = DB::table('weblog_entries')->insertGetId($data);

            // ------------------------------------
            //  Insert the custom field data
            // ------------------------------------

            $cust_fields = [
                'entry_id' => $entry_id,
                'weblog_id' => $weblog_id,
                'title'     => $title,
                'locale'    => 'en_US' // @todo - For now!
            ];

            foreach ($incoming as $key => $val)
            {
                if (substr($key, 0, 6) == 'field_') {
                    $cust_fields[$key] = (empty($val)) ? null : $val;
                }
            }

            // Save the custom field data
            if (count($cust_fields) > 0) {
                DB::table('weblog_entry_data')->insert($cust_fields);
            }

            // ------------------------------------
            //  Update member stats
            // ------------------------------------

            if ($data['author_id'] == Session::userdata('member_id')) {
                $total_entries = Session::userdata('total_entries') +1;
            } else {
                $total_entries = DB::table('members')
                    ->where('member_id', $data['author_id'])
                    ->value('total_entries') + 1;
            }

            DB::table('members')
                ->where('member_id', $data['author_id'])
                ->update(['total_entries' => $total_entries, 'last_entry_date' => Carbon::now()]);

            // ------------------------------------
            //  Set page title and success message
            // ------------------------------------

            $type = 'new';
            $page_title = 'publish.entry_has_been_added';
            $message = __($page_title);

            // ------------------------------------
            //  Admin Notification of New Weblog Entry
            // ------------------------------------

            if (!empty($notify_address)) {

                $notify_ids = explode(',', $notify_address);

                // Remove author
                $notify_ids = array_diff($notify_ids, [Session::userdata('member_id')]);

                if (!empty($notify_ids)) {

                    $members = Member::whereIn('member_id', $notify_ids)->get();

                    if ($members->count() > 0) {
                        Notification::send($members, new NewEntryAdminNotify($entry_id, $notify_address));
                    }
                }
            }
        }
        else
        {
            // ------------------------------------
            //  Update an existing entry
            // ------------------------------------

            // First we need to see if the author of the entry has changed.

            $query = DB::table('weblog_entries')
                ->select('author_id')
                ->where('entry_id', $entry_id)
                ->first();

            $old_author = $query->author_id;

            if ($old_author != $data['author_id'])
            {
                // Lessen the counter on the old author
                $query = DB::table('members')->select('total_entries')->where('member_id', $old_author);

                $total_entries = $query->total_entries - 1;

                DB::table('members')->where('member_id', $old_author)
                    ->update(['total_entries' => $total_entries]);


                // Increment the counter on the new author
                $query = DB::table('members')->select('total_entries')->where('member_id', $data['author_id']);

                $total_entries = $query->total_entries + 1;

                DB::table('members')->where('member_id', $data['author_id']) ->update(['total_entries' => $total_entries]);
            }

            // ------------------------------------
            //  Update the entry
            // ------------------------------------

            unset($data['entry_id']);

            DB::table('weblog_entries')
                ->where('entry_id', $entry_id)
                ->update($data);

            // ------------------------------------
            //  Update the custom fields
            // ------------------------------------

            $cust_fields =
            [
                'weblog_id' =>  $weblog_id,
                'title'     => $title
            ];

            foreach ($incoming as $key => $val) {
                if (substr($key, 0, 6) == 'field_')
                {
                    $cust_fields[$key] = (empty($val)) ? null : $val;
                }
            }

            DB::table('weblog_entry_data')->where('entry_id', $entry_id)->update($cust_fields);

            // ------------------------------------
            //  Delete categories
            //  - We will resubmit all categories next
            // ------------------------------------

            DB::table('weblog_entry_categories')->where('entry_id', $entry_id)->delete();

            // ------------------------------------
            //  Set page title and success message
            // ------------------------------------

            $type = 'update';
            $page_title = 'publish.entry_has_been_updated';
            $message = __($page_title);
        }

        // ------------------------------------
        //  Insert categories
        // ------------------------------------

        if ($this->cat_parents > 0)
        {
            $this->cat_parents = array_unique($this->cat_parents);

            sort($this->cat_parents);

            foreach($this->cat_parents as $val)
            {
                if ($val != '')
                {
                    DB::table('weblog_entry_categories')
                        ->insert(
                            [
                                'entry_id'      => $entry_id,
                                'category_id'   => $val
                            ]);
                }
            }
        }

        // ------------------------------------
        //  Save revisions if needed
        // ------------------------------------

        if (!Request::input('versioning_enabled')) {
            $enable_versioning = 'n';
        }

        if (Request::filled('save') and $enable_qucksave_versioning == 'n') {
            $enable_versioning = 'n';
        }

        if ($enable_versioning == 'y')
        {
            $version_data =
            [
                'entry_id'     => $entry_id,
                'weblog_id'    => $weblog_id,
                'author_id'    => Session::userdata('member_id'),
                'version_date' => Carbon::now(),
                'version_data' => serialize(Request::all())
            ];


            DB::table('entry_versioning')->insert($version_data);

            // Clear old revisions if needed
            $max = (is_numeric($max_revisions) AND $max_revisions > 0) ? $max_revisions : 10;

            $version_count = DB::table('entry_versioning')->where('entry_id', $entry_id)->count();

            // Prune!
            if ($version_count > $max)
            {
                $ids = DB::table('entry_versioning')
                    ->select('version_id')
                    ->where('entry_id', $entry_id)
                    ->orderBy('version_id', 'desc')
                    ->limit($max)
                    ->pluck('version_id')
                    ->all();

                if (!empty($ids)) {
                    DB::table('entry_versioning')
                        ->whereNotIn('version_id', $ids)
                        ->where('entry_id', $entry_id)
                        ->delete();
                }
            }
        }

        //---------------------------------
        // Quick Save Returns Here
        //  - does not update stats
        //  - does not empty caches
        //---------------------------------

        if (isset($incoming['save'])) {
            $loc = '?C=edit&M=editEntry&weblog_id='.$weblog_id.'&entry_id='.$entry_id.'&U=saved';
            return redirect($loc);
        }

        // ------------------------------------
        //  Update global stats
        // ------------------------------------

        if ($old_weblog != '')
        {
            Stats::update_weblog_stats($old_weblog);
        }

        Stats::update_weblog_stats($weblog_id);

        // ------------------------------------
        //  Clear caches if needed
        // ------------------------------------

        if (Site::config('new_posts_clear_caches') == 'y') {
            cms_clear_caching('all');
        }

        // ------------------------------------
        //  Redirect to ths "success" page
        // ------------------------------------

        $loc = '?C=edit&U='.$type;

        return redirect($loc);
    }

    // --------------------------------------------------------------------

    /**
    * Fetch the Parents for the Categories
    *
    * @param array The array of cats to find the parents for
    * @return void
    */
    public function fetch_category_parents($cat_array = '')
    {
        if (count($cat_array) == 0) {
            return;
        }

        $query = DB::table('categories')
            ->select('parent_id')
            ->whereIn('category_id', $cat_array)
            ->get();

        if ($query->count() == 0) {
            return;
        }

        $temp = [];

        foreach ($query as $row)
        {
            if ($row->parent_id != 0)
            {
                $this->cat_parents[] = $row->parent_id;

                $temp[] = $row->parent_id;
            }
        }

        $this->fetch_category_parents($temp);
    }

    // --------------------------------------------------------------------

    /**
    * Builds the Categories into their Nested <select> form for Publish page
    *
    * @param integer $group_id The category group number
    * @param array $selected The array of category ids selected in form
    * @return void
    */
    public function category_tree($group_id = '', $selected = [])
    {
        // Fetch category group ID number
        if ($group_id == '')
        {
            if ( ! $group_id = Request::input('group_id')) {
                return false;
            }
        }

        $catarray = [];

        if (is_array($selected)) {
            foreach ($selected as $val) {
                $catarray[$val] = $val;
            }
        }

        // Fetch category groups
        if ( ! is_numeric(str_replace('|', '', $group_id))) {
            return false;
        }

        $query = DB::table('categories')
            ->whereIn('group_id', explode('|', $group_id))
            ->orderBy('group_id')
            ->orderBy('parent_id')
            ->orderBy('category_order')
            ->select('category_name', 'category_id', 'parent_id', 'group_id')
            ->get();

        if ($query->count() == 0) {
            return false;
        }

        // Assign the result to multi-dimensional array
        foreach($query as $row) {
            $cat_array[$row->category_id] = [
                $row->parent_id,
                $row->category_name,
                $row->group_id
            ];
        }

        $size = count($cat_array) + 1;

        $this->categories[] = Cp::input_select_header('category[]', 1, $size);

        // Build our output...

        $sel = '';

        foreach($cat_array as $key => $val)
        {
            if (0 == $val[0])
            {
                if (isset($last_group) && $last_group != $val['2'])
                {
                    $this->categories[] = Cp::input_select_option('', '-------');
                }

                $sel = (isset($catarray[$key])) ? '1' : '';

                $this->categories[] = Cp::input_select_option($key, $val[1], $sel);
                $this->category_subtree($key, $cat_array, $depth=1, $selected);

                $last_group = $val['2'];
            }
        }

        $this->categories[] = Cp::input_select_footer();
    }

    // --------------------------------------------------------------------

    /**
    * Recursive method to build nested categories
    *
    * @param integer $cat_id The parent category_id
    * @param array $cat_array The array of all categories
    * @param integer $depth The current depth
    * @param array The selected categories
    * @return void
    */
    private function category_subtree($cat_id, $cat_array, $depth, $selected = [])
    {
        $spcr = "&nbsp;";
        $catarray = [];

        if (is_array($selected))
        {
            foreach ($selected as $key => $val)
            {
                $catarray[$val] = $val;
            }
        }

        $indent = $spcr.$spcr.$spcr.$spcr;

        if ($depth == 1)
        {
            $depth = 4;
        }
        else
        {
            $indent = str_repeat($spcr, $depth).$indent;

            $depth = $depth + 4;
        }

        $sel = '';

        foreach ($cat_array as $key => $val)
        {
            if ($cat_id == $val[0])
            {
                $pre = ($depth > 2) ? "&nbsp;" : '';

                $sel = (isset($catarray[$key])) ? '1' : '';

                $this->categories[] = Cp::input_select_option($key, $pre.$indent.$spcr.$val[1], $sel);
                $this->category_subtree($key, $cat_array, $depth, $selected);
            }
        }
    }


//=====================================================================
//  "Edit" Page
//=====================================================================


    // --------------------------------------------------------------------

    /**
    * Edit Entries page
    *
    * @param integer $weblog_id The weblog_id to load
    * @param string $message A message to display on page
    * @return void
    */
    public function editEntries($message = '')
    {
        Cp::$title  = __('publish.edit_weblog_entries');
        Cp::$crumb  = __('publish.edit_weblog_entries');
        Cp::$body   = $this->listEntries($message);
    }

    // --------------------------------------------------------------------

    /**
    * List Entries page
    *
    * @param string $message A message to display on page
    * @return void
    */
    public function listEntries($message = '')
    {
        // Security check
        if ( ! Session::access('can_access_edit')) {
            return Cp::unauthorizedAccess();
        }

        $allowed_blogs = array_keys(Session::userdata('assigned_weblogs'));

        if (empty($allowed_blogs)) {
            return Cp::unauthorizedAccess(__('publish.no_weblogs'));
        }

        $total_blogs = count($allowed_blogs);

        // ------------------------------------
        //  Determine Weblog(s) to Show
        // ------------------------------------

        $weblog_id = Request::input('weblog_id');

        if ($weblog_id == 'null' OR $weblog_id === false OR ! is_numeric($weblog_id)) {
            $weblog_id = '';
        }

        $cat_group = '';
        $cat_id = Request::input('category_id');
        $status = Request::input('status');
        $order  = Request::input('order');
        $date_range = Request::input('date_range');

        // ------------------------------------
        //  Begin Page Output
        // ------------------------------------

        $message = '';

        if ($U = Request::input('U')) {
            $message = ($U == 'new') ? __('publish.entry_has_been_added') : __('publish.entry_has_been_updated');
        }

        $r = '';

        // Do we have a message to show?
        // Note: a message is displayed on this page after editing or submitting a new entry
        if (Request::input('U') == 'mu') {
            $message = __('publish.multi_entries_updated');
        }

        if ($message != '') {
            $r .= Cp::quickDiv('success-message', $message);
        }

        $r .= Cp::quickDiv('tableHeading', __('publish.edit_weblog_entries'));

        // Declare the "filtering" form
        $s = Cp::formOpen(
            [
                'action'    => 'C=edit'.AMP.'M=listEntries',
                'name'      => 'filterform',
                'id'        => 'filterform'
            ]
        );

        // If we have more than one weblog we'll write the JavaScript menu switching code
        if ($total_blogs > 1) {
            $s .= $this->editFilteringMenus();
        }

        // Table start
        $s .= Cp::div('box');
        $s .= Cp::table('', '0', '', '100%').
              '<tr>'.PHP_EOL.
              Cp::td('littlePadding', '', '7').PHP_EOL;

        // ------------------------------------
        //  Weblog Pulldown
        //  - Each weblog has its assigned categories/statuses so we updated the form when weblog chosen
        // ------------------------------------

        if ($total_blogs > 1)
        {
            $s .= "<select name='weblog_id' class='select' onchange='changeFilterMenu();'>\n";
        }
        else
        {
            $s .= "<select name='weblog_id' class='select'>\n";
        }


        // Weblog selection pull-down menu
        // Fetch the names of all weblogs and write each one in an <option> field

        $query = DB::table('weblogs')
            ->select('weblog_title', 'weblog_id', 'cat_group');

        // If the user is restricted to specific blogs, add that to the query
        if (Session::userdata('group_id') != 1) {
            $query->whereIn('weblog_id', $allowed_blogs);
        }

        $query = $query->orderBy('weblog_title')->get();

        if ($query->count() == 1)
        {
            $weblog_id = $query->first()->weblog_id;
            $cat_group = $query->first()->cat_group;
        }
        elseif($weblog_id != '')
        {
            foreach($query as $row) {
                if ($row->weblog_id == $weblog_id) {
                    $weblog_id = $row->weblog_id;
                    $cat_group = $row->cat_group;
                }
            }
        }

        $s .= Cp::input_select_option('null', __('publish.filter_by_weblog'));

        if ($query->count() > 1)
        {
            $s .= Cp::input_select_option('null',  __('cp.all'));
        }

        $selected = '';

        foreach ($query as $row)
        {
            if ($weblog_id != '')
            {
                $selected = ($weblog_id == $row->weblog_id) ? 'y' : '';
            }

            $s .= Cp::input_select_option($row->weblog_id, $row->weblog_title, $selected);
        }

        $s .= Cp::input_select_footer().
              '&nbsp;';

        // ------------------------------------
        //  Category Pulldown
        // ------------------------------------

        $s .= Cp::input_select_header('category_id').
              Cp::input_select_option('', __('publish.filter_by_category'));

        if ($total_blogs > 1)
        {
            $s .= Cp::input_select_option('all', __('cp.all'), ($cat_id == 'all') ? 'y' : '');
        }

        $s .= Cp::input_select_option('none', __('publish.none'), ($cat_id == 'none') ? 'y' : '');

        if ($cat_group != '')
        {
            $query = DB::table('categories')
                ->select('category_id', 'category_name', 'group_id', 'parent_id');

            if ($this->nest_categories == 'y') {
                $query->orderBy('group_id')->orderBy('parent_id');
            }

            $query = $query->orderBy('category_name')->get();

            $categories = [];

            if ($query->count() > 0) {
                foreach ($query as $row) {
                    $categories[] = [$row->group_id, $row->category_id, $row->category_name, $row->parent_id];
                }

                if ($this->nest_categories == 'y') {
                    $this->cat_array = [];

                    foreach($categories as $key => $val)
                    {
                        if (0 == $val['3'])
                        {
                            $this->cat_array[] = array($val[0], $val[1], $val['2']);
                            $this->category_edit_subtree($val[1], $categories, $depth=1);
                        }
                    }
                } else {
                    $this->cat_array = $categories;
                }
            }

            foreach($this->cat_array as $key => $val) {
                if ( ! in_array($val[0], explode('|',$cat_group))) {
                    unset($this->cat_array[$key]);
                }
            }

            foreach ($this->cat_array as $ckey => $cat)
            {
                if ($ckey-1 < 0 OR ! isset($this->cat_array[$ckey-1]))
                {
                    $s .= Cp::input_select_option('', '-------');
                }

                $s .= Cp::input_select_option($cat['1'], str_replace('!-!', '&nbsp;', $cat['2']), (($cat_id == $cat['1']) ? 'y' : ''));

                if (isset($this->cat_array[$ckey+1]) && $this->cat_array[$ckey+1]['0'] != $cat['0'])
                {
                    $s .= Cp::input_select_option('', '-------');
                }
            }
        }

        $s .= Cp::input_select_footer().'&nbsp;';

        // ------------------------------------
        //  Status Pulldown
        // ------------------------------------

        $s .= Cp::input_select_header('status').
              Cp::input_select_option('', __('publish.filter_by_status')).
              Cp::input_select_option('all', __('cp.all'), ($status == 'all') ? 1 : '');

        if ($weblog_id != '')
        {
            $rez = DB::table('weblogs')
                ->select('status_group')
                ->where('weblog_id', $weblog_id)
                ->first();

            $query = DB::table('statuses')
                ->select('status')
                ->where('group_id', $rez->status_group)
                ->orderBy('status_order')
                ->get();

            if ($query->count() > 0)
            {
                foreach ($query as $row)
                {
                    $selected = ($status == $row->status) ? 1 : '';
                    $status_name = ($row->status == 'closed' OR $row->status == 'open') ?  __('publish.'.$row->status) : $row->status;
                    $s .= Cp::input_select_option($row->status, $status_name, $selected);
                }
            }
        }
        else
        {
             $s .= Cp::input_select_option('open', __('publish.open'), ($status == 'open') ? 1 : '');
             $s .= Cp::input_select_option('closed', __('publish.closed'), ($status == 'closed') ? 1 : '');
        }

        $s .= Cp::input_select_footer().
              '&nbsp;';

        // ------------------------------------
        //  Date Range Pulldown
        // ------------------------------------

        $sel_1 = ($date_range == '1')   ? 1 : '';
        $sel_2 = ($date_range == '7')   ? 1 : '';
        $sel_3 = ($date_range == '31')  ? 1 : '';
        $sel_4 = ($date_range == '182') ? 1 : '';
        $sel_5 = ($date_range == '365') ? 1 : '';

        $s .= Cp::input_select_header('date_range').
              Cp::input_select_option('', __('publish.date_range')).
              Cp::input_select_option('1', __('publish.today'), $sel_1).
              Cp::input_select_option('7', __('publish.past_week'), $sel_2).
              Cp::input_select_option('31', __('publish.past_month'), $sel_3).
              Cp::input_select_option('182', __('publish.past_six_months'), $sel_4).
              Cp::input_select_option('365', __('publish.past_year'), $sel_5).
              Cp::input_select_option('', __('publish.any_date')).
              Cp::input_select_footer().
              '&nbsp;';

        // ------------------------------------
        //  Order By Pulldown
        // ------------------------------------

        $options = [
            'entry_date-asc'  => __('publish.ascending'),
            'entry_date-desc' => __('publish.descending'),
            'title-asc'      => __('publish.title_asc'),
            'title-desc'      => __('publish.title_desc'),
        ];

        $s .= Cp::input_select_header('order').
              Cp::input_select_option('', __('publish.order'));

        foreach( $options as $k => $v) {
            $s .= Cp::input_select_option(
                $k,
                $v,
                ($order == $k));
        }


        $s .= Cp::input_select_footer().
              '&nbsp;';

        // ------------------------------------
        //  Per Page Pulldown
        // ------------------------------------

        if ( ! ($perpage = Request::input('perpage')) && session()->has('perpage')) {
            $perpage = session('perpage');
        }

        if (empty($perpage)) {
            $perpage = 50;
        }

        session('perpage', $perpage);

        $s .= Cp::input_select_header('perpage').
              Cp::input_select_option('25', '25 '.__('publish.results'), ($perpage == 25)  ? 1 : '').
              Cp::input_select_option('50', '50 '.__('publish.results'), ($perpage == 50)  ? 1 : '').
              Cp::input_select_option('75', '75 '.__('publish.results'), ($perpage == 75)  ? 1 : '').
              Cp::input_select_option('100', '100 '.__('publish.results'), ($perpage == 100)  ? 1 : '').
              Cp::input_select_option('150', '150 '.__('publish.results'), ($perpage == 150)  ? 1 : '').
              Cp::input_select_footer().
              '&nbsp;';

        $s .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL;

        // ------------------------------------
        //  New Row! Keywords!
        // ------------------------------------

        $s .= '<tr>'.PHP_EOL.
              Cp::td('littlePadding', '', '7').PHP_EOL;

        $keywords = '';

        // Form Keywords
        if (Request::filled('keywords')) {
            $keywords = Request::input('keywords');
        }

        // Pagination Keywords
        if (Request::filled('pkeywords')) {
            $keywords = trim(base64_decode(Request::input('pkeywords')));
        }

        // IP Search! WHEE!
        if (substr(strtolower($keywords), 0, 3) == 'ip:')
        {
            $keywords = str_replace('_','.',$keywords);
        }

        $exact_match = (Request::input('exact_match') != '') ? Request::input('exact_match') : '';

        $s .= Cp::div('default').__('publish.keywords').NBS;
        $s .= Cp::input_text('keywords', $keywords, '40', '200', 'input', '200px').NBS;
        $s .= Cp::input_checkbox('exact_match', 'yes', $exact_match).NBS.__('publish.exact_match').NBS;

        $search_in = (Request::input('search_in') != '') ? Request::input('search_in') : 'title';

        $s .= Cp::input_select_header('search_in').
              Cp::input_select_option('title', __('publish.title_only'), ($search_in == 'title') ? 1 : '').
              Cp::input_select_option('body', __('publish.title_and_body'), ($search_in == 'body') ? 1 : '').
              Cp::input_select_footer().
              '&nbsp;';

        // ------------------------------------
        //  Submit! Submit!
        // ------------------------------------

        $s .= Cp::input_submit(__('publish.search'), 'submit');
        $s .= '</div>'.PHP_EOL;

        $s .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL;
        $s .= '</div>'.PHP_EOL;
        $s .= '</form>'.PHP_EOL;


        $r .= $s;

        // ------------------------------------
        //  Fetch the searchable fields
        // ------------------------------------

        $fields = [];

        $query = DB::table('weblogs');

        if ($weblog_id != '') {
            $query->where('weblog_id', $weblog_id);
        }

        $field_groups = $query->pluck('field_group')->all();

        if (!empty($field_groups)) {
            $fields = DB::table('weblog_fields')
                ->whereIn('group_id', $field_groups)
                ->whereIn('field_type', ['text', 'textarea', 'select'])
                ->pluck('field_name')
                ->all();
        }

        // ------------------------------------
        //  Build the main query
        // ------------------------------------

        $pageurl = BASE.'?C=edit'.AMP.'M=listEntries';

        $search_query = DB::table('weblog_entries')
            ->join('weblogs', 'weblog_entries.weblog_id', '=', 'weblogs.weblog_id')
            ->join('weblog_entry_data', 'weblog_entries.entry_id', '=', 'weblog_entry_data.entry_id')
            ->leftJoin('members', 'members.member_id', '=', 'weblog_entries.author_id')
            ->select('weblog_entries.entry_id');

        // ---------------------------------------
        //  JOINS
        // ---------------------------------------

        if ($cat_id == 'none' or (!empty($cat_id) && is_numeric($cat_id)))
        {
            $search_query->leftJoin('weblog_entry_categories', 'weblog_entries.entry_id', '=', 'weblog_entry_categories.entry_id')
                  ->leftJoin('categories', 'weblog_entry_categories.category_id', '=', 'categories.category_id');
        }

        // ---------------------------------------
        //  Limit to weblogs assigned to user
        // ---------------------------------------

        $search_query->whereIn('weblog_entries.weblog_id', $allowed_blogs);

        if ( ! Session::access('can_edit_other_entries') AND ! Session::access('can_view_other_entries')) {
            $search_query->where('weblog_entries.author_id', Session::userdata('member_id'));
        }

        // ---------------------------------------
        //  Exact Values
        // ---------------------------------------

        if ($weblog_id) {
            $pageurl .= AMP.'weblog_id='.$weblog_id;

            $search_query->where('weblog_entries.weblog_id', $weblog_id);
        }

        if ($date_range) {
            $pageurl .= AMP.'date_range='.$date_range;

            $search_query->where('weblog_entries.entry_date', '>', Carbon::now()->subDays($date_range));
        }

        if (is_numeric($cat_id)) {
            $pageurl .= AMP.'category_id='.$cat_id;

            $search_query->where('weblog_entry_categories.category_id', $cat_id);
        }

        if ($cat_id == 'none') {
            $pageurl .= AMP.'category_id='.$cat_id;

            $search_query->whereNull('weblog_entry_categories.entry_id');
        }

        if ($status && $status != 'all') {
            $pageurl .= AMP.'status='.$status;

            $search_query->where('weblog_entries.status', $status);
        }

        // ---------------------------------------
        //  Keywords
        // ---------------------------------------

        if ($keywords != '')
        {
            $search_query = $this->editKeywordsSearch($search_query, $keywords, $search_in, $exact_match, $fields);

            $pageurl .= AMP.'pkeywords='.base64_encode($keywords);

            if ($exact_match == 'yes')
            {
                $pageurl .= AMP.'exact_match=yes';
            }

            $pageurl .= AMP.'search_in='.$search_in;
        }

        // ---------------------------------------
        //  Order By!
        // ---------------------------------------

        if ($order) {
            $pageurl .= AMP.'order='.$order;

            switch ($order)
            {
                case 'entry_date-asc'   : $search_query->orderBy('entry_date', 'asc');
                    break;
                case 'entry_date-desc'  :  $search_query->orderBy('entry_date', 'desc');
                    break;
                case 'title-asc'        :  $search_query->orderBy('title', 'asc');
                    break;
                case 'title-desc'       :  $search_query->orderBy('title', 'desc');
                    break;
                default                 :  $search_query->orderBy('entry_date', 'desc');
            }
        } else {
             $search_query->orderBy('entry_date', 'desc');
        }

        // For entries with the same date, we add Title in there to insure
        // consistency in the displaying of results
        $search_query->orderBy('title', 'desc');

        // ------------------------------------
        //  Are there results?
        // ------------------------------------

        $total_query = clone $search_query;

        $total_count = $total_query->count();

        if ($total_count == 0)
        {
            $r .= Cp::quickDiv('highlight', BR.__('publish.no_entries_matching_that_criteria'));

            Cp::$title = __('cp.edit').Cp::breadcrumbItem(__('publish.edit_weblog_entries'));
			Cp::$body  = $r;
			Cp::$crumb = __('publish.edit_weblog_entries');
			return;
        }

        // Get the current row number and add the LIMIT clause to the SQL query
        if ( ! $rownum = Request::input('rownum')) {
            $rownum = 0;
        }

        // ------------------------------------
        //  Run the query again, fetching ID numbers
        // ------------------------------------

        $query = clone $search_query;
        $query = $query->offset($rownum)->limit($perpage)->get();

        $pageurl .= AMP.'perpage='.$perpage;

        $entry_ids = $query->pluck('entry_id')->all();

        // ------------------------------------
        //  Fetch the weblog information we need later
        // ------------------------------------

        $w_array = DB::table('weblogs')
            ->pluck('weblog_name', 'weblog_id')
            ->all();

        $r .= Cp::magicCheckboxesJavascript();

        // Build the item headings
        // Declare the "multi edit actions" form
        $r .= Cp::formOpen(
           [
                'action' => 'C=edit'.AMP.'M=entriesEditForm',
                'name'  => 'target',
                'id'    => 'target'
            ]
        );

        // ------------------------------------
        //  Build the output table
        // ------------------------------------

        $r  .=
            Cp::table('tableBorderNoTop row-hover', '0', '', '100%').
            '<tr>'.PHP_EOL.
                Cp::tableCell('tableHeadingAlt', '#').
                Cp::tableCell('tableHeadingAlt', __('publish.title')).
                Cp::tableCell('tableHeadingAlt', __('publish.author')).
                Cp::tableCell('tableHeadingAlt', __('publish.entry_date')).
                Cp::tableCell('tableHeadingAlt', __('publish.weblog')).
                Cp::tableCell('tableHeadingAlt', __('publish.status')).
                Cp::tableCell('tableHeadingAlt', Cp::input_checkbox('toggle_all')).
            '</tr>'.
            PHP_EOL;

        // ------------------------------------
        //  Build and run the full SQL query
        // ------------------------------------

        $query = DB::table('weblog_entries')
            ->leftJoin('weblogs', 'weblog_entries.weblog_id', '=', 'weblogs.weblog_id')
            ->leftJoin('weblog_entry_data', 'weblog_entries.entry_id', '=', 'weblog_entry_data.entry_id')
            ->leftJoin('members', 'members.member_id', '=', 'weblog_entries.author_id')
            ->select(
                'weblog_entries.entry_id',
                'weblog_entries.weblog_id',
                'weblog_entry_data.title',
                'weblog_entries.author_id',
                'weblog_entries.status',
                'weblog_entries.entry_date',
                'weblogs.live_look_template',
                'members.email',
                'members.screen_name')
            ->whereIn('weblog_entries.entry_id', $entry_ids);

        // ---------------------------------------
        //  Order By!
        // ---------------------------------------

        if ($order) {
            switch ($order)
            {
                case 'entry_date-asc'   : $query->orderBy('entry_date', 'asc');
                    break;
                case 'entry_date-desc'  :  $query->orderBy('entry_date', 'desc');
                    break;
                case 'title-asc'        :  $query->orderBy('title', 'asc');
                    break;
                case 'title-desc'       :  $query->orderBy('title', 'desc');
                    break;
                default                 :  $query->orderBy('entry_date', 'desc');
            }
        } else {
             $query->orderBy('entry_date', 'desc');
        }

        // For entries with the same date, we add Title in there to insure
        // consistency in the displaying of results
        $query->orderBy('title', 'desc');

        $query = $query->get();

        // load the site's templates
        $templates = [];

        $tquery = DB::table('templates')
        	->join('sites', 'sites.site_id', '=', 'templates.site_id')
            ->select('templates.folder', 'templates.template_name', 'templates.template_id', 'sites.site_name')
            ->orderBy('templates.folder')
            ->orderBy('templates.template_name')
            ->get();


        foreach ($tquery as $row) {
            $templates[$row->template_id] = $row->site_name.': '.$row->folder.'/'.$row->template_name;
        }

        // Loop through the main query result and write each table row

        $i = 0;

        foreach($query as $row)
        {
            $tr  = '<tr>'.PHP_EOL;

            // Entry ID number
            $tr .= Cp::tableCell('', $row->entry_id);

            // Weblog entry title (view entry)
            $tr .= Cp::tableCell('',
                Cp::anchor(
                    BASE.'?C=edit'.AMP.'M=editEntry'.AMP.'weblog_id='.$row->weblog_id.AMP.'entry_id='.$row->entry_id,
                    '<b>'.$row->title.'</b>'
                )
            );

            // Username
            $name = Cp::anchor('mailto:'.$row->email, $row->screen_name, 'title="Send an email to '.$row->screen_name.'"');

            $tr .= Cp::tableCell('', $name);
            $tr .= Cp::td().
                Cp::quickDiv(
                    'noWrap',
                    Localize::createHumanReadableDateTime($row->entry_date)
                ).
                '</td>'.PHP_EOL;

            // Weblog
            $tr .= Cp::tableCell('', (isset($w_array[$row->weblog_id])) ? Cp::quickDiv('noWrap', $w_array[$row->weblog_id]) : '');

            // Status
            $tr .= Cp::td();
            $tr .= $row->status;
            $tr .= '</td>'.PHP_EOL;

            // Delete checkbox
            $tr .= Cp::tableCell('', Cp::input_checkbox('toggle[]', $row->entry_id, '' , ' id="delete_box_'.$row->entry_id.'"'));

            $tr .= '</tr>'.PHP_EOL;
            $r .= $tr;

        } // End foreach

        $r .= '</table>'.PHP_EOL;

        $r .= Cp::table('', '0', '', '100%');
        $r .= '<tr>'.PHP_EOL.
              Cp::td();

        // Pass the relevant data to the paginate class
        $r .=  Cp::div('crumblinks').
               Cp::pager(
                            $pageurl,
                            $total_count,
                            $perpage,
                            $rownum,
                            'rownum'
                          ).
              '</div>'.PHP_EOL.
              '</td>'.PHP_EOL.
              Cp::td('defaultRight');

        $r .= Cp::input_hidden('pageurl', base64_encode($pageurl));

        // Delete button
        $r .= Cp::div('littlePadding');

        $r .= Cp::input_submit(__('cp.submit'));

        $r .= NBS.Cp::input_select_header('action').
              Cp::input_select_option('edit', __('publish.edit_selected')).
              Cp::input_select_option('delete', __('publish.delete_selected')).
              Cp::input_select_option('edit', '------').
              Cp::input_select_option('add_categories', __('publish.add_categories')).
              Cp::input_select_option('remove_categories', __('publish.remove_categories')).
              Cp::input_select_footer();

        $r .= '</div>'.PHP_EOL;

        $r .= '</td>'.PHP_EOL.
              '</tr>'.PHP_EOL.
              '</table>'.PHP_EOL.
              '</form>'.PHP_EOL;

        // Set output data
        return Cp::$body = $r;
    }

    // --------------------------------------------------------------------

    /**
     * Keywords search for Edit Page
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $keywords
     * @param string $search_in title/body/everywhere
     * @param string $exact_match  yes/no
     * @param array $fields
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function editKeywordsSearch($query, $keywords, $search_in, $exact_match, $fields)
    {
        return $query->where(function($q) use ($keywords, $search_in, $exact_match, $fields)
        {
            if ($exact_match == 'yes')
            {
                $q->where('weblog_entry_data.title', '=', $keywords);
            }
            else
            {
                $q->where('weblog_entry_data.title', 'LIKE', '%'.$keywords.'%');
            }

            if ($search_in == 'body' OR $search_in == 'everywhere')
            {
                foreach ($fields as $val)
                {
                    if ($exact_match == 'yes')
                    {
                        $q->orWhere('weblog_entry_data.field_'.$val, '=', $keywords);
                    }
                    else
                    {
                        $q->where('weblog_entry_data.field_'.$val, 'LIKE', '%'.$keywords.'%');
                    }
                }
            }
        });
    }

    // --------------------------------------------------------------------

    /**
    * Category dropdown for "Edit" section
    *
    * @param string $cat_id Category ID building from
    * @param array $categories List of all categories
    * @param integer $depth The current depth
    * @return void
    */
    public function category_edit_subtree($cat_id, $categories, $depth)
    {
        $spcr = '!-!';

        $indent = $spcr.$spcr.$spcr.$spcr;

        if ($depth == 1)
        {
            $depth = 4;
        }
        else
        {
            $indent = str_repeat($spcr, $depth).$indent;

            $depth = $depth + 4;
        }

        $sel = '';

        foreach ($categories as $key => $val)
        {
            if ($cat_id == $val['3'])
            {
                $pre = ($depth > 2) ? $spcr : '';

                $this->cat_array[] = array($val[0], $val[1], $pre.$indent.$spcr.$val['2']);

                $this->category_edit_subtree($val[1], $categories, $depth);
            }
        }
    }

    // --------------------------------------------------------------------

    /**
    * JS Filtering code for "Edit" page
    *
    * @return string
    */
    private function editFilteringMenus()
    {
        // ------------------------------------
        //  All Categories
        // ------------------------------------

        $query = DB::table('categories')
            ->select('category_id', 'category_name', 'group_id', 'parent_id');

        if ($this->nest_categories == 'y') {
            $query->orderBy('group_id')
                ->orderBy('parent_id');
        }

        $query = $query->orderBy('category_name')->get();

        $categories = [];

        if ($query->count() > 0) {
            foreach ($query as $row) {
                $categories[] = [$row->group_id, $row->category_id, $row->category_name, $row->parent_id];
            }

            if ($this->nest_categories == 'y')
            {
                foreach($categories as $key => $val)
                {
                    if (0 == $val[3])
                    {
                        $this->cat_array[] = [$val[0], $val[1], $val[2]];
                        $this->category_edit_subtree($val[1], $categories, $depth=1);
                    }
                }
            }
            else
            {
                $this->cat_array = $categories;
            }
        }

        // ------------------------------------
        //  All Statuses
        // ------------------------------------

        $query = DB::table('statuses')
            ->orderBy('status_order')
            ->select('group_id', 'status')
            ->get();

        foreach ($query as $row)
        {
            $statuses[$row->group_id][]  = $row->status;
        }

        // ------------------------------------
        //  Build Weblogs Array - Simplified
        // ------------------------------------

        $weblogs = [];

        $allowed_blogs = array_keys(Session::userdata('assigned_weblogs'));

        if (count($allowed_blogs) > 0)
        {
            $query = DB::table('weblogs')
                ->select('weblog_id', 'cat_group', 'status_group');

            if (Session::userdata('group_id') != 1) {
                $query->whereIn('weblog_id', $allowed_blogs);
            }

            $query = $query->orderBy('weblog_title')->get();

            foreach ($query as $row)
            {
                $weblogs[$row->weblog_id] = [
                    'categories' => [],
                    'statuses'   => []
                ];

                if (!empty($statuses[$row->status_group])) {
                    foreach($statuses[$row->status_group] as $status) {
                        $weblogs[$row->weblog_id]['statuses'][] =
                            '<option value="">'.$status.'</option>';
                    }
                }

                if (!empty($row->cat_group)) {
                    $groups = explode('|', $row->cat_group);

                    foreach($groups as $group) {

                        $weblogs[$row->weblog_id]['categories'][] =
                                '<option value="">------</option>';

                        foreach($this->cat_array as $v) {
                            if($v[0] != $group) {
                                continue;
                            }

                            $weblogs[$row->weblog_id]['categories'][] =
                                '<option value="'.$v[1].'">'.addslashes($v[2]).'</option>';

                        }
                    }
                }
            }
        }

        ob_start();

?>

<script type="text/javascript">

function changeFilterMenu()
{
    var categories = new Array();
    var statuses   = new Array();

    var c = 0;
    var s = 0;

    categories[c] = '<option value=""><?php echo __('cp.all'); ?></option>'; c++;
    categories[c] = '<option value="none"><?php echo __('publish.none'); ?></option>'; c++;
    statuses[s]   = '<option value="all"><?php echo __('cp.all'); ?></option>'; s++;

    var blog = $('select[name=weblog_id]').first().val();

    if (blog == "null")
    {
        statuses[s] = '<option value="open">open</option>'; s++;
        statuses[s] = '<option value="open">closed</option>'; s++;
    }

<?php foreach ($weblogs as $weblog_id => $groups) { ?>

    if (blog == <?php echo $weblog_id; ?>)
    {
        <?php foreach($groups['categories'] as $option) { ?>

            categories[c] = '<?php echo $option;?>'; c++;

        <?php } ?>

        <?php foreach($groups['statuses'] as $option) { ?>

            statuses[s] = '<?php echo $option;?>'; s++;

        <?php } ?>
    }

    <?php } ?>


    spaceString = eval("/!-!/g");

    $('select[name=category_id] option').remove();
    $('select[name=status] option').remove();

    var _select = $('select[name=category_id]');

    for (i = 0; i < categories.length; i++)
    {
        _select.append(categories[i].replace(spaceString, String.fromCharCode(160)));
    }

    var _select = $('select[name=status]');

    for (i = 0; i < statuses.length; i++)
    {
        _select.append(statuses[i]);
    }

}

</script>

<?php

        $javascript = ob_get_contents();

        ob_end_clean();

        return $javascript;
    }

    // --------------------------------------------------------------------

    /**
    * Simple Multi-Entry Edit Form
    *
    * @return string
    */
    public function entriesEditForm()
    {
        if ( ! Session::access('can_access_edit')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! in_array(Request::input('action'), ['edit', 'delete', 'add_categories', 'remove_categories'])) {
            return Cp::unauthorizedAccess();
        }

        if ( ! Request::filled('toggle')) {
            return $this->editEntries();
        }

        if (Request::input('action') == 'delete') {
            return $this->deleteEntriesConfirm();
        }

        // ------------------------------------
        //  Fetch the entry IDs
        // ------------------------------------

        foreach (Request::input('toggle') as $key => $val) {
            if (!empty($val) && is_numeric($val)) {
                $entry_ids[] = $val;
            }
        }

        if (empty($entry_ids)) {
            return Cp::unauthorizedAccess(__('publish.unauthorized_to_edit'));
        }

        // ------------------------------------
        //  Build and run the query
        // ------------------------------------

        $base_query = DB::table('weblog_entries AS t')
            ->join('weblog_entry_data AS d', 'd.entry_id', '=', 't.entry_id')
            ->join('weblogs AS w', 'w.weblog_id', '=', 't.weblog_id')
            ->select('t.entry_id',
                't.weblog_id',
                't.author_id',
                'd.title',
                't.url_title',
                't.entry_date',
                't.status',
                't.sticky')
            ->whereIn('t.weblog_id', array_keys(Session::userdata('assigned_weblogs')))
            ->orderBy('entry_date', 'asc');

        $query = clone $base_query;
        $query = $query->whereIn('t.entry_id', $entry_ids)->get();

        // ------------------------------------
        //  Security check...
        // ------------------------------------

        // Before we show anything we have to make sure that the user is allowed to
        // access the blog the entry is in, and if the user is trying
        // to edit an entry authored by someone else they are allowed to

        $weblog_ids     = [];
        $disallowed_ids = [];
        $assigned_weblogs = array_keys(Session::userdata('assigned_weblogs'));

        foreach ($query as $row)
        {
            if (! Session::access('can_edit_other_entries') && $row->author_id != Session::userdata('member_id'))
            {
               $disallowed_ids = $row->entry_id;
            } else {
                $weblog_ids[] = $row->weblog_id;
            }
        }

        // ------------------------------------
        //  Are there disallowed posts?
        //  - If so, we have to remove them....
        // ------------------------------------

        if (count($disallowed_ids) > 0)
        {
            $disallowed_ids = array_unique($disallowed_ids);

            $new_ids = array_diff($entry_ids, $disallowed_ids);

            // After removing the disallowed entry IDs are there any left?
            if (count($new_ids) == 0) {
                return Cp::unauthorizedAccess(__('publish.unauthorized_to_edit'));
            }

            // Run the query one more time with the proper IDs.
            $query = clone $base_query;
            $query = $query->whereIn('t.entry_id', $new_ids)->get();
        }

        // ------------------------------------
        //  Adding/Removing of Categories Breaks Off to Their Own Function
        // ------------------------------------

        if (Request::input('action') == 'add_categories') {
            return $this->multi_categories_edit('add', $query);
        } elseif (Request::input('action') == 'remove_categories') {
            return $this->multi_categories_edit('remove', $query);
        }

        // ------------------------------------
        //  Fetch the status details for weblogs
        // ------------------------------------

        $weblog_query = DB::table('weblogs')
            ->select('weblog_id', 'status_group', 'default_status')
            ->whereIn('weblog_id', $weblog_ids)
            ->get();

        // ------------------------------------
        //  Fetch disallowed statuses
        // ------------------------------------

        $no_status_access = [];

        if (Session::userdata('group_id') != 1) {
            $no_status_access = DB::table('status_id')
                ->select('status_id')
                ->where('member_group', Session::userdata('group_id'))
                ->pluck('status_id')
                ->all();
        }

        // ------------------------------------
        //  Build the output
        // ------------------------------------

        $r  = Cp::formOpen(array('action' => 'C=edit'.AMP.'M=updateMultipleEntries'));
        $r .= '<div class="tableHeading">'.__('publish.multi_entry_editor').'</div>';

        if (Request::input('pageurl')) {
            $r .= Cp::input_hidden('redirect', Request::input('pageurl'));
        }

        foreach ($query as $row) {
            $r .= Cp::input_hidden('entry_id['.$row->entry_id.']', $row->entry_id);
            $r .= Cp::input_hidden('weblog_id['.$row->entry_id.']', $row->weblog_id);

            $r .= PHP_EOL.'<div class="publish-box">';

            $r .= PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";

            $r .= PHP_EOL.'<td class="publishItemWrapper" valign="top" style="width:45%;">'.BR;
            $r .= Cp::div('clusterLineR');

            $r .= Cp::heading(__('publish.title'), 5).
                  Cp::input_text('title['.$row->entry_id.']', $row->title, '20', '100', 'input', '95%');

            $r .= Cp::quickDiv('defaultSmall', NBS);

            $r .= Cp::heading(__('publish.url_title'), 5).
                  Cp::input_text('url_title['.$row->entry_id.']', $row->url_title, '20', '75', 'input', '95%');

            $r .= '</div>'.PHP_EOL;
            $r .= '</td>';

            // ------------------------------------
            //  Status pull-down menu
            // ------------------------------------

            $status_queries = [];
            $status_menu = '';

            foreach ($weblog_query as $weblog_row)
            {
                if ($weblog_row->weblog_id != $row->weblog_id) {
                    continue;
                }

                $status_query = DB::table('statuses')
                    ->where('group_id', $weblog_row->status_group)
                    ->orderBy('status_order')
                    ->get();

                $menu_status = '';

                if ($status_query->count() == 0)
                {
                    // No status group assigned, only Super Admins can create 'open' entries
                    if (Session::userdata('group_id') == 1)
                    {
                        $menu_status .= Cp::input_select_option('open', __('cp.open'), ($row->status == 'open') ? 1 : '');
                    }

                    $menu_status .= Cp::input_select_option('closed', __('cp.closed'), ($row->status == 'closed') ? 1 : '');
                }
                else
                {
                    $no_status_flag = true;

                    foreach ($status_query as $status_row)
                    {
                        $selected = ($row->status == $status_row->status) ? 1 : '';

                        if (in_array($status_row->status_id, $no_status_access))
                        {
                            continue;
                        }

                        $no_status_flag = false;

                        $status_name =
                            ($status_row->status == 'open' OR $status_row->status == 'closed') ?
                            __('publish.'.$status_row->status) :
                            escape_attribute($status_row->status);

                        $menu_status .= Cp::input_select_option(escape_attribute($status_row->status), $status_name, $selected);
                    }

                    // ------------------------------------
                    //  No Statuses? Default is Closed
                    // ------------------------------------

                    if ($no_status_flag == TRUE) {
                        $menu_status .= Cp::input_select_option('closed', __('cp.closed'));
                    }
                }

                $status_menu = $menu_status;
            }

            $r .= PHP_EOL.'<td class="publishItemWrapper" valign="top" style="width:25%;">'.BR;
            $r .= Cp::div('clusterLineR');
            $r .= Cp::heading(__('publish.entry_status'), 5);
            $r .= Cp::input_select_header('status['.$row->entry_id.']');
            $r .= $status_menu;
            $r .= Cp::input_select_footer();

            $r .= Cp::div('paddingTop');
            $r .= Cp::heading(__('publish.entry_date'), 5);
            $r .= Cp::input_text('entry_date['.$row->entry_id.']', Localize::createHumanReadableDateTime($row->entry_date), '18', '23', 'input', '150px');
            $r .= '</div>'.PHP_EOL;

            $r .= '</div>'.PHP_EOL;
            $r .= '</td>';

            $r .= PHP_EOL.'<td class="publishItemWrapper" valign="top" style="width:30%;">'.BR;

            $r .= Cp::heading(NBS.__('publish.sticky'), 5);
            $r .= '<label>'.
                Cp::input_checkbox('sticky['.$row->entry_id.']', 'y', $row->sticky).
                ' '.
                __('publish.sticky').
            '</label>';

            $r .= '</td>';

            $r .= "</tr></table>";

            $r .= '</div>'.PHP_EOL;
        }

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('cp.update'))).
              '</form>'.PHP_EOL;

        Cp::$title = __('publish.multi_entry_editor');
        Cp::$crumb = __('publish.multi_entry_editor');
        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Multi-Entry Edit submission processing
    *
    * @return string|\Illuminate\Http\RedirectResponse
    */
    public function updateMultipleEntries()
    {
        if ( ! Session::access('can_access_edit')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! is_array(Request::input('entry_id')) or ! is_array(Request::input('weblog_id'))) {
            return Cp::unauthorizedAccess();
        }

        $titles      = Request::input('title');
        $url_titles  = Request::input('url_title');
        $entry_dates = Request::input('entry_date');
        $statuses    = Request::input('status');
        $stickys     = Request::input('sticky');
        $weblog_ids  = Request::input('weblog_id');

        foreach (Request::input('entry_id') as $id)
        {
            $weblog_id = $weblog_ids[$id];

            if(empty($titles[$id])) {
                continue;
            }

            $data = [
                'title'             => strip_tags($titles[$id]),
                'url_title'         => $url_titles[$id],
                'entry_date'        => $entry_dates[$id],
                'status'            => $statuses[$id],
                'sticky'            => (isset($stickys[$id]) AND $stickys[$id] == 'y') ? 'y' : 'n',
            ];

            $error = [];

            // ------------------------------------
            //  No entry title? Assign error.
            // ------------------------------------

            if ($data['title'] == '') {
                $error[] = __('publish.missing_title');
            }

            // ------------------------------------
            //  Is the title unique?
            // ------------------------------------

            if ($data['title'] != '') {
                // ------------------------------------
                //  Do we have a URL title?
                // ------------------------------------

                // If not, create one from the title
                if ($data['url_title'] == '') {
                    // Forces a lower case
                    $data['url_title'] = create_url_title($data['title'], true);
                }

                // Kill all the extraneous characters.
                // We want the URL title to pure alpha text
                $data['url_title'] = create_url_title($data['url_title']);

                // Is the url_title a pure number?  If so we show an error.
                if (is_numeric($data['url_title'])) {
                    $error[] = __('publish.url_title_is_numeric');
                }

                // Field is limited to 75 characters, so trim url_title before unique work below
                $data['url_title'] = substr($data['url_title'], 0, 70);

                // ------------------------------------
                //  Is URL title unique?
                // ------------------------------------

                $unique = false;
                $i = 0;

                while ($unique == false)
                {
                    $temp = ($i == 0) ? $data['url_title'] : $data['url_title'].'-'.$i;
                    $i++;

                    $unique_query = DB::table('weblog_entries')
                        ->where('url_title', $temp)
                        ->where('weblog_id', $weblog_id);

                    if ($id != '') {
                        $unique_query->where('entry_id', '!=', $id);
                    }

                     if ($unique_query->count() == 0) {
                        $unique = true;
                     }

                     // Safety
                     if ($i >= 50) {
                        $error[] = __('publish.url_title_not_unique');
                        break;
                     }
                }

                $data['url_title'] = $temp;
            }

            // ------------------------------------
            //  No date? Assign error.
            // ------------------------------------

            if ($data['entry_date'] == '') {
                $error[] = __('publish.missing_date');
            }

            // ------------------------------------
            //  Convert the date to a Unix timestamp
            // ------------------------------------

            $data['entry_date'] = Localize::humanReadableToUtcCarbon($data['entry_date']);

            if ( ! $data['entry_date'] instanceof Carbon) {
                $error[] = __('publish.invalid_date_formatting');
            }

            // ------------------------------------
            //  Do we have an error to display?
            // ------------------------------------

             if (count($error) > 0)
             {
                $msg = '';

                foreach($error as $val)
                {
                    $msg .= Cp::quickDiv('littlePadding', $val);
                }

                return Cp::errorMessage($msg);
             }

            // ------------------------------------
            //  Update the entry
            // ------------------------------------

             DB::table('weblog_entry_data')
                ->where('entry_id', $id)
                ->update(['title' => $data['title']]);

            unset($data['title']);

            DB::table('weblog_entries')
                ->where('entry_id', $id)
                ->update($data);
        }

        // ------------------------------------
        //  Clear caches if needed
        // ------------------------------------

        if (Site::config('new_posts_clear_caches') == 'y') {
            cms_clear_caching('all');
        }

        if (Request::filled('redirect') && ($redirect = base64_decode(Request::input('redirect'))) !== FALSE) {
            return redirect($redirect);
        } else {
            return redirect('?C=edit&U=mu');
        }
    }

    // --------------------------------------------------------------------

    /**
    * Add/Remove Categories to/from Multiple Entries form
    *
    * @param string $type Edit or Remove?
    * @param object $query
    * @return string
    */
    public function multi_categories_edit($type, $query)
    {
        if ( ! Session::access('can_access_edit')) {
            return Cp::unauthorizedAccess();
        }

        if ($query->count() == 0) {
            return Cp::unauthorizedAccess(__('publish.unauthorized_to_edit'));
        }

        // ------------------------------------
        //  Fetch the cat_group
        // ------------------------------------

        $sql = "SELECT DISTINCT cat_group FROM weblogs WHERE weblog_id IN(";

        $weblog_ids = [];
        $entry_ids  = [];

        foreach ($query as $row)
        {
            $weblog_ids[] = $row->weblog_id;
            $entry_ids[] = $row->entry_id;
        }

        $group_query = DB::table('weblogs')
            ->whereIn('weblog_id', $weblog_ids)
            ->distinct()
            ->select('cat_group')
            ->get();

        $valid = 'n';

        if ($group_query->count() > 0)
        {
            $valid = 'y';
            $last  = explode('|', $group_query->last()->cat_group);

            foreach($group_query as $row) {
                $valid_cats = array_intersect($last, explode('|', $row->cat_group));

                if (sizeof($valid_cats) == 0) {
                    $valid = 'n';
                    break;
                }
            }
        }

        if ($valid == 'n') {
            return Cp::userError( __('publish.no_category_group_match'));
        }

        $this->category_tree(($cat_group = implode('|', $valid_cats)));

        if (count($this->categories) == 0) {
            $cats = Cp::quickDiv('littlePadding', Cp::quickDiv('highlight', __('publish.no_categories')), 'categorytree');
        } else {
            $cats = "<div id='categorytree'>";

            foreach ($this->categories as $val)
            {
                $cats .= $val;
            }

            $cats .= '</div>';
        }

        if (Session::access('can_edit_categories'))
        {
            $cats .= '<div id="cateditlink" style="padding:0; margin:0;display:none;">';

            if (stristr($cat_group, '|'))
            {
                $catq_query = DB::table('category_groups')
                    ->where('group_id', explode('|', $cat_group))
                    ->select('group_name', 'group_id')
                    ->get();

                $links = '';

                foreach($catg_query as $catg_row)
                {
                    $links .= Cp::anchorpop(
                        BASE.'?C=WeblogAdministration'.
                            AMP.'M=category_manager'.
                            AMP.'group_id='.$catg_row['group_id'].
                            AMP.'cat_group='.$cat_group.
                            AMP.'Z=1',
                        '<b>'.$catg_row['group_name'].'</b>'
                    ).', ';
                }

                $cats .= Cp::quickDiv('littlePadding', '<b>'.__('publish.edit_categories').': </b>'.substr($links, 0, -2), '750');
            }
            else
            {
                $cats .= Cp::quickDiv(
                    'littlePadding',
                    Cp::anchorpop(
                        BASE.'?C=WeblogAdministration'.
                            AMP.'M==category_editor'.
                            AMP.'group_id='.$cat_group.
                            AMP.'Z=1',
                        '<b>'.__('publish.edit_categories').'</b>',
                        '750'
                    )
                );
            }

            $cats .= '</div>';
        }

        // ------------------------------------
        //  Build the output
        // ------------------------------------

        $r  = Cp::formOpen(
            [
                'action'    => 'C=edit'.AMP.'M=entriesCategoryUpdate',
                'name'      => 'entryform',
                'id'        => 'entryform'
            ],
            [
                'entry_ids' => implode('|', $entry_ids),
                'type'      => ($type == 'add') ? 'add' : 'remove'
            ]
        );

        $r .= <<<EOT

<script type="text/javascript">

    function set_catlink()
    {
        $('#cateditlink').css('display', 'block');
    }

    function swap_categories(str)
    {
    	$('#categorytree').html(str);
    }
</script>
EOT;

        $r .= '<div class="tableHeading">'.__('publish.multi_entry_category_editor').'</div>';

        $r .= PHP_EOL.'<div class="publish-box">';

        $r .= Cp::heading(($type == 'add') ? __('publish.add_categories') : __('publish.remove_categories'), 5);

        $r .= PHP_EOL."<table class='clusterBox' border='0' cellpadding='0' cellspacing='0' style='width:99%'><tr>";
        $r .= PHP_EOL.'<td class="publishItemWrapper" valign="top" style="width:45%;">'.BR;
        $r .= $cats;
        $r .= '</td>';
        $r .= "</tr></table>";

        $r .= '</div>'.PHP_EOL;

        $r .= Cp::quickDiv('paddingTop', Cp::input_submit(__('cp.update'))).
              '</form>'.PHP_EOL;

        Cp::$body_props .= ' onload="displayCatLink();" ';
        Cp::$title = __('publish.multi_entry_category_editor');
        Cp::$crumb = __('publish.multi_entry_category_editor');
        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Add/Remove Categories to/from Multiple Entries processing
    *
    * @return string
    */
    public function entriesCategoryUpdate()
    {
        if ( ! Session::access('can_access_edit')) {
            return Cp::unauthorizedAccess();
        }

        if (!Request::filled('entry_ids') or !Request::filled('type')) {
            return Cp::unauthorizedAccess(__('publish.unauthorized_to_edit'));
        }

        if (
            !Request::filled('category') or
            ! is_array(Request::input('category')) or
            sizeof(Request::input('category')) == 0
        ) {
            return Cp::userError( __('publish.no_categories_selected'));
        }

        // ------------------------------------
        //  Fetch categories
        // ------------------------------------

        $this->cat_parents = Request::input('category');

        if ($this->assign_cat_parent == true) {
            $this->fetch_category_parents(Request::input('category'));
        }

        $this->cat_parents = array_unique($this->cat_parents);

        sort($this->cat_parents);

        $entry_ids = [];

        foreach (explode('|', Request::input('entry_ids')) as $entry_id) {
            $entry_ids[] = $entry_id;
        }

        // ------------------------------------
        //  Get Category Group IDs
        // ------------------------------------

        $query = DB::table('weblogs')
            ->select('weblogs.cat_group')
            ->join('weblog_entries', 'weblog_entries.weblog_id', '=', 'weblogs.weblog_id')
            ->whereIn('weblog_entries.entry_id', $entry_ids)
            ->get();

        $valid = 'n';

        if ($query->count() > 0)
        {
            $valid = 'y';
            $last  = explode('|', $query->last()->cat_group);

            foreach($query as $row)
            {
                $valid_cats = array_intersect($last, explode('|', $row->cat_group));

                if (sizeof($valid_cats) == 0)
                {
                    $valid = 'n';
                    break;
                }
            }
        }

        if ($valid == 'n') {
            return Cp::userError(__('publish.no_category_group_match'));
        }

        // ------------------------------------
        //  Remove Cats, Then Add Back In
        // ------------------------------------

        $valid_cat_ids = DB::table('categories')
            ->where('group_id', $valid_cats)
            ->whereIn('category_id', $this->cat_parents)
            ->pluck('category_id')
            ->all();

        if (!empty($valid_cat_ids)) {
            DB::table('weblog_entry_categories')
                ->whereIn('category_id', $valid_cat_ids)
                ->whereIn('entry_id', $entry_ids)
                ->delete();
        }

        if (Request::input('type') == 'add') {

            $insert_cats = array_intersect($this->cat_parents, $valid_cat_ids);

            // How brutish...
            foreach($entry_ids as $id)
            {
                foreach($insert_cats as $val)
                {
                    DB::table('weblog_entry_categories')
                        ->insert(
                        [
                            'entry_id'     => $id,
                            'category_id'  => $val
                        ]);
                }
            }
        }

        // ------------------------------------
        //  Clear caches if needed
        // ------------------------------------

        if (Site::config('new_posts_clear_caches') == 'y') {
            cms_clear_caching('all');
        }

        return $this->editEntries(__('publish.multi_entries_updated'));
    }

    // --------------------------------------------------------------------

    /**
    * Delete Entries confirmation page
    *
    * @return string
    */
    public function deleteEntriesConfirm()
    {
        if ( ! Session::access('can_delete_self_entries') AND
             ! Session::access('can_delete_all_entries')) {
            return Cp::unauthorizedAccess();
        }

        if ( ! Request::filled('toggle') or !is_array(Request::input('toggle'))) {
            return $this->editEntries();
        }

        $r  = Cp::formOpen(['action' => 'C=edit'.AMP.'M=deleteEntries']);

        $i = 0;
        foreach (Request::input('toggle') as $key => $val) {
            if (!empty($val)) {
                $r .= Cp::input_hidden('delete[]', $val);
                $i++;
            }
        }

        $r .= Cp::quickDiv('alertHeading', __('publish.delete_confirm'));
        $r .= Cp::div('box');

        if ($i == 1) {
            $r .= Cp::quickDiv('defaultBold', __('publish.delete_entry_confirm'));
        }
        else{
            $r .= Cp::quickDiv('defaultBold', __('publish.delete_entries_confirm'));
        }

        // if it's just one entry, let's be kind and show a title
        if ($i == 1) {
            $entry_id = array_pop(Request::input('toggle'));
            $query = DB::table('weblog_entry_data')
                ->where('entry_id', $entry_id)
                ->first(['title']);

            if ($query)
            {
                $r .= '<br>'.
                      Cp::quickDiv(
                        'defaultBold',
                        str_replace(
                            '%title',
                            $query->title,
                            __('publish.entry_title_with_title')
                        )
                      );
            }
        }

        $r .= '<br>'.
              Cp::quickDiv('alert', __('cp.action_can_not_be_undone')).
              '<br>'.
              Cp::input_submit(__('cp.delete')).
              '</div>'.PHP_EOL.
              '</form>'.PHP_EOL;

        Cp::$title = __('publish.delete_confirm');
        Cp::$crumb = __('publish.delete_confirm');
        Cp::$body  = $r;
    }

    // --------------------------------------------------------------------

    /**
    * Delete Entries processing
    *
    * @return string
    */
    public function deleteEntries()
    {
        if ( ! Session::access('can_delete_self_entries') AND
             ! Session::access('can_delete_all_entries'))
        {
            return Cp::unauthorizedAccess();
        }

        if ( ! Request::filled('delete') && is_array(Request::input('delete'))) {
            return $this->editEntries();
        }

        $ids = Request::input('delete');

        $query = DB::table('weblog_entries')
            ->whereIn('entry_id', $ids)
            ->select('weblog_id', 'author_id', 'entry_id')
            ->get();

        $allowed_blogs = array_keys(Session::userdata('assigned_weblogs'));

        foreach ($query as $row)
        {
            if (Session::userdata('group_id') != 1) {
                if ( ! in_array($row->weblog_id, $allowed_blogs)) {
                    return $this->editEntries();
                }
            }

            if ($row->author_id == Session::userdata('member_id')) {
                if ( ! Session::access('can_delete_self_entries')) {
                    return Cp::unauthorizedAccess(__('publish.unauthorized_to_delete_self'));
                }
            } else {
                if ( ! Session::access('can_delete_all_entries')) {
                    return Cp::unauthorizedAccess(__('publish.unauthorized_to_delete_others'));
                }
            }
        }

        $entry_ids = [];

        foreach ($query as $row) {
            $entry_ids[] = $row->entry_id;
            $weblog_id = $row->weblog_id;

            DB::table('weblog_entries')->where('entry_id', $row->entry_id)->delete();
            DB::table('weblog_entry_data')->where('entry_id', $row->entry_id)->delete();
            DB::table('weblog_entry_categories')->where('entry_id', $row->entry_id)->delete();

            $tot = DB::table('members')
                ->where('member_id', $row->author_id)
                ->value('total_entries');

            if ($tot > 0) {
                $tot -= 1;
            }

            DB::table('members')
                ->where('member_id', $row->author_id)
                ->update(['total_entries' => $tot]);

            // Update statistics
            Stats::update_weblog_stats($row->weblog_id);
        }

        // ------------------------------------
        //  Clear caches
        // ------------------------------------

        cms_clear_caching('all');

        // ------------------------------------
        //  Return success message
        // ------------------------------------

        $message = __('publish.entries_deleted');

        return $this->editEntries($message);
    }

    // --------------------------------------------------------------------

    /**
    * Upload Files Form
    *
    * @return string
    */
    public function uploadFileForm()
    {
        Cp::$title = __('publish.file_upload');

        Cp::$body .= Cp::quickDiv('tableHeading', __('publish.file_upload'));

        Cp::$body .= Cp::div('box').BR;


        if (Session::userdata('group_id') != 1) {
            $ids = DB::table('upload_no_access')
                ->where('member_group', Session::userdata('group_id'))
                ->pluck('upload_id')
                ->all();
        }

        $query = DB::table('upload_prefs')
            ->select('id', 'name')
            ->orderBy('name');

        if ( ! empty($ids)) {
            $query->whereNotIn('id', $ids);
        }

        $query = $query->get();

        if ($query->count() == 0) {
            return Cp::unauthorizedAccess();
        }

        Cp::$body .= '<form method="post" action="'.
                BASE.'?C=publish'.
                AMP.'M=uploadFile'.
                AMP.'Z=1'.
            '" enctype="multipart/form-data">'.
            "\n";

        Cp::$body .= Cp::input_hidden('field_group', Request::input('field_group'));

        Cp::$body .= Cp::quickDiv('', "<input type=\"file\" name=\"userfile\" size=\"20\" />".BR.BR);

        Cp::$body .= Cp::quickDiv('littlePadding', __('publish.select_destination_dir'));

        Cp::$body .= Cp::input_select_header('destination');

        foreach ($query as $row)
        {
            Cp::$body .= Cp::input_select_option($row->id, $row->name);
        }

        Cp::$body .= Cp::input_select_footer();


        Cp::$body .= Cp::quickDiv('', BR.Cp::input_submit(__('publish.upload')).'<br><br>');

        Cp::$body .= '</form>'.PHP_EOL;

        Cp::$body .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  File Browser
        // ------------------------------------

        Cp::$body .= Cp::quickDiv('', BR.BR);

        Cp::$body .= Cp::quickDiv('tableHeading', __('filebrowser.file_browser'));
        Cp::$body .= Cp::div('box');

        Cp::$body .= '<form method="post" action="'.BASE.'?C=publish'.AMP.'M=fileBrowser'.AMP.'Z=1'."\" enctype=\"multipart/form-data\">\n";

        Cp::$body .= Cp::input_hidden('field_group', Request::input('field_group'));

        Cp::$body .= Cp::quickDiv('paddingTop', __('publish.select_destination_dir'));

        Cp::$body .= Cp::input_select_header('directory');

        foreach ($query as $row)
        {
            Cp::$body .= Cp::input_select_option($row->id, $row->name);
        }

        Cp::$body .= Cp::input_select_footer();


        Cp::$body .= Cp::quickDiv('', BR.Cp::input_submit(__('publish.view')));

        Cp::$body .= '</form>'.PHP_EOL;
        Cp::$body .= BR.BR.'</div>'.PHP_EOL;

        Cp::$body .= Cp::quickDiv('littlePadding', BR.'<div align="center"><a href="JavaScript:window.close();">'.__('cp.close_window').'</a></div>');
    }

    // --------------------------------------------------------------------

    /**
    * Upload Files - DISABLEd
    *
    * @return string
    */
    public function uploadFile()
    {
        return Cp::errorMessage('Disabled for the time being, sorry');
    }

    // --------------------------------------------------------------------

    /**
    * File Browser
    *
    * @return string
    */
    public function fileBrowser()
    {
        $id = Request::input('directory');
        $field_group = Request::input('field_group');

        Cp::$title = __('filebrowser.file_browser');

        $r  = Cp::quickDiv('tableHeading', __('filebrowser.file_browser'));
        $r .= Cp::quickDiv('box', 'Disabled for the time being, sorry');

        $query = DB::table('upload_prefs')->where('id', $id);

        if ($query->count() == 0) {
            return;
        }

        if (Session::userdata('group_id') != 1)
        {
            $safety_count = DB::table('upload_no_access')
                ->where('upload_id', $query->id)
                ->where('upload_loc', 'cp')
                ->where('member_group', Session::userdata('group_id'))
                ->count();

            if ($safety_count != 0) {
                return Cp::unauthorizedAccess();
            }
        }

        Cp::$body = $r;
    }
}

