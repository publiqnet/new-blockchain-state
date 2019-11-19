<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/19/19
 * Time: 11:17 AM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\Block;
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
            $viewsData = file_get_contents('https://tracker-api.publiq.network/api/temp/article/' . $contentUnit->getUri());
            $viewsData = json_decode($viewsData, true);

            $views = 0;
            if ($viewsData) {
                foreach ($viewsData as $viewsDataSingle) {
                    $blockHash = $viewsDataSingle['block']['hash'];
                    $channelPublicKey = $viewsDataSingle['channel']['publicKey'];
                    $viewsTime = $viewsDataSingle['viewsTime'];
                    $viewsCount = $viewsDataSingle['viewsCount'];

                    /**
                     * @var Block $block
                     */
                    $block = $this->em->getRepository(Block::class)->findOneBy(['hash' => $blockHash]);

                    /**
                     * @var Account $channel
                     */
                    $channel = $this->em->getRepository(Account::class)->findOneBy(['publicKey' => $channelPublicKey]);

                    if ($block && $channel) {
                        $contentUnitViews = new ContentUnitViews();
                        $contentUnitViews->setContentUnit($contentUnit);
                        $contentUnitViews->setBlock($block);
                        $contentUnitViews->setChannel($channel);
                        $contentUnitViews->setViewsTime($viewsTime);
                        $contentUnitViews->setViewsCount($viewsCount);

                        $this->em->persist($contentUnitViews);
                        $this->em->flush();

                        $views += $viewsCount;
                    }
                }
            }

            $contentUnit->setViews($views);
            $this->em->persist($contentUnit);
            $this->em->flush();
        }

        $this->io->success('Done');
        $this->release();

        return null;
    }
}
