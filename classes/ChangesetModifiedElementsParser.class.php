<?php

/** Parses the content of OSM changesets (OsmChange format) and lists the ids of elements 
  * that have been changed in it. */
class ChangesetModifiedElementsParser {
    
    public function parse(string $xml): array
    {
        // disable weird XML features parsing posing a possible security vulnerability #2
        libxml_disable_entity_loader(true);
        $osmChangeXml = simplexml_load_string($xml);
        $nodes = array();
        $ways = array();
        $relations = array();
        // not <create> because these would be split-ways and a split-way should only count as 1 change
        foreach ($osmChangeXml->modify as $modifyXml) {
            foreach ($modifyXml->node as $nodeXml) {
                $nodes[] = intval((string) $nodeXml->attributes()['id']);
            }
            foreach ($modifyXml->way as $wayXml) {
                $ways[] = intval((string) $wayXml->attributes()['id']);
            }
            foreach ($modifyXml->relation as $relationXml) {
                $relations[] = intval((string) $relationXml->attributes()['id']);
            }
        }
        return array(
            "nodes" => $nodes,
            "ways" => $ways,
            "relations" => $relations
        );
    }
}