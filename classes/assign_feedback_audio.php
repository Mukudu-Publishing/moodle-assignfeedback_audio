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
 * Assign Feedback Class file.
 *
 * @package   assignfeedback_audio
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * The feedback class.
 *
 * @package   assignfeedback_audio
 * @copyright 2019 - 2021 Mukudu Ltd - Bham UK
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_feedback_audio extends assign_feedback_plugin {

    /**
     * Should return the name of this plugin type.
     *
     * @return string - the name
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_audio');
    }

    /**
     * This function should be overridden to provide an array of elements that can be added to a moodle
     * form for display in the settings page for the assignment.
     * @param MoodleQuickForm $mform The form to add the elements to
     * @return $array
     */
    public function get_settings(MoodleQuickForm $mform) {

        $mform->addElement('duration', 'assignfeedback_audio_timeallowed', get_string('timeallowedprompt', 'assignfeedback_audio'),
            array(
                'defaultunit' => MINSECS,
                'units' => array(MINSECS)
            )
        );

        $mform->addHelpButton('assignfeedback_audio_timeallowed', 'timeallowedprompt', 'assignfeedback_audio');
        $mform->setDefault('assignfeedback_audio_timeallowed', 1);
        // Hide if feedback plugin is disabled.
        $mform->hideIf('assignfeedback_audio_timeallowed', 'assignfeedback_audio_enabled', 'notchecked');
    }

    /**
     * The assignment subtype is responsible for saving it's own settings as the database table for the
     * standard type cannot be modified.
     *
     * @param stdClass $data - the data submitted from the form
     * @return bool - on error the subtype should call set_error and return false.
     */
    public function save_settings(stdClass $data) {
        $this->set_config('duration', $data->assignfeedback_audio_timeallowed);
        return true;
    }

    /**
     * Get any additional fields for the submission/grading form for this assignment.
     *
     * @param mixed $grade submission|grade - For submission plugins this is the submission data,
     *                                                    for feedback plugins it is the grade data
     * @param MoodleQuickForm $mform - This is the form
     * @param stdClass $data - This is the form data that can be modified for example by a filemanager element
     * @param int $userid - This is the userid for the current submission.
     *                      This is passed separately as there may not yet be a submission or grade.
     * @return boolean - true if we added anything to the form
     */
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid) {
        global $PAGE;

        $params = array();      // Pass to javascript.
        $posturl = new moodle_url('/mod/assign/feedback/audio/getrecording.php');
        $params['posturl'] = $posturl->out(false);
        $params['maxduration'] = $this->get_config('duration');
        $params['gradeid'] = $grade ? $grade->id : 0;
        $params['contextid'] = $this->assignment->get_context()->id;

        $PAGE->requires->js_call_amd('assignfeedback_audio/record-me', 'init', $params);

        // Title.
        $mform->addElement('header', 'general', get_string('pluginname', 'assignfeedback_audio'));

        // Show existing feedback.
        if ($params['gradeid']) {
            $divtitle = get_string('exisitingfeedbackprompt', 'assignfeedback_audio');
            if ($html = $this->render_audio_controls( $params['gradeid'], $divtitle)) {
                $mform->addElement('html', $html);
            }
        }

        $mform->addElement('static', 'description', '', get_string('staticprompttext', 'assignfeedback_audio',
                format_time($this->get_config('duration')))
           );

        $mform->addElement('html', \html_writer::div('', 'alert alert-danger feedbackinfoarea',
            array('style' => 'display: none', 'role' => 'alert')));

        $mform->addElement('hidden', 'fileref', '', array('id' => 'id_fileref'));
        $mform->setType('fileref', PARAM_TEXT);

        $buttonarray = array();
        $buttonarray[] =& $mform->createElement('button', 'startbtn', get_string('startrecordingprompt', 'assignfeedback_audio'),
            array('class' => 'btn btn-default startbtn', 'disabled' => false));
        $buttonarray[] =& $mform->createElement('button', 'stopbtn', get_string('stoprecordingprompt', 'assignfeedback_audio'),
            array('class' => 'btn btn-default stopbtn', 'disabled' => false));
        $buttonarray[] =& $mform->createElement('button', 'listenbtn', get_string('listenrecordingprompt', 'assignfeedback_audio'),
            array('class' => 'btn btn-default listenbtn', 'disabled' => false));
        $buttonarray[] =& $mform->createElement('button', 'deletebtn', get_string('removerecordingprompt', 'assignfeedback_audio'),
            array('class' => 'btn btn-default deletebtn', 'disabled' => false));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

        $mform->addElement('html', '<br />');

        return true;
    }

    /**
     * Has the plugin form element been modified in the current submission?
     *
     * @param stdClass $grade The grade.
     * @param stdClass $data Form data from the feedback form.
     * @return boolean - True if the form element has been modified.
     */
    public function is_feedback_modified(stdClass $grade, stdClass $data) {
        // We know we have a change if the fileref is set.
        return (!empty($data->fileref));
    }

    /**
     * Save any custom data for this form submission
     *
     * @param stdClass $grade - assign_submission or assign_grade.
     *              For submission plugins this is the submission data,
     *              for feedback plugins it is the grade data
     * @param stdClass $data - the data submitted from the form
     * @return bool - on error the subtype should call set_error and return false.
     */
    public function save(stdClass $grade, stdClass $data) {
        $contextid = $this->assignment->get_context()->id;

        $fs = get_file_storage();
        // Get the draft file submitted.
        $files = $fs->get_area_files($contextid, 'user', 'draft', $data->fileref, null, false);
        if ($draftfile = array_pop($files)) {
            $newfilerecord = array(
                'contextid' => $contextid,
                'component' => 'assignfeedback_audio',
                'filearea'  => 'feedback',
                'itemid'    => $grade->id,
                'filepath'  => $draftfile->get_filepath(),
                'filename'  => $draftfile->get_filename()
            );

            // Should replace old recording before saving.
            if (!$this->is_empty($grade)) {
                $allparams = $this->getfileparams($grade->id);
                $params = array_slice($allparams, 0, (count($allparams) - 2));
                $fs->delete_area_files(...$params);
            }
            $fs->create_file_from_storedfile($newfilerecord, $draftfile);
        }

        return true;
    }

    /**
     * Get the required params for our recordings.
     * @param int $gradeid - The grade id.
     * @param boolean $includedirs - whether we need the sub folders.
     * @return array
     */
    private function getfileparams($gradeid, $includedirs = false) {
        return array(
            $this->assignment->get_context()->id,
            'assignfeedback_audio',
            'feedback',
            $gradeid,
            null,
            $includedirs);
    }

    /**
     * Is this assignment plugin empty? (ie no submission or feedback)
     * @param stdClass $submissionorgrade assign_submission or assign_grade
     * @return bool
     */
    public function is_empty(stdClass $submissionorgrade) {
        $fs = get_file_storage();
        $fileparams = $this->getfileparams($submissionorgrade->id);
        return $fs->file_exists(...$fileparams);
    }

    /**
     * Render the audio controls.
     * @param int $gradeid - the grade id.
     * @param string $title - the title
     * @return string - HTML snippet.
     */
    private function render_audio_controls($gradeid, $title = '') {

        $content = '';
        $fileparams = $this->getfileparams($gradeid);
        $fs = get_file_storage();
        if ($files = $fs->get_area_files(...$fileparams)) {
            if ($feedbackfile = array_pop($files)) {
                $feedbackfileurl = \moodle_url::make_pluginfile_url(
                        $feedbackfile->get_contextid(),
                        $feedbackfile->get_component(),
                        $feedbackfile->get_filearea(),
                        $feedbackfile->get_itemid(),
                        $feedbackfile->get_filepath(),
                        $feedbackfile->get_filename()
                    );

                // Audio player, could be a rendender.
                $content = "
            <div>
                <audio controls src='$feedbackfileurl'>
                Your browser does not support the audio tag.
                </audio>
            </div>";

                if ($title) {
                    $content = "<div><h5>$title</h5></div>\n" . $content;
                }
            }
        }
        return $content;
    }

    /**
     * Should not output anything - return the result as a string so it can be consumed by webservices.
     *
     * @param stdClass $grade assign_submission or assign_grade
     *                 For submission plugins this is the submission data, for feedback plugins it is the grade data
     * @param bool $showviewlink Modifed to return whether or not to show a link to the full submission/feedback
     * @return string - return a string representation of the submission in full
     */
    public function view_summary(stdClass $grade, & $showviewlink) {
        return $this->render_audio_controls($grade->id);
    }

}