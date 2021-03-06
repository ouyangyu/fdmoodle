<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the definition for the library class for PDF feedback plugin
 *
 *
 * @package   assignfeedback_editpdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \assignfeedback_editpdf\document_services;
use \assignfeedback_editpdf\page_editor;

/**
 * library class for editpdf feedback plugin extending feedback plugin base class
 *
 * @package   assignfeedback_editpdf
 * @copyright 2012 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_feedback_editpdf extends assign_feedback_plugin {

    /**
     * Get the name of the file feedback plugin
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_editpdf');
    }

    /**
     * Create a widget for rendering the editor.
     *
     * @param int $userid
     * @param stdClass $grade
     * @param bool $readonly
     * @return assignfeedback_editpdf_widget
     */
    public function get_widget($userid, $grade, $readonly) {
        $attempt = -1;
        if ($grade) {
            $attempt = $grade->attemptnumber;
        } else {
            $grade = $this->assignment->get_user_grade($userid, true);
        }

        $feedbackfile = document_services::get_feedback_document($this->assignment->get_instance()->id,
                                                                 $userid,
                                                                 $attempt);

        $stampfiles = array();
        $fs = get_file_storage();
        $syscontext = context_system::instance();

        // Copy any new stamps to this instance.
        if ($files = $fs->get_area_files($syscontext->id,
                                         'assignfeedback_editpdf',
                                         'stamps',
                                         0,
                                         "filename",
                                         false)) {
            foreach ($files as $file) {
                $filename = $file->get_filename();
                if ($filename !== '.') {

                    $existingfile = $fs->get_file($this->assignment->get_context()->id,
                                                  'assignfeedback_editpdf',
                                                  'stamps',
                                                  $grade->id,
                                                  '/',
                                                  $file->get_filename());
                    if (!$existingfile) {
                        $newrecord = new stdClass();
                        $newrecord->contextid = $this->assignment->get_context()->id;
                        $newrecord->itemid = $grade->id;
                        $fs->create_file_from_storedfile($newrecord, $file);
                    }
                }
            }
        }

        // Now get the full list of stamp files for this instance.
        if ($files = $fs->get_area_files($this->assignment->get_context()->id,
                                         'assignfeedback_editpdf',
                                         'stamps',
                                         $grade->id,
                                         "filename",
                                         false)) {
            foreach ($files as $file) {
                $filename = $file->get_filename();
                if ($filename !== '.') {
                    $url = moodle_url::make_pluginfile_url($this->assignment->get_context()->id,
                                                   'assignfeedback_editpdf',
                                                   'stamps',
                                                   $grade->id,
                                                   '/',
                                                   $file->get_filename(),
                                                   false);
                    array_push($stampfiles, $url->out());
                }
            }
        }

        $url = false;
        $filename = '';
        if ($feedbackfile) {
            $url = moodle_url::make_pluginfile_url($this->assignment->get_context()->id,
                                                   'assignfeedback_editpdf',
                                                   document_services::FINAL_PDF_FILEAREA,
                                                   $grade->id,
                                                   '/',
                                                   $feedbackfile->get_filename(),
                                                   false);
           $filename = $feedbackfile->get_filename();
        }

        // Retrieve total number of pages.
        $pagetotal = document_services::page_number_for_attempt($this->assignment->get_instance()->id,
                $userid,
                $attempt);

        $widget = new assignfeedback_editpdf_widget($this->assignment->get_instance()->id,
                                                    $userid,
                                                    $attempt,
                                                    $url,
                                                    $filename,
                                                    $stampfiles,
                                                    $readonly,
                                                    $pagetotal);
        return $widget;
    }

    /**
     * Get form elements for grading form
     *
     * @param stdClass $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @param int $userid
     * @return bool true if elements were added to the form
     */
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid) {
        global $PAGE;

        $attempt = -1;
        if ($grade) {
            $attempt = $grade->attemptnumber;
        }

        $files = document_services::list_compatible_submission_files_for_attempt($this->assignment, $userid, $attempt);
        // Only show the editor if there was a compatible file submitted.
        if (count($files)) {

            $renderer = $PAGE->get_renderer('assignfeedback_editpdf');

            $widget = $this->get_widget($userid, $grade, false);

            $html = $renderer->render($widget);
            $mform->addElement('static', 'editpdf', get_string('editpdf', 'assignfeedback_editpdf'), $html);
            $mform->addHelpButton('editpdf', 'editpdf', 'assignfeedback_editpdf');
        }
    }

    /**
     * Generate the pdf.
     *
     * @param stdClass $grade
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $grade, stdClass $data) {
        if (page_editor::has_annotations_or_comments($grade->id, true)) {
            document_services::generate_feedback_document($this->assignment, $grade->userid, $grade->attemptnumber);
        }

        return true;
    }

    /**
     * Display the list of files in the feedback status table.
     *
     * @param stdClass $grade
     * @param bool $showviewlink (Always set to false).
     * @return string
     */
    public function view_summary(stdClass $grade, & $showviewlink) {
        $showviewlink = false;
        return $this->view($grade);
    }

    /**
     * Display the list of files in the feedback status table.
     *
     * @param stdClass $grade
     * @return string
     */
    public function view(stdClass $grade) {
        global $PAGE;
        $html = '';
        // Show a link to download the pdf.
        if (page_editor::has_annotations_or_comments($grade->id, false)) {
            $html = $this->assignment->render_area_files('assignfeedback_editpdf',
                                                         document_services::FINAL_PDF_FILEAREA,
                                                         $grade->id);

            // Also show the link to the read-only interface.
            $renderer = $PAGE->get_renderer('assignfeedback_editpdf');
            $widget = $this->get_widget($grade->userid, $grade, true);

            $html .= $renderer->render($widget);
        }
        return $html;
    }

    /**
     * Return true if there are no released comments/annotations.
     *
     * @param stdClass $grade
     */
    public function is_empty(stdClass $grade) {
        global $DB;

        $comments = $DB->count_records('assignfeedback_editpdf_cmnt', array('gradeid'=>$grade->id, 'draft'=>0));
        $annotations = $DB->count_records('assignfeedback_editpdf_annot', array('gradeid'=>$grade->id, 'draft'=>0));
        return $comments == 0 && $annotations == 0;
    }

    /**
     * The assignment has been deleted - remove the plugin specific data
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        $grades = $DB->get_records('assign_grades', array('assignment'=>$this->assignment->get_instance()->id), '', 'id');
        if ($grades) {
            list($gradeids, $params) = $DB->get_in_or_equal(array_keys($grades), SQL_PARAMS_NAMED);
            $DB->delete_records_select('assignfeedback_editpdf_annot', 'gradeid ' . $gradeids, $params);
            $DB->delete_records_select('assignfeedback_editpdf_cmnt', 'gradeid ' . $gradeids, $params);
        }
        return true;
    }

    /**
     * Automatically enable or disable editpdf feedback plugin based on
     * whether the ghostscript path is set correctly.
     *
     * @return bool
     */
    public function is_enabled() {
        $testpath = assignfeedback_editpdf\pdf::test_gs_path();
        if (!extension_loaded('zlib')) {
            return false;
        }
        if ($testpath->status == assignfeedback_editpdf\pdf::GSPATH_OK) {
            return true;
        }
        return false;
    }
    /**
     * Automatically hide the setting for the editpdf feedback plugin.
     *
     * @return bool false
     */
    public function is_configurable() {
        return false;
    }
}
