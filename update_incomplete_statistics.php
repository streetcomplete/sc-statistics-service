#!/usr/bin/env php
<?php
/** Checks which analyzations of statistics for users are incomplete and completes them.
 *  
 *  Can only be called from cli. Could run every minute or so because if nothing is to do,
 *  it only results in one simple SELECT query.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once 'config.php';
require_once 'classes/ChangesetsWalker.class.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// all calculations must be done in UTC!
date_default_timezone_set('UTC');

$mysqli = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_NAME);
$mysqli->query('SET SESSION time_zone="+0:00"');
$changesets_walker = new ChangesetsWalker($mysqli, Config::DB_NAME, Config::OSM_OAUTH_TOKEN);
// will only finish analyzations that have been untouched for 30 seconds or more
$changesets_walker->analyzeUnfinished(time() - 30, Config::MAX_CRON_CHANGESET_ANALYZING_IN_SECONDS);
$mysqli->close();

http_response_code(200);