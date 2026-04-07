<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * 최초 1회 실행 시 테이블 생성
 */
function gcal_ensure_tables() {
    global $g5;

    $token_table = $g5['prefix'].'calendar_google_token';
    $map_table   = $g5['prefix'].'calendar_google_map';

    sql_query("CREATE TABLE IF NOT EXISTS {$token_table} (
        id int(11) NOT NULL AUTO_INCREMENT,
        mb_id varchar(50) NOT NULL,
        access_token text NOT NULL,
        refresh_token text,
        expires_at datetime,
        token_type varchar(50),
        scope text,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_mb (mb_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8", false);

    sql_query("CREATE TABLE IF NOT EXISTS {$map_table} (
        id int(11) NOT NULL AUTO_INCREMENT,
        bo_table varchar(50) NOT NULL,
        wr_id int(11) NOT NULL,
        google_event_id varchar(255) NOT NULL,
        sync_source varchar(20) NOT NULL DEFAULT 'local',
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_wr (bo_table, wr_id),
        UNIQUE KEY uniq_google (bo_table, google_event_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8", false);
}