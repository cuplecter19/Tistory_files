<?php
if (!defined("_GNUBOARD_")) exit;

if ($w == '' && isset($_POST['cal_repeat']) && $_POST['cal_repeat']=='1') {
    $repeat_type = isset($_POST['cal_repeat_type']) ? $_POST['cal_repeat_type'] : 'weekly';
    $repeat_count = isset($_POST['cal_repeat_count']) ? intval($_POST['cal_repeat_count']) : 0;
    if (!in_array($repeat_type, array('daily','weekly','monthly'))) $repeat_type='weekly';

    if ($repeat_count > 0 && $repeat_count <= 365) {
        $src = sql_fetch("SELECT * FROM {$write_table} WHERE wr_id='".intval($wr_id)."'");
        if ($src['wr_id']) {
            $start_date = $src['wr_1'] ? $src['wr_1'] : substr($src['wr_datetime'], 0, 10);
            $end_date   = $src['wr_2'] ? $src['wr_2'] : $start_date;
            $diff_days = 0;
            if ($end_date && $end_date != $start_date) $diff_days = intval((strtotime($end_date)-strtotime($start_date))/86400);

            for ($i=1; $i<=$repeat_count; $i++) {
                if ($repeat_type=='daily') $new_start = date('Y-m-d', strtotime($start_date.' +'.$i.' days'));
                else if ($repeat_type=='weekly') $new_start = date('Y-m-d', strtotime($start_date.' +'.($i*7).' days'));
                else $new_start = date('Y-m-d', strtotime($start_date.' +'.$i.' months'));

                $new_end = $diff_days>0 ? date('Y-m-d', strtotime($new_start.' +'.$diff_days.' days')) : $new_start;

                // wr_9: 원본의 GOAL/DDAY/WIDGET 플래그 보존 + REPEAT 정보 추가
                $src_wr9_base = $src['wr_9'] ? $src['wr_9'] : '';
                $repeat_wr9_parts = array();
                if (strpos($src_wr9_base, 'GOAL=1') !== false) $repeat_wr9_parts[] = 'GOAL=1';
                if (strpos($src_wr9_base, 'DDAY=1') !== false) $repeat_wr9_parts[] = 'DDAY=1';
                if (strpos($src_wr9_base, 'WIDGET=1') !== false) $repeat_wr9_parts[] = 'WIDGET=1';
                $repeat_wr9_parts[] = 'FREQ='.strtoupper($repeat_type).';COUNT='.intval($repeat_count);
                $new_repeat_wr9 = implode(';', $repeat_wr9_parts);

                sql_query("INSERT INTO {$write_table}
                  SET wr_num=(SELECT IFNULL(MIN(t.wr_num),0)-1 FROM {$write_table} t),
                      wr_reply='', wr_comment=0, wr_comment_reply='',
                      ca_name='".sql_real_escape_string($src['ca_name'])."',
                      wr_option='',
                      wr_subject='".sql_real_escape_string($src['wr_subject'])."',
                      wr_content='".sql_real_escape_string($src['wr_content'])."',
                      wr_link1='', wr_link2='',
                      wr_hit=0, wr_good=0, wr_nogood=0,
                      mb_id='".sql_real_escape_string($src['mb_id'])."',
                      wr_name='".sql_real_escape_string($src['wr_name'])."',
                      wr_password='".sql_real_escape_string($src['wr_password'])."',
                      wr_email='".sql_real_escape_string($src['wr_email'])."',
                      wr_homepage='',
                      wr_datetime=NOW(), wr_last=NOW(),
                      wr_ip='".sql_real_escape_string($_SERVER['REMOTE_ADDR'])."',
                      wr_1='".sql_real_escape_string($new_start)."',
                      wr_2='".sql_real_escape_string($new_end)."',
                      wr_3='".sql_real_escape_string($src['wr_3'])."',
                      wr_4='',
                      wr_5='local',
                      wr_6='".sql_real_escape_string($src['wr_6'])."',
                      wr_7='".sql_real_escape_string($src['wr_7'])."',
                      wr_8='".sql_real_escape_string($src['wr_8'])."',
                      wr_9='".sql_real_escape_string($new_repeat_wr9)."',
                      wr_is_comment=0");
                $new_wr_id = sql_insert_id();
                if ($new_wr_id) sql_query("UPDATE {$write_table} SET wr_parent='{$new_wr_id}' WHERE wr_id='{$new_wr_id}'");
            }

            sql_query("UPDATE {$g5['board_table']}
                       SET bo_count_write=(SELECT COUNT(*) FROM {$write_table} WHERE wr_is_comment=0)
                       WHERE bo_table='".sql_real_escape_string($bo_table)."'");
        }
    }
}