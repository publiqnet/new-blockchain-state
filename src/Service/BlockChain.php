<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/24/18
 * Time: 2:01 PM
 */

namespace App\Service;

use App\Command\StateSyncCommand;
use App\Entity\Account;
use App\Entity\Draft;
use Doctrine\ORM\EntityManagerInterface;
use PubliqAPI\Base\PublicAddressType;
use PubliqAPI\Base\Rtt;
use PubliqAPI\Model\Authority;
use PubliqAPI\Model\Broadcast;
use PubliqAPI\Model\CancelSponsorContentUnit;
use PubliqAPI\Model\Coin;
use PubliqAPI\Model\Content;
use PubliqAPI\Model\File;
use PubliqAPI\Model\LoggedTransactionsRequest;
use PubliqAPI\Model\PublicAddressesRequest;
use PubliqAPI\Model\Served;
use PubliqAPI\Model\Signature;
use PubliqAPI\Model\SignedTransaction;
use PubliqAPI\Model\SponsorContentUnit;
use PubliqAPI\Model\StorageFileDetails;
use PubliqAPI\Model\Transaction;
use PubliqAPI\Model\TransactionBroadcastRequest;
use PubliqAPI\Model\TransactionDone;
use PubliqAPI\Model\UriError;

class BlockChain
{
    private $em;
    private $stateEndpoint;
    private $broadcastEndpoint;
    private $channelEndpoint;
    private $channelStorageEndpoint;
    private $detectLanguageEndpoint;
    private $detectKeywordsEndpoint;
    private $channelStorageOrderEndpoint;
    private $channelPrivateKey;
    private $channelAddress;
    private $customService;

    function __construct(EntityManagerInterface $em, $stateEndpoint, $broadcastEndpoint, $channelEndpoint, $channelStorageEndpoint, $detectLanguageEndpoint, $detectKeywordsEndpoint, $channelStorageOrderEndpoint, $channelPrivateKey, $channelAddress, Custom $customService)
    {
        $this->em = $em;
        $this->stateEndpoint = $stateEndpoint;
        $this->broadcastEndpoint = $broadcastEndpoint;
        $this->channelEndpoint = $channelEndpoint;
        $this->channelStorageEndpoint = $channelStorageEndpoint;
        $this->detectLanguageEndpoint = $detectLanguageEndpoint;
        $this->detectKeywordsEndpoint = $detectKeywordsEndpoint;
        $this->channelStorageOrderEndpoint = $channelStorageOrderEndpoint;
        $this->channelPrivateKey = $channelPrivateKey;
        $this->channelAddress = $channelAddress;
        $this->customService = $customService;
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
     * @param $text
     * @return array
     * @throws \Exception
     */
    public function detectContentLanguageKeywords($text)
    {
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($text)];

        $body = $this->callJsonRPC($this->detectLanguageEndpoint, $header, $text);
        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);
        if ($headerStatusCode != 200) {
            throw new \Exception('Issue with detecting language');
        }

        $body = $this->callJsonRPC($this->detectKeywordsEndpoint, $header, $text);
        $headerStatusCode = $body['status_code'];
        $keywords = json_decode($body['data'], true);
        if ($headerStatusCode != 200) {
            throw new \Exception('Issue with detecting keywords');
        }

        $keywordsArr = [];
        if ($keywords) {
            for ($i=0; $i<count($keywords) && $i<3; $i++) {
                if (trim($keywords[$i][0])) {
                    $keywordsArr[] = substr($keywords[$i][0], 0, 24);
                }
            }
        }

        return [$data, $keywordsArr];
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
     * @param $fileData
     * @param $fileMimeType
     * @return bool|string
     * @throws \Exception
     */
    public function uploadFile($fileData, $fileMimeType)
    {
        $header = ['Content-Type: ' . $fileMimeType, 'Content-Length: ' . strlen($fileData)];

        $body = $this->callJsonRPC($this->channelEndpoint . '/storage', $header, $fileData);

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
     * @param int $feeWhole
     * @param int $feeFraction
     * @return mixed
     * @throws \Exception
     */
    public function verifySignature($publicKey, $signedString, $action, $creationTime, $expiryTime, $feeWhole = 0, $feeFraction = 0)
    {
        $coin = new Coin();
        $coin->setFraction($feeFraction);
        $coin->setWhole($feeWhole);

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

        $body = $this->callJsonRPC($this->broadcastEndpoint, $header, $data);

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
     * @param string $uri
     * @return bool|string
     * @throws \Exception
     */
    public function signFile(string $uri)
    {
        list($feeWhole, $feeFraction) = $this->customService->getFee();

        $date = new \DateTime();
        $timeZone = new \DateTimeZone('UTC');
        $date->setTimezone($timeZone);

        $action = new File();
        $action->setUri($uri);
        $action->addAuthorAddresses($this->channelAddress);

        $coin = new Coin();
        $coin->setFraction($feeFraction);
        $coin->setWhole($feeWhole);

        $transaction = new Transaction();
        $transaction->setAction($action);
        $transaction->setFee($coin);
        $transaction->setCreation($date->getTimestamp());
        $transaction->setExpiry($date->getTimestamp() + 3600);

        $transactionBroadcastRequest = new TransactionBroadcastRequest();
        $transactionBroadcastRequest->setTransactionDetails($transaction);
        $transactionBroadcastRequest->setPrivateKey($this->channelPrivateKey);

        $data = $transactionBroadcastRequest->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->broadcastEndpoint, $header, $data);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with file publishing');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param string $uri
     * @param array $files
     * @param int $contentId
     * @return bool|string
     * @throws \Exception
     */
    public function signContentUnit(string $uri, array $files, int $contentId)
    {
        list($feeWhole, $feeFraction) = $this->customService->getFee();

        $date = new \DateTime();
        $timeZone = new \DateTimeZone('UTC');
        $date->setTimezone($timeZone);

        $action = new \PubliqAPI\Model\ContentUnit();
        $action->setUri($uri);
        $action->setChannelAddress($this->channelAddress);
        $action->addAuthorAddresses($this->channelAddress);
        $action->setContentId($contentId);
        foreach ($files as $fileUri) {
            $action->addFileUris($fileUri);
        }

        $coin = new Coin();
        $coin->setFraction($feeFraction);
        $coin->setWhole($feeWhole);

        $transaction = new Transaction();
        $transaction->setAction($action);
        $transaction->setFee($coin);
        $transaction->setCreation($date->getTimestamp());
        $transaction->setExpiry($date->getTimestamp() + 3600);

        $transactionBroadcastRequest = new TransactionBroadcastRequest();
        $transactionBroadcastRequest->setTransactionDetails($transaction);
        $transactionBroadcastRequest->setPrivateKey($this->channelPrivateKey);

        $data = $transactionBroadcastRequest->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->broadcastEndpoint, $header, $data);

        $headerStatusCode = $body['status_code'];
        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with content unit publishing');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param Content $content
     * @return bool|string
     * @throws \Exception
     */
    public function signContent(Content $content)
    {
        list($feeWhole, $feeFraction) = $this->customService->getFee();

        if (!$content->getChannelAddress()) {
            $content->setChannelAddress($this->channelAddress);
        }

        $date = new \DateTime();
        $timeZone = new \DateTimeZone('UTC');
        $date->setTimezone($timeZone);

        $coin = new Coin();
        $coin->setFraction($feeFraction);
        $coin->setWhole($feeWhole);

        $transaction = new Transaction();
        $transaction->setAction($content);
        $transaction->setFee($coin);
        $transaction->setCreation($date->getTimestamp());
        $transaction->setExpiry($date->getTimestamp() + 3600);

        $transactionBroadcastRequest = new TransactionBroadcastRequest();
        $transactionBroadcastRequest->setTransactionDetails($transaction);
        $transactionBroadcastRequest->setPrivateKey($this->channelPrivateKey);

        $data = $transactionBroadcastRequest->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        $body = $this->callJsonRPC($this->broadcastEndpoint, $header, $data);

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

        $body = $this->callJsonRPC($this->channelStorageEndpoint . '/storage?file=' . $uri, $header, null, 'GET');

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
     * @param string $storageAddress
     * @param string $fileUri
     * @param string $contentUnitUri
     * @param string $sessionId
     * @return bool|string
     * @throws \Exception
     */
    public function getStorageOrder(string $storageAddress, string $fileUri, string $contentUnitUri, string $sessionId)
    {
        $header = ['Content-Type:application/json'];

        $body = $this->callJsonRPC($this->channelStorageOrderEndpoint . '?private_key=' . $this->channelPrivateKey . '&storage_address=' . $storageAddress . '&file_uri=' . $fileUri . '&content_unit_uri=' . $contentUnitUri . '&session_id=' . $sessionId, $header, null, 'GET');

        $headerStatusCode = $body['status_code'];

        //  check data
        if ($headerStatusCode == 200) {
            return json_decode($body['data'], true);
        }

        if ($headerStatusCode == 404) {
            return null;
        }

        throw new \Exception('Issue with getting storage order token');
    }

    /**
     * @param string $storageOrderToken
     * @return bool|string
     * @throws \Exception
     */
    public function servedFile(string $storageOrderToken)
    {
        $served = New Served();

        $served->setStorageOrderToken($storageOrderToken);

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
    public function getFileDetails(string $fileUri, string $storageUrl = null)
    {
        $storageFileDetails = New StorageFileDetails();
        $storageFileDetails->setUri($fileUri);

        $data = $storageFileDetails->convertToJson();
        $header = ['Content-Type:application/json', 'Content-Length: ' . strlen($data)];

        if ($storageUrl) {
            $body = $this->callJsonRPC($storageUrl . '/api', $header, $data);
        } else {
            $body = $this->callJsonRPC($this->channelStorageEndpoint . '/api', $header, $data);
        }
        $headerStatusCode = $body['status_code'];

        $data = json_decode($body['data'], true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Issue with getting file details: ' . ($storageUrl ? $storageUrl : $this->channelStorageEndpoint));
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param string $signature
     * @param string $uri
     * @param string $sponsorAddress
     * @param $whole
     * @param $fraction
     * @param int $hours
     * @param int $startTimePoint
     * @param $creationTime
     * @param $expiryTime
     * @param int $feeWhole
     * @param int $feeFraction
     * @return bool|string
     * @throws \Exception
     */
    public function boostContent($signature, $uri, $sponsorAddress, $whole, $fraction, $hours, $startTimePoint, $creationTime, $expiryTime, $feeWhole = 0, $feeFraction = 0)
    {
        $coin = new Coin();
        $coin->setFraction($fraction);
        $coin->setWhole($whole);

        $sponsorContentUnit = new SponsorContentUnit();
        $sponsorContentUnit->setUri($uri);
        $sponsorContentUnit->setSponsorAddress($sponsorAddress);
        $sponsorContentUnit->setAmount($coin);
        $sponsorContentUnit->setHours($hours);
        $sponsorContentUnit->setStartTimePoint($startTimePoint);

        $coin = new Coin();
        $coin->setFraction($feeFraction);
        $coin->setWhole($feeWhole);

        $transaction = new Transaction();
        $transaction->setAction($sponsorContentUnit);
        $transaction->setFee($coin);
        $transaction->setCreation($creationTime);
        $transaction->setExpiry($expiryTime);

        $authority = new Authority();
        $authority->setAddress($sponsorAddress);
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
            throw new \Exception('Issue with boosting');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param string $signature
     * @param string $uri
     * @param string $sponsorAddress
     * @param $transactionHash
     * @param $creationTime
     * @param $expiryTime
     * @param int $feeWhole
     * @param int $feeFraction
     * @return bool|string
     * @throws \Exception
     */
    public function cancelBoostContent($signature, $uri, $sponsorAddress, $transactionHash, $creationTime, $expiryTime, $feeWhole = 0, $feeFraction = 0)
    {
        $cancelSponsorContentUnit = new CancelSponsorContentUnit();
        $cancelSponsorContentUnit->setUri($uri);
        $cancelSponsorContentUnit->setSponsorAddress($sponsorAddress);
        $cancelSponsorContentUnit->setTransactionHash($transactionHash);

        $coin = new Coin();
        $coin->setFraction($feeFraction);
        $coin->setWhole($feeWhole);

        $transaction = new Transaction();
        $transaction->setAction($cancelSponsorContentUnit);
        $transaction->setFee($coin);
        $transaction->setCreation($creationTime);
        $transaction->setExpiry($expiryTime);

        $authority = new Authority();
        $authority->setAddress($sponsorAddress);
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
            throw new \Exception('Issue with boosting cancellation');
        }

        $validateRes = Rtt::validate($body['data']);

        return $validateRes;
    }

    /**
     * @param \App\Entity\ContentUnit $contentUnitEntity
     * @param Draft|null $draft
     * @return array
     * @throws \Exception
     */
    public function publishContentUnit(\App\Entity\ContentUnit $contentUnitEntity, Draft $draft = null)
    {
        $timezone = new \DateTimeZone('UTC');
        $datetime = new \DateTime();
        $datetime->setTimezone($timezone);

        $content = new Content();
        $content->setContentId($contentUnitEntity->getContentId());
        $content->setChannelAddress($this->channelAddress);
        $content->addContentUnitUris($contentUnitEntity->getUri());

        $broadcastResult = $this->signContent($content);
        if ($broadcastResult instanceof TransactionDone) {
            //  add temp data
            $channelAccount = $this->em->getRepository(Account::class)->findOneBy(['publicKey' => $this->channelAddress]);

            $contentEntity = new \App\Entity\Content();
            $contentEntity->setContentId($contentUnitEntity->getContentId());
            $contentEntity->setChannel($channelAccount);
            $this->em->persist($contentEntity);

            $contentUnitEntity->setContent($contentEntity);
            $this->em->persist($contentUnitEntity);

            //  add temp transaction
            $transactionHash = $broadcastResult->getTransactionHash();
            $transactionEntity = new \App\Entity\Transaction();
            $transactionEntity->setTransactionHash($transactionHash);
            $transactionEntity->setContent($contentEntity);
            $transactionEntity->setTimeSigned($datetime->getTimestamp());
            $transactionEntity->setFeeWhole(0);
            $transactionEntity->setFeeFraction(0);
            $transactionEntity->setTransactionSize(0);
            $this->em->persist($transactionEntity);

            //  set draft as published
            if (!$draft) {
                $draft = $this->em->getRepository(Draft::class)->findOneBy(['uri' => $contentUnitEntity->getUri()]);
            }
            if ($draft) {
                $draft->setPublishDate($datetime->getTimestamp());
                $this->em->persist($draft);
            }

            $this->em->flush();

            return ['status' => true];
        } else {
            if ($broadcastResult instanceof UriError) {
                return ['status' => false, 'message' => 'Error type: ' . get_class($broadcastResult) . '; ' . $broadcastResult->getUriProblemType() . ' - ' . $broadcastResult->getUri()];
            } else {
                return ['status' => false, 'message' => 'Error type: ' . get_class($broadcastResult)];
            }
        }
    }
}