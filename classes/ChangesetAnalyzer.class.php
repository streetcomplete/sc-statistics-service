<?php

require_once 'ChangesetModifiedElementsFetcher.class.php';
require_once 'ReverseCountryGeocoder.class.php';

/** Does further analysis on a changeset and adds this data to it. Currently 
 *  - ISO 3166-1 alpha-2 / ISO 3166-2 country codes of the center of the changeset
 *  - the solved quest count
 */
class ChangesetAnalyzer {

    private $changesetModifiedElementsFetcher;
    private $geocoder;
    
    public function __construct(mysqli $mysqli, string $db_name, string $osm_user = null, string $osm_pass = null)
    {
        $this->mysqli = $mysqli;
        $this->changesetModifiedElementsFetcher = new ChangesetModifiedElementsFetcher($osm_user, $osm_pass);
        $this->geocoder = new ReverseCountryGeocoder($mysqli, $db_name, 'data'.DIRECTORY_SEPARATOR.'boundaries.json');
    }

    public function analyze(Changeset $changeset)
    {
        $changeset->solved_quest_count = $this->getSolvedQuestsCountOfChangeset($changeset->id);

        $center_lat = ($changeset->min_lat + $changeset->max_lat) / 2;
        $center_lon;
        // for the jokers that cross the 180th meridian within one changeset
        if (sign($changeset->max_lon) != sign($changeset->min_lon)) {
            $center_lon = $changeset->max_lon;
        } else {
            $center_lon = ($changeset->min_lon + $changeset->max_lon) / 2;
        }
        $changeset->country_code = $this->getCountryCode($center_lon, $center_lat);
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
    
    private function getCountryCode($longitude, $latitude): ?string
    {
        $codes = $this->geocoder->getIsoCodes($longitude, $latitude);
        return empty($codes) ? null : $codes[0];
    }
}


function sign($val)
{
    return $val != 0 ? $val/abs($val) : 0;
}