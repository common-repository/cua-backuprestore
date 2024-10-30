function cuabr_reload_recents() {
                // Generate an Ajax request to send the recent backup sets

                data = {
                        'action'  : 'cuabr_action',
                        'task'    : 'recents',
                        'frm_nonce_dir' : document.getElementById('frm_nonce_dir').value,
                        'frm_user_id' : document.getElementById('frm_user_id').value,
                        'frm_blog_id' : document.getElementById('frm_blog_id').value,
                        'frm_backup_root' : document.getElementById('frm_backup_root').value,
                        'frm_backup_dir' : document.getElementById('frm_backup_dir').value,
                        'frm_backupdir_url_base' : document.getElementById('frm_backupdir_url_base').value

                };

                jQuery.post(ajaxurl, data, function(response) {
                        document.getElementById('recents').innerHTML =  response;
                });

}


function cuabr_backup_data ( task ) {
         // Generate an Ajax request to start the backup process
         
         if ( document.getElementById("cuabr_overquota").value == "yes") {
              sweetAlert ({
                    title: 'Error !',
                    text: "This blog has exceeded its backup file disk quota. Please delete some old backup sets.",
                    animation: false,
                    html: true,
                    type: 'error'
              });
              jQuery("body").toggleClass("wait");
              document.getElementById('goButton').disabled =  false;
              document.getElementById('goButton').value =  ' Start Backup ';
              return false;
         }

         data = {
                 'action'  :      'cuabr_action',
                 'task'    :      task,
                 'blog_id':       document.getElementById('frm_blog_id').value,
                 'user_id':       document.getElementById('frm_user_id').value,
                 'frm_nonce_dir': document.getElementById('frm_nonce_dir').value,
                 'over_quota':    document.getElementById('cuabr_overquota').value,
                 'frm_backup_root' : document.getElementById('frm_backup_root').value,
                 'frm_backup_dir' : document.getElementById('frm_backup_dir').value,
                 'frm_backupdir_url_base' : document.getElementById('frm_backupdir_url_base').value
                 };

         jQuery.post(ajaxurl, data, function(response) {
                       tresponse = response.trim();
                       document.getElementById('status_msg').innerHTML =  tresponse;
                       jQuery("body").toggleClass("wait");

                    if ( tresponse == 'Done.' ) {
                       document.getElementById('goButton').disabled =  false;
                       document.getElementById('goButton').value =  ' Start Backup ';

                       var blog_or_network = "blog";
                       if ( document.getElementById('frm_blog_id').value == 1 ) {
                          blog_or_network = "network";
                       }

                       msg = 'Your ' + blog_or_network + ' has been backed up.<p> \
                              It is a good idea to download the backup set file \
                              onto your own computer.';
                       sweetAlert ({
                            title: 'Done !',
                            text: msg,
                            animation: false,
                            html: true,
                            type: 'success'
                       });

                    } else {
                       document.getElementById('recents').innerHTML =  tresponse;
                    }
                    cuabr_reload_recents();
         });
         return false;

}

function cuabr_upload ( nonce_dir, blog_id) {

  // Generate an Ajax request to start the upload process

  if ( document.getElementById("cuabr_overquota").value == "yes") {
              sweetAlert ({
                    title: 'Error !',
                    text: "This blog has exceeded its backup file disk quota. Please delete some old backup sets.",
                    animation: false,
                    html: true,
                    type: 'error'
              });
              return false;
  }

  // disable the upload button so that the user does not click it until ve are back
  document.getElementById("cuabr-upload-button").disabled = true;
  document.getElementById("cuabr-upload-button").value = "Uploading...";
  jQuery("body").toggleClass("wait");

  // get all field contents of the form
  var formData = new FormData(document.getElementById('cuabr-upload-form'));

  // add ajax specific data elements
  formData.append("action", "cuabr_action");
  formData.append("task", "upload");
  formData.append("frm_nonce_dir", nonce_dir );
  formData.append("frm_user_id", document.getElementById("frm_user_id").value  );
  formData.append("frm_blog_id", document.getElementById("frm_blog_id").value );
  formData.append("frm_backup_root", document.getElementById("frm_backup_root").value );
  formData.append("frm_backup_dir", document.getElementById("frm_backup_dir").value );
  formData.append("frm_backupdir_url_base", document.getElementById("frm_backupdir_url_base").value );
  jQuery.ajax({
     url: 'admin-ajax.php',
     type: 'POST',
     data: formData,
     contentType: false,
     processData: false,
     success: function ( response ) {
                             // enable the upload button 
                             document.getElementById("cuabr-upload-button").disabled = false;
                             document.getElementById("cuabr-upload-button").value = "Upload File(s)";
                             jQuery("body").toggleClass("wait");
                             tresponse = response.trim();
                             if ( tresponse.indexOf("Uploaded") == 0 ) {
                                sweetAlert( {
                                  title:    'Done!',
                                  text :     tresponse,            // "Uploaded n files"
                                  type :    'success',
                                  animation: false,
                                  html:      true });

                                  cuabr_reload_recents();
                             } else {
                                if ( tresponse == "0" ) {
                                   sweetAlert( {
                                     title:    'Error!',
                                     text :    'Uploaded file is beyond server\'s upload size limits, or destination disk is full!',
                                     type :    'error',
                                     animation: false,
                                     html:      true });
                                } else {
                                   if ( tresponse.indexOf("OK") == 0 ) {
                                      msg_type = "success";
                                      title = "Done!";
                                   } else {
                                      title = "Error!";
                                      msg_type = "error";
                                   } 
                                  
                                   sweetAlert( {
                                     title:    title,
                                     text :    tresponse,
                                     type :    msg_type,
                                     animation: false,
                                     html:      true });
                                }
                                cuabr_reload_recents();
                             }

              },
     error: function ( response ) {
                   alert ("ErrResponse "+response.responseText);
              }
  });
  return false;

}


// See http://learnwebtutorials.com/updating-javascript-alerts-to-sweetalert
// for a very nice tutorial on sweetAlert
// Official site for params is : http://t4t5.github.io/sweetalert/
function cuabr_recents_restore ( backup_dir, backup_file, nonce_dir, blog_id, blog_path, backup_date, server_date  ) {

         // Generates an Ajax req to restore the selected backup set
         ago = cuabr_how_long_ago ( backup_date, server_date);

         blog_or_network = "network";

         // If blog_id == 1, the user clicked to restore the whole network.
         // This action deserves a very serious warning!

         var warning_message  = 'You chose to <font color=red>restore THE WHOLE NETWORK</font>! <p><br> This will revert ALL BLOGS to older states. <p><br> Is this really what you want to do?';

         if ( blog_id != 1 ) {
             blog_or_network = "blog";
             warning_message  = 'Are you sure that you want to revert blog <p>&nbsp;<br>'  + blog_path + '<p>&nbsp;<br>' + '<span style="color:red"> to the older state of<br>' + ago + '  ago; ' + '</span><br>' + 'which was backed up on ' + backup_date + ' (server time)' + '<br>&nbsp;' + '</span><p>Please note that this process is <font color=red>irrevocable</font>.  You might want to make a last minute backup before proceeding.';

         }   

               sweetAlert({  
                    title: '',
                    text: warning_message,
                    html: true,
                    type: 'warning',
                    showCancelButton: true,
                    cancelButtonColor: '#333333',
                    confirmButtonColor: '#009999',
                    confirmButtonText: 'Yes! Go Restore!',
                    cancelButtonText: 'NO! That was a mistake!',
                    closeOnConfirm: false,
                    closeOnCancel: false
                    },
              function(isConfirmed) {
                  if (isConfirmed) {
                     //
                     // Two small modifications in sweetAlert JS code are required for the
                     // following to work:
                     //    1. edit the JS file so that
                     //       "<h2>Title</h2>" becomes "<h2 id=cua_title>Title</h2>"
                     //       (there is only one occurrence of "<h2>Title</h2>" in the JS file
                     //    2. change "<p>Text</p>\n" right next to "<h2>Title</h2>" so that 
                     //       it becomes "<p id=cua_message>Text</p>\n"

                     jQuery( '#cua_title' ).html('Please wait...');
                     jQuery("body").toggleClass("wait");
                     jQuery( '#cua_message' ).html('It might take a while...');

                     jQuery( '.cancel' ).hide();
                     jQuery( '.confirm' ).hide();
                     data = {
                             'action'     :    'cuabr_action',
                             'task'       :    'restorefile',
                             'blog_id'    :    blog_id,
                             'backup_dir' :    backup_dir,
                             'backup_file':    backup_file,
                             'nonce_dir'  :    nonce_dir
                             };

                     jQuery.post(ajaxurl, data, function ( response) {
                             tresponse = response.trim();
                             if ( tresponse == "Done." ) {
                                jQuery("body").toggleClass("wait");
                                sweetAlert(  'Done!',
                                  'Your ' + blog_or_network + ' has been restored.',
                                  'success');
                                  cuabr_reload_recents();
                             } else {
                                jQuery("body").toggleClass("wait");
                                sweetAlert(  'Error!',
                                  'Something went wrong: \n' + tresponse,
                                  'error');
                             }
                     });

                     // Refresh the recents table
                     data = {
                        'action'  : 'cuabr_action',
                        'task'    : 'recents',
                        'frm_nonce_dir' : document.getElementById('frm_nonce_dir').value,
                        'frm_user_id' : document.getElementById('frm_user_id').value,
                        'frm_blog_id' : document.getElementById('frm_blog_id').value,
                        'frm_backup_root' : document.getElementById('frm_backup_root').value,
                        'frm_backup_dir' : document.getElementById('frm_backup_dir').value,
                        'frm_backupdir_url_base' : document.getElementById('frm_backupdir_url_base').value
                     };
                     jQuery.post(ajaxurl, data, function(response) {
                             document.getElementById('recents').innerHTML =  response;
                     });

                     return false;

                  } else {
                     sweetAlert( 'Cancelled',
                                 'Your ' + blog_or_network + ' remains untouched.',
                                 'error');
                  }
              });

         return false ;
}

function cuabr_how_long_ago ( backup_date, server_date) {

         // Calculate the diff between backup_date and current server_date
         // backup and server dates are in the form "YYYY/MM/DD hh:mm:ss"
         backup_date_parts = backup_date.split(/ /);
         backup_date_part = backup_date_parts[0];
         backup_time_part = backup_date_parts[1];

         backup_date_ymd = backup_date_part.split(/\//);
         backup_year  = backup_date_ymd[0];
         backup_month = backup_date_ymd[1];
         backup_day   = backup_date_ymd[2];

         backup_date_hms = backup_time_part.split(/:/);
         backup_hour  = backup_date_hms[0];
         backup_min   = backup_date_hms[1];
         backup_sec   = backup_date_hms[2];

         server_date_parts = server_date.split(/ /);
         server_date_part = server_date_parts[0];
         server_time_part = server_date_parts[1];

         server_date_ymd = server_date_part.split(/\//);
         server_year  = server_date_ymd[0];
         server_month = server_date_ymd[1];
         server_day   = server_date_ymd[2];

         server_date_hms = server_time_part.split(/:/);
         server_hour  = server_date_hms[0];
         server_min   = server_date_hms[1];
         server_sec   = server_date_hms[2];

         backup_date_msec = new Date(backup_year, backup_month, backup_day, backup_hour, backup_min, backup_sec, 0);
         server_date_msec = new Date(server_year, server_month, server_day, server_hour, server_min, server_sec, 0);

         diff = Math.abs( server_date_msec  - backup_date_msec) / 1000;   // date objects are in milisecs
         diff = Math.floor(diff);

         numdays = Math.floor(diff / 86400);

         numhours = Math.floor((diff % 86400) / 3600);

         numminutes = Math.floor(((diff % 86400) % 3600) / 60);

         numseconds = ((diff % 86400) % 3600) % 60;
 
         ago = "";
         if ( numdays != 0 ) {
            ago = numdays + " days";
         }
         if ( numhours != 0 ) {
            ago = ago + " " + numhours + " hour(s)";
         }
         if ( numminutes != 0 ) {
            ago = ago + " " + numminutes + " minute(s)";
         }
         if ( numseconds != 0 ) {
            ago = ago + " " + numseconds + " second(s)";
         }
         return ( ago);
}

function cuabr_recents_delete ( backup_dir, backup_file, nonce_dir, blog_path, backup_date, server_date  ) {
         
         // Generates an Ajax req to delete the selected backup set

         ago = cuabr_how_long_ago ( backup_date, server_date);

         sweetAlert({
              title: '',
              text: 'Do you really want to delete backup for blog' + 
                     '<p>' + blog_path + '<p>' + '<span style="color:red">backed up ' + 
                     ago + '  ago; ' + '</span><br>' + 'i.e. at ' + backup_date +
                     ' (server time)' + '<br>&nbsp;' + '</span>',
              html: true,
              type: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#009999',
              confirmButtonText: 'Yes! Go delete...!',
              cancelButtonText: 'NO! That was a mistake!',
              closeOnConfirm: false,
              closeOnCancel: false
              },
              function(isConfirmed) {
                  if (isConfirmed) {
                     //
                     // A small modification in sweetAlert JS code in required for the
                     // following to work:
                     // edit the JS file so that
                     // "<h2>Title</h2>" becomes "<h2 id=cua_title>Title</h2>"
                     // (there is only one occurrence of "<h2>Title</h2>" in the JS file
                     jQuery( '#cua_title' ).html('Please wait...');

                     jQuery( '.cancel' ).hide();
                     jQuery( '.confirm' ).hide();
                     data = {
                             'action'  :      'cuabr_action',
                             'task'    :      'deletefile',
                             'backup_dir':    backup_dir,
                             'backup_file':   backup_file,
                             'nonce_dir': nonce_dir,
                             };

                     jQuery.post(ajaxurl, data, function ( response) {
                             tresponse = response.trim();
                             if ( tresponse == "deleted" ) {
                                sweetAlert(  'Done!',
                                  'Backup file has been deleted.',
                                  'success');
                                  cuabr_reload_recents();
                             } else {
                                sweetAlert(  'Error!',
                                  'Something went wrong: ' + tresponse,
                                  'error');
                             }
                     });

                     // Refresh the recents table
                     data = {
                        'action'  : 'cuabr_action',
                        'task'    : 'recents',
                        'frm_nonce_dir' : document.getElementById('frm_nonce_dir').value,
                        'frm_user_id' : document.getElementById('frm_user_id').value,
                        'frm_blog_id' : document.getElementById('frm_blog_id').value

                     };
                     jQuery.post(ajaxurl, data, function(response) {
                             document.getElementById('recents').innerHTML =  response;
                     });

                     return false;

                  } else {
                     sweetAlert( 'Cancelled',
                                 'Your backup file is safe.',
                                 'error');
                  }
              });
         return false ;
}

function cuabr_recents_restoreas (  ) {
         
         // Generates an Ajax req to Restore-As the selected backup set
         // ("Restores AS" a selected backup set on to a different blog)

         var to_blog_id   = document.getElementById('cuabr_to_blog').value;
         if ( to_blog_id == 0 ) {
              return false;             // A selection has to be made
         }

         var from_blog_id = document.getElementById('cuabr_from_blog_id').value;
         if ( from_blog_id == 1 ) {
                  sweetAlert(  'Error!',
                               'The \"Restore-As\" operation is not allowed on full site backups!',
                               'error');
                  return false ;
         }

         if ( from_blog_id == to_blog_id ) {
                  sweetAlert(  'Error!',
                               'You cannot \"Restore-As\" a blog ONTO itself!',
                               'error');
                  return false ;
         }
         

         var nonce_dir   = document.getElementById('cuabr_frm_nonce_dir').value;
         var backup_date = document.getElementById('cuabr_frm_date_time').value;
         var server_date = document.getElementById('cuabr_frm_current_server_time').value;
         var selection   = document.getElementById('cuabr_to_blog').selectedIndex;
             selection   = document.getElementById('cuabr_to_blog').options[selection].text;
         var destination_blog_name = selection;
         var source_blog_name      = document.getElementById('cuabr_from_blog_name').value;
         // values to be passed to ajax server
         var from_blog_id = document.getElementById('cuabr_from_blog_id').value;
         var backup_dir   = document.getElementById('cuabr_frm_backup_dir').value;
         var backup_file  = document.getElementById('cuabr_frm_backup_file').value;

         ago = cuabr_how_long_ago ( backup_date, server_date);

         warning_message  = 'Are you sure that you want to Restore-As blog <p>&nbsp;<br>'  + source_blog_name + ' ONTO blog ' + destination_blog_name + '?<p>&nbsp;<br>The backup was made ' + ago + '  ago; ' + '<br>' + ' on ' + backup_date + ' (server time)' + '<br>&nbsp;' + '<p>Please note that this process is <font color=red>irrevocable</font>.  You might want to make a last minute backup before proceeding.';


               sweetAlert({  
                    title: '',
                    text: warning_message,
                    html: true,
                    type: 'warning',
                    showCancelButton: true,
                    cancelButtonColor: '#333333',
                    confirmButtonColor: '#009999',
                    confirmButtonText: 'Yes! Go Restore-As!',
                    cancelButtonText: 'NO! That was a mistake!',
                    closeOnConfirm: false,
                    closeOnCancel: false
                    },
              function(isConfirmed) {
                  if (isConfirmed) {
                     //
                     // Two small modifications in sweetAlert JS code are required for the
                     // following to work:
                     //    1. edit the JS file so that
                     //       "<h2>Title</h2>" becomes "<h2 id=cua_title>Title</h2>"
                     //       (there is only one occurrence of "<h2>Title</h2>" in the JS file
                     //    2. change "<p>Text</p>\n" right next to "<h2>Title</h2>" so that 
                     //       it becomes "<p id=cua_message>Text</p>\n"

                     jQuery( '#cua_title' ).html('Please wait...');
                     jQuery( '#cua_message' ).html('It might take a while...');

                     jQuery( '.cancel' ).hide();
                     jQuery( '.confirm' ).hide();
                     data = {
                             'action'          :    'cuabr_action',
                             'task'            :    'restorefileas',
                             'from_blog_id'    :    from_blog_id,
                             'to_blog_id'      :    to_blog_id,
                             'backup_dir'      :    backup_dir,
                             'backup_file'     :    backup_file,
                             'nonce_dir'       :    nonce_dir
                             };

                     jQuery.post(ajaxurl, data, function ( response) {
                             tresponse = response.trim();
                             if ( tresponse == "Done." ) {
                                sweetAlert(  'Done!',
                                  'Your blog has been restored.',
                                  'success');
                                  cuabr_reload_recents();
                             } else {
                                sweetAlert(  'Error!',
                                  'Something went wrong: \n' + tresponse,
                                  'error');
                             }
                     });


                  } else {
                     sweetAlert( 'Cancelled',
                                 'Your blog remains untouched.',
                                 'error');
                  }
              });

         return false ;
}
