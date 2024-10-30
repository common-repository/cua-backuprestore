<?php
function cuabr_do_restoreas (  $from_blog_id, $to_blog_id, $backup_dir, $backup_file, $nonce_dir ) {

    // Similar to function cuabr_do_restore BUT Restores a backup set of 
    // one blog ONTO ANOTHER blog.

    // The restore process:
    //  1. Check if our nonce dir is really there ( /tmp/cua-XXXXXXXX )
    //     RestoreAS from or to FULL NETWORK is not allowed.
    //     Check all files under destination dir are owned by
    //     www-data or its equivalent. If not, issue an error. 
    //  2. create a temp directory for the to_blog in the uploads dir
    //     Typically: /var/www/wp-content/uploads/sites/9/cua-backups/tmp
    //                /var/www/wp-content/uploads/sites/cua-backups/tmp for 
    //                whole network backups
    //  3. untar the from_blog's backup set into this directory
    //     Check if the from_blog data really belongs to from_blog
    //     In the dumpfile.sql file :
    //        Replace all strings like "http://wpserver.com/from_blog/..."
    //             with "http://wpserver.com/to_blog/..."
    //        Replace all table names like "wpms_10_xyz" with "wpms_31_xyz"
    //             where "wpms" is the table prefix for WP site and
    //             "10" and "31" are blog_id's for from_blog and to_blog
    //             repectively.
    //  4. Execute a mysql command to restore tables from the new dumpfile.sql 
    //     which is in the tar ball and remove the dump file.
    //  5. Move the remaining contents of the tmp directory to
    //     the blog's (or network's) uploads directory.

    global $wpdb; // This is how you get access to the database
   
    if ( ! is_dir( $nonce_dir ) )  {
        print   "<font style='font-size:18px;color:red'>Error: Invalid entry point; you are not authorized to do this!</font>";
        wp_die();
    }

    // Is the user an administrator of blog $from_blog_id?
    switch_to_blog ( $from_blog_id);
    if ( ! current_user_can("manage_options")) {
        restore_current_blog();
        return ("You are not an administrator of the source blog.");
    }

    // Is the user an administrator of blog $to_blog_id?
    switch_to_blog ( $to_blog_id);
    if ( ! current_user_can("manage_options")) {
        restore_current_blog();
        return ("You are not an administrator of the destination blog.");
    }

    // create a temp dir under $backup_dir to untar the backup file into
       $tmp_untar_dir = $backup_dir . "tmp";

       // Delete any existing temp directory
       if (  is_dir ($tmp_untar_dir ) ) {
          $rm_cmd = "/bin/rm -r " . $tmp_untar_dir;
          system( $rm_cmd);
       }

       // Create a fresh temp dir
       mkdir ( $tmp_untar_dir );
    
    $pwd = getcwd();            // save the current working dir
    chdir ( $tmp_untar_dir );
    $currentdir = getcwd();     // switch working dir to untar destination
    $tar_cmd = "tar xf $backup_dir$backup_file";

    $completion_code = 9;
    exec( $tar_cmd, $dummy, $completion_code);
    if ( $completion_code != 0 ) {
         print   "Could not execute: \n$tar_cmd";
         wp_die();
    } 

    $db_restore_success  = cuabr_restore_db_as ( $from_blog_id, $to_blog_id, $nonce_dir, $tmp_untar_dir);

    if ( $db_restore_success  == "OK" ) {

         $uploads_restore_success = cuabr_restore_uploads_as ( $from_blog_id, $to_blog_id, $nonce_dir, $tmp_untar_dir);

    } else {

         restore_current_blog();
         return "dB tables could not be restored.\n". $db_restore_success;

    }

    if ( $uploads_restore_success  != "OK" ) {
         restore_current_blog();
         return "UPLOADS directory contents could not be restored.";
    }

    chdir ( $pwd );            // restore working directory

    restore_current_blog();

    return "Done."; 

}


//----------------------------------------------------------
// Builds and executes the mysql command to restore the dumpfile.sql 
// of the backup set.

function cuabr_restore_db_as ( $from_blog_id, $to_blog_id, $nonce_dir, $tmp_untar_dir) {

    $cuabr_mysql          = get_site_option( 'cuabr_mysql', 'undefined');
    if ( ! is_executable( $cuabr_mysql ) ) {
         return ( $cuabr_mysql  . " command is not executable");
    }

    // Does this dump file belong to source blog's tables?
    // The dump file should contain lines like
    //         -- Host: localhost    Database: wpms
    //         DROP TABLE IF EXISTS `wpms_10_links`
    //         CREATE TABLE `wpms_10_links`
    //         INSERT INTO `wpms_10_links`
    //         . . .
    //         DROP TABLE IF EXISTS `wpms_10_wpgmza`
    //         CREATE TABLE `wpms_10_wpgmza`
    //         INSERT INTO `wpms_10_wpgmza`
    // In the example above "_10_"s in table names mean that the corresponding 
    // table belongs to blog with id 10.
    // Therefore before restoring the database, we need to make sure
    // the blog_id's in the table names mentioned in the dump file and the blog 
    // for which we shall be restoring tables do match.

    $dump_file = $tmp_untar_dir . "/dumpfile.sql";
    if ( ! file_exists( $dump_file ) ) {
       return ("The mysql dump file cannot be found in the backup set!");
    }

    $db_ok = cuabr_check_database_name ( DB_NAME, $dump_file );
    if ( $db_ok != "OK" )  {
       return ("The mysql dump file does not belong to the database of this wordpress site!");
    }

    list( $tables_ok, $dump_is_for_network) = cuabr_check_table_blogids ( $from_blog_id, $dump_file ); 

    if ( ( $from_blog_id == 1 ) || ( $to_blog_id == 1 ) || $dump_is_for_network ) { 
        return ( "You can not use this procedure on a WHOLE NETWORK!" );
    } 

    if ( $tables_ok != "OK" )  {
       return ("The mysqldump file contains modification commands for table(s) which do not belong to the source blog!");
    }

    // Save the blog name for destination blog; this has to be restored
    // after dB restore
    switch_to_blog( $to_blog_id);
    $saved_blog_name = get_bloginfo('name');
    restore_current_blog();
   
    // edit dump file to replace source blog specific references/URLs
    $new_dump_file = cuabr_edit_dumpfile( $from_blog_id, $to_blog_id, $dump_file, $nonce_dir);

    $mysql_cmd  = $cuabr_mysql . " -u " . DB_USER .  " -p" . DB_PASSWORD . " " .  DB_NAME . " < " . $new_dump_file;

    // In case of an error, we will show the command which caused the err but we do not
    // want the user to see the dB passwd
    $mysql_cmd_without_pwd  = $cuabr_mysql . " -u " . DB_USER .  " -p" . "********" . " " .  DB_NAME . " < " . $new_dump_file;

    $completion_code = 9;
    exec ( $mysql_cmd, $output, $completion_code);
    if ( $completion_code != 0 ) {
         return   "Could not execute mysql command! $mysql_cmd_without_pwd";
    } 

    // Set the destination blog's name to saved_blog_name
    switch_to_blog( $to_blog_id);
    update_option("blogname", $saved_blog_name);
    restore_current_blog();

    // cleaning up. Remove the dumpfile.sql file and its edited version; we don't need them anymore
    unlink ( $dump_file );
    unlink ( $new_dump_file );

    return "OK";
}

//----------------------------------------------------------
// replaces references to source blog with references to destination blog

function cuabr_edit_dumpfile ( $from_blog_id, $to_blog_id, $dump_file, $nonce_dir)  {
    //     In the $dump_file :
    //        Replace all strings like "http://wpserver.com/from_blog/..."
    //             with "http://wpserver.com/to_blog/..."
    //        Replace all table names like "wpms_10_xyz" with "wpms_31_xyz"
    //             where "wpms" is the table prefix for WP site and
    //             "10" and "31" are blog_id's for from_blog and to_blog
    //             repectively.
   
    // What are the full urls for source and destination blogs?
    switch_to_blog ( $from_blog_id);
    $from_url = get_bloginfo( "url" ) . "/"; 
    restore_current_blog();
    switch_to_blog ( $to_blog_id);
    $to_url = get_bloginfo( "url" ); 

    // get rid of "http[s]:" from to_url to get replacing to_url
    $replacing_to_url = preg_replace( "^http(s?):^i", "", $to_url);
    restore_current_blog();
  
    $fh = fopen( $dump_file, "r"); 
    $new = fopen( $nonce_dir."/tmp.sql", "w");

    // Build a regexp for replacements
    // get rid of http[s]
    $regexp = preg_replace( "^http(s?):^", "", $from_url);

    // get rid of any "/" at the end
    $regexp = preg_replace( "^/$^",        "", $regexp);

    // add "non-word stop" to regexp
    $regexp = $regexp . "([\'/])";

    $regexp_for_table_names = "^".$table_prefix."_".$from_blog_id."_^";
    while ( ( $dump_line = fgets( $fh ) ) !== false ) {
          $new_line = preg_replace ( "^" . $regexp . "^i", $replacing_to_url.'$1', $dump_line);
          $new_line = preg_replace ( $regexp_for_table_names, $table_prefix."_".$to_blog_id."_", $new_line);
          fputs( $new, $new_line);
    }
    fclose ($fh);
    fclose ($new);

    return ( $nonce_dir . "/tmp.sql" ); 
 
}

//----------------------------------------------------------
// Moves the 'uploads' dir contents from the temporary untar directory
// to its real place. Old contents of the blog's 'uploads' directory
// are not deleted but identical named files/directories will be overwritten

function cuabr_restore_uploads_as ( $from_blog_id, $to_blog_id, $nonce_dir, $tmp_untar_dir) {

    switch_to_blog( $to_blog_id );
    $upload_dir_array = wp_upload_dir();
    $upload_dir_path = $upload_dir_array[ 'basedir' ];
    restore_current_blog();
 
    // The "--backup" parameter of the "mv" command is important!
    // If this param is not used, mv will deny overwriting non-empty directories.
    // the "backup" param will result in a set of non-empty backup directories 
    // all with names ending with "~". i.e. if there is a non-empty dir called
    // "dirA", and another "dirA" is to be moved onto it; the mv command will
    // first rename "dirA" to "dirA~" and then perform the expected mv operation.
    // After we are finished moving files/dirs; we should delete "*~".

    // Check if there are any files or directories under the destination
    // directory which are NOT owned by www-data 

    $find_cmd = "/usr/bin/find $upload_dir_path \! -user ".get_current_user();

    exec ( $find_cmd, $output);
    // if there is any output, uploads directory will not be restored
    if ( count( $output) > 0 ) {
           return "There are files/directories NOT OWNED by the web server user. Backup-set will not be restored.";
    }

    $mv_cmd = "/bin/mv -f --backup " . $tmp_untar_dir . "/* " . $upload_dir_path;

          $completion_code = 9;
          exec ( $mv_cmd, $output, $completion_code);
          if ( $completion_code != 0 ) {
              return   "Could not execute mv command! $mv_cmd</font>";
          } 

          $find_cmd = '/usr/bin/find ' . $upload_dir_path . ' -name "*~" -exec /bin/rm -r {} \;';

          system( $find_cmd);

    return "OK";
}
