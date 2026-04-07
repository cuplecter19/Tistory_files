<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;

include_once(__DIR__.'/google_config.php');
include_once(__DIR__.'/google_db.php');

if (!$is_member) {
    alert('로그인이 필요합니다.');
}

gcal_ensure_tables();

$state = isset($_GET['state']) ? $_GET['state'] : '';
$code  = isset($_GET['code']) ? $_GET['code'] : '';
$saved_state = get_session('gcal_oauth_state');
$bo_table = get_session('gcal_oauth_bo_table');

if (!$state || !$saved_state || $state !== $saved_state || !$code) {
    alert('OAuth 인증이 올바르지 않습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table);
}

$token_url = 'https://oauth2.googleapis.com/token';
$post_data = http_build_query(array(
    'code' => $code,
    'client_id' => GCAL_CLIENT_ID,
    'client_secret' => GCAL_CLIENT_SECRET,
    'redirect_uri' => GCAL_REDIRECT_URI,
    'grant_type' => 'authorization_code'
));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$res = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($http != 200 || !$res) {
    alert('토큰 발급 실패: '.$http.' '.$err, G5_BBS_URL.'/board.php?bo_table='.$bo_table);
}

$tok = json_decode($res, true);
if (!is_array($tok) || empty($tok['access_token'])) {
    alert('토큰 응답 파싱 실패', G5_BBS_URL.'/board.php?bo_table='.$bo_table);
}

$mb_id = $member['mb_id'];
$access_token = sql_real_escape_string($tok['access_token']);
$refresh_token = isset($tok['refresh_token']) ? sql_real_escape_string($tok['refresh_token']) : '';
$expires_in = isset($tok['expires_in']) ? intval($tok['expires_in']) : 3600;
$expires_at = date('Y-m-d H:i:s', time() + $expires_in - 30);
$token_type = isset($tok['token_type']) ? sql_real_escape_string($tok['token_type']) : '';
$scope = isset($tok['scope']) ? sql_real_escape_string($tok['scope']) : '';

$table = $g5['prefix'].'calendar_google_token';
$row = sql_fetch("SELECT id FROM {$table} WHERE mb_id='".sql_real_escape_string($mb_id)."'");

if ($row && $row['id']) {
    $set_refresh = $refresh_token ? ", refresh_token='{$refresh_token}'" : "";
    sql_query("UPDATE {$table}
               SET access_token='{$access_token}',
                   expires_at='{$expires_at}',
                   token_type='{$token_type}',
                   scope='{$scope}',
                   updated_at=NOW()
                   {$set_refresh}
               WHERE mb_id='".sql_real_escape_string($mb_id)."'");
} else {
    sql_query("INSERT INTO {$table}
               SET mb_id='".sql_real_escape_string($mb_id)."',
                   access_token='{$access_token}',
                   refresh_token='{$refresh_token}',
                   expires_at='{$expires_at}',
                   token_type='{$token_type}',
                   scope='{$scope}',
                   updated_at=NOW()");
}

goto_url(G5_BBS_URL.'/board.php?bo_table='.$bo_table);