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

        //  GET LAST CONTENT UNIT & ALL CONTENTS WITHOUT DATA CHECKED
        $contentUnit = $this->em->getRepository(ContentUnit::class)->findBy([], ['id' => 'DESC'], 1);
        $lastContentUnit = $contentUnit[0];

        $contentUnitsToUpdate = $this->em->getRepository(ContentUnit::class)->findBy(['textWithDataChecked' => 0]);

        //  GET FILES WITHOUT DETAILS
        $files = $this->em->getRepository(File::class)->findAll();
        if ($files) {
            /**
             * @var File $file
             */
            foreach ($files as $file) {
                //  get file details
                if (!$file->getMimeType()) {
                    /**
                     * @var Account[] $fileStorages
                     */
                    $fileStorages = $this->customService->getFileStoragesWithPublicAccess($file);
                    if (count($fileStorages)) {
                        $randomStorage = rand(0, count($fileStorages) - 1);
                        $storageUrl = $fileStorages[$randomStorage]->getUrl();

                        $fileDetails = $this->blockChainService->getFileDetails($file->getUri(), $storageUrl);
                        if ($fileDetails instanceof StorageFileDetailsResponse) {
                            $file->setMimeType($fileDetails->getMimeType());
                            $file->setSize($fileDetails->getSize());
                            if ($file->getMimeType() == 'text/html') {
                                $fileText = file_get_contents($storageUrl . '/storage?file=' . $file->getUri());
                                $file->setContent($fileText);
                            }

                            $this->em->persist($file);
                            $this->em->flush();
                        }
                    }
                }

                $fileContentUnits = $file->getContentUnits();
                if ($fileContentUnits) {
                    /**
                     * @var \App\Entity\ContentUnit $fileContentUnit
                     */
                    foreach ($fileContentUnits as $fileContentUnit) {
                        if ($fileContentUnit->isTextWithDataChecked()) {
                            continue;
                        }

                        $contentUnitText = $fileContentUnit->getTextWithData();

                        if ($file->getMimeType() == 'text/html') {
                            $fileText = $file->getContent();
                            $contentUnitText = str_replace($file->getUri(), $fileText, $contentUnitText);
                        } elseif ($fileContentUnit->getContent()) {
                            /**
                             * @var Content $fileContent
                             */
                            $fileContent = $fileContentUnit->getContent();

                            /**
                             * @var Account $channel
                             */
                            $channel = $fileContent->getChannel();

                            $fileUrl = $channel->getUrl() . '/storage?file=' . $file->getUri();
                            $contentUnitText = str_replace('src="' . $file->getUri() . '"', 'src="' . $fileUrl . '"', $contentUnitText);
                        }

                        $fileContentUnit->setTextWithData($contentUnitText);
                        $this->em->persist($fileContentUnit);
                        $this->em->flush();
                    }
                }
            }

            if ($contentUnitsToUpdate) {
                foreach ($contentUnitsToUpdate as $contentUnit) {
                    if ($contentUnit->getId() < $lastContentUnit->getId()) {
                        $contentUnit->setTextWithDataChecked(true);
                        $this->em->persist($contentUnit);
                    }
                }
                $this->em->flush();
            }
        }

        $this->io->success('Done');
        $this->release();

        return null;
    }
}
