<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;
include_once(__DIR__.'/google_db.php');

header('Content-Type: application/json; charset=utf-8');

@ini_set('display_errors', '0');
error_reporting(0);

if (!$is_member) {
    echo json_encode(array('success'=>false,'error'=>'login required')); exit;
}

gcal_ensure_tables();

$pref_table = $g5['prefix'].'calendar_user_pref';
$mb_id = $member['mb_id'];

$VALID_THEMES = array('sakura','ocean','melon','kuromi','mocha','lemon');

/* ── GET: 설정 불러오기 ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $row = sql_fetch("SELECT theme, header_image FROM {$pref_table} WHERE mb_id='".sql_real_escape_string($mb_id)."'");
    if ($row && $row['theme']) {
        $header_image = null;
        if ($row['header_image']) {
            $header_image = json_decode($row['header_image'], true);
        }
        echo json_encode(array(
            'success' => true,
            'theme' => $row['theme'],
            'header_image' => $header_image
        ));
    } else {
        echo json_encode(array('success'=>true,'theme'=>'sakura','header_image'=>null));
    }
    exit;
}

/* ── POST: 설정 저장 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = isset($_POST['theme']) ? trim($_POST['theme']) : '';
    $header_image_raw = isset($_POST['header_image']) ? trim($_POST['header_image']) : '';

    // Validate theme
    if ($theme && !in_array($theme, $VALID_THEMES)) {
        $theme = '';
    }

    // Validate header_image JSON
    $header_image_json = null;
    if ($header_image_raw !== '' && $header_image_raw !== 'null') {
        $parsed = json_decode($header_image_raw, true);
        if (is_array($parsed) && isset($parsed['src'])) {
            // Validate src: must be a URL or data URI
            $src = $parsed['src'];
            $is_valid_url = filter_var($src, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $src);
            $is_data_uri  = preg_match('/^data:image\//i', $src);
            if ($is_valid_url || $is_data_uri) {
                $safe = array(
                    'src' => $src,
                    'type' => isset($parsed['type']) && in_array($parsed['type'], array('url','file')) ? $parsed['type'] : 'url',
                    'height' => isset($parsed['height']) ? max(60, min(400, intval($parsed['height']))) : 160,
                    'fit' => isset($parsed['fit']) && in_array($parsed['fit'], array('cover','contain','fill')) ? $parsed['fit'] : 'cover'
                );
                $header_image_json = json_encode($safe);
            }
        }
    }

    // Build SET clauses for only provided fields
    $sets = array();
    if ($theme) {
        $sets[] = "theme='".sql_real_escape_string($theme)."'";
    }
    if ($header_image_raw !== '') {
        if ($header_image_raw === 'null' || $header_image_json === null) {
            $sets[] = "header_image=NULL";
        } else {
            $sets[] = "header_image='".sql_real_escape_string($header_image_json)."'";
        }
    }

    if (empty($sets)) {
        echo json_encode(array('success'=>false,'error'=>'no data')); exit;
    }

    $sets[] = "updated_at=NOW()";

    // UPSERT: Insert or update
    $existing = sql_fetch("SELECT mb_id FROM {$pref_table} WHERE mb_id='".sql_real_escape_string($mb_id)."'");
    if ($existing && $existing['mb_id']) {
        sql_query("UPDATE {$pref_table} SET ".implode(',', $sets)." WHERE mb_id='".sql_real_escape_string($mb_id)."'");
    } else {
        $theme_val = $theme ? $theme : 'sakura';
        $himg_val  = $header_image_json ? "'".sql_real_escape_string($header_image_json)."'" : "NULL";
        sql_query("INSERT INTO {$pref_table}
            SET mb_id='".sql_real_escape_string($mb_id)."',
                theme='".sql_real_escape_string($theme_val)."',
                header_image=".$himg_val.",
                updated_at=NOW()");
    }

    echo json_encode(array('success'=>true));
    exit;
}

echo json_encode(array('success'=>false,'error'=>'invalid method'));
