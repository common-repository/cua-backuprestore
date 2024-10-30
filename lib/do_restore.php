<?php
function cuabr_do_restore (  $blog_id, $backup_dir, $backup_file, $nonce_dir ) {

    // Restores a backup set onto the blog's uploads dir and dB tables
    // for the site $site. If this installation is a multisite WP,
    // then network admin can backup everything ($site = 1 meaning "All Sites") or
    // select a site to backup.

    // Other users (blog admins) can choose from the backup sets of their own blogs.

    // The restore process:
    //  1. Check if our nonce dir is really there ( /tmp/cua-XXXXXXXX )
    //     Make sure that the destination blog type  and the type of backup-set 
    //     match each other. In other words; if the user wants to restore
    //     a backup-set of a single blog, then the destination should 
    //     also be a single blog (actually; same blog).
    //     The "Restore-As" facility can handle the situation where
    //     Blog-A is to be restored on to Blog-B.
    //     If the user wants to restore a backup-set of the WHOLE NETWORK, 
    //     then the destination should also be the WHOLE NETWORK.
    //   
    //     Then check all files under destination dir are owned by
    //     www-data or its equivalent. If not, issue an error. 
    //  2. create a temp directory for the current blog in the uploads dir
    //     Typically: /var/www/wp-content/plugins/cua-backup-restore/cua-backups/nnn
    //  3. untar the blog's backup set into this directory
    //  4. Execute a mysql command to restore tables from the dumpfile.sql 
    //     which is in the tar ball and remove the dump file.
    //  5. Move the remaining contents of the tmp directory to
    //     the blog's (or network's) uploads directory.

    global $wpdb; // we need to access the dB
   
    if ( ! is_dir( $nonce_dir ) )  {
        print   "<font style='font-size:18px;color:red'>Error: Invalid entry point; you are not authorized to do this!</font>";
        wp_die();
    }

    // Is the user an administrator of blog $blog_id?
    switch_to_blog ( $blog_id);

    if ( ! current_user_can("manage_options")) {
        restore_current_blog();
        return ("You are not an administrator of this blog.");
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

    
    $db_restore_success  = cuabr_restore_db ( $blog_id, $nonce_dir, $tmp_untar_dir);

    if ( $db_restore_success  == "OK" ) {

         $uploads_restore_success = cuabr_restore_uploads ( $blog_id, $nonce_dir, $tmp_untar_dir);

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

function cuabr_restore_db ( $blog_id, $nonce_dir, $tmp_untar_dir) {

    $cuabr_mysql          = get_site_option( 'cuabr_mysql', 'undefined');
    if ( ! is_executable( $cuabr_mysql ) ) {
         return ( $cuabr_mysql  . " command is not executable");
    }

    // Does this dump file belong to site's database and tables?
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
    // 
    // If the dump file is for the whole network ( that is; blog_id=1) then we should
    // only check the database name of the "-- Host: ..... Database: xyz" line.

    $dump_file = $tmp_untar_dir . "/dumpfile.sql";
    if ( ! file_exists( $dump_file ) ) {
       return ("The mysql dump file cannot be found in the backup set!");
    }

    $db_ok = cuabr_check_database_name ( DB_NAME, $dump_file );
    if ( $db_ok != "OK" )  {
       return ("The mysql dump file does not belong to the database of this wordpress site!");
    }

 
    list( $tables_ok, $dump_is_for_network) = cuabr_check_table_blogids ( $blog_id, $dump_file ); 

    if ( ( $blog_id != 1 ) && ( $dump_is_for_network == true ) ) {   // Restoring the whole network
                                                              // but dump is for a blog
        return ( "You cannot restore a WHOLE NETWORK onto a blog!" );
    } 

    if ( ( $blog_id == 1 ) && ( $dump_is_for_network == false ) ) {    // Restoring a blog but
                                                                       // dump is for whole network
        return ( "You can not restore a blog onto the WHOLE NETWORK!" );
    } 


    if ( $tables_ok != "OK" )  {
       return ("The mysqldump file contains records for table(s) which do not belong to this blog!");
    }

    $mysql_cmd  = $cuabr_mysql . " -u " . DB_USER .  " -p" . DB_PASSWORD . " " .  DB_NAME . " < " . $dump_file;
    // we keep a copy of the mysql command without the password
    // because in case of an error, we display this mysql cmd without the pwd
    // in the error message so that the users don't see the dB password
    $mysql_cmd_without_pwd  = $cuabr_mysql . " -u " . DB_USER .  " -p" . "********" . " " .  DB_NAME . " < " . $dump_file;

          $completion_code = 9;


          exec ( $mysql_cmd." 2>&1", $output, $completion_code);
          if ( $completion_code != 0 ) {
              $out = implode( "<br>", $output);
              return   "Could not execute mysql command! $mysql_cmd_without_pwd Output was: $out";
          } 

    // cleaning up. Remove the dumpfile.sql file; we don't need it anymore
    unlink ( $tmp_untar_dir . "/dumpfile.sql" );

    return "OK";
}

//----------------------------------------------------------
// Traverses the first few lines of the mysql dump file
// searching for the "-- Host: localhost    Database: wpms" line.
// compares the db name found on this line and db name 
// in WP configuration. If they don't match, it means
// the backupset does not belong to this WP site.

function cuabr_check_database_name ( $dbname, $dumpfile) {

    // checks whether the -- Host: localhost    Database: wpms line
    // of the dump file matches our DB
    $hnd = fopen( $dumpfile, "r");
    $n = 0;
    if ( $hnd ) {
        while (( $line = fgets( $hnd)) !== false ) {
           $n = $n + 1;
           if ( $n > 20 ) {   // no need to search for "Database:" beyond
                              // the 20th line. This line should be found
                              // in the first few lines of the dumpfile.
              fclose ($hnd);
              return "Database name not found in the dump file.";
           }
           if ( preg_match( '/^-- Host:.*Database:\s(.*)$/', $line, $matches) ) {
              if ( trim( $matches[1] ) == $dbname ) {
                   fclose ($hnd);
                   return "OK";
              }
              return "Database name in the dump file and the WP setup do not match!";
           }
        }
    } else {
         return "dumpfile cannot be opened";
    }
}

//----------------------------------------------------------
// Traverses the whole  mysql dump file
// searching for  "DROP TABLE, CREATE TABEL, INSERT INTO" lines.
// All the table names mentioned in these lines should be 
// in the form "prefix_n_xyz" where prefix is the table prefix set
// in WP config; "n" is the blog's numerical ID, "xyz" part
// is not significant. 
// This function checks whether all "n"s in those statements are
// same and equal to the function call param.

function cuabr_check_table_blogids ( $blog_id, $dumpfile) {
   
    global $wpdb;
    
    // If an SQL statement like "DROP TABLE $table_prefix_posts" exists in the dump file
    // then this dump file is a dump of the WHOLE NETWORK
    // Blog dumps contain only SQL statements for $table_prefix_NN_xyz like tabled
    $table_prefix = $wpdb -> prefix;

    // What is the table prefix for the main site
    switch_to_blog( 1);
    $main_site_table_prefix = $wpdb -> prefix;
    restore_current_blog();

    $main_site_sample_table = $main_site_table_prefix."posts";

    $dump_is_for_network = false;
    $there_is_a_foreign_blog   = false;
    $hnd = fopen( $dumpfile, "r");
    if ( $hnd ) {

        while (( $line = fgets( $hnd)) !== false ) {

           if ( preg_match( '/^(INSERT INTO|DROP TABLE|CREATE TABLE|LOCK TABLE)\s*`'.$table_prefix.'_(\d+)_/', $line, $matches)) {
              if ( trim( $matches[2] ) != $blog_id ) {
                  $there_is_a_foreign_blog   = true;
              }
           }

           if ( preg_match( '/^(INSERT INTO|DROP TABLE|CREATE TABLE|LOCK TABLE)\s*`'.$main_site_sample_table.'/i', $line)) {
              $dump_is_for_network = true;
           }

        }

        if( $there_is_a_foreign_blog && !$dump_is_for_network ) {  // Networks dumps WILL contain foreign tables!
                   fclose ($hnd);
                   return array( "Foreign blog_id in dump file!", "");
        }

        fclose ($hnd);
        return array( "OK", $dump_is_for_network);   // all n's in _n_ are same with our blog_id, success!

    } else {

        fclose ($hnd);
        return array( "dumpfile cannot be opened", "");

    }
}

//----------------------------------------------------------
// Moves the 'uploads' dir contents from the temporary untar directory
// to its real place. Old contents of the blog's 'uploads' directory
// are not deleted but identical named files/directories will be overwritten

function cuabr_restore_uploads ( $blog_id, $nonce_dir, $tmp_untar_dir) {

    $upload_dir_array = wp_upload_dir();
    $upload_dir_path = $upload_dir_array[ 'basedir' ];
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

    $find_cmd = '/usr/bin/find ' . $upload_dir_path . ' -name "*~" -exec /bin/rm -r {} \;';
    system( $find_cmd);

    if ( $completion_code != 0 ) {
              return   "Could not execute mv command! $mv_cmd</font>";
    } 

    return "OK";
}
