<?php
class Config
{
    const DB_HOST = "db";
    const DB_NAME = "statistics";
    const DB_USER = "statistics_user";
    const DB_PASS = "statistics_pw";

    const OSM_OAUTH_TOKEN = null;

    /* time the backend should scan through the users' changeset history via the OSM API until
     * cancelling it and just returning what it got so far when a user queries his statistics.
     * looking through 100 changesets takes about 0.5 - 1.0 seconds */
    const MAX_CHANGESET_ANALYZING_IN_SECONDS = 3;

    /* time that must pass before a repeated query of a user to get his statistics triggers 
     * the backend to continue scanning the users' changeset history via the OSM API. */
    const MIN_DELAY_BETWEEN_CHANGESET_ANALYZING_IN_SECONDS = 30;
    
    /* time the cronjob should spend on analyzing changesets at most (i.e. should be lower than
     * PHP timeout) */
    const MAX_CRON_CHANGESET_ANALYZING_IN_SECONDS = 300;
}
