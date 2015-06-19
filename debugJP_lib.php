<?php
require_once("../../config.php");
require_once("locallib.php");
//TODO use $quest record

function updateallteams($questid)
{//test_update_team();
global $DB;
$query= $DB->get_records_select("quest_teams","questid=?",array($questid));
$quest = $DB->get_record('quest',array('id'=>$questid));
$idteams = array();
foreach($query as $team)
{
$idteams[]=$team->id;
print("<p>Updating team $team->id on quest $questid: </p>  ");
quest_update_team_scores($quest->id,$team->id);
//test_update_team($questid, $team->id,false);
}
// Clean orphan records
if (!empty($idteams))
{
$select = 'teamid not in ('.join(',', $idteams).') and questid='.$questid;
}
else
{
    $select = 'questid='.$questid;
}
print("<p>Cleaning orphan calification_teams records.");
$DB->delete_records_select('quest_calification_teams', $select);

}
//TODO use $quest record
function updateallusers($questid)
{
global $DB;
$query= $DB->get_records_select("quest_calification_users","questid=?",array($questid));
$quest = $DB->get_record('quest',array('id'=>$questid));

foreach($query as $usercal)
{
print("<p>Updating user $usercal->userid on quest $questid: </p>  ");
quest_update_user_scores($quest,$usercal->userid);
//test_update_team($questid, $team->id,false);
}
}
function test_update_team($questid,$teamid,$update=true)
{
global $DB;
$query = $DB->get_records_select("quest_calification_users","teamid=?",array($teamid));
	foreach($query as $q)$members[]=$q->userid;
	$member_list=implode(',',$members);

//print_object($member_list);

	$pointsanswers=quest_calculate_user_score($questid,$members);

	$nanswers = quest_count_user_answers($questid,$members);
//print("<p>nanswers: $nanswers");
	$nanswersassessed = quest_count_user_answers_assesed($questid,$members);
//print("<p>nassessed: $nanswersassessed");		
	$submissionpoints= quest_calculate_user_submissions_score($questid,$members);
//print("usersubmisiion score .$submissionpoints");
	$nsubmissions = quest_count_user_submissions($questid,$members);
//print("<p>Team $teamid formed by $member_list has $nsubmissions submissions");
	$nsubmissionassessed = quest_count_user_submissions_assesed($questid,$members);
//print("<p>nsub_assessed: $nsubmissionassessed");			
	$points=$submissionpoints + $pointsanswers;
if ($update)
{	
print("...actualizando... equipo $teamid en quest $questid");
$calification_teams = $DB->get_record('quest_calification_teams', array("questid"=>$questid,"teamid"=>$teamid));
	$calification_teams->nanswers=$nanswers;
	$calification_teams->nanswerassessment=$nanswersassessed;
	$calification_teams->points="$points";
	$calification_teams->pointsanswers="$pointsanswers";
	$calification_teams->pointssubmission="$submissionpoints";
	$calification_teams->nsubmissionsassessment=$nsubmissionassessed;
	$calification_teams->nsubmissions=$nsubmissions;
	$DB->update_record('quest_calification_teams', $calification_teams);
}
}
function test1()
{

$submissionid=370;
//update submission
//////////////////////////////////////////////////777       
//get first answer correct
//update pointsanswercorrect
$submission= new stdClass();
$submission->id=$submissionid;

$submission = quest_calculate_pointsanswercorrect_and_date($submission);
print_object($submission);
}
/**
 * 
 * @param stdClass $submission
 * @return stdClass Submission updated
 */
function quest_calculate_pointsanswercorrect_and_date($submission)
{
global $DB;

	$query  = $DB->get_records_select("quest_answers","submissionid=? and grade>=50",array($submission->id),"date, pointsmax");

	if (count($query)>0)
	{
	$query=reset($query); // get first record
    	$submission->dateanswercorrect=$query->date;
	$submission->pointsanswercorrect=$query->pointsmax;
	}
	else
	{
	$submission->dateanswercorrect=0;
	$submission->pointsanswercorrect=0;
	}
	return $submission;
}
/**
 * Counts and update record for quest_submissions
 * @param unknown $sid
 * @return unknown
 */
function quest_update_submission_counts($sid)
{
global $DB, $message;
$submission= $DB->get_record("quest_submissions",array('id'=>$sid),'*',MUST_EXIST);
$na=quest_count_submission_answers($sid);
$naa=quest_count_submission_answers_assesed($sid);
$nac=quest_count_submission_answers_correct($sid);

$message .= "<p>Submission $sid has $na answers ($naa assessed) ($nac correct)</p>";

$submission->nanswers = $na;
$submission->nanswersassessed = $naa;
$submission->nanswerscorrect = $nac;
$submission = quest_calculate_pointsanswercorrect_and_date($submission);
$DB->update_record('quest_submissions', $submission);
return $submission;
}
/**
 * @param $sid submission id
 */
function quest_count_submission_answers($sid)
{
	global $DB;
if($query = $DB->get_record_select("quest_answers","submissionid=?",array($sid),"count(*) as num")) //evp no estoy segura de que funciones
	return	$query->num;
	else
	return 0;
}
function quest_count_submission_answers_assesed($sid)
{
	global $DB;
if($query = $DB->get_record_select("quest_answers","submissionid=? and phase>0",array($sid),"count(*) as num")) //evp no estoy segura de que funcio
	return	$query->num;
	else
	return 0;
}
function quest_count_submission_answers_correct($sid)
{
	global $DB;
	if($query = $DB->get_record_select("quest_answers","submissionid=? and grade>=50",array($sid),"count(grade) as num"))//evp no s� si va a funcionar esto
		return $query->num;
	else
		return 0;
}
function quest_recalculate_all_submissions_stats()
{
global $CFG;
$sql="update mdl_quest_submissions set nanswers=".
	"(select count(*) from mdl_quest_answers as ans where ans.submissionid=mdl_quest_submissions.id )";
$sql2="update mdl_quest_submissions set nanswerscorrect=".
	"(select count(*) from mdl_quest_answers as ans where ans.submissionid=mdl_quest_submissions.id and ans.grade>50 )";
$sql3="update mdl_quest_submissions set nanswersassessed=".
	"(select count(*) from mdl_quest_answers as ans where ans.submissionid=mdl_quest_submissions.id and ans.phase>0 )";
$sql4="update mdl_quest_submissions set dateanswercorrect=(select min(date) from mdl_quest_answers as ans where ans.submissionid=mdl_quest_submissions.id and ans.grade>=50)";

//echo str_replace("mdl_",$CFG->prefix, $sql);
echo "Recalculating nanwers, nanswerscorrect, dateanswercorrect";

 execute_sql(str_replace("mdl_",$CFG->prefix, $sql));
 execute_sql(str_replace("mdl_",$CFG->prefix, $sql2));
 execute_sql(str_replace("mdl_",$CFG->prefix, $sql3));
 execute_sql(str_replace("mdl_",$CFG->prefix, $sql4));
}
/*
$action=optional_param('action','none',PARAM_ALPHA);
echo "<p>Action is $action.</p>";
if ($action=="recalculatesubmissions")
{
echo "<p>Executing: Recalculate submissions stats</p>";
quest_recalculate_all_submissions_stats();
}

echo "<p>Action done</p>";
*/
?>
