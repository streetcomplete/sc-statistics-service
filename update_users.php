#!/usr/bin/env php
<?php
/** Script for updating the statistics for all the given user ids . Can only be called from 
  * cli. */

require_once 'config.php';
require_once 'classes/ChangesetsWalker.class.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// all calculations must be done in UTC!
date_default_timezone_set('UTC');

$user_ids_str = $argv[1];
if (!isset($user_ids_str)) {
    http_response_code(400);
    exit(1);
}
$user_ids = explode(",", $user_ids_str);

$mysqli = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_NAME);
$mysqli->query('SET SESSION time_zone="+0:00"');
$changesets_walker = new ChangesetsWalker($mysqli, Config::DB_NAME, Config::OSM_OAUTH_TOKEN);
echo 'analyzing...' . PHP_EOL;
foreach ($user_ids as $user_id) {
    echo $user_id . PHP_EOL;
    $changesets_walker->analyzeUser($user_id);
}
echo 'done.' . PHP_EOL;
$mysqli->close();

http_response_code(200);
