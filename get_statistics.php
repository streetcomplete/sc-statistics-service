<?php
require_once 'config.php';
require_once 'classes/ChangesetsWalker.class.php';
require_once 'classes/ChangesetsDao.class.php';
require_once 'classes/ChangesetsWalkerStateDao.class.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// all calculations must be done in UTC!
date_default_timezone_set('UTC');

header('Content-Type: application/json');

// TODO only allow with user agent StreetComplete

if (!isset($_GET['user_id'])) {
    returnError(400, 'Missing user_id parameter');
}
$user_id = (int) $_GET['user_id'];
if (!is_int($user_id)) {
    returnError(400, 'user_id must be an integer');
}

try {

    $mysqli = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_NAME);
    $changesets_walker = new ChangesetsWalker($mysqli, Config::OSM_API_USER, Config::OSM_API_PASS);
    $changesets_walker->analyzeUser($user_id, Config::MAX_CHANGESET_ANALYZING_IN_SECONDS);

    $changesets_dao = new ChangesetsDao($mysqli);
    $changesets_walker_state_dao = new ChangesetsWalkerStateDao($mysqli);

    $solved_quest_types = $changesets_dao->getQuestCounts($user_id);
    $days_active = $changesets_dao->getDaysActive($user_id);
    $last_update = $changesets_walker_state_dao->getFinishedAnalyzingBeforeDateClosed($user_id);

    $mysqli->close();
} catch (Exception $e) {
    returnError(500, $e->getMessage());
}

http_response_code(200);
exit(json_encode(array(
    'questTypes' => $solved_quest_types,
    'daysActive' => $days_active,
    'lastUpdate' => date('c', $last_update)
)));

function returnError($code, $message)
{
    http_response_code($code);
    exit(json_encode(array('error' => $message)));
}
