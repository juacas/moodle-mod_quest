<?php  // $Id: assessments_autors.php
  // This file is part of INTUITEL http://www.intuitel.eu as an adaptor for Moodle http://moodle.org/
//
// INTUITEL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// INTUITEL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with INTUITEL for Moodle Adaptor.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Questournament activity for Moodle
 *
 * Module developed at the University of Valladolid
 * Designed and directed by Juan Pablo de Castro with the effort of many other
 * students of telecommunciation engineering
 * this module is provides as-is without any guarantee. Use it as your own risk.
 *
 * @author Juan Pablo de Castro and many others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @copyright (c) 2014, INTUITEL Consortium
 * @package mod_quest
  ACTIONS:
  - displaygradingform
  - editelements
  - insertelements
  - updateassessment

 * ************************************************* */

    require_once("../../config.php");
    require_once("lib.php");
    require_once("locallib.php");

     $id=optional_param('id',-1,PARAM_INT);     // Course Module ID
     $qid=optional_param('qid',-1,PARAM_INT);     // quest ID
     //...get the action
     $action = required_param('action',PARAM_ALPHA);

     global $DB, $OUTPUT, $PAGE;
    // get some useful stuff...
    if ($id) {
       $cm = get_coursemodule_from_id("quest",$id,null,null,MUST_EXIST);
       $quest = $DB->get_record("quest", array("id"=> $cm->instance),'*',MUST_EXIST);
    } else if ($qid) {
        $quest = $DB->get_record("quest", array("id"=>$qid),'*',MUST_EXIST);
        $cm = get_coursemodule_from_instance("quest", $quest->id, $quest->course,null,MUST_EXIST);
    } else {
        print_error("No id given",'quest');
    }
    $course = get_course($cm->course);

    $url =  new moodle_url('/mod/quest/assessments_autors.php',array('action'=>$action));
    if ($id!== -1)
    {
    	    $url->param('id', $id);
    }
    if ($qid!== -1){
    	    $url->param('qid', $qid);
    }

    $context = context_module::instance( $cm->id);
    $ismanager=has_capability('mod/quest:manage',$context);

    require_login($course->id, false, $cm);
    $PAGE->set_url($url);
    $PAGE->set_title(format_string($quest->name));
    $PAGE->set_context($context);
    $PAGE->set_heading($course->fullname);



 	quest_check_visibility($course,$cm);


    $strquests = get_string("modulenameplural", "quest");
    $strquest  = get_string("modulename", "quest");
    $strassessments = get_string("assessments", "quest");

    // ... print the header and...
  /*  print_header_simple(format_string($quest->name), "",
                 "<a href=\"index.php?id=$course->id\">$strquests</a> ->
                  <a href=\"view.php?id=$cm->id\">".format_string($quest->name,true)."</a> -> $strassessments",
                  "", "", true);

*/

    /*************** display grading form *********************************/
    if ($action == 'displaygradingform')
    {
    	echo $OUTPUT->header();
  		echo $OUTPUT->heading_with_help(get_string("specimenassessmentformsubmission", "quest"), "specimensubmission", "quest");

        quest_print_assessment_autor($quest);

        $id = $_GET['id'];
         // called with no assessment
        echo $OUTPUT->continue_button("view.php?id=$id");
    }

    /*********************** edit assessment elements (for teachers) ***********************/
    else if ($action == 'editelements') {

        if (!$ismanager) {
            print_error("Only teachers can look at this page",'quest');
        }
 		// set up heading, form and table
        echo $OUTPUT->header();

        $count = $DB->count_records("quest_items_assesments_autor", array("questid"=> $quest->id));
        if ($count) {
            notify(get_string("warningonamendingelements", "quest"));
        }

        $gradingstrategy=$quest->gradingstrategyautor==0?get_string('nograde','quest'):get_string('accumulative','quest');
        $heading = get_string("editingassessmentelementsofautors","quest").' ('.$gradingstrategy.')';
        echo $OUTPUT->heading_with_help($heading, "elementsautor", "quest");

        ?>
        <form name="form" method="post" action="assessments_autors.php">
        <input type="hidden" name="id" value="<?php echo $cm->id ?>" />
        <input type="hidden" name="action" value="insertelements" />
        <table align="center" border="1">
        <?php


        // get existing elements, if none set up appropriate default ones
        if ($elementsraw = $DB->get_records("quest_elementsautor", array("questid"=> $quest->id), "elementno ASC" )) {
            foreach ($elementsraw as $element) {
                $elements[] = $element;   // to renumber index 0,1,2...
            }
        }
        // check for missing elements (this happens either the first time round or when the number of elements is icreased)
        for ($i=0; $i<$quest->nelementsautor; $i++) {
            if (!isset($elements[$i])) {
            	$elements[$i] = new stdClass();
                $elements[$i]->description = '';
                $elements[$i]->scale =0;
                $elements[$i]->maxscore = 0;
                $elements[$i]->weight = 11;
            }
        }

        switch ($quest->gradingstrategyautor)
        {
            case 0: // no grading
                for ($i=0; $i<$quest->nelementsautor; $i++) {
                    $iplus1 = $i+1;
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><b>". get_string("element","quest")." $iplus1:</b></td>\n";
                    echo "<td><textarea name=\"description[]\" rows=\"3\" cols=\"75\">".$elements[$i]->description."</textarea>\n";
                    echo "  </td></tr>\n";
                    echo "<tr valign=\"top\">\n";
                    echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                    echo "</tr>\n";
                }
                break;

            case 1: // accumulative grading
                // set up scales name
                foreach ($QUEST_SCALES as $KEY => $SCALE) {
                    $SCALES[] = $SCALE['name'];
                }
                for ($i=0; $i<$quest->nelementsautor; $i++) {
                    $iplus1 = $i+1;
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><b>". get_string("element","quest")." $iplus1:</b></td>\n";
                    echo "<td><textarea name=\"description[]\" rows=\"3\" cols=\"75\">".$elements[$i]->description."</textarea>\n";
                    echo "  </td></tr>\n";
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><b>". get_string("typeofscale", "quest"). ":</b></td>\n";
                    echo "<td valign=\"top\">\n";
                    echo html_writer::select($SCALES, "scale[]", $elements[$i]->scale);
                    if ($elements[$i]->weight == '') { // not set
                        $elements[$i]->weight = 11; // unity
                    }
                    echo "</td></tr>\n";
                    echo "<tr valign=\"top\"><td align=\"right\"><b>".get_string("elementweight", "quest").":</b></td><td>\n";
                    quest_choose_from_menu($QUEST_EWEIGHTS, "weight[]", $elements[$i]->weight, "");
                    echo "      </td>\n";
                    echo "</tr>\n";
                    echo "<tr valign=\"top\">\n";
                    echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                    echo "</tr>\n";
                }
                break;

            case 2: // error banded grading
                for ($i=0; $i<$quest->nelementsautor; $i++) {
                    $iplus1 = $i+1;
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><b>". get_string("element","quest")." $iplus1:</b></td>\n";
                    echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">".$elements[$i]->description."</textarea>\n";
                    echo "  </td></tr>\n";
                    if ($elements[$i]->weight == '') { // not set
                        $elements[$i]->weight = 11; // unity
                        }
                    echo "</tr>\n";
                    echo "<tr valign=\"top\"><td align=\"right\"><b>".get_string("elementweight", "quest").":</b></td><td>\n";
                    quest_choose_from_menu($QUEST_EWEIGHTS, "weight[]", $elements[$i]->weight, "");
                    echo "      </td>\n";
                    echo "</tr>\n";
                    echo "<tr valign=\"top\">\n";
                    echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                    echo "</tr>\n";
                }
                echo "</center></table><br />\n";
                echo "<center><b>".get_string("gradetable","quest")."</b></center>\n";
                echo "<center><table cellpadding=\"5\" border=\"1\"><tr><td align=\"CENTER\">".
                    get_string("numberofnegativeresponses", "quest");
                echo "</td><td>". get_string("suggestedgrade", "quest")."</td></tr>\n";
                for ($j = $quest->maxcalification; $j >= 0; $j--) {
                    $numbers[$j] = $j;
                }
                for ($i=0; $i<=$quest->nelementsautor; $i++) {
                    echo "<tr><td align=\"CENTER\">$i</td><td align=\"CENTER\">";
                    if (!isset($elements[$i])) {  // the "last one" will be!
                        $elements[$i]->description = "";
                        $elements[$i]->maxscore = 0;
                    }
                    echo html_writer::select($numbers, "maxscore[$i]", $elements[$i]->maxscore, "");
                    echo "</td></tr>\n";
                }
                echo "</table></center>\n";
                break;

            case 3: // criterion grading
                for ($j = $quest->maxcalification; $j >= 0; $j--) {
                    $numbers[$j] = $j;
                }
                for ($i=0; $i<$quest->nelementsautor; $i++) {
                    $iplus1 = $i+1;
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><b>". get_string("criterion","quest")." $iplus1:</b></td>\n";
                    echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">".$elements[$i]->description."</textarea>\n";
                    echo "  </td></tr>\n";
                    echo "<tr><td><b>". get_string("suggestedgrade", "quest").":</b></td><td>\n";
                    echo html_writer::select($numbers, "maxscore[$i]", $elements[$i]->maxscore, "");
                    echo "</td></tr>\n";
                    echo "<tr valign=\"top\">\n";
                    echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                    echo "</tr>\n";
                }
                break;

            case 4: // rubric
                for ($j = $quest->maxcalification; $j >= 0; $j--) {
                    $numbers[$j] = $j;
                }
                if ($rubricsraw = $DB->get_records("quest_rubrics_autor", array("questid"=> $quest->id))) {
                    foreach ($rubricsraw as $rubric) {
                        $rubrics[$rubric->elementno][$rubric->rubricno] = $rubric->description;   // reindex 0,1,2...
                    }
                }
                for ($i=0; $i<$quest->nelementsautor; $i++) {
                    $iplus1 = $i+1;
                    echo "<tr valign=\"top\">\n";
                    echo "  <td align=\"right\"><b>". get_string("element","quest")." $iplus1:</b></td>\n";
                    echo "<td><textarea name=\"description[$i]\" rows=\"3\" cols=\"75\">".$elements[$i]->description."</textarea>\n";
                    echo "  </td></tr>\n";
                    echo "<tr valign=\"top\"><td align=\"right\"><b>".get_string("elementweight", "quest").":</b></td><td>\n";
                    quest_choose_from_menu($QUEST_EWEIGHTS, "weight[]", $elements[$i]->weight, "");
                    echo "      </td>\n";
                    echo "</tr>\n";

                    for ($j=0; $j<5; $j++) {
                        $jplus1 = $j+1;
                        if (empty($rubrics[$i][$j])) {
                            $rubrics[$i][$j] = "";
                        }
                        echo "<tr valign=\"top\">\n";
                        echo "  <td align=\"right\"><b>". get_string("grade","quest")." $j:</b></td>\n";
                        echo "<td><textarea name=\"rubric[$i][$j]\" rows=\"3\" cols=\"75\">".$rubrics[$i][$j]."</textarea>\n";
                        echo "  </td></tr>\n";
                        }
                    echo "<tr valign=\"top\">\n";
                    echo "  <td colspan=\"2\" class=\"questassessmentheading\">&nbsp;</td>\n";
                    echo "</tr>\n";
                    }
                break;
            }
        // close table and form

        ?>
        </table><br />
        <input type="submit" value="<?php  print_string("savechanges") ?>" />
        <input type="submit" name="cancel" value="<?php  print_string("cancel") ?>" />
        </form>
        <?php
    }

    /*********************** insert/update assignment elements (for teachers)***********************/
    else if ($action == 'insertelements') {

        if (!$ismanager) {
            error("Only teachers can look at this page");
        }

        $form = data_submitted();

        // let's not fool around here, dump the junk!
        $DB->delete_records("quest_elementsautor", array("questid"=> $quest->id));

        // determine wich type of grading
        switch ($quest->gradingstrategyautor) {
            case 0: // no grading
                // Insert all the elements that contain something
                foreach ($form->description as $key => $description) {
                    if ($description) {
                        unset($element);
                        $element = new stdClass();
                        $element->description   = $description;
                        $element->questid = $quest->id;
                        $element->elementno = $key;
                        if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                            error("Could not insert quest element!");
                        }
                    }
                }
                break;

            case 1: // accumulative grading
                // Insert all the elements that contain something
                foreach ($form->description as $key => $description) {
                    if ($description) {
                        $element = new stdClass();
                        $element->description   = $description;
                        $element->questid = $quest->id;
                        $element->elementno = $key;
                        if (isset($form->scale[$key])) {
                            $element->scale = $form->scale[$key];
                            switch ($QUEST_SCALES[$form->scale[$key]]['type']) {
                                case 'radio' :  $element->maxscore = $QUEST_SCALES[$form->scale[$key]]['size'] - 1;
                                                        break;
                                case 'selection' :  $element->maxscore = $QUEST_SCALES[$form->scale[$key]]['size'];
                                                        break;
                            }
                        }
                        if (isset($form->weight[$key])) {
                            $element->weight = $form->weight[$key];
                        }
                        if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                            error("Could not insert quest element!");
                        }
                    }
                }
                break;

            case 2: // error banded grading...
            case 3: // ...and criterion grading
                // Insert all the elements that contain something, the number of descriptions is one less than the number of grades
                foreach ($form->maxscore as $key => $themaxscore) {
                    unset($element);
                    $element->questid = $quest->id;
                    $element->elementno = $key;
                    $element->maxscore = $themaxscore;
                    if (isset($form->description[$key])) {
                        $element->description   = $form->description[$key];
                    }
                    if (isset($form->weight[$key])) {
                        $element->weight = $form->weight[$key];
                    }
                    if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                        error("Could not insert quest element!");
                    }
                }
                break;

            case 4: // ...and criteria grading
                // Insert all the elements that contain something
                foreach ($form->description as $key => $description) {
                    unset($element);
                    $element->questid = $quest->id;
                    $element->elementno = $key;
                    $element->description   = $description;
                    $element->weight = $form->weight[$key];
                    for ($j=0;$j<5;$j++) {
                        if (empty($form->rubric[$key][$j]))
                            break;
                    }
                    $element->maxscore = $j - 1;
                    if (!$element->id = $DB->insert_record("quest_elementsautor", $element)) {
                        error("Could not insert quest element!");
                    }
                }
                // let's not fool around here, dump the junk!
                $DB->delete_records("quest_rubrics_autor", "questid", $quest->id);
                for ($i=0;$i<$quest->nelementsautor;$i++) {
                    for ($j=0;$j<5;$j++) {
                        unset($element);
                        if (empty($form->rubric[$i][$j])) {  // OK to have an element with fewer than 5 items
                             break;
                         }
                        $element->questid = $quest->id;
                        $element->elementno = $i;
                        $element->rubricno = $j;
                        $element->description   = $form->rubric[$i][$j];
                        if (!$element->id = $DB->insert_record("quest_rubrics_autor", $element)) {
                            error("Could not insert quest element!");
                        }
                    }
                }
                break;
        } // end of switch

        redirect("view.php?id=$cm->id", get_string("savedok","quest"));
    }

    /*************** update assessment (by teacher or student) ***************************/
    else if ($action == 'updateassessment') {
        global $message;

        $aid=required_param('aid',PARAM_INT);
        if (! $assessment = $DB->get_record("quest_assessments_autors", array("id"=> $aid))) {
            print_error("quest assessment is misconfigured");
        }
        if (! $submission = $DB->get_record("quest_submissions", array("id"=> $assessment->submissionid)))
        {
            print_error("quest submission is misconfigured");
        }
        // first get the assignment elements for maxscores and weights...
        $elementsraw = $DB->get_records("quest_elementsautor", array("questid"=> $quest->id), "elementno ASC");
        if (count($elementsraw) < $quest->nelementsautor) {
            print_string("noteonassessmentelements", "quest");
        }
        if ($elementsraw) {
            foreach ($elementsraw as $element) {
                $elements[] = $element;   // to renumber index 0,1,2...
            }
        } else {
            $elements = null;
        }

        $timenow = time();


 /**
	Manual grading
 */
	 $manualGrade=optional_param('manualcalification',null,PARAM_ALPHANUM);
/* JPC: this section was not stateless
 * if($submission->nanswerscorrect == 0){
                $points = $submission->initialpoints;
               }
               else{
                $points = $submission->pointsanswercorrect;
               }
*/
	 $points=$submission->initialpoints;

	 if ($manualGrade !=null )
	 {

	 $percent=((int)$manualGrade)/100;

	 $grade = $points * $percent;
	 $message .=  "Grading manually! $points * $percent = $grade";
	 }
	 else
	 { // form grading
        // don't fiddle about, delete all the old and add the new!
        $DB->delete_records("quest_items_assesments_autor", array("assessmentautorid"=>  $assessment->id));

        $form = data_submitted('nomatch'); //Nomatch because we can come from assess.php
        $num_elements = $quest->nelementsautor;

        //determine what kind of grading we have
        switch ($quest->gradingstrategyautor) {
            case 0: // no grading
                // Insert all the elements that contain something
                for ($i = 0; $i < $num_elements; $i++) {
                    $element = new stdClass();
                    $element->questid = $quest->id;
                    $element->assessmentautorid = $assessment->id;
                    $element->elementno = $i;
                    $element->answer = $form->{"feedback_$i"};
                    $element->commentteacher =$form->generalcomment;
//                     print_object($form);die;
                    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                        print_error("Could not insert quest grade!",'quest');
                    }
                }
                $grade = $assessment->points; // set to satisfy save to db

                break;

            case 1: // accumulative grading
                // Insert all the elements that contain something
                foreach ($form->grade as $key => $thegrade) {
                    unset($element);
                    $element = new stdclass;
                    $element->questid = $quest->id;
                    $element->userid = $USER->id;
                    $element->assessmentautorid = $assessment->id;
                    $element->elementno = $key;
                    $element->answer   = $form->{"feedback_$key"};
                    $element->calification = $thegrade;
                    $element->commentteacher =$form->generalcomment; // EVP CHECK THIS... DATA BASE CONTAINS THIS FIELD BUT I do not find it in the form and I have included this to avoid error. I think this field is not used
                    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                        print_error("Could not insert quest grade!",'quest');
                        }
                    }
                // now work out the grade...
                $rawgrade=0;
                $totalweight=0;
                foreach ($form->grade as $key => $grade) {
                    $maxscore = $elements[$key]->maxscore;
                    $weight = $QUEST_EWEIGHTS[$elements[$key]->weight];
                    if ($weight > 0) {
                        $totalweight += $weight;
                    }
                    $rawgrade += ($grade / $maxscore) * $weight;

                }


                $grade = $points * ($rawgrade / $totalweight);

                break;

            case 2: // error banded graded
                // Insert all the elements that contain something
                $error = 0.0;
                for ($i =0; $i < $num_elements; $i++) {
                    unset($element);
                    $element->questid = $quest->id;
                    $element->assessmentid = $assessment->id;
                    $element->elementno = $i;
                    $element->answer   = $form->{"feedback_$i"};
                    $element->calification = $form->grade[$i];
                    if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                        error("Could not insert quest grade!");
                    }
                    if (empty($form->grade[$i])){
                        $error += $QUEST_EWEIGHTS[$elements[$i]->weight];
                    }
                }
                // now save the adjustment
                $element=new stdClass();
                $i = $num_elements;
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = $i;
                $element->calification = $form->grade[$i];
                if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                    error("Could not insert quest grade!");
                }
                $rawgrade = ($elements[intval($error + 0.5)]->maxscore + $form->grade[$i]);
                // do sanity check
                if ($rawgrade < 0) {
                    $rawgrade = 0;
                } else if ($rawgrade > $quest->maxcalification) {
                    $rawgrade = $quest->maxcalification;
                }
                echo "<b>".get_string("weightederrorcount", "quest", intval($error + 0.5))."</b>\n";


                $grade = $points * ($rawgrade / $quest->maxcalification);

                break;

            case 3: // criteria grading
                // save in the selected criteria value in element zero,
                unset($element);
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = 0;
                $element->calification = $form->grade[0];
                if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                    error("Could not insert quest grade!");
                }
                // now save the adjustment in element one
                unset($element);
                $element->questid = $quest->id;
                $element->assessmentid = $assessment->id;
                $element->elementno = 1;
                $element->calification = $form->grade[1];
                if (!$element->id = $DB->insert_record("quest_items_assesments_autor", $element)) {
                    error("Could not insert quest grade!");
                }
                $rawgrade = ($elements[$form->grade[0]]->maxscore + $form->grade[1]);


                $grade = $points * ($rawgrade / $quest->maxcalification);


                break;

            case 4: // rubric grading (identical to accumulative grading)
                // Insert all the elements that contain something
                foreach ($form->grade as $key => $thegrade) {
                    unset($element);
                    $element->questid = $quest->id;
                    $element->assessmentid = $assessment->id;
                    $element->elementno = $key;
                    $element->answer = $form->{"feedback_$key"};
                    $element->calification = $thegrade;
                    if (!$element->id = $DB->insert_record("quest_items_asesments_autor", $element)) {
                        error("Could not insert quest grade!");
                    }
                }
                // now work out the grade...
                $rawgrade=0;
                $totalweight=0;
                foreach ($form->grade as $key => $grade) {
                    $maxscore = $elements[$key]->maxscore;
                    $weight = $QUEST_EWEIGHTS[$elements[$key]->weight];
                    if ($weight > 0) {
                        $totalweight += $weight;
                    }
                    $rawgrade += ($grade / $maxscore) * $weight;
                }

                $grade = $points * ($rawgrade / $totalweight);

                break;

        } // end of switch
    }// form grading or manual grading


        $assessment->state = ASSESSMENT_STATE_BY_AUTOR;
        $assessment->points=$grade;
		$assessment->dateassessment = $timenow;

        $submission->evaluated = 1;


        // any comment?
        if (!empty($form->generalcomment)) {
//             $DB->set_field("quest_assessments_autors", "commentsteacher", $form->generalcomment, array("id"=> $assessment->id));
				$assessment->commentsteacher=$form->generalcomment;
        }

        if (!empty($form->generalteachercomment)) {
//             $DB->set_field("quest_assessments_autors", "commentsforteacher", $form->generalteachercomment, array("id"=> $assessment->id));
				$assessment->commentsforteacher=$form->generalteachercomment;
        }
        quest_update_submission($submission);// weird bug with number precission and decimal point in Moodle 2.5+
        quest_update_assessment_author($assessment);// weird bug with number precission and decimal point in Moodle 2.5+
        require_once('debugJP_lib.php');
        quest_update_submission_counts($submission->id);

        /////////////////////
        // recalculate points and report to gradebook
        //////////////////////
        quest_grade_updated($quest,$submission->userid);

        if($ismanager){
              if($user = get_complete_user_data('id', $submission->userid))
              {
                quest_send_message($user, "viewassessmentautor.php?aid=$aid", 'assessmentautor', $quest,  $submission);
              }
        }

        add_to_log($course->id, "quest", "assess_submissi",
                "viewassessmentautor.php?id=$cm->id&amp;aid=$assessment->id", "$assessment->id", "$cm->id");

        // set up return address
        if(!isset($form->returnto)){
            $returnto = "view.php?id=$cm->id";
        }else{
        	$returnto = $form->returnto;
        }

	echo $OUTPUT->header();
        // show grade if grading strategy is not zero
        if ($quest->gradingstrategyautor) {
	echo $message. get_string("thegradeis", "quest").": ".
                    number_format($grade, 4).
                    " (".get_string("initialpoints",'quest')." ".number_format($points,2).")";
	echo $OUTPUT->continue_button($returnto);
        }
        else {
            echo $message . get_string("thegradeis", "quest").": ".
                    number_format($grade, 4)."No grading.";
	echo $OUTPUT->continue_button($returnto);
        }
    }

    /*************** no man's land **************************************/
    else {
        print_error('unkownactionerror','quest',null,$action);
    }

   echo $OUTPUT->footer();
