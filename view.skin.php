<?php
if (!defined("_GNUBOARD_")) exit;

$event_date  = $view['wr_1'] ? $view['wr_1'] : substr($view['wr_datetime'], 0, 10);
$event_end   = $view['wr_2'] ? $view['wr_2'] : $event_date;
$event_color = $view['wr_3'] ? $view['wr_3'] : '#3B82F6';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Pretendard:wght@200;300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">

<?php
$_skin_url = str_replace('http://', 'https://', $board_skin_url);
?>
<link rel="stylesheet" href="<?php echo $_skin_url; ?>/style.css">

<div id="calendar-board" class="cal-wrap">
    <div class="cal-view-card">
        <div class="cal-view-header" style="border-left: 5px solid <?php echo htmlspecialchars($event_color); ?>">
            <div class="cal-view-badge" style="background:<?php echo htmlspecialchars($event_color); ?>15;color:<?php echo htmlspecialchars($event_color); ?>">
                <i class="fa-regular fa-calendar"></i>
                <?php echo $event_date; ?>
                <?php if ($event_end && $event_end != $event_date) { ?>
                    ~ <?php echo $event_end; ?>
                <?php } ?>
            </div>
            <h2 class="cal-view-title"><?php echo htmlspecialchars($view['wr_subject']); ?></h2>
            <div class="cal-view-meta">
                <span><i class="fa-regular fa-user"></i> <?php echo $view['wr_name']; ?></span>
                <span><i class="fa-regular fa-clock"></i> <?php echo substr($view['wr_datetime'], 0, 16); ?></span>
                <span><i class="fa-regular fa-eye"></i> <?php echo number_format($view['wr_hit']); ?></span>
            </div>
        </div>
        <div class="cal-view-content">
            <?php echo $view['wr_content']; ?>
        </div>
        <div class="cal-view-footer">
            <?php if ($update_href) { ?>
            <a href="<?php echo $update_href; ?>" class="cal-btn cal-btn-edit">
                <i class="fa-solid fa-pen"></i> 수정
            </a>
            <?php } ?>
            <?php if ($delete_href) { ?>
            <a href="<?php echo $delete_href; ?>" class="cal-btn cal-btn-delete" onclick="return confirm('정말 삭제하시겠습니까?');">
                <i class="fa-solid fa-trash"></i> 삭제
            </a>
            <?php } ?>
            <a href="./board.php?bo_table=<?php echo $bo_table; ?>" class="cal-btn cal-btn-list">
                <i class="fa-solid fa-calendar"></i> 캘린더로 돌아가기
            </a>
        </div>
    </div>

    <!-- 댓글 영역 -->
    <?php if ($board['bo_comment_level'] <= $member['mb_level']) { ?>
    <div class="cal-comment-section">
        <h3><i class="fa-regular fa-comments"></i> 댓글</h3>
        <?php echo $comment_list; ?>
    </div>
    <?php } ?>
</div>