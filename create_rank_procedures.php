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
DROP PROCEDURE IF EXISTS insert_country_ranks;

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
END;

DROP PROCEDURE IF EXISTS insert_country_ranks_current_week;

CREATE PROCEDURE insert_country_ranks_current_week()
BEGIN

  DECLARE done INT DEFAULT FALSE;
  DECLARE current_country_code VARCHAR(6);
  DECLARE all_country_codes CURSOR FOR SELECT DISTINCT SUBSTRING_INDEX(country_code, '-', 1) FROM changesets;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  CREATE TEMPORARY TABLE solved_quest_counts_by_user_and_country_current_week AS
    SELECT user_id, SUBSTRING_INDEX(country_code, '-', 1) AS country_code, SUM(solved_quest_count) as solved_quest_count
    FROM changesets
    WHERE created_at >= ( DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) )
    GROUP BY user_id, SUBSTRING_INDEX(country_code, '-', 1);

  OPEN all_country_codes;

  country_ranks_loop: LOOP
    FETCH all_country_codes INTO current_country_code;
    IF done THEN
      LEAVE country_ranks_loop;
    END IF;

    INSERT INTO user_ranks_current_week
      SELECT user_id, country_code, @rank := @rank + 1 rank, solved_quest_count
      FROM solved_quest_counts_by_user_and_country_current_week, (SELECT @rank := 0) init
      WHERE country_code = current_country_code
      ORDER BY solved_quest_count DESC;

  END LOOP;

  CLOSE all_country_codes;
END;

DROP PROCEDURE IF EXISTS insert_country_ranks_last_week;

CREATE PROCEDURE insert_country_ranks_last_week()
BEGIN

  DECLARE done INT DEFAULT FALSE;
  DECLARE current_country_code VARCHAR(6);
  DECLARE all_country_codes CURSOR FOR SELECT DISTINCT SUBSTRING_INDEX(country_code, '-', 1) FROM changesets;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  CREATE TEMPORARY TABLE solved_quest_counts_by_user_and_country_last_week AS
    SELECT user_id, SUBSTRING_INDEX(country_code, '-', 1) AS country_code, SUM(solved_quest_count) as solved_quest_count
    FROM changesets
    WHERE created_at >= ( DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 7 DAY) )
    AND created_at < ( DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY) )
    GROUP BY user_id, SUBSTRING_INDEX(country_code, '-', 1);

  OPEN all_country_codes;

  country_ranks_loop: LOOP
    FETCH all_country_codes INTO current_country_code;
    IF done THEN
      LEAVE country_ranks_loop;
    END IF;

    INSERT INTO user_ranks_last_week
      SELECT user_id, country_code, @rank := @rank + 1 rank, solved_quest_count
      FROM solved_quest_counts_by_user_and_country_last_week, (SELECT @rank := 0) init
      WHERE country_code = current_country_code
      ORDER BY solved_quest_count DESC;

  END LOOP;

  CLOSE all_country_codes;
END;
");

$mysqli->close();

http_response_code(200);
