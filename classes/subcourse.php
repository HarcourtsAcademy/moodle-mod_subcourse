<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace mod_subcourse;

/**
 * Description of subcourse
 *
 * @author timbutler
 */
class subcourse {
    
    public $id,
            $course,
            $isenrolled,
            $islocal,
            $localcourse,
            $localcoursecontext,
            $name,
            $progress,
            $refcourse,
            $remotecourse;
    
    public function __construct($id, $course, $name, $refcourse) {
        global $DB;
        
        $this->id = $id;
        $this->course = $course;
        $this->name = $name;
        
        if ($refcourse > 0) {
            $this->refcourse = $refcourse;
            $this->islocal = true;
            $this->localcourse = $DB->get_record('course', array('id' => $this->refcourse));
            $this->localcoursecontext = \context_course::instance($this->refcourse);
            $this->isenrolled = is_enrolled($this->localcoursecontext);
            
        } else {
            // The remote course id is negative, convert it back
            $this->refcourse = -1 * $refcourse;

            $this->islocal = false;
            $this->remotecourse = $DB->get_record('mnetservice_enrol_courses', array('id' => $this->refcourse), '*', MUST_EXIST);
            $this->isenrolled = $this->get_remote_course_is_enrolled();
        }
        
        if ($this->islocal) {
        }
    }
    
//    public function __get($name) {
//        return $this->$name;
//    }
    
    public function get_content() {
        $content = '';
        if (!$this->isenrolled && !is_siteadmin()) {
            // The student is not enrolled
            $content.= get_string('notenrolled', 'mod_subcourse');
        }
        return $content;
    }
    
    public function get_icon() {
        if (!$this->isenrolled && !is_siteadmin()) {
            // The student is not enrolled
            return new \moodle_url('/mod/subcourse/pix/icon-notenrolled.svg');
        }
        
        if ($this->isenrolled) {
            if ($this->progress == 100) {
                return new \moodle_url('/mod/subcourse/pix/icon-complete.svg');
            } else {
                return new \moodle_url('/mod/subcourse/pix/icon-inprogress.svg');
            }
        }
        
        return new \moodle_url('/mod/subcourse/pix/icon.svg');
    }
    
    public function get_course_summary() {
        if ($this->islocal) {
            return $this->get_local_course_summary();
        }
        return $this->get_remote_course_summary();
    }
    
    /**
     * 
     * Returns the local course summary formatted for display.
     * 
     * @global type $DB
     * @return String
     */
    private function get_local_course_summary() {
        if ($this->localcourse && $this->localcourse->summary) {
            $options = array('filter' => false, 'overflowdiv' => true, 'noclean' => true, 'para' => false);
            $summary = file_rewrite_pluginfile_urls($this->localcourse->summary, 'pluginfile.php', $this->localcoursecontext->id, 'course', 'summary', null);
            $content = \html_writer::start_tag('div', array('class' => 'summary'));
            $content.= format_text($summary, $this->localcourse->summaryformat, $options, $this->localcourse->id);
            $content.= \html_writer::end_tag('div'); // .summary
            return $content;
        }
        return '';
    }
    
    function percent_complete($maxgrade, $grade) {
        if ($grade >= $maxgrade) {
            return 100;
        }
        return floor( ($grade / $maxgrade) * 100 );
    }
    
    private function get_progress() {
        if (!empty($this->progress)) {
            return $this->progress;
        }
        
        if ($this->islocal) {
            $this->progress = $this->get_local_progress();
        } else {
            $this->progress = $this->get_remote_progress();
        }
        
        return $this->progress;
    }
    
    private function get_local_progress() {
        global $USER;

        $currentgrade = grade_get_grades($this->course, 'mod', 'subcourse', $this->id, $USER->id);
        $gradepass = $currentgrade->items[0]->gradepass;

        // Use the maximum grade if there is no passing grade set
        if ($gradepass == 0) {
            $gradepass = $currentgrade->items[0]->grademax;
        }

        if (!empty($currentgrade->items[0]->grades)) {
            $currentgrade = reset($currentgrade->items[0]->grades);
            if (isset($currentgrade->grade) and !($currentgrade->hidden)) {
                $grade = $currentgrade->grade;

                return $this->percent_complete($gradepass, $grade);
            }
        }

        return 0;
    }
    
    private function get_remote_progress () {
        return 21;
    }
    
    public function get_progress_bar() {
        //$progress    = subcourse_get_progress($cm);
        $progress = $this->get_progress();
        $progressbarclass = ($progress < 100 ? 'progress' : 'progress progress-success');

        $progressbar = \html_writer::start_div($progressbarclass, array('style' => 'margin-right: 2em;'));
        if (!$this->isenrolled) {
            $progressbar.= \html_writer::div('Not enrolled', 'bar bar-empty', array('style' => "width: 100%;"));
        } elseif (7 < $progress && $progress <= 100) {
            $progressbar.= \html_writer::div("$progress% Complete", 'bar', array('style' => "width: $progress%;"));
        } elseif ($progress > 0) {
            $progressbar.= \html_writer::div('', 'bar', array('style' => "width: $progress%;"));
        } else {
            $progressbar.= \html_writer::div('Not started', 'bar bar-empty', array('style' => "width: 100%;"));
        }
        $progressbar.= \html_writer::end_div();
        
        return $progressbar;
    }
    
    /**
     * 
     * Returns the remote course summary formatted for display.
     * 
     * @param type $remotecourse
     * @return String
     */
    function get_remote_course_summary() {
        $content = '';

        if ($this->remotecourse->summary) {
            $options = array('filter' => false, 'overflowdiv' => true, 'noclean' => true, 'para' => false);
            $summary = format_text($this->remotecourse->summary, $this->remotecourse->summaryformat, $options);

            $content.= \html_writer::start_tag('div', array('class' => 'summary'));
            $content.= $summary;
            $content.= \html_writer::end_tag('div'); // .summary
        }
        return $content;
    }
    
    /**
     * 
     * Returns true if the current user is enrolled in the remote course.
     * 
     * @global type $DB
     * @global type $USER
     * @param type $remotecourse
     * @return boolean
     */
    private function get_remote_course_is_enrolled() {
        global $DB, $USER;
        
        if (empty($this->remotecourse)) {
            return;
        }

        $service = \mnetservice_enrol::get_instance();

        $lastfetchenrolments = get_config('mnetservice_enrol', 'lastfetchenrolments');
        $usecache = true;
        if (!$usecache or empty($lastfetchenrolments) or (time()-$lastfetchenrolments > 600)) {
            // fetch fresh data from remote if we just came from the course selection screen
            // or every 10 minutes
            $usecache = false;
            $result = $service->req_course_enrolments($this->remotecourse->hostid, $this->remotecourse->remoteid, $usecache);

            if ($result !== true) {
                error_log($service->format_error_message($result));
                return false;
            }
        }

        // Get whether the current user is enrolled in the remote course.
        $conditions = array('hostid' => $this->remotecourse->hostid,
                            'remotecourseid' => $this->remotecourse->remoteid,
                            'userid' => $USER->id);
        $record = $DB->get_record('mnetservice_enrol_enrolments', $conditions, '*', IGNORE_MULTIPLE);
        return(!empty($record));
    }

}
