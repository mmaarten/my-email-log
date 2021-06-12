<?php
/**
 * Plugin Name:       My Email Log
 * Plugin URI:        https://github.com/mmaarten/my-email-log
 * Description:       Logs information about emails send by use of the wp_mail function.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      5.6
 * Author:            Maarten Menten
 * Author URI:        https://profiles.wordpress.org/maartenm/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * textdomain:        my-email-log
 */

$autoloader = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoloader)) {
    require $autoloader;
}

define('MY_EMAIL_LOG_PLUGIN_FILE', __FILE__);

add_action('plugins_loaded', ['\My\EmailLog\App', 'init']);
