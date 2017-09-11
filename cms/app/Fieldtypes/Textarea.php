<?php

namespace Kilvin\FieldTypes;

use Cp;
use Kilvin\Plugins\Weblogs\Models\Entry;
use Illuminate\Database\Schema\Blueprint;
use Kilvin\Support\Plugins\FieldType;

class Textarea extends FieldType
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
        return __('admin.Textarea');
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
    public function settingsFormHtml(array $settings = [])
    {
        $num_rows = (!empty($settings['textarea_num_rows'])) ? $settings['textarea_num_rows'] : 10;
        return
            '<table class="tableBorder">
                <tr>
                    <td class="tableHeading" colspan="2">'.__('admin.Field Settings').'</td>
                </tr>
                <tr>
                    <td>
                        <div class="littlePadding">
                            <input
                                style="width:100%"
                                type="text"
                                id="textarea_num_rows"
                                name="settings[textarea_num_rows]"
                                value="'.$num_rows.'"
                            >'.
                            ' '.__('admin.Number of Rows').
                         '</div>
                     </td>
                 </tr>
             </table>';
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
        $rules['settings[textarea_num_rows]'] = 'nullable|integer|max:100';

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
        Cp::footerJavascript('');

        $row = (!empty($field->settings['textarea_num_rows'])) ? ceil($field->settings['textarea_num_rows']) : 10;

        return '<textarea
            style="width:100%;"
            id="'.$this->field->field_name.'"
            name="fields['.$this->field->field_name.']"
            rows="'.$rows.'"
            class="textarea"
        >'.escape_attribute($value).'</textarea>';
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
