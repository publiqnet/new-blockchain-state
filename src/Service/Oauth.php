<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 3/14/19
 * Time: 4:38 PM
 */

namespace App\Service;

class Oauth
{
    private $oauthEndpoint;

    function __construct($oauthEndpoint)
    {
        $this->oauthEndpoint = $oauthEndpoint;
    }

    /**
     * @param $url
     * @param $header
     * @return array
     */
    public function callJsonRPC($url, $header)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 100);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        curl_close($ch);

        $retArr = ['status_code' => $headerStatusCode, 'data' => $body];

        return $retArr;
    }

    /**
     * @param string $token
     * @return array
     * @throws \Exception
     */
    public function authenticateUserByToken(string $token)
    {
        $header = ['Content-Type:application/json', 'X-API-TOKEN: ' . $token];

        $body = $this->callJsonRPC($this->oauthEndpoint . '/api/user', $header);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check response
        if ($headerStatusCode == 200) {
            return ['status' => 200, 'data' => $data];
        }

        if ($headerStatusCode == 401 || $headerStatusCode == 404) {
            return ['status' => 404, 'msg' => 'Invalid token'];
        }

        throw new \Exception('Issue with token authentication');
    }
}