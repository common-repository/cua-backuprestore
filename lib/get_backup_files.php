<?php

function  cuabr_get_backup_files ( $backup_dir, $user_id, $blog_id) {

// traverses the backup-set files from the backups/$blog_id directory.
// and builds an sorted array of info texts (sorted on files' data/time)
//
// an array entry looks like:
//  fileName|userName|blogPath|data time|IPAddr
//           fileName is the tar file's name
//           userName is the full name of the user who backed up the blog

     if ( $handle = opendir($backup_dir )) {
         while (false !== ($file = readdir($handle))) {
             if ( ('.' === $file) || ('..' === $file) ) continue;

                // Does the filename look like: "cua-20150811114725-1-9kRubAojaB.info" or
                //                              "cul-20150811114725-1-9kRubAojaB.info" (uploaded file)
                if ( preg_match( '/(^cu[al]-\d*-\d*-\w*)(\.info$)/', $file, $matches) ) {
                     $fname = $matches[1];
                     $fext = $matches[2];
                        // open the info file and get details
                        $fh = fopen( $backup_dir .  $file, "r" );
                        if ( ! $fh ) {
                           print   "<font style='font-size:18px;color:red'>Error: Cannot open $file!</font>";
                           wp_die();
                        }
                        while ( ( $info_line = fgets( $fh) ) !== false ) {
                              list ( $info_var, $info_value ) = explode (":", $info_line);
                              $info_var   = trim($info_var);
                              $info_value = trim($info_value);
                              switch ( $info_var) {
                                    case "SiteID":
                                      $info_site_id  = $info_value;
                                      break;
                                    case "SiteName":
                                      $info_site_name  = $info_value;
                                      break;
                                    case "SitePath":
                                      $info_site_path  = $info_value;
                                      break;
                                    case "Backed up at":
                                      $info_backup_date  = $info_value;
                                      break;
                                    case "Backed up by":
                                      $info_backup_user  = $info_value;
                                      break;
                                    case "From IP":
                                      $info_ip  = $info_value;
                                      break;
                              }

                        }
                        fclose($fh);
                        if ( $info_site_id != $blog_id ) {
                           continue;   // loop if site_ids do not match
                        }
                        $info_date   = substr( $info_backup_date, 0, 8);
                        $info_time   = substr( $info_backup_date, 8, 6);
                        $info_date   = substr( $info_date, 0, 4) . "/" .
                                       substr( $info_date, 4, 2) . "/" .
                                       substr( $info_date, 6, 2);
                        $info_time   = substr( $info_time, 0, 2) . ":" .
                                       substr( $info_time, 2, 2) . ":" .
                                       substr( $info_time, 4, 2);
                        $user_info   = get_userdata( $info_backup_user);
                        $info_user_name = "Unknown";
                        if ( $user_info ) $info_user_name = $user_info -> user_login;

                        // Does the user have administrative rights on the site to which
                        // this backup set belongs?

                        // Display this backup-set line ONLY if the curent user is an
                        // admin of the blog to which this backup-set belongs.
                        // Network admins can see all...
                        switch_to_blog ( $info_site_id);
                        if ( current_user_can( "manage_options") ) {
                              // put date-time to position 0 so that we can reverse
                              // sort the array on "date-time"
                              $res[] = $info_date . " " . $info_time . "|" .
                                       $fname . ".tar" . "|" . 
                                       $info_backup_user . "|" .
                                       $info_user_name . "|" .
                                       $info_site_id . "|" .
                                       $info_site_path . "|" .
                                       $info_ip;
                        }
                        restore_current_blog();
                } 
         }
         closedir($handle);
     }

     if ( empty( $res) ) return false;
     rsort( $res, SORT_STRING);

     return ($res);
}
