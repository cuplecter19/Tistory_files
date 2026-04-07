<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;

include_once(__DIR__.'/google_config.php');
include_once(__DIR__.'/google_db.php');
include_once(__DIR__.'/google_calendar_sync.php');

$bo_table  = isset($_GET['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['bo_table']) : '';
$cal_year  = isset($_GET['cal_year']) ? intval($_GET['cal_year']) : (int)date('Y');
$cal_month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : (int)date('n');
$is_ajax   = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$bo_table) {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success'=>false,'error'=>'bo_table required'));
    }
    exit;
}

gcal_ensure_tables();
if (!$is_member) {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success'=>false,'error'=>'login required'));
    }
    exit;
}

$access_token = gcal_get_valid_access_token($member['mb_id']);
if (!$access_token) {
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success'=>false,'need_auth'=>true,'auth_url'=>$_skin_url.'/google_oauth_start.php?bo_table='.$bo_table));
    }
    goto_url($_skin_url.'/google_oauth_start.php?bo_table='.$bo_table);
}

$month_str = str_pad($cal_month, 2, '0', STR_PAD_LEFT);
$time_min  = $cal_year . '-' . $month_str . '-01T00:00:00+09:00';
$days_in_month = (int)date('t', mktime(0, 0, 0, $cal_month, 1, $cal_year));
$time_max  = $cal_year . '-' . $month_str . '-' . str_pad($days_in_month, 2, '0', STR_PAD_LEFT) . 'T23:59:59+09:00';

$url = 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode(GCAL_CALENDAR_ID).'/events'
      .'?timeMin='.urlencode(gmdate('c', strtotime($time_min)))
      .'&timeMax='.urlencode(gmdate('c', strtotime($time_max)))
      .'&singleEvents=true&orderBy=startTime&maxResults=250';

$res = gcal_api_request('GET', $url, $access_token);
$events = array();
$error = '';

if ($res['http'] == 200 && $res['body']) {
    $data = json_decode($res['body'], true);
    if (isset($data['items']) && is_array($data['items'])) {
        $events = $data['items'];
    }
} else {
    $error = 'Google API Error: HTTP '.$res['http'].' '.$res['error'];
}

if (!$error) {
    $write_table = $g5['write_prefix'].$bo_table;
    $map_table = $g5['prefix'].'calendar_google_map';

    foreach ($events as $ge) {
        if (empty($ge['id'])) continue;
        $gid = sql_real_escape_string($ge['id']);
        $summary = isset($ge['summary']) ? $ge['summary'] : '(제목없음)';
        $desc = isset($ge['description']) ? $ge['description'] : '';
        $colorId = isset($ge['colorId']) ? $ge['colorId'] : '';
        $startRaw = isset($ge['start']['dateTime']) ? $ge['start']['dateTime'] : (isset($ge['start']['date']) ? $ge['start']['date'] : '');
        $endRaw   = isset($ge['end']['dateTime']) ? $ge['end']['dateTime'] : (isset($ge['end']['date']) ? $ge['end']['date'] : '');
        if (!$startRaw) continue;

        $startDate = substr($startRaw, 0, 10);
        $endDate = $endRaw ? substr($endRaw, 0, 10) : $startDate;

        $map = sql_fetch("SELECT * FROM {$map_table} WHERE bo_table='".sql_real_escape_string($bo_table)."' AND google_event_id='{$gid}'");
        if ($map && $map['wr_id']) {
            sql_query("UPDATE {$write_table}
                       SET wr_subject='".sql_real_escape_string($summary)."',
                           wr_content='".sql_real_escape_string($desc)."',
                           wr_1='".sql_real_escape_string($startDate)."',
                           wr_2='".sql_real_escape_string($endDate)."',
                           wr_3='#EA4335',
                           wr_5='google',
                           wr_8='Asia/Seoul',
                           wr_9='',
                           wr_last=NOW()
                       WHERE wr_id='".intval($map['wr_id'])."'");
        } else {
            sql_query("INSERT INTO {$write_table}
                SET wr_num=(SELECT IFNULL(MIN(t.wr_num),0)-1 FROM {$write_table} t),
                    wr_reply='',
                    wr_comment=0,
                    wr_comment_reply='',
                    ca_name='',
                    wr_option='',
                    wr_subject='".sql_real_escape_string($summary)."',
                    wr_content='".sql_real_escape_string($desc)."',
                    wr_link1='',
                    wr_link2='',
                    wr_hit=0, wr_good=0, wr_nogood=0,
                    mb_id='".sql_real_escape_string($member['mb_id'])."',
                    wr_name='Google Sync',
                    wr_password='',
                    wr_email='',
                    wr_homepage='',
                    wr_datetime=NOW(),
                    wr_last=NOW(),
                    wr_ip='".sql_real_escape_string($_SERVER['REMOTE_ADDR'])."',
                    wr_1='".sql_real_escape_string($startDate)."',
                    wr_2='".sql_real_escape_string($endDate)."',
                    wr_3='#EA4335',
                    wr_4='{$gid}',
                    wr_5='google',
                    wr_6='',
                    wr_7='',
                    wr_8='Asia/Seoul',
                    wr_9='',
                    wr_is_comment=0");
            $new_wr_id = sql_insert_id();
            if ($new_wr_id) {
                sql_query("UPDATE {$write_table} SET wr_parent='{$new_wr_id}' WHERE wr_id='{$new_wr_id}'");
                sql_query("INSERT INTO {$map_table}
                           SET bo_table='".sql_real_escape_string($bo_table)."',
                               wr_id='{$new_wr_id}',
                               google_event_id='{$gid}',
                               sync_source='google',
                               updated_at=NOW()");
            }
        }
    }

    sql_query("UPDATE {$g5['board_table']}
               SET bo_count_write=(SELECT COUNT(*) FROM {$write_table} WHERE wr_is_comment=0)
               WHERE bo_table='".sql_real_escape_string($bo_table)."'");
}

if (!isset($_SESSION)) session_start();
$_SESSION['google_cal_events'] = $events;
$_SESSION['google_cal_error']  = $error;
$_SESSION['google_cal_time']   = time();

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success' => empty($error),
        'count'   => count($events),
        'error'   => $error,
        'updated' => date('Y-m-d H:i:s')
    ));
    exit;
}

$redirect_url = G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&cal_year='.$cal_year.'&cal_month='.$cal_month;
header('Location: '.$redirect_url);
exit;