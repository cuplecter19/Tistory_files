<?php
if (!defined("_GNUBOARD_")) exit;

$_skin_url = str_replace('http://', 'https://', $board_skin_url);

// 전역 헤더 이미지 DB에서 로드 (비로그인 방문자도 동일하게 표시)
include_once(__DIR__.'/google_db.php');
gcal_ensure_tables();
$_pref_table = $g5['prefix'].'calendar_user_pref';
$_global_pref = sql_fetch("SELECT header_image FROM {$_pref_table} WHERE mb_id='__global__'");
$_global_header_image = null;
if ($_global_pref && $_global_pref['header_image']) {
    $_global_header_image = json_decode($_global_pref['header_image'], true);
    // 유효하지 않은 데이터 무시
    if (!is_array($_global_header_image) || empty($_global_header_image['src'])) {
        $_global_header_image = null;
    }
}

$cal_year  = isset($_GET['cal_year']) ? intval($_GET['cal_year']) : intval(date('Y'));
$cal_month = isset($_GET['cal_month']) ? intval($_GET['cal_month']) : intval(date('n'));
$is_ajax   = isset($_GET['ajax']) && $_GET['ajax'] == '1';

$prev_month = $cal_month - 1; $prev_year = $cal_year; if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $cal_month + 1; $next_year = $cal_year; if ($next_month > 12) { $next_month = 1; $next_year++; }

$first_day_timestamp = mktime(0,0,0,$cal_month,1,$cal_year);
$days_in_month = intval(date('t',$first_day_timestamp));
$start_weekday = intval(date('w',$first_day_timestamp));

$today_day = 0;
if (intval(date('Y')) == $cal_year && intval(date('n')) == $cal_month) {
    $today_day = intval(date('j'));
}

function cal_hex_to_rgba($hex, $alpha = 0.5) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) == 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) != 6) return 'rgba(200,200,200,'.$alpha.')';
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    return 'rgba('.$r.','.$g.','.$b.','.$alpha.')';
}

$month_str = str_pad($cal_month,2,'0',STR_PAD_LEFT);
$sql = "SELECT wr_id,wr_subject,wr_content,wr_datetime,wr_1,wr_2,wr_3,wr_name,mb_id,wr_4,wr_5,wr_6,wr_7,wr_8,wr_9
        FROM {$write_table}
        WHERE wr_is_comment=0
          AND ((wr_1 LIKE '{$cal_year}-{$month_str}%')
            OR (wr_2>='{$cal_year}-{$month_str}-01' AND wr_1<='{$cal_year}-{$month_str}-{$days_in_month}'))
        ORDER BY wr_1 ASC, wr_id ASC";
$res = sql_query($sql);

$events = array();
while($row=sql_fetch_array($res)){
  $sd = $row['wr_1'] ? $row['wr_1'] : substr($row['wr_datetime'],0,10);
  $ed = $row['wr_2'] ? $row['wr_2'] : $sd;
  $s = strtotime($sd); $e = strtotime($ed);
  if($s===false) continue; if($e===false) $e=$s;
  for($ts=$s;$ts<=$e;$ts+=86400){
    $d=intval(date('j',$ts)); $m=intval(date('n',$ts)); $y=intval(date('Y',$ts));
    if($m==$cal_month && $y==$cal_year){ if(!isset($events[$d])) $events[$d]=array(); $events[$d][]=$row; }
  }
}

// D-day Goal 목록 (오늘 이후, 당일 포함)
$today_str = date('Y-m-d');
$goal_sql = "SELECT wr_id, wr_subject, wr_1, wr_2, wr_3, wr_9
             FROM {$write_table}
             WHERE wr_is_comment=0
               AND wr_9 LIKE '%GOAL=1%'
               AND wr_1 >= '{$today_str}'
             ORDER BY wr_1 ASC
             LIMIT 20";
$goal_res = sql_query($goal_sql);
$goal_list = array();
$today_ts = strtotime($today_str);
while ($grow = sql_fetch_array($goal_res)) {
    $target_ts = strtotime($grow['wr_1']);
    $diff = intval(($target_ts - $today_ts) / 86400);
    if ($diff < 0) continue;
    $grow['_dday_diff'] = $diff;
    $grow['_dday_text'] = ($diff === 0) ? 'D-Day!' : 'D-'.$diff;
    $goal_list[] = $grow;
}

// D-day 기념일 목록 (과거 포함 전부)
$dday_sql = "SELECT wr_id, wr_subject, wr_1, wr_2, wr_3, wr_9
             FROM {$write_table}
             WHERE wr_is_comment=0
               AND wr_9 LIKE '%DDAY=1%'
             ORDER BY wr_1 ASC
             LIMIT 20";
$dday_res = sql_query($dday_sql);
$dday_list = array();
while ($drow = sql_fetch_array($dday_res)) {
    $target_ts = strtotime($drow['wr_1']);
    $diff = intval(($target_ts - $today_ts) / 86400);
    if ($diff > 0) {
        $drow['_dday_text'] = 'D-'.$diff;
    } elseif ($diff === 0) {
        $drow['_dday_text'] = 'D-Day!';
    } else {
        $drow['_dday_text'] = 'D+'.abs($diff);
    }
    $drow['_dday_diff'] = $diff;
    $dday_list[] = $drow;
}

$base_url='./board.php?bo_table='.$bo_table;
$prev_url=$base_url.'&cal_year='.$prev_year.'&cal_month='.$prev_month;
$next_url=$base_url.'&cal_year='.$next_year.'&cal_month='.$next_month;
$today_url=$base_url;
$google_refresh_url=$_skin_url.'/google_calendar.php?bo_table='.$bo_table.'&cal_year='.$cal_year.'&cal_month='.$cal_month;
$google_auth_url=$_skin_url.'/google_oauth_start.php?bo_table='.$bo_table;

$weekday_labels = array('일','월','화','수','목','금','토');

ob_start();
?>
<div id="calendar-board" class="cal-wrap">

  <!-- ═══ 터미널 상단바 ═══ -->
  <div class="cal-titlebar">
    <div class="cal-titlebar-dots">
      <span class="cal-theme-dot" data-theme="sakura" title="벚꽃"></span>
      <span class="cal-theme-dot" data-theme="ocean" title="바다"></span>
      <span class="cal-theme-dot" data-theme="melon" title="메론소다"></span>
      <span class="cal-theme-dot" data-theme="kuromi" title="구로미"></span>
      <span class="cal-theme-dot" data-theme="mocha" title="다크 모카"></span>
      <span class="cal-theme-dot" data-theme="lemon" title="레모네이드"></span>
    </div>
    <div class="cal-titlebar-label">Calendar</div>
    <div class="cal-titlebar-actions">
      <?php if ($is_admin): ?>
      <button type="button" class="cal-titlebar-btn" id="btn-header-img" title="헤더 이미지 설정"><i class="fa-solid fa-camera"></i></button>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══ 헤더 이미지 영역 ═══ -->
  <div class="cal-header-image<?php if ($_global_header_image) echo ' has-image'; ?>" id="cal-header-image">
    <?php if (!$_global_header_image): ?>
    <div class="cal-header-image-placeholder" id="cal-header-placeholder"<?php if (!$is_admin) echo ' style="display:none;"'; ?>>
      <span>📷 이미지를 등록하세요</span>
    </div>
    <?php endif; ?>
    <?php
    $_himg_src    = $_global_header_image ? htmlspecialchars($_global_header_image['src'], ENT_QUOTES, 'UTF-8') : '';
    $_himg_height = $_global_header_image ? intval(isset($_global_header_image['height']) ? $_global_header_image['height'] : 160) : 160;
    $_himg_fit    = $_global_header_image ? htmlspecialchars(isset($_global_header_image['fit']) ? $_global_header_image['fit'] : 'cover', ENT_QUOTES, 'UTF-8') : 'cover';
    $_himg_style  = $_global_header_image
        ? 'height:'.$_himg_height.'px;object-fit:'.$_himg_fit.';'
        : 'display:none;';
    ?>
    <img id="cal-header-img-el" class="cal-header-img" src="<?php echo $_himg_src; ?>" alt="" style="<?php echo $_himg_style; ?>">
    <?php if ($is_admin): ?>
    <button type="button" class="cal-header-img-remove" id="btn-header-img-remove" <?php if (!$_global_header_image) echo 'style="display:none;"'; ?> title="이미지 제거">×</button>
    <?php endif; ?>
  </div>

  <!-- ═══ 헤더 이미지 설정 모달 (관리자 전용) ═══ -->
  <?php if ($is_admin): ?>
  <div id="cal-img-modal" class="cal-modal">
    <div id="cal-img-backdrop" class="cal-modal-backdrop"></div>
    <div class="cal-modal-content" style="max-width:420px;">
      <div class="cal-modal-header"><h3>헤더 이미지 설정</h3><button type="button" id="cal-img-modal-close">×</button></div>
      <div class="cal-form-body">
        <div class="cal-img-tabs">
          <button type="button" class="cal-img-tab active" data-tab="url">URL 입력</button>
          <button type="button" class="cal-img-tab" data-tab="file">파일 업로드</button>
        </div>
        <div class="cal-img-tab-panel" id="cal-img-tab-url">
          <label class="cal-label">이미지 URL
            <input type="url" id="cal-img-url-input" class="cal-input" placeholder="https://example.com/image.jpg">
          </label>
          <div class="cal-img-preview-box" id="cal-img-url-preview"></div>
        </div>
        <div class="cal-img-tab-panel" id="cal-img-tab-file" style="display:none;">
          <label class="cal-label">이미지 파일
            <div class="cal-file-drop" id="cal-file-drop">
              <span>파일을 드래그하거나 클릭하세요</span>
              <input type="file" id="cal-img-file-input" accept="image/*" style="display:none;">
            </div>
          </label>
          <div class="cal-img-preview-box" id="cal-img-file-preview"></div>
        </div>
        <div class="cal-form-row" style="grid-template-columns:1fr auto;align-items:end;gap:8px;">
          <label class="cal-label">높이 (px)
            <input type="number" id="cal-img-height-input" class="cal-input" value="160" min="60" max="400" step="10">
          </label>
          <label class="cal-label">맞춤
            <select id="cal-img-fit-select" class="cal-input">
              <option value="cover">채우기 (cover)</option>
              <option value="contain">맞추기 (contain)</option>
              <option value="fill">늘리기 (fill)</option>
            </select>
          </label>
        </div>
        <button type="button" class="cal-btn cal-btn-submit" id="btn-img-save" style="width:100%;">적용</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══ 캘린더 본체 ═══ -->
  <div class="cal-body">
    <div class="cal-header">
      <div class="cal-header-left"><h2 class="cal-title"><?php echo $cal_year; ?>년 <?php echo $cal_month; ?>월</h2></div>
      <div class="cal-header-right">
        <button type="button" class="cal-btn" id="btn-open-goals" title="D-day 관리">D-day</button>
        <a href="<?php echo $google_auth_url; ?>" class="cal-btn" id="btn-google-auth" target="_top" rel="noopener noreferrer">Google 연결</a>
        <a href="<?php echo $google_refresh_url; ?>" class="cal-btn" id="btn-google-refresh">동기화</a>
        <a href="<?php echo $prev_url; ?>" class="cal-btn js-cal-nav">◀</a>
        <a href="<?php echo $today_url; ?>" class="cal-btn">오늘</a>
        <a href="<?php echo $next_url; ?>" class="cal-btn js-cal-nav">▶</a>
      </div>
    </div>

    <div class="cal-weekdays">
      <?php for($wi=0;$wi<7;$wi++):
        $wc = 'cal-weekday';
        if ($wi==0) $wc .= ' cal-weekday-sun';
        if ($wi==6) $wc .= ' cal-weekday-sat';
      ?>
      <div class="<?php echo $wc; ?>"><?php echo $weekday_labels[$wi]; ?></div>
      <?php endfor; ?>
    </div>

    <div class="cal-grid"><div class="cal-days">
      <?php for($i=0;$i<$start_weekday;$i++) echo '<div class="cal-day cal-day-empty"></div>'; ?>
      <?php for($day=1;$day<=$days_in_month;$day++):
        $has_event = isset($events[$day]) && count($events[$day])>0;
        $is_today = ($day == $today_day);

        $is_holiday = false;
        $has_goal = false;
        $has_dday = false;
        if ($has_event) {
            foreach ($events[$day] as $ev_chk) {
                if (isset($ev_chk['wr_5']) && $ev_chk['wr_5'] === 'holiday') {
                    $is_holiday = true;
                }
                if (isset($ev_chk['wr_9']) && strpos($ev_chk['wr_9'], 'GOAL=1') !== false) {
                    $has_goal = true;
                }
                if (isset($ev_chk['wr_9']) && strpos($ev_chk['wr_9'], 'DDAY=1') !== false) {
                    $has_dday = true;
                }
            }
        }

        $classes = 'cal-day';
        if ($is_today) $classes .= ' cal-day-today';
        if ($has_event) $classes .= ' cal-has-event';
        if ($is_holiday) $classes .= ' cal-day-holiday';
        if ($has_goal) $classes .= ' cal-day-goal';
        if ($has_dday) $classes .= ' cal-day-dday';

        $events_json=array(); $colors=array();
        if($has_event){ foreach($events[$day] as $ev){
          $c = $ev['wr_3'] ? $ev['wr_3'] : '#3B82F6';
          $colors[]=$c;
          $is_goal_ev = (isset($ev['wr_9']) && strpos($ev['wr_9'], 'GOAL=1') !== false) ? true : false;
          $is_dday_ev = (isset($ev['wr_9']) && strpos($ev['wr_9'], 'DDAY=1') !== false) ? true : false;
          $is_widget_ev = (isset($ev['wr_9']) && strpos($ev['wr_9'], 'WIDGET=1') !== false) ? true : false;
          $events_json[] = array(
            'id'         => intval($ev['wr_id']),
            'subject'    => $ev['wr_subject'],
            'content'    => $ev['wr_content'],
            'preview'    => mb_substr(strip_tags($ev['wr_content']),0,200),
            'date'       => $ev['wr_1'],
            'end_date'   => $ev['wr_2'],
            'color'      => $c,
            'type'       => $ev['wr_5'] ? $ev['wr_5'] : 'local',
            'time_start' => $ev['wr_6'],
            'time_end'   => $ev['wr_7'],
            'is_goal'    => $is_goal_ev,
            'is_dday'    => $is_dday_ev,
            'is_widget'  => $is_widget_ev
          );
        }}
        $colors=array_values(array_unique($colors));
      ?>
      <div class="<?php echo $classes; ?>" data-day="<?php echo $day; ?>" data-events="<?php echo htmlspecialchars(json_encode($events_json, JSON_UNESCAPED_UNICODE),ENT_QUOTES,'UTF-8'); ?>">
        <div class="cal-day-num"><?php echo $day; ?></div>
        <?php if($has_goal){ ?><div class="cal-day-goal-flag">⚑</div><?php } ?>
        <?php if($has_dday){ ?><div class="cal-day-dday-flag">◈</div><?php } ?>
        <?php if($has_event){ ?>
          <div class="cal-day-dots"><?php for($ci=0;$ci<count($colors)&&$ci<4;$ci++){ ?><span class="cal-dot" style="background:<?php echo htmlspecialchars($colors[$ci]); ?>"></span><?php } ?></div>
          <div class="cal-tooltip"><?php
            $seen_tt = array();
            foreach($events[$day] as $ev){
              $subj = $ev['wr_subject'] ? $ev['wr_subject'] : '(제목없음)';
              $tc = $ev['wr_3'] ? $ev['wr_3'] : '#3B82F6';
              $key = $subj.'|'.$tc;
              if (isset($seen_tt[$key])) continue;
              $seen_tt[$key] = true;
              $bg_rgba = cal_hex_to_rgba($tc, 0.15);
              $goal_tag = '';
              if (isset($ev['wr_9']) && strpos($ev['wr_9'], 'GOAL=1') !== false) {
                  // D-day 계산
                  $ev_date_ts = strtotime($ev['wr_1']);
                  $now_ts = strtotime($today_str);
                  $ev_diff = intval(($ev_date_ts - $now_ts) / 86400);
                  if ($ev_diff >= 0) {
                      $goal_tag = ' <span class="cal-tooltip-goal">' . ($ev_diff === 0 ? 'D-Day!' : 'D-'.$ev_diff) . '</span>';
                  }
              } elseif (isset($ev['wr_9']) && strpos($ev['wr_9'], 'DDAY=1') !== false) {
                  $ev_date_ts = strtotime($ev['wr_1']);
                  $now_ts = strtotime($today_str);
                  $ev_diff = intval(($ev_date_ts - $now_ts) / 86400);
                  if ($ev_diff > 0) {
                      $dday_tag_text = 'D-'.$ev_diff;
                  } elseif ($ev_diff === 0) {
                      $dday_tag_text = 'D-Day!';
                  } else {
                      $dday_tag_text = 'D+'.abs($ev_diff);
                  }
                  $goal_tag = ' <span class="cal-tooltip-dday">'.$dday_tag_text.'</span>';
              }
              echo '<div class="cal-tooltip-line" style="background:'.$bg_rgba.';">'
                 . htmlspecialchars($subj, ENT_QUOTES, 'UTF-8')
                 . $goal_tag
                 . '</div>';
            }
          ?></div>
        <?php } ?>
      </div>
      <?php endfor; ?>
    </div></div>

    <div class="cal-detail-panel" id="cal-detail-panel" style="display:none;">
      <div class="cal-detail-header"><h3 id="cal-detail-date"></h3><button type="button" class="cal-btn" id="cal-detail-close">닫기</button></div>
      <div id="cal-detail-list" class="cal-detail-list"></div>
    </div>
  </div><!-- /.cal-body -->

  <!-- ═══ 모달들 ═══ -->
  <div id="cal-modal" class="cal-modal">
    <div id="cal-modal-backdrop" class="cal-modal-backdrop"></div>
    <div class="cal-modal-content">
      <div class="cal-modal-header"><h3>일정 추가</h3><button type="button" id="cal-modal-close">×</button></div>
      <div id="cal-modal-form" class="cal-form-body">
        <input type="hidden" name="w" id="modal_w" value="">
        <input type="hidden" name="bo_table" id="modal_bo_table" value="<?php echo $bo_table; ?>">
        <input type="hidden" name="wr_id" id="modal_wr_id" value="0">
        <input type="text" name="wr_subject" id="modal_subject" class="cal-input" placeholder="제목">
        <textarea name="wr_content" id="modal_content" class="cal-textarea" placeholder="내용"></textarea>
        <div class="cal-form-row">
          <label class="cal-label">시작일<input type="date" name="wr_1" id="modal_wr_1" class="cal-input"></label>
          <label class="cal-label">종료일<input type="date" name="wr_2" id="modal_wr_2" class="cal-input"></label>
        </div>
        <div class="cal-form-row">
          <label class="cal-label">시작시간<input type="time" name="wr_6" id="modal_wr_6" class="cal-input"></label>
          <label class="cal-label">종료시간<input type="time" name="wr_7" id="modal_wr_7" class="cal-input"></label>
        </div>
        <div class="cal-color-row">
          <label class="cal-label">색상 <input type="color" name="wr_3" id="modal_wr_3" class="cal-input cal-input-color" value="#3B82F6"></label>
          <div id="cal-recent-colors" class="cal-recent-colors"></div>
        </div>
        <!-- D-day 타입 라디오 버튼 -->
        <div class="cal-form-row cal-dday-type-row">
          <span class="cal-label-text">타입</span>
          <label class="cal-radio-label"><input type="radio" name="cal_dday_type" id="modal_dday_type_none" value="none" checked> 없음</label>
          <label class="cal-radio-label"><input type="radio" name="cal_dday_type" id="modal_dday_type_goal" value="goal"> ⚑ Goal</label>
          <label class="cal-radio-label"><input type="radio" name="cal_dday_type" id="modal_dday_type_dday" value="dday"> ◈ D-day</label>
        </div>
        <!-- 위젯 표시 여부 -->
        <div class="cal-form-row cal-widget-row" id="modal_widget_row">
          <label class="cal-goal-check"><input type="checkbox" name="cal_widget" id="modal_cal_widget" value="1"> <span class="cal-goal-check-label">📌 위젯에 표시</span></label>
        </div>
        <div class="cal-form-row cal-repeat-row">
          <label><input type="checkbox" name="cal_repeat" id="modal_cal_repeat" value="1"> 반복</label>
          <select name="cal_repeat_type" id="modal_cal_repeat_type" class="cal-input">
            <option value="daily">매일</option>
            <option value="weekly">매주</option>
            <option value="monthly">매월</option>
          </select>
          <input type="number" name="cal_repeat_count" id="modal_cal_repeat_count" value="4" min="1" max="365" class="cal-input" style="width:80px;">
          <span style="font-size:12px;color:#9a8d9e;">회</span>
        </div>
        <button type="button" class="cal-btn cal-btn-submit" id="btn-save-event">저장</button>
      </div>
    </div>
  </div>

  <div id="cal-copy-modal" class="cal-modal">
    <div id="cal-copy-backdrop" class="cal-modal-backdrop"></div>
    <div class="cal-modal-content">
      <div class="cal-modal-header"><h3>일정 복사</h3><button type="button" id="cal-copy-close">×</button></div>
      <div id="cal-copy-form" class="cal-form-body">
        <input type="hidden" name="bo_table" id="copy_bo_table" value="<?php echo $bo_table; ?>">
        <input type="hidden" name="src_wr_id" id="copy_src_wr_id" value="">
        <label class="cal-label">복사할 날짜<input type="date" name="target_date" id="copy_target_date" class="cal-input"></label>
        <button type="button" class="cal-btn cal-btn-submit" id="btn-exec-copy">복사 실행</button>
      </div>
    </div>
  </div>

  <div id="cal-view-modal" class="cal-modal">
    <div id="cal-view-backdrop" class="cal-modal-backdrop"></div>
    <div class="cal-modal-content">
      <div class="cal-view-color-bar" style="height:5px;border-radius:20px 20px 0 0;"></div>
      <div style="padding:20px;">
        <div class="cal-modal-header">
          <h3 id="cal-view-title"></h3>
          <button type="button" id="cal-view-close">×</button>
        </div>
        <div class="cal-view-date-row" id="cal-view-date-info" style="font-size:12px;color:#9a8d9e;margin-bottom:14px;"></div>
        <div class="cal-view-goal-badge" id="cal-view-goal-badge" style="display:none;"></div>
        <div class="cal-view-body" id="cal-view-content" style="font-size:14px;line-height:1.7;min-height:60px;margin-bottom:16px;"></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;border-top:1px solid var(--cal-border-light);padding-top:12px;">
          <button type="button" class="cal-btn" id="btn-view-edit">수정</button>
          <button type="button" class="cal-btn cal-btn-danger" id="btn-view-delete">삭제</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ D-day 통합 모달 (Goal + D-day 탭) ═══ -->
  <div id="cal-goal-modal" class="cal-modal">
    <div id="cal-goal-backdrop" class="cal-modal-backdrop"></div>
    <div class="cal-modal-content" style="max-width:440px;">
      <div class="cal-modal-header">
        <h3>📌 D-day 관리</h3>
        <button type="button" id="cal-goal-modal-close">×</button>
      </div>
      <!-- 탭 UI -->
      <div class="cal-dday-tabs">
        <button type="button" class="cal-dday-tab active" data-dday-tab="goal">⚑ Goal</button>
        <button type="button" class="cal-dday-tab" data-dday-tab="dday">◈ D-day 기념일</button>
      </div>
      <!-- Goal 탭 패널 -->
      <div class="cal-dday-tab-panel" id="cal-dday-tab-goal">
        <div class="cal-goal-modal-body">
          <?php if (empty($goal_list)) { ?>
            <div class="cal-goal-empty-big">
              <div class="cal-goal-empty-icon">⚑</div>
              <p>설정된 D-day 목표가 없습니다.</p>
              <span>일정 추가 시 'Goal' 타입을 선택하세요.</span>
            </div>
          <?php } else { ?>
            <?php foreach ($goal_list as $gl) {
              $gc = $gl['wr_3'] ? $gl['wr_3'] : '#af52de';
              $bg_rgba = cal_hex_to_rgba($gc, 0.08);
              $is_dday_today = ($gl['_dday_diff'] === 0);
            ?>
            <div class="cal-goal-card<?php if ($is_dday_today) echo ' cal-goal-card-today'; ?>" style="border-left-color:<?php echo htmlspecialchars($gc); ?>; background:<?php echo $bg_rgba; ?>;">
              <div class="cal-goal-card-info">
                <div class="cal-goal-card-title"><?php echo htmlspecialchars($gl['wr_subject'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="cal-goal-card-date">📆 <?php echo $gl['wr_1']; ?></div>
              </div>
              <div class="cal-goal-card-dday<?php if ($is_dday_today) echo ' dday-today'; ?>"><?php echo $gl['_dday_text']; ?></div>
            </div>
            <?php } ?>
          <?php } ?>
        </div>
      </div>
      <!-- D-day 기념일 탭 패널 -->
      <div class="cal-dday-tab-panel" id="cal-dday-tab-dday" style="display:none;">
        <div class="cal-goal-modal-body">
          <?php if (empty($dday_list)) { ?>
            <div class="cal-goal-empty-big">
              <div class="cal-goal-empty-icon">◈</div>
              <p>설정된 D-day 기념일이 없습니다.</p>
              <span>일정 추가 시 'D-day' 타입을 선택하세요.</span>
            </div>
          <?php } else { ?>
            <?php foreach ($dday_list as $dl) {
              $dc = $dl['wr_3'] ? $dl['wr_3'] : '#007aff';
              $bg_rgba = cal_hex_to_rgba($dc, 0.08);
              $is_dday_today = ($dl['_dday_diff'] === 0);
              $is_past = ($dl['_dday_diff'] < 0);
            ?>
            <div class="cal-goal-card cal-goal-card-dday-type<?php if ($is_dday_today) echo ' cal-goal-card-today'; ?>" style="border-left-color:<?php echo htmlspecialchars($dc); ?>; background:<?php echo $bg_rgba; ?>;">
              <div class="cal-goal-card-info">
                <div class="cal-goal-card-title"><?php echo htmlspecialchars($dl['wr_subject'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="cal-goal-card-date">📆 <?php echo $dl['wr_1']; ?></div>
              </div>
              <div class="cal-goal-card-dday cal-dday-badge<?php if ($is_dday_today) echo ' dday-today'; if ($is_past) echo ' dday-past'; ?>"><?php echo $dl['_dday_text']; ?></div>
            </div>
            <?php } ?>
          <?php } ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$calendar_html = ob_get_clean();

if ($is_ajax) {
    echo $calendar_html;
    exit;
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="<?php echo $_skin_url; ?>/style.css?v=<?php echo time(); ?>">
<div id="calendar-app"><?php echo $calendar_html; ?></div>
<script src="<?php echo $_skin_url; ?>/calendar.js?v=<?php echo time(); ?>"></script>
<script>
CalendarBoard.init({
  year: <?php echo $cal_year; ?>,
  month: <?php echo $cal_month; ?>,
  bo_table: '<?php echo $bo_table; ?>',
  google_refresh_url: '<?php echo $google_refresh_url; ?>',
  save_action_url: '<?php echo $_skin_url; ?>/ajax_event_save.php',
  copy_action_url: '<?php echo $_skin_url; ?>/copy_event.php',
  delete_action_url: '<?php echo $_skin_url; ?>/delete_event.php',
  pref_url: '<?php echo $_skin_url; ?>/ajax_pref.php',
  header_upload_url: '<?php echo $_skin_url; ?>/ajax_header_upload.php'
});
</script>
