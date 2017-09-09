<?php

namespace Kilvin\Plugins\Base;

use Cp;
use Kilvin\Plugins\Weblogs\Models\Entry;
use Illuminate\Database\Schema\Blueprint;
use Kilvin\Plugins\Base\FieldType;

class Textarea extends FieldType
{
    protected $field;
    protected $language;

    // ----------------------------------------------------

    /**
     * Constructor
     *
     * Uses Laravel dependency injection, so put anything you want in the constructor attributes
     *
     * @return void
     */
    public function __construct()
    {

    }

    // ----------------------------------------------------

    /**
     * Set Field Details
     *
     * @param object
     * @return void
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    // ----------------------------------------------------

    /**
     * Set Language
     *
     * @param object
     * @return void
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    // ----------------------------------------------------

    /**
     * Name of the Filter
     *
     * @return string
     */
    public function name()
    {
        // @todo - Translate!
        return 'Base FieldType';
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
     * @param array $entry All of the incoming entry data
     * @return mixed Could be anything really, as long as Twig can use it
     */
    public function storedValue($field_name, $entry, $source)
    {
        return $entry['field_'.$field_name];
    }

    // ----------------------------------------------------

    /**
     * Settings Form HTML
     *
     * The HTML fields you wish to display in the Edit Field Form
     *
     * @return string
     */
    public function settingsFormFields($incoming = null)
    {
        Cp::footerJavascript('');
        return '';
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
        return [];
    }

    // ----------------------------------------------------

    /**
     * Publish Form HTML
     *
     * The HTML displayed in the Publish form for this field.
     * I'd suggest using views to build this, but who am I to tell you what's right?
     *
     * @param string $field_name The name of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return string
     */
    public function publishFormHtml($field_name, $entry, $source)
    {
        Cp::footerJavascript('');
        return '';
    }

    // ----------------------------------------------------

    /**
     * Publish Form Validation
     *
     * The validation rules performed on submission
     *
     * @param string $field_name The name of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return array
     */
    public function publishFormValidation($field_name, $entry, $source)
    {
        $rules = [];

        if ($this->fields->is_field_required == 'y') {
            $rules[] = 'required';
        }

        return $rules;
    }

    // ----------------------------------------------------

    /**
     * Store Value
     *
     * What should be stored in the database column for this field
     *
     * @param string $field_name The name of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return mixed
     */
    public function storedValue($field_name, $entry, $source)
    {
        return $entry['field_'.$field_name];
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
