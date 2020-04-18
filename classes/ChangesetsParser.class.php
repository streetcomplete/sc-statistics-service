<?php

require_once 'Changeset.class.php';

/** Parses OSM changesets into an own data structure */
class ChangesetsParser {
    
    public function parse(string $xml): array
    {
        // disable weird XML features parsing posing a possible security vulnerability #2
        libxml_disable_entity_loader(true);
        $changesetsXml = simplexml_load_string($xml);
        $r = array();
        foreach ($changesetsXml->changeset as $changesetXml)
        {
            $changeset = $this->parseChangeset($changesetXml);
            array_push($r, $changeset);
        }
        return $r;
    }

    private function parseChangeset(SimpleXMLElement $changeset): Changeset
    {
        $r = new Changeset();
        $r->id = intval((string) $changeset->attributes()['id']);
        $r->user_id = intval((string) $changeset->attributes()['uid']);
        $r->changes_count = intval((string) $changeset->attributes()['changes_count']);
        $r->created_at = strtotime((string) $changeset->attributes()['created_at']);
        $closed_at = (string) $changeset->attributes()['closed_at'];
        if ($closed_at) $r->closed_at = strtotime($closed_at);
        $r->max_lat = doubleval((string) $changeset->attributes()['max_lat']);
        $r->min_lat = doubleval((string) $changeset->attributes()['min_lat']);
        $r->max_lon = doubleval((string) $changeset->attributes()['max_lon']);
        $r->min_lon = doubleval((string) $changeset->attributes()['max_lon']);
        $r->open = filter_var((string) $changeset->attributes()['open'], FILTER_VALIDATE_BOOLEAN);
        foreach ($changeset->tag as $tag)
        {
            if (((string) $tag['k']) == 'StreetComplete:quest_type') {
                $r->quest_type = (string) $tag['v'];
                break;
            }
        }
        return $r;
    }
}