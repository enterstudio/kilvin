<?php

namespace Kilvin\FieldTypes;

use Cp;
use DB;
use Kilvin\Plugins\Weblogs\Models\Entry;
use Illuminate\Database\Schema\Blueprint;
use Kilvin\Support\Plugins\FieldType;

class Dropdown extends FieldType
{
    protected $field;

    // ----------------------------------------------------

    /**
     * Name of the FieldType
     *
     * @return string
     */
    public function name()
    {
        return __('admin.Dropdown');
    }

    // ----------------------------------------------------

    /**
     * Column Type
     *
     * Essentially we send you the Blueprint object and you add whatever field type you want
     *
     * @link https://laravel.com/docs/5.5/migrations#columns
     * @param string $column_name What the column will be called in the weblog_field_data table
     * @param Illuminate\Database\Schema\Blueprint $table The table that is having the field added
     * @param null|object $existing On edit, if changing field type, we send existing column details
     * @return void
     */
    public function columnType($column_name, Blueprint &$table, $existing = null)
    {
        $table->text($column_name)->nullable(true);
    }

    // ----------------------------------------------------

    /**
     * Field Ouput
     *
     * That which is pushed out to the Template parser as final value
     *
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return mixed Could be anything really, as long as Twig can use it
     */
    public function storedValue($value, $entry, $source)
    {
        return $value;
    }

    // ----------------------------------------------------

    /**
     * Settings Form HTML
     *
     * The HTML fields you wish to display in the Edit Field Form
     *
     * @param array $settings The Settings for this field
     * @return string
     */
    public function settingsFormFields($settings = [])
    {
        extract($settings);

        $populate_type = $pulldown_populate ?? 'manual';
        $weblog_id     = $pulldown_weblog_id ?? null;
        $list_items    = $pulldown_list_items ?? '';
        $weblog_field  = $pulldown_weblog_field ?? '';

        // ------------------------------------
        //  Create the "populate" radio options
        // ------------------------------------

        $typemenu .= Cp::quickDiv(
            'default',
            '<label>'.
                Cp::input_radio(
                    'settings[pulldown_populate]',
                    'manual',
                    ($populate_type == 'manual') ? 1 : 0,
                    ' class="js-pulldown-populate"'
                ).
                ' '.
                __('admin.field_populate_manually').
            '</label>');

        $typemenu .= Cp::quickDiv(
            'default',
            '<label>'.
                Cp::input_radio(
                    'settings[pulldown_populate]',
                    'weblog',
                    ($populate_type == 'weblog') ? 1 : 0,
                    ' class="js-pulldown-populate"'
                ).
                ' '.
                __('admin.field_populate_from_blog').
            '</label>');

        // ------------------------------------
        //  Populate Manually
        // ------------------------------------

        $display = ($poulate_type == 'manual') ? 'block' : 'none';

        $typopts = '<div id="pulldown_populate_manual" style="display: '.$display.'; padding:0; margin:5px 0 0 0;">';

        $typopts .= Cp::quickDiv(
                'defaultBold',
                __('admin.field_list_items')
            ).
            Cp::quickDiv(
                'default',
                __('admin.field_list_instructions')
            ).
            Cp::input_textarea(
                'settings[pulldown_list_items]',
                $list_items,
                10,
                'textarea',
                '400px'
            );

        $typopts .= '</div>'.PHP_EOL;

        // ------------------------------------
        //  Populate via an existing field
        // ------------------------------------

        $display = ($poulate_type == 'weblog') ? 'block' : 'none';
        $typopts .= '<div id="pulldown_populate_weblog" style="display: '.$display.'; padding:0; margin:5px 0 0 0;">';

        $query = DB::table('weblogs')
            ->orderBy('weblog_title', 'asc')
            ->select('weblog_id', 'weblog_title', 'field_group')
            ->get();

        // Create the drop-down menu
        $typopts .= Cp::quickDiv('littlePadding', Cp::quickDiv('defaultBold', __('admin.select_weblog_for_field')));
        $typopts .= "<select name='settings[pulldown_weblog_field]' class='select'>".PHP_EOL;

        list($weblog_id, $field_name) = explode(':', $weblog_field);

        // Fetch the field names
        foreach ($query as $row) {
            $rez = DB::table('weblog_fields')
                ->where('group_id', $row->field_group)
                ->orderBy('field_label', 'asc')
                ->select('field_id', 'field_name', 'field_label')
                ->get();

            $typopts .= Cp::input_select_option('', $row->weblog_title);

            foreach ($rez as $frow)
            {
                $sel = ($weblog_id == $row->weblog_id AND $field_name == $frow->field_name) ? 1 : 0;

                $typopts .= Cp::input_select_option(
                    $row->weblog_id.'_'.$frow->field_name,
                    NBS.'-'.NBS.$frow->field_label,
                    $sel);
            }
        }

        $typopts .= Cp::input_select_footer();
        $typopts .= '</div>'.PHP_EOL;


        $r  = '<table>';
        $r .= '<tr>'.PHP_EOL;
        $r .= Cp::tableCell('', $typemenu, '50%', 'top');
        $r .= Cp::tableCell('', $typopts, '50%');
        $r .= '</tr>'.PHP_EOL.'</table>';

        return $r;
    }

    // ----------------------------------------------------

    /**
     * Settings Form Validation Rules
     *
     * Rules for any Settings Form Fields submitted
     *
     * @param array $incoming The incoming data from the Request
     * @return array
     */
    public function settingsValidationRules($incoming = [])
    {
        $rules['settings[pulldown_populate]'] = 'required|in:manual,weblog';
        $rules['settings[pulldown_weblog_id]'] = 'integer|exists:weblogs,id';
        $rules['settings[pulldown_list_items]'] = 'required_if:pulldown_populate,manual';
        $rules['settings[pulldown_weblog_field]'] = 'required_if:pulldown_populate,weblog';

        return $rules;
    }

    // ----------------------------------------------------

    /**
     * Publish Form HTML
     *
     * The HTML displayed in the Publish form for this field.
     * I'd suggest using views to build this, but who am I to tell you what's right?
     *
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return string
     */
    public function publishFormHtml($value, $entry, $source)
    {
        $r = Cp::input_select_header('fields['.$this->field->field_name.']', '', '');

        if ($field->settings['pulldown_populate'] == 'manual') {
            foreach (explode("\n", trim($field->settings['field_list_items'])) as $v) {
                $v = trim($v);

                $selected = ($v == $field_data) ? 1 : '';

                $v = escape_attribute($v);
                $r .= Cp::input_select_option($v, $v, $selected);
            }
        }

        // We need to pre-populate this menu from an another weblog custom field
        if ($field->settings['pulldown_populate'] == 'weblog') {
            $pop_query = DB::table('weblog_entry_data')
                ->where('weblog_id', $field->settings['pulldown_weblog_id'])
                ->select("field_".$field->settings['pulldown_field_name'])
                ->get();

            $r .= Cp::input_select_option('', '--', '');

            if ($pop_query->count() > 0) {
                foreach ($pop_query as $prow) {
                    $selected = ($prow->{'field_'.$field->settings['pulldown_field_name']} == $field_data) ? 1 : '';
                    $pretitle = substr($prow->{'field_'.$field->settings['pulldown_field_name']}, 0, 110);
                    $pretitle = preg_replace("/\r\n|\r|\n|\t/", ' ', $pretitle);
                    $pretitle = escape_attribute($pretitle);

                    $r .= Cp::input_select_option(
                        escape_attribute(
                            $prow->{'field_'.$field->settings['pulldown_field_name']}),
                        $pretitle,
                        $selected
                    );
                }
            }
        }

        $r .= Cp::input_select_footer();

        return $r;
    }

    // ----------------------------------------------------

    /**
     * Publish Form Validation
     *
     * The validation rules performed on submission
     *
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return array
     */
    public function publishFormValidation($value, $entry, $source)
    {
        $rules = [];

        if ($this->fields->is_field_required == 'y') {
            $rules['fields['.$this->field->field_name.']'][] = 'required';
        }

        return $rules;
    }

    // ----------------------------------------------------

    /**
     * Template Output
     *
     * What you output to the Template
     *
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @return mixed
     */
    public function templateOutput($value, $entry)
    {
        return $value;
    }
}
