<?php

global $mybb;

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";

if ($mybb->settings['transferthread_enable'] == 1)
{
    $plugins->add_hook('showthread_start', 'transfer_thread_setup_variables');
    $plugins->add_hook('moderation_start', 'transfer_thread_action');
}

global $transferthread;

function transferthread_info()
{
    $codename = str_replace('.php', '', basename(__FILE__));
    return array(
        "name"          => "Transfer Thread",
        "description"   => "Adds a new moderation option through which a thread ownership can be transferred to another user.",
        "website"       => "https://github.com/zegkljan/MyBB-Transfer-Thread",
        "author"        => "Jan Žegklitz",
        "authorsite"    => "https://zegkljan.net/",
        "version"       => "0.1",
        "guid"          => "",
        "codename"      => $codename,
        "compatibility" => "18*"
    );
}

function transferthread_install()
{
    global $db, $lang;

    $lang->load('transferthread');

    $setting_group = array(
        'name' => 'transferthread-settinggroup',
        'title' => 'Transfer Thread Settings',
        'description' => 'Transfer Thread settings description TODO.',
        'disporder' => 1, // The order your setting group will display
        'isdefault' => 0
    );

    $gid = $db->insert_query("settinggroups", $setting_group);

    $setting_array = array(
        /*
        // A text setting
        'fav_colour' => array(
            'title' => 'Favorite Colour',
            'description' => 'Enter your favorite colour:',
            'optionscode' => 'text',
            'value' => 'Blue', // Default
            'disporder' => 1
        ),
        // A select box
        'green_good' => array(
            'title' => 'Is green good?',
            'description' => 'Select your opinion on whether green is good:',
            'optionscode' => "select\n0=Yes\n1=Maybe\n2=No",
            'value' => 2,
            'disporder' => 2
        ),
        */
        // A yes/no boolean box
        'transferthread_enable' => array(
            'title' => $lang->transferthread_enable_title,
            'description' => $lang->transferthread_enable_description,
            'optionscode' => 'yesno',
            'value' => 1,
            'disporder' => 1
        ),
    );

    foreach ($setting_array as $name => $setting)
    {
        $setting['name'] = $name;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
    }

    // Don't forget this!
    rebuild_settings();
}

function transferthread_is_installed()
{
    global $mybb;
    if (isset($mybb->settings['transferthread_enable']))
    {
        return true;
    }

    return false;
}

function transferthread_uninstall()
{
    global $db, $mybb;

    $db->delete_query('settings', "name IN ('transferthread_enable')");
    $db->delete_query('settinggroups', "name = 'transferthread-settinggroup'");

    // Don't forget this
    rebuild_settings();
}

function transferthread_activate()
{
    global $db;

    // create new template for transfer thread moderation option
    $template = '<option value="transferthread">{$lang->transferthread_action_name}</option>';

    $insert_array = array(
        'title' => 'showthread_moderationoptions_transferthread',
        'template' => $db->escape_string($template),
        'sid' => '-1',
        'version' => '',
        'dateline' => time()
    );

    $db->insert_query('templates', $insert_array);

    // add this option to the standard moderation tools
    find_replace_templatesets(
        "showthread_moderationoptions_standard",
        '#' . preg_quote('{$approveunapprovethread}') . '#i',
        '{$approveunapprovethread}' . "\n" . '{$transferthread}'
    );

    // create new template for transfer thread moderation
    $template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->transferthread_action_name}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="moderation.php" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->transferthread_action_name}</strong></td>
</tr>
{$loginbox}
<tr>
<td class="trow1"><strong>{$lang->transferthread_new_user}</strong></td>
<td class="trow2"><input type="text" id="username" class="textbox" name="username" size="40" maxlength="{$mybb->settings[\'maxnamelength\']}" style="width: 210px" /></td>
</tr>
</table>
<br />
<div align="center"><input type="submit" class="button" name="submit" value="{$lang->transferthread_action_name}" /></div>
<input type="hidden" name="action" value="do_transferthread" />
<input type="hidden" name="tid" value="{$tid}" />
</form>
{$footer}
<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css?ver=1807">
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js?ver=1806"></script>
<script type="text/javascript">
<!--
if(use_xmlhttprequest == "1")
{
	MyBB.select2();
	$("#username").select2({
		placeholder: "{$lang->search_user}",
		minimumInputLength: 2,
		multiple: false,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term, // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var value = $(element).val();
			if (value !== "") {
				callback({
					id: value,
					text: value
				});
			}
		},
	});
}
// -->
</script>
</body>
</html>';
    $insert_array = array(
        'title' => 'moderation_transferthread',
        'template' => $db->escape_string($template),
        'sid' => '-1',
        'version' => '',
        'dateline' => time()
    );
    
    $db->insert_query('templates', $insert_array);
}

function transferthread_deactivate()
{
    global $db, $mybb;

    // add this option to the standard moderation tools
    find_replace_templatesets(
        "showthread_moderationoptions_standard",
        '#' . preg_quote('{$approveunapprovethread}' . "\n" . '{$transferthread}') . '#i',
        '{$approveunapprovethread}'
    );
    
    $db->delete_query("templates", "title = 'showthread_moderationoptions_transferthread'");
    $db->delete_query("templates", "title = 'moderation_transferthread'");
}

function transfer_thread_setup_variables()
{
    global $templates, $transferthread, $lang;

    $lang->load('transferthread');

    //if (is_moderator($forum['fid'], "canmanagethreads"))
    //{
            eval("\$transferthread = \"".$templates->get("showthread_moderationoptions_transferthread")."\";");
    //}
    //$transferthread = $templates->get("showthread_moderationoptions_transferthread");
}

function transfer_thread_action()
{
    global $mybb, $lang, $templates, $theme, $db;
    global $headerinclude;
    global $header;
    global $footer;
    
    require_once "./global.php";
    require_once MYBB_ROOT."inc/functions_post.php";
    require_once MYBB_ROOT."inc/functions_upload.php";
    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser;
    require_once MYBB_ROOT."inc/class_moderation.php";
    
    $lang->load("moderation");
    $lang->load('transferthread');

    $tid = $mybb->get_input('tid', MyBB::INPUT_INT);
    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);
    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
    $pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);

    if($pid)
    {
        $post = get_post($pid);
        if(!$post)
        {
            error($lang->error_invalidpost);
        }
        $tid = $post['tid'];
    }

    if($tid)
    {
        $thread = get_thread($tid);
        if(!$thread)
        {
            error($lang->error_invalidthread);
        }
        $fid = $thread['fid'];
    }

    if($fid)
    {
        $modlogdata['fid'] = $fid;
        $forum = get_forum($fid);

        // Make navigation
        build_forum_breadcrumb($fid);

        // Get our permissions all nice and setup
        $permissions = forum_permissions($fid);
    }
    
    if ($mybb->get_input('action') == 'transferthread')
    {
        if(isset($thread))
        {
            $thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
            add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
            $modlogdata['tid'] = $thread['tid'];
        }

        if(isset($forum))
        {
            // Check if this forum is password protected and we have a valid password
            check_forum_password($forum['fid']);
        }

        $mybb->user['username'] = htmlspecialchars_uni($mybb->user['username']);
        eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");

        $allowable_moderation_actions = array("getip", "getpmip", "cancel_delayedmoderation", "delayedmoderation", "threadnotes", "purgespammer", "viewthreadnotes");

        if($mybb->request_method != "post" && !in_array($mybb->input['action'], $allowable_moderation_actions))
        {
            error_no_permission();
        }
        
        add_breadcrumb($lang->transferthread_action_name);
        if(!is_moderator($fid, "canmanagethreads"))
        {
                error_no_permission();
        }
        
        // Load the auto complete javascript if it is enabled.
	    eval("\$autocompletejs = \"".$templates->get("private_send_autocomplete")."\";");
        eval('$page = "'.$templates->get('moderation_transferthread').'";');
        output_page($page);
        exit;
    }
    elseif ($mybb->get_input('action') == 'do_transferthread')
    {
        // Verify incoming POST request
        verify_post_check($mybb->get_input('my_post_key'));

        $user = get_user_by_username($mybb->get_input('username'), array("fields" => array('username')));

        if($user['uid'])
        {
            $query = $db->simple_select("threads", "firstpost", "tid = '{$tid}'");
            $thread = $db->fetch_array($query);
            
            $newuser = array(
                "uid" => $user['uid'],
                "username" => $user['username']
            );
            $db->update_query("threads", $newuser, "tid = '{$tid}'");
            $db->update_query("posts", $newuser, "pid = '{$thread['firstpost']}'");
            redirect("showthread.php?tid=".$tid, "message: " . $lang->transferthread_owner_changed . " -- uid: " . $user['uid'] . " -- tid: " . $tid);
        }
        else
        {
            error($lang->transferthread_invalid_user);
        }
    }
}


/*
// Move a thread // line 873
          case "move":
                  add_breadcrumb($lang->nav_move);
                  if(!is_moderator($fid, "canmanagethreads"))
                  {
                          error_no_permission();
                  }
  
                  $plugins->run_hooks("moderation_move");
  
                  $forumselect = build_forum_jump("", '', 1, '', 0, true, '', "moveto");
                  eval("\$movethread = \"".$templates->get("moderation_move")."\";");
                  output_page($movethread);
                  break;


// Let's get this thing moving! // line 888
          case "do_move":
  
                  // Verify incoming POST request
                  verify_post_check($mybb->get_input('my_post_key'));
  
                  $moveto = $mybb->get_input('moveto', MyBB::INPUT_INT);
                  $method = $mybb->get_input('method');
  
                  if(!is_moderator($fid, "canmanagethreads"))
                  {
                          error_no_permission();
                  }
                  // Check if user has moderator permission to move to destination
                  if(!is_moderator($moveto, "canmanagethreads") && !is_moderator($fid, "canmovetononmodforum"))
                  {
                          error_no_permission();
                  }
                  $newperms = forum_permissions($moveto);
                  if($newperms['canview'] == 0 && !is_moderator($fid, "canmovetononmodforum"))
                  {
                          error_no_permission();
                  }
  
                  $newforum = get_forum($moveto);
                  if(!$newforum || $newforum['type'] != "f" || $newforum['type'] == "f" && $newforum['linkto'] != '')
                  {
                          error($lang->error_invalidforum);
                  }
                  if($method != "copy" && $thread['fid'] == $moveto)
                  {
                          error($lang->error_movetosameforum);
                  }
  
                  $plugins->run_hooks('moderation_do_move');
  
                  $expire = 0;
                  if($mybb->get_input('redirect_expire', MyBB::INPUT_INT) > 0)
                  {
                          $expire = TIME_NOW + ($mybb->get_input('redirect_expire', MyBB::INPUT_INT) * 86400);
                  }
  
                  $the_thread = $tid;
  
                  $newtid = $moderation->move_thread($tid, $moveto, $method, $expire);
  
                  switch($method)
                  {
                          case "copy":
                                  log_moderator_action($modlogdata, $lang->thread_copied);
                                  break;
                          default:
                          case "move":
                          case "redirect":
                                  log_moderator_action($modlogdata, $lang->thread_moved);
                                  break;
                  }
  
                  moderation_redirect(get_thread_link($newtid), $lang->redirect_threadmoved);
                  break;

*/
