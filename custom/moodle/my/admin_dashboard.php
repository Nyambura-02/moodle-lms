<?php
// This file is part of Moodle - http://moodle.org/
//
// GlobeCon LMS - Administrator Dashboard Redirector
// The authoritative dashboard is now unified on /my/

require_once(__DIR__ . '/../config.php');
redirect(new moodle_url('/my/', ['role' => 'admin']));
