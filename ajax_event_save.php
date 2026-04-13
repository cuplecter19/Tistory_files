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
$w        = isset($_POST['w']) ? $_POST['w'] : '';
$wr_id    = isset($_POST['wr_id']) ? intval($_POST['wr_id']) : 0;

if (!$bo_table) { echo json_encode(array('success'=>false,'error'=>'bo_table required')); exit; }

$write_table = $g5['write_prefix'].$bo_table;

$chk = sql_query("SHOW TABLES LIKE '{$write_table}'", false);
if (!$chk || !sql_fetch_array($chk)) {
    echo json_encode(array('success'=>false,'error'=>'board table not found: '.$bo_table)); exit;
}

$wr_subject = isset($_POST['wr_subject']) ? trim($_POST['wr_subject']) : '';
$wr_content = isset($_POST['wr_content']) ? trim($_POST['wr_content']) : '';
$wr_1       = isset($_POST['wr_1']) ? preg_replace('/[^0-9\-]/','',$_POST['wr_1']) : date('Y-m-d');
$wr_2       = isset($_POST['wr_2']) && $_POST['wr_2'] ? preg_replace('/[^0-9\-]/','',$_POST['wr_2']) : $wr_1;
$wr_3       = isset($_POST['wr_3']) ? trim($_POST['wr_3']) : '#3B82F6';
$wr_6       = isset($_POST['wr_6']) ? preg_replace('/[^0-9:]/','',$_POST['wr_6']) : '';
$wr_7       = isset($_POST['wr_7']) ? preg_replace('/[^0-9:]/','',$_POST['wr_7']) : '';
$wr_8       = 'Asia/Seoul';

$cal_goal     = isset($_POST['cal_goal']) && $_POST['cal_goal']=='1';
$cal_dday     = isset($_POST['cal_dday']) && $_POST['cal_dday']=='1';
$cal_widget   = isset($_POST['cal_widget']) && $_POST['cal_widget']=='1';
$cal_repeat   = isset($_POST['cal_repeat']) && $_POST['cal_repeat']=='1';
$repeat_type  = isset($_POST['cal_repeat_type']) ? $_POST['cal_repeat_type'] : 'weekly';
$repeat_count = isset($_POST['cal_repeat_count']) ? intval($_POST['cal_repeat_count']) : 0;
if (!in_array($repeat_type, array('daily','weekly','monthly'))) $repeat_type='weekly';
// Goal과 D-day는 동시 선택 불가
if ($cal_goal && $cal_dday) $cal_dday = false;

if ($wr_subject === '') {
    echo json_encode(array('success'=>false,'error'=>'subject required')); exit;
}
if (!preg_match('/^\#[0-9a-fA-F]{6}$/', $wr_3)) $wr_3 = '#3B82F6';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $wr_1)) {
    echo json_encode(array('success'=>false,'error'=>'invalid start date')); exit;
}
if ($wr_2 && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $wr_2)) $wr_2 = $wr_1;

function cal_add_date($date, $type, $i) {
    if ($type=='daily') return date('Y-m-d', strtotime($date.' +'.$i.' days'));
    if ($type=='weekly') return date('Y-m-d', strtotime($date.' +'.($i*7).' days'));
    return date('Y-m-d', strtotime($date.' +'.$i.' months'));
}

/* wr_9 조합: GOAL/DDAY/WIDGET 플래그 + REPEAT 정보 */
function build_wr9($is_goal, $is_dday = false, $is_widget = false, $is_repeat = false, $repeat_type = '', $repeat_count = 0) {
    $parts = array();
    if ($is_goal) $parts[] = 'GOAL=1';
    if ($is_dday) $parts[] = 'DDAY=1';
    if ($is_widget) $parts[] = 'WIDGET=1';
    if ($is_repeat && $repeat_count > 0) {
        $parts[] = 'FREQ='.strtoupper($repeat_type).';COUNT='.intval($repeat_count);
    }
    return implode(';', $parts);
}

/* ── 수정 모드 ── */
if ($w === 'u' && $wr_id > 0) {
    $row = sql_fetch("SELECT wr_id, mb_id, wr_9 FROM {$write_table} WHERE wr_id='".intval($wr_id)."' AND wr_is_comment=0");
    if (!$row || !$row['wr_id']) {
        echo json_encode(array('success'=>false,'error'=>'event not found')); exit;
    }

    // 기존 wr_9에서 GOAL 이외의 정보 유지 가능, 여기서는 새로 구성
    $new_wr9 = build_wr9($cal_goal, $cal_dday, $cal_widget, $cal_repeat, $repeat_type, $repeat_count);

    sql_query("UPDATE {$write_table}
               SET wr_subject='".sql_real_escape_string($wr_subject)."',
                   wr_content='".sql_real_escape_string($wr_content)."',
                   wr_1='".sql_real_escape_string($wr_1)."',
                   wr_2='".sql_real_escape_string($wr_2)."',
                   wr_3='".sql_real_escape_string($wr_3)."',
                   wr_6='".sql_real_escape_string($wr_6)."',
                   wr_7='".sql_real_escape_string($wr_7)."',
                   wr_8='".sql_real_escape_string($wr_8)."',
                   wr_9='".sql_real_escape_string($new_wr9)."',
                   wr_5=CASE
                       WHEN wr_4 != '' AND wr_5 IN ('google','both') THEN 'local_modified'
                       WHEN wr_5='' OR wr_5 IS NULL THEN 'local'
                       ELSE wr_5
                   END,
                   wr_last=NOW()
               WHERE wr_id='".intval($wr_id)."'");

    $created_ids = array();
    if ($cal_repeat && $repeat_count > 0 && $repeat_count <= 365) {
        $diff_days = intval((strtotime($wr_2) - strtotime($wr_1)) / 86400);
        if ($diff_days < 0) $diff_days = 0;

        for ($i=1; $i<=$repeat_count; $i++) {
            $ns = cal_add_date($wr_1, $repeat_type, $i);
            $ne = $diff_days>0 ? date('Y-m-d', strtotime($ns.' +'.$diff_days.' days')) : $ns;
            $repeat_wr9 = build_wr9($cal_goal, $cal_dday, $cal_widget, true, $repeat_type, $repeat_count);

            sql_query("INSERT INTO {$write_table}
                SET wr_num=(SELECT IFNULL(MIN(t.wr_num),0)-1 FROM {$write_table} t),
                    wr_reply='', wr_comment=0, wr_comment_reply='',
                    ca_name='', wr_option='',
                    wr_subject='".sql_real_escape_string($wr_subject)."',
                    wr_content='".sql_real_escape_string($wr_content)."',
                    wr_link1='', wr_link2='',
                    wr_hit=0, wr_good=0, wr_nogood=0,
                    mb_id='".sql_real_escape_string($member['mb_id'])."',
                    wr_name='".sql_real_escape_string($member['mb_nick'])."',
                    wr_password='', wr_email='', wr_homepage='',
                    wr_datetime=NOW(), wr_last=NOW(),
                    wr_ip='".sql_real_escape_string($_SERVER['REMOTE_ADDR'])."',
                    wr_1='".sql_real_escape_string($ns)."',
                    wr_2='".sql_real_escape_string($ne)."',
                    wr_3='".sql_real_escape_string($wr_3)."',
                    wr_4='', wr_5='local',
                    wr_6='".sql_real_escape_string($wr_6)."',
                    wr_7='".sql_real_escape_string($wr_7)."',
                    wr_8='".sql_real_escape_string($wr_8)."',
                    wr_9='".sql_real_escape_string($repeat_wr9)."',
                    wr_is_comment=0");
            $nid = sql_insert_id();
            if ($nid) {
                sql_query("UPDATE {$write_table} SET wr_parent='{$nid}' WHERE wr_id='{$nid}'");
                $created_ids[] = $nid;
            }
        }
    }

    sql_query("UPDATE {$g5['board_table']}
               SET bo_count_write=(SELECT COUNT(*) FROM {$write_table} WHERE wr_is_comment=0)
               WHERE bo_table='".sql_real_escape_string($bo_table)."'");

    echo json_encode(array('success'=>true,'mode'=>'update','wr_id'=>$wr_id,'created_repeat'=>$created_ids));
    exit;
}

/* ── 신규 등록 ── */
$new_wr9 = build_wr9($cal_goal, $cal_dday, $cal_widget, $cal_repeat, $repeat_type, $repeat_count);

sql_query("INSERT INTO {$write_table}
    SET wr_num=(SELECT IFNULL(MIN(t.wr_num),0)-1 FROM {$write_table} t),
        wr_reply='', wr_comment=0, wr_comment_reply='',
        ca_name='', wr_option='',
        wr_subject='".sql_real_escape_string($wr_subject)."',
        wr_content='".sql_real_escape_string($wr_content)."',
        wr_link1='', wr_link2='',
        wr_hit=0, wr_good=0, wr_nogood=0,
        mb_id='".sql_real_escape_string($member['mb_id'])."',
        wr_name='".sql_real_escape_string($member['mb_nick'])."',
        wr_password='', wr_email='', wr_homepage='',
        wr_datetime=NOW(), wr_last=NOW(),
        wr_ip='".sql_real_escape_string($_SERVER['REMOTE_ADDR'])."',
        wr_1='".sql_real_escape_string($wr_1)."',
        wr_2='".sql_real_escape_string($wr_2)."',
        wr_3='".sql_real_escape_string($wr_3)."',
        wr_4='', wr_5='local',
        wr_6='".sql_real_escape_string($wr_6)."',
        wr_7='".sql_real_escape_string($wr_7)."',
        wr_8='".sql_real_escape_string($wr_8)."',
        wr_9='".sql_real_escape_string($new_wr9)."',
        wr_is_comment=0");
$new_id = sql_insert_id();
if (!$new_id) {
    echo json_encode(array('success'=>false,'error'=>'insert failed')); exit;
}
sql_query("UPDATE {$write_table} SET wr_parent='{$new_id}' WHERE wr_id='{$new_id}'");

$created_ids = array($new_id);
if ($cal_repeat && $repeat_count > 0 && $repeat_count <= 365) {
    $diff_days = intval((strtotime($wr_2) - strtotime($wr_1)) / 86400);
    if ($diff_days < 0) $diff_days = 0;
    for ($i=1; $i<=$repeat_count; $i++) {
        $ns = cal_add_date($wr_1, $repeat_type, $i);
        $ne = $diff_days>0 ? date('Y-m-d', strtotime($ns.' +'.$diff_days.' days')) : $ns;
        $repeat_wr9 = build_wr9($cal_goal, $cal_dday, $cal_widget, true, $repeat_type, $repeat_count);
        sql_query("INSERT INTO {$write_table}
            SET wr_num=(SELECT IFNULL(MIN(t.wr_num),0)-1 FROM {$write_table} t),
                wr_reply='', wr_comment=0, wr_comment_reply='',
                ca_name='', wr_option='',
                wr_subject='".sql_real_escape_string($wr_subject)."',
                wr_content='".sql_real_escape_string($wr_content)."',
                wr_link1='', wr_link2='',
                wr_hit=0, wr_good=0, wr_nogood=0,
                mb_id='".sql_real_escape_string($member['mb_id'])."',
                wr_name='".sql_real_escape_string($member['mb_nick'])."',
                wr_password='', wr_email='', wr_homepage='',
                wr_datetime=NOW(), wr_last=NOW(),
                wr_ip='".sql_real_escape_string($_SERVER['REMOTE_ADDR'])."',
                wr_1='".sql_real_escape_string($ns)."',
                wr_2='".sql_real_escape_string($ne)."',
                wr_3='".sql_real_escape_string($wr_3)."',
                wr_4='', wr_5='local',
                wr_6='".sql_real_escape_string($wr_6)."',
                wr_7='".sql_real_escape_string($wr_7)."',
                wr_8='".sql_real_escape_string($wr_8)."',
                wr_9='".sql_real_escape_string($repeat_wr9)."',
                wr_is_comment=0");
        $nid = sql_insert_id();
        if ($nid) {
            sql_query("UPDATE {$write_table} SET wr_parent='{$nid}' WHERE wr_id='{$nid}'");
            $created_ids[] = $nid;
        }
    }
}

sql_query("UPDATE {$g5['board_table']}
           SET bo_count_write=(SELECT COUNT(*) FROM {$write_table} WHERE wr_is_comment=0)
           WHERE bo_table='".sql_real_escape_string($bo_table)."'");

echo json_encode(array('success'=>true,'mode'=>'insert','wr_id'=>$new_id,'created'=>$created_ids));