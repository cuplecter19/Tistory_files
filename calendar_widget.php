<?php
/**
 * 캘린더 위젯 – 플로팅 윈도우 (include 방식)
 * 
 * 사용법: 메인 페이지(tail.sub.php 추천)에서
 *   <?php include_once(G5_SKIN_PATH.'/board/calendar/calendar_widget.php'); ?>
 * 또는
 *   <?php include_once('./skin/board/calendar/calendar_widget.php'); ?>
 */

// 이미 common.php가 로드되지 않은 경우에만 로드
if (!defined('_GNUBOARD_')) {
    include_once('./../../../common.php');
}
if (!defined('_GNUBOARD_')) exit;

// 관리자가 아니어도 위젯을 보여주고 싶으면 아래 줄 삭제
if (!$is_admin) return;

// 위젯 설정
$widget_bo_table = 'calendar';
$widget_count = 3;
$widget_write_table = $g5['write_prefix'].$widget_bo_table;

$today = date('Y-m-d');

// 테이블 존재 확인
$_cw_chk = sql_query("SHOW TABLES LIKE '{$widget_write_table}'", false);
$_cw_table_exists = ($_cw_chk && sql_fetch_array($_cw_chk));

$cw_events = array();
$cw_goals  = array();
$cw_ddays  = array();

if ($_cw_table_exists) {
    // 위젯에 표시할 다가오는 일정 조회 (WIDGET=1 플래그 필수)
    $sql = "SELECT wr_id, wr_subject, wr_1, wr_2, wr_3, wr_6, wr_7, wr_5, wr_9
            FROM {$widget_write_table}
            WHERE wr_is_comment=0
              AND wr_9 LIKE '%WIDGET=1%'
              AND (wr_1 >= '{$today}' OR (wr_2 >= '{$today}' AND wr_1 <= '{$today}'))
            ORDER BY wr_1 ASC, wr_6 ASC
            LIMIT {$widget_count}";
    $result = sql_query($sql);
    while ($row = sql_fetch_array($result)) {
        $cw_events[] = $row;
    }

    // 위젯에 표시할 Goal (WIDGET=1 플래그 필수)
    $goal_sql = "SELECT wr_id, wr_subject, wr_1, wr_2, wr_3, wr_9
                 FROM {$widget_write_table}
                 WHERE wr_is_comment=0
                   AND wr_9 LIKE '%GOAL=1%'
                   AND wr_9 LIKE '%WIDGET=1%'
                   AND wr_1 >= '{$today}'
                 ORDER BY wr_1 ASC
                 LIMIT 5";
    $goal_result = sql_query($goal_sql);
    while ($row = sql_fetch_array($goal_result)) {
        $cw_goals[] = $row;
    }

    // 위젯에 표시할 D-day 기념일 (WIDGET=1 플래그 필수, 과거 포함)
    $dday_sql = "SELECT wr_id, wr_subject, wr_1, wr_2, wr_3, wr_9
                 FROM {$widget_write_table}
                 WHERE wr_is_comment=0
                   AND wr_9 LIKE '%DDAY=1%'
                   AND wr_9 LIKE '%WIDGET=1%'
                 ORDER BY wr_1 ASC
                 LIMIT 5";
    $dday_result = sql_query($dday_sql);
    while ($row = sql_fetch_array($dday_result)) {
        $cw_ddays[] = $row;
    }
}

// 위젯 고유 ID (같은 페이지에 여러 번 include 방지)
$_cw_uid = 'cw_' . substr(md5('cal_widget'), 0, 6);
?>

<!-- ═══ 캘린더 플로팅 위젯 ═══ -->
<div id="<?php echo $_cw_uid; ?>" class="cw-float-window" style="display:none;">

  <!-- 타이틀바 (드래그 핸들) -->
  <div class="cw-float-titlebar" data-cw-drag="true">
    <div class="cw-float-dots">
      <span class="cw-float-dot cw-dot-close" title="닫기"></span>
      <span class="cw-float-dot cw-dot-min" title="최소화"></span>
      <span class="cw-float-dot cw-dot-max" title="접기/펼치기"></span>
    </div>
    <div class="cw-float-title">📅 일정</div>
    <div class="cw-float-title-spacer"></div>
  </div>

  <!-- 본문 (접기/펼치기 대상) -->
  <div class="cw-float-body">

<?php if (empty($cw_events)) { ?>
    <div class="cw-float-empty"><span>ℹ</span> No upcoming events found.</div>
<?php } else {
    $today_ts_cw = strtotime($today);
    foreach ($cw_events as $ev) {
        $color = $ev['wr_3'] ? $ev['wr_3'] : '#007aff';
        $hex = ltrim($color, '#');
        if (strlen($hex)==3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
        $bg_rgba   = "rgba({$r},{$g},{$b},0.07)";
        $bdr_rgba  = "rgba({$r},{$g},{$b},0.35)";

        $date_str = $ev['wr_1'] ? $ev['wr_1'] : '';
        if ($ev['wr_2'] && $ev['wr_2'] !== $ev['wr_1']) $date_str .= ' ~ '.$ev['wr_2'];

        $time_str = '';
        if ($ev['wr_6']) { $time_str = $ev['wr_6']; if ($ev['wr_7']) $time_str .= ' - '.$ev['wr_7']; }

        $is_holiday = ($ev['wr_5'] === 'holiday');

        // 이 일정이 Goal인지
        $is_goal_ev = (strpos($ev['wr_9'], 'GOAL=1') !== false);
        $goal_badge = '';
        if ($is_goal_ev) {
            $diff = intval((strtotime($ev['wr_1']) - $today_ts_cw) / 86400);
            if ($diff >= 0) {
                $dday_t = ($diff === 0) ? 'D-Day!' : 'D-'.$diff;
                $goal_badge = '<span class="cw-float-goal-inline'.($diff===0?' dday-today':'').'">'.$dday_t.'</span>';
            }
        }
?>
    <div class="cw-float-event" style="background:<?php echo $bg_rgba; ?>;">
      <div class="cw-float-event-title">
        <?php echo htmlspecialchars($ev['wr_subject'], ENT_QUOTES, 'UTF-8'); ?>
        <?php if ($is_holiday) { ?><span class="cw-float-tag-holiday">공휴일</span><?php } ?>
        <?php echo $goal_badge; ?>
      </div>
      <div class="cw-float-event-meta">
        <?php if ($date_str) { ?><span class="cw-float-meta-item">📆 <?php echo $date_str; ?></span><?php } ?>
        <?php if ($time_str) { ?><span class="cw-float-meta-item">🕐 <?php echo $time_str; ?></span><?php } ?>
      </div>
    </div>
<?php
    }
} ?>

    <!-- D-day 섹션 -->
    <div class="cw-float-goal-section">
      <div class="cw-float-goal-label"><span>◈</span> D-day</div>
<?php
$cw_has_dday_items = false;
$today_ts_cw2 = strtotime($today);

// Goal 항목 표시
foreach ($cw_goals as $gl) {
    $target_ts = strtotime($gl['wr_1']);
    $diff = intval(($target_ts - $today_ts_cw2) / 86400);
    if ($diff < 0) continue;
    $cw_has_dday_items = true;
    $dday_text  = ($diff === 0) ? 'D-Day!' : 'D-'.$diff;
    $dday_class = ($diff === 0) ? 'cw-float-goal-dday dday-today' : 'cw-float-goal-dday';
    $gc = $gl['wr_3'] ? $gl['wr_3'] : '#af52de';
    $ghex = ltrim($gc, '#');
    if (strlen($ghex)==3) $ghex=$ghex[0].$ghex[0].$ghex[1].$ghex[1].$ghex[2].$ghex[2];
    $gbg = 'rgba('.hexdec(substr($ghex,0,2)).','.hexdec(substr($ghex,2,2)).','.hexdec(substr($ghex,4,2)).',0.06)';
?>
      <div class="cw-float-goal-item" style="background:<?php echo $gbg; ?>;">
        <span class="cw-float-goal-name"><?php echo htmlspecialchars($gl['wr_subject'], ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="cw-float-goal-type-tag">⚑</span>
        <span class="<?php echo $dday_class; ?>"><?php echo $dday_text; ?></span>
      </div>
<?php } ?>
<?php
// D-day 기념일 항목 표시
foreach ($cw_ddays as $dl) {
    $cw_has_dday_items = true;
    $target_ts = strtotime($dl['wr_1']);
    $diff = intval(($target_ts - $today_ts_cw2) / 86400);
    if ($diff > 0) {
        $dday_text = 'D-'.$diff;
    } elseif ($diff === 0) {
        $dday_text = 'D-Day!';
    } else {
        $dday_text = 'D+'.abs($diff);
    }
    $dday_class = ($diff === 0) ? 'cw-float-goal-dday dday-today' : (($diff < 0) ? 'cw-float-goal-dday cw-float-dday-past' : 'cw-float-goal-dday cw-float-dday-future');
    $dc = $dl['wr_3'] ? $dl['wr_3'] : '#007aff';
    $dhex = ltrim($dc, '#');
    if (strlen($dhex)==3) $dhex=$dhex[0].$dhex[0].$dhex[1].$dhex[1].$dhex[2].$dhex[2];
    $dbg = 'rgba('.hexdec(substr($dhex,0,2)).','.hexdec(substr($dhex,2,2)).','.hexdec(substr($dhex,4,2)).',0.06)';
?>
      <div class="cw-float-goal-item cw-float-dday-item" style="background:<?php echo $dbg; ?>;">
        <span class="cw-float-goal-name"><?php echo htmlspecialchars($dl['wr_subject'], ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="cw-float-goal-type-tag cw-float-dday-tag">◈</span>
        <span class="<?php echo $dday_class; ?>"><?php echo $dday_text; ?></span>
      </div>
<?php } ?>
<?php if (!$cw_has_dday_items) { ?>
      <div class="cw-float-goal-empty">설정된 D-day가 없습니다.</div>
<?php } ?>
    </div>

    <!-- 하단 링크 -->
    <div class="cw-float-footer">
      <a href="<?php echo G5_BBS_URL; ?>/board.php?bo_table=<?php echo $widget_bo_table; ?>">전체 일정 보기 →</a>
    </div>
  </div><!-- /.cw-float-body -->

  <!-- 리사이즈 핸들 -->
  <div class="cw-float-resize-handle"></div>
</div>

<!-- 최소화 시 복원 버튼 -->
<div id="<?php echo $_cw_uid; ?>_mini" class="cw-float-mini-btn" style="display:none;" title="캘린더 위젯 열기">
  📅
</div>

<style>
/* ══════════════════════════════════════
   플로팅 위젯 – 모든 스타일 scoped
   충돌 방지를 위해 .cw-float- 접두사 사용
   ══════════════════════════════════════ */

.cw-float-window {
  position: fixed;
  top: 24px;
  right: 24px;
  width: 340px;
  min-width: 260px;
  max-width: 90vw;
  z-index: 10000;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 8px 40px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.06);
  font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  overflow: hidden;
  transition: box-shadow 0.2s;
  user-select: none;
}
.cw-float-window:hover {
  box-shadow: 0 12px 48px rgba(0,0,0,0.16), 0 0 0 1px rgba(0,0,0,0.08);
}
.cw-float-window.cw-dragging {
  box-shadow: 0 20px 60px rgba(0,0,0,0.20), 0 0 0 1px rgba(0,0,0,0.10);
  opacity: 0.95;
}

/* ── 타이틀바 ── */
.cw-float-titlebar {
  display: flex;
  align-items: center;
  padding: 10px 14px;
  background: #f5f5f7;
  border-bottom: 1px solid #e8e8ec;
  cursor: grab;
  gap: 8px;
  min-height: 20px;
}
.cw-float-titlebar:active {
  cursor: grabbing;
}
.cw-float-dots {
  display: flex;
  gap: 7px;
  flex-shrink: 0;
}
.cw-float-dot {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  cursor: pointer;
  transition: filter 0.15s, transform 0.15s;
  position: relative;
}
.cw-float-dot:hover {
  filter: brightness(0.85);
  transform: scale(1.15);
}
.cw-dot-close  { background: #ff5f57; }
.cw-dot-min    { background: #febc2e; }
.cw-dot-max    { background: #28c840; }

/* 호버 시 아이콘 표시 */
.cw-float-dot::after {
  content: '';
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 8px;
  font-weight: 800;
  color: rgba(0,0,0,0.5);
  opacity: 0;
  transition: opacity 0.15s;
}
.cw-float-dots:hover .cw-dot-close::after  { content: '×'; opacity: 1; }
.cw-float-dots:hover .cw-dot-min::after    { content: '−'; opacity: 1; }
.cw-float-dots:hover .cw-dot-max::after    { content: '↕'; opacity: 1; font-size: 7px; }

.cw-float-title {
  flex: 1;
  text-align: center;
  font-size: 12px;
  font-weight: 500;
  color: #8e8e93;
  letter-spacing: 0.5px;
  pointer-events: none;
}
.cw-float-title-spacer {
  width: 52px;
  flex-shrink: 0;
}

/* ── 본문 ── */
.cw-float-body {
  padding: 14px 16px 8px;
  max-height: 70vh;
  overflow-y: auto;
  overflow-x: hidden;
  transition: max-height 0.3s ease, padding 0.3s ease, opacity 0.3s ease;
}
.cw-float-body::-webkit-scrollbar {
  width: 4px;
}
.cw-float-body::-webkit-scrollbar-thumb {
  background: #d0d0d4;
  border-radius: 4px;
}

/* 접힌 상태 */
.cw-float-window.cw-collapsed .cw-float-body {
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
  opacity: 0;
  overflow: hidden;
}
.cw-float-window.cw-collapsed .cw-float-resize-handle {
  display: none;
}

/* ── 일정 카드 ── */
.cw-float-event {
  padding: 10px 12px;
  border-radius: 10px;
  margin-bottom: 8px;
  transition: transform 0.15s;
}
.cw-float-event:last-of-type { margin-bottom: 0; }
.cw-float-event:hover { transform: translateX(3px); }

.cw-float-event-title {
  font-size: 13px;
  font-weight: 600;
  color: #1c1c1e;
  margin-bottom: 4px;
  line-height: 1.3;
}
.cw-float-event-meta {
  font-size: 11px;
  color: #6e6e73;
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.cw-float-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 3px;
}

/* 공휴일 태그 */
.cw-float-tag-holiday {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  color: #ff3b30;
  background: rgba(255,59,48,0.08);
  padding: 1px 6px;
  border-radius: 4px;
  margin-left: 6px;
  vertical-align: middle;
}

/* Goal 인라인 배지 (일정 목록 내) */
.cw-float-goal-inline {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  color: #af52de;
  background: rgba(175,82,222,0.10);
  padding: 1px 6px;
  border-radius: 4px;
  margin-left: 5px;
  vertical-align: middle;
}
.cw-float-goal-inline.dday-today {
  color: #ff3b30;
  background: rgba(255,59,48,0.08);
}

/* ── 빈 상태 ── */
.cw-float-empty {
  color: #8e8e93;
  font-size: 12px;
  font-family: 'Courier New', Courier, monospace;
  padding: 16px 0;
}
.cw-float-empty span { color: #ff9f0a; }

/* ── D-day Goal 섹션 ── */
.cw-float-goal-section {
  border-top: 1px solid #f0f0f2;
  margin-top: 8px;
  padding-top: 0;
}
.cw-float-goal-label {
  font-size: 11px;
  font-weight: 600;
  color: #8e8e93;
  letter-spacing: 0.5px;
  padding: 10px 0 6px;
  font-family: 'Courier New', Courier, monospace;
}
.cw-float-goal-label span { color: #af52de; }

.cw-float-goal-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 7px 10px;
  border-radius: 8px;
  margin-bottom: 5px;
  background: #faf5ff;
}
.cw-float-goal-item:last-child { margin-bottom: 0; }

.cw-float-goal-name {
  font-size: 12px;
  font-weight: 600;
  color: #1c1c1e;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cw-float-goal-dday {
  font-size: 12px;
  font-weight: 700;
  color: #af52de;
  flex-shrink: 0;
  margin-left: 8px;
  white-space: nowrap;
}
.cw-float-goal-dday.dday-today {
  color: #ff3b30;
  font-weight: 800;
}
.cw-float-goal-empty {
  font-size: 11px;
  color: #aeaeb2;
  padding: 6px 0;
}

/* ── 하단 링크 ── */
.cw-float-footer {
  padding: 8px 0 6px;
  text-align: right;
  border-top: 1px solid #f0f0f2;
  margin-top: 6px;
}
.cw-float-footer a {
  font-size: 11px;
  color: #007aff;
  text-decoration: none;
  font-weight: 500;
}
.cw-float-footer a:hover { text-decoration: underline; }

/* ── 리사이즈 핸들 ── */
.cw-float-resize-handle {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 16px;
  height: 16px;
  cursor: se-resize;
  opacity: 0;
  transition: opacity 0.2s;
}
.cw-float-window:hover .cw-float-resize-handle {
  opacity: 1;
}
.cw-float-resize-handle::before {
  content: '';
  position: absolute;
  bottom: 3px;
  right: 3px;
  width: 8px;
  height: 8px;
  border-right: 2px solid #c0c0c4;
  border-bottom: 2px solid #c0c0c4;
  border-radius: 0 0 2px 0;
}

/* ── 최소화 버튼 ── */
.cw-float-mini-btn {
  position: fixed;
  bottom: 24px;
  right: 24px;
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: #ffffff;
  box-shadow: 0 4px 20px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  cursor: pointer;
  z-index: 10001;
  transition: transform 0.2s, box-shadow 0.2s;
  user-select: none;
}
.cw-float-mini-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 6px 24px rgba(0,0,0,0.18);
}

/* ── 등장 애니메이션 ── */
@keyframes cwFloatIn {
  from { opacity: 0; transform: translateY(-12px) scale(0.96); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}
.cw-float-window.cw-visible {
  display: block !important;
  animation: cwFloatIn 0.3s ease-out;
}

/* ── 반응형 ── */
@media (max-width: 480px) {
  .cw-float-window {
    width: calc(100vw - 16px) !important;
    left: 8px !important;
    right: 8px !important;
    top: 8px !important;
    max-width: none;
    border-radius: 10px;
  }
  .cw-float-mini-btn {
    bottom: 16px;
    right: 16px;
  }
}
</style>

<script>
(function(){
  'use strict';

  var WIDGET_ID = '<?php echo $_cw_uid; ?>';
  var POS_STORAGE_KEY  = 'cw_float_pos';
  var STATE_STORAGE_KEY = 'cw_float_state'; // 'open' | 'minimized' | 'closed'

  var win, miniBtn, titlebar, body, resizeHandle;
  var isDragging = false, isResizing = false;
  var dragOffsetX = 0, dragOffsetY = 0;
  var startW = 0, startH = 0, startX = 0, startY = 0;

  function init() {
    win       = document.getElementById(WIDGET_ID);
    miniBtn   = document.getElementById(WIDGET_ID + '_mini');
    if (!win || !miniBtn) return;

    titlebar     = win.querySelector('.cw-float-titlebar');
    body         = win.querySelector('.cw-float-body');
    resizeHandle = win.querySelector('.cw-float-resize-handle');

    restoreState();
    restorePosition();
    bindTitlebarDots();
    bindDrag();
    bindResize();
    bindMiniBtn();
  }

  /* ══════ 상태 저장/복원 ══════ */
  function saveState(state) {
    try { localStorage.setItem(STATE_STORAGE_KEY, state); } catch(e){}
  }
  function getState() {
    try { return localStorage.getItem(STATE_STORAGE_KEY) || 'open'; } catch(e){ return 'open'; }
  }
  function restoreState() {
    var state = getState();
    if (state === 'closed' || state === 'minimized') {
      win.style.display = 'none';
      miniBtn.style.display = 'flex';
    } else {
      win.style.display = 'block';
      win.classList.add('cw-visible');
      miniBtn.style.display = 'none';
    }
  }

  /* ══════ 위치 저장/복원 ══════ */
  function savePosition() {
    try {
      var data = {
        top:    win.style.top,
        left:   win.style.left,
        right:  win.style.right,
        width:  win.style.width
      };
      localStorage.setItem(POS_STORAGE_KEY, JSON.stringify(data));
    } catch(e){}
  }
  function restorePosition() {
    try {
      var raw = localStorage.getItem(POS_STORAGE_KEY);
      if (!raw) return;
      var data = JSON.parse(raw);
      if (data.top)   win.style.top   = data.top;
      if (data.left)  win.style.left  = data.left;
      if (data.width) win.style.width = data.width;
      // right는 left가 설정되면 해제
      if (data.left) win.style.right = 'auto';
    } catch(e){}
    // 화면 밖으로 나갔는지 보정
    clampToViewport();
  }
  function clampToViewport() {
    var rect = win.getBoundingClientRect();
    var changed = false;
    if (rect.left < 0) { win.style.left = '0px'; win.style.right = 'auto'; changed = true; }
    if (rect.top < 0)  { win.style.top = '0px'; changed = true; }
    if (rect.right > window.innerWidth) {
      win.style.left = Math.max(0, window.innerWidth - rect.width) + 'px';
      win.style.right = 'auto';
      changed = true;
    }
    if (rect.bottom > window.innerHeight && rect.top > 0) {
      win.style.top = Math.max(0, window.innerHeight - rect.height) + 'px';
      changed = true;
    }
    if (changed) savePosition();
  }

  /* ══════ 타이틀바 도트 버튼 ══════ */
  function bindTitlebarDots() {
    var closeBtn = win.querySelector('.cw-dot-close');
    var minBtn   = win.querySelector('.cw-dot-min');
    var maxBtn   = win.querySelector('.cw-dot-max');

    if (closeBtn) {
      closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        win.style.display = 'none';
        win.classList.remove('cw-visible');
        miniBtn.style.display = 'flex';
        saveState('closed');
      });
    }

    if (minBtn) {
      minBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        win.style.display = 'none';
        win.classList.remove('cw-visible');
        miniBtn.style.display = 'flex';
        saveState('minimized');
      });
    }

    if (maxBtn) {
      maxBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        win.classList.toggle('cw-collapsed');
      });
    }
  }

  /* ══════ 미니 버튼으로 복원 ══════ */
  function bindMiniBtn() {
    miniBtn.addEventListener('click', function() {
      miniBtn.style.display = 'none';
      win.style.display = 'block';
      win.classList.add('cw-visible');
      win.classList.remove('cw-collapsed');
      saveState('open');
      clampToViewport();
    });
  }

  /* ══════ 드래그 ══════ */
  function bindDrag() {
    titlebar.addEventListener('mousedown', onDragStart);
    titlebar.addEventListener('touchstart', onDragStartTouch, { passive: false });
  }

  function onDragStart(e) {
    // 도트 버튼 위에서는 드래그 시작 안 함
    if (e.target.classList.contains('cw-float-dot')) return;
    e.preventDefault();
    isDragging = true;
    win.classList.add('cw-dragging');

    var rect = win.getBoundingClientRect();
    dragOffsetX = e.clientX - rect.left;
    dragOffsetY = e.clientY - rect.top;

    document.addEventListener('mousemove', onDragMove);
    document.addEventListener('mouseup', onDragEnd);
  }

  function onDragStartTouch(e) {
    if (e.target.classList.contains('cw-float-dot')) return;
    if (e.touches.length !== 1) return;
    e.preventDefault();
    isDragging = true;
    win.classList.add('cw-dragging');

    var rect = win.getBoundingClientRect();
    var t = e.touches[0];
    dragOffsetX = t.clientX - rect.left;
    dragOffsetY = t.clientY - rect.top;

    document.addEventListener('touchmove', onDragMoveTouch, { passive: false });
    document.addEventListener('touchend', onDragEndTouch);
  }

  function onDragMove(e) {
    if (!isDragging) return;
    var newLeft = e.clientX - dragOffsetX;
    var newTop  = e.clientY - dragOffsetY;
    // 화면 밖 방지
    newLeft = Math.max(0, Math.min(newLeft, window.innerWidth - 60));
    newTop  = Math.max(0, Math.min(newTop, window.innerHeight - 40));
    win.style.left  = newLeft + 'px';
    win.style.top   = newTop + 'px';
    win.style.right = 'auto';
  }

  function onDragMoveTouch(e) {
    if (!isDragging || !e.touches[0]) return;
    e.preventDefault();
    var t = e.touches[0];
    var newLeft = t.clientX - dragOffsetX;
    var newTop  = t.clientY - dragOffsetY;
    newLeft = Math.max(0, Math.min(newLeft, window.innerWidth - 60));
    newTop  = Math.max(0, Math.min(newTop, window.innerHeight - 40));
    win.style.left  = newLeft + 'px';
    win.style.top   = newTop + 'px';
    win.style.right = 'auto';
  }

  function onDragEnd() {
    isDragging = false;
    win.classList.remove('cw-dragging');
    document.removeEventListener('mousemove', onDragMove);
    document.removeEventListener('mouseup', onDragEnd);
    savePosition();
  }

  function onDragEndTouch() {
    isDragging = false;
    win.classList.remove('cw-dragging');
    document.removeEventListener('touchmove', onDragMoveTouch);
    document.removeEventListener('touchend', onDragEndTouch);
    savePosition();
  }

  /* ══════ 리사이즈 ══════ */
  function bindResize() {
    if (!resizeHandle) return;
    resizeHandle.addEventListener('mousedown', onResizeStart);
  }

  function onResizeStart(e) {
    e.preventDefault();
    e.stopPropagation();
    isResizing = true;
    startX = e.clientX;
    startY = e.clientY;
    startW = win.offsetWidth;
    startH = win.offsetHeight;
    document.addEventListener('mousemove', onResizeMove);
    document.addEventListener('mouseup', onResizeEnd);
  }

  function onResizeMove(e) {
    if (!isResizing) return;
    var newW = startW + (e.clientX - startX);
    if (newW < 260) newW = 260;
    if (newW > window.innerWidth - 16) newW = window.innerWidth - 16;
    win.style.width = newW + 'px';
  }

  function onResizeEnd() {
    isResizing = false;
    document.removeEventListener('mousemove', onResizeMove);
    document.removeEventListener('mouseup', onResizeEnd);
    savePosition();
  }

  /* ══════ 시작 ══════ */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
