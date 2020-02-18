<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 2/17/20
 * Time: 3:21 PM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\ChannelSummary;
use App\Service\BlockChain;
use Doctrine\ORM\EntityManager;
use PubliqAPI\Base\PublicAddressType;
use PubliqAPI\Model\PublicAddressesInfo;
use PubliqAPI\Model\PublicAddressInfo;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ChannelSummaryCommand extends ContainerAwareCommand
{
    use LockableTrait;

    protected static $defaultName = 'state:channel-summary';

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $em */
    private $io;


    public function __construct(BlockChain $blockChain)
    {
        parent::__construct();

        $this->blockChainService = $blockChain;
    }

    protected function configure()
    {
        $this->setDescription('Generate channels summary of distributed & published content');
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

        $timezone = new \DateTimeZone('UTC');
        $date = new \DateTime();
        $date->setTimezone($timezone);

        //  last month
        $date->modify('-1 month');

        $channelsRes = $this->em->getRepository(Account::class)->getChannelsSummary($date->getTimestamp());
        foreach ($channelsRes as $channelsResSingle) {
            $distributedContentsCount = $channelsResSingle->getDistributedContentsCount();
            $publishedContentsCount = $channelsResSingle->getPublishedContentsCount();

            $channelSummary = $this->em->getRepository(ChannelSummary::class)->findOneBy(['channel' => $channelsResSingle]);
            if (!$channelSummary) {
                $channelSummary = new ChannelSummary();
                $channelSummary->setChannel($channelsResSingle);
            }

            $channelSummary->setDistributedMonth($distributedContentsCount);
            $channelSummary->setPublishedMonth($publishedContentsCount);

            $this->em->persist($channelSummary);
            $this->em->flush();
        }

        //  last week
        $date->modify('+1 month');
        $date->modify('-1 week');
        $channelsRes = $this->em->getRepository(Account::class)->getChannelsSummary($date->getTimestamp());
        foreach ($channelsRes as $channelsResSingle) {
            $distributedContentsCount = $channelsResSingle->getDistributedContentsCount();
            $publishedContentsCount = $channelsResSingle->getPublishedContentsCount();

            $channelSummary = $this->em->getRepository(ChannelSummary::class)->findOneBy(['channel' => $channelsResSingle]);
            if (!$channelSummary) {
                $channelSummary = new ChannelSummary();
                $channelSummary->setChannel($channelsResSingle);
            }

            $channelSummary->setDistributedWeek($distributedContentsCount);
            $channelSummary->setPublishedWeek($publishedContentsCount);

            $this->em->persist($channelSummary);
            $this->em->flush();
        }

        //  last day
        $date->modify('+1 week');
        $date->modify('-1 day');
        $channelsRes = $this->em->getRepository(Account::class)->getChannelsSummary($date->getTimestamp());
        foreach ($channelsRes as $channelsResSingle) {
            $distributedContentsCount = $channelsResSingle->getDistributedContentsCount();
            $publishedContentsCount = $channelsResSingle->getPublishedContentsCount();

            $channelSummary = $this->em->getRepository(ChannelSummary::class)->findOneBy(['channel' => $channelsResSingle]);
            if (!$channelSummary) {
                $channelSummary = new ChannelSummary();
                $channelSummary->setChannel($channelsResSingle);
            }

            $channelSummary->setDistributedDay($distributedContentsCount);
            $channelSummary->setPublishedDay($publishedContentsCount);

            $this->em->persist($channelSummary);
            $this->em->flush();
        }

        $this->io->success('Public addresses is synced now!');
        $this->release();

        return null;
    }
}