<?php
if (!defined('_GNUBOARD_')) exit;

include_once(__DIR__.'/google_config.php');
include_once(__DIR__.'/google_db.php');

function gcal_token_row($mb_id) {
    global $g5;
    $table = $g5['prefix'].'calendar_google_token';
    return sql_fetch("SELECT * FROM {$table} WHERE mb_id='".sql_real_escape_string($mb_id)."'");
}

function gcal_refresh_access_token($mb_id) {
    global $g5;
    $row = gcal_token_row($mb_id);
    if (!$row || !$row['refresh_token']) return false;

    $post_data = http_build_query(array(
        'client_id' => GCAL_CLIENT_ID,
        'client_secret' => GCAL_CLIENT_SECRET,
        'refresh_token' => $row['refresh_token'],
        'grant_type' => 'refresh_token'
    ));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http != 200 || !$res) return false;
    $tok = json_decode($res, true);
    if (!is_array($tok) || empty($tok['access_token'])) return false;

    $access_token = sql_real_escape_string($tok['access_token']);
    $expires_in = isset($tok['expires_in']) ? intval($tok['expires_in']) : 3600;
    $expires_at = date('Y-m-d H:i:s', time() + $expires_in - 30);

    $table = $g5['prefix'].'calendar_google_token';
    sql_query("UPDATE {$table}
               SET access_token='{$access_token}',
                   expires_at='{$expires_at}',
                   updated_at=NOW()
               WHERE mb_id='".sql_real_escape_string($mb_id)."'");
    return gcal_token_row($mb_id);
}

function gcal_get_valid_access_token($mb_id) {
    $row = gcal_token_row($mb_id);
    if (!$row) return '';
    if (strtotime($row['expires_at']) <= time()) {
        $row = gcal_refresh_access_token($mb_id);
        if (!$row) return '';
    }
    return $row['access_token'];
}

function gcal_api_request($method, $url, $access_token, $body_arr = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $headers = array(
        'Authorization: Bearer '.$access_token,
        'Accept: application/json'
    );

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $json = json_encode($body_arr);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return array('http'=>$http, 'body'=>$res, 'error'=>$err);
}