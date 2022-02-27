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
 * Our lib file.
 *
 * @package   assignfeedback_audio
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/filelib.php');

/**
 * Send back an AJAX error.
 *
 * @param string $strorstrname - the string name of the error message.
 * @param string $plugin - the plugin name.
 * @param array $params - array of errors.
 */
function send_ajax_error($strorstrname, $plugin = 'assignfeedback_audio', $params = array()) {

    $errorcontent = array('status' => 'error',
        'message' => get_string($strorstrname, $plugin, (object) $params)
    );
    echo(json_encode($errorcontent));
    exit(0);
}

/**
 * Sent back response.
 *
 * @param string $params - encoded JSON params.
 */
function return_ajax_response($params) {
    $params = (array) $params;
    $params['status'] = 'success';
    echo json_encode($params);
}

/**
 * Save the Uploaded recording.
 *
 * @param object $file - file object.
 * @param int $gradeid - the grade id.
 * @param int $contextid - context.
 * @return number|NULL - draft item id or null.
 */
function save_uploaded_audio($file, $gradeid,
        $contextid) {
    if (file_exists($file['tmp_name'])) {
        if (filesize($file['tmp_name'])) {

            // Now we save file as a draft file.
            $draftitemid = file_get_unused_draft_itemid();
            $filename = basename($file['tmp_name'], '.webm') . '.webm';

            $fs = get_file_storage();

            $filerecord = array(
                'contextid' => $contextid,
                'component' => 'user',
                'filearea'  => 'draft',
                'itemid'    => $draftitemid,
                'filepath'  => '/',
                'filename'  => $filename,
            );
            $fs->create_file_from_pathname($filerecord, $file['tmp_name']);
            return $draftitemid;
        } else {
            send_ajax_error('erroremptyfile', 'assignfeedback_audio');
        }
    } else {
        send_ajax_error('nofilesent', 'assignfeedback_audio');
    }
    return null;
}

/**
 * Send the audio file.
 *
 * @param stdClass $course - course object.
 * @param stdClass $cm - course module object.
 * @param stdClass $context - context object.
 * @param string $filearea - the file area.
 * @param array $args - the arguments passed to the plugin.
 * @param boolean $forcedownload - whether to force the user to download.
 * @param array $options - an array of optional options.
 * @return boolean false or send the file.
 */
function assignfeedback_audio_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {

    // Check the contextlevel is as expected.
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // Make sure the filearea is used by the plugin.
    if ($filearea !== 'feedback') {
        return false;
    }

    // User is logged in and has access to the course?
    require_login($course, true);

    $itemid = array_shift($args); // 1st item in $args.

    // Extract the filepath from the $args array.
    $filename = array_pop($args); // Last item in $args.
    if (!$args) {
        $filepath = '/'; // Empty $args => '/'.
    } else {
        $filepath = '/'.implode('/', $args).'/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'assignfeedback_audio', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser.
    send_stored_file($file, 1440, 0, $forcedownload, $options);
}

