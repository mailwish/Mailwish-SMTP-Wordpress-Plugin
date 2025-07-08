<?php
/**
 * Uninstall script for MailWish SMTP plugin
 * 
 * This file is executed when the plugin is deleted from WordPress admin.
 * It cleans up all plugin data from the database.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('mailwish_smtp_options');

// For multisite installations
if (is_multisite()) {
    // Get all blog IDs
    $blog_ids = get_sites(array('fields' => 'ids'));
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        delete_option('mailwish_smtp_options');
        restore_current_blog();
    }
}

// Clean up any transients (if we had any)
delete_transient('mailwish_smtp_test_result');

// Remove any custom database tables (if we had any)
// global $wpdb;
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mailwish_smtp_logs");

// Clear any cached data
wp_cache_flush();
?>
