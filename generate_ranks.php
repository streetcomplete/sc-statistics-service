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

function createGenerateRanksStatements(string $table, int $last_x_days = -1) {
    $last_x_days_condition = '';
    if ($last_x_days > 0) {
        $last_x_days_condition = 'WHERE created_at > DATE_SUB(CURDATE(), INTERVAL '.$last_x_days.' DAY)';
    }
    return "
CREATE TABLE IF NOT EXISTS ".$table."(
  user_id BIGINT UNSIGNED,
  country_code VARCHAR(6) DEFAULT '',
  rank INT,
  changes_count INT,
  CONSTRAINT user_pkey PRIMARY KEY (user_id, country_code)
);

START TRANSACTION;

DELETE FROM ".$table.";

INSERT INTO ".$table." (user_id, country_code, changes_count)
  SELECT user_id, SUBSTRING_INDEX(country_code, '-', 1), SUM(changes_count)
  FROM changesets 
  ".$last_x_days_condition."
  GROUP BY user_id, SUBSTRING_INDEX(country_code, '-', 1);

REPLACE INTO ".$table." (user_id, country_code, changes_count)
  SELECT user_id, NULL, SUM(changes_count)
  FROM ".$table."
  GROUP BY user_id;

REPLACE INTO ".$table." (user_id, country_code, changes_count, rank)
  SELECT 
    user_id, country_code, changes_count,
    DENSE_RANK() OVER (PARTITION BY country_code ORDER BY changes_count DESC)
  FROM ".$table.";

COMMIT;
";
}

$mysqli->multi_query(
    createGenerateRanksStatements('user_ranks') . 
    createGenerateRanksStatements('user_ranks_current_week', 7)
);

$mysqli->close();

http_response_code(200);
