<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/18/19
 * Time: 1:43 PM
 */

namespace App\Command;

use App\Entity\Block;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TempCommand extends ContainerAwareCommand
{
    use LockableTrait;

    protected static $defaultName = 'state:temp';

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
         * @var Block[] $blocks
         */
        $blocks = $this->em->getRepository(Block::class)->findAll();
        foreach ($blocks as $block) {
            $blockNumber = $block->getNumber();

            $blockHashData = file_get_contents('https://tracker-api.publiq.network/api/temp/block/' . $blockNumber);
            $blockHashData = json_decode($blockHashData, true);

            $blockHash = $blockHashData['hash'];
            $block->setHash($blockHash);

            $this->em->persist($block);
            $this->em->flush();

            $this->io->writeln($blockNumber . ': ' . $blockHash);
        }

        $this->io->success('Done');
        $this->release();

        return null;
    }
}
