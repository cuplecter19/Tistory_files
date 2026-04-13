<?php if (!defined("_GNUBOARD_")) exit; ?>
<?php
$_skin_url = str_replace('http://', 'https://', $board_skin_url);
$event_date  = isset($write['wr_1']) && $write['wr_1'] ? $write['wr_1'] : date('Y-m-d');
$event_end   = isset($write['wr_2']) && $write['wr_2'] ? $write['wr_2'] : date('Y-m-d');
$event_color = isset($write['wr_3']) && $write['wr_3'] ? $write['wr_3'] : '#3B82F6';
$event_wr9   = isset($write['wr_9']) ? $write['wr_9'] : '';
$event_wr4   = isset($write['wr_4']) ? $write['wr_4'] : '';
$event_wr5   = isset($write['wr_5']) ? $write['wr_5'] : '';
$_is_goal   = (strpos($event_wr9, 'GOAL=1') !== false);
$_is_dday   = (strpos($event_wr9, 'DDAY=1') !== false);
$_is_widget = (strpos($event_wr9, 'WIDGET=1') !== false);
?>
<link rel="stylesheet" href="<?php echo $_skin_url; ?>/style.css">
<form name="fwrite" id="fwrite" action="<?php echo $action_url; ?>" method="post" enctype="multipart/form-data" autocomplete="off" onsubmit="return calBuildWr9();">
<input type="hidden" name="w" value="<?php echo $w; ?>">
<input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
<input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
<input type="hidden" name="wr_4" value="<?php echo htmlspecialchars($event_wr4); ?>">
<input type="hidden" name="wr_5" value="<?php echo htmlspecialchars($event_wr5); ?>">
<input type="hidden" name="wr_9" id="fwrite_wr_9" value="<?php echo htmlspecialchars($event_wr9); ?>">
<input type="text" name="wr_subject" value="<?php echo htmlspecialchars($write['wr_subject']); ?>" required>
<textarea name="wr_content"><?php echo htmlspecialchars($write['wr_content']); ?></textarea>
<input type="date" name="wr_1" value="<?php echo $event_date; ?>">
<input type="date" name="wr_2" value="<?php echo $event_end; ?>">
<input type="time" name="wr_6" value="<?php echo isset($write['wr_6'])?$write['wr_6']:''; ?>">
<input type="time" name="wr_7" value="<?php echo isset($write['wr_7'])?$write['wr_7']:''; ?>">
<input type="text" name="wr_3" value="<?php echo $event_color; ?>">
<!-- D-day 타입 선택 -->
<div class="cal-form-row cal-dday-type-row">
  <span class="cal-label-text">타입</span>
  <label class="cal-radio-label"><input type="radio" name="cal_dday_type" value="none"<?php if (!$_is_goal && !$_is_dday) echo ' checked'; ?>> 없음</label>
  <label class="cal-radio-label"><input type="radio" name="cal_dday_type" value="goal"<?php if ($_is_goal) echo ' checked'; ?>> ⚑ Goal</label>
  <label class="cal-radio-label"><input type="radio" name="cal_dday_type" value="dday"<?php if ($_is_dday) echo ' checked'; ?>> ◈ D-day</label>
</div>
<!-- 위젯 표시 여부 -->
<div class="cal-form-row cal-widget-row">
  <label class="cal-goal-check"><input type="checkbox" name="cal_widget" id="fwrite_cal_widget" value="1"<?php if ($_is_widget) echo ' checked'; ?>> <span class="cal-goal-check-label">📌 위젯에 표시</span></label>
</div>
<?php if ($w != 'u') { ?>
<label><input type="checkbox" name="cal_repeat" id="fwrite_cal_repeat" value="1"> 반복</label>
<select name="cal_repeat_type" id="fwrite_cal_repeat_type"><option value="daily">매일</option><option value="weekly">매주</option><option value="monthly">매월</option></select>
<input type="number" name="cal_repeat_count" id="fwrite_cal_repeat_count" value="4" min="1" max="365">
<?php } ?>
<button type="submit"><?php echo $w == 'u' ? '수정' : '등록'; ?></button>
</form>
<script>
function calBuildWr9(){
  var parts = [];
  var radios = document.querySelectorAll('input[name="cal_dday_type"]');
  var ddayType = 'none';
  for (var i = 0; i < radios.length; i++) { if (radios[i].checked) { ddayType = radios[i].value; break; } }
  if (ddayType === 'goal') parts.push('GOAL=1');
  if (ddayType === 'dday') parts.push('DDAY=1');
  var widgetChk = document.getElementById('fwrite_cal_widget');
  if (widgetChk && widgetChk.checked) parts.push('WIDGET=1');
  var repeatChk = document.getElementById('fwrite_cal_repeat');
  var repeatType = document.getElementById('fwrite_cal_repeat_type');
  var repeatCount = document.getElementById('fwrite_cal_repeat_count');
  if (repeatChk && repeatChk.checked && repeatCount && parseInt(repeatCount.value, 10) > 0) {
    var rType = (repeatType && repeatType.value) ? repeatType.value.toUpperCase() : 'WEEKLY';
    parts.push('FREQ=' + rType + ';COUNT=' + parseInt(repeatCount.value, 10));
  }
  document.getElementById('fwrite_wr_9').value = parts.join(';');
  // 수정 모드에서 구글 연동 이벤트라면 submit 시점에 local_modified로 표시
  var wr4 = document.querySelector('input[name="wr_4"]');
  var wr5 = document.querySelector('input[name="wr_5"]');
  if (wr4 && wr5 && wr4.value !== '') {
    var cur = wr5.value;
    if (cur === 'google' || cur === 'both') { wr5.value = 'local_modified'; }
  }
  return true;
}
</script>