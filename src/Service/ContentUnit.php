<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/2/19
 * Time: 4:48 PM
 */

namespace App\Service;

use App\Entity\Account;
use App\Entity\BoostedContentUnit;
use App\Entity\File;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

class ContentUnit
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var string
     */
    private $channelAddress;

    /**
     * @var BlockChain
     */
    private $blockChain;

    /**
     * @var Custom
     */
    private $custom;

    public function __construct(EntityManagerInterface $em, string $channelAddress, BlockChain $blockChain, Custom $custom)
    {
        $this->em = $em;
        $this->channelAddress = $channelAddress;
        $this->blockChain = $blockChain;
        $this->custom = $custom;
    }

    /**
     * @param $contentUnits
     * @param null $boosted
     * @return mixed
     * @throws \Exception
     */
    public function prepare($contentUnits, $boosted = null)
    {
        /**
         * @var \App\Entity\ContentUnit $contentUnit
         */
        foreach ($contentUnits as $contentUnit) {
            /**
             * @var Transaction $transaction
             */
            $transaction = $contentUnit->getTransaction();
            $contentUnit->setPublished($transaction->getTimeSigned());

            if ($contentUnit->getCover()) {
                /**
                 * @var File $coverFile
                 */
                $coverFile = $contentUnit->getCover();

                /**
                 * @var Account[] $fileStorages
                 */
                $fileStorages = $this->custom->getFileStoragesWithPublicAccess($coverFile);
                if (count($fileStorages)) {
                    $randomStorage = rand(0, count($fileStorages) - 1);
                    $storageUrl = $fileStorages[$randomStorage]->getUrl();

                    $coverFile->setUrl($storageUrl . '/storage?file=' . $coverFile->getUri() . '&channel_address=' . $this->channelAddress);
                } elseif ($contentUnit->getContent()) {
                    /**
                     * @var \App\Entity\Content $content
                     */
                    $content = $contentUnit->getContent();

                    /**
                     * @var Account $channel
                     */
                    $channel = $content->getChannel();

                    $storageUrl = $channel->getUrl();

                    $coverFile->setUrl($storageUrl . '/storage?file=' . $coverFile->getUri() . '&channel_address=' . $this->channelAddress);
                }
            }

            if ($boosted === null) {
                $isBoosted = $this->em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);
                $contentUnit->setBoosted($isBoosted);
            } else {
                $contentUnit->setBoosted($boosted);
            }
        }

        return $contentUnits;
    }
}