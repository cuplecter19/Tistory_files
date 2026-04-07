<?php
if (!defined("_GNUBOARD_")) exit;

$_skin_url = str_replace('http://', 'https://', $board_skin_url);

$cal_year  = isset($_GET['cal_year'])  ? intval($_GET['cal_year'])  : (int)date('Y');
$cal_month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : (int)date('n');

$prev_month = $cal_month - 1; $prev_year  = $cal_year; if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $cal_month + 1; $next_year  = $cal_year; if ($next_month > 12) { $next_month = 1; $next_year++; }

$first_day_timestamp = mktime(0,0,0,$cal_month,1,$cal_year);
$days_in_month = (int)date('t',$first_day_timestamp);
$start_weekday = (int)date('w',$first_day_timestamp);
$today = (int)date('j'); $today_month=(int)date('n'); $today_year=(int)date('Y');

$month_str = str_pad($cal_month,2,'0',STR_PAD_LEFT);
$sql = "SELECT wr_id, wr_subject, wr_content, wr_datetime, wr_1, wr_2, wr_3, wr_name, mb_id, wr_4, wr_5, wr_6, wr_7, wr_8, wr_9
        FROM {$write_table}
        WHERE wr_is_comment=0
          AND ((wr_1 LIKE '{$cal_year}-{$month_str}%')
           OR (wr_2 >= '{$cal_year}-{$month_str}-01' AND wr_1 <= '{$cal_year}-{$month_str}-{$days_in_month}'))
        ORDER BY wr_1 ASC, wr_id ASC";
$result = sql_query($sql);

$events = array();
while ($row = sql_fetch_array($result)) {
    $start_date = $row['wr_1'] ? $row['wr_1'] : substr($row['wr_datetime'],0,10);
    $end_date = $row['wr_2'] ? $row['wr_2'] : $start_date;
    $sd = strtotime($start_date); $ed = strtotime($end_date);
    if ($sd===false) continue; if ($ed===false) $ed=$sd;

    for ($ts=$sd; $ts<=$ed; $ts+=86400) {
        $d_key=(int)date('j',$ts); $d_m=(int)date('n',$ts); $d_y=(int)date('Y',$ts);
        if ($d_m==$cal_month && $d_y==$cal_year) {
            if (!isset($events[$d_key])) $events[$d_key]=array();
            $events[$d_key][]=$row;
        }
    }
}

$base_url = './board.php?bo_table='.$bo_table;
$prev_url = $base_url.'&cal_year='.$prev_year.'&cal_month='.$prev_month;
$next_url = $base_url.'&cal_year='.$next_year.'&cal_month='.$next_month;
$today_url = $base_url;
$write_url = './board.php?bo_table='.$bo_table.'&wr_id=0&w=w';
$google_refresh_url = $_skin_url.'/google_calendar.php?bo_table='.$bo_table.'&cal_year='.$cal_year.'&cal_month='.$cal_month;
$google_auth_url = $_skin_url.'/google_oauth_start.php?bo_table='.$bo_table;

$day_names = array('일','월','화','수','목','금','토');
?>
<link rel="stylesheet" href="<?php echo $_skin_url; ?>/style.css">

<div id="calendar-board" class="cal-wrap">
  <div class="cal-header">
    <div class="cal-header-left">
      <h2 class="cal-title"><i class="fa-regular fa-calendar"></i><span><?php echo $cal_year; ?>년 <?php echo $cal_month; ?>월</span></h2>
      <span class="cal-today-badge">오늘: <?php echo date('Y.m.d (D)'); ?></span>
    </div>
    <div class="cal-header-right">
      <a href="<?php echo $google_auth_url; ?>" class="cal-btn cal-btn-google">Google 연결</a>
      <a href="<?php echo $google_refresh_url; ?>" class="cal-btn cal-btn-google" id="btn-google-refresh"><i class="fa-brands fa-google"></i> 동기화</a>
      <a href="<?php echo $prev_url; ?>" class="cal-btn cal-btn-nav"><i class="fa-solid fa-chevron-left"></i></a>
      <a href="<?php echo $today_url; ?>" class="cal-btn cal-btn-today">오늘</a>
      <a href="<?php echo $next_url; ?>" class="cal-btn cal-btn-nav"><i class="fa-solid fa-chevron-right"></i></a>
      <?php if ($is_member || $board['bo_write_level']==1) { ?>
      <a href="<?php echo $write_url; ?>" class="cal-btn cal-btn-write" id="btn-open-write-modal"><i class="fa-solid fa-plus"></i> 일정 추가</a>
      <?php } ?>
    </div>
  </div>

  <div class="cal-grid">
    <div class="cal-weekdays">
      <?php foreach($day_names as $idx=>$dn){ ?><div class="cal-weekday <?php echo $idx==0?'sun':($idx==6?'sat':''); ?>"><?php echo $dn; ?></div><?php } ?>
    </div>
    <div class="cal-days">
      <?php for($i=0;$i<$start_weekday;$i++) echo '<div class="cal-day cal-day-empty"></div>'; ?>
      <?php for($day=1;$day<=$days_in_month;$day++):
        $weekday = ($start_weekday+$day-1)%7;
        $is_today = ($day==$today && $cal_month==$today_month && $cal_year==$today_year);
        $has_event = isset($events[$day]) && count($events[$day])>0;

        $classes='cal-day';
        if($is_today) $classes.=' cal-today';
        if($weekday==0) $classes.=' cal-sun';
        if($weekday==6) $classes.=' cal-sat';
        if($has_event) $classes.=' cal-has-event';

        $events_json=array();
        $dot_colors=array();

        if($has_event){
          foreach($events[$day] as $ev){
            $color = $ev['wr_3'] ? $ev['wr_3'] : '#3B82F6';
            $dot_colors[] = $color;
            $events_json[] = array(
              'id'=>$ev['wr_id'],
              'subject'=>$ev['wr_subject'],
              'content'=>mb_substr(strip_tags($ev['wr_content']),0,200),
              'date'=>$ev['wr_1'],
              'end_date'=>$ev['wr_2'],
              'color'=>$color,
              'type'=>($ev['wr_5']=='google'?'google':'local'),
              'name'=>$ev['wr_name'],
              'time_start'=>$ev['wr_6'],
              'time_end'=>$ev['wr_7']
            );
          }
        }
        $dot_colors = array_values(array_unique($dot_colors));
      ?>
      <div class="<?php echo $classes; ?>" data-day="<?php echo $day; ?>" data-events="<?php echo htmlspecialchars(json_encode($events_json),ENT_QUOTES,'UTF-8'); ?>">
        <div class="cal-day-num"><?php echo $day; ?></div>
        <?php if($has_event){ ?>
        <div class="cal-day-dots">
          <?php for($di=0;$di<count($dot_colors) && $di<4;$di++){ ?>
          <span class="cal-dot" style="background:<?php echo htmlspecialchars($dot_colors[$di]); ?>"></span>
          <?php } ?>
        </div>
        <?php } ?>
      </div>
      <?php endfor; ?>
      <?php
      $total_cells = $start_weekday + $days_in_month;
      $remaining = (7 - ($total_cells % 7)) % 7;
      for($i=0;$i<$remaining;$i++) echo '<div class="cal-day cal-day-empty"></div>';
      ?>
    </div>
  </div>

  <div class="cal-detail-panel" id="cal-detail-panel" style="display:none;">
    <div class="cal-detail-header">
      <h3 id="cal-detail-date"></h3>
      <button class="cal-detail-close" id="cal-detail-close" type="button"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="cal-detail-list" id="cal-detail-list"></div>
  </div>

  <!-- AJAX 작성/수정 모달 -->
  <div id="cal-modal" class="cal-modal" style="display:none;">
    <div class="cal-modal-backdrop" id="cal-modal-backdrop"></div>
    <div class="cal-modal-content">
      <div class="cal-modal-header">
        <h3 id="cal-modal-title">일정 추가</h3>
        <button type="button" id="cal-modal-close">×</button>
      </div>
      <form id="cal-modal-form">
        <input type="hidden" name="w" id="modal_w" value="">
        <input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
        <input type="hidden" name="wr_id" id="modal_wr_id" value="0">
        <input type="text" name="wr_subject" id="modal_subject" class="cal-input" placeholder="제목" required>
        <textarea name="wr_content" id="modal_content" class="cal-textarea" placeholder="내용"></textarea>
        <div class="cal-form-row">
          <input type="date" name="wr_1" id="modal_wr_1" class="cal-input">
          <input type="date" name="wr_2" id="modal_wr_2" class="cal-input">
        </div>
        <div class="cal-form-row">
          <input type="time" name="wr_6" id="modal_wr_6" class="cal-input">
          <input type="time" name="wr_7" id="modal_wr_7" class="cal-input">
        </div>
        <input type="text" name="wr_3" id="modal_wr_3" class="cal-input" value="#3B82F6" placeholder="#3B82F6">
        <div class="cal-form-row">
          <label><input type="checkbox" name="cal_repeat" id="modal_repeat" value="1"> 반복</label>
          <select name="cal_repeat_type" id="modal_repeat_type" class="cal-input">
            <option value="daily">매일</option>
            <option value="weekly" selected>매주</option>
            <option value="monthly">매월</option>
          </select>
          <input type="number" name="cal_repeat_count" id="modal_repeat_count" class="cal-input" value="4" min="1" max="365">
        </div>
        <div class="cal-form-row" style="justify-content:flex-end;">
          <button type="submit" class="cal-btn cal-btn-submit">저장</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="<?php echo $_skin_url; ?>/calendar.js"></script>
<script>
CalendarBoard.init({
  bo_table:'<?php echo $bo_table; ?>',
  base_url:'<?php echo $base_url; ?>',
  year:<?php echo $cal_year; ?>,
  month:<?php echo $cal_month; ?>,
  google_refresh_url:'<?php echo $google_refresh_url; ?>',
  write_action_url:'<?php echo G5_BBS_URL; ?>/write_update.php'
});
</script>