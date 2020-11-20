<?php

/** Represents the ids only of a osmChange of one changeset */
class ChangesetElementIds
{
    public $creations;
    public $modifications;
    public $deletions;
}

class ElementIds
{
    public $nodes;
    public $ways;
    public $relations;
    
    function __construct() {
       $this->nodes = array();
       $this->ways = array();
       $this->relations = array();
    }
}
