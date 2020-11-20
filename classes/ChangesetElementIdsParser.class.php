<?php

require_once 'ChangesetElementIds.class.php';

/** Parses the content of OSM changesets (OsmChange format) and lists the ids of elements 
  * that have been added, changed and deleted in it. */
class ChangesetElementIdsParser {
    
    public function parse(string $xml): ChangesetElementIds
    {
        // disable weird XML features parsing posing a possible security vulnerability #2
        libxml_disable_entity_loader(true);
        $osmChangeXml = simplexml_load_string($xml);
        
        $r = new ChangesetElementIds();
        $r->creations = $this->parseElements($osmChangeXml->create);
        $r->modifications = $this->parseElements($osmChangeXml->modify);
        $r->deletions = $this->parseElements($osmChangeXml->delete);
        
        return $r;
    }
    
    private function parseElements(array $elementsXml): ElementIds
    {
        $r = new ElementIds();
        foreach ($elementsXml as $nd) {
            foreach ($nd->node as $nodeXml) {
                $r->nodes[] = intval((string) $nodeXml->attributes()['id']);
            }
            foreach ($nd->way as $wayXml) {
                $r->ways[] = intval((string) $wayXml->attributes()['id']);
            }
            foreach ($nd->relation as $relationXml) {
                $r->relations[] = intval((string) $relationXml->attributes()['id']);
            }
        }
        return $r;
    }
}
