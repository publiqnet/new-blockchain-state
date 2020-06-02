<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/8/20
 * Time: 7:54 PM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\Publication;
use App\Service\BlockChain;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TrendingsCommand extends Command
{
    const ACTION_COUNT = 5000;

    use LockableTrait;

    protected static $defaultName = 'state:update-trending';

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;

    /** @var \App\Service\Custom $customService */
    private $customService;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;

    public function __construct(EntityManagerInterface $em, BlockChain $blockChain, Custom $custom)
    {
        parent::__construct();

        $this->em = $em;
        $this->blockChainService = $blockChain;
        $this->customService = $custom;
    }

    protected function configure()
    {
        $this->setDescription('Command to update trending status for authors & publications');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

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

        //  enable channel exclude filter
        $this->em->getFilters()->enable('channel_exclude_filter');

        //  AUTHOR
        $query = $this->em->getRepository(Account::class)->createQueryBuilder('a')->update()->set('a.trendingPosition', 0)->getQuery();
        $query->execute();

        /**
         * @var Account[] $trendingAuthors
         */
        $trendingAuthors = $this->em->getRepository(Account::class)->getTrendingAuthors(16);
        if ($trendingAuthors) {
            $i = 32;
            foreach ($trendingAuthors as $trendingAuthor) {
                if ($trendingAuthor->getTotalViews() == 0) {
                    break;
                }
                $trendingAuthor->setTrendingPosition($i);
                $this->em->persist($trendingAuthor);

                $i--;
            }

            $this->em->flush();
        }

        //  PUBLICATION
        $query = $this->em->getRepository(Publication::class)->createQueryBuilder('p')->update()->set('p.trendingPosition', 0)->getQuery();
        $query->execute();

        /**
         * @var Publication[] $trendingPublications
         */
        $trendingPublications = $this->em->getRepository(Publication::class)->getTrendingPublications(16);
        if ($trendingPublications) {
            $i = 32;
            foreach ($trendingPublications as $trendingPublication) {
                if ($trendingPublication->getTotalViews() == 0) {
                    break;
                }
                $trendingPublication->setTrendingPosition($i);
                $this->em->persist($trendingPublication);

                $i--;
            }

            $this->em->flush();
        }

        $this->io->success('Done');

        $this->release();

        return null;
    }
}
