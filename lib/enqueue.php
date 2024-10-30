<?php
/********************************************
* ENQUEUE SCRIPTS AND STYLES
********************************************/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action( 'admin_enqueue_scripts', 'cuabr_ajax_related' );

function cuabr_ajax_related() {

   wp_register_script('cuabr_ajax', 
                    plugins_url('cua-backuprestore/js/cuabr-jscripts.js'), 
                    array('jquery'), 
                    '1.0.0', 
                    false );
   wp_enqueue_script('cuabr_ajax');

   wp_register_script('cuabr_dialog', 
                    plugins_url('cua-backuprestore/js/cuabr-sweetalert.js'), 
                    array('jquery'), 
                    '1.0.0', 
                    false );
   wp_enqueue_script('cuabr_dialog');


}

add_action( 'admin_enqueue_scripts', 'cuabr_dialog_related' );
function cuabr_dialog_related() {
        wp_enqueue_style( 'cuabr_sweetalert_css', plugins_url('cua-backuprestore/css/cuabr-sweetalert.css') );
}
