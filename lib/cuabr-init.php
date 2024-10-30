<?php
/********************************************
* CUABR Plugin init
********************************************/
global $wpdb;


if ( ! defined( 'ABSPATH' ) ) {
    echo "What?";
    exit;
}

include( CUABR_PLUGIN_PATH . 'lib/ajax_action_callback.php' );

// add menu item to wp-admin
add_action( 'admin_menu', 'cuabr_admin_menu' );

function cuabr_admin_menu() {

    //add_options_page(
    add_menu_page(
        'CUA Backup-Restore',
        'CUA Backup/Restore',
        'manage_options',
        'cua-backuprestore',
        'cuabr_settings_page'
    );

}

// create settings page
function cuabr_settings_page() {

    if( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'This is not allowed!' );
    }
    require( CUABR_PLUGIN_PATH . 'lib/cuabr-main-page.php' );
}

// register settings in the database
add_action('admin_init', 'cuabr_register_settings');

function cuabr_register_settings() {
    register_setting('cuabr_settings_group', 'cuabr_BackupRestore', 'cuabr_validate_input');
}

// Register Ajax stuff
add_action( "wp_ajax_cuabr_action", "cuabr_action_callback");

// Remove the "Thank you" text from the std footer
add_filter ( 'admin_footer_text', 'cuabr_null_footer');
function cuabr_null_footer () {
   echo "";
}

// Remove the version text from the std footer
add_filter ( 'update_footer', 'cuabr_null_version', 11);
function cuabr_null_version () {
   return " ";
}

