<?php
function cuabr_get_upload_limits() {

    // get php.ini settings related to file upload size limits 
    $max_upload   = cuabr_return_bytes(ini_get('upload_max_filesize'));
    $max_post     = cuabr_return_bytes(ini_get('post_max_size'));
    $memory_limit = cuabr_return_bytes(ini_get('memory_limit'));

    // return the smallest of them, this defines the real limit
    $upload_limit_in_bytes = min($max_upload, $max_post, $memory_limit);
    $upload_limit =  cuabr_formatBytes( $upload_limit_in_bytes ) ;

    $max_file_uploads = ini_get('max_file_uploads');
    $total_upload_limit =  $max_file_uploads * $upload_limit_in_bytes;

    return array ( $upload_limit_in_bytes, $upload_limit, $total_upload_limit);

}

function cuabr_return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last)
    {
        case 'g':
        $val *= 1024;
        case 'm':
        $val *= 1024;
        case 'k':
        $val *= 1024;
    }
    return $val;
}

function cuabr_formatBytes( $bytes, $precision = 0) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    // Uncomment one of the following alternatives
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}
