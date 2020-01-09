<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/27/19
 * Time: 5:22 PM
 */

namespace App\Command;

use App\Entity\ContentUnit;
use App\Service\BlockChain;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TempCommand extends ContainerAwareCommand
{
    const ACTION_COUNT = 5000;

    use LockableTrait;

    protected static $defaultName = 'state:temp';

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;

    /** @var \App\Service\Custom $customService */
    private $customService;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;

    /** @var array $balances */
    private $balances = [];


    public function __construct(BlockChain $blockChain, Custom $custom)
    {
        parent::__construct();

        $this->blockChainService = $blockChain;
        $this->customService = $custom;
    }

    protected function configure()
    {
        $this->setDescription('Temporary command to set highlight articles');
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
         * @var ContentUnit[] $articles
         */
        $articles = $this->em->getRepository(ContentUnit::class)->getBoostedArticlesWithCover(9999);
        if ($articles) {
            foreach ($articles as $article) {
                $article->setHighlight(true);
                $article->setHighlightFont('Vollkorn');
                $this->em->persist($article);
            }

            $this->em->flush();
        }

        $this->io->success('Done');

        $this->release();

        return null;
    }
}
