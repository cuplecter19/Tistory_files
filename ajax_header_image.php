<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
error_reporting(0);

if (!$is_member) {
    echo json_encode(array('success'=>false,'error'=>'login required')); exit;
}

$bo_table = isset($_POST['bo_table']) ? preg_replace('/[^a-z0-9_]/i','',$_POST['bo_table']) : '';
$action   = isset($_POST['action']) ? trim($_POST['action']) : 'save';
$type     = isset($_POST['type']) ? trim($_POST['type']) : 'url';
$fit      = isset($_POST['fit']) ? trim($_POST['fit']) : 'cover';
$height   = isset($_POST['height']) ? intval($_POST['height']) : 160;
$image_url = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';

if (!$bo_table) {
    echo json_encode(array('success'=>false,'error'=>'bo_table required')); exit;
}

if (!defined('G5_DATA_PATH') || !defined('G5_DATA_URL')) {
    echo json_encode(array('success'=>false,'error'=>'data path not available')); exit;
}

$write_table = $g5['write_prefix'].$bo_table;
$chk = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
if (!$chk || !sql_fetch_array($chk)) {
    echo json_encode(array('success'=>false,'error'=>'board table not found')); exit;
}

$height = max(60, min(400, $height));
if (!in_array($fit, array('cover','contain','fill'))) $fit = 'cover';

$data_dir = rtrim(G5_DATA_PATH, '/').'/calendar_header';
$data_url = rtrim(G5_DATA_URL, '/').'/calendar_header';
$meta_file = $data_dir.'/'.$bo_table.'.json';

if (!is_dir($data_dir)) {
    @mkdir($data_dir, 0755, true);
}

if (!is_dir($data_dir) || !is_writable($data_dir)) {
    echo json_encode(array('success'=>false,'error'=>'storage not writable')); exit;
}

function cal_header_read_meta($meta_file) {
    if (!is_file($meta_file)) return null;
    $raw = @file_get_contents($meta_file);
    if ($raw === false || $raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function cal_header_delete_local_file($src, $data_url, $data_dir) {
    if (!$src) return;
    $prefix = rtrim($data_url, '/').'/';
    if (strpos($src, $prefix) !== 0) return;
    $rel = substr($src, strlen($prefix));
    $basename = basename($rel);
    if (!$basename || preg_match('/[^a-zA-Z0-9_\.\-]/', $basename)) return;
    $target = rtrim($data_dir, '/').'/'.$basename;
    if (is_file($target)) @unlink($target);
}

function cal_header_validate_url($url) {
    if (!$url) return false;
    if (!preg_match('/^https?:\/\/.+/i', $url)) return false;
    return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
}

$current = cal_header_read_meta($meta_file);

if ($action === 'remove') {
    if (is_array($current) && isset($current['type']) && $current['type'] === 'file' && isset($current['src'])) {
        cal_header_delete_local_file($current['src'], $data_url, $data_dir);
    }
    if (is_file($meta_file)) @unlink($meta_file);
    echo json_encode(array('success'=>true,'data'=>null)); exit;
}

if ($type !== 'url' && $type !== 'file') {
    echo json_encode(array('success'=>false,'error'=>'invalid type')); exit;
}

$saved_src = '';

if ($type === 'url') {
    if (!cal_header_validate_url($image_url)) {
        echo json_encode(array('success'=>false,'error'=>'invalid image url')); exit;
    }
    if (is_array($current) && isset($current['type']) && $current['type'] === 'file' && isset($current['src'])) {
        cal_header_delete_local_file($current['src'], $data_url, $data_dir);
    }
    $saved_src = $image_url;
} else {
    if (!isset($_FILES['image_file']) || !is_array($_FILES['image_file'])) {
        echo json_encode(array('success'=>false,'error'=>'image file required')); exit;
    }
    $up = $_FILES['image_file'];
    if (!isset($up['error']) || $up['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(array('success'=>false,'error'=>'file upload failed')); exit;
    }
    if (!isset($up['tmp_name']) || !is_uploaded_file($up['tmp_name'])) {
        echo json_encode(array('success'=>false,'error'=>'invalid uploaded file')); exit;
    }
    if (!isset($up['size']) || intval($up['size']) > (5 * 1024 * 1024)) {
        echo json_encode(array('success'=>false,'error'=>'file size limit exceeded')); exit;
    }

    $img_info = @getimagesize($up['tmp_name']);
    if (!$img_info || !isset($img_info[2])) {
        echo json_encode(array('success'=>false,'error'=>'invalid image file')); exit;
    }

    $allowed_types = array(
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif'
    );
    if (defined('IMAGETYPE_WEBP')) $allowed_types[IMAGETYPE_WEBP] = 'webp';
    if (!isset($allowed_types[$img_info[2]])) {
        echo json_encode(array('success'=>false,'error'=>'unsupported image type')); exit;
    }

    $ext = $allowed_types[$img_info[2]];
    $new_name = 'himg_'.$bo_table.'_'.date('YmdHis').'_'.mt_rand(1000,9999).'.'.$ext;
    $target_path = $data_dir.'/'.$new_name;

    if (!@move_uploaded_file($up['tmp_name'], $target_path)) {
        echo json_encode(array('success'=>false,'error'=>'failed to save image')); exit;
    }
    @chmod($target_path, 0644);

    if (is_array($current) && isset($current['type']) && $current['type'] === 'file' && isset($current['src'])) {
        cal_header_delete_local_file($current['src'], $data_url, $data_dir);
    }

    $saved_src = $data_url.'/'.$new_name;
}

$meta = array(
    'src' => $saved_src,
    'type' => $type,
    'height' => $height,
    'fit' => $fit,
    'updated_at' => date('Y-m-d H:i:s')
);

if (@file_put_contents($meta_file, json_encode($meta)) === false) {
    echo json_encode(array('success'=>false,'error'=>'failed to save metadata')); exit;
}
@chmod($meta_file, 0644);

echo json_encode(array('success'=>true,'data'=>array(
    'src' => $meta['src'],
    'type' => $meta['type'],
    'height' => $meta['height'],
    'fit' => $meta['fit']
)));
