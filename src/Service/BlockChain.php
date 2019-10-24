<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/24/18
 * Time: 2:01 PM
 */

namespace App\Service;

use App\Command\StateSyncCommand;
use PubliqAPI\Base\PublicAddressType;
use PubliqAPI\Base\Rtt;
use PubliqAPI\Model\LoggedTransactionsRequest;
use PubliqAPI\Model\PublicAddressesRequest;
use PubliqAPI\Model\Served;
use PubliqAPI\Model\StorageFileDetails;

class BlockChain
{
    private $stateEndpoint;
    private $channelEndpoint;

    function __construct($stateEndpoint, $channelEndpoint)
    {
        $this->stateEndpoint = $stateEndpoint;
        $this->channelEndpoint = $channelEndpoint;
    }

    /**
     * @param $url
     * @param $header
     * @param string $dataString
     * @param string $method
     * @return array
     */
    public function callJsonRPC($url, $header, $dataString = null, $method = 'POST')
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
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
     * @param int $id
     * @return int
     * @throws \Exception
     */
    public function getLoggedTransactions($id)
    {
        $request = new LoggedTransactionsRequest();
        $request->setStartIndex($id);
        $request->setMaxCount(StateSyncCommand::ACTION_COUNT);

        $data = $request->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->stateEndpoint, $header, $data);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with getting LoggedTransactions');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function getPublicAddresses()
    {
        $request = new PublicAddressesRequest();
        $request->setAddressType(PublicAddressType::rpc);

        $data = $request->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->stateEndpoint, $header, $data);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with getting PublicAddresses');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param String $fileUri
     * @param $contentUnitUri
     * @param $peerAddress
     * @return bool|string
     * @throws \Exception
     */
    public function servedFile(string $fileUri, $contentUnitUri, $peerAddress)
    {
        $served = New Served();
        $served->setContentUnitUri($contentUnitUri);
        $served->setPeerAddress($peerAddress);
        $served->setFileUri($fileUri);

        $data = $served->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->channelEndpoint . '/api', $header, $data);
        $headerStatusCode = $body['status_code'];

        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with file serving');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param string $fileUri
     * @param string|null $storageUrl
     * @return bool|string
     * @throws \Exception
     */
    public function getFileDetails(string $fileUri, string $storageUrl)
    {
        $storageFileDetails = New StorageFileDetails();
        $storageFileDetails->setUri($fileUri);

        $data = $storageFileDetails->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($storageUrl . '/api', $header, $data);
        $headerStatusCode = $body['status_code'];

        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with getting file details: ' . $storageUrl);
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }
}