<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/27/19
 * Time: 5:22 PM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\ContentUnit;
use App\Entity\ContentUnitTag;
use App\Entity\Tag;
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
         * @var Account $channel
         */
        $channel = $this->em->getRepository(Account::class)->find(1621);

        /**
         * @var ContentUnit[] $articles
         */
        $articles = $this->em->getRepository(ContentUnit::class)->findOneBy(['channel' => $channel]);
        if ($articles) {
            foreach ($articles as $article) {
                $contentUnitText = $article->getText();

                //  GET TAGS
                while (strpos($contentUnitText, '</h3>')){
                    $contentUnitTag = trim(strip_tags(substr($contentUnitText, 0, strpos($contentUnitText, '</h3>') + 5)));
                    $contentUnitText = substr($contentUnitText, strpos($contentUnitText, '</h3>') + 5);

                    $tagEntity = $this->em->getRepository(Tag::class)->findOneBy(['name' => $contentUnitTag]);
                    if (!$tagEntity) {
                        $tagEntity = new Tag();
                        $tagEntity->setName($contentUnitTag);
                        $this->em->persist($tagEntity);
                        $this->em->flush();
                    }

                    $contentUnitTagEntity = new ContentUnitTag();
                    $contentUnitTagEntity->setContentUnit($article);
                    $contentUnitTagEntity->setContentUnitUri($article->getUri());
                    $contentUnitTagEntity->setTag($tagEntity);
                    $this->em->persist($contentUnitTagEntity);
                    $this->em->flush();

                    echo $article->getUri() . ' - ' . $contentUnitTag . PHP_EOL;
                }
            }

            $this->em->flush();
        }

        $this->io->success('Done');

        $this->release();

        return null;
    }
}
