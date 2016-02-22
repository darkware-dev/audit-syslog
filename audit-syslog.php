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

function current_blog_token()
{
    // Check for the special case of the root domain
    if (get_current_blog_id() == 1) return "root";

    $baseinfo = get_blog_details(1);
    $bloginfo = get_blog_details(get_current_blog_id());

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
function audsys_activate_plugin($plugin, $network_enabled)
{
    log_action(LOG_NOTICE, "Plugin enabled: $plugin");
}

add_action('deactivated_plugin', 'audsys_deactivate_plugin', 1, 2);
function audsys_deactivate_plugin($plugin, $network_enabled)
{
    log_action(LOG_NOTICE, "Plugin disabled: $plugin");
}

/*
add_action('activate_plugin', 'audsys_activate_plugin', 1, 2);
function audsys_activate_plugin($plugin, $network_enabled)
{
    log_action(LOG_NOTICE, "Plugin enabled: $plugin");
}
*/
