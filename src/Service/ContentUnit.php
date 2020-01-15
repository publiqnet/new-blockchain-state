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
use Symfony\Component\Serializer\SerializerInterface;

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
     * @var string
     */
    private $backendEndpoint;

    /**
     * @var BlockChain
     */
    private $blockChain;

    /**
     * @var Custom
     */
    private $custom;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(EntityManagerInterface $em, string $channelAddress, string $backendEndpoint, BlockChain $blockChain, Custom $custom, SerializerInterface $serializer)
    {
        $this->em = $em;
        $this->channelAddress = $channelAddress;
        $this->backendEndpoint = $backendEndpoint;
        $this->blockChain = $blockChain;
        $this->custom = $custom;
        $this->serializer = $serializer;
    }

    /**
     * @param $contentUnits
     * @param null $boosted
     * @param Account|null $author
     * @return mixed
     * @throws \Exception
     */
    public function prepare($contentUnits, $boosted = null, Account $author = null)
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

            /**
             * @var File $coverFile
             */
            $coverFile = $contentUnit->getCover();
            if ($coverFile && $contentUnit->getContent()) {
                /**
                 * @var Account $channel
                 */
                $channel = $contentUnit->getContent()->getChannel();
                $storageUrl = $channel->getUrl();

                $coverFile->setUrl($storageUrl . '/storage?file=' . $coverFile->getUri());
            }

            if ($boosted === null) {
                $isBoosted = $this->em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnit);
                $contentUnit->setBoosted($isBoosted);
            } else {
                $contentUnit->setBoosted($boosted);
            }

            if ($author == $contentUnit->getAuthor()) {
                //  get article next & previous versions
                $previousVersions = $this->em->getRepository(\App\Entity\ContentUnit::class)->getArticleHistory($contentUnit, true);
                if ($previousVersions) {
                    /**
                     * @var \App\Entity\ContentUnit $previousVersion
                     */
                    foreach ($previousVersions as $previousVersion) {
                        /**
                         * @var Transaction $transaction
                         */
                        $transaction = $previousVersion->getTransaction();
                        $previousVersion->setPublished($transaction->getTimeSigned());
                    }
                }
                $previousVersions = $this->serializer->serialize($previousVersions, 'json', ['groups' => ['contentUnitList', 'tag', 'file', 'accountBase', 'publication']]);
                $previousVersions = json_decode($previousVersions, true);

                $contentUnit->setPreviousVersions($previousVersions);
            }

            //  check if transaction confirmed
            $block = $contentUnit->getTransaction()->getBlock();
            if ($block) {
                $contentUnit->setStatus('confirmed');
            } else {
                $contentUnit->setStatus('pending');
            }

            //  check boosts
            if ($contentUnit->getBoosts()) {
                $timezone = new \DateTimeZone('UTC');
                $date = new \DateTime();
                $date->setTimezone($timezone);

                $boosts = $contentUnit->getBoosts();

                /**
                 * @var BoostedContentUnit $boost
                 */
                foreach ($boosts as $boost) {
                    if ($boost->isCancelled()) {
                        $boost->setStatus('cancelled');
                    } elseif (($boost->getStartTimePoint() + $boost->getHours() * 3600) < $date->getTimestamp()) {
                        $boost->setStatus('finished');
                    } elseif (!$boost->getTransaction()->getBlock()) {
                        $boost->setStatus('pending');
                    } else {
                        $boost->setStatus('active');
                    }
                }
            }
        }

        return $contentUnits;
    }

    /**
     * @param $contentUnits
     * @param bool $list
     * @return mixed
     */
    public function prepareTags($contentUnits, $list = true)
    {
        if ($list) {
            for ($i=0; $i<count($contentUnits); $i++) {
                if ($contentUnits[$i]['tags']) {
                    $cuTagsArr = [];
                    $cuTags = $contentUnits[$i]['tags'];
                    for ($j=0; $j<count($cuTags); $j++) {
                        $cuTagsArr[] = $cuTags[$j]['tag'];
                    }
                    $contentUnits[$i]['tags'] = $cuTagsArr;
                }
            }
        } else {
            if ($contentUnits['tags']) {
                $cuTagsArr = [];
                $cuTags = $contentUnits['tags'];
                for ($j=0; $j<count($cuTags); $j++) {
                    $cuTagsArr[] = $cuTags[$j]['tag'];
                }
                $contentUnits['tags'] = $cuTagsArr;
            }
        }

        return $contentUnits;
    }
}