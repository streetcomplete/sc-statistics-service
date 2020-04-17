<?php

require_once 'Changeset.class.php';

/** Persists the changesets for each user with the solved quest type counts and creation dates.
 *  */
class ChangesetsDao
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
        $this->createTable();
    }

    private function createTable()
    {
        /* 2 decimals in lat/lon is about 300-600m inaccuracy. This is more than enough to 
           calculate the country/state, even close to borders */
        $this->mysqli->query(
            'CREATE TABLE IF NOT EXISTS changesets(
                changeset_id BIGINT UNSIGNED PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                quest_type VARCHAR(256) NOT NULL,
                changes_count INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                closed_at DATETIME,
                center_lat DECIMAL(5,2),
                center_lon DECIMAL(5,2),
                open BOOLEAN NOT NULL
            )'
        );
    }

    public function putChangesets(array $changesets)
    {
        $this->mysqli->begin_transaction();
        foreach ($changesets as $changeset) {
            $this->putChangeset($changeset);
        }
        $this->mysqli->commit();
    }

    private function putChangeset(Changeset $changeset)
    {
        $stmt = $this->mysqli->prepare(
            'REPLACE INTO changesets(changeset_id, user_id, quest_type, changes_count, created_at, closed_at, center_lat, center_lon, open)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $created_at = date('Y-m-d H:i:s', $changeset->created_at);
        $closed_at = $changeset->closed_at ? date('Y-m-d H:i:s', $changeset->closed_at) : null;
        $center_lat = $changeset->center_lat ? round(100 * $changeset->center_lat)/100 : null;
        $center_lon = $changeset->center_lon ? round(100 * $changeset->center_lon)/100 : null;
        
        $stmt->bind_param('iisissssi', 
            $changeset->id,
            $changeset->user_id,
            $changeset->quest_type,
            $changeset->changes_count,
            $created_at,
            $closed_at,
            $center_lat,
            $center_lon,
            $changeset->open,
        );
        $stmt->execute();
        $stmt->close();
    }
    
    public function getOpenChangesetIds(int $user_id): array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT changeset_id FROM changesets WHERE user_id = ? AND open IS TRUE'
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $r = array();
        while ($row = $result->fetch_row()) {
            array_push($r, $row[0]);
        }
        $stmt->close();
        return $r;
    }
    
    /** Returns associative array of quest_type -> solved count */
    public function getQuestCounts(int $user_id): array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT quest_type, SUM(changes_count)
              FROM changesets
              WHERE user_id = ?
              GROUP BY quest_type'
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $r = array();
        while ($row = $result->fetch_row()) {
            $r[$row[0]] = intval($row[1]);
        }
        $stmt->close();
        return $r;
    }
    
    /** Returns the number of days the user created changesets */
    public function getDaysActive(int $user_id): int
    {
        $stmt = $this->mysqli->prepare(
            'SELECT COUNT(DISTINCT DATE(created_at))
              FROM changesets
              WHERE user_id = ?'
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $r;
    }
}