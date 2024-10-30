<?php function cuabr_ajax_call() { ?>
        <script type="text/javascript" >
        
        var data;
        jQuery(document).ready(function($) {

             check_status_msg =  document.getElementById('status_msg');
             if ( check_status_msg != null ) {
                document.getElementById('status_msg').innerHTML =  'Checking settings...';

                // The value of 'action' below MUST be the same as the 
                // first part of the name of the callback function.
                // In our case, the callback function's name is "cuabr_action_callback";
                // therefore, the value of 'action' must be "cuabr_action".
                data = {
                        'action'  : 'cuabr_action',
                        'task'    : 'TEST'
                };

                // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                jQuery.post(ajaxurl, data, function(response) {
                        document.getElementById('status_msg').innerHTML =  response;
                        if ( response == 'Done.' ) {
                              document.getElementById('goButton').disabled =  false;
                              document.getElementById('goButton').value =  ' Start Backup ';
                        }
                });

             }

        });
        </script> 
<?php
}

