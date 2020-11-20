<?php

require_once 'ChangesetElementIdsParser.class.php';

/** Fetches the created, modified and deleted element ids for a given changeset from the OSM API 0.6. 
 *
 *  See https://wiki.openstreetmap.org/wiki/API_v0.6#Download:_GET_.2Fapi.2F0.6.2Fchangeset.2F.23id.2Fdownload
 */
class ChangesetElementIdsFetcher
{
    private $user;
    private $pass;
    private $parser;

    public function __construct(string $user = null, string $pass = null)
    {
        $this->user = $user;
        $this->pass = $pass;
        $this->parser = new ChangesetModifiedElementsParser();
    }

    public function fetch(int $changeset_id): ChangesetElementIds
    {
        $url = 'https://api.openstreetmap.org/api/0.6/changeset/'.$changeset_id.'/download';
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
        curl_close($curl);
        return $response;
    }
}