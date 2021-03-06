<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/21/19
 * Time: 3:50 PM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\File;
use App\Entity\Publication;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThumbnailCommand extends ContainerAwareCommand
{
    use LockableTrait;

    protected static $defaultName = 'state:thumbnail-images';

    /** @var \App\Service\Custom $customService */
    private $customService;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;


    public function __construct(Custom $custom)
    {
        parent::__construct();

        $this->customService = $custom;
    }

    protected function configure()
    {
        $this->setDescription('Create thumbnail for image');
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
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        /**
         * @var File[] $covers
         */
        $covers = $this->em->getRepository(File::class)->getCoverFilesWithoutThumbnails();
        if ($covers) {
            foreach ($covers as $cover) {
                $result = $this->customService->createThumbnail($cover, 'public/');
                $this->io->writeln($cover->getUri() . ': ' . intval($result));
            }
        }

        /**
         * @var Account[] $authors
         */
        $authors = $this->em->getRepository(Account::class)->getAccountsWithoutThumbnails();
        if ($authors) {
            foreach ($authors as $author) {
                $result = $this->customService->createThumbnailAuthor($author, 'public/');
                $this->io->writeln($author->getPublicKey() . ': ' . intval($result));
            }
        }

        /**
         * @var Publication[] $publications
         */
        $publications = $this->em->getRepository(Publication::class)->getPublicationsWithoutThumbnails();
        if ($publications) {
            foreach ($publications as $publication) {
                $result = $this->customService->createThumbnailPublication($publication, 'public/');
                $this->io->writeln($publication->getSlug() . ': ' . intval($result));
            }
        }

        $this->io->success('Done');

        $this->release();

        return null;
    }
}
