<?php if (!defined("_GNUBOARD_")) exit; ?>
<?php
$_skin_url = str_replace('http://', 'https://', $board_skin_url);
$event_date  = isset($write['wr_1']) && $write['wr_1'] ? $write['wr_1'] : date('Y-m-d');
$event_end   = isset($write['wr_2']) && $write['wr_2'] ? $write['wr_2'] : date('Y-m-d');
$event_color = isset($write['wr_3']) && $write['wr_3'] ? $write['wr_3'] : '#3B82F6';
?>
<link rel="stylesheet" href="<?php echo $_skin_url; ?>/style.css">
<form name="fwrite" id="fwrite" action="<?php echo $action_url; ?>" method="post" enctype="multipart/form-data" autocomplete="off">
<input type="hidden" name="w" value="<?php echo $w; ?>">
<input type="hidden" name="bo_table" value="<?php echo $bo_table; ?>">
<input type="hidden" name="wr_id" value="<?php echo $wr_id; ?>">
<input type="text" name="wr_subject" value="<?php echo htmlspecialchars($write['wr_subject']); ?>" required>
<textarea name="wr_content"><?php echo htmlspecialchars($write['wr_content']); ?></textarea>
<input type="date" name="wr_1" value="<?php echo $event_date; ?>">
<input type="date" name="wr_2" value="<?php echo $event_end; ?>">
<input type="time" name="wr_6" value="<?php echo isset($write['wr_6'])?$write['wr_6']:''; ?>">
<input type="time" name="wr_7" value="<?php echo isset($write['wr_7'])?$write['wr_7']:''; ?>">
<input type="text" name="wr_3" value="<?php echo $event_color; ?>">
<?php if ($w != 'u') { ?>
<label><input type="checkbox" name="cal_repeat" value="1"> 반복</label>
<select name="cal_repeat_type"><option value="daily">매일</option><option value="weekly">매주</option><option value="monthly">매월</option></select>
<input type="number" name="cal_repeat_count" value="4" min="1" max="365">
<?php } ?>
<button type="submit"><?php echo $w == 'u' ? '수정' : '등록'; ?></button>
</form>