<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/19/19
 * Time: 11:17 AM
 */

namespace App\Command;

use App\Entity\ContentUnit;
use App\Entity\ContentUnitViews;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TempCommandViews extends ContainerAwareCommand
{
    use LockableTrait;

    protected static $defaultName = 'state:temp-views';

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $em */
    private $io;


    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Temp command to update corrupt data');
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
         * @var ContentUnit[] $contentUnits
         */
        $contentUnits = $this->em->getRepository(ContentUnit::class)->findAll();
        foreach ($contentUnits as $contentUnit) {
            $views = $this->em->getRepository(ContentUnitViews::class)->getArticleViews($contentUnit);
            $views = intval($views[0]['views']);

            $contentUnit->setViews($views);
            $this->em->persist($contentUnit);
            $this->em->flush();

            echo $contentUnit->getUri() . ': ' . $views . PHP_EOL;
        }

        $this->io->success('Done');
        $this->release();

        return null;
    }
}
