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
use App\Entity\UserViewLog;
use App\Entity\ContentUnit;
use App\Entity\UserViewLogHistory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class Custom
{
    const FB_CLIENT_ID = '1949989915051606';
    const FB_CLIENT_SECRET = 'e9ea0e9284f61ac66d538ae56bbebab3';

    /**
     * @var EntityManager
     */
    private $em;

    private $socialAssetsPath;
    private $socialImagePath;
    private $channelStorageEndpoint;

    function __construct(EntityManagerInterface $em, $socialAssetsPath, $socialImagePath, $channelStorageEndpoint)
    {
        $this->em = $em;
        $this->socialAssetsPath = $socialAssetsPath;
        $this->socialImagePath = $socialImagePath;
        $this->channelStorageEndpoint = $channelStorageEndpoint;
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

    /**
     * @param Request $request
     * @param ContentUnit $contentUnit
     * @param $account
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function viewLog(Request $request, ContentUnit $contentUnit, $account)
    {
        //  generate fingerprint by request
        $userInfo = [];
        $userInfo['userAgent'] = $request->headers->get('User-Agent');
        $userInfo['acceptableContentTypes'] = $request->getAcceptableContentTypes();
        $userInfo['clientIp'] = $request->getClientIp();
        $userInfo['mimeType'] = $request->getMimeType('string');
        $userInfo['charset'] = $request->getCharsets();
        $userInfo['encodings'] = $request->getEncodings();
        $userInfo['userInfo'] = $request->getUserInfo();
        $userIdentifier = md5(serialize($userInfo));

        $date = new \DateTime();
        $timezone = new \DateTimeZone('UTC');
        $date->setTimezone($timezone);

        $viewLog = $this->em->getRepository(UserViewLog::class)->findOneBy(['userIdentifier' => $userIdentifier, 'contentUnit' => $contentUnit]);
        if (!$viewLog) {
            $viewLog = new UserViewLog();
            $viewLog->setContentUnit($contentUnit);
            $viewLog->setUserIdentifier($userIdentifier);
            $viewLog->setDatetime($date->getTimestamp());

            $addView = true;
        } else {
            if (($date->getTimestamp() - $viewLog->getDatetime()) > 3600) {
                $viewLog->setDatetime($date->getTimestamp());

                $addView = true;
            } else {
                $addView = false;
            }
        }

        if ($account) {
            $viewLog->setUser($account);
        }
        $this->em->persist($viewLog);

        //  insert data into history
        $viewLogHistory = new UserViewLogHistory();
        $viewLogHistory->setContentUnit($contentUnit);
        $viewLogHistory->setUserIdentifier($userIdentifier);
        $viewLogHistory->setIp($request->getClientIp());
        $viewLogHistory->setDatetime($date->getTimestamp());
        if ($account) {
            $viewLogHistory->setUser($account);
        }
        $this->em->persist($viewLogHistory);
        $this->em->flush();

        return $addView;
    }
}