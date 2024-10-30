<?php 
      
      global $cua_plugin_version;
      global $logo_image;
      global $help_icon;

      include( CUABR_PLUGIN_PATH . 'lib/get_upload_limits.php' );

      $os_is_good = true;               // This plugin does NOT work on WinX OSs.
      if (  stripos( PHP_OS, "Win" )  ) $os_is_good = false;

      if (  is_multisite() !== true )  {
         echo "
           <div  style='border:5px solid red; width:70%'>
           <h2><span><font color=red>&nbsp; STOP!</font></span></h2>
	     <div  style='font-size:18px;padding:20px'> 
             Your Wordpress installation is not a <b>Multi Site</b> installation.<br>
             This plugin will <b>NOT work</b> on this Wordpress site.<br>&nbsp;<br>
             Actually there many excellent Backup/Restore plugins for single site installations. 
              Please visit the plugin repository of wordpress.org.<br>&nbsp;<br>
             <b><font color=red>You are strongly recommended to deactivate 
             and delete (uninstall) this plugin.</font></b>
             </div>  <!-- /inside -->
           </div>  <!-- /box -->";

           wp_die();
      }


      $current_user_obj = wp_get_current_user();
      $user_id = $current_user_obj -> ID;

      // calculate the backup_dir for this network

      $network_url      = network_site_url(); 
      $network_dir      = ABSPATH;
      $wp_plugins_dir   = $network_dir . "wp-content/plugins/";
      $wp_plugins_url   = $network_url . "wp-content/plugins/";

      $blog_id          = get_current_blog_id();
      $cuabr_backup_root   = $wp_plugins_dir."cua-backuprestore/cua-backups/";
      $cuabr_backup_dir    = $cuabr_backup_root . $blog_id . "/";
      $cuabr_backupdir_url_base = $wp_plugins_url."cua-backuprestore/cua-backups/";

      // create an index file to avoid directory listing
      system( "touch " . $cuabr_backup_root . "index.html");

      // create the backup dir if it is not already there
      system( "mkdir -p " . $cuabr_backup_dir);
      system( "touch  " . $cuabr_backup_dir . "index.html");

      // Shall we start with Settings Panel hidden or shown? Are mysql & mysqldump set?
      $cuabr_mysql          = get_site_option( 'cuabr_mysql', 'undefined');
      $cuabr_mysqldump      = get_site_option( 'cuabr_mysqldump', 'undefined');
      $cuabr_max_backup_age = get_site_option( 'cuabr_max_backup_age', 0);
      $cuabr_quota          = get_site_option( 'cuabr_quota', 4);

      $panel_display = "block";    // assume shown
      if ( $cuabr_mysql      != "undefined" && 
           $cuabr_mysqldump  != "undefined" ) {
         $panel_display = "none";
      }

     // create a nonce dir for security and to put temp stuff into 
     // the name of this nonce dir will be passed in form element
     // any php script accessed thru these forms will check the presence of this nonce dir
     // and will not perform unless this nonce dir exists

     // delete any nonce dir older than 24 hours - clean up our waste!
     system( '/usr/bin/find  /tmp -name "cua-*" -mmin +1440 -exec /bin/rm -r {} \;' );

     $completion_code = 9;
     $nonce_dir = exec( "/bin/mktemp -d --tmpdir=/tmp cua-XXXXXXXXXX", $output, $completion_code );
     if ( $completion_code != 0 ) {
          print "<font style='color:red; font-size:16px'>Error: /bin/mktemp command could not be executed!</font>";
          wp_die();
     }
     $nonce_dir = $output[0];

     // Get some php.ini settings related to max file upload size

     list( $upload_limit_in_bytes, $upload_limit, $total_upload_limit) = cuabr_get_upload_limits();

     $upload_dir = wp_upload_dir();
     $upl = $upload_dir ['basedir'];

?>
<!-- for "busy" mouse pointer animation -->
<style>
  body.wait, body.wait *{
    cursor: wait !important; 
  }
  div{
    cursor: pointer;
  }
</style>

<div style="width: 200px; z-index: 10; position: fixed; right: 10px; top: 70px;">
    <a href=http://cuabr.net target=__new><img src='<?php echo $logo_image;?>' border=0 width=150></a>
</div>

	<h2>CUA Backup/Restore<span> - Ver <?php global $cua_plugin_version; echo $cua_plugin_version; ?></span></h2>

<?php
   if (  stripos( PHP_OS, "Win" )  )  {
      echo "
        <div style='border:5px solid red'>
        <h2><span><font color=red>&nbsp; STOP!</font></span></h2>
	  <div  style='font-size:18px'> 
             The operating system of your WordPress server is some kind of MS Windows.<br>
             This plugin will <b>NOT work</b> on WinX servers because it uses *NIX specific tools.<br>
             It will work on <b>Linux, FreeBSD</b> and in general all <b>*NIX</b> 
             operating systems.<br>&nbsp;<br>
             <b><font color=red>You are strongly recommended to deactivate 
             and delete (uninstall) this plugin.</font></b>
          </div>  <!-- /inside -->
        </div>  <!-- /box -->";
   }
?>

<?php
   if ( is_subdomain_install () )  {
      echo "
        <div  style='border:5px solid red'>
        <h2><span><font color=red>&nbsp; STOP!</font></span></h2>
	  <div  style='font-size:18px'> 
             Your Multi-Site Wordpress installation is configured to operate in <b>sub-domain</b> mode.<br>
             This plugin will <b>NOT work</b> on this Wordpress site.<br>
             It will work on <b>sub-directory</b> installations only.
             <b><font color=red>You are strongly recommended to deactivate 
             and delete (uninstall) this plugin.</font></b>
          </div>  <!-- /inside -->
        </div>  <!-- /box -->";
   }
?>


<!-- this div  should be shown only to super_admin -->

	<div  
           <?php 
                 $super_admin      = is_super_admin( $user_id);
                 if ( ! $super_admin ) {
                     echo "style='display:none'";
                 }
           ?> style="width:100%">
           <script>
           function cuabr_toggle_settings() {
               backupdiv = document.getElementById("cuabr_backupform")
               elem  = document.getElementById("settings_form_div")
               word  = document.getElementById("showhide")
               if ( elem.style.display == "none" ) { // unhide it
                   elem.style.display = "block";
                   backupdiv.style.display = "none";
                   word.innerHTML = "Hide";
               } else {                              // hide it
                   elem.style.display="none";
                   backupdiv.style.display="block";
                   word.innerHTML = "Show";
               }
               return false;
           }
           </script>


	   <h3><span>CUA Backup/Restore Plugin Settings 
                &nbsp;&nbsp;&nbsp;
                &nbsp;&nbsp;&nbsp;
                &nbsp;&nbsp;&nbsp;
                &nbsp;&nbsp;&nbsp;
                <a href="" onClick="cuabr_toggle_settings(); return false;">
                 [ <span id=showhide>Show/Hide</span> Settings Panel ]</a>
            </span>
           </h3>

           <div id=settings_form_div  style='display:<?php echo $panel_display;?>;
                         position:relative;
                         border-radius: 8px; background:#e3e3e3;
                         border: 2px solid #999999;
                         margin-top: 10px; margin-bottom: 10px;
                         padding: 10px; height: 180px; width:70%'>
                <span style="position:absolute; top: 0; right:10px">
                    <a href="#cuabr-close" class=cuabr-close  
                     onClick='document.getElementById("settings_form_div").style.display="none";
                              document.getElementById("cuabr_backupform").style.display="block";
                              return false;' style='text-decorations:none'>X</a>
                </span>

               <form action=options.php method=post>

               <?php 
                settings_fields('cuabr_settings_group');

                $suggested_mysql_command = "e.g. : /usr/bin/mysql";
                $suggest = shell_exec ("which mysql");
                if ( $suggest ) {
                   // we do not want the return char at the end
                   $suggested_mysql_command = trim( $suggest);
                }

                $suggested_mysqldump_command = "e.g. : /usr/bin/mysqldump";
                $suggest = shell_exec ("which mysqldump");
                if ( $suggest ) {
                   // we do not want the return char at the end
                   $suggested_mysqldump_command = trim( $suggest);
                }
              ?>

              <script>
              function cuabr_suggest ( option, value) {
                     document.getElementById(option).value = value;
              }
              </script>

              <table border=0 cellspacing=1 cellpadding=3>
                 <tr><td valign=top>Path to mysql command</td>
                     <td><input id=frm_mysql name=cuabr_BackupRestore[cuabr_mysql] size=48 
                                value='<?php echo $cuabr_mysql;?>'></td>
                     <td><a href="" onClick="cuabr_suggest('frm_mysql', 
                                 '<?php echo $suggested_mysql_command;?>'); 
                                 return (false);">Suggest</a></td>
                 </tr>
                 <tr><td valign=top>Path to mysqldump command</td>
                     <td><input id=frm_mysqldump name=cuabr_BackupRestore[cuabr_mysqldump] size=48 
                                value='<?php echo $cuabr_mysqldump;?>'></td>
                     <td><a href="" onClick="cuabr_suggest('frm_mysqldump', 
                                 '<?php echo $suggested_mysqldump_command;?>'); 
                                 return (false);">Suggest</a></td>
                 </tr>
                 <tr><td valign=top>Number of days to keep backup sets</td>
                     <td><input id=frm_backup_age name=cuabr_BackupRestore[cuabr_max_backup_age] size=2 
                                value='<?php echo $cuabr_max_backup_age;?>'> ('0' to keep backups indefinitely)</td>
                 </tr>
                 <tr><td valign=top>Disk quota for backup files (for each blog)</td>
                     <td><input id=frm_quota name=cuabr_BackupRestore[cuabr_quota] size=2 
                                value='<?php echo $cuabr_quota;?>'> in GBytes ('0' for unlimited)</td>
                 </tr>
              </table>
              <input type=submit class=button-primary value='Save Changes'>
            </form>

          </div> <!-- /inside -->
       </div> <!-- /box -->

<div id=cuabr_backupform style='display:block;
                         position:relative;
                         border-radius: 8px; background:#e3e3e3; 
                         border: 2px solid #999999; 
                         margin-top: 10px; margin-bottom: 10px; 
                         padding: 10px; height: 150px; width:70%'  
               width='70%'>  
    <div id=cuabr-help style="position:relative;top:3px;float:right;margin-right:10px">
               <a href=http://cuabr.net target=new><img src="<?php echo $help_icon;?>"></a>
    </div>
    <h3>Choose the blog to work on:</h3>
    <div id=status_msg style="display:none">Ready</div>
    
    <blockquote>
    Sites that you can back-up:
     <form name=cuabr_backup_restore_form id=cuabr_backup_restore_form method=post>
 
    <?php 
       $blog_name        = get_bloginfo( 'name' );
       $blog_details_obj = get_blog_details( $blog_id );
       $blog_path        = $blog_details_obj -> path;
       $blog_domain      = $blog_details_obj -> domain;

       if ( ! is_multisite() ) {
          print "<input type=hidden id=frm_blog_id value=1>";  // Is blog_id ==1 for singlesite WP?
          print "$blog_name ($blog_path)\n";
       } else {
          $blog_list = wp_get_sites();

             $on_change = "data = {
                        'action'  : 'cuabr_action',
                        'task'    : 'recents',
                        'frm_backup_root'   : document.getElementById('frm_backup_root').value, 
                        'frm_backup_dir'    : document.getElementById('frm_backup_dir').value, 
                        'frm_backupdir_url_base'    : document.getElementById('frm_backupdir_url_base').value, 
                        'frm_nonce_dir'     : document.getElementById('frm_nonce_dir').value,
                        'frm_user_id'       : document.getElementById('frm_user_id').value,
                        'frm_blog_id'       : document.getElementById('frm_blog_id').value,
                };
                document.getElementById('restoreas_div').style.display='none';
                document.getElementById('uploader_div').style.display='none';
                document.getElementById('upload_button').style.display='block';
                var selectBox = document.getElementById('frm_blog_id');
                var selectedText = selectBox.options[selectBox.selectedIndex].text;
                document.getElementById('cua_backing-up_for').innerHTML=selectedText;
                jQuery.post(ajaxurl, data, function(response) {
                        document.getElementById('recents').innerHTML =  response;
                });";


             print "<p><select name='frm_blog_id' id='frm_blog_id'
                       onChange=\"".$on_change.'">';

             if ( $super_admin ) {
                   print "        <option value='1'>THE WHOLE NETWORK</option>\n";
             }
             foreach ($blog_list as $blog) {
            
                // We have already included the network ( blog_id = 1) to
                // the select options. Adding it once more will be confusing.
                   if ( $blog['blog_id'] == 1 ) {
                      continue;
                   }

                // Show only the blogs that the user ad admin rights on.
                   switch_to_blog ( $blog['blog_id'] );
                   if ( ! current_user_can( "manage_options") ) {
                             restore_current_blog();
                             continue;
                   }
             
                   $sw_blog_name = get_bloginfo();
                   restore_current_blog();

                   $managed_blogs[$blog['blog_id'] ] = $sw_blog_name;  // Array of blogs 
                                                                       // this user has manager 
                                                                       // rights on.
                   $selected = "";
                   if ( $blog['blog_id'] == $blog_id ) {
                       $selected = " selected ";
                   }
                   print "<option value=".$blog["blog_id"].$selected.">".$blog["domain"].$blog["path"]."</option>\n";
             }
       }

       print "</select>
                  <input type=hidden id=frm_user_id value='" . $user_id . "'>
                  <input type=hidden id=frm_backup_root value='" . $cuabr_backup_root . "'>
                  <input type=hidden id=frm_backup_dir value='" . $cuabr_backup_dir . "'>
                  <input type=hidden id=frm_backupdir_url_base value='" . $cuabr_backupdir_url_base . "'>
                  <input type=hidden id=frm_network_url value='" . $network_url . "'>
                  <input type=hidden id=frm_network_dir value='" . $network_dir . "'>
                  <input type=hidden name=frm_nonce_dir  id=frm_nonce_dir  value='".$nonce_dir."'>
                  &nbsp;&nbsp;&nbsp; 
                  <input id='goButton' type='button' class='pure-button pure-button-primary' value=' Start Backup '
                         onClick='document.getElementById(\"status_msg\").innerHTML =  \"<b><font color=red>Backing up...</font></b>\";
                                 document.getElementById(\"goButton\").disabled = true;
                                 document.getElementById(\"goButton\").value = \" Please wait \";
                                 jQuery(\"body\").toggleClass(\"wait\");
                                 cuabr_backup_data(\"Back up\");'> 
                  &nbsp;";
?>
     </form>
    </blockquote>
</div> <!-- /cuabr_backupform -->
<?php 

   $utc_offset_sign = "+";
   $utc_offset      = get_option('gmt_offset');
   if ( $utc_offset < 0 ) $utf_offset_sign = "-";
   print "<table cellspacing=10 cellpadding=10  width='70%'>
             <tr>
                  <td>Server time now: <b><font color=DarkBlue>" . 
                        current_time( 'Y/m/d H:i:s', 0) . 
                        " UTC" . $utc_offset_sign.$utc_offset.
                        "</font></b></td>
                  <td align=right>
                       <input id='upload_button' type='button' class='pure-button pure-button-primary' value=' Upload a Backup Set '
                             onClick='document.getElementById(\"restoreas_div\").style.display=\"none\"; 
                                      document.getElementById(\"uploader_div\").style.display=\"block\"; 
                                      document.getElementById(\"upload_button\").style.display=\"none\";  
                                      return false;'>
                   </td>
             </tr>
          </table>";



?>
<style>
.cuabr-close {
    background: #606061;
    color: #FFFFFF;
    line-height: 25px;
    position: absolute;
    right: -12px;
    text-align: center;
    top: -10px;
    width: 24px;
    text-decoration: none;
    font-weight: bold;
    -webkit-border-radius: 12px;
    -moz-border-radius: 12px;
    border: 2px solid white;
    border-radius: 12px;
    -moz-box-shadow: 1px 1px 3px #000;
    -webkit-box-shadow: 1px 1px 3px #000;
    box-shadow: 1px 1px 3px #000;
}
.cuabr-close:hover {
    color:#ff0000;
    background: #e0e0e0;
}
</style>

<div id=restoreas_div style='display:none;
                         position:relative;
                         border-radius: 8px; background:#e3e3e3; 
                         border: 2px solid #999999; 
                         margin-top: 10px; margin-bottom: 10px; 
                         padding: 10px; height: 150px; width:70%'  
               width='70%'>  <h3>Restoring blogs onto other blogs; Restore-As.</h3>
       <span style="position:absolute; top: 0; right:10px">
           <a href="#cuabr-close" class=cuabr-close  
                     onClick='document.getElementById("restoreas_div").style.display="none";
                              return false;' style='text-decorations:none'>X</a>
       </span>

  <div class="cuabr-restoreas-container">  

     <!-- available sources -->

     <form id=cuabr-restoreas-form 
           method="post" 
           onSubmit='cuabr_recents_restoreas(); return false;'>
       <table width=100%>
       <tr>
       <td valign=top>
       Restore blog &nbsp;&nbsp;&nbsp;
                <input type=hidden name="cuabr_from_blog_id"            id="cuabr_from_blog_id" value="">
                <input type=hidden name="cuabr_from_blog_path"          id="cuabr_from_blog_path" value="">
                <input type="text" name="cuabr_from_blog_name"          id="cuabr_from_blog_name" 
                       readonly style="padding:3px;font-size:14px; background-color:white" 
                       size=40 value="">
                <input type=hidden name="cuabr_frm_backup_dir"          id="cuabr_frm_backup_dir" value="">
                <input type=hidden name="cuabr_frm_backup_file"         id="cuabr_frm_backup_file" value="">
                <input type=hidden name="cuabr_frm_nonce_dir"           id="cuabr_frm_nonce_dir" value="">
                <input type=hidden name="cuabr_frm_date_time"           id="cuabr_frm_date_time" value="">
                <input type=hidden name="cuabr_frm_current_server_time" id="cuabr_frm_current_server_time" value="">
       <p><input type="submit" id=cuabr-restoreas-button value="Start Restore" 
                 class="pure-button pure-button-primary">
       </td>
       <td valign=top>
                &nbsp;&nbsp;&nbsp;<b>ONTO</b> blog&nbsp;&nbsp;&nbsp;
       <select name=cuabr_to_blog id=cuabr_to_blog style="font-size:14px;padding:3px">
          <option value=0>Choose one</option>
       <?php 
             
             // altough we are in a multisite network, it is possible that we have no 
             // blogs other than the main site.
             if ( count( $managed_blogs) > 0 ) {
                $keys = array_keys( $managed_blogs );
                sort( $keys);
                foreach ( $keys as $managed_blog ) {
                     $site_url = get_blog_option( $managed_blog, "home", "Unknown");
                     $site_dir = preg_replace( '/(.*)\/(\w*$)/', '$2', $site_url);
                     print "<option value=".$managed_blog . ">/$site_dir : $managed_blogs[$managed_blog]</option>\n";
                }
             }
       ?>
       </select>
          
       <br>&nbsp;&nbsp;&nbsp;<a href="" onClick='cuabr_help();return false;'><b>Can't see your destination blog in the above list?</a>
       <br>&nbsp;&nbsp;&nbsp;<a href="" onClick='cuabr_help();return false;'><b>Help</b></a>
   
       <input type=hidden name="action"          value="cuabr_action">
       <input type=hidden name="task"            value="restoreas">
       <p>
       </td>
       </tr>
       </table>

     </form>

  </div>
</div>  <!-- end of restoreas_div -->
<div id=uploader_div style='display:none;
                         position:relative;
                         border-radius: 8px; background:#e3e3e3; 
                         border: 2px solid #999999; 
                         margin-top: 10px; margin-bottom: 10px; 
                         padding: 10px; height: 150px; width:70%'  
               class=wait
               width='70%'>
               <h3>Uploading a backup set</h3>
               Your server's upload settings are: Max  
              <b><?php echo $upload_limit;?></b> for each file &nbsp;and&nbsp; max <b>
                 <? echo ini_get('max_file_uploads');?>
              </b> files at a time.
              <br>&nbsp;<br>
       <span style="position:absolute; top: 0; right:10px">
           <a href="#cuabr-close" class=cuabr-close  
                     onClick='document.getElementById("uploader_div").style.display="none";
                              document.getElementById("upload_button").style.display="block";
                              return false;' style='text-decorations:none'>X</a>


       </span>

  <div class="cuabr-uploader-container">  

     <script> 
     // Repaint recents table
     cuabr_reload_recents();

     function cuabr_readme() {
        var msg= "<span style=\'text-align:left; font-weight:normal\'>                         \
                 <ul style=\'text-align:left; list-style-type: square\'>                       \
                   <blockquote>                                                                \
                      <li>If the size of your backup set fits into your server's               \
                          upload size limits; then you can upload any tar file                 \
                          with any name (e.g. my_backup_set.tar).</li> \
                      <li>If your backup set file is larger than the server upload             \
                          size limits; then you have to split the file into smaller            \
                          pieces by a desktop tool and then upload these parts all at once.    \
                          (Your browser must support HTML5 multiple file upload.)              \
                          In this case the uploaded files must ALL have filenames like:        \
                          <font color=darkgreen>some_file_name.tar.partNN</font> where         \
                          NN\'s are sequence numbers of the splitted file\'s parts.            \
                      </li>                                                                    \
                   </blockquote>                                                               \
                 </ul><p><font color=red size=\'-1\'>By the way; your server\'s file upload    \
                 size limit is <?php echo $upload_limit;?> and you can upload max              \
                 <?php echo ini_get('max_file_uploads');?> files at a time; which makes a      \
                 total max backup set size of <?php echo cuabr_formatBytes( ini_get( 'max_file_uploads')*$upload_limit_in_bytes );?></font></span>";

        sweetAlert({
              title: "Uploading backup set files", 
              text:  msg,
              animation: false,
              html:  true
        });

        return false;
     }

     </script> 
     <script> 
     function cuabr_help() {
        var msg= "<span style=\'font-weight:normal; text-align:left\'> \
                 <p style=\'font-weight:normal;'>In order to restore a blog\'s backup as a different blog                           \
                 (a feature which can be used to clone a blog on the same server)                   \
                 <blockquote> \
                  <ul style=\'text-align:left; list-style-type: square\'>                            \
                    <blockquote>                                                                     \
                      <li>first you need to create the new blog using regular WP admin procedures (i.e. My Sites -> Network Admin -> Sites -> Add New),</li>  \
                      <li>assign/create user account(s) with administrator rights on this new blog, (in order to  \'Restore-As\', you must have administrative right on both blogs)</li> \
                      <li>make a recent backup set of the blog that you want to \'Restore-As\' onto this new site,</li>  \
                      <li>when you come back to this screen; you should see the new blog\'s name        \
                          in the \'ONTO blog\' select box. If you cannot see the freshly              \
                          created blog here, you must have made some mistake in assigning           \
                          admin(s) to this new blog. One has to have administrative rights          \
                          on the new blog as well as the original blog to see it in this list.                                   \
                         </li>                                                                      \
                    </blockquote>                                                                     \
                 </ul> \
               </blockquote> \
               <p style=\'font-weight:normal;'>During the \'Restore-As\' operation, ALL database entries related to      \
                 posts, pages, uploads, media references will be modified to reflect the new blog\'s \
                 URL. i.e. all links like \'http://wpserver.com/old-blog/XYZ\' will                   \
                 be replaced with \'http://wpserver.com/new-blog/XYZ\'. The content and uploaded      \
                 files will be overwritten.";

        sweetAlert({
              title: "About the Restore-As operation:", 
              text:  msg,
              animation: false,
              html:  true
        });

        return false;
     }

     </script> 

     <!-- multiple file upload form -->
     If your browser supports HTML5, you can select multiple files to upload. 
     &nbsp;&nbsp;<a href='' onClick='cuabr_readme();return false;'><font color=red>
     <b>Important:</b> Please read me</font></a><p>

     <form id=cuabr-upload-form 
           method="post" enctype="multipart/form-data" 
           onSubmit='cuabr_upload("<?php echo $nonce_dir;?>", "<?php echo $blog_id;?>"); return false;'>

       <input type="file" name="files[]" multiple="multiple" id="files">

       <input type=hidden name="action"          value="cuabr_action">
       <input type=hidden name="task"            value="upload">
       <input type=hidden name="nonce_dir"       value="<? echo $nonce_dir;?>">
       <input type=hidden name="blog_id"         value="<? echo $blog_id;?>">
       <input type="submit" id=cuabr-upload-button value="Upload File(s)" 
              class="pure-button pure-button-primary">

     </form>


  </div>
</div>  <!-- end of uploader_div -->

<?php
   print "<h3>Recent backups for <font color=DarkBlue><span id=cua_backing-up_for>".$blog_domain . $blog_path."</span></font></h3>";
   print "<div id=recents style='display:block'></div>";

     echo "</div>
        </div><!-- container -->\n";
     echo "</div>";


?>
