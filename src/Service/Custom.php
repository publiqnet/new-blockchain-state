<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/22/19
 * Time: 4:08 PM
 */

namespace App\Service;

use App\Entity\Block;
use App\Entity\File;
use App\Entity\Account;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class Custom
{
    /**
     * @var EntityManager
     */
    private $em;

    private $endpoint;
    private $dsEndpoint;
    private $oldBackendEndpoint;

    function __construct(EntityManagerInterface $em, $endpoint, $dsEndpoint, $oldBackendEndpoint)
    {
        $this->em = $em;
        $this->endpoint = $endpoint;
        $this->dsEndpoint = $dsEndpoint;
        $this->oldBackendEndpoint = $oldBackendEndpoint;
    }

    /**
     * @param File $file
     * @return array
     */
    public function getFileStoragesWithPublicAccess(File $file)
    {
        $fileStoragesWithPublicAccess = [];

        /**
         * @var Account[] $fileStorages
         */
        $fileStorages = $file->getStorages();

        if (count($fileStorages)) {
            foreach ($fileStorages as $fileStorage) {
                if ($fileStorage->getUrl()) {
                    $fileStoragesWithPublicAccess[] = $fileStorage;
                }
            }
        }

        return $fileStoragesWithPublicAccess;
    }

    /**
     * @param string $email
     * @return bool|string
     * @throws \Exception
     */
    public function getOldPublicKey($email)
    {
        $ch = curl_init($this->oldBackendEndpoint . '/' . $email);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type:application/json']
        );

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $data = json_decode($body, true);

        if ($headerStatusCode == 200) {
            return $data['publicKey'];
        }

        return false;
    }

    /**
     * @param string $from
     * @return bool|string
     * @throws \Exception
     */
    public function searchAuthors($from = '0.0.0')
    {
        $dataString = sprintf('{"id":1, "method":"call", "params":[0, "search_accounts", ["PBQ","","%s",1000]]}', $from);

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type:application/json', 'Content-Length: ' . strlen($dataString)]
        );

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $data = json_decode($body, true);

        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Connection failed: searchAuthors');
        }

        return $data['result'];
    }

    /**
     * @param $publicKey
     * @return bool|string
     * @throws \Exception
     * @return array
     */
    public function getAuthorArticles($publicKey)
    {
        $dataString = sprintf('{"id":1, "method":"call", "params":[0, "search_content", ["","",[],"","%s","","","-1"]]}', $publicKey);

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type:application/json', 'Content-Length: ' . strlen($dataString)]
        );

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $data = json_decode($body, true);

        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Connection failed: getAuthorArticles');
        }

        return $data['result'];
    }

    /**
     * @param $articleId
     * @return array|string
     * @throws \Exception
     * @return array
     */
    public function getArticle($articleId)
    {
        $ch = curl_init($this->dsEndpoint . '/' . $articleId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type:application/json']
        );

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $data = json_decode($body, true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Connection failed: getArticle');
        }

        return $data['content']['data'];
    }

    /**
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function getFee()
    {
        /**
         * @var Block $block
         */
        $block = $this->em->getRepository(Block::class)->getLastBlock();
        if ($block->getFeeWhole() === null) {
            $feeWhole = 0;
            $feeFraction = 0;

            /**
             * @var Transaction[] $transactions
             */
            $transactions = $block->getTransactions();
            if (count($transactions)) {
                foreach ($transactions as $transaction) {
                    $feeWhole += $transaction->getFeeWhole();
                    $feeFraction += $transaction->getFeeFraction();
                }

                if ($feeFraction > 99999999) {
                    while ($feeFraction > 99999999) {
                        $feeWhole++;
                        $feeFraction -= 100000000;
                    }
                }

                //  calculate average fee
                $fee = $feeWhole + $feeFraction / 100000000;
                $transactionsCount = count($transactions);
                $averageFee = $fee * 100000000 / $transactionsCount;

                $feeWhole = floor($averageFee / 100000000);
                $feeFraction = $averageFee % 100000000;
            }

            $block->setFeeWhole(intval($feeWhole));
            $block->setFeeFraction(intval($feeFraction));

            $this->em->persist($block);
            $this->em->flush();
        } else {
            $feeWhole = $block->getFeeWhole();
            $feeFraction = $block->getFeeFraction();
        }

        return [$feeWhole, $feeFraction];
    }
}