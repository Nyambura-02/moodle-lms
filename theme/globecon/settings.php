<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Settings for GlobeCon LMS theme.
 *
 * @package    theme_globecon
 * @copyright  2026 GlobeCon Convergence Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new admin_settingpage('themesettingglobecon', get_string('configtitle', 'theme_globecon'));

    // Brand Primary Color Setting
    $name = 'theme_globecon/brandcolor';
    $title = get_string('brandcolor', 'theme_globecon');
    $description = get_string('brandcolor_desc', 'theme_globecon');
    $default = '#0f2942'; // Corporate Deep Navy
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Accent Secondary Color Setting
    $name = 'theme_globecon/accentcolor';
    $title = get_string('accentcolor', 'theme_globecon');
    $description = get_string('accentcolor_desc', 'theme_globecon');
    $default = '#0284c7'; // Corporate Electric Cyan
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Custom Logo Setting
    $name = 'theme_globecon/logo';
    $title = get_string('logo', 'theme_globecon');
    $description = get_string('logo_desc', 'theme_globecon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Custom Login Background Setting
    $name = 'theme_globecon/loginbackground';
    $title = get_string('loginbackground', 'theme_globecon');
    $description = get_string('loginbackground_desc', 'theme_globecon');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'loginbackground');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);

    // Custom CSS Setting
    $name = 'theme_globecon/customcss';
    $title = get_string('customcss', 'theme_globecon');
    $description = get_string('customcss_desc', 'theme_globecon');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $settings->add($setting);
}
