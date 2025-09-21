<?php
/**
 * Language definitions for the paypalr (PayPal Restful Api) payment module.
 *
 * Last updated: v1.3.0
 */
if (zen_get_zcversion() >= '1.5.8') {
    return;
}

global $template_dir;

$lang_filename = 'lang.paypalr.php';
$languages_dir = DIR_FS_CATALOG . DIR_WS_LANGUAGES;
$lang_file_locations[] = $languages_dir . 'english/modules/payment/' . $lang_filename;
if ($_SESSION['language'] !== 'english') {
    $lang_file_locations[] = $languages_dir . $_SESSION['language'] . '/modules/payment/' . $lang_filename;
}
$lang_file_locations[] = $languages_dir . 'english/' . $template_dir . '/modules/payment/' . $lang_filename;
if ($_SESSION['language'] !== 'english') {
    $lang_file_locations[] = $languages_dir . $_SESSION['language'] . '/modules/payment/' . $template_dir . '/' . $lang_filename;
}

$language_defines = [];
foreach ($lang_file_locations as $next_file_to_load) {
    if (!file_exists($next_file_to_load)) {
        continue;
    }
    $defines = require $next_file_to_load;
    $language_defines = array_merge($language_defines, $defines);
}

foreach ($language_defines as $key => $value) {
    if (!defined($key)) {
        define($key, $value);
    }
}
