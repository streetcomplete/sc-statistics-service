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
  rank INT,
  solved_quest_count INT,
  CONSTRAINT user_pkey PRIMARY KEY (user_id, country_code)
);

START TRANSACTION;

DELETE FROM user_ranks;

INSERT INTO user_ranks (user_id, country_code, solved_quest_count)
  SELECT user_id, country_code, SUM(solved_quest_count)
  FROM changesets GROUP BY user_id, country_code;

REPLACE INTO user_ranks (user_id, country_code, solved_quest_count)
  SELECT user_id, NULL, SUM(solved_quest_count)
  FROM user_ranks GROUP BY user_id;

REPLACE INTO user_ranks (user_id, country_code, solved_quest_count, rank)
  SELECT 
    user_id, country_code, solved_quest_count,
	DENSE_RANK() OVER (PARTITION BY country_code ORDER BY solved_quest_count DESC)
  FROM user_ranks;

COMMIT;
");

$mysqli->close();

http_response_code(200);
