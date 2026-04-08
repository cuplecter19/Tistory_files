<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * [필수] 아래 값들을 Google Cloud에서 발급받은 값으로 교체하세요.
 * - OAuth 동의 화면/사용자 유형/승인된 리디렉션 URI 설정 필요
 * - Calendar API 활성화 필요
 */
define('GCAL_CLIENT_ID', 'your google calendar client id');
define('GCAL_CLIENT_SECRET', 'secret key');
define('GCAL_REDIRECT_URI', 'your skin url/google_oauth_callback.php');

/**
 * 쓰기(push) 대상 캘린더 ID
 * - 로컬 일정을 구글로 올릴 때 사용
 */
define('GCAL_PRIMARY_CALENDAR_ID', 'primary');

/**
 * 하위 호환용 별칭
 */
define('GCAL_CALENDAR_ID', GCAL_PRIMARY_CALENDAR_ID);

/**
 * 조회(동기화)할 캘린더 목록
 */
$GCAL_SOURCE_CALENDARS = array(
    'primary',
    'ko.south_korea#holiday@group.v.calendar.google.com'
);

/**
 * 공휴일 캘린더 ID 목록
 * - 아래 목록에 포함된 캘린더에서 가져온 이벤트는 빨간색(#D50000)으로 처리
 */
$GCAL_HOLIDAY_CALENDARS = array(
    'ko.south_korea#holiday@group.v.calendar.google.com'
);

/**
 * 공휴일 색상
 */
define('GCAL_HOLIDAY_COLOR', '#D50000');

/**
 * 테이블명
 */
define('GCAL_TOKEN_TABLE', $g5['prefix'].'calendar_google_token');
define('GCAL_MAP_TABLE', $g5['prefix'].'calendar_google_map');
