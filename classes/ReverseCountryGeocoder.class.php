<?php

/** Provides ISO country code(s) for given positions.
  * 
  * Uses spatial functions of MySQL to do the checks and populates the boundaries tables 
  * itself if it doesn't exist yet from a GEOJSON. The GEOJSON used was generated from 
  * this file https://josm.openstreetmap.de/export/HEAD/josm/trunk/data/boundaries.osm
  * via https://github.com/westnordost/countryboundaries
  * So this means, there are subdivisions available for US, CA, AU, CH and IN etc */
class ReverseCountryGeocoder
{
    private $mysqli;

    public function __construct(mysqli $mysqli, string $db_name, string $boundaries_file_path)
    {
        $this->mysqli = $mysqli;
        if (!$this->tableExists($db_name)) {
            $this->initTable($boundaries_file_path);
        }
    }

    /** For the given geo position, returns an array of countries/state in which the position 
      * is located, for example array("US-TX", "US"). */
    public function getIsoCodes($longitude, $latitude): array {
        $stmt = $this->mysqli->prepare(
        'SELECT id FROM boundaries
          WHERE ST_Contains(shape, ST_GeomFromText(?, 3857))
          ORDER BY area ASC'
        );
        $point = 'POINT(' . $longitude . ' ' . $latitude . ')';
        $stmt->bind_param('s', $point);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception($this->mysqli->error);
        }
        $r = array();
        while ($row = $result->fetch_row()) {
            $r[] = $row[0];
        }
        $stmt->close();
        return $r;
    }

    private function initTable(string $boundaries_file_path) {
        $this->mysqli->query(
          'CREATE TABLE boundaries (
            id VARCHAR(6) PRIMARY KEY NOT NULL,
            shape GEOMETRY NOT NULL,
            area DOUBLE NOT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1;');
        $this->mysqli->query('ALTER TABLE boundaries ADD SPATIAL INDEX(shape)');
        $geojson = json_decode(file_get_contents($boundaries_file_path), true);
        $features = $geojson["features"];
        $this->mysqli->begin_transaction();
        foreach($features as $feature) {
            try {
                $id = $feature["properties"]["id"];
                $geom = json_encode($feature["geometry"]);
                $stmt = $this->mysqli->prepare(
                  'INSERT INTO boundaries (id, shape, area) VALUES (?, ST_GeomFromGeoJSON(?), ST_AREA(ST_GeomFromGeoJSON(?)))'
                );
                $stmt->bind_param('sss', $id, $geom, $geom);
                $stmt->execute();
                $stmt->close();
            } catch (Exception $e) {
                  trigger_error("WARNING: skipping country $id due to error: " . $e->getMessage(), E_USER_WARNING);
            }
        }
        $this->mysqli->commit();
    }
    
    private function tableExists(string $db_name): bool
    {
        $stmt = $this->mysqli->prepare("
          SELECT *
           FROM information_schema.tables
           WHERE table_schema = ? AND table_name = 'boundaries'
           LIMIT 1"
        );
        $stmt->bind_param('s', $db_name);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        $stmt->close();
        return $row ? true : false;
    }
}
