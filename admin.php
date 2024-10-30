<?php
if (!defined('ABSPATH')) die;

function mmgr_user_checkboxes($cap, $field, $users)
{
	global $wpdb;
	// Yes, there's a nice new way to do it, but this is backward
	// compatible to legacy 2.0.11 which is still in use for a while
	$userids = $wpdb->get_col("SELECT ID FROM $wpdb->users");

	foreach ($userids as $userid) {
		$tmp_user = new WP_User($userid);
		if ($tmp_user->has_cap($cap) && !empty($tmp_user->user_email)) {
?>
	<label><input type="checkbox" name="<?php echo $field; ?>[]" value="1" <?php @checked("1", in_array($userid, $users)); ?> /> <?php echo sprintf(__('%1$s (%2$s)'), attribute_escape($tmp_user->display_name), $tmp_user->user_email); ?><br/>
<?php
		}
	}
}

function mmgr_admin_css()
{
	echo "<link rel='stylesheet' href='".get_option('siteurl').dirname(substr(__FILE__, strpos(__FILE__, '/wp-content/plugins/')))."/mail-manager.css' type='text/css' media='all' />\n";
}

/*
 * We override some of WP's settings because unsetting them would cause some
 * functions we hook into to not be called at all, and we need to take control
 * of whether those functions are called. So we decide whether the options
 * should be set based on our settings.
 */
function mmgr_fixup_wp_options($options)
{
	if (!array_key_exists('installed', $options)) {
		if (get_option('comments_notify'))
			$options['mmgr_comment_notify_postauthor'] = 1;
		else
			$options['mmgr_comment_notify_postauthor'] = 0;
		if (get_option('moderation_notify'))
			$options['mmgr_moderation_notify_postauthor'] = 1;
		else
			$options['mmgr_moderation_notify_postauthor'] = 0;
		$options['installed'] = true;
		update_option('mmgr_options', $options);
	}
	if (!$options['mmgr_comment_notify_postauthor'] && !$options['mmgr_comment_notify_admin'] && empty($options['mmgr_comment_notify'])) {
		update_option('comments_notify', 0);
	} else {
		update_option('comments_notify', 1);
	}
	if (!$options['mmgr_moderation_notify_postauthor'] && !$options['mmgr_moderation_notify_admin'] && empty($options['mmgr_moderation_notify'])) {
		update_option('moderation_notify', 0);
	} else {
		update_option('moderation_notify', 1);
	}
}

function mmgr_options()
{
	$options = get_option('mmgr_options');

	if ($_POST) {
		if ($_POST['mmgr_comment_notify_postauthor']) {
			$options['mmgr_comment_notify_postauthor'] = 1;
		} else {
			$options['mmgr_comment_notify_postauthor'] = 0;
		}
		if ($_POST['mmgr_comment_notify_admin']) {
			$options['mmgr_comment_notify_admin'] = 1;
		} else {
			$options['mmgr_comment_notify_admin'] = 0;
		}
		if ($_POST['mmgr_comment_notify']) {
			/* Sanitize */
			$a = $_POST['mmgr_comment_notify'];
			array_walk($a, 'intval');
			$options['mmgr_comment_notify'] = array_unique($a);
		} else {
			$options['mmgr_comment_notify'] = array();
		}
		if ($_POST['mmgr_moderation_notify_postauthor']) {
			$options['mmgr_moderation_notify_postauthor'] = 1;
		} else {
			$options['mmgr_moderation_notify_postauthor'] = 0;
		}
		if ($_POST['mmgr_moderation_notify_admin']) {
			$options['mmgr_moderation_notify_admin'] = 1;
		} else {
			$options['mmgr_moderation_notify_admin'] = 0;
		}
		if ($_POST['mmgr_moderation_notify']) {
			/* Sanitize */
			array_walk($_POST['mmgr_moderation_notify'], 'intval');
			$options['mmgr_moderation_notify'] = $_POST['mmgr_moderation_notify'];
		} else {
			$options['mmgr_moderation_notify'] = array();
		}
		update_option('mmgr_options', $options);
		mmgr_fixup_wp_options($options);
?>
		<div id="message" class="updated fade"><p><strong><?php _e('Options saved.') ?></strong></p></div>
<?php
	}
?>
	<div class="wrap">
	<h2><?php _e("Mail Manager Options"); ?></h2>
	<p>Commercial support is available for this software.</p>
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">

	<table class="form-table">
	<tr><th scope="row"><?php _e("When anyone posts a comment"); ?></th>
	<td>
	<label><input type="checkbox" name="mmgr_comment_notify_postauthor" value="1" <?php checked('1', $options['mmgr_comment_notify_postauthor']); ?> />
	<?php _e('Notify the post author'); ?></label><br/>
	<label><input type="checkbox" name="mmgr_comment_notify_admin" value="1" <?php checked('1', $options['mmgr_comment_notify_admin']); ?> />
	<?php _e('Notify the blog administrator'); ?> (<?php echo get_option('admin_email'); ?>)</label><br/>
	<?php _e('Notify these users:'); ?><br/>
	<?php mmgr_user_checkboxes('edit_others_posts', 'mmgr_comment_notify', $options['mmgr_comment_notify']); ?>
	</td></tr>
	</table>

	<table class="form-table">
	<tr><th scope="row"><?php _e("When a comment is held for moderation"); ?></th>
	<td>
	<label><input type="checkbox" name="mmgr_moderation_notify_postauthor" value="1" <?php checked('1', $options['mmgr_moderation_notify_postauthor']); ?> />
	<?php _e('Notify the post author'); ?></label><br/>
	<label><input type="checkbox" name="mmgr_moderation_notify_admin" value="1" <?php checked('1', $options['mmgr_moderation_notify_admin']); ?> />
	<?php _e('Notify the blog administrator'); ?> (<?php echo get_option('admin_email'); ?>)</label><br/>
	<?php _e('Notify these users:'); ?><br/>
	<?php mmgr_user_checkboxes('edit_others_posts', 'mmgr_moderation_notify', $options['mmgr_moderation_notify']); ?>
	</td></tr>
	</table>

	<p class="submit"><input class="button" type="submit" name="submit" value="<?php _e('Update &raquo;'); ?>" /></p>
	</form>
	</div>
<?php
}

function mmgr_admin_menu()
{
	mmgr_fixup_wp_options(get_option('mmgr_options'));
	if (current_user_can('manage_options')) {
		add_options_page(__('Mail Manager'), __('Mail Manager'), 8, 'mmgr_options', 'mmgr_options');
	}
}

add_action('admin_head', 'mmgr_admin_css');
add_action('admin_menu', 'mmgr_admin_menu');
