<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/26/19
 * Time: 11:44 AM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\Content;
use App\Entity\ContentUnit;
use App\Entity\File;
use App\Service\BlockChain;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use PubliqAPI\Model\StorageFileDetailsResponse;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FileDetailsCommand extends ContainerAwareCommand
{
    use LockableTrait;

    protected static $defaultName = 'state:file-details';

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;

    /** @var \App\Service\Custom $customService */
    private $customService;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $em */
    private $io;


    public function __construct(BlockChain $blockChain, Custom $custom)
    {
        parent::__construct();

        $this->blockChainService = $blockChain;
        $this->customService = $custom;
    }

    protected function configure()
    {
        $this->setDescription('Get file details from storage or channel');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');

        // DISABLE SQL LOGGING, CAUSE IT CAUSES MEMORY SHORTAGE on large inserts
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');
            return 0;
        }

        //  GET CONTENT UNITS WITHOUT DETAILS
        /**
         * @var ContentUnit[] $contentUnits
         */
        $contentUnits = $this->em->getRepository(ContentUnit::class)->findBy(['text' => null]);
        if ($contentUnits) {
            foreach ($contentUnits as $contentUnit) {
                /**
                 * @var Account $channel
                 */
                $channel = $contentUnit->getChannel();
                if ($channel->getUrl()) {
                    $storageData = file_get_contents($channel->getUrl() . '/storage?file=' . $contentUnit->getUri());
                    if (!mb_check_encoding($storageData, 'UTF-8')) {
                        $storageData = utf8_encode($storageData);
                    }

                    if ($storageData) {
                        $contentUnitTitle = 'Unknown';
                        $coverUri = null;

                        if (strpos($storageData, '</h1>')) {
                            if (strpos($storageData, '<h1>') > 0) {
                                $coverPart = substr($storageData, 0, strpos($storageData, '<h1>'));

                                $coverPart = substr($coverPart, strpos($coverPart,'src="') + 5);
                                $coverUri = substr($coverPart, 0, strpos($coverPart, '"'));
                            }
                            $contentUnitTitle = trim(strip_tags(substr($storageData, 0, strpos($storageData, '</h1>') + 5)));
                            $contentUnitText = substr($storageData, strpos($storageData, '</h1>') + 5);
                        } else {
                            $contentUnitText = $storageData;
                        }

                        $contentUnit->setTitle($contentUnitTitle);
                        $contentUnit->setText($contentUnitText);
                        $contentUnit->setTextWithData($contentUnitText);
                        if ($coverUri) {
                            $coverFileEntity = $this->em->getRepository(File::class)->findOneBy(['uri' => $coverUri]);
                            $contentUnit->setCover($coverFileEntity);
                        }

                        $this->em->persist($contentUnit);
                        $this->em->flush();
                    }
                }
            }
        }

        //  GET CONTENT UNITS WITHOUT DETAILED DATA
        /**
         * @var ContentUnit[] $contentUnits
         */
        $contentUnits = $this->em->getRepository(ContentUnit::class)->findBy(['textWithDataChecked' => false]);
        if ($contentUnits) {
            foreach ($contentUnits as $contentUnit) {
                if (!$contentUnit->getText()) {
                    continue;
                }

                /**
                 * @var Account $channel
                 */
                $channel = $contentUnit->getChannel();

                /**
                 * @var File[] $files
                 */
                $files = $contentUnit->getFiles();
                if ($files) {
                    $allFilesKnown = true;
                    foreach ($files as $file) {
                        //  get file details
                        if (!$file->getMimeType()) {
                            $fileDetails = $this->blockChainService->getFileDetails($file->getUri(), $channel->getUrl());
                            if ($fileDetails instanceof StorageFileDetailsResponse) {
                                $file->setMimeType($fileDetails->getMimeType());
                                $file->setSize($fileDetails->getSize());
                                if ($file->getMimeType() == 'text/html') {
                                    $fileText = file_get_contents($channel->getUrl() . '/storage?file=' . $file->getUri());
                                    if (!mb_check_encoding($fileText, 'UTF-8')) {
                                        $fileText = utf8_encode($fileText);
                                    }
                                    $file->setContent($fileText);
                                }

                                $this->em->persist($file);
                                $this->em->flush();
                            }
                        }

                        if (!$file->getMimeType()) {
                            $allFilesKnown = false;
                            continue;
                        }

                        $contentUnitText = $contentUnit->getTextWithData();

                        if ($file->getMimeType() == 'text/html') {
                            $fileText = $file->getContent();
                            $contentUnitText = str_replace($file->getUri(), $fileText, $contentUnitText);
                        } elseif ($contentUnit->getContent()) {
                            /**
                             * @var Content $fileContent
                             */
                            $fileContent = $contentUnit->getContent();

                            /**
                             * @var Account $channel
                             */
                            $channel = $fileContent->getChannel();

                            $fileUrl = $channel->getUrl() . '/storage?file=' . $file->getUri();
                            $contentUnitText = str_replace('src="' . $file->getUri() . '"', 'src="' . $fileUrl . '"', $contentUnitText);
                        }

                        $contentUnit->setTextWithData($contentUnitText);
                        $this->em->persist($contentUnit);
                        $this->em->flush();
                    }

                    if ($allFilesKnown) {
                        $contentUnit->setTextWithDataChecked(true);
                        $this->em->persist($contentUnit);
                        $this->em->flush();
                    }
                }
            }
        }

        $this->io->success('Done');
        $this->release();

        return null;
    }
}
