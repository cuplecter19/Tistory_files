<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;

header('Content-Type: application/json; charset=utf-8');

@ini_set('display_errors', '0');
error_reporting(0);

$HEADER_UPLOAD_DATA_DIR = 'calendar_header';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'error' => 'invalid method'));
    exit;
}

if (!$is_admin) {
    echo json_encode(array('success' => false, 'error' => 'admin required'));
    exit;
}

if (!isset($_FILES['header_file']) || !is_array($_FILES['header_file'])) {
    echo json_encode(array('success' => false, 'error' => 'no file'));
    exit;
}

$file = $_FILES['header_file'];
if (!isset($file['error']) || intval($file['error']) !== UPLOAD_ERR_OK) {
    echo json_encode(array('success' => false, 'error' => 'upload failed'));
    exit;
}

if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
    echo json_encode(array('success' => false, 'error' => 'invalid upload'));
    exit;
}

$size = isset($file['size']) ? intval($file['size']) : 0;
if ($size <= 0 || $size > 5 * 1024 * 1024) {
    echo json_encode(array('success' => false, 'error' => 'max 5MB'));
    exit;
}

$mime = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }
}
if (!$mime && function_exists('getimagesize')) {
    $img_info = @getimagesize($file['tmp_name']);
    if (is_array($img_info) && isset($img_info['mime'])) {
        $mime = $img_info['mime'];
    }
}

$allowed = array(
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp'
);
if (!isset($allowed[$mime])) {
    echo json_encode(array('success' => false, 'error' => 'invalid image type'));
    exit;
}

$upload_dir = rtrim(G5_DATA_PATH, '/').'/'.$HEADER_UPLOAD_DATA_DIR;
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}
if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
    echo json_encode(array('success' => false, 'error' => 'upload dir not writable'));
    exit;
}

$ext = $allowed[$mime];
$rand = '';
if (function_exists('openssl_random_pseudo_bytes')) {
    $rand_bytes = openssl_random_pseudo_bytes(8);
    if ($rand_bytes !== false) {
        $rand = bin2hex($rand_bytes);
    }
}
if ($rand === '') {
    $rand = substr(sha1(uniqid(mt_rand(), true)), 0, 16);
}

$filename = 'header_'.$rand.'.'.$ext;
$save_path = $upload_dir.'/'.$filename;

if (!move_uploaded_file($file['tmp_name'], $save_path)) {
    echo json_encode(array('success' => false, 'error' => 'save failed'));
    exit;
}

$file_url = rtrim(G5_DATA_URL, '/').'/'.$HEADER_UPLOAD_DATA_DIR.'/'.$filename;
echo json_encode(array(
    'success' => true,
    'src' => $file_url,
    'type' => 'file'
));
exit;
