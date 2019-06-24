<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/24/18
 * Time: 2:01 PM
 */

namespace App\Service;

use App\Command\StateSyncCommand;
use PubliqAPI\Base\Rtt;
use PubliqAPI\Model\Authority;
use PubliqAPI\Model\Broadcast;
use PubliqAPI\Model\Coin;
use PubliqAPI\Model\Content;
use PubliqAPI\Model\LoggedTransactionsRequest;
use PubliqAPI\Model\Served;
use PubliqAPI\Model\Signature;
use PubliqAPI\Model\SignedTransaction;
use PubliqAPI\Model\StorageFileDetails;
use PubliqAPI\Model\Transaction;
use PubliqAPI\Model\TransactionBroadcastRequest;

class BlockChain
{
    private $stateEndpoint;
    private $storageEndpointUpload;
    private $storageEndpointGet;
    private $storageEndpointApi;

    function __construct($stateEndpoint, $storageEndpointUpload, $storageEndpointGet, $storageEndpointApi)
    {
        $this->stateEndpoint = $stateEndpoint;
        $this->storageEndpointUpload = $storageEndpointUpload;
        $this->storageEndpointGet = $storageEndpointGet;
        $this->storageEndpointApi = $storageEndpointApi;
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
            throw new \Exception('Issue with getting blocks');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param $fileData
     * @param $fileMimeType
     * @return bool|string
     * @throws \Exception
     */
    public function uploadFile($fileData, $fileMimeType)
    {
        $header = ['Content-Type: ' . $fileMimeType, 'Content-Length: ' . strlen($fileData)];

        $body = $this->callJsonRPC($this->storageEndpointUpload, $header, $fileData);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with uploading file');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param $publicKey
     * @param $signedString
     * @param $action
     * @param $creationTime
     * @param $expiryTime
     * @return mixed
     * @throws \Exception
     */
    public function verifySignature($publicKey, $signedString, $action, $creationTime, $expiryTime)
    {
        $coin = new Coin();
        $coin->setFraction(0);
        $coin->setWhole(0);

        $transaction = new Transaction();
        $transaction->setCreation($creationTime);
        $transaction->setExpiry($expiryTime);
        $transaction->setAction($action);
        $transaction->setFee($coin);

        $request = new Signature();
        $request->setPublicKey($publicKey);
        $request->setSignature($signedString);
        $request->setPackage($transaction);

        $data = $request->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->stateEndpoint, $header, $data);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with signature verification');
        }

        $validateRes = Rtt::validate($body['data']);

        return ['signatureResult' => $validateRes, 'transaction' => $transaction];
    }

    /**
     * @param Transaction $transaction
     * @param $address
     * @param $signature
     * @return bool|string
     * @throws \Exception
     */
    public function broadcast(Transaction $transaction, $address, $signature)
    {
        $authority = new Authority();
        $authority->setAddress($address);
        $authority->setSignature($signature);

        $signedTransaction = new SignedTransaction();
        $signedTransaction->setTransactionDetails($transaction);
        $signedTransaction->addAuthorizations($authority);

        $broadcast = new Broadcast();
        $broadcast->setPackage($signedTransaction);
        $broadcast->setEchoes(2);

        $data = $broadcast->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->stateEndpoint, $header, $data);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with broadcasting');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param Content $content
     * @param string $channelPrivateKey
     * @return bool|string
     * @throws \Exception
     */
    public function signContent(Content $content, string $channelPrivateKey)
    {
        $coin = new Coin();
        $coin->setFraction(0);
        $coin->setWhole(0);

        $transaction = new Transaction();
        $transaction->setAction($content);
        $transaction->setFee($coin);
        $transaction->setCreation(time());
        $transaction->setExpiry(time() + 43200);

        $transactionBroadcastRequest = new TransactionBroadcastRequest();
        $transactionBroadcastRequest->setTransactionDetails($transaction);
        $transactionBroadcastRequest->setPrivateKey($channelPrivateKey);

        $data = $transactionBroadcastRequest->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->stateEndpoint, $header, $data);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with content publishing');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param string $uri
     * @return bool|string
     * @throws \Exception
     */
    public function getContentUnitData(string $uri)
    {
        $header = ['Content-Type:application/json'];

        $body = $this->callJsonRPC($this->storageEndpointGet . '?file=' . $uri, $header, null, 'GET');

        $headerStatusCode = $body['status_code'];

        //  check data
        if ($headerStatusCode == 200) {
            return $body['data'];
        }

        if ($headerStatusCode == 404) {
            return null;
        }

        throw new \Exception('Issue with getting content unit data');
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

        $body = $this->callJsonRPC($this->storageEndpointApi, $header, $data);
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
    public function getFileDetails(string $fileUri, string $storageUrl = null)
    {
        $storageFileDetails = New StorageFileDetails();
        $storageFileDetails->setUri($fileUri);

        $data = $storageFileDetails->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        if ($storageUrl) {
            $body = $this->callJsonRPC($storageUrl . '/api', $header, $data);
        } else {
            $body = $this->callJsonRPC($this->storageEndpointApi, $header, $data);
        }
        $headerStatusCode = $body['status_code'];

        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with getting file details');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }
}