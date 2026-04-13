<?php
include_once('./../../../common.php');
if (!defined('_GNUBOARD_')) exit;
include_once(__DIR__.'/google_config.php');
include_once(__DIR__.'/google_db.php');
include_once(__DIR__.'/google_calendar_sync.php');

$bo_table  = isset($_GET['bo_table']) ? preg_replace('/[^a-z0-9_]/i', '', $_GET['bo_table']) : '';
$cal_year  = isset($_GET['cal_year']) ? intval($_GET['cal_year']) : intval(date('Y'));
$cal_month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : intval(date('n'));
$is_ajax   = isset($_GET['ajax']) && $_GET['ajax']=='1';

if (!$bo_table) exit;
if (!$is_member) exit;

gcal_ensure_tables();

$access = gcal_get_valid_access_token($member['mb_id']);
if (!$access) {
    if ($is_ajax) {
      header('Content-Type: application/json; charset=utf-8');
      echo json_encode(array('success'=>false,'need_auth'=>true,'auth_url'=>str_replace('http://','https://',$board_skin_url).'/google_oauth_start.php?bo_table='.$bo_table));
      exit;
    }
    goto_url(str_replace('http://','https://',$board_skin_url).'/google_oauth_start.php?bo_table='.$bo_table);
}

$month_str = str_pad($cal_month,2,'0',STR_PAD_LEFT);
$days_in_month = intval(date('t', mktime(0,0,0,$cal_month,1,$cal_year)));
$write_table = $g5['write_prefix'].$bo_table;
$map_table = $g5['prefix'].'calendar_google_map';

$push_new_count = 0;
$push_update_count = 0;
$push_error = '';

/**
 * 로컬 이벤트를 구글 캘린더 API용 payload로 변환하는 헬퍼 함수
 */
function gcal_build_event_payload($row) {
    $sdate = $row['wr_1'] ? $row['wr_1'] : date('Y-m-d');
    $edate = $row['wr_2'] ? $row['wr_2'] : $sdate;
    $stime = $row['wr_6'] ? $row['wr_6'] : '';
    $etime = $row['wr_7'] ? $row['wr_7'] : '';
    $tz    = $row['wr_8'] ? $row['wr_8'] : 'Asia/Seoul';

    $payload = array(
        'summary'     => $row['wr_subject'] ? $row['wr_subject'] : '(제목없음)',
        'description' => $row['wr_content'] ? $row['wr_content'] : ''
    );

    // 시간이 있으면 dateTime, 없으면 종일(date) 이벤트로 처리
    if ($stime !== '') {
        if (!$etime) {
            // 종료 시간이 없으면 시작 시간 + 1시간
            $end_dt = new DateTime($edate.'T'.$stime.':00');
            $end_dt->modify('+1 hour');
            $etime = $end_dt->format('H:i');
        }
        $payload['start'] = array('dateTime' => $sdate.'T'.$stime.':00', 'timeZone' => $tz);
        $payload['end']   = array('dateTime' => $edate.'T'.$etime.':00', 'timeZone' => $tz);
    } else {
        $payload['start'] = array('date' => $sdate);
        // 구글 종일 이벤트: end.date는 종료일 다음 날
        $end_dt = new DateTime($edate);
        $end_dt->modify('+1 day');
        $payload['end']   = array('date' => $end_dt->format('Y-m-d'));
    }

    return $payload;
}

/* ======================================================
   1-A) LOCAL -> GOOGLE: 신규 로컬 일정 Push (POST)
   ====================================================== */
$lq = "SELECT wr_id, wr_subject, wr_content, wr_1, wr_2, wr_4, wr_5, wr_6, wr_7, wr_8
       FROM {$write_table}
       WHERE wr_is_comment=0 AND (wr_4='' OR wr_4 IS NULL) AND (wr_5='local' OR wr_5='' OR wr_5 IS NULL)";
$lr = sql_query($lq);
while($row = sql_fetch_array($lr)){
    $payload = gcal_build_event_payload($row);

    $res = gcal_api_request('POST', 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode(GCAL_PRIMARY_CALENDAR_ID).'/events', $access, $payload);
    if ($res['http']==200 || $res['http']==201){
        $d = json_decode($res['body'], true);
        if (!empty($d['id'])){
            $gid = sql_real_escape_string($d['id']);
            sql_query("UPDATE {$write_table} SET wr_4='{$gid}', wr_5='both', wr_last=NOW() WHERE wr_id='".intval($row['wr_id'])."'");
            sql_query("INSERT INTO {$map_table} SET bo_table='".sql_real_escape_string($bo_table)."', wr_id='".intval($row['wr_id'])."', google_event_id='{$gid}', sync_source='both', updated_at=NOW()
                       ON DUPLICATE KEY UPDATE google_event_id=VALUES(google_event_id), sync_source='both', updated_at=NOW()");
            $push_new_count++;
        }
    } else {
        $push_error .= 'POST wr_id='.$row['wr_id'].' HTTP '.$res['http'].'; ';
    }
}

/* ======================================================
   1-B) LOCAL -> GOOGLE: 수정된 로컬 일정 Push (PATCH)
   ====================================================== */
$mq = "SELECT wr_id, wr_subject, wr_content, wr_1, wr_2, wr_4, wr_5, wr_6, wr_7, wr_8
       FROM {$write_table}
       WHERE wr_is_comment=0 AND wr_4 != '' AND wr_4 IS NOT NULL AND wr_5='local_modified'";
$mr = sql_query($mq);
while($row = sql_fetch_array($mr)){
    $payload = gcal_build_event_payload($row);
    $event_id = $row['wr_4'];

    $patch_url = 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode(GCAL_PRIMARY_CALENDAR_ID).'/events/'.urlencode($event_id);
    $res = gcal_api_request('PATCH', $patch_url, $access, $payload);
    if ($res['http']==200){
        sql_query("UPDATE {$write_table} SET wr_5='both', wr_last=NOW() WHERE wr_id='".intval($row['wr_id'])."'");
        sql_query("UPDATE {$map_table} SET sync_source='both', updated_at=NOW() WHERE bo_table='".sql_real_escape_string($bo_table)."' AND wr_id='".intval($row['wr_id'])."'");
        $push_update_count++;
    } else {
        $push_error .= 'PATCH wr_id='.$row['wr_id'].' HTTP '.$res['http'].'; ';
    }
}

/* ======================================================
   2) GOOGLE -> LOCAL (다중 캘린더 조회 + 공휴일 색상)
   ====================================================== */
// 변경 전 (해당 월만)
// $time_min = gmdate('c', strtotime($cal_year.'-'.$month_str.'-01 00:00:00 +0900'));
// $time_max = gmdate('c', strtotime($cal_year.'-'.$month_str.'-'.$days_in_month.' 23:59:59 +0900'));

// 변경 후 (해당 연도 전체)
$time_min = gmdate('c', strtotime($cal_year.'-01-01 00:00:00 +0900'));
$time_max = gmdate('c', strtotime($cal_year.'-12-31 23:59:59 +0900'));

// 구글 캘린더 색상 ID → HEX 매핑
$google_color_map = array(
    '1'=>'#7986CB','2'=>'#33B679','3'=>'#8E24AA','4'=>'#E67C73',
    '5'=>'#F6BF26','6'=>'#F4511E','7'=>'#039BE5','8'=>'#616161',
    '9'=>'#3F51B5','10'=>'#0B8043','11'=>'#D50000'
);
$google_default_color = '#4285F4';

$total_event_count = 0;
$error = '';

// 각 소스 캘린더를 순회하며 동기화
foreach ($GCAL_SOURCE_CALENDARS as $source_cal_id) {

    $is_holiday_cal = in_array($source_cal_id, $GCAL_HOLIDAY_CALENDARS);

    $url = 'https://www.googleapis.com/calendar/v3/calendars/'
         . urlencode($source_cal_id)
         . '/events?timeMin='.urlencode($time_min)
         . '&timeMax='.urlencode($time_max)
         . '&singleEvents=true&orderBy=startTime&maxResults=250';

    $gr = gcal_api_request('GET', $url, $access);

    $events = array();
    if ($gr['http']==200 && $gr['body']){
        $dd = json_decode($gr['body'], true);
        if (isset($dd['items']) && is_array($dd['items'])) $events = $dd['items'];
    } else {
        $error .= 'API Error HTTP '.$gr['http'].' for '.$source_cal_id.'; ';
        continue;
    }

    $total_event_count += count($events);

    foreach ($events as $ge) {
        if (empty($ge['id'])) continue;

        // 공휴일 캘린더 + 일반 캘린더의 이벤트 ID 충돌 방지를 위해 prefix 부착
        $raw_gid = $ge['id'];
        $gid_key = $is_holiday_cal ? ('holiday_'.$raw_gid) : $raw_gid;
        $gid = sql_real_escape_string($gid_key);

        $summary = isset($ge['summary']) ? $ge['summary'] : '(제목없음)';
        $desc    = isset($ge['description']) ? $ge['description'] : '';

        $sraw = isset($ge['start']['dateTime']) ? $ge['start']['dateTime'] : (isset($ge['start']['date']) ? $ge['start']['date'] : '');
        $eraw = isset($ge['end']['dateTime']) ? $ge['end']['dateTime'] : (isset($ge['end']['date']) ? $ge['end']['date'] : '');
        if (!$sraw) continue;

        $sdate = substr($sraw,0,10);
        $edate = $eraw ? substr($eraw,0,10) : $sdate;

        // 종일 이벤트의 종료일 보정 (Google은 종일 이벤트 end를 다음날로 반환)
        if (isset($ge['start']['date']) && !isset($ge['start']['dateTime'])) {
            if ($edate > $sdate) {
                $edate = date('Y-m-d', strtotime($edate.' -1 day'));
            }
        }

        $stime = (strlen($sraw)>=16) ? substr($sraw,11,5) : '';
        $etime = (strlen($eraw)>=16) ? substr($eraw,11,5) : '';

        // 색상 결정: 공휴일 캘린더이면 무조건 빨간색
        if ($is_holiday_cal) {
            $event_color = GCAL_HOLIDAY_COLOR;
        } else {
            $event_color = null;
            if (isset($ge['colorId']) && isset($google_color_map[$ge['colorId']])) {
                $event_color = $google_color_map[$ge['colorId']];
            }
        }

        // sync_source: 공휴일은 'holiday', 일반은 'both'
        $sync_source = $is_holiday_cal ? 'holiday' : 'both';

        $m = sql_fetch("SELECT * FROM {$map_table} WHERE bo_table='".sql_real_escape_string($bo_table)."' AND google_event_id='{$gid}'");

        if ($m && $m['wr_id']) {
            // 로컬에서 수정 중인 이벤트는 덮어쓰지 않음 (LOCAL -> GOOGLE Push 우선)
            $existing_row = sql_fetch("SELECT wr_3, wr_5 FROM {$write_table} WHERE wr_id='".intval($m['wr_id'])."'");
            if ($existing_row && $existing_row['wr_5'] === 'local_modified') {
                continue;
            }

            // 기존 로컬 일정 업데이트
            if ($is_holiday_cal) {
                // 공휴일은 항상 빨간색 강제
                $keep_color = GCAL_HOLIDAY_COLOR;
            } else {
                $keep_color = ($existing_row && $existing_row['wr_3']) ? $existing_row['wr_3'] : ($event_color ? $event_color : $google_default_color);
            }

            sql_query("UPDATE {$write_table}
                       SET wr_subject='".sql_real_escape_string($summary)."',
                           wr_content='".sql_real_escape_string($desc)."',
                           wr_1='".sql_real_escape_string($sdate)."',
                           wr_2='".sql_real_escape_string($edate)."',
                           wr_3='".sql_real_escape_string($keep_color)."',
                           wr_5='".sql_real_escape_string($sync_source)."',
                           wr_6='".sql_real_escape_string($stime)."',
                           wr_7='".sql_real_escape_string($etime)."',
                           wr_8='Asia/Seoul',
                           wr_last=NOW()
                       WHERE wr_id='".intval($m['wr_id'])."'");
        } else {
            // 새 이벤트 삽입
            $new_color = $is_holiday_cal ? GCAL_HOLIDAY_COLOR : ($event_color ? $event_color : $google_default_color);

            sql_query("INSERT INTO {$write_table}
                SET wr_num=(SELECT IFNULL(MIN(t.wr_num),0)-1 FROM {$write_table} t),
                    wr_reply='', wr_comment=0, wr_comment_reply='',
                    ca_name='', wr_option='',
                    wr_subject='".sql_real_escape_string($summary)."',
                    wr_content='".sql_real_escape_string($desc)."',
                    wr_link1='', wr_link2='',
                    wr_hit=0, wr_good=0, wr_nogood=0,
                    mb_id='".sql_real_escape_string($member['mb_id'])."',
                    wr_name='".($is_holiday_cal ? 'Holiday' : 'Google Sync')."',
                    wr_password='', wr_email='', wr_homepage='',
                    wr_datetime=NOW(), wr_last=NOW(),
                    wr_ip='".sql_real_escape_string($_SERVER['REMOTE_ADDR'])."',
                    wr_1='".sql_real_escape_string($sdate)."',
                    wr_2='".sql_real_escape_string($edate)."',
                    wr_3='".sql_real_escape_string($new_color)."',
                    wr_4='{$gid}',
                    wr_5='".sql_real_escape_string($sync_source)."',
                    wr_6='".sql_real_escape_string($stime)."',
                    wr_7='".sql_real_escape_string($etime)."',
                    wr_8='Asia/Seoul',
                    wr_9='',
                    wr_is_comment=0");
            $nid = sql_insert_id();
            if ($nid) {
                sql_query("UPDATE {$write_table} SET wr_parent='{$nid}' WHERE wr_id='{$nid}'");
                sql_query("INSERT INTO {$map_table}
                           SET bo_table='".sql_real_escape_string($bo_table)."',
                               wr_id='{$nid}',
                               google_event_id='{$gid}',
                               sync_source='".sql_real_escape_string($sync_source)."',
                               updated_at=NOW()");
            }
        }
    }
}

// 글 수 갱신
sql_query("UPDATE {$g5['board_table']}
           SET bo_count_write=(SELECT COUNT(*) FROM {$write_table} WHERE wr_is_comment=0)
           WHERE bo_table='".sql_real_escape_string($bo_table)."'");

if ($is_ajax){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'success'=>empty($error) && empty($push_error),
        'count'=>$total_event_count,
        'pushed_new'=>$push_new_count,
        'pushed_updated'=>$push_update_count,
        'error'=>$error,
        'push_error'=>$push_error,
        'updated'=>date('Y-m-d H:i:s')
    ));
    exit;
}
goto_url(G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&cal_year='.$cal_year.'&cal_month='.$cal_month);
