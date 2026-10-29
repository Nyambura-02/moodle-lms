<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library functions for GlobeCon theme.
 *
 * @package    theme_globecon
 * @copyright  2026 GlobeCon Convergence Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serves theme files such as logo, favicon, login background.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_globecon_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && in_array($filearea, ['logo', 'loginbackground', 'favicon'])) {
        $theme = theme_config::load('globecon');
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}

/**
 * Get Pre SCSS variables and custom theme overrides.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_globecon_get_pre_scss($theme) {
    $scss = '';
    
    // Primary Brand Color (Corporate Navy Blue)
    $brandcolor = !empty($theme->settings->brandcolor) ? $theme->settings->brandcolor : '#0f2942';
    // Accent / Secondary Color (Corporate Electric Cyan/Teal)
    $accentcolor = !empty($theme->settings->accentcolor) ? $theme->settings->accentcolor : '#0284c7';

    $scss .= "\$primary: {$brandcolor} !default;\n";
    $scss .= "\$info: {$accentcolor} !default;\n";
    $scss .= "\$font-family-base: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !default;\n";
    
    return $scss;
}

/**
 * Get Extra SCSS content.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_globecon_get_extra_scss($theme) {
    $content = '';
    
    // Include custom theme SCSS styles
    $scssfile = __DIR__ . '/scss/post.scss';
    if (file_exists($scssfile)) {
        $content .= file_get_contents($scssfile);
    }
    
    // Append any custom CSS entered via Theme Settings in Site Admin
    if (!empty($theme->settings->customcss)) {
        $content .= "\n" . $theme->settings->customcss;
    }
    
    return $content;
}

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_globecon_get_main_scss_content($theme) {
    global $CFG;
    
    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : 'default.scss';
    $fs = get_file_storage();
    
    $context = context_system::instance();
    if ($filename === 'default.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    } else if ($filename === 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/plain.scss');
    } else if ($file = $fs->get_file($context->id, 'theme_boost', 'preset', 0, '/', $filename)) {
        $scss .= $file->get_content();
    } else {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    }
    
    // Prepend pre-scss
    $prescss = theme_globecon_get_pre_scss($theme);
    $extrascss = theme_globecon_get_extra_scss($theme);
    
    return $prescss . "\n" . $scss . "\n" . $extrascss;
}
