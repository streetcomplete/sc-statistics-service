<?php

/** Represents one Changeset of a user with only the information that is interesting for StreetComplete */
class Changeset
{
    public $id;
    public $user_id;
    public $changes_count;
    public $created_at;
    public $closed_at;
    public $center_lat;
    public $center_lon;
    public $open;
    public $quest_type;
}