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
START TRANSACTION;

CREATE TABLE IF NOT EXISTS user_ranks(
  user_id BIGINT UNSIGNED,
  country_code VARCHAR(6) DEFAULT '',
  rank INT NOT NULL,
  solved_quest_count INT,
  CONSTRAINT user_pkey PRIMARY KEY (user_id, country_code)
);

DELETE FROM user_ranks;

CREATE TEMPORARY TABLE solved_quest_counts_by_user AS 
  SELECT user_id, SUM(solved_quest_count) as solved_quest_count
  FROM changesets GROUP BY user_id;

INSERT INTO user_ranks (user_id, rank, solved_quest_count)
  SELECT user_id, @rank := @rank + 1 rank, solved_quest_count
  FROM solved_quest_counts_by_user, (SELECT @rank := 0) init
  ORDER BY solved_quest_count DESC;

DROP PROCEDURE IF EXISTS insert_country_ranks;
DELIMITER //
CREATE PROCEDURE insert_country_ranks()
BEGIN

  DECLARE done INT DEFAULT FALSE;
  DECLARE current_country_code VARCHAR(6);
  DECLARE all_country_codes CURSOR FOR SELECT DISTINCT SUBSTRING_INDEX(country_code, '-', 1) FROM changesets;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  CREATE TEMPORARY TABLE solved_quest_counts_by_user_and_country AS 
    SELECT user_id, SUBSTRING_INDEX(country_code, '-', 1) AS country_code, SUM(solved_quest_count) as solved_quest_count
    FROM changesets
    GROUP BY user_id, SUBSTRING_INDEX(country_code, '-', 1);
  
  OPEN all_country_codes;
  
  country_ranks_loop: LOOP
    FETCH all_country_codes INTO current_country_code;
    IF done THEN
      LEAVE country_ranks_loop;
    END IF;
    
    INSERT INTO user_ranks
      SELECT user_id, country_code, @rank := @rank + 1 rank, solved_quest_count
      FROM solved_quest_counts_by_user_and_country, (SELECT @rank := 0) init
      WHERE country_code = current_country_code
      ORDER BY solved_quest_count DESC;
    
  END LOOP;

  CLOSE all_country_codes;
END//
DELIMITER ;
CALL insert_country_ranks;

COMMIT;
");

$mysqli->close();

http_response_code(200);