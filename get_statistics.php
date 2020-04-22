<?php
require_once 'config.php';
require_once 'classes/ChangesetsWalker.class.php';
require_once 'classes/ChangesetsDao.class.php';
require_once 'classes/ChangesetsWalkerStateDao.class.php';

//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// all calculations must be done in UTC!
date_default_timezone_set('UTC');

header('Content-Type: application/json');

/* when/if https://github.com/openstreetmap/openstreetmap-website/pull/2145 or similar is 
 * merged, we might add a proper check here, requiring each user to prove to this API that 
 * this user actually has an access token for the user it tries to get the statistics for. */
if (!startsWith($_SERVER['HTTP_USER_AGENT'], "StreetComplete")) {
    returnError(403, 'This is not a public API');
}

if (!isset($_GET['user_id'])) {
    returnError(400, 'Missing user_id parameter');
}
$user_id = (int) $_GET['user_id'];
if (!is_int($user_id)) {
    returnError(400, 'user_id must be an integer');
}

try {

    $mysqli = new mysqli(Config::DB_HOST, Config::DB_USER, Config::DB_PASS, Config::DB_NAME);
    $changesets_walker = new ChangesetsWalker($mysqli, Config::DB_NAME, Config::OSM_API_USER, Config::OSM_API_PASS);
    $last_update_date = $changesets_walker->getLastUpdateDate($user_id);
    if (time() >= $last_update_date + Config::MIN_DELAY_BETWEEN_CHANGESET_ANALYZING_IN_SECONDS) {
        $changesets_walker->analyzeUser($user_id, Config::MAX_CHANGESET_ANALYZING_IN_SECONDS);
    }

    $changesets_dao = new ChangesetsDao($mysqli);
    $changesets_walker_state_dao = new ChangesetsWalkerStateDao($mysqli);

    $solved_quest_types = $changesets_dao->getSolvedQuestCounts($user_id);
    $solved_by_country = $changesets_dao->getSolvedQuestsByCountry($user_id);
    $days_active = $changesets_dao->getDaysActive($user_id);
    $last_update = $changesets_walker_state_dao->getFinishedAnalyzingBeforeDateClosed($user_id);

    $mysqli->close();
} catch (Exception $e) {
    returnError(500, $e->getMessage());
}

http_response_code(200);
exit(json_encode(array(
    'questTypes' => $solved_quest_types,
    'countries' => $solved_by_country,
    'daysActive' => $days_active,
    'lastUpdate' => date('c', $last_update)
)));

function returnError($code, $message)
{
    http_response_code($code);
    exit(json_encode(array('error' => $message)));
}

function startsWith($haystack, $needle): bool
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}