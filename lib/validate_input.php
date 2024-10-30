<?php
function cuabr_validate_input ( $input ) {

      $mysql            = $input['cuabr_mysql'];
      $mysqldump        = $input['cuabr_mysqldump'];
      $max_backup_age   = $input['cuabr_max_backup_age'];
      $quota            = $input['cuabr_quota'];

      cuabr_check_options ( $backup_dir, $mysql, $mysqldump, $max_backup_age, $quota);

      // if the above function returns at all, the settings values are sane.
      // if the user is super-admin, save these values as site-options
      // which will be valid for all blogs on this site.
     
      $user_id = get_current_user_id(); 

      if ( is_super_admin( $user_id) ) {
         update_site_option ( 'cuabr_mysql', $mysql );
         update_site_option ( 'cuabr_mysqldump', $mysqldump );
         update_site_option ( 'cuabr_max_backup_age', $max_backup_age );
         update_site_option ( 'cuabr_quota', $quota );
      }

      return $input;
}


function cuabr_check_options (  $backup_dir, $mysql, $mysqldump, $max_backup_age) {

      if ( (! is_numeric ( $quota ))  || ( $quota < 0 ) ) {
        add_settings_error(
                'Error',                    
                '04',                        
                'Error: Invalid value for backup disk quota:"' . $quota . '".', 
                'error'                         
        );
      }
      
      if ( (! is_numeric ( $max_backup_age ))  || ( $max_backup_age < 0 ) ) {
        add_settings_error(
                'Error',                    
                '04',                      
                'Error: Invalid value for number of days to keep backup sets:"' . $max_backup_age . '".',
                'error'                        
        );
      }
      
      if ( (! is_executable( $mysql )) || ($mysql == "undefined") ) {
        add_settings_error(
                'Error',
                '04',
                'Error: mysql command "' . $mysql . '" does not exist or is not executable.',
                'error'
        );
      }

      // Does the mysql command path end with "mysql"?
      // We do not want someone to enter "/bin/rm -f /var/www" as the mysql command
      
      if ( ! preg_match( '/\/mysql/', $mysql) )  {
        add_settings_error(
                'Error',
                '05',
                'Error: Invalid mysql command "' . $mysql . '".',
                'error'
        );
      }

      if ( (! is_executable( $mysqldump )) || ($mysqldump == "undefined") ) {
        add_settings_error(
                'Error',
                '05',
                'Error: mysqldump command "' . $mysqldump . '" does not exist or is not executable.',
                'error'
        );
      }

      // Does the mysqldump command path end with "mysqldump"?
      // We do not want someone to enter "/bin/rm -f /var/www" as the mysqldump command
      
      if ( ! preg_match( '/\/mysqldump$/', $mysqldump) )  {
        add_settings_error(
                'Error',
                '05',
                'Error: Invalid mysqldump command "' . $mysqldump . '".',
                'error'
        );
      }


      // if max_backups < 0 it doesn't make sense
      if (  $max_backup_age < 0 ) {
                  add_settings_error(
                          'Error',
                          '06',
                          'Error: Invalid max days to keep backup sets: "' . $max_backup_age . '".',
                          'error'
                  );
               return;
      }

}
