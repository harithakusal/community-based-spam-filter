<?php

// error display
//error_reporting(E_ALL);
//ini_set('display_errors',1);

// Initialize the plugin
function squirrelmail_plugin_init_spam_rater() {
   global $squirrelmail_plugin_hooks;

   $squirrelmail_plugin_hooks['read_body_header_right']['spam_rater'] = 'spam_rater_show_link';
   $squirrelmail_plugin_hooks['html_top']['delete_move_next'] = 'delete_move_next_action';
   $squirrelmail_plugin_hooks['right_main_after_header']['delete_move_next'] = 'delete_move_next_action';
   $squirrelmail_plugin_hooks['read_body_bottom']['delete_move_next'] = 'delete_move_next_read_b';
}

// Show the link to ratings
function spam_rater_show_link() {

   // GLOBALS
   require_once(SM_PATH . 'functions/global.php');
 
   sqgetGlobalVar('passed_id',    $passed_id,    SQ_FORM);
   sqgetGlobalVar('passed_ent_id',$passed_ent_id,SQ_FORM);
   sqgetGlobalVar('mailbox',      $mailbox,      SQ_FORM);
   if ( sqgetGlobalVar('startMessage', $startMessage, SQ_FORM) ) {
       $startMessage = (int)$startMessage;
   }
   // END GLOBALS

   // catch unset passed_ent_id
   if (! sqgetGlobalVar('passed_ent_id', $passed_ent_id, SQ_FORM) ) {
    $passed_ent_id = 0;
   }
?>

<html>
  <head>
    <meta charset="UTF-8">
	<script language="javascript" type="text/javascript">
	function triggerButton() {	
		alert("Thanks for your rating!");
	}
</script>
  </head>
  <body>
	<br>
      <font size="3" font color="red">Rate email: </font>

	<a href="../src/view_header.php?passed_id=<?php echo urlencode($passed_id); ?>&amp;rating=ham&amp;mailbox=<?php echo urlencode($mailbox); ?>&amp;passed_ent_id=<?php echo urlencode($passed_ent_id); ?>" onclick="triggerButton()">1 - Not spam</a>&nbsp;&nbsp;
	
	<a href="../src/view_header.php?passed_id=<?php echo urlencode($passed_id); ?>&amp;rating=good&amp;mailbox=<?php echo urlencode($mailbox); ?>&amp;passed_ent_id=<?php echo urlencode($passed_ent_id); ?>" onclick="triggerButton()">2 - Good</a>&nbsp;&nbsp;

	<a href="../src/view_header.php?passed_id=<?php echo urlencode($passed_id); ?>&amp;rating=maybe&amp;mailbox=<?php echo urlencode($mailbox); ?>&amp;passed_ent_id=<?php echo urlencode($passed_ent_id); ?>" onclick="triggerButton()">3 - May be</a>&nbsp;&nbsp;

	<a href="../src/view_header.php?passed_id=<?php echo urlencode($passed_id); ?>&amp;rating=bad&amp;mailbox=<?php echo urlencode($mailbox); ?>&amp;passed_ent_id=<?php echo urlencode($passed_ent_id); ?>" onclick="triggerButton()">4 - Bad</a>&nbsp;&nbsp;

	<a href="../src/view_header.php?passed_id=<?php echo urlencode($passed_id); ?>&amp;rating=spam&amp;mailbox=<?php echo urlencode($mailbox); ?>&amp;passed_ent_id=<?php echo urlencode($passed_ent_id); ?>" onclick="triggerButton()">5 - Spam</a>

  </body>
</html>

<?php
}

// fixes the sort_array for the prev_del/next_del links when using server side sorting or thread sorting
function fix_sort_array () {
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

function delete_move_del_arr_elem($arr, $index) {
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

function delete_move_show_msg_array() {
    global $msort, $msgs;
    $keys = array_keys($msort);
    for ($i = 0; $i < count($keys); $i++) {
        echo '<p>key ' . $keys[$i] . ' msgid ' . $msgs[$keys[$i]]['ID'] . '</p>';
    }
}
// ******** removes from all 
function delete_move_expunge_from_all($id) {
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

        $msgs = delete_move_del_arr_elem($msgs, $delAt);
        $msort = delete_move_del_arr_elem($msort, $delAt);
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
function delete_move_next_action() {

    if ( sqgetGlobalVar('unread_id', $unread_id, SQ_GET) ) {
        delete_move_next_unread();
    } else if ( sqgetGlobalVar('delete_id', $delete_id, SQ_GET) ) {
        delete_move_next_delete();
        fix_sort_array();
    } else if ( sqgetGlobalVar('move_id', $move_id, SQ_POST) ) {
        delete_move_next_move();
        fix_sort_array();
    }
}

function delete_move_next_read_t() {

    global $delete_move_next_t;

    if($delete_move_next_t == 'on') {
        delete_move_next_read('top');
    }
}

function delete_move_next_read_b() {

    global $delete_move_next_b;

    if ($delete_move_next_b != 'off') {
        delete_move_next_read('bottom');
    }
}

function delete_move_next_read($currloc) {
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
           "<form action=\"read_body.php?mailbox=$urlMailbox&amp;sort=$sort&amp;startMessage=$startMessage&amp;passed_id=$next\" method=\"post\"><small>".
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

function delete_move_next_unread() {
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
function delete_move_next_delete() {
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
        delete_move_expunge_from_all($delete_id);
        // sqimap_mailbox_expunge($imapConnection, $mailbox, true);
    }
}

// ********* move mail
function delete_move_next_move() {
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
        delete_move_expunge_from_all($move_id);
        // sqimap_mailbox_expunge($imapConnection, $mailbox, true);
    }

    if ($targetMailbox != $lastTargetMailbox) {
        $lastTargetMailbox = $targetMailbox;
        sqsession_register($lastTargetMailbox, 'lastTargetMailbox');
    }
}





























?>
