<?php
/*
Plugin Name: 	CUA Backup-Restore
Plugin URI:	http://cuabr.net
Description: 	Provides Backup & Restore capabilities for network and blog administrators on MultiSite WordPress sites.
Author: 	Can Ugur Ayfer
Author URI: 	http://cuabr.net
License: 	GPL3
Version:	1.0 
*/
/*  Copyright 2015  Can Ugur Ayfer  (email : cayfer@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 3, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You can review the GPL3 License at http://www.gnu.org/licenses/gpl-3.0.txt
	or write to the Free Software Foundation, Inc., 
        51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/****  Thanks **************************************
I've used Benbodhi's "SVG Support" plugin source
as the skeleton of this plugin. His code is clean,
neat, readable. I didn't hesitate to do this because
his plugin is covered by GPL2.

Thanks Benbodhi!
***************************************************/

// Security thing
if ( ! defined( 'ABSPATH' ) ) {
   echo "what?";
   exit;
}


/*******
 GLOBALS
********/

require_once(ABSPATH."/".WPINC."/pluggable.php");
require_once(ABSPATH."/".WPINC."/link-template.php");
define( 'CUABR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );


global $cua_plugin_version;
global $logo_image;
global $help_icon;
$cua_plugin_version = '1.0';			

$plugin_file = plugin_basename(__FILE__);

// Get the site_options options
$cuabr_mysql          = get_site_option( 'cuabr_mysql', 'undefined');
$cuabr_mysqldump      = get_site_option( 'cuabr_mysqldump', 'undefined');
$cuabr_max_backup_age = get_site_option( 'cuabr_max_backup_age', 0);
$cuabr_quota          = get_site_option( 'cuabr_quota', 4);

// get the location of the logo image and help image
// make sure that we refer to network plugin dir; not the blog's plugin dir
switch_to_blog(0);
$logo_image = plugin_dir_url( __FILE__ )."img/logo.png";
$help_icon  = plugin_dir_url( __FILE__ )."img/help.png";
restore_current_blog();

/********
 INCLUDES 
*********/
include( CUABR_PLUGIN_PATH . 'lib/cuabr-init.php' ); 
include( CUABR_PLUGIN_PATH . 'lib/enqueue.php' );
include( CUABR_PLUGIN_PATH . 'lib/validate_input.php' );
include( CUABR_PLUGIN_PATH . 'lib/do_backup.php' );
include( CUABR_PLUGIN_PATH . 'lib/do_restore.php' );
include( CUABR_PLUGIN_PATH . 'lib/do_restoreas.php' );


/**********
 DEVEL AIDS 
***********/
function cua2_debug ($msg) {
    file_put_contents ( "/tmp/cua-plugin.log", $msg."\n", FILE_APPEND);
}


