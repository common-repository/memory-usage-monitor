<?php
/*
Plugin Name: Memory Usage Monitor
Description: Displays memory/RAM usage for each plugin as they are activated and deactivated.
Version: 1.0.2
Author: Webby Website Optimisation
Author URI: https://www.webby.net.au
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function webbyau_memory_usage_before_plugin_load()
{
    // Check if a plugin is getting activated or deactivated
    $plugin_action = isset($_GET['action']) && ($_GET['action'] == 'activate' || $_GET['action'] == 'deactivate') && isset($_GET['plugin']);
    // Check if a theme is being switched
    $theme_switch = isset($_GET['action']) && $_GET['action'] == 'switch-theme';

    if ($plugin_action || $theme_switch) {
        global $webbyau_memory_usage_start;
        $webbyau_memory_usage_start = memory_get_usage();
    }
}

add_action('admin_init', 'webbyau_memory_usage_before_plugin_load', 10, 2);

function webbyau_memory_usage_after_plugin_load()
{
    global $webbyau_memory_usage_start;
    $webbyau_memory_usage_end = memory_get_usage();
    $memory_usage = round((($webbyau_memory_usage_end - $webbyau_memory_usage_start) / 1024) / 1024, 2);

    // Get recently activated or deactivated plugin
    if(isset($_GET['plugin']) && !empty($_GET['plugin'])) {
        // Sanitize the plugin parameter to ensure it's a string and remove unexpected characters
        $plugin = sanitize_text_field($_GET['plugin']);
    
        // Make sure it's a valid filename
        if (validate_file($plugin) === 0) {
            // Note that plugin_dir_path is a WordPress function that sanitizes the provided
            // directory path and appends a trailing slash to it
            $plugin_path = plugin_dir_path(__DIR__) . '/' . $plugin;
            $plugin_data = get_plugin_data($plugin_path);
    
            if ($plugin_data['Name'] !== 'Memory Usage Monitor') {
                // Save the memory usage message as a transient
                webbyau_memory_usage_set_transient_memory_usage_message('Memory usage for ' . esc_html($plugin_data['Name']) . ': ' . $memory_usage . ' MB', 'notice');
            }
        }
    }
}
add_action('shutdown', 'webbyau_memory_usage_after_plugin_load', 10, 2);

function webbyau_memory_usage_set_transient_memory_usage_message($message, $type)
{
    set_transient('webbyau_memory_usage_transient_message', array(
        'message' => $message,
        'type'    => $type,
    ), 60);
}

function webbyau_memory_usage_admin_notices()
{
    $transient_message = get_transient('webbyau_memory_usage_transient_message');

    if ($transient_message) {
       // Expected to contain only HTML classes. Should be escaped.
        $notice_class = esc_attr($transient_message['type']);
        // The message can contain HTML, so we should escape it properly.
        $message = wp_kses_post($transient_message['message']);

        echo "<div class='notice " . esc_html($notice_class) . " is-dismissible'>";
        echo "<p>" . esc_html($message) . "</p>";
        echo "</div>";
    }
}
add_action('admin_notices', 'webbyau_memory_usage_admin_notices');

function webbyau_memory_usage_custom_plugin_activation()
{
    webbyau_memory_usage_set_transient_memory_usage_message('Activate or deactivate a plugin to see memory usage for that plugin', 'notice');
}
register_activation_hook(__FILE__, 'webbyau_memory_usage_custom_plugin_activation');

function webbyau_memory_usage_theme_activation($old_theme_name, $old_theme)
{
    global $webbyau_memory_usage_start;
    $webbyau_memory_usage_end = memory_get_usage();
    $memory_usage = round((($webbyau_memory_usage_end - $webbyau_memory_usage_start) / 1024) / 1024, 2);

    $new_theme = wp_get_theme();
    webbyau_memory_usage_set_transient_memory_usage_message('Memory usage for ' . $new_theme->get('Name') . ': ' . $memory_usage . ' MB', 'notice');
}

add_action('after_switch_theme', 'webbyau_memory_usage_theme_activation', 10, 2);

function webbyau_memory_usage_before_bulk_action($action) {
    if ($action == 'bulk-plugins' || $action == 'update-selected') {
        global $webbyau_memory_usage_start;
        $webbyau_memory_usage_start = memory_get_usage();
    }
}
add_action('check_admin_referer', 'webbyau_memory_usage_before_bulk_action', 10, 1);

function webbyau_memory_usage_after_bulk_action() {
    global $webbyau_memory_usage_start;
    if (!$webbyau_memory_usage_start) {
        return;
    }
    $webbyau_memory_usage_end = memory_get_usage();
    $memory_usage = round((($webbyau_memory_usage_end - $webbyau_memory_usage_start) / 1024) / 1024, 2);
    if ($_GET['action'] == 'deactivate-selected') {
        webbyau_memory_usage_set_transient_memory_usage_message('Memory usage difference after deactivating selected plugins: ' . $memory_usage . ' MB', 'notice');
    } elseif ($_GET['action'] == 'activate-selected') {
        webbyau_memory_usage_set_transient_memory_usage_message('Memory usage difference after activating selected plugins: ' . $memory_usage . ' MB', 'notice');
    }
}
add_action('admin_footer', 'webbyau_memory_usage_after_bulk_action');

function webbyau_memory_usage_bulk_notice()
{
    $transient_message = get_transient('webbyau_memory_usage_transient_message');

    if ($transient_message) {
        $class = 'notice-' . $transient_message['type'];
        $message = $transient_message['message'];
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        delete_transient('webbyau_memory_usage_transient_message');
    }
}
add_action('admin_notices', 'webbyau_memory_usage_bulk_notice');