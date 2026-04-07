<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;

include_once(__DIR__.'/google_config.php');
include_once(__DIR__.'/google_db.php');

if (!$is_member) {
    alert('로그인이 필요합니다.');
}

gcal_ensure_tables();

$state = md5(uniqid('', true));
set_session('gcal_oauth_state', $state);
set_session('gcal_oauth_bo_table', isset($_GET['bo_table']) ? preg_replace('/[^a-z0-9_]/i','',$_GET['bo_table']) : '');

$scope = urlencode('https://www.googleapis.com/auth/calendar');
$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth'
    .'?client_id='.urlencode(GCAL_CLIENT_ID)
    .'&redirect_uri='.urlencode(GCAL_REDIRECT_URI)
    .'&response_type=code'
    .'&access_type=offline'
    .'&prompt=consent'
    .'&scope='.$scope
    .'&state='.$state;

goto_url($auth_url);