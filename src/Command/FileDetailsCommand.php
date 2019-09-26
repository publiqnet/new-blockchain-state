<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/26/19
 * Time: 11:44 AM
 */

namespace App\Command;

use App\Entity\Account;
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

        //  GET FILES WITHOUT DETAILS
        $files = $this->em->getRepository(\App\Entity\File::class)->findBy(['mimeType' => null]);
        if ($files) {
            /**
             * @var \App\Entity\File $file
             */
            foreach ($files as $file) {
                /**
                 * @var Account[] $fileStorages
                 */
                $fileStorages = $this->customService->getFileStoragesWithPublicAccess($file);
                if (count($fileStorages)) {
                    $randomStorage = rand(0, count($fileStorages) - 1);
                    $storageUrl = $fileStorages[$randomStorage]->getUrl();

                    //  get file details
                    if (!$file->getMimeType()) {
                        $fileDetails = $this->blockChainService->getFileDetails($file->getUri(), $storageUrl);
                        if ($fileDetails instanceof StorageFileDetailsResponse) {
                            $file->setMimeType($fileDetails->getMimeType());
                            $file->setSize($fileDetails->getSize());

                            $this->em->persist($file);
                            $this->em->flush();

                            if ($file->getMimeType() == 'text/html') {
                                $fileText = file_get_contents($storageUrl . '/storage?file=' . $file->getUri());

                                $fileContentUnits = $file->getContentUnits();
                                if ($fileContentUnits) {
                                    /**
                                     * @var \App\Entity\ContentUnit $fileContentUnit
                                     */
                                    foreach ($fileContentUnits as $fileContentUnit) {
                                        $contentUnitText = $fileContentUnit->getTextWithData();
                                        $contentUnitText = str_replace($file->getUri(), $fileText, $contentUnitText);
                                        $fileContentUnit->setTextWithData($contentUnitText);

                                        $this->em->persist($fileContentUnit);
                                        $this->em->flush();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->io->success('Done');
        $this->release();

        return null;
    }
}
