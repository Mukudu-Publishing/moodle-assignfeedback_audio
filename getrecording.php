<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Recording AJAX JS file.
 *
 * @package   assignfeedback_audio
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/lib.php');

if ($action = optional_param('action', '', PARAM_TEXT)) {
    switch ($action) {
        case 'save' :
            // Save file.
            if (empty($_FILES)) {
                send_ajax_error('nofilesent', 'assignfeedback_audio');
            }
            if (!$file = $_FILES['audiofile']) {
                send_ajax_error('nofilesent', 'assignfeedback_audio');
            }
            if (!$gradeid = optional_param('gradeid', 0, PARAM_INT)) {
                send_ajax_error('nogradeid', 'assignfeedback_audio');
            }
            if (!$contextid = optional_param('contextid', 0, PARAM_INT)) {
                send_ajax_error('nocontextid', 'assignfeedback_audio');
            }
            try {
                $tempfileref = save_uploaded_audio($file, $gradeid, $contextid);
            } catch ( moodle_exception $e ) {
                send_ajax_error('moodleerrormsg', 'assignfeedback_audio', $e->getMessage());
            }
            return_ajax_response( array('fileref' => $tempfileref));
            break;
        default:
            // Raise Error.
            send_ajax_error('invalidactionspecified', 'assignfeedback_audio');
    }
} else {
    send_ajax_error('noactionspecified', 'assignfeedback_audio');
}
