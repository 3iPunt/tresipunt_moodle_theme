<?php


require_once($CFG->dirroot.'/course/renderer.php');

class theme_tresipunt_core_course_renderer extends core_course_renderer
{

    public function frontpage_available_courses() {
        global $CFG;
        require_once($CFG->libdir. '/coursecatlib.php');

        $chelper = new coursecat_helper();
        $chelper->set_show_courses(self::COURSECAT_SHOW_COURSES_EXPANDED)->
        set_courses_display_options(array(
            'recursive' => true,
            'limit' => $CFG->frontpagecourselimit,
            'viewmoreurl' => new moodle_url('/course/index.php'),
            'viewmoretext' => new lang_string('fulllistofcourses')));

        $chelper->set_attributes(array('class' => 'frontpage-course-list-all'));
        $courses = coursecat::get(0)->get_courses($chelper->get_courses_display_options());
        $totalcount = coursecat::get(0)->get_courses_count($chelper->get_courses_display_options());
        if (!$totalcount && !$this->page->user_is_editing() && has_capability('moodle/course:create', context_system::instance())) {
            // Print link to create a new course, for the 1st available category.
            return $this->add_new_course_button();
        }
        return $this->coursecat_courses($chelper, $courses, $totalcount);
    }


    /**
     * Displays one course in the list of courses.
     *
     * This is an internal function, to display an information about just one course
     * please use {@link core_course_renderer::course_info_box()}
     *
     * @param coursecat_helper $chelper various display options
     * @param course_in_list|stdClass $course
     * @param string $additionalclasses additional classes to add to the main <div> tag (usually
     *    depend on the course position in list - first/last/even/odd)
     * @return string
     */
    protected function coursecat_coursebox(coursecat_helper $chelper, $course, $additionalclasses = '') {
        global $CFG;
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
        $classes = trim('coursebox clearfix '. $additionalclasses);
        if ($chelper->get_show_courses() >= self::COURSECAT_SHOW_COURSES_EXPANDED) {
            $nametag = 'h3';
        } else {
            $classes .= ' collapsed';
            $nametag = 'div';
        }

        $content .= $this->output->box_start('coursebox', "course-{$course->id}");

        $coursenamelink = html_writer::link(new moodle_url('/course/view.php', array('id' => $course->id)), $course->fullname, array('class' => $course->visible ? '' : 'dimmed'));
        $coursecover = '';
        $coursecoverlink = '';

        // display course overview files
        $contentimages = $contentfiles = '';

        if($course->has_course_overviewfiles()){
            foreach ($course->get_course_overviewfiles() as $file) {
                $isimage = $file->is_valid_image();
                $url = moodle_url::make_file_url('/pluginfile.php',   '/'. $file->get_contextid(). '/'. $file->get_component(). '/'.
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

        $content .= $contentimages. $contentfiles;

        if ($coursecover) {
            $coursecoverlink = html_writer::empty_tag('img', array('src' => $coursecover,
                'alt' => $this->strings->summary, 'class' => '','style' =>'clear:both; position: absolute; left: 50%; top: 50%; width: 100%; -webkit-transform: translate(-50%,-75%); -ms-transform: translate(-50%,-75%); transform: translate(-50%,-75%);'));
        }

        $nametag = 'h3';

        if ($coursecoverlink) {
            $content .= html_writer::start_tag('div', array('class' => 'course-image thumbnail', 'style' => 'position: relative;  width: 100%;  height: 150px;  overflow: hidden;'));
            $content .= html_writer::tag($nametag, $coursecoverlink, array('class' => ''));
            $content .= html_writer::end_tag('div');
        }

        $content .= html_writer::start_tag('div', array('class' => 'course_title'));

        // No need to pass title through s() here as it will be done automatically by html_writer.
        $attributes = array('title' => $course->fullname);
        if ($course->id > 0) {
            if (empty($course->visible)) {
                $attributes['class'] = 'dimmed';
            }
            $courseurl = new moodle_url('/course/view.php', array('id' => $course->id));
            $coursefullname = format_string(get_course_display_name_for_list($course), true, $course->id);
            $link = html_writer::link($courseurl, $coursefullname, $attributes);
            $content .= $this->output->heading($link, 3, 'coursename');
        } else {
            $content .= $this->output->heading(html_writer::link(
                    new moodle_url('/auth/mnet/jump.php', array('hostid' => $course->hostid, 'wantsurl' => '/course/view.php?id='.$course->remoteid)),
                    format_string($course->shortname, true), $attributes) . ' (' . format_string($course->hostname) . ')', 2, 'title');
        }
        $content .= $this->output->container('', 'flush');
        $content .= html_writer::end_tag('div');

        $content .= $this->output->container('', 'flush');
        $content .= $this->output->box_end();
        return $content;
    }

    /**
     * Renders HTML to display a list of course modules in a course section
     * Also displays "move here" controls in Javascript-disabled mode
     *
     * This function calls {@link core_course_renderer::course_section_cm()}
     *
     * @param stdClass $course course object
     * @param int|stdClass|section_info $section relative section number or section object
     * @param int $sectionreturn section number to return to
     * @param int $displayoptions
     * @return void
     */
    public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = array()) {
        global $USER;

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

        $extraclasses = '';
        if($section->section != 0){
            $extraclasses = 'accordion-section';
        }
        // Always output the section module list.
        $output .= html_writer::start_tag('section', array('class' => 'sectioncontent ' . $extraclasses));
        $output .= html_writer::tag('ul', $sectionoutput, array('class' => 'section img-text'));

        return $output;
    }
}

require_once($CFG->dirroot.'/course/format/topics/renderer.php');
class theme_tresipunt_format_topics_renderer extends format_topics_renderer {

    public function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $PAGE;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        $PAGE->requires->js('/theme/tresipunt/javascript/module.js');

        //@rpruano - JS that allows custom navigation in sections
        $PAGE->requires->js('/theme/tresipunt/javascript/course_sections.js');

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            } else if (course_get_format($course)->is_section_current($section)) {
                $sectionstyle = ' current';
            }
        }

//        $o .= html_writer::start_tag('div');
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
        $o.= html_writer::end_tag('div');

        $context = context_course::instance($course->id);
        $o .= $this->section_availability_message($section,
            has_capability('moodle/course:viewhiddensections', $context));
//        $o .= html_writer::end_tag('div');
        return $o;
    }

    public function section_footer() {
        $o = html_writer::end_tag('div');
        $o.= html_writer::end_tag('li');

        return $o;
    }
}
