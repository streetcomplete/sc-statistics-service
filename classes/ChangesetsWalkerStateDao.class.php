<?php

/** Persists the current state of the changeset analysis for each user. 
 *
 *  For each run, we walk from the most current changeset to whatever date is in the column
 *  'finished_analyzing_before_date_closed', which defines up to which date the user's changeset
 *  history has been successfully analyzed. At the start, it is set to the "date of birth" of 
 *  StreetComplete, the 20th February of 2017.
 *  When this first walk completed, the 'finished_analyzing_before_date_closed' column is set to
 *  the closed date of the most current changeset, the 'newest_date_closed' column.
 * 
 *  The 'oldest_date_created' is the date that walks towards 
 *  'finished_analyzing_before_date_closed' in 100-changesets-steps.
 *  
 *  */
class ChangesetsWalkerStateDao
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
        $this->createTable();
    }
    
    private function createTable()
    {
        $this->mysqli->query(
            "CREATE TABLE IF NOT EXISTS changesets_walker_state(
                user_id BIGINT UNSIGNED PRIMARY KEY,
                finished_analyzing_before_date_closed DATETIME DEFAULT '2017-02-20 00:00:00',
                newest_date_closed DATETIME,
                oldest_date_created DATETIME,
                last_update DATETIME NOT NULL
            )"
        );
    }
    
    public function update(int $user_id, int $newest_date_closed = null, int $oldest_date_created = null, bool $range_is_done = false)
    {
        $this->mysqli->begin_transaction();
        
        if (isset($oldest_date_created)) {
            $this->updateOldestDateCreated($user_id, $oldest_date_created);
        }
        if (isset($newest_date_closed)) {
            $this->updateNewestDateClosed($user_id, $newest_date_closed);
        }
        if ($range_is_done) {
            $this->setAnalyzingRangeDone($user_id);
        }
        $this->updateLastUpdateDate($user_id);
        $this->mysqli->commit();
    }
    
    private function updateNewestDateClosed(int $user_id, int $newest_date_closed)
    {
        $stmt = $this->mysqli->prepare(
            'INSERT INTO changesets_walker_state (user_id, newest_date_closed) VALUES (?,?)
             ON DUPLICATE KEY UPDATE 
             newest_date_closed = GREATEST(COALESCE(newest_date_closed, ?), ?)'
        );
        $min_date = '1000-01-01 00:00:00'; // MIN MySQL DATETIME
        $date = date('Y-m-d H:i:s', $newest_date_closed);
        $stmt->bind_param('isss', $user_id, $date, $min_date, $date);
        $stmt->execute();
        $stmt->close();
    }
    
    private function updateOldestDateCreated(int $user_id, int $oldest_date_created)
    {
        $stmt = $this->mysqli->prepare(
            'INSERT INTO changesets_walker_state (user_id, oldest_date_created) VALUES (?,?)
             ON DUPLICATE KEY UPDATE 
             oldest_date_created = LEAST(COALESCE(oldest_date_created, ?), ?)'
        );
        $max_date = '9999-12-31 23:59:59'; // MAX MySQL DATETIME
        $date = date('Y-m-d H:i:s', $oldest_date_created);
        $stmt->bind_param('isss', $user_id, $date, $max_date, $date);
        $stmt->execute();
        $stmt->close();
    }
    
    private function setAnalyzingRangeDone(int $user_id)
    {
        $stmt = $this->mysqli->prepare(
            'UPDATE changesets_walker_state
             SET 
              finished_analyzing_before_date_closed = newest_date_closed,
              newest_date_closed = DEFAULT, 
              oldest_date_created = DEFAULT
             WHERE user_id = ?'
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }

	private function updateLastUpdateDate(int $user_id)
	{
		// unfortunately it is not possible to create the column last_update in the table with
		// the modifier "DEFAULT UTC_TIMESTAMP ON UPDATE UTC_TIMESTAMP" (=auto-update timestamp
		// on every update). Se we need to do it manually.
		$stmt = $this->mysqli->prepare(
			'UPDATE changesets_walker_state
			 SET last_update = UTC_TIMESTAMP
			 WHERE user_id = ?');
		$stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
	}

    public function getCurrentAnalyzingRange(int $user_id): array
    {
        $stmt = $this->mysqli->prepare(
            'SELECT oldest_date_created, finished_analyzing_before_date_closed
              FROM changesets_walker_state 
              WHERE user_id = ?'
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        // no entry yet
        $r;
        if ($row) {
            $oldest_date_created = $row['oldest_date_created'];
            $r = array(
                strtotime($row['finished_analyzing_before_date_closed']),
                ($oldest_date_created) ? strtotime($oldest_date_created) : null
            );
        } else {
            $r = array(strtotime('2017-02-20'), null);
        }
        $stmt->close();
        return $r;
    }
    
    public function getUserIdWithUnfinishedAnalyzingRange(int $updated_before): ?int
    {
        $stmt = $this->mysqli->prepare(
        'SELECT user_id
          FROM changesets_walker_state
          WHERE newest_date_closed IS NOT NULL AND last_update < ?
          ORDER BY last_update ASC
          LIMIT 1'
        );
        $date = date('Y-m-d H:i:s', $updated_before);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        if ($row) {
            $r = $row[0];
        } else {
            $r = null;
        }
        $stmt->close();
        return $r;
    }
    
    public function getFinishedAnalyzingBeforeDateClosed(int $user_id): int
    {
        $stmt = $this->mysqli->prepare(
            'SELECT finished_analyzing_before_date_closed
              FROM changesets_walker_state 
              WHERE user_id = ?'
        );
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        if ($row) {
            $r = strtotime($row[0]);
        } else {
            $r = strtotime('2017-02-20');
        }
        
        $stmt->close();
        return $r;
    }
}