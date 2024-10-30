<?php
function cuabr_do_backup ( $user_id, $blog_id, $nonce_dir, $backup_root, $backup_dir ) {

    // Does the actual backup of  db+contents)
    // for the site $site. If this installation is a multisite WP,
    // then network admin can backup everything ($site = 1 meaning "All Sites") or
    // select a site to backup.

    // Other users (blog admins) can choose from their own sites to backup.

    // The backup process:
    //  1. Check if our nonce dir is really there ( /tmp/cua-XXXXXXXX )
    //  2. create a directory for the current blog in the uploads dir
    //     Typically: /var/www/wp-content/plugins/cua-backuprestore/cua-backups/nnn
    //  3. dump mysql database (All Sites) or blog tables (for selected blog)
    //  4. Create an "cua-xxxx.info" file in this directory. The "cua-xxxx.info"
    //     should contain
    //           i) the name and ID# of the blog to which the backup-set belongs to
    //          ii) The data and time backup was made
    //         iii) The username and IP address used when the backup
    //              was made.
    //  5. tar the blog's upload directory,the mysql dump file 
    //     and the info file to this directory

    global $wpdb; // This is how you get access to the database

  
    if ( ! is_dir( $nonce_dir ) )  {
        print   "<font style='font-size:18px;color:red'>Error: Invalid entry point; you are not authorized to do this!</font>";
        wp_die();
    }

    switch_to_blog( $blog_id);

    // site options stuff
    $cuabr_mysql          = get_site_option( 'cuabr_mysql', 'undefined');
    $cuabr_mysqldump      = get_site_option( 'cuabr_mysqldump', 'undefined');
    $cuabr_max_backup_age = get_site_option( 'cuabr_max_backup_age', 0);

    // Create a directory for this site under the $backup_dir directory.
    // Mysql dumps and uploaded files  will be tar'red here

    if ( ! is_dir( $backup_dir ) ) {
        $completion_code = 9;
        $cmd =   "mkdir -p " . $backup_dir;
        exec ( $cmd, $output, $completion_code);
        if ( $completion_code != 0 ) {
               print   "<font style='font-size:18px;color:red'>Error: Could not create $backup_dir!</font>";
               wp_die();
        } 
        // create an empty index.html file here to avoid directory listing
        file_put_contents  ( $backup_dir . "index.html", " ");
    }

    // if "this-blog" or "WHOLE NETWORK" (all-blogs) is selected (i.e. $blog_id == 1),  
    // we shall dump the whole database

    // contents stuff
    //---------------
          $home_path = get_home_path();

          // If necessary, create $backup_dir/$blog_id and check if it is writable
          if ( ! is_writable( $backup_dir ) ) {
             $http_user = get_current_user();
             add_settings_error(
                'Error',                     // Setting title
                '01',                        //error ID
                $backup_dir . ' does not exist or is not writable by web server user "'. $http_user.'"',     // Error message
                'error'                         // Type of message
             );
             wp_die();
          }

          // getting ready to create a tar command
                $source      = $home_path . "wp-content/uploads";
                if ( $blog_id != 1 ) {    // If not "whole network" 
                    $source = $home_path . "wp-content/uploads/sites/" . $blog_id;
                }

                // make sure that $source directory exists and writable
                if ( ! is_dir ( $source ) ) {
                   mkdir ( $source);
                }

                $date_part = current_time("YmdHis");

                $site_part = $blog_id;
                $prefix = "cua-" . $date_part . "-" . $site_part;
                $completion_code = 9;
                exec( "/bin/mktemp  --tmpdir=" . $backup_dir . " " . $prefix ."-XXXXXXXXXX.tar",
                                   $output, $completion_code );
                if ( $completion_code != 0 ) {
                     print "<font style='color:red; font-size:16px'>Error: /bin/mktemp command could not be executed!</font>";
                     wp_die();
                }

                $tar_filename = basename($output[0]);
    // mysqldump stuff 
    //----------------
          if ( $site != 1 ) {  // if not "whole network" 

             // get the table names like "wpms_9_*" for blog with siteID=9
             $show_tables_cmd = "echo 'show tables like \"".DB_NAME."\_".$blog_id."\_%\"' | " . $cuabr_mysql . " -u " . DB_USER . " -p" . DB_PASSWORD . " " . DB_NAME;

             exec ( $show_tables_cmd, $output);

             // dump the tables of the selected site
             $mysqldump  = $cuabr_mysqldump . " -u " . DB_USER .  " -p" . DB_PASSWORD . " " .  DB_NAME  ;
             $mysqldump_without_pwd = $cuabr_mysqldump . " -u " . DB_USER .  " -p" . "*******" . " " .  DB_NAME  ;

             for ( $i = 2; $i < count ( $output ); $i++ ) {  // Skip the first two lines
                 $l = trim ( $output[$i] );
                 $mysqldump .= " " . $l;
                 $mysqldump_without_pwd .= " " . $l;
             }

             $mysqldump .= " > " . $source . "/dumpfile.sql";
             $mysqldump_without_pwd .= " > " . $source . "/dumpfile.sql";

          } else {

             // dump the whole database
             $mysqldump  = $cuabr_mysqldump . " -u " . DB_USER .  " -p" . DB_PASSWORD . " " .  DB_NAME . " > " . $source . "/dumpfile.sql";
             $mysqldump_without_pwd  = $cuabr_mysqldump . " -u " . DB_USER .  " -p" . "********" . " " .  DB_NAME . " > " . $source . "/dumpfile.sql";

          }

          $completion_code = 9;
          exec ( $mysqldump, $output, $completion_code);
          if ( $completion_code != 0 ) {
              print   "<font style='font-size:18px;color:red'>Error: Could not execute mysqldump command! $mysqldump_without_pwd</font>";
              wp_die();
          } 


          // Create the info file

             // generate info file name
             $info_filename = preg_replace ( "/\.tar$/", ".info", $tar_filename );

             switch_to_blog( $blog_id);
             $sw_blog_name = get_bloginfo();
             $sw_blog_details_obj = get_blog_details( $blog_id);
             $sw_blog_domain = $sw_blog_details_obj -> domain;
             $sw_blog_path   = $sw_blog_details_obj -> path;

             $info_text =                "SiteID : "    . $blog_id . "\n";
             $info_text = $info_text . "SiteName : "    . $sw_blog_name . "\n";
             $info_text = $info_text . "SitePath : "    . $sw_blog_domain . $sw_blog_path . "\n";
             $info_text = $info_text . "Backed up at : "        . $date_part . "\n";
             $info_text = $info_text . "Backed up by : "        . $user_id . "\n";
             $info_text = $info_text . "From IP : "     . $_SERVER['REMOTE_ADDR'] . "\n";
 
             $file_put = file_put_contents ( $backup_dir . $info_filename, $info_text );
             if ( ! $file_put ) {
                print "<font style='color:red; font-size:16px'>Error: Cannot write to $backup_dir" . $info_filename . "!</font>";
                wp_die();
             }
              
             // Also put the info file into the tar source dir so that it is included in the tarfile.
             // put the info file with ".txt" extension so that it is not excluded by the tar command
             $file_put = file_put_contents ( $source . "/info.txt", $info_text );
             if ( ! $file_put ) {
                print "<font style='color:red; font-size:16px'>Error: Cannot write to $source" . "/info.txt" . "!</font>";
                wp_die();
             }

          // Now run the tar command

          $pwd = getcwd();

          chdir( $source);

          $tar  = "tar -c -C " . $source . 
                  " --exclude cua-backups" .
                  " --exclude cua-*.tar --exclude cul-*.tar --exclude cua-*.info --exclude cul-*.info -f " .
                   $backup_dir . $tar_filename . " .";
          $completion_code = 9;
          exec ( $tar, $output, $completion_code);
          chdir($pwd);
          //CUA unlink( $source . "/" . $info_filename . ".txt" );  // Cleanup! we don't need this file anymore
          unlink( $source . "/dumpfile.sql" );                // Cleanup! we don't need this file anymore
          if ( $completion_code != 0 ) {
              print   "<font style='font-size:18px;color:red'>Error: Could not execute tar command! $tar</font>";
              wp_die();
          }

}
