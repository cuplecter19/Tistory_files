<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * [필수] 아래 값들을 Google Cloud에서 발급받은 값으로 교체하세요.
 * - OAuth 동의 화면/사용자 유형/승인된 리디렉션 URI 설정 필요
 * - Calendar API 활성화 필요
 */
define('GCAL_CLIENT_ID', '74014543406-p34i23o7tc76n95ofrp6nmijpghgn36q.apps.googleusercontent.com');
define('GCAL_CLIENT_SECRET', 'github 보안상 생략');
define('GCAL_REDIRECT_URI', 'https://milkyway1206.ivyro.net/skin/board/calendar/google_oauth_callback.php');

/**
 * 동기화 대상 캘린더 ID
 * - 기본 캘린더는 'primary'
 * - 특정 캘린더는 이메일 형태 ID 사용 가능
 */
define('GCAL_CALENDAR_ID', '07b6b1683974a26cd5b4d2ebbc4adb30edeeb51bc3490e6127703353d0023ea5@group.calendar.google.com');

/**
 * 토큰 저장 테이블명
 */
define('GCAL_TOKEN_TABLE', $g5['prefix'].'calendar_google_token');