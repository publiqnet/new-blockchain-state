<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/16/21
 * Time: 1:15 PM
 */

namespace App\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Google\Cloud\PubSub\PubSubClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PubSubTestCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'state:pub-sub-test';

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;


    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
    }

    protected function configure()
    {
        $this->setDescription('Test Google PubSub');
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
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        $pubSub = new PubSubClient();
        $topic = $pubSub->topic($_ENV['GOOGLE_PUBSUB_TOPIC_ID']);
        $publishRes = $topic->publish([
            'naturalId' => 'blogAndPostId/blog/post/4265-608203fc64125e0006f705ee',
            'publiqUrl' => 'https://explorer.publiq.network/t/H47Jaj419ggDGmuoaBpBUe15M46MEhmxspPJ3BjhBwDy'
        ]);

        var_dump($publishRes);

        $this->io->success('Done');
        $this->release();

        return null;
    }
}