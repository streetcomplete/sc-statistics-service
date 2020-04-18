<?php

require_once 'ChangesetsWalkerStateDao.class.php';
require_once 'ChangesetsDao.class.php';
require_once 'ChangesetsFetcher.class.php';
require_once 'ChangesetModifiedElementsFetcher.class.php';

/** Walks through a user's changeset history to find the relevant information for StreetComplete
 *  statistics.
 *
 *  The OSM API for querying a user's changeset history only returns up to 100 results but at the 
 *  same time does not support real pagination. One can only limit the results by a date-time-range.
 *
 *  So, the algorithm to get all changesets of a user is to walk from the most current changeset to 
 *  the first changeset. However, when we finally reached the first changeset, new changesets may 
 *  have been added in the meantime. So we need to again walk to the changeset that was the newest
 *  on the last run, etc.
 *
 *  As the walking through the history may take a long time, it is taken into consideration that
 *  the process is cancelled at any time because it takes too long. This is why after each chunk of
 *  100 changesets, the current state and the changesets are persisted before continuing.
 *  */
class ChangesetsWalker
{
    private $changesetsFetcher;
    private $changesetModifiedElementsFetcher;
    private $changesetsDao;
    private $changesetsWalkerStateDao;
    
    public function __construct(mysqli $mysqli, string $osm_user = null, string $osm_pass = null)
    {
        $this->mysqli = $mysqli;
        $this->changesetsFetcher = new ChangesetsFetcher($osm_user, $osm_pass);
        $this->changesetModifiedElementsFetcher = new ChangesetModifiedElementsFetcher($osm_user, $osm_pass);
        $this->changesetsDao = new ChangesetsDao($mysqli);
        $this->changesetsWalkerStateDao = new ChangesetsWalkerStateDao($mysqli);
    }
    
    public function analyzeUser(int $user_id, int $timeout_in_seconds = null)
    {
        $start_time = time();
        do {
            if (isset($timeout_in_seconds) && time() - $start_time >= $timeout_in_seconds) break;
            
            $range = $this->changesetsWalkerStateDao->getCurrentAnalyzingRange($user_id);
            $closed_after = $range[0];
            $created_before = $range[1];
            
            $changesets = $this->changesetsFetcher->fetchForUser($user_id, $closed_after, $created_before);
            // OSM API doesn't know this user: cancel
            if (!isset($changesets)) {
                return;
            }
            // break if no changesets have been found
            if (count($changesets) == 0) break;
            
            $sc_changesets = array(); // only SC changesets that are relevant for StreetComplete stats
            $oldest_created_date = NULL;
            $newest_closed_date = NULL;
            foreach ($changesets as $changeset) {
                if (!isset($oldest_created_date)) {
                    $oldest_created_date = $changeset->created_at;
                } else {
                    $oldest_created_date = min($oldest_created_date, $changeset->created_at);
                }
                if (!isset($newest_closed_date)) {
                    $newest_closed_date = $changeset->closed_at;
                } else {
                    $newest_closed_date = max($newest_closed_date, $changeset->closed_at);
                }
                if (isset($changeset->quest_type)) {
                    array_push($sc_changesets, $changeset);
                }
            }
            
            if (!empty($sc_changesets)) {
                $this->updateChangesetsWithRealNumberOfSolvedQuests($sc_changesets);
                $this->changesetsDao->putChangesets($sc_changesets);
            }
            
            /* break condition: The closed date of the newest changeset in the fetch result is 
               equal to the date before which the user's changeset history has been analyzed 
               already and this is the first call to fetch the changesets for a range */
            if ($newest_closed_date == $closed_after && !isset($created_before)) break;
            
            // OSM API always returns 100 unless there are no more -> we reached the end
            $range_is_done = count($changesets) < 100;
            $this->changesetsWalkerStateDao->updateAnalyzingRange(
                $user_id, $newest_closed_date, $oldest_created_date, $range_is_done
            );
        } while(true);
        
       $this->recheckOpenChangesets($user_id);
    }

    /** Analyze the changeset history of users whose analyzing process is not finished yet */
    public function analyzeUnfinished(int $updated_before) {
        while($user_id = $this->changesetsWalkerStateDao->getUserIdWithUnfinishedAnalyzingRange($updated_before)) {
            $this->analyzeUser($user_id);
        }
    }

    private function updateChangesetsWithRealNumberOfSolvedQuests(array $changesets)
    {
        foreach ($changesets as $changeset) {
            $number_of_solved_quests = $this->getSolvedQuestsCountOfChangeset($changeset->id);
            if (isset($number_of_solved_quests)) {
                if ($changeset->solved_quest_count != $number_of_solved_quests) {
                    $changeset->solved_quest_count = $number_of_solved_quests;
                }
            }
        }
    }

    private function getSolvedQuestsCountOfChangeset(int $changeset_id): ?int
    {
            /* the changes_count attribute of the changeset is not always equal to the actual
             * solved quest count. It deviates for the following cases:
             * 1. changes were reverted. Each revert counts as an additional change towards changes_count
             * 2. a way was split. A split may create new nodes at the split positions and creates new
             *    way(s).
             */
            // So, firstly, we only count MODIFIED elements.
            $elements = $this->changesetModifiedElementsFetcher->fetch($changeset_id);
            if (!isset($elements)) return null;
            // Secondly, we look for elements that have been changed multiple times in the changeset.
            return
                $this->getSolvedQuestsCount($elements["nodes"]) +
                $this->getSolvedQuestsCount($elements["ways"]) +
                $this->getSolvedQuestsCount($elements["relations"]);
    }

    /** returns number of changed element ids, subtracting any reverts */
    private function getSolvedQuestsCount(array $element_ids_in_changeset): int
    {
        $counts = array_count_values($element_ids_in_changeset);
        $result = 0;
        foreach ($counts as $id => $count) {
            /* if an element id has been changed several times in the changeset, we can assume
               that every other time was a revert. So changed 1 time: +1 change. Changed 
               2 times: +1 change and then revert. Changed 3 times: +1 change, then revert,
               then change again. In other words, if the id occurs an odd number of times 
               in the changeset, it counts as +1, otherwise +0 */
            $is_odd = $count % 2 != 0;
            if ($is_odd) $result++;
        }
        return $result;
    }

    private function recheckOpenChangesets(int $user_id) {
        $open_changesets_ids = $this->changesetsDao->getOpenChangesetIds($user_id);
        if (!empty($open_changesets_ids)) {
            $previously_open_changesets = $this->changesetsFetcher->fetchByIds($open_changesets_ids);
            if (!empty($previously_open_changesets)) {
                $this->changesetsDao->putChangesets($previously_open_changesets);
            }
        }
    }
}