<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/10/20
 * Time: 3:44 PM
 */

namespace App\Command;

use App\Entity\ContentUnit;
use App\Entity\IndexNumber;
use App\Event\ArticleNewEvent;
use App\Event\ArticleShareEvent;
use App\Service\BlockChain;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class NotifyCommand extends ContainerAwareCommand
{
    use LockableTrait;

    protected static $defaultName = 'state:notify';

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;

    /** @var \App\Service\Custom $customService */
    private $customService;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;


    public function __construct(BlockChain $blockChain, Custom $custom)
    {
        parent::__construct();

        $this->blockChainService = $blockChain;
        $this->customService = $custom;
    }

    protected function configure()
    {
        $this->setDescription('Command to notify users about new articles');
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
         * get the last index number - if not exist set default as 0
         * @var IndexNumber $indexNumber
         */
        $indexNumber = $this->em->getRepository(IndexNumber::class)->findOneBy([], ['id' => 'DESC']);
        if (!$indexNumber) {
            $this->io->success('Done');
            $this->release();

            return null;
        }

        $timezone = new \DateTimeZone('UTC');
        $datetimeObj = new \DateTime();
        $datetimeObj->setTimezone($timezone);

        $datetime = $indexNumber->getLastNotifyTime();
        if ($datetime == 0) {
            $indexNumber->setLastNotifyTime($datetimeObj->getTimestamp());
            $this->em->persist($indexNumber);
            $this->em->flush();

            $this->io->success('Done');
            $this->release();

            return null;
        }

        /**
         * @var ContentUnit[] $articles
         */
        $articles = $this->em->getRepository(ContentUnit::class)->getArticleAfterDate($datetime);
        if ($articles) {
            foreach ($articles as $article) {
                // notify author to share
                $this->getContainer()->get('event_dispatcher')->dispatch(
                    ArticleShareEvent::NAME,
                    new ArticleShareEvent($article)
                );

                // notify subscribed users
                $this->getContainer()->get('event_dispatcher')->dispatch(
                    ArticleNewEvent::NAME,
                    new ArticleNewEvent($article->getAuthor(), $article)
                );
            }
        }

        $indexNumber->setLastNotifyTime($datetimeObj->getTimestamp());
        $this->em->persist($indexNumber);
        $this->em->flush();

        $this->io->success('Done');
        $this->release();

        return null;
    }
}
