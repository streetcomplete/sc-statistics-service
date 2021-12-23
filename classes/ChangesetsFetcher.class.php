<?php

require_once 'ChangesetsParser.class.php';

/** Fetches OSM changesets from the OSM API 0.6 
 *
 *  See https://wiki.openstreetmap.org/wiki/API_v0.6#Query:_GET_.2Fapi.2F0.6.2Fchangesets  */
class ChangesetsFetcher
{
    const OSM_CHANGESETS_API = 'https://api.openstreetmap.org/api/0.6/changesets';
    
    private $user;
    private $pass;
    private $parser;

    public function __construct(string $user = null, string $pass = null)
    {
        $this->user = $user;
        $this->pass = $pass;
        $this->parser = new ChangesetsParser();
    }
    
    public function fetchForUser(int $user_id, int $closed_after, int $created_before = null): ?array
    {
        if (!$created_before) {
            $time = date('c', $closed_after);
        } else {
            $time = date('c', $closed_after) . ',' . date('c', $created_before);
        }
        $url = self::OSM_CHANGESETS_API . '?user=' . $user_id . '&time=' . $time;
        $response = $this->fetchUrl($url, $this->user, $this->pass);
        if ($response->code == 404) {
            return null;
        }
        else if ($response->code != 200) {
            throw new Exception('OSM API returned error code ' . $response->code);
        }
        return $this->parser->parse($response->body);
    }

    public function fetchByIds(array $changeset_ids): ?array
    {
        $url = self::OSM_CHANGESETS_API . '?changesets=' . implode(",", $changeset_ids);
        $response = $this->fetchUrl($url, $this->user, $this->pass);
        if ($response->code == 404) {
            return null;
        }
        else if ($response->code != 200) {
            throw new Exception('OSM API returned error code ' . $response->code);
        }
        return $this->parser->parse($response->body);
    }

    private function fetchUrl(string $url, string $user = null, string $pass = null)
    {
        $response = new stdClass();
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'StreetComplete Statistics Analyzer'); 
        if ($user !== null and $pass !== null) {
            curl_setopt($curl, CURLOPT_USERPWD, $user . ":" . $pass);
        }
        $response->body = curl_exec($curl);
        $response->code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if($response->code == 0) {
	        throw new Exception('query failed without receiving response, function returned code ' . $response->code . ' curl reports ' . curl_error($curl));
        }
        curl_close($curl);
        return $response;
    }
}