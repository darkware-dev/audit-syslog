<?php
/*******************************************************************************
 * audit-syslog: A WordPress plugin to pass security auditing information to syslog.
 * Copyright (c) $now.year  jeff@darkware.org and contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 ******************************************************************************/

/**
Plugin Name: Audit Logging
Plugin URI: http://darkware.org/audit-syslog/
Description: Enable
Version: 0.1
Author: Jeff Sharpe
Author URI: http://darkware.org
License: GPLv3 or later
*/

openlog("wordpress", LOG_PID | LOG_PERROR, LOG_LOCAL1);

function log_action($level, $message)
{
    $current_user = wp_get_current_user();
    if (is_user_logged_in()) $uname = $current_user->user_login;
    else $uname = "";

   log_user_action($level, $uname, $message);
}

function get_blog_token($blog_id)
{
    // Check for the special case of the root domain
    if (get_current_blog_id() == 1) return "root";

    $baseinfo = get_blog_details(1);
    $bloginfo = get_blog_details($blog_id);

    if ($bloginfo->domain == $baseinfo->domain)
    {
        // We're in a path-based multisite install
        return $bloginfo->path;
    }
    else
    {
        // We're in a domain-based multisite install
        $subdomain = $bloginfo->domain;
        $basedomain = $baseinfo->domain;

        $basepos = strpos($subdomain, $basedomain);
        if ($basepos == strlen($subdomain) - strlen($basedomain)) return substr($subdomain, 0, $basepos-1);
        else return $subdomain;
    }
}

function current_blog_token()
{
    return get_blog_token(get_current_blog_id());
}

function log_user_action($level, $user, $message)
{
    if (array_key_exists('HTTP_X_REAL_IP', $_SERVER)) $remote_ip = $_SERVER['HTTP_X_REAL_IP'];
    else $remote_ip = $_SERVER['REMOTE_ADDR'];

    syslog($level, sprintf("[%s] <%s>@%s :: %s", current_blog_token(), $user, $remote_ip, $message));
}

add_action('wp_login', 'audsys_wp_login', 1, 2);
function audsys_wp_login($user_login, $user)
{
    log_user_action(LOG_NOTICE, $user_login, "User logged in");
}

add_action('activated_plugin', 'audsys_activate_plugin', 1, 2);
function audsys_activate_plugin($plugin, $across_network)
{
    if ($across_network) log_action(LOG_NOTICE, "Plugin enabled for network: $plugin");
    else log_action(LOG_NOTICE, "Plugin enabled: $plugin");
}

add_action('deactivated_plugin', 'audsys_deactivate_plugin', 1, 2);
function audsys_deactivate_plugin($plugin, $across_network)
{
    if ($across_network) log_action(LOG_NOTICE, "Plugin disabled for network: $plugin");
    else log_action(LOG_NOTICE, "Plugin disabled: $plugin");
}

add_action('activate_plugin', 'audsys_switch_theme', 1, 2);
function audsys_switch_theme($theme_name, $theme)
{
    log_action(LOG_NOTICE, "Theme switched: $theme_name");
}

add_action('added_existing_user', 'audsys_add_existing_user', 1, 2);
function audsys_add_existing_user($uid, $success)
{
    $user = get_user_by('id', $uid);
    $username = (isset($user) && isset($user->user_login)) ? $user->user_login : "<unknown>";
    if ($success === true) log_action(LOG_NOTICE, "Added existing user: $username [#$uid]");
    else log_action(LOG_NOTICE, "Unsuccessfully attempted to add user: $username [#$uid]");
}

add_action('remove_user_from_blog', 'audsys_remove_blog_user', 1, 2);
function audsys_remove_blog_user($uid, $blog_id)
{
    $user = get_user_by('id', $uid);
    $username = (isset($user) && isset($user->user_login)) ? $user->user_login : "<unknown>";

    if ($blog_id === get_current_blog_id()) log_action(LOG_NOTICE, "Removed user from blog: $username [#$uid]");
    else
    {
        $blog_token = get_blog_token($blog_id);
        log_action(LOG_NOTICE, "Removed user from external blog '$blog_token' [#$blog_id]: $username [#$uid]");
    }
}

add_action('user_register', 'audsys_add_user', 1, 1);
function audsys_add_user($uid)
{
    $user = get_user_by('id', $uid);
    $username = (isset($user) && isset($user->user_login)) ? $user->user_login : "<unknown>";
    log_action(LOG_NOTICE, "Created new user: $username [#$uid]");
}

add_action('add_user_role', 'audsys_set_role', 1, 2);
function audsys_set_role($uid, $role)
{
    $user = get_user_by('id', $uid);
    $username = (isset($user) && isset($user->user_login)) ? $user->user_login : "<unknown>";
    log_action(LOG_NOTICE, "Set role for user $username [#$uid] to $role");
}

add_action('delete_user', 'audsys_delete_user', 1, 2);
function audsys_delete_user($uid, $reassign)
{
    $user = get_user_by('id', $uid);
    $username = (isset($user) && isset($user->user_login)) ? $user->user_login : "<unknown>";
    log_action(LOG_NOTICE, "Deleted user $username [#$uid]");
}

add_action(' wpmu_new_blog', 'audsys_create_blog', 1, 6);
function audsys_create_blog($blog_id, $user_id, $domain, $path, $site_id, $meta)
{
    $blog_token = get_blog_token($blog_id);
    $user = get_user_by('id', $user_id);
    $username = (isset($user) && isset($user->user_login)) ? $user->user_login : "<unknown>";
    log_action(LOG_NOTICE, "Created new blog '$blog_token)' with administrator '$username' [#$user_id]");
}

add_action('wpmu_blog_updated', 'audsys_update_blog', 1, 1);
function audsys_update_blog($blog_id)
{
    $blog_token = get_blog_token($blog_id);
    log_action(LOG_NOTICE, "Updated blog details for '$blog_token' [#$blog_id]");
}

add_action('delete_blog', 'audsys_delete_blog', 1, 2);
function audsys_delete_blog($blog_id, $drop)
{
    $blog_token = get_blog_token($blog_id);
    log_action(LOG_NOTICE, "Deleted blog '$blog_token' [#$blog_id]" . (($drop) ? " with database tables." : ""));
}

add_action('archive_blog', 'audsys_blog_status_archive', 1, 1);
function audsys_blog_status_archive($blog_id)
{
    $blog_token = get_blog_token($blog_id);
    log_action(LOG_NOTICE, "Set blog status to archived on '$blog_token' [#$blog_id]");
}

add_action('unarchive_blog', 'audsys_blog_status_unarchive', 1, 1);
function audsys_blog_status_unarchive($blog_id)
{
    $blog_token = get_blog_token($blog_id);
    log_action(LOG_NOTICE, "Set blog status to not-archived on '$blog_token' [#$blog_id]");
}

add_action('activate_blog', 'audsys_blog_status_activated', 1, 1);
function audsys_blog_status_activated($blog_id)
{
    $blog_token = get_blog_token($blog_id);
    log_action(LOG_NOTICE, "Set blog status to archived on '$blog_token' [#$blog_id]");
}

add_action('deactivate_blog', 'audsys_blog_status_deactivated', 1, 1);
function audsys_blog_status_deactivated($blog_id)
{
    $blog_token = get_blog_token($blog_id);
    log_action(LOG_NOTICE, "Set blog status to archived on '$blog_token' [#$blog_id]");
}

add_action('make_spam_blog', 'audsys_blog_status_spam', 1, 1);
function audsys_blog_status_spam($blog_id)
{
    $blog_token = get_blog_token($blog_id);
    log_action(LOG_NOTICE, "Set blog status to spam on '$blog_token' [#$blog_id]");
}

add_action('make_ham_blog', 'audsys_blog_status_not_spam', 1, 1);
function audsys_blog_status_not_spam($blog_id)
{
    $blog_token = get_blog_token($blog_id);
    log_action(LOG_NOTICE, "Set blog status to non-spam on '$blog_token' [#$blog_id]");
}

add_action('wp_handle_upload', 'audsys_file_upload', 1, 2);
function audsys_file_upload($upload, $context)
{
    $filename = $upload['file'];
    $filetype = (isset($file['type']) && '' != $file['type']) ? $upload['type'] : "unknown";
    log_action(LOG_NOTICE, "Uploaded file: $filename (type: $filetype) [$context]");
}

