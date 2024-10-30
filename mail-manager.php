<?php
/*
Plugin Name: Mail Manager
Version: 0.1
Plugin URI: http://mail-manager.ioerror.us/
Author: Michael Hampton
Author URI: http://www.homelandstupidity.us/
Description: Sends administrative email messages for a wide variety of WordPress events.
License: GPL

Mail Manager - Additional email notification options for WordPress
Copyright (C) 2008 Michael Hampton

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('ABSPATH')) die;

$mmgr_remove_to = false;
$mmgr_moderation = false;
$mmgr_skip = false;

if (is_admin() || strstr($_SERVER['PHP_SELF'], 'wp-admin/')) {
	require_once(dirname(__FILE__).'/admin.php');
}

/*
 * Convert textual headers to an array
 * Capitalize "CC" so we can find it later
 */
function mmgr_headers_to_array($headers)
{
	/* Nothing to do? */
	if (is_array($headers)) return $headers;
	if (empty($headers)) return array();

	/* Break them down */
	$tempheaders = (array) explode("\n", $headers);
	$headers = array();
	foreach ($tempheaders as $header) {
		if (strpos($header, ":") === false) continue;
		list ($name, $content) = explode(":", trim($header), 2);
		if (strtolower(trim($name)) == 'cc') $name = 'CC';
		$headers[trim($name)] = trim($content);
	}
	return $headers;
}

/*
 * Convert array headers back to textual form
 */
function mmgr_headers_to_string($headers)
{
	/* Nothing to do? */
	if (!is_array($headers)) return $headers;
	if (empty($headers)) return "";

	/* Something to do */
	$newheaders = "";
	foreach ($headers as $key => $value) {
		$newheaders .= "$key: $value\n";
	}
	return $newheaders;
}

/*
 * Determine who the recipients should be for comment/moderation notices
 */
function mmgr_get_comment_recipients($option)
{
	global $mmgr_remove_to;

	$recipients = array();
	$options = get_option('mmgr_options');
	if (!$options["{$option}_postauthor"]) {
		$mmgr_remove_to = true;
	}
	if ($options["{$option}_admin"]) {
		$recipients[] = get_option('admin_email');
	}
	foreach($options[$option] as $userid) {
		$tmp_user = new WP_User($userid);
		if ($tmp_user->user_email) {
			$recipients[] = $tmp_user->user_email;
		}
	}
	return array_unique($recipients);
}

/*
 * Add or change recipients for comment notification emails
 */
function mmgr_notify_comments($headers, $comment_id)
{
	global $mmgr_skip;

	if ($mmgr_skip) return $headers;
	$headers = mmgr_headers_to_array($headers);
	$recipients = mmgr_get_comment_recipients('mmgr_comment_notify');

	if (array_key_exists('CC', $headers)) {
		$cc = (array) explode(',', $headers['CC']);
		if (!empty($cc)) {
			foreach ($cc as $cc1) {
				$recipients[] = trim($cc1);
			}
		}
	}
	$headers['CC'] = implode(',', $recipients);
	trigger_error("Generated comment header CC: " . $headers['CC']);
	$mmgr_skip = true;
	return mmgr_headers_to_string($headers);
}

/*
 * Set a flag so we can mangle the headers later. This is done because WP
 * doesn't currently provide a filter for message headers in comment
 * moderation emails.
 */
function mmgr_flag_moderation($notify_message, $comment_id)
{
	global $mmgr_moderation;

	$mmgr_moderation = $comment_id;
	return $notify_message;
}

/*
 * Add or change recipients for comment moderation emails.
 */
function mmgr_notify_moderation($message)
{
	global $mmgr_skip;

	if (!$mmgr_moderation) return $message;
	if ($mmgr_skip) return $message;
	extract($message);
	$headers = mmgr_headers_to_array($headers);
	$recipients = mmgr_get_comment_recipients('mmgr_moderation_notify');

	if (array_key_exists('CC', $headers)) {
		$cc = (array) explode(',', $headers['CC']);
		if (!empty($cc)) {
			foreach ($cc as $cc1) {
				$recipients[] = trim($cc1);
			}
		}
	}
	$headers['CC'] = implode(',', $recipients);
	trigger_error("Generated moderation header CC: " . $headers['CC']);
	$mmgr_skip = true;
	return compact('to', 'subject', 'message', 'headers');
}

/*
 * Sometimes the To: recipient should not receive the mail.
 * We substitute the first CC instead. Should never get here
 * without at least one CC, but if we do, To will be empty,
 * and without recipients, no mail should be sent.
 */
function mmgr_remove_to($message)
{
	global $mmgr_remove_to;

	if (!$mmgr_remove_to) return $message;
	extract($message);
	$tempheaders = mmgr_headers_to_array($headers);
	$recipients = array();
	if (array_key_exists('CC', $tempheaders)) {
		$cc = (array) explode(',', $headers['CC']);
		if (!empty($cc)) {
			foreach ($cc as $cc1) {
				$recipients[] = trim($cc1);
			}
		}
	} else {
		$cc = array();
	}
	@$to = array_shift($cc);
	if (!empty($cc)) {
		$headers['CC'] = implode(',', $cc);
	} else {
		unset($headers['CC']);
	}
	return compact('to', 'subject', 'message', 'headers');
}

add_filter('comment_notification_headers', 'mmgr_notify_comments');
add_filter('comment_moderation_text', 'mmgr_flag_moderation');
add_filter('wp_mail', 'mmgr_notify_moderation');
add_filter('wp_mail', 'mmgr_remove_to');
