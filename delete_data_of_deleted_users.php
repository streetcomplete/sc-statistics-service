#!/usr/bin/env php
<?php
/** Fetches the IDs of all deleted users from OSM (these are 22MB and growing) and deletes the 
 *  data associated to them from all the tables.
 *  This is for GDPR compliance, as deleted users should have all data, also derived/aggregated
 *  data, deleted.
 * 
 *  Can only be called from cli. Should be run daily in a cronjob - the users_deleted.txt is
 *  daily.
 * 
 *  See https://planet.openstreetmap.org/users_deleted/  */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// step 1: create temporary table to load deleted users data into
$mysqli = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_NAME);
$mysqli->query(
    'CREATE TABLE IF NOT EXISTS deleted_user_ids (
      user_id BIGINT UNSIGNED PRIMARY KEY
    )'
);

$row = $mysqli->query('SELECT user_id FROM deleted_user_ids ORDER BY user_id DESC LIMIT 1;')->fetch_row();
$highest_deleted_user_id = $row ? $row[0] : 0;

// step 2: download file, parse and insert
$url = "https://planet.openstreetmap.org/users_deleted/users_deleted.txt";
$handle = fopen($url, 'r');
$mysqli->begin_transaction();
$count = 0;
while (($line = fgets($handle)) !== false) {
    $user_id = intval($line);
    if(!$user_id || $user_id <= $highest_deleted_user_id) continue;

    $stmt = $mysqli->prepare('INSERT INTO deleted_user_ids (user_id) VALUES (?)');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->close();
    $count++;

    if ($count % 10000 == 0) {
        $mysqli->commit();
        //echo $user_id . PHP_EOL;
        $mysqli->begin_transaction();
    }
}
$mysqli->commit();

// step 3: delete the rows belonging to deleted users from the other tables
$mysqli->query(
    'DELETE FROM changesets_walker_state
      WHERE user_id IN (SELECT user_id FROM deleted_user_ids)'
);
$mysqli->query(
    'DELETE FROM changesets
      WHERE user_id IN (SELECT user_id FROM deleted_user_ids)'
);

$mysqli->close();

http_response_code(200);