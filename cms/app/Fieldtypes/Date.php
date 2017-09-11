<?php

namespace Kilvin\FieldTypes;

use Cp;
use Kilvin\Plugins\Weblogs\Models\Entry;
use Illuminate\Database\Schema\Blueprint;
use Kilvin\Support\Plugins\FieldType;
use Illuminate\Validation\ValidationException;

class Date extends FieldType
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
        return __('admin.Date');
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
        // @todo - Certain fields will fail to convert to timestamp, so check for that and throw Exception
        $table->timestamp($column_name)->nullable(true)->change();
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
        if (empty($value)) {
            return null;
        }

        $custom_date = Localize::humanReadableToUtcCarbon($value);

        // Localize::humanReadableToUtcCarbon() returns either a
        // valid Carbon object or a verbose error
        if ( ! $custom_date instanceof Carbon) {
            if ($custom_date !== false) {
                throw new ValidationException($custom_date.' ('.$this->field->field_label.')');
            }

            throw new ValidationException(__('publish.invalid_date_formatting'));
        }

        return $custom_date;
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
     * @param string|null $value The value of the field
     * @param array $entry All of the incoming entry data
     * @param string $source db/post
     * @return string
     */
    public function publishFormHtml($value, $entry, $source)
    {
        if (empty($value)) {
            $value = '';
        }

        $custom_date_string = '';
        $custom_date = '';
        $cal_date = '';

        if ($source == 'post') {
            $custom_date = (empty($value)) ? '' : Localize::humanReadableToUtcCarbon($value);
        } else {
            $custom_date = (empty($value)) ? '' : Carbon::parse($value);
        }

        if (!empty($custom_date) && $custom_date instanceof Carbon) {
            $date_object     = (empty($custom_date)) ? Carbon::now() : $custom_date->copy();
            $date_object->tz = Site::config('site_timezone');
            $cal_date        = $date_object->timestamp * 1000;

            $custom_date_string = Localize::createHumanReadableDateTime($date_object);
        }

        // ------------------------------------
        //  JavaScript Calendar
        // ------------------------------------

        $cal_img =
            '<a href="#" class="toggle-element" data-toggle="calendar_'.$this->field->field_name.'">
                <span style="display:inline-block; height:25px; width:25px; vertical-align:top;">
                    '.Cp::calendarImage().'
                </span>
            </a>';

        $r .= Cp::input_text(
            $this->field->field_name,
            $custom_date_string,
            '22',
            '22',
            'input',
            '170px',
            'onkeyup="update_calendar(\''.$this->field->field_name.'\', this.value);" '
        ).
        $cal_img;

        $r .= '<div id="calendar_'.$this->field->field_name.'" style="display:none;margin:4px 0 0 0;padding:0;">';

        $xmark = ($custom_date_string == '') ? 'false' : 'true';
        $r .= PHP_EOL.'<script type="text/javascript">

                var '.$this->field->field_name .' = new calendar(
                                        "'.$this->field->field_name.'",
                                        new Date('.$cal_date.'),
                                        '.$xmark.'
                                        );

                document.write('.$this->field->field_name.'.write());
                </script>'.PHP_EOL;

        $r .= '</div>';

        $r .= Cp::div('littlePadding');
        $r .= '<a href="javascript:void(0);" onclick="set_to_now(\''.$this->field->field_name.'\')" >'.
        __('publish.today').
        '</a>'.NBS.'|'.NBS;
        $r .= '<a href="javascript:void(0);" onclick="clear_field(\''.$this->field->field_name.'\');" >'.__('cp.clear').'</a>';
        $r .= '</div>'.PHP_EOL;
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

        $rules['fields['.$this->field->field_name.']'][] = 'date';

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
        return (string) $value;
    }
}
