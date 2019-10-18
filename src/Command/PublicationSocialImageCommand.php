<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 10/11/19
 * Time: 11:40 AM
 */

namespace App\Command;

use App\Entity\Publication;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PublicationSocialImageCommand extends ContainerAwareCommand
{
    use LockableTrait;

    protected static $defaultName = 'state:publication-social-images';

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
        $this->setDescription('Create social images for publications');
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
         * @var Publication $publications[]
         */
        $publications = $this->em->getRepository(Publication::class)->findBy(['socialImage' => null]);
        if ($publications) {
            foreach ($publications as $publication) {
                $this->customService->createSocialImageOfPublication($publication, 'public/');
            }
        }

        $this->io->success('Done');

        $this->release();

        return null;
    }
}
