<?php

/** Represents one Changeset of a user with only the information that is interesting for StreetComplete */
class Changeset
{
    public $id;
    public $user_id;
    public $changes_count;
    public $solved_quest_count; 
    public $created_at;
    public $closed_at;
    public $min_lat;
    public $max_lat;
    public $min_lon;
    public $max_lon;
    public $country_code;
    public $open;
    public $quest_type;
}