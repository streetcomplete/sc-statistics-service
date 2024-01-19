<?php

require_once 'ReverseCountryGeocoder.class.php';

/** Does further analysis on a changeset and adds this data to it. Currently only
 *  the ISO 3166-1 alpha-2 / ISO 3166-2 country codes of the center of the changeset
 */
class ChangesetAnalyzer {

    private $geocoder;
    
    public function __construct(mysqli $mysqli, string $db_name, string $osm_auth_token = null)
    {
        $this->geocoder = new ReverseCountryGeocoder($mysqli, $db_name, 'data'.DIRECTORY_SEPARATOR.'boundaries.json');
    }

    public function analyze(Changeset $changeset)
    {
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