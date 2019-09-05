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

    public function __construct(EntityManagerInterface $em, string $channelAddress, BlockChain $blockChain, Custom $custom, SerializerInterface $serializer)
    {
        $this->em = $em;
        $this->channelAddress = $channelAddress;
        $this->blockChain = $blockChain;
        $this->custom = $custom;
        $this->serializer = $serializer;
    }

    /**
     * @param $contentUnits
     * @param null $boosted
     * @param Account|null $author
     * @return mixed
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

            if ($author) {
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
        }

        return $contentUnits;
    }
}