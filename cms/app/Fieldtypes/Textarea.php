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
     * @param string $field_name The name of the field
     * @param Illuminate\Database\Schema\Blueprint $table The table that is having the field added
     * @return object
     */
    public function columnType($field_name, Blueprint $table)
    {
        $table->text($field_name);
    }

    // ----------------------------------------------------

    /**
     * Field Ouput
     *
     * That which is pushed out to the Template parser as final value
     *
     * @param string $field_name The name of the field
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return mixed Could be anything really, as long as Twig can use it
     */
    public function storedValue($field_name, $value, $entry, $source)
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
        return Cp::quickDiv(
            'littlePadding',
            Cp::input_text(
                'textarea_num_rows',
                $incoming['textarea_num_rows'],
                '4',
                '3',
                'input',
                '30px').
            NBS.
            __('admin.Textarea Rows')
        );
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
        $rules['textarea_num_rows'] = 'required|number|max:100';

        return $rules;
    }

    // ----------------------------------------------------

    /**
     * Publish Form HTML
     *
     * The HTML displayed in the Publish form for this field.
     * I'd suggest using views to build this, but who am I to tell you what's right?
     *
     * @param string $field_name The name of the field
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return string
     */
    public function publishFormHtml($field_name, $value, $entry, $source)
    {
        Cp::footerJavascript('');

        $row = (!empty($field->textarea_num_rows)) ? ceil($field->textarea_num_rows) : 10;

        return '<textarea
            style="width:100%;"
            id="'.$field_name.'"
            name="'.$field_name.'"
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
     * @param string $field_name The name of the field
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return array
     */
    public function publishFormValidation($field_name, $value, $entry, $source)
    {
        $rules = [];

        if ($this->fields->is_field_required == 'y') {
            $rules[$field_name] = 'required';
        }

        return $rules;
    }

    // ----------------------------------------------------

    /**
     * Template Output
     *
     * What you output to the Template
     *
     * @param string $field_name The name of the field
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @return mixed
     */
    public function templateOutput($field_name, $value, $entry)
    {
        return $value;
    }

    // -------------------------------------------------------------------------
    //  Events!
    // -------------------------------------------------------------------------

    /**
     * Performs actions before an entry is saved.
     *
     * @param Kilvin\Plugins\Weblogs\Models\Entry $entry The entry that is about to be saved
     * @param bool $new   Is this a new entry?
     * @return bool Whether the entry should be saved
     */
    public function beforeEntrySave(Entry $entry, bool $new): bool
    {
        return true;
    }

    // ----------------------------------------------------

    /**
     * Performs actions after the entry has been saved.
     *
     * @param Kilvin\Plugins\Weblogs\Models\Entry $entry The entry that was just saved
     * @param bool $new Whether the entry is new
     * @return void
     */
    public function afterEntrySave(Entry $entry, bool $new)
    {

    }

    // ----------------------------------------------------

    /**
     * Performs actions before an entry is deleted.
     *
     * @param Kilvin\Plugins\Weblogs\Models\Entry $entry The entry that is about to be deleted
     * @return bool Whether the entry should be deleted
     */
    public function beforeEntryDelete(Entry $entry): bool
    {
        return true;
    }

    // ----------------------------------------------------

    /**
     * Performs actions after the entry has been deleted.
     *
     * @param Kilvin\Plugins\Weblogs\Models\Entry $entry The entry that was just deleted
     * @return void
     */
    public function afterEntryDelete(Entry $entry)
    {

    }
}
