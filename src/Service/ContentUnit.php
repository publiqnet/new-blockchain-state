<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/2/19
 * Time: 4:48 PM
 */

namespace App\Service;

use App\Entity\BoostedContentUnit;
use App\Entity\File;
use App\Entity\Account;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PubliqAPI\Model\StorageFileDetailsResponse;

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
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    public function prepare($contentUnits, $boosted = null)
    {
        /**
         * @var \App\Entity\ContentUnit $contentUnit
         */
        foreach ($contentUnits as $contentUnit) {
            $files = $contentUnit->getFiles();
            if ($files) {
                $fileStorageUrls = [];

                /**
                 * @var File $file
                 */
                foreach ($files as $file) {
                    $storageUrl = '';
                    $storageAddress = '';

                    /**
                     * @var Account[] $fileStorages
                     */
                    $fileStorages = $this->custom->getFileStoragesWithPublicAccess($file);
                    if (count($fileStorages)) {
                        $randomStorage = rand(0, count($fileStorages) - 1);
                        $storageUrl = $fileStorages[$randomStorage]->getUrl();
                        $storageAddress = $fileStorages[$randomStorage]->getPublicKey();

                        //  get file details
                        if (!$file->getMimeType()) {
                            $fileDetails = $this->blockChain->getFileDetails($file->getUri(), $storageUrl);
                            if ($fileDetails instanceof StorageFileDetailsResponse) {
                                $file->setMimeType($fileDetails->getMimeType());
                                $file->setSize($fileDetails->getSize());

                                $this->em->persist($file);
                                $this->em->flush();
                            }
                        }

                        $file->setUrl($storageUrl . '/storage?file=' . $file->getUri() . '&channel_address=' . $this->channelAddress);
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
                        $storageAddress = $channel->getPublicKey();

                        //  get file details
                        if (!$file->getMimeType()) {
                            $fileDetails = $this->blockChain->getFileDetails($file->getUri(), $storageUrl);
                            if ($fileDetails instanceof StorageFileDetailsResponse) {
                                $file->setMimeType($fileDetails->getMimeType());
                                $file->setSize($fileDetails->getSize());

                                $this->em->persist($file);
                                $this->em->flush();
                            }
                        }

                        $file->setUrl($storageUrl . '/storage?file=' . $file->getUri() . '&channel_address=' . $this->channelAddress);
                    }

                    $fileStorageUrls[$file->getUri()] = ['url' => $storageUrl, 'address' => $storageAddress];
                }

                //  replace file uri to url
                foreach ($fileStorageUrls as $uri => $fileStorageData) {
                    $contentUnitText = $contentUnit->getText();
                    $contentUnitText = str_replace('src="' . $uri . '"', 'src="' . $fileStorageData['url'] . '/storage?file=' . $uri . '&channel_address=' . $this->channelAddress . '"', $contentUnitText);
                    $contentUnit->setText($contentUnitText);
                }
            }

            /**
             * @var Transaction $transaction
             */
            $transaction = $contentUnit->getTransaction();
            $contentUnit->setPublished($transaction->getTimeSigned());

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