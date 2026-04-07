<?php
if (!defined("_GNUBOARD_")) exit;

include_once(__DIR__.'/google_config.php');
include_once(__DIR__.'/google_db.php');
include_once(__DIR__.'/google_calendar_sync.php');

function cal_add_interval_date($date, $type, $i) {
    if ($type == 'daily') return date('Y-m-d', strtotime($date.' +'.$i.' days'));
    if ($type == 'weekly') return date('Y-m-d', strtotime($date.' +'.($i*7).' days'));
    return date('Y-m-d', strtotime($date.' +'.$i.' months')); // monthly
}

function cal_insert_google_event($mb_id, $subject, $content, $start_date, $end_date, $start_time, $end_time, $tz, $color_hex='#EA4335') {
    $access = gcal_get_valid_access_token($mb_id);
    if (!$access) return array('ok'=>false, 'id'=>'', 'err'=>'no_access_token');

    $start_dt = $start_date.'T'.($start_time ? $start_time : '00:00').':00';
    $end_dt   = $end_date.'T'.($end_time ? $end_time : '23:59').':00';

    $payload = array(
        'summary' => $subject,
        'description' => $content,
        'start' => array('dateTime'=>$start_dt, 'timeZone'=>$tz ? $tz : 'Asia/Seoul'),
        'end'   => array('dateTime'=>$end_dt,   'timeZone'=>$tz ? $tz : 'Asia/Seoul')
    );

    $url = 'https://www.googleapis.com/calendar/v3/calendars/'.urlencode(GCAL_CALENDAR_ID).'/events';
    $res = gcal_api_request('POST', $url, $access, $payload);

    if ($res['http'] == 200 || $res['http'] == 201) {
        $d = json_decode($res['body'], true);
        $gid = isset($d['id']) ? $d['id'] : '';
        return array('ok'=>true, 'id'=>$gid, 'err'=>'');
    }
    return array('ok'=>false, 'id'=>'', 'err'=>'http_'.$res['http']);
}

if ($w == '') {
    gcal_ensure_tables();
    $write_table = $g5['write_prefix'].$bo_table;
    $map_table = $g5['prefix'].'calendar_google_map';

    $src = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id='".intval($wr_id)."'");
    if ($src && $src['wr_id']) {
        $start_date = $src['wr_1'] ? $src['wr_1'] : substr($src['wr_datetime'], 0, 10);
        $end_date   = $src['wr_2'] ? $src['wr_2'] : $start_date;
        $start_time = $src['wr_6'] ? $src['wr_6'] : '';
        $end_time   = $src['wr_7'] ? $src['wr_7'] : '';
        $tz         = $src['wr_8'] ? $src['wr_8'] : 'Asia/Seoul';

        // 기본 생성 건도 구글 업로드는 refresh 버튼에서 수행 (요구사항)
        // 반복건도 동일 정책: 로컬 생성만 하고 refresh 시 동기화

        if (isset($_POST['cal_repeat']) && $_POST['cal_repeat'] == '1') {
            $repeat_type  = isset($_POST['cal_repeat_type']) ? $_POST['cal_repeat_type'] : 'weekly'; // daily/weekly/monthly
            $repeat_count = isset($_POST['cal_repeat_count']) ? intval($_POST['cal_repeat_count']) : 0;

            if (!in_array($repeat_type, array('daily','weekly','monthly'))) $repeat_type = 'weekly';

            if ($repeat_count > 0 && $repeat_count <= 365) {
                $diff_days = 0;
                if ($end_date && $end_date != $start_date) {
                    $diff_days = (int)((strtotime($end_date) - strtotime($start_date)) / 86400);
                }

                for ($i=1; $i<=$repeat_count; $i++) {
                    $new_start = cal_add_interval_date($start_date, $repeat_type, $i);
                    $new_end   = $diff_days > 0 ? date('Y-m-d', strtotime($new_start.' +'.$diff_days.' days')) : $new_start;

                    sql_query("INSERT INTO {$write_table}
                        SET wr_num=(SELECT IFNULL(MIN(t.wr_num),0)-1 FROM {$write_table} t),
                            wr_reply='',
                            wr_comment=0,
                            wr_comment_reply='',
                            ca_name='".sql_real_escape_string($src['ca_name'])."',
                            wr_option='',
                            wr_subject='".sql_real_escape_string($src['wr_subject'])."',
                            wr_content='".sql_real_escape_string($src['wr_content'])."',
                            wr_link1='',
                            wr_link2='',
                            wr_hit=0, wr_good=0, wr_nogood=0,
                            mb_id='".sql_real_escape_string($src['mb_id'])."',
                            wr_name='".sql_real_escape_string($src['wr_name'])."',
                            wr_password='".sql_real_escape_string($src['wr_password'])."',
                            wr_email='".sql_real_escape_string($src['wr_email'])."',
                            wr_homepage='',
                            wr_datetime=NOW(),
                            wr_last=NOW(),
                            wr_ip='".sql_real_escape_string($_SERVER['REMOTE_ADDR'])."',
                            wr_1='".sql_real_escape_string($new_start)."',
                            wr_2='".sql_real_escape_string($new_end)."',
                            wr_3='".sql_real_escape_string($src['wr_3'])."',
                            wr_4='',
                            wr_5='local',
                            wr_6='".sql_real_escape_string($start_time)."',
                            wr_7='".sql_real_escape_string($end_time)."',
                            wr_8='".sql_real_escape_string($tz)."',
                            wr_9='FREQ=".strtoupper($repeat_type).";COUNT=".intval($repeat_count)."',
                            wr_is_comment=0");
                    $new_wr_id = sql_insert_id();
                    if ($new_wr_id) {
                        sql_query("UPDATE {$write_table} SET wr_parent='{$new_wr_id}' WHERE wr_id='{$new_wr_id}'");
                    }
                }
            }
        }

        sql_query("UPDATE {$g5['board_table']}
                   SET bo_count_write=(SELECT COUNT(*) FROM {$write_table} WHERE wr_is_comment=0)
                   WHERE bo_table='".sql_real_escape_string($bo_table)."'");
    }
}