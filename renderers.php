<?php
require_once($CFG->dirroot.'/course/renderer.php');
require_once($CFG->dirroot.'/course/format/topics/renderer.php');
require_once($CFG->dirroot.'/mod/quiz/renderer.php');
require_once($CFG->dirroot.'/question/engine/renderer.php');
require_once($CFG->dirroot.'/theme/boost/classes/output/core_renderer.php');

class theme_tresipunt_format_topics_renderer extends format_topics_renderer {
    public function section_title($section, $course) {
        global $DB,$PAGE;

        if ($section->section != 0) {
            $title = $DB->get_field('config_plugins', 'value', array('name' => 'sectionname_'.$course->id.'_'.$section->section));
            if (!$title) {
                $title = get_section_name($course, $section);
            }
        } else {
            $title = get_section_name($course, $section);
        }
        $url = course_get_url($course, $section->section, array('navigation' => true));

        if ($url) {
            $title = html_writer::link($url, $title);
        }
        return $title;
    }

    public function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $PAGE;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        $PAGE->requires->js('/theme/tresipunt/module.js');

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            } else if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

//      $o .= html_writer::start_tag('div');
        $o .= html_writer::start_tag('li', array('id' => 'section-'.$section->section,
            'class' => 'section main clearfix '.$sectionstyle, 'role'=>'region','style' => 'width:100% !important;float:left !important;clear:left !important',
            'aria-label'=> get_section_name($course, $section)));

        // Create a span that contains the section title to be used to create the keyboard section move menu.
        $o .= html_writer::tag('span', get_section_name($course, $section), array('class' => 'hidden sectionname'));

     /*   $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $rightcontent, array('class' => 'right side'));*/
        $o.= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        $sectionname = html_writer::tag('span', $this->section_title($section, $course));
        $o.= $this->output->heading($sectionname, 3, 'sectionname' . $classes);

        $o.= html_writer::start_tag('div', array('class' => 'summary'));
        $o.= $this->format_summary_text($section);
//      $o.= html_writer::end_tag('div');

        $context = context_course::instance($course->id);
        $o .= $this->section_availability_message($section,
                has_capability('moodle/course:viewhiddensections', $context));
        $o .= html_writer::end_tag('div');
        return $o;
    }

    public function section_footer() {
        $o = html_writer::end_tag('div');
        $o.= html_writer::end_tag('li');

        return $o;
    }
}

class theme_tresipunt_core_course_renderer extends core_course_renderer {
    public function course_section_cm($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array()) {
        global $USER;
        $output = '';
        // We return empty string (because course module will not be displayed at all)
        // if:
        // 1) The activity is not visible to users
        // and
        // 2) The 'availableinfo' is empty, i.e. the activity was
        //     hidden in a way that leaves no info, such as using the
        //     eye icon.

        if (!$mod->uservisible && empty($mod->availableinfo)) {
            return $output;
        }

        $indentclasses = 'mod-indent';
        if (!empty($mod->indent)) {
            $indentclasses .= ' mod-indent-'.$mod->indent;
            if ($mod->indent > 15) {
                $indentclasses .= ' mod-indent-huge';
            }
        }

        $output .= html_writer::start_tag('div');

        if ($this->page->user_is_editing()) {
            $output .= course_get_cm_move($mod, $sectionreturn);
        }


        $output .= html_writer::start_tag('div', array('class' => 'mod-indent-outer'));


        // This div is used to indent the content.
        $output .= html_writer::div('', $indentclasses);

        // Start a wrapper for the actual content to keep the indentation consistent
        $output .= html_writer::start_tag('div');

        // Display the link to the module (or do nothing if module has no url)
        $cmname = $this->course_section_cm_name($mod, $displayoptions);

        // RMA: Indent activities called 'Actividades.
        if (strpos($cmname, 'Actividades') != 0) {
            $cmname = '<span style="margin-left:30px">'.$cmname.'</span>';
        }

        if (!empty($cmname)) {
            // Start the div for the activity title, excluding the edit icons.
            $output .= html_writer::start_tag('div', array('class' => 'activityinstance'));
            $output .= $cmname;


            // Module can put text after the link (e.g. forum unread)
            $output .= $mod->afterlink;

            // Closing the tag which contains everything but edit icons. Content part of the module should not be part of this.
            $output .= html_writer::end_tag('div'); // .activityinstance
        }

        // If there is content but NO link (eg label), then display the
        // content here (BEFORE any icons). In this case cons must be
        // displayed after the content so that it makes more sense visually
        // and for accessibility reasons, e.g. if you have a one-line label
        // it should work similarly (at least in terms of ordering) to an
        // activity.
        $contentpart = $this->course_section_cm_text($mod, $displayoptions);
        $url = $mod->url;
        if (empty($url)) {
            $output .= $contentpart;
        }

        $modicons = '';
        if ($this->page->user_is_editing()) {
            $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);

            // RMA. Show edit SCORM only when ADMIN When teacher, show edit on
            // each activity except SCORM.
            global $DB;
            $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            $isteacheranywhere = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);

            if ( ($mod->modname != 'scorm' && $isteacheranywhere) || !$isteacheranywhere) {
                $modicons .= ' '. $this->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
                $modicons .= $mod->afterediticons;
            }
        }



        $modicons .= $this->course_section_cm_completion($course, $completioninfo, $mod, $displayoptions);

        if (!empty($modicons)) {
            $output .= html_writer::span($modicons, 'actions');
        }

        // If there is content AND a link, then display the content here
        // (AFTER any icons). Otherwise it was displayed before
        if (!empty($url)) {
            $output .= $contentpart;
        }

        // show availability info (if module is not available)
        $output .= $this->course_section_cm_availability($mod, $displayoptions);

        $output .= html_writer::end_tag('div'); // $indentclasses

        // End of indentation div.
        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');
        return $output;
    }

    public function course_section_cm_name_title(cm_info $mod, $displayoptions = array()) {
        $output = '';
        if (!$mod->uservisible && empty($mod->availableinfo)) {
            // Nothing to be displayed to the user.
            return $output;
        }
        $url = $mod->url;
        if (!$url) {
            return $output;
        }

        //Accessibility: for files get description via icon, this is very ugly hack!
        $instancename = $mod->get_formatted_name();
        $altname = $mod->modfullname;
        // Avoid unnecessary duplication: if e.g. a forum name already
        // includes the word forum (or Forum, etc) then it is unhelpful
        // to include that in the accessible description that is added.
        if (false !== strpos(core_text::strtolower($instancename),
                core_text::strtolower($altname))) {
            $altname = '';
        }
        // File type after name, for alphabetic lists (screen reader).
        if ($altname) {
            $altname = get_accesshide(' '.$altname);
        }

        // For items which are hidden but available to current user
        // ($mod->uservisible), we show those as dimmed only if the user has
        // viewhiddenactivities, so that teachers see 'items which might not
        // be available to some students' dimmed but students do not see 'item
        // which is actually available to current student' dimmed.
        $linkclasses = '';
        $accesstext = '';
        $textclasses = '';
        if ($mod->uservisible) {
            $conditionalhidden = $this->is_cm_conditionally_hidden($mod);
            $accessiblebutdim = (!$mod->visible || $conditionalhidden) &&
                has_capability('moodle/course:viewhiddenactivities', $mod->context);
            if ($accessiblebutdim) {
                $linkclasses .= ' dimmed';
                $textclasses .= ' dimmed_text';
                if ($conditionalhidden) {
                    $linkclasses .= ' conditionalhidden';
                    $textclasses .= ' conditionalhidden';
                }
                // Show accessibility note only if user can access the module himself.
                $accesstext = get_accesshide(get_string('hiddenfromstudents').':'. $mod->modfullname);
            }
        } else {
            $linkclasses .= ' dimmed';
            $textclasses .= ' dimmed_text';
        }

        // Get on-click attribute value if specified and decode the onclick - it
        // has already been encoded for display (puke).
        $onclick = htmlspecialchars_decode($mod->onclick, ENT_QUOTES);

        $groupinglabel = $mod->get_grouping_label($textclasses);

        // Display link itself.
        $activitylink = html_writer::empty_tag('img', array('src' => $mod->get_icon_url(),
                'class' => 'iconlarge activityicon', 'role' => 'presentation')) . $accesstext .
                html_writer::tag('span', $instancename . $altname, array('class' => 'instancename'));
        if ($mod->uservisible) {
            $output .= html_writer::link($url, $activitylink, array('class' => $linkclasses, 'onclick' => $onclick)) .
                    $groupinglabel;
        } else {
            // We may be displaying this just in order to show information
            // about visibility, without the actual link ($mod->uservisible)
            $output .= html_writer::tag('div', $activitylink, array('class' => $textclasses)) .
                    $groupinglabel;
        }
        return $output;
    }

    public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = array()) {
        global $DB,$USER;

        $output = '';
        $modinfo = get_fast_modinfo($course);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $completioninfo = new completion_info($course);

        // check if we are currently in the process of moving a module with JavaScript disabled
        $ismoving = $this->page->user_is_editing() && ismoving($course->id);
        if ($ismoving) {
            $movingpix = new pix_icon('movehere', get_string('movehere'), 'moodle', array('class' => 'movetarget'));
            $strmovefull = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
        }

        // Get the list of modules visible to user (excluding the module being moved if there is one)
        $moduleshtml = array();
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];

                if ($ismoving and $mod->id == $USER->activitycopy) {
                    // do not display moving mod
                    continue;
                }

                if ($modulehtml = $this->course_section_cm_list_item($course,
                        $completioninfo, $mod, $sectionreturn, $displayoptions)) {
                    $moduleshtml[$modnumber] = $modulehtml;
                }
            }
        }

        $sectionoutput = '';
        if (!empty($moduleshtml) || $ismoving) {
            foreach ($moduleshtml as $modnumber => $modulehtml) {
                if ($ismoving) {
                    $movingurl = new moodle_url('/course/mod.php', array('moveto' => $modnumber, 'sesskey' => sesskey()));
                    $sectionoutput .= html_writer::tag('li',
                            html_writer::link($movingurl, $this->output->render($movingpix), array('title' => $strmovefull)),
                            array('class' => 'movehere'));
                }

                $sectionoutput .= $modulehtml;
            }

            if ($ismoving) {
                $movingurl = new moodle_url('/course/mod.php', array('movetosection' => $section->id, 'sesskey' => sesskey()));
                $sectionoutput .= html_writer::tag('li',
                        html_writer::link($movingurl, $this->output->render($movingpix), array('title' => $strmovefull)),
                        array('class' => 'movehere'));
            }
        }

        // Always output the section module list.
        $output .= html_writer::start_tag('section', array('class' => 'sectioncontent', 'style' => 'display:none !important'));
        $output .= html_writer::start_tag('div', array('class' => 'col-md-3'));

        // Show Brightcove image and URL.
        $s = $section->section;
        $sectioncover = $DB->get_field('config_plugins', 'value', array('name' => 'sectioncover_'.$course->id.'_'.$s));

        if ($sectioncover) {
            $sectioncovertn = $DB->get_field('config_plugins', 'value', array('name' => 'sectioncovertn_'.$course->id.'_'.$s));
            $output .= '<a href="'.$sectioncover.'" target="_blank"><img class="img-responsive" src="'.$sectioncovertn.'" height="200" style="margin-top:20px;border:1px solid;" title="Clica en la imagen para ver el video">Clica en la imagen para ver el video</a>';
        }

        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'col-md-9'));
        $output .= html_writer::tag('ul', $sectionoutput, array('class' => 'section img-text'));
        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('section');

        return $output;
    }

    protected function coursecat_coursebox(coursecat_helper $chelper, $course, $additionalclasses = '') {
        global $CFG, $DB;
        if (!isset($this->strings->summary)) {
            $this->strings->summary = get_string('summary');
        }
        if ($chelper->get_show_courses() <= self::COURSECAT_SHOW_COURSES_COUNT) {
            return '';
        }
        if ($course instanceof stdClass) {
            require_once($CFG->libdir. '/coursecatlib.php');
            $course = new course_in_list($course);
        }
        $content = '';
        $classes = trim('col-md-3 '. $additionalclasses);
        //@rpruano this is doing strange things
//        if ($chelper->get_show_courses() >= self::COURSECAT_SHOW_COURSES_EXPANDED) {
//            $nametag = 'h3';
//        } else {
//            $classes .= ' collapsed';
//            $nametag = 'div';
//        }
        $nametag = 'h3';
        //END
        // .coursebox
        $content .= html_writer::start_tag('div', array(
            'class' => $classes,
            'data-courseid' => $course->id,
            'data-type' => self::COURSECAT_TYPE_COURSE,
        ));

        $content .= html_writer::start_tag('div', array('class' => 'info'));

        // course name
        $coursename = $chelper->get_course_formatted_name($course);

        $coursenamelink = html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $coursename, array('class' => $course->visible ? '' : 'dimmed'));

        // Get course cover. Remotely.
        // Find if exists current course
//        $coursecover = $DB->get_field('config_plugins', 'value', array('name' => 'coursecover_'.$course->id));
//
//        if (!$coursecover) {
//            // This a copy course. Get tresipunt course (ISBN_0)
//            $idnumber = $DB->get_field('course', 'idnumber', array('id' => $course->id));
//            $idnumber = explode('_', $idnumber);
//
//            $courseidtresipunt = $DB->get_field('course', 'id', array('idnumber' => $idnumber[0].'_0'));
//            $coursecover = $DB->get_field('config_plugins', 'value', array('name' => 'coursecover_'.$courseidtresipunt));
//        }
//
//        $coursecoverlink = html_writer::empty_tag('img', array('src' => $coursecover,
//                    'alt' => $this->strings->summary, 'class' => '','style' =>'clear:both; position: absolute; left: 50%; top: 50%; width: 100%; -webkit-transform: translate(-50%,-75%); -ms-transform: translate(-50%,-75%); transform: translate(-50%,-75%);'));

        $content .= html_writer::start_tag('div', array('class' => 'content', 'style' => 'background-color: #f4f4f4; text-align:center; padding: 0 0 15px'));
        $content .= $this->coursecat_coursebox_content($chelper, $course);

        $content .= html_writer::tag($nametag, $coursenamelink, array('class' => 'coursename'));

        $content .= html_writer::end_tag('div'); // .content
        // If we display course in collapsed form but the course has summary or course contacts, display the link to the info page.
        $content .= html_writer::start_tag('div', array('class' => 'moreinfo'));
        if ($chelper->get_show_courses() < self::COURSECAT_SHOW_COURSES_EXPANDED) {
            if ($course->has_summary() || $course->has_course_contacts() || $course->has_course_overviewfiles()) {
                $url = new moodle_url('/course/info.php', array('id' => $course->id));
               /* $image = html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/info'),
                    'alt' => $this->strings->summary));
                $content .= html_writer::link($url, $image, array('title' => $this->strings->summary));*/
                // Make sure JS file to expand course content is included.
                $this->coursecat_include_js();
            }

        }
        $content .= html_writer::end_tag('div'); // .moreinfo

        // print enrolmenticons
        if ($icons = enrol_get_course_info_icons($course)) {
            $content .= html_writer::start_tag('div', array('class' => 'enrolmenticons'));
            foreach ($icons as $pix_icon) {
                $content .= $this->render($pix_icon);
            }
            $content .= html_writer::end_tag('div'); // .enrolmenticons
        }

        $content .= html_writer::end_tag('div'); // .info


        $content .= html_writer::end_tag('div'); // .coursebox
        return $content;
    }

    protected function coursecat_coursebox_content(coursecat_helper $chelper, $course) {
        global $CFG, $DB;

        $coursenamelink = html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $course->fullname, array('class' => $course->visible ? '' : 'dimmed'));

        // Get course cover. Remotely.
        $coursecover = $DB->get_field('config_plugins', 'value', array('name' => 'coursecover_'.$course->id));

        if (!$coursecover) {
            // This a copy course. Get tresipunt course (ISBN_0)
            $idnumber = $DB->get_field('course', 'idnumber', array('id' => $course->id));
            $idnumber = explode('_', $idnumber);

            $courseidtresipunt = $DB->get_field('course', 'id', array('idnumber' => $idnumber[0].'_0'));
            $coursecover = $DB->get_field('config_plugins', 'value', array('name' => 'coursecover_'.$courseidtresipunt));
        }
        $coursecoverlink = html_writer::empty_tag('img', array('src' => $coursecover,
            'alt' => $this->strings->summary, 'class' => $course->visible ? '' : 'dimmed', 'style' => 'clear:both; position: absolute; left: 50%; top: 50%; width: 100%; -webkit-transform: translate(-50%,-75%); -ms-transform: translate(-50%,-75%); transform: translate(-50%,-75%);'));
        $nametag = 'h3';

//        if ($chelper->get_show_courses() < self::COURSECAT_SHOW_COURSES_EXPANDED) {
//            return '';
//        }
        if ($course instanceof stdClass) {
            require_once($CFG->libdir. '/coursecatlib.php');
            $course = new course_in_list($course);
        }
        $content = '';

        // display course summary
        if ($course->has_summary()) {
            $content .= html_writer::start_tag('div', array('class' => 'summary'));
            $content .= $chelper->get_course_formatted_summary($course,
                    array('overflowdiv' => true, 'noclean' => true, 'para' => false));
            $content .= html_writer::end_tag('div'); // .summary
        }


        // display course overview files
        $contentimages = $contentfiles = '';

        if($course->has_course_overviewfiles()){
            foreach ($course->get_course_overviewfiles() as $file) {
                $isimage = $file->is_valid_image();
                $url = file_encode_url("$CFG->wwwroot/pluginfile.php",
                        '/'. $file->get_contextid(). '/'. $file->get_component(). '/'.
                        $file->get_filearea(). $file->get_filepath(). $file->get_filename(), !$isimage);
                if ($isimage) {
                    $coursecoverlinkmoodle = html_writer::empty_tag('img', array('src' => $url,
                        'alt' => $this->strings->summary, 'class' => $course->visible ? '' : 'dimmed', 'style' => 'clear:both; position: absolute; left: 50%; top: 50%; width: 100%; -webkit-transform: translate(-50%,-75%); -ms-transform: translate(-50%,-75%); transform: translate(-50%,-75%);'));
                    $contentimages .= html_writer::start_tag('div', array('class' => 'course-image thumbnail', 'style' => 'position: relative;  width: 100%;  height: 200px;  overflow: hidden;'));
                    $contentimages .= html_writer::tag($nametag, $coursecoverlinkmoodle, array('class' => ''));
                    $contentimages .= html_writer::end_tag('div');
                }
                else {
                    $image = $this->output->pix_icon(file_file_icon($file, 24), $file->get_filename(), 'moodle');
                    $filename = html_writer::tag('span', $image, array('class' => 'fp-icon')).
                            html_writer::tag('span', $file->get_filename(), array('class' => 'fp-filename'));
                    $contentfiles .= html_writer::tag('span',
                            html_writer::link($url, $filename),
                            array('class' => 'coursefile fp-filename-icon'));
                }
            }
        }
        else {
            if ($coursecover && $coursecoverlink) {
                $contentimages .= html_writer::start_tag('div', array('class' => 'course-image thumbnail', 'style' => 'position: relative;  width: 100%;  height: 200px;  overflow: hidden;'));
                $contentimages .= html_writer::tag($nametag, $coursecoverlink, array('class' => ''));
                $contentimages .= html_writer::end_tag('div');
            }
        }

        $content .= $contentimages. $contentfiles;

        // display course contacts. See course_in_list::get_course_contacts()
        /*if ($course->has_course_contacts()) {
            $content .= html_writer::start_tag('ul', array('class' => 'teachers'));
            foreach ($course->get_course_contacts() as $userid => $coursecontact) {
                $name = $coursecontact['rolename'].': '.
                        html_writer::link(new moodle_url('/user/view.php',
                                array('id' => $userid, 'course' => SITEID)),
                            $coursecontact['username']);
                $content .= html_writer::tag('li', $name);
            }
            $content .= html_writer::end_tag('ul'); // .teachers
        }*/

        // display course category if necessary (for example in search results)
        if ($chelper->get_show_courses() == self::COURSECAT_SHOW_COURSES_EXPANDED_WITH_CAT) {
            require_once($CFG->libdir. '/coursecatlib.php');
            if ($cat = coursecat::get($course->category, IGNORE_MISSING)) {
                $content .= html_writer::start_tag('div', array('class' => 'coursecat'));
                $content .= get_string('category').': '.
                        html_writer::link(new moodle_url('/course/index.php', array('categoryid' => $cat->id)),
                                $cat->get_formatted_name(), array('class' => $cat->visible ? '' : 'dimmed'));
                $content .= html_writer::end_tag('div'); // .coursecat
            }
        }
        return $content;
    }

    function course_search_form($value = '', $format = 'plain') {
        global $CFG, $USER;

        static $count = 0;
        $formid = 'coursesearch';
        if ((++$count) > 1) {
            $formid .= $count;
        }

        switch ($format) {
            case 'navbar' :
                $formid = 'coursesearchnavbar';
                $inputid = 'navsearchbox';
                $inputsize = 20;
                break;
            case 'short' :
                $inputid = 'shortsearchbox';
                $inputsize = 12;
                break;
            default :
                $inputid = 'coursesearchbox';
                $inputsize = 30;
        }

        $strsearchcourses= get_string("searchcourses");
        $placeholdersearchcourses = get_string("mycourses") . '...';

        $searchurl = new moodle_url('/course/search.php');

        $output = html_writer::start_tag('form', array('id' => $formid, 'action' => $searchurl, 'method' => 'get'));
        $output .= html_writer::start_tag('fieldset', array('class' => 'coursesearchbox invisiblefieldset'));
        $output .= html_writer::tag('label', $strsearchcourses.': ', array('for' => $inputid));
        $output .= html_writer::empty_tag('input', array('type' => 'text', 'id' => $inputid,
            'size' => $inputsize, 'name' => 'search', 'value' => s($value), 'placeholder' => $placeholdersearchcourses, 'class' => 'form-control'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit',
            'value' => get_string('search'), 'class' => 'btn btn-primary'));
        $output .= html_writer::end_tag('fieldset');
        $output .= html_writer::end_tag('form');

        if(is_siteadmin($USER->id)) {
        $output .= html_writer::tag('div', html_writer::link($CFG->wwwroot.'/course/index.php', 'Todos los cursos'),
            array('class' => 'paging paging-morelink'));
        }

        return $output;
    }

    protected function coursecat_courses(coursecat_helper $chelper, $courses, $totalcount = null) {
        global $CFG;
        if ($totalcount === null) {
            $totalcount = count($courses);
        }
        if (!$totalcount) {
            // Courses count is cached during courses retrieval.
            return '';
        }

        if ($chelper->get_show_courses() == self::COURSECAT_SHOW_COURSES_AUTO) {
            // In 'auto' course display mode we analyse if number of courses is more or less than $CFG->courseswithsummarieslimit
            if ($totalcount <= $CFG->courseswithsummarieslimit) {
                $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED);
            } else {
                $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_COLLAPSED);
            }
        }

        // prepare content of paging bar if it is needed
        $paginationurl = $chelper->get_courses_display_option('paginationurl');
        $paginationallowall = $chelper->get_courses_display_option('paginationallowall');
        if ($totalcount > count($courses)) {
            // there are more results that can fit on one page
            if ($paginationurl) {
                // the option paginationurl was specified, display pagingbar
                $perpage = $chelper->get_courses_display_option('limit', $CFG->coursesperpage);
                $page = $chelper->get_courses_display_option('offset') / $perpage;
                $pagingbar = $this->paging_bar($totalcount, $page, $perpage,
                    $paginationurl->out(false, array('perpage' => $perpage)));
                if ($paginationallowall) {
                    $pagingbar .= html_writer::tag('div', html_writer::link($paginationurl->out(false, array('perpage' => 'all')),
                        get_string('showall', '', $totalcount)), array('class' => 'paging paging-showall'));
                }
            }
//            } else if ($viewmoreurl = $chelper->get_courses_display_option('viewmoreurl')) {
//                // the option for 'View more' link was specified, display more link
//                $viewmoretext = $chelper->get_courses_display_option('viewmoretext', new lang_string('viewmore'));
//                $morelink = html_writer::tag('div', html_writer::link($viewmoreurl, $viewmoretext),
//                    array('class' => 'paging paging-morelink'));
//            }
        } else if (($totalcount > $CFG->coursesperpage) && $paginationurl && $paginationallowall) {
            // there are more than one page of results and we are in 'view all' mode, suggest to go back to paginated view mode
            $pagingbar = html_writer::tag('div', html_writer::link($paginationurl->out(false, array('perpage' => $CFG->coursesperpage)),
                get_string('showperpage', '', $CFG->coursesperpage)), array('class' => 'paging paging-showperpage'));
        }

        // display list of courses
        $attributes = $chelper->get_and_erase_attributes('courses');
        $content = html_writer::start_tag('div', $attributes);

        if (!empty($pagingbar)) {
            $content .= $pagingbar;
        }

        $coursecount = 0;
        foreach ($courses as $course) {
            $coursecount ++;
            $classes = ($coursecount%2) ? 'odd' : 'even';
            if ($coursecount == 1) {
                $classes .= ' first';
            }
            if ($coursecount >= count($courses)) {
                $classes .= ' last';
            }
            $content .= $this->coursecat_coursebox($chelper, $course, $classes);
        }

        if (!empty($pagingbar)) {
            $content .= $pagingbar;
        }
        if (!empty($morelink)) {
            $content .= $morelink;
        }

        $content .= html_writer::end_tag('div'); // .courses

        return $content;
    }

      
}

require_once($CFG->dirroot.'/mod/quiz/classes/output/edit_renderer.php');
class theme_tresipunt_mod_quiz_edit_renderer extends mod_quiz\output\edit_renderer {
    public function section_shuffle_questions(mod_quiz\structure $structure, $section)
    {
        $checkboxattributes = array(
            'type' => 'checkbox',
            'id' => 'shuffle-' . $section->id,
            'value' => 1,
            'data-action' => 'shuffle_questions',
            'class' => 'cm-edit-action',
        );

        if (!$structure->can_be_edited()) {
            $checkboxattributes['disabled'] = 'disabled';
        }
        if ($section->shufflequestions) {
            $checkboxattributes['checked'] = 'checked';
        }

        if ($structure->is_first_section($section)) {
            $help = $this->help_icon('shufflequestions', 'quiz');
        } else {
            $help = '';
        }

        $progressspan = html_writer::span('', 'shuffle-progress');
        $checkbox = html_writer::empty_tag('input', $checkboxattributes);
        $label = html_writer::label(get_string('shufflequestions', 'quiz'),
            $checkboxattributes['id'], false);
        return html_writer::span($progressspan . $checkbox . $label . $help,
            'instanceshufflequestions', array('data-action' => 'shuffle_questions'));
    }
}

class theme_tresipunt_mod_quiz_renderer extends mod_quiz_renderer  {
    public function view_page_buttons(mod_quiz_view_object $viewobj) {
        global $CFG, $DB, $USER;
        $output = '';

       if (!$viewobj->quizhasquestions) {
            $output .= $this->no_questions_message($viewobj->canedit, $viewobj->editurl);
        }

        $output .= $this->access_messages($viewobj->preventmessages);

        if ($viewobj->buttontext) {
            $output .= $this->start_attempt_button($viewobj->buttontext,
                    $viewobj->startattempturl, $viewobj->preflightcheckform,
                    $viewobj->popuprequired, $viewobj->popupoptions);
        }

        if ($viewobj->showbacktocourse) {
            $output .= $this->single_button($viewobj->backtocourseurl,
                    get_string('backtocourse', 'quiz'), 'get',
                    array('class' => 'continuebutton'));
        }

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $isteacheranywhere = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);

        if (!is_siteadmin($USER->id) && !$isteacheranywhere) {
            $startattempturl = $viewobj->startattempturl;
            $startattempturl = str_replace('amp;', '', $startattempturl);
            echo '<script>location.href="'.$startattempturl.'";</script>';
        }
        return $output;
    }
}

class theme_tresipunt_core_question_renderer extends core_question_renderer {

    public function question(question_attempt $qa, qbehaviour_renderer $behaviouroutput,
                             qtype_renderer $qtoutput, question_display_options $options, $number) {

        $output = '';
        $output .= html_writer::start_tag('div', array(
            'id' => 'q' . $qa->get_slot(),
            'class' => implode(' ', array(
                'que',
                $qa->get_question()->qtype->name(),
                $qa->get_behaviour_name(),
                $qa->get_state_class($options->correctness && $qa->has_marks()),
            ))
        ));

        //@rpruano - Show/Hide question info block in quiz attempt
//        $output .= html_writer::tag('div',
//            $this->info($qa, $behaviouroutput, $qtoutput, $options, $number),
//            array('class' => 'info'));
        //END
        $qa->number = $number;
        $output .= html_writer::start_tag('div', array('class' => 'content'));

        $output .= html_writer::tag('div',
            $this->add_part_heading($qtoutput->formulation_heading(),
                $this->formulation($qa, $behaviouroutput, $qtoutput, $options)),
            array('class' => 'formulation clearfix'));
        $output .= html_writer::nonempty_tag('div',
            $this->add_part_heading(get_string('feedback', 'question'),
                $this->outcome($qa, $behaviouroutput, $qtoutput, $options)),
            array('class' => 'outcome clearfix'));
        $output .= html_writer::nonempty_tag('div',
            $this->add_part_heading(get_string('comments', 'question'),
                $this->manual_comment($qa, $behaviouroutput, $qtoutput, $options)),
            array('class' => 'comment clearfix'));
        $output .= html_writer::nonempty_tag('div',
            $this->response_history($qa, $behaviouroutput, $qtoutput, $options),
            array('class' => 'history clearfix'));

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }
}

class theme_tresipunt_core_renderer extends core_renderer {

    /** @var custom_menu_item language The language menu if created */
    protected $language = null;

    /**
     * Outputs the opening section of a box.
     *
     * @param string $classes A space-separated list of CSS classes
     * @param string $id An optional ID
     * @param array $attributes An array of other attributes to give the box.
     * @return string the HTML to output.
     */
    public function box_start($classes = 'generalbox', $id = null, $attributes = array()) {
        if (is_array($classes)) {
            $classes = implode(' ', $classes);
        }
        return parent::box_start($classes . ' p-y-1', $id, $attributes);
    }

    /**
     * Wrapper for header elements.
     *
     * @return string HTML to display the main header.
     */
    public function full_header() {
        global $PAGE, $USER;

        $html = '';

        if(is_siteadmin($USER->id) || ($PAGE->pagetype !== 'course-index-category' && $PAGE->pagetype !== 'site-index')) {

            $html = html_writer::start_tag('header', array('id' => 'page-header', 'class' => 'row'));
            $html .= html_writer::start_div('col-xs-12 p-a-1');
            $html .= html_writer::start_div('card');
            $html .= html_writer::start_div('card-block');
            $html .= html_writer::div($this->context_header_settings_menu(), 'pull-xs-right context-header-settings-menu');
            $html .= html_writer::start_div('pull-xs-left');
            $html .= $this->context_header();
            $html .= html_writer::end_div();
            $pageheadingbutton = $this->page_heading_button();
            if (empty($PAGE->layout_options['nonavbar'])) {
                $html .= html_writer::start_div('clearfix w-100 pull-xs-left', array('id' => 'page-navbar'));
                $html .= html_writer::tag('div', $this->navbar(), array('class' => 'breadcrumb-nav'));
                $html .= html_writer::div($pageheadingbutton, 'breadcrumb-button pull-xs-right');
                $html .= html_writer::end_div();
            } else if ($pageheadingbutton) {
                $html .= html_writer::div($pageheadingbutton, 'breadcrumb-button nonavbar pull-xs-right');
            }
            $html .= html_writer::tag('div', $this->course_header(), array('id' => 'course-header'));
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
            $html .= html_writer::end_tag('header');
        }
        return $html;
    }

    /**
     * The standard tags that should be included in the <head> tag
     * including a meta description for the front page
     *
     * @return string HTML fragment.
     */
    public function standard_head_html() {
        global $SITE, $PAGE;

        $output = parent::standard_head_html();
        if ($PAGE->pagelayout == 'frontpage') {
            $summary = s(strip_tags(format_text($SITE->summary, FORMAT_HTML)));
            if (!empty($summary)) {
                $output .= "<meta name=\"description\" content=\"$summary\" />\n";
            }
        }

        return $output;
    }

    /*
     * This renders the navbar.
     * Uses bootstrap compatible html.
     */
    public function navbar() {
        return $this->render_from_template('core/navbar', $this->page->navbar);
    }

    /*
    * This renders the return to course resources button.
    * Uses bootstrap compatible html.
    */
    public function return_course_button() {
        $pageheadingbutton = $this->page_heading_button();
        $html = html_writer::div($pageheadingbutton, 'breadcrumb-button pull-xs-right');
        return $html;
    }

    /**
     * We don't like these...
     *
     */
    public function edit_button(moodle_url $url) {
        return '';
    }

    /**
     * Override to inject the logo.
     *
     * @param array $headerinfo The header info.
     * @param int $headinglevel What level the 'h' tag will be.
     * @return string HTML for the header bar.
     */
    public function context_header($headerinfo = null, $headinglevel = 1) {
        global $SITE;

        if ($this->should_display_main_logo($headinglevel)) {
            $sitename = format_string($SITE->fullname, true, array('context' => context_course::instance(SITEID)));
            return html_writer::div(html_writer::empty_tag('img', [
                'src' => $this->get_logo_url(null, 150), 'alt' => $sitename]), 'logo');
        }

        return parent::context_header($headerinfo, $headinglevel);
    }

    /**
     * Get the compact logo URL.
     *
     * @return string
     */
    public function get_compact_logo_url($maxwidth = 100, $maxheight = 100) {
        return parent::get_compact_logo_url(null, 70);
    }

    /**
     * Whether we should display the main logo.
     *
     * @return bool
     */
    public function should_display_main_logo($headinglevel = 1) {
        global $PAGE;

        // Only render the logo if we're on the front page or login page and the we have a logo.
        $logo = $this->get_logo_url();
        if ($headinglevel == 1 && !empty($logo)) {
            if ($PAGE->pagelayout == 'frontpage' || $PAGE->pagelayout == 'login') {
                return true;
            }
        }

        return false;
    }
    /**
     * Whether we should display the logo in the navbar.
     *
     * We will when there are no main logos, and we have compact logo.
     *
     * @return bool
     */
    public function should_display_navbar_logo() {
        $logo = $this->get_compact_logo_url();
        return !empty($logo) && !$this->should_display_main_logo();
    }

    /*
     * Overriding the custom_menu function ensures the custom menu is
     * always shown, even if no menu items are configured in the global
     * theme settings page.
     */
    public function custom_menu($custommenuitems = '') {
        global $CFG;

        if (empty($custommenuitems) && !empty($CFG->custommenuitems)) {
            $custommenuitems = $CFG->custommenuitems;
        }
        $custommenu = new custom_menu($custommenuitems, current_language());
        return $this->render_custom_menu($custommenu);
    }

    /**
     * We want to show the custom menus as a list of links in the footer on small screens.
     * Just return the menu object exported so we can render it differently.
     */
    public function custom_menu_flat() {
        global $CFG;
        $custommenuitems = '';

        if (empty($custommenuitems) && !empty($CFG->custommenuitems)) {
            $custommenuitems = $CFG->custommenuitems;
        }
        $custommenu = new custom_menu($custommenuitems, current_language());
        $langs = get_string_manager()->get_list_of_translations();
        $haslangmenu = $this->lang_menu() != '';

        if ($haslangmenu) {
            $strlang = get_string('language');
            $currentlang = current_language();
            if (isset($langs[$currentlang])) {
                $currentlang = $langs[$currentlang];
            } else {
                $currentlang = $strlang;
            }
            $this->language = $custommenu->add($currentlang, new moodle_url('#'), $strlang, 10000);
            foreach ($langs as $langtype => $langname) {
                $this->language->add($langname, new moodle_url($this->page->url, array('lang' => $langtype)), $langname);
            }
        }

        return $custommenu->export_for_template($this);
    }

    /*
     * This renders the bootstrap top menu.
     *
     * This renderer is needed to enable the Bootstrap style navigation.
     */
    protected function render_custom_menu(custom_menu $menu) {
        global $CFG;

        $langs = get_string_manager()->get_list_of_translations();
        $haslangmenu = $this->lang_menu() != '';

        if (!$menu->has_children() && !$haslangmenu) {
            return '';
        }

        if ($haslangmenu) {
            $strlang = get_string('language');
            $currentlang = current_language();
            if (isset($langs[$currentlang])) {
                $currentlang = $langs[$currentlang];
            } else {
                $currentlang = $strlang;
            }
            $this->language = $menu->add($currentlang, new moodle_url('#'), $strlang, 10000);
            foreach ($langs as $langtype => $langname) {
                $this->language->add($langname, new moodle_url($this->page->url, array('lang' => $langtype)), $langname);
            }
        }

        $content = '';
        foreach ($menu->get_children() as $item) {
            $context = $item->export_for_template($this);
            $content .= $this->render_from_template('core/custom_menu_item', $context);
        }

        return $content;
    }

    /**
     * This code renders the navbar button to control the display of the custom menu
     * on smaller screens.
     *
     * Do not display the button if the menu is empty.
     *
     * @return string HTML fragment
     */
    public function navbar_button() {
        global $CFG;

        if (empty($CFG->custommenuitems) && $this->lang_menu() == '') {
            return '';
        }

        $iconbar = html_writer::tag('span', '', array('class' => 'icon-bar'));
        $button = html_writer::tag('a', $iconbar . "\n" . $iconbar. "\n" . $iconbar, array(
            'class'       => 'btn btn-navbar',
            'data-toggle' => 'collapse',
            'data-target' => '.nav-collapse'
        ));
        return $button;
    }

    /**
     * Renders tabtree
     *
     * @param tabtree $tabtree
     * @return string
     */
    protected function render_tabtree(tabtree $tabtree) {
        if (empty($tabtree->subtree)) {
            return '';
        }
        $data = $tabtree->export_for_template($this);
        return $this->render_from_template('core/tabtree', $data);
    }

    /**
     * Renders tabobject (part of tabtree)
     *
     * This function is called from {@link core_renderer::render_tabtree()}
     * and also it calls itself when printing the $tabobject subtree recursively.
     *
     * @param tabobject $tabobject
     * @return string HTML fragment
     */
    protected function render_tabobject(tabobject $tab) {
        throw new coding_exception('Tab objects should not be directly rendered.');
    }

    /**
     * Prints a nice side block with an optional header.
     *
     * @param block_contents $bc HTML for the content
     * @param string $region the region the block is appearing in.
     * @return string the HTML to be output.
     */
    public function block(block_contents $bc, $region) {
        $bc = clone($bc); // Avoid messing up the object passed in.
        if (empty($bc->blockinstanceid) || !strip_tags($bc->title)) {
            $bc->collapsible = block_contents::NOT_HIDEABLE;
        }

        $id = !empty($bc->attributes['id']) ? $bc->attributes['id'] : uniqid('block-');
        $context = new stdClass();
        $context->skipid = $bc->skipid;
        $context->blockinstanceid = $bc->blockinstanceid;
        $context->dockable = $bc->dockable;
        $context->id = $id;
        $context->hidden = $bc->collapsible == block_contents::HIDDEN;
        $context->skiptitle = strip_tags($bc->title);
        $context->showskiplink = !empty($context->skiptitle);
        $context->arialabel = $bc->arialabel;
        $context->ariarole = !empty($bc->attributes['role']) ? $bc->attributes['role'] : 'complementary';
        $context->type = $bc->attributes['data-block'];
        $context->title = $bc->title;
        $context->content = $bc->content;
        $context->annotation = $bc->annotation;
        $context->footer = $bc->footer;
        $context->hascontrols = !empty($bc->controls);
        if ($context->hascontrols) {
            $context->controls = $this->block_controls($bc->controls, $id);
        }

        return $this->render_from_template('core/block', $context);
    }

    /**
     * Returns the CSS classes to apply to the body tag.
     *
     * @since Moodle 2.5.1 2.6
     * @param array $additionalclasses Any additional classes to apply.
     * @return string
     */
    public function body_css_classes(array $additionalclasses = array()) {
        return $this->page->bodyclasses . ' ' . implode(' ', $additionalclasses);
    }

    /**
     * Renders preferences groups.
     *
     * @param  preferences_groups $renderable The renderable
     * @return string The output.
     */
    public function render_preferences_groups(preferences_groups $renderable) {
        return $this->render_from_template('core/preferences_groups', $renderable);
    }

    /**
     * Renders an action menu component.
     *
     * @param action_menu $menu
     * @return string HTML
     */
    public function render_action_menu(action_menu $menu) {

        // We don't want the class icon there!
        foreach ($menu->get_secondary_actions() as $action) {
            if ($action instanceof \action_menu_link && $action->has_class('icon')) {
                $action->attributes['class'] = preg_replace('/(^|\s+)icon(\s+|$)/i', '', $action->attributes['class']);
            }
        }

        if ($menu->is_empty()) {
            return '';
        }
        $context = $menu->export_for_template($this);

        // We do not want the icon with the caret, the caret is added by Bootstrap.
        if (empty($context->primary->menutrigger)) {
            $newurl = $this->pix_url('t/edit', 'moodle');
            $context->primary->icon['attributes'] = array_reduce($context->primary->icon['attributes'],
                function($carry, $item) use ($newurl) {
                    if ($item['name'] === 'src') {
                        $item['value'] = $newurl->out(false);
                    }
                    $carry[] = $item;
                    return $carry;
                }, []
            );
        }

        return $this->render_from_template('core/action_menu', $context);
    }

    /**
     * Implementation of user image rendering.
     *
     * @param help_icon $helpicon A help icon instance
     * @return string HTML fragment
     */
    protected function render_help_icon(help_icon $helpicon) {
        $context = $helpicon->export_for_template($this);
        return $this->render_from_template('core/help_icon', $context);
    }

    /**
     * Renders a single button widget.
     *
     * This will return HTML to display a form containing a single button.
     *
     * @param single_button $button
     * @return string HTML fragment
     */
    protected function render_single_button(single_button $button) {
        return $this->render_from_template('core/single_button', $button->export_for_template($this));
    }

    /**
     * Renders a single select.
     *
     * @param single_select $select The object.
     * @return string HTML
     */
    protected function render_single_select(single_select $select) {
        return $this->render_from_template('core/single_select', $select->export_for_template($this));
    }

    /**
     * Renders a paging bar.
     *
     * @param paging_bar $pagingbar The object.
     * @return string HTML
     */
    protected function render_paging_bar(paging_bar $pagingbar) {
        // Any more than 10 is not usable and causes wierd wrapping of the pagination in this theme.
        $pagingbar->maxdisplay = 10;
        return $this->render_from_template('core/paging_bar', $pagingbar->export_for_template($this));
    }

    /**
     * Renders a url select.
     *
     * @param url_select $select The object.
     * @return string HTML
     */
    protected function render_url_select(url_select $select) {
        return $this->render_from_template('core/url_select', $select->export_for_template($this));
    }

    /**
     * Renders a pix_icon widget and returns the HTML to display it.
     *
     * @param pix_icon $icon
     * @return string HTML fragment
     */
    protected function render_pix_icon(pix_icon $icon) {
        $data = $icon->export_for_template($this);
        foreach ($data['attributes'] as $key => $item) {
            $name = $item['name'];
            $value = $item['value'];
            if ($name == 'class') {
                $data['extraclasses'] = $value;
                unset($data['attributes'][$key]);
                $data['attributes'] = array_values($data['attributes']);
                break;
            }
        }
        return $this->render_from_template('core/pix_icon', $data);
    }

    /**
     * Renders the login form.
     *
     * @param \core_auth\output\login $form The renderable.
     * @return string
     */
    public function render_login(\core_auth\output\login $form) {
        global $SITE;

        $context = $form->export_for_template($this);

        // Override because rendering is not supported in template yet.
        $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        $context->errorformatted = $this->error_text($context->error);
        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $context->logourl = $url;
        $context->sitename = format_string($SITE->fullname, true, ['context' => context_course::instance(SITEID), "escape" => false]);

        return $this->render_from_template('theme_boost/core_login', $context);
    }

    /**
     * Render the login signup form into a nice template for the theme.
     *
     * @param mform $form
     * @return string
     */
    public function render_login_signup_form($form) {
        global $SITE;

        $context = $form->export_for_template($this);
        $url = $this->get_logo_url();
        if ($url) {
            $url = $url->out(false);
        }
        $context['logourl'] = $url;
        $context['sitename'] = format_string($SITE->fullname, true, ['context' => context_course::instance(SITEID), "escape" => false]);

        return $this->render_from_template('core/signup_form_layout', $context);
    }

    /**
     * This is an optional menu that can be added to a layout by a theme. It contains the
     * menu for the course administration, only on the course main page.
     *
     * @return string
     */
    public function context_header_settings_menu() {
        $context = $this->page->context;
        $menu = new action_menu();

        $items = $this->page->navbar->get_items();
        $currentnode = end($items);

        $showcoursemenu = false;
        $showfrontpagemenu = false;
        $showusermenu = false;

        // We are on the course home page.
        if (($context->contextlevel == CONTEXT_COURSE) &&
            !empty($currentnode) &&
            ($currentnode->type == navigation_node::TYPE_COURSE || $currentnode->type == navigation_node::TYPE_SECTION)) {
            $showcoursemenu = true;
        }

        $courseformat = course_get_format($this->page->course);
        // This is a single activity course format, always show the course menu on the activity main page.
        if ($context->contextlevel == CONTEXT_MODULE &&
            !$courseformat->has_view_page()) {

            $this->page->navigation->initialise();
            $activenode = $this->page->navigation->find_active_node();
            // If the settings menu has been forced then show the menu.
            if ($this->page->is_settings_menu_forced()) {
                $showcoursemenu = true;
            } else if (!empty($activenode) && ($activenode->type == navigation_node::TYPE_ACTIVITY ||
                    $activenode->type == navigation_node::TYPE_RESOURCE)) {

                // We only want to show the menu on the first page of the activity. This means
                // the breadcrumb has no additional nodes.
                if ($currentnode && ($currentnode->key == $activenode->key && $currentnode->type == $activenode->type)) {
                    $showcoursemenu = true;
                }
            }
        }

        // This is the site front page.
        if ($context->contextlevel == CONTEXT_COURSE &&
            !empty($currentnode) &&
            $currentnode->key === 'home') {
            $showfrontpagemenu = true;
        }

        // This is the user profile page.
        if ($context->contextlevel == CONTEXT_USER &&
            !empty($currentnode) &&
            ($currentnode->key === 'myprofile')) {
            $showusermenu = true;
        }


        if ($showfrontpagemenu) {
            $settingsnode = $this->page->settingsnav->find('frontpage', navigation_node::TYPE_SETTING);
            if ($settingsnode) {
                // Build an action menu based on the visible nodes from this navigation tree.
                $skipped = $this->build_action_menu_from_navigation($menu, $settingsnode, false, true);

                // We only add a list to the full settings menu if we didn't include every node in the short menu.
                if ($skipped) {
                    $text = get_string('morenavigationlinks');
                    $url = new moodle_url('/course/admin.php', array('courseid' => $this->page->course->id));
                    $link = new action_link($url, $text, null, null, new pix_icon('t/edit', $text));
                    $menu->add_secondary_action($link);
                }
            }
        } else if ($showcoursemenu) {
            $settingsnode = $this->page->settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);
            if ($settingsnode) {
                // Build an action menu based on the visible nodes from this navigation tree.
                $skipped = $this->build_action_menu_from_navigation($menu, $settingsnode, false, true);

                // We only add a list to the full settings menu if we didn't include every node in the short menu.
                if ($skipped) {
                    $text = get_string('morenavigationlinks');
                    $url = new moodle_url('/course/admin.php', array('courseid' => $this->page->course->id));
                    $link = new action_link($url, $text, null, null, new pix_icon('t/edit', $text));
                    $menu->add_secondary_action($link);
                }
            }
        } else if ($showusermenu) {
            // Get the course admin node from the settings navigation.
            $settingsnode = $this->page->settingsnav->find('useraccount', navigation_node::TYPE_CONTAINER);
            if ($settingsnode) {
                // Build an action menu based on the visible nodes from this navigation tree.
                $this->build_action_menu_from_navigation($menu, $settingsnode);
            }
        }

        return $this->render($menu);
    }

    /**
     * This is an optional menu that can be added to a layout by a theme. It contains the
     * menu for the most specific thing from the settings block. E.g. Module administration.
     *
     * @return string
     */
    public function region_main_settings_menu() {
        global $DB,$USER;

        $context = $this->page->context;
        $menu = new action_menu();

        if ($context->contextlevel == CONTEXT_MODULE) {

            $this->page->navigation->initialise();
            $node = $this->page->navigation->find_active_node();

            // RMA Checck if is scorm and editingteaacher so it cant see the acitonmenu
            $isscorm = $node->icon->component;

            $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
            $isteacheranywhere = $DB->record_exists('role_assignments', ['userid' => $USER->id, 'roleid' => $roleid]);

            if ( ($isscorm == 'scorm') && ($isteacheranywhere) ) {
                return false;
            }

            $buildmenu = false;
            // If the settings menu has been forced then show the menu.
            if ($this->page->is_settings_menu_forced()) {
                $buildmenu = true;
            } else if (!empty($node) && ($node->type == navigation_node::TYPE_ACTIVITY ||
                    $node->type == navigation_node::TYPE_RESOURCE)) {

                $items = $this->page->navbar->get_items();
                $navbarnode = end($items);
                // We only want to show the menu on the first page of the activity. This means
                // the breadcrumb has no additional nodes.
                if ($navbarnode && ($navbarnode->key === $node->key && $navbarnode->type == $node->type)) {
                    $buildmenu = true;
                }
            }
            if ($buildmenu) {
                // Get the course admin node from the settings navigation.
                $node = $this->page->settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
                if ($node) {
                    // Build an action menu based on the visible nodes from this navigation tree.
                    $this->build_action_menu_from_navigation($menu, $node);
                }
            }

        } else if ($context->contextlevel == CONTEXT_COURSECAT) {
            // For course category context, show category settings menu, if we're on the course category page.
            if ($this->page->pagetype === 'course-index-category') {
                $node = $this->page->settingsnav->find('categorysettings', navigation_node::TYPE_CONTAINER);
                if ($node) {
                    // Build an action menu based on the visible nodes from this navigation tree.
                    $this->build_action_menu_from_navigation($menu, $node);
                }
            }

        } else {
            $items = $this->page->navbar->get_items();
            $navbarnode = end($items);

            if ($navbarnode && ($navbarnode->key === 'participants')) {
                $node = $this->page->settingsnav->find('users', navigation_node::TYPE_CONTAINER);
                if ($node) {
                    // Build an action menu based on the visible nodes from this navigation tree.
                    $this->build_action_menu_from_navigation($menu, $node);
                }

            }
        }
        return $this->render($menu);
    }

    /**
     * Take a node in the nav tree and make an action menu out of it.
     * The links are injected in the action menu.
     *
     * @param action_menu $menu
     * @param navigation_node $node
     * @param boolean $indent
     * @param boolean $onlytopleafnodes
     * @return boolean nodesskipped - True if nodes were skipped in building the menu
     */
    private function build_action_menu_from_navigation(action_menu $menu,
                                                       navigation_node $node,
                                                       $indent = false,
                                                       $onlytopleafnodes = false) {
        $skipped = false;
        // Build an action menu based on the visible nodes from this navigation tree.
        foreach ($node->children as $menuitem) {
            if ($menuitem->display) {
                if ($onlytopleafnodes && $menuitem->children->count()) {
                    $skipped = true;
                    continue;
                }
                if ($menuitem->action) {
                    if ($menuitem->action instanceof action_link) {
                        $link = $menuitem->action;
                        // Give preference to setting icon over action icon.
                        if (!empty($menuitem->icon)) {
                            $link->icon = $menuitem->icon;
                        }
                    } else {
                        $link = new action_link($menuitem->action, $menuitem->text, null, null, $menuitem->icon);
                    }
                } else {
                    if ($onlytopleafnodes) {
                        $skipped = true;
                        continue;
                    }
                    $link = new action_link(new moodle_url('#'), $menuitem->text, null, ['disabled' => true], $menuitem->icon);
                }
                if ($indent) {
                    $link->add_class('m-l-1');
                }
                if (!empty($menuitem->classes)) {
                    $link->add_class(implode(" ", $menuitem->classes));
                }

                $menu->add_secondary_action($link);
                $skipped = $skipped || $this->build_action_menu_from_navigation($menu, $menuitem, true);
            }
        }
        return $skipped;
    }

    /**
     * Secure login info.
     *
     * @return string
     */
    public function secure_login_info() {
        return $this->login_info(false);
    }
}

require_once($CFG->dirroot.'/user/classes/output/myprofile/renderer.php');
require_once($CFG->dirroot.'/user/classes/output/myprofile/category.php');

class theme_tresipunt_core_user_myprofile_renderer extends core_user\output\myprofile\renderer {

    public function render_category(core_user\output\myprofile\category $category) {
        if($category->name == 'coursedetails' || $category->name == 'miscellaneous') {
            return '';
        }
        $classes = $category->classes;
        if (empty($classes)) {
            $return = \html_writer::start_tag('section', array('class' => 'node_category'));
        } else {
            $return = \html_writer::start_tag('section', array('class' => 'node_category ' . $classes));
        }
        $return .= \html_writer::tag('h3', $category->title);
        $nodes = $category->nodes;
        if (empty($nodes)) {
            // No nodes, nothing to render.
            return '';
        }
        $return .= \html_writer::start_tag('ul');
        foreach ($nodes as $node) {
            $return .= $this->render($node);
        }
        $return .= \html_writer::end_tag('ul');
        $return .= \html_writer::end_tag('section');
        return $return;
    }
}

//QUESTION TYPES RENDERERS

require_once($CFG->dirroot.'/question/type/rendererbase.php');
require_once($CFG->dirroot.'/question/type/ddimageortext/renderer.php');
class theme_tresipunt_qtype_ddimageortext_renderer extends qtype_ddimageortext_renderer
{

    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {
        global $PAGE;

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $questiontext = $qa->number . '. ' . $question->format_questiontext($qa);

        $output = html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        $bgimage = self::get_url_for_image($qa, 'bgimage');

        $img = html_writer::empty_tag('img', array(
            'src' => $bgimage, 'class' => 'dropbackground',
            'alt' => get_string('dropbackground', 'qtype_ddimageortext')));

        $droparea = html_writer::tag('div', $img, array('class' => 'droparea'));

        $dragimagehomes = '';
        foreach ($question->choices as $groupno => $group) {
            $dragimagehomesgroup = '';
            $orderedgroup = $question->get_ordered_choices($groupno);
            foreach ($orderedgroup as $choiceno => $dragimage) {
                $dragimageurl = self::get_url_for_image($qa, 'dragimage', $dragimage->id);
                $classes = array("group{$groupno}",
                    'draghome',
                    "dragitemhomes{$dragimage->no}",
                    "choice{$choiceno}");
                if ($dragimage->infinite) {
                    $classes[] = 'infinite';
                }
                if ($dragimageurl === null) {
                    $classes[] = 'yui3-cssfonts';
                    $dragimagehomesgroup .= html_writer::tag('div', $dragimage->text,
                        array('src' => $dragimageurl, 'class' => join(' ', $classes)));
                } else {
                    $dragimagehomesgroup .= html_writer::empty_tag('img',
                        array('src' => $dragimageurl, 'alt' => $dragimage->text,
                            'class' => join(' ', $classes)));
                }
            }
            $dragimagehomes .= html_writer::tag('div', $dragimagehomesgroup,
                array('class' => 'dragitemgroup' . $groupno));
        }

        $dragitemsclass = 'dragitems';
        if ($options->readonly) {
            $dragitemsclass .= ' readonly';
        }
        $dragitems = html_writer::tag('div', $dragimagehomes, array('class' => $dragitemsclass));
        $dropzones = html_writer::tag('div', '', array('class' => 'dropzones'));

        $hiddens = '';
        foreach ($question->places as $placeno => $place) {
            $varname = $question->field($placeno);
            list($fieldname, $html) = $this->hidden_field_for_qt_var($qa, $varname);
            $hiddens .= $html;
            $question->places[$placeno]->fieldname = $fieldname;
        }
        $output .= html_writer::tag('div',
            $droparea . $dragitems . $dropzones . $hiddens, array('class' => 'ddarea'));
        $topnode = 'div#q' . $qa->get_slot() . ' div.ddarea';
        $params = array('drops' => $question->places,
            'topnode' => $topnode,
            'readonly' => $options->readonly);

        $PAGE->requires->string_for_js('blank', 'qtype_ddimageortext');
        $PAGE->requires->yui_module('moodle-qtype_ddimageortext-dd',
            'M.qtype_ddimageortext.init_question',
            array($params));

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag('div',
                $question->get_validation_error($qa->get_last_qt_data()),
                array('class' => 'validationerror'));
        }
        return $output;
    }
}


require_once($CFG->dirroot.'/question/type/ddmarker/renderer.php');
class theme_tresipunt_qtype_ddmarker_renderer extends qtype_ddmarker_renderer
{
    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {
        global $PAGE, $OUTPUT;

        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        $questiontext = $qa->number . '. ' . $question->format_questiontext($qa);

        $output = html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        $bgimage = self::get_url_for_image($qa, 'bgimage');

        $img = html_writer::empty_tag('img', array(
            'src' => $bgimage, 'class' => 'dropbackground',
            'alt' => get_string('dropbackground', 'qtype_ddmarker')));

        $droparea = html_writer::tag('div', $img, array('class' => 'droparea'));

        $draghomes = '';
        $orderedgroup = $question->get_ordered_choices(1);
        $componentname = $question->qtype->plugin_name();
        $hiddenfields = '';
        foreach ($orderedgroup as $choiceno => $drag) {
            $classes = array('draghome',
                "choice{$choiceno}");
            if ($drag->infinite) {
                $classes[] = 'infinite';
            } else {
                $classes[] = 'dragno' . $drag->noofdrags;
            }
            $targeticonhtml =
                $OUTPUT->pix_icon('crosshairs', '', $componentname, array('class' => 'target'));

            $markertextattrs = array('class' => 'markertext');
            $markertext = html_writer::tag('span', $drag->text, $markertextattrs);
            $draghomesattrs = array('class' => join(' ', $classes));
            $draghomes .= html_writer::tag('span', $targeticonhtml . $markertext, $draghomesattrs);
            $hiddenfields .= $this->hidden_field_choice($qa, $choiceno, $drag->infinite, $drag->noofdrags);
        }

        $dragitemsclass = 'dragitems';
        if ($options->readonly) {
            $dragitemsclass .= ' readonly';
        }

        $dragitems = html_writer::tag('div', $draghomes, array('class' => $dragitemsclass));
        $dropzones = html_writer::tag('div', '', array('class' => 'dropzones'));
        $texts = html_writer::tag('div', '', array('class' => 'markertexts'));
        $output .= html_writer::tag('div',
            $droparea . $dragitems . $dropzones . $texts,
            array('class' => 'ddarea'));

        if ($question->showmisplaced && $qa->get_state()->is_finished()) {
            $visibledropzones = $question->get_drop_zones_without_hit($response);
        } else {
            $visibledropzones = array();
        }

        $topnode = 'div#q' . $qa->get_slot();
        $params = array('dropzones' => $visibledropzones,
            'topnode' => $topnode,
            'readonly' => $options->readonly);

        $PAGE->requires->yui_module('moodle-qtype_ddmarker-dd',
            'M.qtype_ddmarker.init_question',
            array($params));

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag('div',
                $question->get_validation_error($qa->get_last_qt_data()),
                array('class' => 'validationerror'));
        }

        if ($question->showmisplaced && $qa->get_state()->is_finished()) {
            $wrongparts = $question->get_drop_zones_without_hit($response);
            if (count($wrongparts) !== 0) {
                $wrongpartsstringspans = array();
                foreach ($wrongparts as $wrongpart) {
                    $wrongpartsstringspans[] = html_writer::nonempty_tag('span',
                        $wrongpart->markertext, array('class' => 'wrongpart'));
                }
                $wrongpartsstring = join(', ', $wrongpartsstringspans);
                $output .= html_writer::nonempty_tag('span',
                    get_string('followingarewrongandhighlighted',
                        'qtype_ddmarker',
                        $wrongpartsstring),
                    array('class' => 'wrongparts'));
            }
        }

        $output .= html_writer::tag('div', $hiddenfields, array('class' => 'ddform'));
        return $output;
    }
}

require_once($CFG->dirroot.'/question/type/ddwtos/renderer.php');
class theme_tresipunt_qtype_ddwtos_renderer extends qtype_ddwtos_renderer
{

    protected function qtext_classname()
    {
        return 'qtext ddwtos_questionid_for_javascript';
    }

    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {
        global $PAGE;

        $result = $this->parent_ddwtos_formulation($qa, $options);

        $inputids = array();
        $question = $qa->get_question();
        foreach ($question->places as $placeno => $place) {
            $inputids[$placeno] = $this->box_id($qa, $question->field($placeno));
        }

        $params = array(
            'inputids' => $inputids,
            'topnode' => 'div.que.ddwtos#q' . $qa->get_slot(),
            'readonly' => $options->readonly
        );

        $PAGE->requires->yui_module('moodle-qtype_ddwtos-dd',
            'M.qtype_ddwtos.init_question', array($params));

        return $result;
    }

    private function parent_ddwtos_formulation(question_attempt $qa,
                                                 question_display_options $options) {
        global $PAGE;

        $result = $this->parent_elements_embedded_formulation($qa, $options);

        $inputids = array();
        $question = $qa->get_question();
        foreach ($question->places as $placeno => $place) {
            $inputids[$placeno] = $this->box_id($qa, $question->field($placeno));
        }

        $params = array(
            'inputids' => $inputids,
            'topnode' => 'div.que.ddwtos#q' . $qa->get_slot(),
            'readonly' => $options->readonly
        );

        $PAGE->requires->yui_module('moodle-qtype_ddwtos-dd',
            'M.qtype_ddwtos.init_question', array($params));

        return $result;
    }

    public function parent_elements_embedded_formulation(question_attempt $qa,
                                             question_display_options $options) {

        $question = $qa->get_question();

        $questiontext = '';
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $questiontext .= $this->embedded_element($qa, $i, $options);
            }
            $questiontext .= $fragment;
        }

        $result = '';
        $result .= html_writer::tag('div', $qa->number . '. ' . $question->format_text($questiontext,
            $question->questiontextformat, $qa, 'question', 'questiontext', $question->id),
            array('class' => $this->qtext_classname(), 'id' => $this->qtext_id($qa)));

        $result .= $this->post_qtext_elements($qa, $options);

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error($qa->get_last_qt_data()),
                array('class' => 'validationerror'));
        }

        return $result;
    }
}

require_once($CFG->dirroot.'/question/type/description/renderer.php');
class theme_tresipunt_qtype_description_renderer extends qtype_description_renderer
{
    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {

        return html_writer::tag('div', $qa->number . '. ' . $qa->get_question()->format_questiontext($qa),
            array('class' => 'qtext'));
    }
}

require_once($CFG->dirroot.'/question/type/essay/renderer.php');
class theme_tresipunt_qtype_essay_renderer extends qtype_essay_renderer {
    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options) {

        $question = $qa->get_question();
        $responseoutput = $question->get_format_renderer($this->page);

        // Answer field.
        $step = $qa->get_last_step_with_qt_var('answer');

        if (!$step->has_qt_var('answer') && empty($options->readonly)) {
            // Question has never been answered, fill it with response template.
            $step = new question_attempt_step(array('answer'=>$question->responsetemplate));
        }

        if (empty($options->readonly)) {
            $answer = $responseoutput->response_area_input('answer', $qa,
                $step, $question->responsefieldlines, $options->context);

        } else {
            $answer = $responseoutput->response_area_read_only('answer', $qa,
                $step, $question->responsefieldlines, $options->context);
        }

        $files = '';
        if ($question->attachments) {
            if (empty($options->readonly)) {
                $files = $this->files_input($qa, $question->attachments, $options);

            } else {
                $files = $this->files_read_only($qa, $options);
            }
        }

        $result = '';
        $result .= html_writer::tag('div', $qa->number . '. ' . $question->format_questiontext($qa),
            array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::tag('div', $answer, array('class' => 'answer'));
        $result .= html_writer::tag('div', $files, array('class' => 'attachments'));
        $result .= html_writer::end_tag('div');

        return $result;
    }
}

require_once($CFG->dirroot.'/question/type/gapselect/renderer.php');
class theme_tresipunt_qtype_gapselect_renderer extends qtype_gapselect_renderer {
    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {

        $question = $qa->get_question();

        $questiontext = '';
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $questiontext .= $this->embedded_element($qa, $i, $options);
            }
            $questiontext .= $fragment;
        }

        $result = '';

        $result .= html_writer::tag('div', $qa->number . '. ' . $question->format_text($questiontext,
                $question->questiontextformat, $qa, 'question', 'questiontext', $question->id),
            array('class' => $this->qtext_classname(), 'id' => $this->qtext_id($qa)));

        $result .= $this->post_qtext_elements($qa, $options);

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error($qa->get_last_qt_data()),
                array('class' => 'validationerror'));
        }

        return $result;
    }

}

require_once($CFG->dirroot.'/question/type/match/renderer.php');
class theme_tresipunt_qtype_match_renderer extends qtype_match_renderer
{

    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {

        $question = $qa->get_question();
        $stemorder = $question->get_stem_order();
        $response = $qa->get_last_qt_data();

        $choices = $this->format_choices($question);

        $result = '';
        $result .= html_writer::tag('div', $qa->number . '. ' . $question->format_questiontext($qa),
            array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::start_tag('table', array('class' => 'answer'));
        $result .= html_writer::start_tag('tbody');

        $parity = 0;
        $i = 1;
        foreach ($stemorder as $key => $stemid) {

            $result .= html_writer::start_tag('tr', array('class' => 'r' . $parity));
            $fieldname = 'sub' . $key;

            $result .= html_writer::tag('td', $this->format_stem_text($qa, $stemid),
                array('class' => 'text'));

            $classes = 'control';
            $feedbackimage = '';

            if (array_key_exists($fieldname, $response)) {
                $selected = $response[$fieldname];
            } else {
                $selected = 0;
            }

            $fraction = (int)($selected && $selected == $question->get_right_choice_for($stemid));

            if ($options->correctness && $selected) {
                $classes .= ' ' . $this->feedback_class($fraction);
                $feedbackimage = $this->feedback_image($fraction);
            }

            $result .= html_writer::tag('td',
                html_writer::label(get_string('answer', 'qtype_match', $i),
                    'menu' . $qa->get_qt_field_name('sub' . $key), false,
                    array('class' => 'accesshide')) .
                html_writer::select($choices, $qa->get_qt_field_name('sub' . $key), $selected,
                    array('0' => 'choose'), array('disabled' => $options->readonly, 'class' => 'custom-select m-l-1')) .
                ' ' . $feedbackimage, array('class' => $classes));

            $result .= html_writer::end_tag('tr');
            $parity = 1 - $parity;
            $i++;
        }
        $result .= html_writer::end_tag('tbody');
        $result .= html_writer::end_tag('table');

        $result .= html_writer::end_tag('div'); // Closes <div class="ablock">.

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error($response),
                array('class' => 'validationerror'));
        }

        return $result;
    }
}

require_once($CFG->dirroot.'/question/type/multianswer/renderer.php');
class theme_tresipunt_qtype_multianswer_renderer extends qtype_multianswer_renderer
{

    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {
        $question = $qa->get_question();

        $output = '';
        $subquestions = array();
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $index = $question->places[$i];
                $token = 'qtypemultianswer' . $i . 'marker';
                $token = '<span class="nolink">' . $token . '</span>';
                $output .= $token;
                $subquestions[$token] = $this->subquestion($qa, $options, $index,
                    $question->subquestions[$index]);
            }
            $output .= $fragment;
        }
        $output = $qa->number . '. ' . $question->format_text($output, $question->questiontextformat,
                $qa, 'question', 'questiontext', $question->id);
        $output = str_replace(array_keys($subquestions), array_values($subquestions), $output);

        if ($qa->get_state() == question_state::$invalid) {
            $output .= html_writer::nonempty_tag('div',
                $question->get_validation_error($qa->get_last_qt_data()),
                array('class' => 'validationerror'));
        }

        $this->page->requires->js_init_call('M.qtype_multianswer.init',
            array('#q' . $qa->get_slot()), false, array(
                'name' => 'qtype_multianswer',
                'fullpath' => '/question/type/multianswer/module.js',
                'requires' => array('base', 'node', 'event', 'overlay'),
            ));

        return $output;
    }
}

require_once($CFG->dirroot.'/question/type/multichoice/renderer.php');
class theme_tresipunt_qtype_multichoice_single_renderer extends qtype_multichoice_single_renderer
{

    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {

        $question = $qa->get_question();
        $response = $question->get_response($qa);

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => $this->get_input_type(),
            'name' => $inputname,
        );

        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $radiobuttons = array();
        $feedbackimg = array();
        $feedback = array();
        $classes = array();
        foreach ($question->get_order($qa) as $value => $ansid) {
            $ans = $question->answers[$ansid];
            $inputattributes['name'] = $this->get_input_name($qa, $value);
            $inputattributes['value'] = $this->get_input_value($value);
            $inputattributes['id'] = $this->get_input_id($qa, $value);
            $isselected = $question->is_choice_selected($response, $value);
            if ($isselected) {
                $inputattributes['checked'] = 'checked';
            } else {
                unset($inputattributes['checked']);
            }
            $hidden = '';
            if (!$options->readonly && $this->get_input_type() == 'checkbox') {
                $hidden = html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => $inputattributes['name'],
                    'value' => 0,
                ));
            }
            $radiobuttons[] = $hidden . html_writer::empty_tag('input', $inputattributes) .
                html_writer::tag('label',
                    $this->number_in_style($value, $question->answernumbering) .
                    $question->make_html_inline($question->format_text(
                        $ans->answer, $ans->answerformat,
                        $qa, 'question', 'answer', $ansid)),
                    array('for' => $inputattributes['id'], 'class' => 'm-l-1'));

            // Param $options->suppresschoicefeedback is a hack specific to the
            // oumultiresponse question type. It would be good to refactor to
            // avoid refering to it here.
            if ($options->feedback && empty($options->suppresschoicefeedback) &&
                $isselected && trim($ans->feedback)
            ) {
                $feedback[] = html_writer::tag('div',
                    $question->make_html_inline($question->format_text(
                        $ans->feedback, $ans->feedbackformat,
                        $qa, 'question', 'answerfeedback', $ansid)),
                    array('class' => 'specificfeedback'));
            } else {
                $feedback[] = '';
            }
            $class = 'r' . ($value % 2);
            if ($options->correctness && $isselected) {
                $feedbackimg[] = $this->feedback_image($this->is_right($ans));
                $class .= ' ' . $this->feedback_class($this->is_right($ans));
            } else {
                $feedbackimg[] = '';
            }
            $classes[] = $class;
        }

        $result = '';
        $result .= html_writer::tag('div', $qa->number . '. ' . $question->format_questiontext($qa),
            array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::tag('div', $this->prompt(), array('class' => 'prompt'));

        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        foreach ($radiobuttons as $key => $radio) {
            $result .= html_writer::tag('div', $radio . ' ' . $feedbackimg[$key] . $feedback[$key],
                    array('class' => $classes[$key])) . "\n";
        }
        $result .= html_writer::end_tag('div'); // Answer.

        $result .= html_writer::end_tag('div'); // Ablock.

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error($qa->get_last_qt_data()),
                array('class' => 'validationerror'));
        }

        return $result;
    }
}

class theme_tresipunt_qtype_multichoice_multi_renderer extends qtype_multichoice_multi_renderer
{

    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {

        $question = $qa->get_question();
        $response = $question->get_response($qa);

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => $this->get_input_type(),
            'name' => $inputname,
        );

        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $radiobuttons = array();
        $feedbackimg = array();
        $feedback = array();
        $classes = array();
        foreach ($question->get_order($qa) as $value => $ansid) {
            $ans = $question->answers[$ansid];
            $inputattributes['name'] = $this->get_input_name($qa, $value);
            $inputattributes['value'] = $this->get_input_value($value);
            $inputattributes['id'] = $this->get_input_id($qa, $value);
            $isselected = $question->is_choice_selected($response, $value);
            if ($isselected) {
                $inputattributes['checked'] = 'checked';
            } else {
                unset($inputattributes['checked']);
            }
            $hidden = '';
            if (!$options->readonly && $this->get_input_type() == 'checkbox') {
                $hidden = html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => $inputattributes['name'],
                    'value' => 0,
                ));
            }
            $radiobuttons[] = $hidden . html_writer::empty_tag('input', $inputattributes) .
                html_writer::tag('label',
                    $this->number_in_style($value, $question->answernumbering) .
                    $question->make_html_inline($question->format_text(
                        $ans->answer, $ans->answerformat,
                        $qa, 'question', 'answer', $ansid)),
                    array('for' => $inputattributes['id'], 'class' => 'm-l-1'));

            // Param $options->suppresschoicefeedback is a hack specific to the
            // oumultiresponse question type. It would be good to refactor to
            // avoid refering to it here.
            if ($options->feedback && empty($options->suppresschoicefeedback) &&
                $isselected && trim($ans->feedback)
            ) {
                $feedback[] = html_writer::tag('div',
                    $question->make_html_inline($question->format_text(
                        $ans->feedback, $ans->feedbackformat,
                        $qa, 'question', 'answerfeedback', $ansid)),
                    array('class' => 'specificfeedback'));
            } else {
                $feedback[] = '';
            }
            $class = 'r' . ($value % 2);
            if ($options->correctness && $isselected) {
                $feedbackimg[] = $this->feedback_image($this->is_right($ans));
                $class .= ' ' . $this->feedback_class($this->is_right($ans));
            } else {
                $feedbackimg[] = '';
            }
            $classes[] = $class;
        }

        $result = '';
        $result .= html_writer::tag('div', $qa->number . '. ' . $question->format_questiontext($qa),
            array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::tag('div', $this->prompt(), array('class' => 'prompt'));

        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        foreach ($radiobuttons as $key => $radio) {
            $result .= html_writer::tag('div', $radio . ' ' . $feedbackimg[$key] . $feedback[$key],
                    array('class' => $classes[$key])) . "\n";
        }
        $result .= html_writer::end_tag('div'); // Answer.

        $result .= html_writer::end_tag('div'); // Ablock.

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error($qa->get_last_qt_data()),
                array('class' => 'validationerror'));
        }

        return $result;
    }
}

require_once($CFG->dirroot.'/question/type/numerical/renderer.php');
class theme_tresipunt_qtype_numerical_renderer extends qtype_numerical_renderer
{
    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {

        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');
        if ($question->has_separate_unit_field()) {
            $selectedunit = $qa->get_last_qt_var('unit');
        } else {
            $selectedunit = null;
        }

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => 'text',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'size' => 80,
            'class' => 'form-control',
        );

        if ($options->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }

        $feedbackimg = '';
        if ($options->correctness) {
            list($value, $unit, $multiplier) = $question->ap->apply_units(
                $currentanswer, $selectedunit);
            $answer = $question->get_matching_answer($value, $multiplier);
            if ($answer) {
                $fraction = $question->apply_unit_penalty($answer->fraction, $answer->unitisright);
            } else {
                $fraction = 0;
            }
            $inputattributes['class'] .= ' ' . $this->feedback_class($fraction);
            $feedbackimg = $this->feedback_image($fraction);
        }

        $questiontext = $qa->number . '. ' . $question->format_questiontext($qa);
        $placeholder = false;
        if (preg_match('/_____+/', $questiontext, $matches)) {
            $placeholder = $matches[0];
            $inputattributes['size'] = round(strlen($placeholder) * 1.1);
        }

        $input = html_writer::empty_tag('input', $inputattributes) . $feedbackimg;

        if ($question->has_separate_unit_field()) {
            if ($question->unitdisplay == qtype_numerical::UNITRADIO) {
                $choices = array();
                $i = 1;
                foreach ($question->ap->get_unit_options() as $unit) {
                    $id = $qa->get_qt_field_name('unit') . '_' . $i++;
                    $radioattrs = array('type' => 'radio', 'id' => $id, 'value' => $unit,
                        'name' => $qa->get_qt_field_name('unit'));
                    if ($unit == $selectedunit) {
                        $radioattrs['checked'] = 'checked';
                    }
                    $choices[] = html_writer::tag('label',
                        html_writer::empty_tag('input', $radioattrs) . $unit,
                        array('for' => $id, 'class' => 'unitchoice'));
                }

                $unitchoice = html_writer::tag('span', implode(' ', $choices),
                    array('class' => 'unitchoices'));

            } else if ($question->unitdisplay == qtype_numerical::UNITSELECT) {
                $unitchoice = html_writer::label(get_string('selectunit', 'qtype_numerical'),
                    'menu' . $qa->get_qt_field_name('unit'), false, array('class' => 'accesshide'));
                $unitchoice .= html_writer::select($question->ap->get_unit_options(),
                    $qa->get_qt_field_name('unit'), $selectedunit, array('' => 'choosedots'),
                    array('disabled' => $options->readonly));
            }

            if ($question->ap->are_units_before()) {
                $input = $unitchoice . ' ' . $input;
            } else {
                $input = $input . ' ' . $unitchoice;
            }
        }

        if ($placeholder) {
            $inputinplace = html_writer::tag('label', get_string('answer'),
                array('for' => $inputattributes['id'], 'class' => 'accesshide'));
            $inputinplace .= $input;
            $questiontext = substr_replace($questiontext, $inputinplace,
                strpos($questiontext, $placeholder), strlen($placeholder));
        }

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        if (!$placeholder) {
            $result .= html_writer::start_tag('div', array('class' => 'ablock form-inline'));
            $result .= html_writer::tag('label', get_string('answercolon', 'qtype_numerical'), array('for' => $inputattributes['id']));
            $result .= html_writer::tag('span', $input, array('class' => 'answer'));
            $result .= html_writer::end_tag('div');
        }

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error(array('answer' => $currentanswer, 'unit' => $selectedunit)),
                array('class' => 'validationerror'));
        }

        return $result;
    }
}

require_once($CFG->dirroot.'/question/type/shortanswer/renderer.php');
class theme_tresipunt_qtype_shortanswer_renderer extends qtype_shortanswer_renderer {
    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options) {

        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => 'text',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'size' => 80,
            'class' => 'form-control',
        );

        if ($options->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }

        $feedbackimg = '';
        if ($options->correctness) {
            $answer = $question->get_matching_answer(array('answer' => $currentanswer));
            if ($answer) {
                $fraction = $answer->fraction;
            } else {
                $fraction = 0;
            }
            $inputattributes['class'] .= ' ' . $this->feedback_class($fraction);
            $feedbackimg = $this->feedback_image($fraction);
        }

        $questiontext = $qa->number . '. ' . $question->format_questiontext($qa);
        $placeholder = false;
        if (preg_match('/_____+/', $questiontext, $matches)) {
            $placeholder = $matches[0];
            $inputattributes['size'] = round(strlen($placeholder) * 1.1);
        }
        $input = html_writer::empty_tag('input', $inputattributes) . $feedbackimg;

        if ($placeholder) {
            $inputinplace = html_writer::tag('label', get_string('answer'),
                array('for' => $inputattributes['id'], 'class' => 'accesshide'));
            $inputinplace .= $input;
            $questiontext = substr_replace($questiontext, $inputinplace,
                strpos($questiontext, $placeholder), strlen($placeholder));
        }

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));

        if (!$placeholder) {
            $result .= html_writer::start_tag('div', array('class' => 'ablock form-inline'));
            $result .= html_writer::tag('label', get_string('answer', 'qtype_shortanswer',
                html_writer::tag('span', $input, array('class' => 'answer'))),
                array('for' => $inputattributes['id']));
            $result .= html_writer::end_tag('div');
        }

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error(array('answer' => $currentanswer)),
                array('class' => 'validationerror'));
        }

        return $result;
    }
}

require_once($CFG->dirroot.'/question/type/truefalse/renderer.php');
class theme_tresipunt_qtype_truefalse_renderer extends qtype_truefalse_renderer
{
    public function formulation_and_controls(question_attempt $qa,
                                             question_display_options $options)
    {

        $question = $qa->get_question();
        $response = $qa->get_last_qt_var('answer', '');

        $inputname = $qa->get_qt_field_name('answer');
        $trueattributes = array(
            'type' => 'radio',
            'name' => $inputname,
            'value' => 1,
            'id' => $inputname . 'true',
        );
        $falseattributes = array(
            'type' => 'radio',
            'name' => $inputname,
            'value' => 0,
            'id' => $inputname . 'false',
        );

        if ($options->readonly) {
            $trueattributes['disabled'] = 'disabled';
            $falseattributes['disabled'] = 'disabled';
        }

        // Work out which radio button to select (if any).
        $truechecked = false;
        $falsechecked = false;
        $responsearray = array();
        if ($response) {
            $trueattributes['checked'] = 'checked';
            $truechecked = true;
            $responsearray = array('answer' => 1);
        } else if ($response !== '') {
            $falseattributes['checked'] = 'checked';
            $falsechecked = true;
            $responsearray = array('answer' => 1);
        }

        // Work out visual feedback for answer correctness.
        $trueclass = '';
        $falseclass = '';
        $truefeedbackimg = '';
        $falsefeedbackimg = '';
        if ($options->correctness) {
            if ($truechecked) {
                $trueclass = ' ' . $this->feedback_class((int)$question->rightanswer);
                $truefeedbackimg = $this->feedback_image((int)$question->rightanswer);
            } else if ($falsechecked) {
                $falseclass = ' ' . $this->feedback_class((int)(!$question->rightanswer));
                $falsefeedbackimg = $this->feedback_image((int)(!$question->rightanswer));
            }
        }

        $radiotrue = html_writer::empty_tag('input', $trueattributes) .
            html_writer::tag('label', get_string('true', 'qtype_truefalse'),
                array('for' => $trueattributes['id'], 'class' => 'm-l-1'));
        $radiofalse = html_writer::empty_tag('input', $falseattributes) .
            html_writer::tag('label', get_string('false', 'qtype_truefalse'),
                array('for' => $falseattributes['id'], 'class' => 'm-l-1'));

        $result = '';
        $result .= html_writer::tag('div', $qa->number . '. ' . $question->format_questiontext($qa),
            array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::tag('div', get_string('selectone', 'qtype_truefalse'),
            array('class' => 'prompt'));

        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        $result .= html_writer::tag('div', $radiotrue . ' ' . $truefeedbackimg,
            array('class' => 'r0' . $trueclass));
        $result .= html_writer::tag('div', $radiofalse . ' ' . $falsefeedbackimg,
            array('class' => 'r1' . $falseclass));
        $result .= html_writer::end_tag('div'); // Answer.

        $result .= html_writer::end_tag('div'); // Ablock.

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                $question->get_validation_error($responsearray),
                array('class' => 'validationerror'));
        }

        return $result;
    }
}

