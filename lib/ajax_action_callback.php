<?php
     $file = "/tmp/cuafile.tar";
function cuabr_action_callback() {

        include( CUABR_PLUGIN_PATH . 'lib/get_backup_files.php' );
        include( CUABR_PLUGIN_PATH . 'lib/build_recents_table.php' );

        // "task" can be "upload", "deletefile", "restorefile", "restorefileas",
        //               "Back up", "recents"
        $task                   = $_POST['task'];

        // Functions called by Ajax calls

        // Task to be performed is received in $_POST['task']
        //      then this core performs the task and
        //      returns a result in $response
        //      if $response == "OK", then it is OK
        //      otherwise it means something went wrong and the reason is in $response

        /*******  task = upload ***************************************/
        if ( $task == "upload" ) { // perform  backup file upload 

           if ( $blog_over_quota ) {
              print  "Disk usage over quota";
              wp_die();
           }
           $nonce_dir           = $_POST['nonce_dir'];
           $blog_id             = $_POST['frm_blog_id'];
           $upload_to_blog_id   = $_POST['frm_blog_id'];

           // is the nonce_dir there?
           if ( ! is_dir( $nonce_dir ) )  {
              print   "<font style='font-size:18px;color:red'>Error: 
                       Invalid entry point; you are not authorized to do this!</font>";
              wp_die();
           }

           // calculate the backup_dir for this blog
           $wp_plugins_dir = WP_PLUGIN_DIR;
           $wp_plugins_url = WP_PLUGIN_URL;
           $backup_dir = $wp_plugins_dir."/cua-backuprestore/cua-backups/" . $blog_id . "/";

           switch_to_blog( $upload_to_blog_id);
           $uploads_dir_arr  = wp_upload_dir();
           $destination_dir  = $uploads_dir_arr['basedir'];
           restore_current_blog();

           // Extensions of uploaded files must be in the format ".partnn" where
           // nn is the seq. number of the file in he split file set.
           // e.g. myfile.tar.part01, myfile.tar.part02 etc.

           // how many files were uploaded?
           $n_files = 0;
           foreach ( $_FILES['files']['name'] as $i => $name ) {
              $n_files++;
           }

           // was this a single or multiple file upload?
           $single_file_upload = true;
           if ( $n_files > 1 ) $single_file_upload = false;

           // start with clean nonce_dir
           $rm_cmd = "/bin/rm -r " . $nonce_dir . "/* 2>/dev/null";
           system( $rm_cmd );

           // loop all files
           $count = 0;
           foreach ( $_FILES['files']['name'] as $i => $name ) {
                   // if file was not uploaded then report it, could be a size vs php.ini issue
                   if ( $_FILES['files']['error'][$i] ) {
                       // Get the reason of upload failure
                       $reason = cuabr_get_upload_err_msg( $_FILES['files']['error'][$i] );
                       echo "Error uploading file ".$_FILES["files"]["name"][$i]."<br>".$reason;
                       wp_die();
                   }
                   if ( !is_uploaded_file($_FILES['files']['tmp_name'][$i]) ) {
                       // Get the reason of upload failure
                       $reason = cuabr_get_upload_err_msg( $_FILES['files']['error'][$i] );
                       echo "Error uploading file ".$_FILES["files"]["name"][$i]."<br>".$reason;
                       wp_die();
                   } else {
                   }

                   // If a single file is uploaded the name should be 
                   //    of the form "filename.tar"
                   // If multiple files are uploaded, the names should of the form
                   //    filename.tar.partNN
                   $name_ok = cuabr_check_uploaded_file_name ( $_FILES["files"]["name"][$i], $single_file_upload );
                   if ( ! $name_ok ) {
                       echo "Invalid file type: ".$_FILES["files"]["name"][$i]."<br>";
                       wp_die();
                   }

                   // now we can move uploaded files to $nonce_dir
                   // nonce_dir's are temporary and deleted every once in a while
                
                   if ( move_uploaded_file($_FILES["files"]["tmp_name"][$i], 
                                        $nonce_dir."/".$_FILES["files"]["name"][$i] ) ) {
                       $count++;

                   } else {
                 
                       // Get the reason of upload failure
                       $reason = cuabr_get_upload_err_msg( $_FILES['files']['error'][$i] );
                       echo "Error uploading file ".$_FILES["files"]["name"][$i]."<br>".$reason;
                       wp_die();
                   }
           }

           // now,  a single tar file or all file parts of a split tar file are uploaded successfully
           // we need to check if the tar file is sane; i.e
           //   - check if it is really a tar file (single or split parts)
           //   - check if all the files in the tar file are tarred with relative directories.
           //       None of the filenames in tar file should start with "/"; otherwise
           //       it is possible to overwrite /var/www based files while untarring (very dangerous).
           //   - check if the  tar file contains any PHP files ( it shouldn't)
           //   - Does this uploaded backup set belongs to the blog we are working on?

           $result = cuabr_check_tarfile_sanity ( $nonce_dir,  $single_file_upload, $blog_id);

           if ( $result != "OK" ) {
              echo $result;
              // Delete uploaded file(s) since they are no good
              $files = scandir ( $nonce_dir);   // returns "." and ".." as well
              foreach ( $files as $f) {
                if ( preg_match('/\.tar$/', $f ) ) {    // if the filename ends with ".tar"
                   unlink ( $f );
                }
              }
              wp_die(); 
           }
      
           // generate a proper file name for the uploaded file and move it to backups dir

           // The only .tar file under the nonce_dir is our tar file
           $files = scandir ( $nonce_dir);   // returns "." and ".." as well
           foreach ( $files as $f) {
             if ( preg_match('/\.tar$/', $f ) ) {    // if the filename ends with ".tar"
                $tar_file = $f;
                break;
             }
           }

           list ( $new_name, $date_part) = cuabr_generate_backup_filename ( "cul", $backup_dir, $upload_to_blog_id);
           
           rename ( $nonce_dir . "/" . $tar_file,   $backup_dir . $new_name );

           // Create an info file for this backup file

               // get the blog name etc.
               switch_to_blog( $upload_to_blog_id);
               $sw_blog_name        = get_bloginfo();
               $sw_blog_details_obj = get_blog_details( $upload_to_blog_id);
               $sw_blog_path        = $sw_blog_details_obj -> path;
               $sw_blog_domain      = $sw_blog_details_obj -> domain;
               restore_current_blog();

           $info_filename = $backup_dir . basename( $new_name, "tar") . "info";

           $info_text = "SiteID : " . $upload_to_blog_id . "\n" . "SiteName : " . $sw_blog_name;
           $info_text = $info_text . "\nSitePath : " . $sw_blog_domain . $sw_blog_path . "\n" . "Backed up at : " . $date_part;
           $info_text = $info_text . "\nBacked up by : " . get_current_user_id() . "\nFrom IP : " . $_SERVER['REMOTE_ADDR'];
           file_put_contents( $info_filename, $info_text);

           echo "Uploaded $count file(s)";
           wp_die(); 
        }   // end of if task == upload

        /*******  task = deletefile ***************************************/
        if ( $task == "deletefile" ) { 
           $backup_file  = $_POST['backup_file'];
           $nonce_dir    = $_POST['nonce_dir'];
           $backup_dir   = $_POST['backup_dir'];

           // is the nonce_dir there?
           if ( ! is_dir( $nonce_dir ) )  {
              print   "<font style='font-size:18px;color:red'>Error: 
                       Invalid entry point; you are not authorized to do this!</font>";
              wp_die();
           }
          
           $file_to_delete = $backup_dir . $backup_file;
           $tar_deleted  = false;
           $info_deleted = false;
           $tar_deleted = unlink ($file_to_delete);

           // Delete .info file of the backupset as well (if there is any)
           $file_to_delete = preg_replace ( '/\.tar$/', '.info', $file_to_delete);

           $info_deleted = unlink ($file_to_delete);

           sleep(1);

           if ( $tar_deleted && $info_deleted ) {  // Success, report this to Ajax caller
                echo "deleted";
           } else {
                echo "Could not delete tar file and/or info file";
           }
           wp_die(); // proper way of ending ajax callbacks
        }

        /************** NOT USED ***************************
        if ( $task == "download" ) { // perform  backup file download 
           wp_die(); // proper way of ending ajax callbacks
        }
        ****************************************************/

        /************** NOT USED ***************************
        if ( $task == "TEST" ) { // perform power on self tests
           cuabr_self_test();
           wp_die();
        }

        /*******  task = Back up ***************************************/
        if ( $task == "Back up" ) {
           if ( $blog_over_quota ) {
              print  "Disk usage over quota";
              wp_die();
           }
           // pass control to backup process
           $nonce_dir              = $_POST['frm_nonce_dir'];
           $user_id                = $_POST['user_id'];
           $blog_id                = $_POST['blog_id'];
           $backup_root            = $_POST['frm_backup_root'];
           $backup_dir             = $backup_root . $blog_id ."/";
           cuabr_do_backup ( $user_id, $blog_id, $nonce_dir, $backup_root, $backup_dir );

           print "Done.";
           wp_die(); // proper way of ending ajax callbacks
        }

        /*******  task = restorefile ***************************************/
        if ( $task == "restorefile" ) {
           // pass control to restore process
           $nonce_dir              = $_POST['nonce_dir'];
           $blog_id                = $_POST['blog_id'];
           $backup_dir             = $_POST['backup_dir'];
           $backup_file            = $_POST['backup_file'];

           $result = cuabr_do_restore ( $blog_id, $backup_dir, $backup_file, $nonce_dir );

           print $result;
           wp_die(); // proper way of ending ajax callbacks
        }

        /*******  task = restorefileas ***************************************/
        if ( $task == "restorefileas" ) {
           // Pass control to RestoreAs process
           $nonce_dir              = $_POST['nonce_dir'];
           $from_blog_id           = $_POST['from_blog_id'];
           $to_blog_id             = $_POST['to_blog_id'];
           $backup_dir             = $_POST['backup_dir'];
           $backup_file            = $_POST['backup_file'];

           $result = cuabr_do_restoreas ( $from_blog_id, $to_blog_id, $backup_dir, $backup_file, $nonce_dir );

           print $result;
           wp_die(); // proper way of ending ajax callbacks
        }

        /*******  task = recents ***************************************/
        // builds the recent backups table.
        if ( $task == "recents" ) {
           // Get list of recent backup sets

           $nonce_dir              = $_POST['frm_nonce_dir'];
           $blog_id                = $_POST['frm_blog_id'];
           $user_id                = $_POST['frm_user_id'];
           $backup_root            = $_POST['frm_backup_root'];
           $backup_dir             = $backup_root . $blog_id . "/";
           $backupdir_url_base     = $_POST['frm_backupdir_url_base'];
           // Change current blog to be sure
           switch_to_blog( $blog_id);
           
           $backupset_list = cuabr_get_backup_files ( $backup_dir, $user_id, $blog_id );
           $recents_table = cuabr_build_recents_table( $backup_dir, $backupdir_url_base,
                                                       $backupset_list,  
                                                       $nonce_dir);
           print $recents_table; 

           wp_die(); // proper way of ending ajax callbacks
        }

        wp_die();
}


function cuabr_check_tarfile_sanity ( $nonce_dir,  $single_file_upload, $blog_id) {
   // checks whetner the tar file or tar file parts in nonce_dir are sane
   // Sanity means:
   //     - The uploaded file or the combined parts must be a valid tar file
   //     - The tar file must not contain any php files
   //     - The files/dirs in the tar ball should be all tarred in
   //       relative directories. i.e none of the names should start with "/"
   //     - the info file must contain relevant to this blog

   if ( $single_file_upload ) {

      // what is the file contents? UNIX "file" command will tell us.
      $files = scandir ( $nonce_dir);   // returns "." and ".." as well
      $file = $files[2];
      // check if the file is a tar file
      $pwd = getcwd();  // save the working dir
      chdir ( $nonce_dir );
      exec ("file ".$file, $output);
      if ( strpos ( $output[0], "tar archive")  >= 0 ) {
         // file is a tar file
       
         // are all files in tar ball relative to ./ ?  Any PHP files?
         exec ( "tar tf ".$file , $tar_file_list);
         foreach ( $tar_file_list as $line) {
                 if ( ! preg_match( "/^\.\//", $line ) ) {  
                    return "At least one file in tar ball is not tarred relatively!";
                 }
         }
         foreach ( $tar_file_list as $line ) {
                 if ( preg_match( "/\.php\d*$/i", $line ) ) {  
                    return "File contains PHP file(s). This not allowed!";
                 }
         }

      } else {
         return "File is NOT a tar file";  
      }
      
   } else {    // multipart tar file chunks are uploaded

     //  Note: The Linux command to split file: 
     //            split -a 3 -d -b 2M  bigfile.tar file.tar.part

     // Join the chunks of the uploaded files into one file and untar them

     $pwd = getcwd();  // save the working dir
     chdir ( $nonce_dir );
     exec ("cat  *part* | tar tf - --ignore-zeros", $tar_file_list);
     foreach ( $tar_file_list as $line) {
                 if ( preg_match( "/^\//", line ) ) {  
                    return "At least one file in tar ball is not tarred relatively!";
                 }
     }

     foreach ( $tar_file_list as $line ) {
                 if ( preg_match( "/\.php\d*$/i", line ) ) {  
                    return "tar file contains PHP file(s). This not allowed!";
                 }
     }

     // Uploaded file parts look OK. now join them into a single tar file and move
     // it the backup_dir
     exec( "/bin/mktemp  --tmpdir=" . $backup_dir . " " . "unsplit-XXXXXXXXXX.tar",
            $output, $completion_code );

     $file = basename($output[0]);
     exec ("cat  *part* > $file");  // Thanks to Linux, it will sort the file names
   }

   // Check if the backup set belongs to the blog we are working on
   // extract the info.txt file from the tar ball and parse the SiteID line
   // it should read :
   //      SiteId : nn
   // where nn is the ID number of the blog we are working on
   
   // extract info file from the tar file
   $tar_cmd = "tar xf " . $file . " ./info.txt --to-stdout";
   exec( $tar_cmd, $output, $completion_code);
   if ( $completion_code != 0 ) {
        return "The tar file does not contain an info.txt file!";
   }
   foreach ( $output as $line) {
      if ( preg_match ( '/SiteID : (\d+)/', $line, $matches) ) {
         $info_blog_id = $matches[1] ;
         if ( $info_blog_id != $blog_id ) {
            // delete the uploaded file
            unlink ( $file );
            return "The backup set file does not belong to this blog!<p>&nbsp;<p>Using <font color=red>\"Restore-As\"</font> might help you.";
         }
      }
   }
   // the caller routine will move this tar file to proper place 
   chdir ( $pwd );
   return "OK";
}


function cuabr_generate_backup_filename ( $type, $backup_dir, $blog_id) {
      // $type is either "cua" for files backed up by this plugin, or,
      //                 "cul" for files uploaded using this plugin
      $date_part = current_time("YmdHis");
      $prefix = $type . "-" . $date_part . "-" . $blog_id;
      $completion_code = 9;

      if ( ! is_dir( $backup_dir )) {
         mkdir ( $backup_dir );
      }
      exec( "/bin/mktemp  --tmpdir=" . $backup_dir . " " . $prefix ."-XXXXXXXXXX.tar",
            $output, $completion_code );
      if ( $completion_code != 0 ) {
            print "<font style='color:red; font-size:16px'>Error: /bin/mktemp command could not be executed!</font>";
            wp_die();
      }

      $backup_filename = basename($output[0]);
      return ( array( $backup_filename, $date_part) );
}

function cuabr_get_upload_err_msg( $php_code ) {

          switch ( $php_code ) {
            case UPLOAD_ERR_INI_SIZE: 
                $message = "The uploaded file exceeds the limits enforced in php.ini of the server"; 
                break; 
            case UPLOAD_ERR_PARTIAL: 
                $message = "The uploaded file was only partially uploaded."; 
                break; 
            case UPLOAD_ERR_NO_FILE: 
                $message = "No file was uploaded."; 
                break; 
            case UPLOAD_ERR_NO_TMP_DIR: 
                $message = "Missing a temporary folder."; 
                break; 
            case UPLOAD_ERR_CANT_WRITE: 
                $message = "Failed to write file to disk. Disk or disk quota may be full."; 
                break; 
            case UPLOAD_ERR_EXTENSION: 
                $message = "File upload stopped by extension."; 
                break; 
          }
      
          return $message;
}

function  cuabr_check_uploaded_file_name ( $file_name, $single_file_upload ) {

    // If a single file is uploaded, the name must be of the form "somefile.tar"
    // If multiple files are uploaded, the name must be of the form "somefile.tar.partNNN"
    //    which indicates parts of split file
    if ( $single_file_upload ) {
       $fname_parts = pathinfo( $file_name );
       if ( $fname_parts['extension'] != "tar" ) {
          return false;
       }
    } else {
      if ( ! preg_match("/tar\.part\d+$/", $file_name ) ) {
         return false;
      }
    }

    return true;

}

function cuabr_self_test() {
    // tests to see all settings are correct
    echo "Ready...";
}

