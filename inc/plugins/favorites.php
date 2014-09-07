<?php
/**
 * Favorite Threads
 * Copyright 2010 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(my_strpos($_SERVER['PHP_SELF'], 'usercp.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'usercp_favorites,usercp_favorites_none,usercp_favorites_thread,usercp_favorites_remove';
}

if(my_strpos($_SERVER['PHP_SELF'], 'showthread.php'))
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'showthread_favorite';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("usercp_start", "favorites_run");
$plugins->add_hook("usercp_menu", "favorites_lang");
$plugins->add_hook("showthread_start", "favorites_thread");
$plugins->add_hook("fetch_wol_activity_end", "favorites_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "favorites_online_location");
$plugins->add_hook("datahandler_user_delete_content", "favorites_delete");

$plugins->add_hook("admin_user_users_merge_commit", "favorites_merge");

// The information that shows up on the plugin manager
function favorites_info()
{
	global $lang;
	$lang->load("favorites", true);

	return array(
		"name"				=> $lang->favorites_info_name,
		"description"		=> $lang->favorites_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is installed.
function favorites_install()
{
	global $db;
	favorites_uninstall();
	$collation = $db->build_create_table_collation();

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."favorites (
				fid int(10) unsigned NOT NULL auto_increment,
				uid int(10) unsigned NOT NULL default '0',
				tid int(10) unsigned NOT NULL default '0',
				KEY uid (uid),
				PRIMARY KEY(fid)
			) ENGINE=MyISAM{$collation}");
}

// Checks to make sure plugin is installed
function favorites_is_installed()
{
	global $db;
	if($db->table_exists("favorites"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function favorites_uninstall()
{
	global $db;
	if($db->table_exists("favorites"))
	{
		$db->drop_table("favorites");
	}
}

// This function runs when the plugin is activated.
function favorites_activate()
{
	global $db;
	
	$insert_array = array(
		'title'		=> 'usercp_favorites',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->favorites}</title>
{$headerinclude}
</head>
<body>
{$header}
<form action="usercp.php" method="post" name="input">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="do_favorites" />
<table width="100%" border="0" align="center">
<tr>
{$usercpnav}
<td valign="top">
{$multipage}
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="7"><strong>{$lang->favorites} ({$threadcount})</strong></td>
</tr>
<tr>
<td class="tcat" align="center" colspan="2">&nbsp;</td>
<td class="tcat" align="center"><span class="smalltext"><strong>{$lang->thread}</strong></span></td>
<td class="tcat" align="center" width="7%"><span class="smalltext"><strong>{$lang->replies}</strong></span></td>
<td class="tcat" align="center" width="7%"><span class="smalltext"><strong>{$lang->views}</strong></span></td>
<td class="tcat" align="center" width="200"><span class="smalltext"><strong>{$lang->lastpost}</strong></span></td>
<td class="tcat" align="center" width="1"><input name="allbox" title="Select All" type="checkbox" class="checkbox checkall" value="1" /></td>
</tr>
{$threads}
{$remove_options}
</table>
<br />
<div class="float_left">
	<div class="float_left">
		<dl class="thread_legend smalltext">
			<dd><span class="thread_status newfolder" title="{$lang->new_thread}">&nbsp;</span> {$lang->new_thread}</dd>
			<dd><span class="thread_status newhotfolder" title="{$lang->new_hot_thread}">&nbsp;</span> {$lang->new_hot_thread}</dd>
			<dd><span class="thread_status hotfolder" title="{$lang->hot_thread}">&nbsp;</span> {$lang->hot_thread}</dd>
		</dl>
	</div>

	<div class="float_left">
		<dl class="thread_legend smalltext">
			<dd><span class="thread_status folder" title="{$lang->no_new_thread}">&nbsp;</span> {$lang->no_new_thread}</dd>
			<dd><span class="thread_status dot_folder" title="{$lang->posts_by_you}">&nbsp;</span> {$lang->posts_by_you}</dd>
			<dd><span class="thread_status lockfolder" title="{$lang->locked_thread}">&nbsp;</span> {$lang->locked_thread}</dd>
		</dl>
	</div>
	<br class="clear" />
</div>
{$multipage}
</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_favorites_none',
		'template'	=> $db->escape_string('<tr>
<td class="trow1" colspan="7">{$lang->no_favorite_threads}</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_favorites_thread',
		'template'	=> $db->escape_string('<tr>
<td align="center" class="{$bgcolor}" width="2%"><span class="thread_status {$folder}" title="{$folder_label}">&nbsp;</span></td>
<td align="center" class="{$bgcolor}" width="2%">{$icon}</td>
<td class="{$bgcolor}">{$gotounread}{$thread[\'threadprefix\']}<a href="{$thread[\'threadlink\']}" class="{$new_class}">{$thread[\'subject\']}</a><br /><span class="smalltext"><a href="newreply.php?tid={$thread[\'tid\']}">{$lang->post_reply}</a> | <a href="showthread.php?action=removefavorite&amp;tid={$thread[\'tid\']}&amp;my_post_key={$mybb->post_code}">{$lang->delete_from_favorites}</a></span></td>
<td align="center" class="{$bgcolor}"><a href="javascript:MyBB.whoPosted({$thread[\'tid\']});">{$thread[\'replies\']}</a></td>
<td align="center" class="{$bgcolor}">{$thread[\'views\']}</td>
<td class="{$bgcolor}" style="white-space: nowrap">
<span class="smalltext">{$lastpostdate}<br />
<a href="{$thread[\'lastpostlink\']}">{$lang->lastpost}</a>: {$lastposterlink}</span>
</td>
<td class="{$bgcolor}" align="center"><input type="checkbox" class="checkbox" name="check[{$thread[\'tid\']}]" value="{$thread[\'tid\']}" /></td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'usercp_favorites_remove',
		'template'	=> $db->escape_string('<tr>
<td class="tfoot" colspan="7">
<div class="float_right"><strong>{$lang->with_selected}</strong>
<select name="do">
<option value="delete">{$lang->delete_favorites}</option>
<option value="upgrade_subscription">{$lang->upgrade_subscription}</option>
</select>
{$gobutton}
</div>
<div>
<strong><a href="usercp.php?action=removefavorites&amp;my_post_key={$mybb->post_code}">{$lang->remove_all_favorites}</a></strong>
</div>
</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'showthread_favorite',
		'template'	=> $db->escape_string('<li style="background: url(\'images/favorites_{$add_remove_favorite}.png\') no-repeat 0px 0px;"><a href="showthread.php?action={$add_remove_favorite}favorite&amp;tid={$tid}&amp;my_post_key={$mybb->post_code}">{$add_remove_favorite_text}</a></li>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread", "#".preg_quote('{$add_remove_subscription_text}</a></li>')."#i", '{$add_remove_subscription_text}</a></li>{$addremovefavorite}');
	find_replace_templatesets("usercp_nav_misc", "#".preg_quote('{$draftcount}</a></td></tr>')."#i", '{$draftcount}</a></td></tr><tr><td class="trow1 smalltext"><a href="usercp.php?action=favorites" class="usercp_nav_item" style="background:url(\'images/favorites.png\') no-repeat left center;">{$lang->ucp_nav_favorite_threads}</a></td></tr>');
}

// This function runs when the plugin is deactivated.
function favorites_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('usercp_favorites','usercp_favorites_none','usercp_favorites_thread','usercp_favorites_remove','showthread_favorite')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("showthread", "#".preg_quote('{$addremovefavorite}')."#i", '', 0);
	find_replace_templatesets("usercp_nav_misc", "#".preg_quote('<tr><td class="trow1 smalltext"><a href="usercp.php?action=favorites" class="usercp_nav_item" style="background:url(\'images/favorites.png\') no-repeat left center;">{$lang->ucp_nav_favorite_threads}</a></td></tr>')."#i", '', 0);
}

// The main User CP favorites page
function favorites_run()
{
	global $db, $mybb, $templates, $theme, $headerinclude, $header, $usercpnav, $lang, $footer, $favorites, $cache, $parser, $gobutton, $multipage;
	$lang->load("favorites");

	require_once MYBB_ROOT."inc/functions_user.php";

	if($mybb->input['action'] == "removefavorites")
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);
		{
			$db->delete_query("favorites", "uid='".$mybb->user['uid']."'");
			if($server_http_referer)
			{
				$url = $server_http_referer;
			}
			else
			{
				$url = "usercp.php?action=favorites";
			}
			redirect($url, $lang->redirect_favoritesremoved);
		}
	}

	if($mybb->input['action'] == "do_favorites")
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		if(!is_array($mybb->input['check']))
		{
			error($lang->no_favorites_selected);
		}

		// Clean input - only accept integers thanks!
		$mybb->input['check'] = array_map('intval', $mybb->input['check']);
		$tids = implode(",", $mybb->input['check']);

		// Deleting these favorites?
		if($mybb->input['do'] == "delete")
		{
			$db->delete_query("favorites", "tid IN ({$tids}) AND uid='{$mybb->user['uid']}'");
		}
		// Upgrade to subscription
		else
		{
			if($mybb->input['do'] == "upgrade_subscription")
			{
				add_subscribed_thread($tids);
			}
		}

		// Done, redirect
		redirect("usercp.php?action=favorites", $lang->redirect_favorites_updated);
	}

	if($mybb->input['action'] == "favorites")
	{
		add_breadcrumb($lang->nav_usercp, "usercp.php");
		add_breadcrumb($lang->nav_favorites, "usercp.php?action=favorites");

		// Thread visiblity
		$visible = "AND t.visible != 0";
		if(is_moderator() == true)
		{
			$visible = '';
		}

		// Do Multi Pages
		$query = $db->query("
			SELECT COUNT(f.tid) as threads
			FROM ".TABLE_PREFIX."favorites f
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid = f.tid)
			WHERE f.uid = '".$mybb->user['uid']."' AND t.visible >= 0 {$visible}
		");
		$threadcount = $db->fetch_field($query, "threads");

		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}

		$perpage = $mybb->settings['threadsperpage'];
		$page = $mybb->get_input('page', 1);
		if($page > 0)
		{
			$start = ($page-1) * $perpage;
			$pages = $threadcount / $perpage;
			$pages = ceil($pages);
			if($page > $pages || $page <= 0)
			{
				$start = 0;
				$page = 1;
			}
		}
		else
		{
			$start = 0;
			$page = 1;
		}
		$end = $start + $perpage;
		$lower = $start+1;
		$upper = $end;
		if($upper > $threadcount)
		{
			$upper = $threadcount;
		}
		$multipage = multipage($threadcount, $perpage, $page, "usercp.php?action=favorites");
		$fpermissions = forum_permissions();
		$del_favorites = $favorites = array();

		$query = $db->query("
			SELECT f.fid AS fav, f.tid, t.*, t.username AS threadusername, u.username
			FROM ".TABLE_PREFIX."favorites f
			LEFT JOIN ".TABLE_PREFIX."threads t ON (f.tid=t.tid)
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = t.uid)
			WHERE f.uid='".$mybb->user['uid']."' AND t.visible >= 0 {$visible}
			ORDER BY t.lastpost DESC
			LIMIT {$start}, {$perpage}
		");
		while($favorite = $db->fetch_array($query))
		{
			$forumpermissions = $fpermissions[$favorite['fid']];
			// Only keep if we're allowed to view them
			if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $favorite['uid'] != $mybb->user['uid']))
			{
				// Hmm, you don't have permission to view this thread - remove!
				$del_favorites[] = $favorite['fav'];
			}

			else if($favorite['tid'])
			{
				$favorites[$favorite['tid']] = $favorite;
			}
		}

		if(!empty($del_favorites))
		{
			$fids = implode(',', $del_favorites);

			if($fids)
			{
				$db->delete_query("favorites", "fid IN ({$fids}) AND uid='{$mybb->user['uid']}'");
			}

			$threadcount = $threadcount - count($del_favorites);

			if($threadcount < 0)
			{
				$threadcount = 0;
			}
		}

		if(!empty($favorites))
		{
			$tids = implode(",", array_keys($favorites));
		
			if($mybb->user['uid'] == 0)
			{
				// Build a forum cache.
				$query = $db->query("
					SELECT fid
					FROM ".TABLE_PREFIX."forums
					WHERE active != 0
					ORDER BY pid, disporder
				");
			
				$forumsread = my_unserialize($mybb->cookies['mybb']['forumread']);
			}
			else
			{
				// Build a forum cache.
				$query = $db->query("
					SELECT f.fid, fr.dateline AS lastread
					FROM ".TABLE_PREFIX."forums f
					LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=f.fid AND fr.uid='{$mybb->user['uid']}')
					WHERE f.active != 0
					ORDER BY pid, disporder
				");
			}
			while($forum = $db->fetch_array($query))
			{
				if($mybb->user['uid'] == 0)
				{
					if($forumsread[$forum['fid']])
					{
						$forum['lastread'] = $forumsread[$forum['fid']];
					}
				}
				$readforums[$forum['fid']] = $forum['lastread'];
			}

			// Check participation by the current user in any of these threads - for 'dot' folder icons
			if($mybb->settings['dotfolders'] != 0)
			{
				$query = $db->simple_select("posts", "tid,uid", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
				while($post = $db->fetch_array($query))
				{
					$favorites[$post['tid']]['doticon'] = 1;
				}
			}

			// Read threads
			if($mybb->settings['threadreadcut'] > 0)
			{
				$query = $db->simple_select("threadsread", "*", "uid='{$mybb->user['uid']}' AND tid IN ({$tids})");
				while($readthread = $db->fetch_array($query))
				{
					$favorites[$readthread['tid']]['lastread'] = $readthread['dateline'];
				}
			}

			$icon_cache = $cache->read("posticons");
			$threadprefixes = build_prefixes();

			$threads = '';

			// Now we can build our favorite list
			foreach($favorites as $thread)
			{
				$bgcolor = alt_trow();

				$folder = '';
				$prefix = '';

				// If this thread has a prefix, insert a space between prefix and subject
				if($thread['prefix'] != 0 && isset($threadprefixes[$thread['prefix']]))
				{
					$thread['threadprefix'] = $threadprefixes[$thread['prefix']]['displaystyle'].'&nbsp;';
				}

				// Sanitize
				$thread['subject'] = $parser->parse_badwords($thread['subject']);
				$thread['subject'] = htmlspecialchars_uni($thread['subject']);

				// Build our links
				$thread['threadlink'] = get_thread_link($thread['tid']);
				$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

				// Fetch the thread icon if we have one
				if($thread['icon'] > 0 && $icon_cache[$thread['icon']])
				{
					$icon = $icon_cache[$thread['icon']];
					$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
					eval("\$icon = \"".$templates->get("usercp_subscriptions_thread_icon")."\";");
				}
				else
				{
					$icon = "&nbsp;";
				}

				// Determine the folder
				$folder = '';
				$folder_label = '';

				if(isset($thread['doticon']))
				{
					$folder = "dot_";
					$folder_label .= $lang->icon_dot;
				}

				$gotounread = '';
				$isnew = 0;
				$donenew = 0;
				$lastread = 0;

				if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
				{
					$forum_read = $readforums[$thread['fid']];
			
					$read_cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
					if($forum_read == 0 || $forum_read < $read_cutoff)
					{
						$forum_read = $read_cutoff;
					}
				}
				else
				{
					$forum_read = $forumsread[$thread['fid']];
				}

				$cutoff = 0;
				if($mybb->settings['threadreadcut'] > 0 && $thread['lastpost'] > $forum_read)
				{
					$cutoff = TIME_NOW-$mybb->settings['threadreadcut']*60*60*24;
				}

				if($thread['lastpost'] > $cutoff)
				{
					if($thread['lastread'])
					{
						$lastread = $thread['lastread'];
					}
					else
					{
						$lastread = 1;
					}
				}

				if(!$lastread)
				{
					$readcookie = $threadread = my_get_array_cookie("threadread", $thread['tid']);
					if($readcookie > $forum_read)
					{
						$lastread = $readcookie;
					}
					else
					{
						$lastread = $forum_read;
					}
				}

				if($thread['lastpost'] > $lastread && $lastread)
				{
					$folder .= "new";
					$folder_label .= $lang->icon_new;
					$new_class = "subject_new";
					$thread['newpostlink'] = get_thread_link($thread['tid'], 0, "newpost");
					eval("\$gotounread = \"".$templates->get("forumdisplay_thread_gotounread")."\";");
					$unreadpost = 1;
				}
				else
				{
					$folder_label .= $lang->icon_no_new;
					$new_class = "";
				}

				if($thread['replies'] >= $mybb->settings['hottopic'] || $thread['views'] >= $mybb->settings['hottopicviews'])
				{
					$folder .= "hot";
					$folder_label .= $lang->icon_hot;
				}

				if($thread['closed'] == 1)
				{
					$folder .= "lock";
					$folder_label .= $lang->icon_lock;
				}

				$folder .= "folder";

				if($thread['visible'] == 0)
				{
					$bgcolor = "trow_shaded";
				}

				// Build last post info
				$lastpostdate = my_date('relative', $thread['lastpost']);
				$lastposter = $thread['lastposter'];
				$lastposteruid = $thread['lastposteruid'];

				// Don't link to guest's profiles (they have no profile).
				if($lastposteruid == 0)
				{
					$lastposterlink = $lastposter;
				}
				else
				{
					$lastposterlink = build_profile_link($lastposter, $lastposteruid);
				}

				$thread['replies'] = my_number_format($thread['replies']);
				$thread['views'] = my_number_format($thread['views']);

				eval("\$threads .= \"".$templates->get("usercp_favorites_thread")."\";");
			}

			// Provide remove options
			eval("\$remove_options = \"".$templates->get("usercp_favorites_remove")."\";");
		}
		else
		{
			$remove_options = '';
			eval("\$threads = \"".$templates->get("usercp_favorites_none")."\";");
		}

		eval("\$favorites = \"".$templates->get("usercp_favorites")."\";");
		output_page($favorites);
	}
}

// Show language on User CP menu
function favorites_lang()
{
	global $lang;
	$lang->load("favorites");
}

// Show Thread add/remove favorite links
function favorites_thread()
{
	global $db, $mybb, $templates, $theme, $lang, $favorites, $add_remove_favorite_text, $add_remove_favorite, $uid, $tid, $addremovefavorite;
	$lang->load("favorites");

	$server_http_referer = htmlentities($_SERVER['HTTP_REFERER']);

	if($mybb->user['uid'])
	{
		// Favorite status
		$query = $db->simple_select("favorites", "tid", "tid='".(int)$tid."' AND uid='".(int)$mybb->user['uid']."'", array('limit' => 1));
		if($db->fetch_field($query, 'tid'))
		{
			$add_remove_favorite = 'remove';
			$add_remove_favorite_text = $lang->remove_favorite;
		}
		else
		{
			$add_remove_favorite = 'add';
			$add_remove_favorite_text = $lang->add_favorite;
		}

		eval("\$addremovefavorite = \"".$templates->get("showthread_favorite")."\";");
	}

	if($mybb->input['action'] == "addfavorite")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$tid = $mybb->get_input('tid', 1);
		$thread = get_thread($tid);

		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}

		if($mybb->user['uid'] == 0)
		{
			error_no_permission();
		}

		$forumpermissions = forum_permissions($thread['fid']);
		if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || ($forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
		{
			error_no_permission();
		}
		if(!$uid)
		{
			$uid = $mybb->user['uid'];
		}
		$query = $db->simple_select("favorites", "*", "tid='{$tid}' AND uid='".(int)$uid."'", array('limit' => 1));
		$favorite = $db->fetch_array($query);
		if(!$favorite['tid'])
		{
			$insert_array = array(
				'uid' => (int)$uid,
				'tid' => (int)$tid,
			);
			$db->insert_query("favorites", $insert_array);
		}
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = get_thread_link($thread['tid']);
		}
		redirect($url, $lang->redirect_favoriteadded);
	}

	if($mybb->input['action'] == "removefavorite")
	{
		// Verify incoming POST request
		verify_post_check($mybb->get_input('my_post_key'));

		$tid = $mybb->get_input('tid', 1);
		$thread = get_thread($tid);

		if(!$thread['tid'])
		{
			error($lang->error_invalidthread);
		}

		if($mybb->user['uid'] == 0)
		{
			error_no_permission();
		}

		if(!$uid)
		{
			$uid = $mybb->user['uid'];
		}
		$db->delete_query("favorites", "tid='{$tid}' AND uid='{$uid}'");
		if($server_http_referer)
		{
			$url = $server_http_referer;
		}
		else
		{
			$url = "usercp.php?action=favorites";
		}
		redirect($url, $lang->redirect_favoriteremoved);
	}
}

// Online location support
function favorites_online_activity($user_activity)
{
	global $user;
	if(my_strpos($user['location'], "usercp.php?action=favorites") !== false)
	{
		$user_activity['activity'] = "usercp_favorites";
	}

	return $user_activity;
}

function favorites_online_location($plugin_array)
{
	global $db, $mybb, $lang, $parameters;
	$lang->load("favorites");

	if($plugin_array['user_activity']['activity'] == "usercp_favorites")
	{
		$plugin_array['location_name'] = $lang->viewing_favorites;
	}

	return $plugin_array;
}

// Delete favorites if user is deleted
function favorites_delete($delete)
{
	global $db;

	$db->delete_query('favorites', 'uid IN('.$delete->delete_uids.')');

	return $delete;
}

// Merge favorites if users are merged
function favorites_merge()
{
	global $db, $mybb, $source_user, $destination_user;
	$uid = array(
		"uid" => $destination_user['uid']
	);	
	$db->update_query("favorites", $uid, "uid='{$source_user['uid']}'");
}

?>