#!/usr/bin/env php
<?php
/** Generates the leaderboards / ranks by country and globally
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$mysqli = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_NAME);
$mysqli->multi_query("

CREATE TABLE IF NOT EXISTS user_ranks(
  user_id BIGINT UNSIGNED,
  country_code VARCHAR(6) DEFAULT '',
  rank INT NOT NULL,
  solved_quest_count INT,
  CONSTRAINT user_pkey PRIMARY KEY (user_id, country_code)
);

START TRANSACTION;

DELETE FROM user_ranks;

CREATE TEMPORARY TABLE solved_quest_counts_by_user AS
  SELECT user_id, SUM(solved_quest_count) as solved_quest_count
  FROM changesets GROUP BY user_id;

INSERT INTO user_ranks (user_id, rank, solved_quest_count)
  SELECT user_id, @rank := @rank + 1 rank, solved_quest_count
  FROM solved_quest_counts_by_user, (SELECT @rank := 0) init
  ORDER BY solved_quest_count DESC;

CALL insert_country_ranks;

COMMIT;

CREATE TABLE IF NOT EXISTS user_ranks_current_week(
  user_id BIGINT UNSIGNED,
  country_code VARCHAR(6) DEFAULT '',
  rank INT NOT NULL,
  solved_quest_count INT,
  CONSTRAINT user_pkey PRIMARY KEY (user_id, country_code)
);

START TRANSACTION;

DELETE FROM user_ranks_current_week;

CREATE TEMPORARY TABLE solved_quest_counts_by_user_current_week AS
  SELECT user_id, SUM(solved_quest_count) as solved_quest_count
  FROM changesets
  WHERE created_at >= ( DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) )
  GROUP BY user_id;

INSERT INTO user_ranks_current_week (user_id, rank, solved_quest_count)
  SELECT user_id, @rank := @rank + 1 rank, solved_quest_count
  FROM solved_quest_counts_by_user_current_week, (SELECT @rank := 0) init
  ORDER BY solved_quest_count DESC;

CALL insert_country_ranks_current_week;

COMMIT;

CREATE TABLE IF NOT EXISTS user_ranks_last_week(
  user_id BIGINT UNSIGNED,
  country_code VARCHAR(6) DEFAULT '',
  rank INT NOT NULL,
  solved_quest_count INT,
  CONSTRAINT user_pkey PRIMARY KEY (user_id, country_code)
);

START TRANSACTION;

DELETE FROM user_ranks_last_week;

CREATE TEMPORARY TABLE solved_quest_counts_by_user_last_week AS
  SELECT user_id, SUM(solved_quest_count) as solved_quest_count
  FROM changesets
  WHERE created_at >= ( DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 7 DAY) )
  AND created_at < ( DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) )
  GROUP BY user_id;

INSERT INTO user_ranks_last_week (user_id, rank, solved_quest_count)
  SELECT user_id, @rank := @rank + 1 rank, solved_quest_count
  FROM solved_quest_counts_by_user_last_week, (SELECT @rank := 0) init
  ORDER BY solved_quest_count DESC;

CALL insert_country_ranks_last_week;

COMMIT;
");

$mysqli->close();

http_response_code(200);
