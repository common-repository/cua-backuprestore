<?php
include( CUABR_PLUGIN_PATH . 'lib/get_upload_limits.php' );
function  cuabr_build_recents_table( $backup_dir, $backupdir_url_base,
                                     $backupset_list, 
                                     $nonce_dir) {
     $cuabr_max_backup_age = get_site_option( 'cuabr_max_backup_age', 0);
     $cuabr_quota          = get_site_option( 'cuabr_quota', 4);
    
     if ( ! empty( $backupset_list) ) {

          $table = "<table id='cuabr_recents'
                                    style='line-height:1.6em'
                                    class='dataTable'
                                    cellspacing='2' cellpadding='2'
                                    width='90%'>
                         <thead>
                             <tr bgcolor=#e0e0e0>
                                 <th align=left>Backed up by</th>
                                 <th align=left>Backup/Upload Date (Server time)</th>
                                 <th align=left>Client IP</th>
                                 <th align=right>File Size ( Kbytes)</th>
                                 <th align=center>Action</th>
                             </tr>
                         </thead>

                         <tbody>";
           if ( ( ! is_numeric( $cuabr_max_backup_age ) ) || ( $cuabr_max_backup_age < 0 ) )  {
                $table = $table .  "<tr><td align=center colspan=5><font color=red>Invalid 
                                    number of days to keep backups!</font></td></tr>";
           } else {
                if ( $cuabr_max_backup_age != 0 ) {
                     $table = $table .  "<tr><td align=center colspan=5><font color=red>Backup 
                              sets older than $cuabr_max_backup_age days are deleted 
                              automatically.</font></td></tr>";
                }
           }

           $current_server_time = current_time( 'Y/m/d H:i:s', 0);
           $blog_name = get_bloginfo();

           $green_flag = false;  // Shall we show any legends at the top
           $red_flag   = false;
           $zebra = 1;
           $disk_used_by_blog = 0;
           foreach ( $backupset_list as $backupset ) {
                   list ( $date_time,
                          $backup_file,
                          $backup_user_id,
                          $backup_user,
                          $backup_blog_id,
                          $blog_path,
                          $ip           ) = explode( "|", $backupset);
                   list ( $yyyymmdd, $hhmmss) = explode (" ", $date_time);

                   // How old is this file? Older than $cuabr_max_backup_age?
                      $now = current_time( "Y/m/d H:i:s");
                      $age = strtotime( $now ) - strtotime ( $date_time );
                      $bn = basename( $backup_file);

                      // No age control if  max_backup_age == 0
                      if ( $cuabr_max_backup_age == 0) {
                         $age = -1;  // So that the "if >" comparison below is false
                      }
                      // Skip age control for files with names starting with "cul" (uploaded files)
                      if ( substr($bn, 0, 3) === "cua" )  {
                         if ( $age > $cuabr_max_backup_age * 24*60*60) {

                            unlink ( $backup_dir.$backup_file) ;

                            // delete the info file as well...
                            $info_file = basename( $backup_file, ".tar").".info";
                            unlink ( $backup_dir.$info_file);
                            continue;
                         }
                      }

                   // file size in KBytes
                      $file_size_in_bytes =  exec( "stat -c %s $backup_dir$backup_file" );
                      $file_size =  intval ( $file_size_in_bytes / 1024 ) ;
                      $formatted_file_size = number_format( $file_size);
                      if ( $file_size == 0 ) {   // ignore files with 0 size; they will disappear soon, anyway.
                         continue;
                      }
                      $disk_used_by_blog = $disk_used_by_blog + $file_size;  // in KBytes
 
                   // Decide for size-flag. If a file is larger than the max_upload_size, we
                   // shall display a warning icon:
                   // if file size > $max_upload_size, we shall show a green warning icon
                   //                meaning, in case this file will be uploaded later, 
                   //                it will need to be splitted.
                   // if file size > $max_total_upload_size, we shall show a red warning icon
                   //                meaning a file of this size cannot be uploaded to this server;
                   //                in case it has to done later.

                   list( $max_upload_size, $upload_limit, $max_total_upload_size) = cuabr_get_upload_limits();               
                   $size_flag = "";

                   // Used to mark backup sets that are too large to upload or need to be splitted
                   if ( $file_size_in_bytes >= $max_upload_size ) {

                        $size_flag = "<img src=\"" . plugins_url('cua-backuprestore/img/green.png', 
                                                                 'cuabr-backuprestore') ."\"> ";
                        $green_flag = true;
                   }                  
                   if ( $file_size_in_bytes >= $max_total_upload_size ) {
                        $size_flag = "<img src=\"" . plugins_url('cua-backuprestore/img/red.png', 
                                                                 'cuabr-backuprestore') ."\"> ";
                        $red_flag = true;
                   }                  

                   // Is this an uploaded backup file?
                      $uploaded_mark = "";
                      if ( substr( $backup_file, 0, 4) == 'cul-' ) {
                         $uploaded_mark = " <font color=green><b>( Uploaded )</b></font> ";
                      }

                   if ( $zebra == 1 ) {
                        $zebra_code = " bgcolor=#ffffff";
                   } else {
                        $zebra_code = " bgcolor=#eeeeee";
                   }
                   $zebra = $zebra * (-1);
                   $table = $table . "<tr".$zebra_code.">
                                    <td>$backup_user</td>
                                    <td>$yyyymmdd $hhmmss $uploaded_mark</td>
                                    <td>$ip</td>
                                    <td align=right>$size_flag$formatted_file_size</td>
                                    <td align=center>
                                         <a href='$backupdir_url_base$backup_blog_id/$backup_file'><b>[Download]</b></a>
                                         &nbsp;&nbsp;
                                         <a href='' onClick='cuabr_recents_delete( \"".$backup_dir."\", \"".$backup_file."\", \"".$nonce_dir."\", \"".$blog_path."\", \"".$date_time."\", \"".$current_server_time."\" );return false;'><b>[Delete]</b></a>
                                         &nbsp;&nbsp;
                                         <a href='' onClick='cuabr_recents_restore( \"".$backup_dir."\", \"".$backup_file."\", \"".$nonce_dir."\", \"".$backup_blog_id."\", \"".$blog_path."\", \"".$date_time."\", \"".$current_server_time."\" );return false;'><b>[Restore]</b></a>
                                         &nbsp;&nbsp;
                                         <a href='' onClick='document.getElementById(\"cuabr_from_blog_name\").value=\"". $blog_path . "\";
                                                             document.getElementById(\"cuabr_from_blog_id\").value=\"". $backup_blog_id . "\";
                                                             document.getElementById(\"cuabr_from_blog_path\").value=\"". $blog_path . "\";
                                                             document.getElementById(\"cuabr_frm_backup_dir\").value=\"". $backup_dir . "\";
                                                             document.getElementById(\"cuabr_frm_backup_file\").value=\"". $backup_file . "\";
                                                             document.getElementById(\"cuabr_frm_nonce_dir\").value=\"". $nonce_dir . "\";
                                                             document.getElementById(\"cuabr_frm_date_time\").value=\"". $date_time . "\";
                                                             document.getElementById(\"cuabr_frm_current_server_time\").value=\"". $current_server_time . "\";
                                                             document.getElementById(\"restoreas_div\").style.display=\"block\";
                                                             document.getElementById(\"uploader_div\").style.display=\"none\";
                                                             document.getElementById(\"upload_button\").style.display=\"block\";
                                                             document.getElementById(\"cuabr_to_blog\").focus();
                                                             return (false);'>
                                            <b>[Restore-As]</b></a>
                                    </td>
                                 </tr>\n";
           }

           $table = $table . "</tbody>
                              </table>\n";

           if ( $disk_used_by_blog > $cuabr_quota * 1024 * 1024 ) {
              $table = "<font color=red>This blog is over the quota ( $cuabr_quota GB) for backup sets. Please delete some old backup sets.</font><br>" . $table;
              $table = "<input type=hidden id=cuabr_overquota name=cuabr_overquota value=yes>".$table;
           } else {
              $table = "<input type=hidden id=cuabr_overquota name=cuabr_overquota value=no>".$table;
           }
           if ( $green_flag ) {
              $table = "<img src=\"" . plugins_url('cua-backuprestore/img/green.png', 
                                                   'cuabr-backuprestore') ."\"> &nbsp;
                        indicates file will need to be splitted if downloaded 
                        and then later uploaded back.<br>" . $table;
           } 
           if ( $red_flag ) {
              $table = "<img src=\"" . plugins_url('cua-backuprestore/img/red.png', 
                                                   'cuabr-backuprestore') ."\"> &nbsp;
                        indicates file size is too large to be uploaded to this server. 
                        Better leave them on the server.<br>" . $table;
           } 
     } else {
           $table = "<input type=hidden id=cuabr_overquota name=cuabr_overquota value=no>No previous backups found.";
     }

     return ( $table);
}
