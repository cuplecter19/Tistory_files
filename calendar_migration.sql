-- 1) write 테이블 확장 (예: g5_write_게시판ID)
-- 실제 테이블명에 맞게 실행하세요.
-- ALTER TABLE g5_write_calendar
--   ADD COLUMN wr_4 varchar(255) NOT NULL DEFAULT '' COMMENT 'google_event_id',
--   ADD COLUMN wr_5 varchar(20) NOT NULL DEFAULT 'local' COMMENT 'sync_source',
--   ADD COLUMN wr_6 varchar(5) NOT NULL DEFAULT '' COMMENT 'start_time HH:MM',
--   ADD COLUMN wr_7 varchar(5) NOT NULL DEFAULT '' COMMENT 'end_time HH:MM',
--   ADD COLUMN wr_8 varchar(64) NOT NULL DEFAULT 'Asia/Seoul' COMMENT 'timezone',
--   ADD COLUMN wr_9 text COMMENT 'recurrence rule';

-- 2) 토큰 테이블
CREATE TABLE IF NOT EXISTS g5_calendar_google_token (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 3) 매핑 테이블
CREATE TABLE IF NOT EXISTS g5_calendar_google_map (
  id int(11) NOT NULL AUTO_INCREMENT,
  bo_table varchar(50) NOT NULL,
  wr_id int(11) NOT NULL,
  google_event_id varchar(255) NOT NULL,
  sync_source varchar(20) NOT NULL DEFAULT 'local',
  updated_at datetime NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_wr (bo_table, wr_id),
  UNIQUE KEY uniq_google (bo_table, google_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;