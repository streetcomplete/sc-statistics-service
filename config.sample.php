<?php
class Config
{
    const DB_HOST = "localhost";
    const DB_NAME = "statistics";
    const DB_USER = "statistics_user";
    const DB_PASS = "statistics_pw";

    const OSM_API_USER = null;
    const OSM_API_PASS = null;

    // looking through 100 changesets takes about 0.5 - 1.0 seconds
    const MAX_CHANGESET_ANALYZING_IN_SECONDS = 3;
}
