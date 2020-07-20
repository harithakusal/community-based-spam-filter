<?php

define('PAGE_NAME', 'view_header');
define('SM_PATH','../');

require_once(SM_PATH . 'include/validate.php');
require_once(SM_PATH . 'functions/global.php');
require_once(SM_PATH . 'functions/imap.php');
require_once(SM_PATH . 'functions/html.php');
require_once(SM_PATH . 'functions/url_parser.php');

//$get_arr = '';

function parse_viewheader($imapConnection,$id, $passed_ent_id) {
    global $uid_support;

    $header_full = array();
    $header_output = array();
    $second = array();
    $first = array();

    if (!$passed_ent_id) {
        $read=sqimap_run_command ($imapConnection, "FETCH $id BODY[HEADER]", true, $a, $b, $uid_support);
    } else {
        $query = "FETCH $id BODY[".$passed_ent_id.'.HEADER]';
        $read=sqimap_run_command ($imapConnection, $query, true, $a, $b, $uid_support);
    }    
    $cnum = 0;

    for ($i=1; $i < count($read); $i++) {
        $line = htmlspecialchars($read[$i]);
        switch (true) {
            case (preg_match('/^&gt;/i', $line)):
                $second[$i] = $line;
                $first[$i] = '&nbsp;';
                $cnum++;
                break;
            case (preg_match('/^[ |\t]/', $line)):
                $second[$i] = $line;
                $first[$i] = '';
                break;
            case (preg_match('/^([^:]+):(.+)/', $line, $regs)):
                $first[$i] = $regs[1] . ':';
                $second[$i] = $regs[2];
                $cnum++;
                break;
            default:
                $second[$i] = trim($line);
                $first[$i] = '';
                break;
        }
    }

    for ($i=0; $i < count($second); $i = $j) {
        $f = (isset($first[$i]) ? $first[$i] : '');
        $s = (isset($second[$i]) ? nl2br($second[$i]) : ''); 
        $j = $i + 1;
        while (($first[$j] == '') && ($j < count($first))) {
            $s .= '&nbsp;&nbsp;&nbsp;&nbsp;' . nl2br($second[$j]);
            $j++;
        }
        $lowf=strtolower($f);
      
        if($lowf != 'message-id:' && $lowf != 'in-reply-to:' && $lowf != 'references:') {
            parseEmail($s);
		
        }
        if ($f) {
            $header_output[] = array($f,$s);
		
        }
    }
    
    
	$dbservername = "localhost";
	$dbusername = "root";
	$dbpassword = "123456";
	$dbname = "spam";
    
	$conn = mysqli_connect($dbservername, $dbusername, $dbpassword, $dbname);
	
	$rating_value = $_GET['rating'];
	$spammer = ($second[30]);
	$reporter = ($second[31]);
	$header_output[100] = $spammer;
	
	// Give marks to spammer
	$prev_spammer = $spammer;
	$spammer = ($second[30]);
	
	// Get final mark
	$get_final_mark = "select final_mark from final where spammer = '$spammer';";

	if ($get_final_mark_result = mysqli_query($conn, $get_final_mark)) { }	
	else { echo "Error: ".mysqli_error($conn)."<br>"; }

	if (mysqli_num_rows($get_final_mark_result) > 0) 
	{
		while($result = mysqli_fetch_assoc($get_final_mark_result)) 
		{
			$final_mark = $result['final_mark'];		
		}
	}

	if ($spammer == $prev_spammer) 
	{
		if ($final_mark <= 1) {
			$get_sp_mark = "select final_spammer_mark from final2 where spammer = '$second[30]';";

			if ($get_sp_mark_result = mysqli_query($conn, $get_sp_mark)) { }	
			else { echo "Error: ".mysqli_error($conn)."<br>"; }

			if (mysqli_num_rows($get_sp_mark_result) > 0) 
			{
				while($result = mysqli_fetch_assoc($get_sp_mark_result)) 
				{
					$spammer_mark = $result["final_spammer_mark"] + 1; 			
				}
			}
		}
	}

	
	// Send ratings, reporter, spammer
	if (!$conn) die("Connection failed: " . mysqli_connect_error()); 
	
	$insert_spam_details = "insert into spam_details(rating, reporter, spammer) 
				values('$rating_value', '$reporter', '$spammer');";

	if (mysqli_query($conn, $insert_spam_details)) { }	
	else { echo "Error: ".mysqli_error($conn)."<br>"; }
	
	// Get ratings, reporter, spammer
	$get_spam_details = "select * from spam_details;";

	if ($get_spam_details_result = mysqli_query($conn, $get_spam_details)) { }
	else { echo "Error: ".mysqli_error($conn)."<br>"; }

	if (mysqli_num_rows($get_spam_details_result) > 0) 
	{
		while($row = mysqli_fetch_assoc($get_spam_details_result)) 
		{
			$rating = $row["rating"]; 
			$spammer = $row["spammer"];
			
			// Process ratings
			if ($rating == "ham") { 
				$mark = +3; 
			}
			if ($rating == "good") { 
				$mark = +2; 
			}
			if ($rating == "maybe") {
				if ($mark >  5) { 
					$mark = +1; 
				} 
				if ($mark <= 5) { 
					$mark = -1; 
				} 
			}
			if ($rating == "bad" ) { 
				$mark = -2; 
			}
			if ($rating == "spam") { 
				$mark = -3;
			}
		}
	}
	
	// Send spammer, mark 
	$send_spammer = "insert into spammer values('$mark', '$spammer');";

	if (mysqli_query($conn, $send_spammer)) { }	
	else { echo "Error: ".mysqli_error($conn)."<br>"; }

	// Get mark, spammer mark
	$get_mark_total = "select sum(mark) as tot_mark from spammer where spammer = '$second[30]';";
	
	if ($get_mark_total_result = mysqli_query($conn, $get_mark_total)) { }
	else { echo "Error: ".mysqli_error($conn)."<br>"; }
	
	//get total mark
	if (mysqli_num_rows($get_mark_total_result) > 0) 
	{
		while($result = mysqli_fetch_assoc($get_mark_total_result)) 
		{
			$mark = $result['tot_mark'];
			$mark = $mark + 10; 			
		}
	}
	
	//send final mark, final spammer mark, spammer
	$delete_current_final = "delete from final where spammer = '$second[30]';";
	$insert_final = "insert into final values('$mark', '$spammer');";
	$delete_current_final2 = "delete from final2 where spammer = '$second[30]';";
	$insert_final2 = "insert into final2 values('$spammer_mark', '$second[30]');";

	if (mysqli_query($conn, $delete_current_final)) { }	
	else { echo "Error: ".mysqli_error($conn)."<br>"; }
	
	if (mysqli_query($conn, $insert_final)) { }	
	else { echo "Error: ".mysqli_error($conn)."<br>"; }
	
	if (mysqli_query($conn, $delete_current_final2)) { }	
	else { echo "Error: ".mysqli_error($conn)."<br>"; }
	
	if (mysqli_query($conn, $insert_final2)) { }	
	else { echo "Error: ".mysqli_error($conn)."<br>"; }
	    	
	mysqli_close($conn);
	//header("refresh:0.1; url=../src/right_main.php");
	
	return $header_output[100];
}


/* get global vars */
if ( sqgetGlobalVar('passed_id', $temp, SQ_GET) ) {
  $passed_id = (int) $temp;
}
if ( sqgetGlobalVar('mailbox', $temp, SQ_GET) ) {
  $mailbox = $temp;
}
if ( !sqgetGlobalVar('passed_ent_id', $passed_ent_id, SQ_GET) ) {
  $passed_ent_id = '';
} 

sqgetGlobalVar('key',        $key,          SQ_COOKIE);
sqgetGlobalVar('username',   $username,     SQ_SESSION);
sqgetGlobalVar('onetimepad', $onetimepad,   SQ_SESSION);
sqgetGlobalVar('delimiter',  $delimiter,    SQ_SESSION);

$imapConnection = sqimap_login($username, $key, $imapServerAddress, $imapPort, 0);
$mbx_response = sqimap_mailbox_select($imapConnection, $mailbox, false, false, true);
$header = parse_viewheader($imapConnection,$passed_id, $passed_ent_id);
//view_header($header, $mailbox, $color);
/* get global vars end */


$dbservername = "localhost";
$dbusername = "root";
$dbpassword = "123456";
$dbname = "spam";

$conn = mysqli_connect($dbservername, $dbusername, $dbpassword, $dbname);

$get_final_mark_result = '';
$result = '';
$final_mark2 = '';

// Get final mark
$get_final_mark = "select final_mark from final where spammer = '$header';";

if ($get_final_mark_result = mysqli_query($conn, $get_final_mark)) { }	
else { echo "Error: ".mysqli_error($conn)."<br>"; }

if (mysqli_num_rows($get_final_mark_result) > 0) 
{
	while($result = mysqli_fetch_assoc($get_final_mark_result)) 
	{
		$final_mark2 = $result['final_mark'];
		//echo "final mark: ". $final_mark2;		
	}
}

// move email to junk folder when final mark is less than or equal to 1(email becomes spam)
if ($final_mark2 <= 1) {

	//$targetMailbox = "Junk";
	
	// fixes the sort_array for the prev_del/next_del links when using server side sorting or thread sorting
	function fix_sort_array2 () {
	    global $username, $data_dir, $allow_server_sort, $allow_thread_sort,
	    $thread_sort_messages, 
	    $mailbox, $imapConnection, $sort, $uid_support, $mbx_response;

	    // Got to grab this out of prefs, since it isn't saved from mailbox_view.php
	    if ($allow_thread_sort) {
		$thread_sort_messages = getPref($data_dir, $username, "thread_$mailbox",0);
	    }

	    switch (true) {
	      case ($allow_thread_sort && $thread_sort_messages):
		  $server_sort_array = get_thread_sort($imapConnection);
		  break;
	      case ($allow_server_sort):
		  $server_sort_array = sqimap_get_sort_order($imapConnection, $sort, $mbx_response);
		  break;
	      case ($uid_support):
		  $server_sort_array = sqimap_get_php_sort_order($imapConnection, $mbx_response);
		  break;
	      default:
		  break;
	    }

	}

	function delete_move_del_arr_elem2($arr, $index) {
	    $tmp = array();
	    $j = 0;
	    foreach ($arr as $v) {
		if ($j != $index) {
		   $tmp[] = $v;
		 }
		 $j++;
	    }
	    return $tmp;
	}

	function delete_move_show_msg_array2() {
	    global $msort, $msgs;
	    $keys = array_keys($msort);
	    for ($i = 0; $i < count($keys); $i++) {
		echo '<p>key ' . $keys[$i] . ' msgid ' . $msgs[$keys[$i]]['ID'] . '</p>';
	    }
	}
	// ******** removes from all 
	function delete_move_expunge_from_all2($id) {
	    global $msgs, $msort, $sort, $imapConnection, $mailbox, $uid_support;
	    $delAt = -1;

	    if(isset($msort) && count($msort) > 0) {
		for ($i = 0; $i < count($msort); $i++) {
		    if ($msgs[$i]['ID'] == $id) {
			$delAt = $i;
		    } elseif ($msgs[$i]['ID'] > $id) {
			if (!$uid_support) {
			   $msgs[$i]['ID']--;
			}
		    }
		}

		$msgs = delete_move_del_arr_elem2($msgs, $delAt);
		$msort = delete_move_del_arr_elem2($msort, $delAt);
		if ($sort < 6) {
		    if ($sort % 2) {
			asort($msort);
		    } else {
			arsort($msort);
		    }
		}
		sqsession_register($msgs, 'msgs');
		sqsession_register($msort, 'msort');
	    }

	    sqimap_mailbox_expunge($imapConnection, $mailbox, true);	
	}
	
	// ********** move action
	function delete_move_next_action2() {

	    if ( sqgetGlobalVar('unread_id', $unread_id, SQ_GET) ) {
		delete_move_next_unread2();
	    } else if ( sqgetGlobalVar('delete_id', $delete_id, SQ_GET) ) {
		delete_move_next_delete2();
		fix_sort_array2();
	    } else if ( sqgetGlobalVar('move_id', $move_id, SQ_POST) ) {
		delete_move_next_move2();
		fix_sort_array2();
	    }

	}

	function delete_move_next_read_t2() {

	    global $delete_move_next_t;

	    if($delete_move_next_t == 'on') {
		delete_move_next_read2('top');
	    }
	}

	function delete_move_next_read_b2() {

	    global $delete_move_next_b;

	    if ($delete_move_next_b != 'off') {
		delete_move_next_read2('bottom');
	    }

	}

	function delete_move_next_read2($currloc) {
	    global $delete_move_next_formATtop, $delete_move_next_formATbottom,
		   $color, $where, $what, $currentArrayIndex, $passed_id,
		   $mailbox, $sort, $startMessage, $delete_id, $move_id,
		   $imapConnection, $auto_expunge, $move_to_trash, $mbx_response,
		   $uid_support, $passed_ent_id, $delete_move_next_show_unread;

	    $urlMailbox = urlencode($mailbox);

	    if (!isset($passed_ent_id)) $passed_ent_id = 0;

	    if (!(($where && $what) || ($currentArrayIndex == -1)) && !$passed_ent_id) {
		$next = findNextMessage($passed_id);
		$prev = findPreviousMessage($mbx_response['EXISTS'], $passed_id);
		$prev_if_del = $prev;
		$next_if_del = $next;
		if (!$uid_support && ($auto_expunge || $move_to_trash)) {
		    if ($prev_if_del > $passed_id) {
			$prev_if_del--;
		    }
		    if ($next_if_del > $passed_id) {
			$next_if_del--;
		    }
		}

		if ($next_if_del < 0) {
		    $next_if_del = $prev_if_del;
		}
		if (($delete_move_next_formATtop == 'on') && ($currloc == 'top')) {
		    if ($next_if_del > 0) {
			delete_move_next_moveNextForm($next_if_del);
		    } else {
			delete_move_next_moveRightMainForm();
		    }
		}
		if (($delete_move_next_formATbottom != 'off') && ($currloc == 'bottom')) {
		    if ($next_if_del > 0) {
			delete_move_next_moveNextForm($next_if_del);
		    } else {
			delete_move_next_moveRightMainForm();
		    }
		}
		echo '</table>';
	    }

	}
	// ******* get drop down folder list
	function get_move_target_list() {
	    global $imapConnection, $lastTargetMailbox;
	    if (isset($lastTargetMailbox) && !empty($lastTargetMailbox)) {
		echo sqimap_mailbox_option_list($imapConnection, array(strtolower($lastTargetMailbox)));
	    }
	    else {
		echo sqimap_mailbox_option_list($imapConnection);
	    }
	}

	// main form *******************
	function delete_move_next_moveNextForm($next) {

	    global $color, $where, $what, $currentArrayIndex, $passed_id,
		   $mailbox, $sort, $startMessage, $delete_id, $move_id,
		   $imapConnection;

	    $urlMailbox = urlencode($mailbox);

	    echo '<tr>'.
		 "<td bgcolor=\"$color[9]\" width=\"100%\" align=\"center\">".
		   "<form action=\"read_body.php?mailbox=$urlMailbox&amp;sort=$sort&amp;startMessage=$startMessage&amp;passed_id=$next\"
		   method=\"post\"><small>".
		    "<input type=\"hidden\" name=\"show_more\" value=\"0\">".
		    "<input type=\"hidden\" name=\"move_id\" value=\"$passed_id\">".
		    "<input type=\"hidden\" name=\"smtoken\" value=\"" . sm_generate_security_token() . "\">".
		    '<b><font size=3 color="red">'._("Move to:") . '<font></b>'.
		    ' <select name="targetMailbox">';
	    get_move_target_list(); 
	    echo    '</select> '.
		    '<input type="submit" value="' . _("Move") . '">'.
		    '</small>'.
		   '</form>'.
		 '</td>'.
		 '</tr>';
	}
	//****************
	function delete_move_next_moveRightMainForm() {

	    global $color, $where, $what, $currentArrayIndex, $passed_id,
		   $mailbox, $sort, $startMessage, $delete_id, $move_id,
		   $imapConnection;

	    $urlMailbox = urlencode($mailbox);

	    echo '<tr>' .
		    "<td bgcolor=\"$color[9]\" width=\"100%\" align=\"center\">".
		    "<form action=\"right_main.php?mailbox=$urlMailbox&amp;sort=$sort&amp;startMessage=$startMessage\" method=\"post\"><small>" .
		    "<input type=\"hidden\" name=\"move_id\" value=\"$passed_id\">".
		    "<input type=\"hidden\" name=\"smtoken\" value=\"" . sm_generate_security_token() . "\">".
		    '<b><font size=3 color="red">'._("Move to:") . '<font></b>' .
		    ' <select name="targetMailbox">';
	    get_move_target_list(); 
	    echo    ' </select>' .
		    '<input type=submit value="' . _("Move") . '">'.
		    '</small>'.
		 '</form>' .
		 '</td>'.
		 '</tr>';
	}

	function delete_move_next_unread2() {
	    global $imapConnection;

	    sqgetGlobalVar('unread_id', $unread_id, SQ_GET);
	    if (!sqgetGlobalVar('smtoken',$submitted_token, SQ_GET)) {
		$submitted_token = '';
	    }

	    // first, validate security token
	    sm_validate_security_token($submitted_token, 3600, TRUE);

	    sqimap_toggle_flag($imapConnection, $unread_id, '\\Seen', false, true);

	}

	// *********** delete mail
	function delete_move_next_delete2() {
	    global $imapConnection, $auto_expunge;

	    sqgetGlobalVar('delete_id', $delete_id, SQ_GET);
	    sqgetGlobalVar('mailbox', $mailbox, SQ_GET);
	    if (!sqgetGlobalVar('smtoken',$submitted_token, SQ_GET)) {
		$submitted_token = '';
	    }

	    // first, validate security token
	    sm_validate_security_token($submitted_token, 3600, TRUE);

	    sqimap_msgs_list_delete($imapConnection, $mailbox, $delete_id);
	    if ($auto_expunge) {
		delete_move_expunge_from_all2($delete_id);
		// sqimap_mailbox_expunge($imapConnection, $mailbox, true);
	    }

	}

	// ********* move mail
	function delete_move_next_move2() {
	    global $imapConnection, $mailbox, $auto_expunge, $lastTargetMailbox;

	    sqgetGlobalVar('move_id', $move_id, SQ_POST);
	    sqgetGlobalVar('mailbox', $mailbox, SQ_FORM);
	    sqgetGlobalVar('targetMailbox', $targetMailbox, SQ_POST);
	    if (!sqgetGlobalVar('smtoken',$submitted_token, SQ_POST)) {
		$submitted_token = '';
	    }
		//echo $move_id.'<br>'.$mailbox.'<br>'.$targetMailbox;
	    // first, validate security token
	    sm_validate_security_token($submitted_token, 3600, TRUE);

	    // Move message
	    sqimap_msgs_list_move($imapConnection, $move_id, $targetMailbox);
	    if ($auto_expunge) {
		delete_move_expunge_from_all2($move_id);
		// sqimap_mailbox_expunge($imapConnection, $mailbox, true);
	    }

	    if ($targetMailbox != $lastTargetMailbox) {
		$lastTargetMailbox = $targetMailbox;
		sqsession_register($lastTargetMailbox, 'lastTargetMailbox');

	    }
	}

}



//***********************************************
// Check spammer's mark and block the spammer
$get_sp_mark = "select final_spammer_mark from final2 where spammer = '$header';";

if ($get_sp_mark_result = mysqli_query($conn, $get_sp_mark)) { }	
else { echo "Error: ".mysqli_error($conn)."<br>"; }

if (mysqli_num_rows($get_sp_mark_result) > 0) 
{
	while($result = mysqli_fetch_assoc($get_sp_mark_result)) 
	{
		$spammer_mark = $result["final_spammer_mark"]; 			
	}
}






















mysqli_close($conn);

sqimap_logout($imapConnection);

?>
