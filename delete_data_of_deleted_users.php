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

$url = "https://planet.openstreetmap.org/users_deleted/users_deleted.txt";
$filename = "users_deleted.txt";
// step 1: download file into this directory 
file_put_contents($filename, fopen($url, 'r'));

// step 2: create temporary table to load the data into
$mysqli = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_NAME);
$mysqli->query(
    'CREATE TEMPORARY TABLE deleted_user_ids (
      user_id BIGINT UNSIGNED PRIMARY KEY
    )'
);
// step 3: load the data directly from file into the database
// (much faster than programmatic inserts)
$path = getcwd() . DIRECTORY_SEPARATOR . $filename;
$mysqli->query(
    "LOAD DATA INFILE '" . addslashes($path) . "' 
      INTO TABLE deleted_user_ids
      LINES TERMINATED BY '\n'
      IGNORE 1 LINES;"
);
// step 4: delete the rows belonging to deleted users from the other tables
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