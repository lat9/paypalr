<?php
/**
 * Part of the paypalr (PayPal Restful Api) payment module.
 *
 * These functions may not exist on older versions of Zen Cart. 
 * They are included here for backward-compatibility.
 *
 * Last updated: v1.3.0
 */

if (!function_exists('zen_get_zcversion')) {
    function zen_get_zcversion()
    {
        return PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR;
    }
}

if (!function_exists('zen_cfg_select_multioption_pairs')) {
    function zen_cfg_select_multioption_pairs(array $choices_array, string $stored_value, string $config_key_name = ''): string
    {
        $string = '';
        $name = (($config_key_name) ? 'configuration[' . $config_key_name . '][]' : 'configuration_value');
        $chosen_already = explode(", ", $stored_value);
        foreach ($choices_array as $value) {
            // Account for cases where an = sign is used to allow key->value pairs where the value is friendly display text
            $beforeEquals = strstr($value, '=', true);
            // this entry's checkbox should be pre-selected if the key matches
            $ticked = (in_array($value, $chosen_already, true) || in_array($beforeEquals, $chosen_already, true));
            // determine the value to show (the part after the =; if no =, just the whole string)
            $display_value = strpos($value, '=') !== false ? explode('=', $value, 2)[1] : $value;
            $string .= '<div class="checkbox"><label>' . zen_draw_checkbox_field($name, $value, $ticked, 'id="' . strtolower($value . '-' . $name) . '"') . $display_value . '</label></div>' . "\n";
        }
        $string .= zen_draw_hidden_field($name, '--none--');
        return $string;
    }
}
