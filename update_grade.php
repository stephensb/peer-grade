<?php
require_once "../../config.php";
require_once $CFG->dirroot."/pdo.php";
require_once $CFG->dirroot."/lib/lms_lib.php";
require_once $CFG->dirroot."/core/gradebook/lib.php";
require_once "peer_util.php";

// Sanity checks
$LTI = requireData(array('user_id', 'link_id', 'role','context_id'));
$instructor = isInstructor($LTI);
$p = $CFG->dbprefix;

// Check to see if we are updating the grade for the current 
// user or another
$user_id = $LTI['user_id'];
if ( isset($_REQUEST['user_id']) ) $user_id = $_REQUEST['user_id'];

// Model 
$row = loadAssignment($pdo, $LTI);
$assn_json = null;
$assn_id = false;
if ( $row !== false ) {
    $assn_json = json_decode($row['json']);
    $assn_id = $row['assn_id'];
}

if ( $assn_id == false ) {
    json_error('This assignment is not yet set up');
    return;
}

// Compute the user's grade
$grade = computeGrade($pdo, $assn_id, $assn_json, $user_id);
if ( $grade <= 0 ) {
    json_error('Nothing to grade for this user', $row);
    return;
}

// Lookup the result row if we are grading the non-current user
$result = false;
if ( $user_id != $LTI['user_id'] ) {
    $result = lookupResult($pdo, $LTI, $user_id);
}

// Send the grade
$debuglog = array();
$status = sendGradeDetail($grade, $debuglog, $pdo, $result); // This is the slow bit

if ( $status === true ) {
    if ( $user_id != $LTI['user_id'] ) {
        json_output(array("status" => $status, "debug" => $debuglog));
    } else { 
        json_output(array("status" => $status, "grade" => $grade, "debug" => $debuglog));
    }
} else { 
    json_error($status, $debuglog);
}

