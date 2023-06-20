<?php

/** Accesses the ranks table */
class UserRanksDao
{
    private $mysqli;

    public function __construct($mysqli, string $table = "user_ranks")
    {
        $this->table = $table;
        $this->mysqli = $mysqli;
        $this->createTable();
    }

    function createTable()
    {
        $this->mysqli->query(
            'CREATE TABLE IF NOT EXISTS '.$this->table.'(
              user_id BIGINT UNSIGNED,
              country_code VARCHAR(6) DEFAULT "",
              rank INT,
              changes_count INT,
              CONSTRAINT user_pkey PRIMARY KEY (user_id, country_code)
            );'
        );
    }

    /** Returns the global rank of the given user */
    public function getRank(int $user_id): ?int
    {
        $stmt = $this->mysqli->prepare(
            'SELECT rank FROM '.$this->table.' WHERE user_id = ? AND country_code = ""'
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $r;
    }

    /** Returns associative array of country iso code -> rank */
    public function getCountryRanks(int $user_id): array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT country_code, rank
              FROM '.$this->table.'
              WHERE user_id = ? AND country_code != ""'
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $r = array();
        while ($row = $result->fetch_row()) {
            if (!$row[0]) continue;
            $r[$row[0]] = intval($row[1]);
        }
        $stmt->close();
        return $r;
    }
}