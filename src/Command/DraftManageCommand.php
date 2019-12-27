<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/24/19
 * Time: 12:35 PM
 */

namespace App\Command;

use App\Entity\Draft;
use App\Entity\DraftFile;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DraftManageCommand extends ContainerAwareCommand
{
    use LockableTrait;

    protected static $defaultName = 'state:delete-published-drafts';

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;


    protected function configure()
    {
        $this->setDescription('Delete drafts published a day ago');
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
         * @var Draft[] $drafts
         */
        $drafts = $this->em->getRepository(Draft::class)->getPublishedDrafts();
        if ($drafts) {
            foreach ($drafts as $draft) {
                //  get local files & delete
                /**
                 * @var DraftFile[] $draftFiles
                 */
                $draftFiles = $this->em->getRepository(DraftFile::class)->findBy(['draft' => $draft]);
                if ($draftFiles) {
                    foreach ($draftFiles as $draftFile) {
                        $uri = $draftFile->getUri();
                        $fileUsages = $this->em->getRepository(DraftFile::class)->getFileUsagesWithException($uri, $draft);
                        if (!$fileUsages && file_exists(getcwd() . '/public/' . $draftFile->getPath())) {
                            unlink(getcwd() . '/public/' . $draftFile->getPath());
                        }
                    }
                }

                $this->em->remove($draft);
                $this->em->flush();
            }
        }

        $this->io->success('Done');
        $this->release();

        return null;
    }
}
