<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;
include_once(__DIR__.'/google_db.php');

header('Content-Type: application/json; charset=utf-8');

@ini_set('display_errors', '0');
error_reporting(0);

gcal_ensure_tables();

$pref_table = $g5['prefix'].'calendar_user_pref';
$mb_id = $is_member ? $member['mb_id'] : '';

$VALID_THEMES = array('sakura','ocean','melon','kuromi','mocha','lemon');

/* ── GET: 설정 불러오기 (로그인 불필요 – 헤더 이미지는 전역 공유) ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 전역 헤더 이미지는 __global__ 레코드에서 읽기 (비로그인 방문자도 접근 가능)
    $global_row = sql_fetch("SELECT header_image FROM {$pref_table} WHERE mb_id='__global__'");
    $header_image = null;
    if ($global_row && $global_row['header_image']) {
        $header_image = json_decode($global_row['header_image'], true);
    }

    // 테마는 개인별로 저장 (로그인한 경우에만)
    $theme = 'sakura';
    if ($is_member && $mb_id) {
        $user_row = sql_fetch("SELECT theme FROM {$pref_table} WHERE mb_id='".sql_real_escape_string($mb_id)."'");
        if ($user_row && $user_row['theme'] && in_array($user_row['theme'], $VALID_THEMES)) {
            $theme = $user_row['theme'];
        }
    }

    echo json_encode(array(
        'success' => true,
        'theme' => $theme,
        'header_image' => $header_image
    ));
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

    // 테마 저장: 로그인한 회원만 가능 (개인 설정)
    if ($theme) {
        if (!$is_member) {
            echo json_encode(array('success'=>false,'error'=>'login required')); exit;
        }
        $sets_theme = array(
            "theme='".sql_real_escape_string($theme)."'",
            "updated_at=NOW()"
        );
        $existing = sql_fetch("SELECT mb_id FROM {$pref_table} WHERE mb_id='".sql_real_escape_string($mb_id)."'");
        if ($existing && $existing['mb_id']) {
            sql_query("UPDATE {$pref_table} SET ".implode(',', $sets_theme)." WHERE mb_id='".sql_real_escape_string($mb_id)."'");
        } else {
            sql_query("INSERT INTO {$pref_table}
                SET mb_id='".sql_real_escape_string($mb_id)."',
                    theme='".sql_real_escape_string($theme)."',
                    header_image=NULL,
                    updated_at=NOW()");
        }
        echo json_encode(array('success'=>true));
        exit;
    }

    // 헤더 이미지 저장: 관리자만 가능 (전역 공유 설정)
    if ($header_image_raw !== '') {
        if (!$is_admin) {
            echo json_encode(array('success'=>false,'error'=>'admin required')); exit;
        }

        $header_image_json = null;
        if ($header_image_raw !== 'null') {
            $parsed = json_decode($header_image_raw, true);
            if (is_array($parsed) && isset($parsed['src'])) {
                $src = $parsed['src'];
                // Validate src: must be a URL with proper hostname or a data URI
                $is_valid_url = preg_match('/^https?:\/\/[a-zA-Z0-9][\w\.\-]*\.[a-zA-Z]{2,}/i', $src);
                $is_data_uri  = preg_match('/^data:image\//i', $src);
                if ($is_valid_url || $is_data_uri) {
                    $safe = array(
                        'src' => $src,
                        'type' => isset($parsed['type']) && in_array($parsed['type'], array('url','file')) ? $parsed['type'] : 'url',
                        'height' => isset($parsed['height']) ? max(60, min(400, intval($parsed['height']))) : 160,
                        'fit' => isset($parsed['fit']) && in_array($parsed['fit'], array('cover','contain','fill')) ? $parsed['fit'] : 'cover'
                    );
                    $header_image_json = json_encode($safe);
                } else {
                    echo json_encode(array('success'=>false,'error'=>'invalid image src')); exit;
                }
            } else {
                echo json_encode(array('success'=>false,'error'=>'invalid header_image data')); exit;
            }
        }

        // 전역 설정 UPSERT (__global__ 레코드)
        $global_existing = sql_fetch("SELECT mb_id FROM {$pref_table} WHERE mb_id='__global__'");
        $himg_val = $header_image_json ? "'".sql_real_escape_string($header_image_json)."'" : "NULL";
        if ($global_existing && $global_existing['mb_id']) {
            sql_query("UPDATE {$pref_table} SET header_image={$himg_val}, updated_at=NOW() WHERE mb_id='__global__'");
        } else {
            sql_query("INSERT INTO {$pref_table}
                SET mb_id='__global__',
                    theme='sakura',
                    header_image={$himg_val},
                    updated_at=NOW()");
        }

        echo json_encode(array('success'=>true));
        exit;
    }

    echo json_encode(array('success'=>false,'error'=>'no data')); exit;
}

echo json_encode(array('success'=>false,'error'=>'invalid method'));
