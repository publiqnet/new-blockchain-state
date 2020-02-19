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
use App\Entity\Dictionary;
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
        $this->setDescription('Temporary command');
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

        $es = file_get_contents('https://publiq.fimagenes.com:444/jsons/es.json');
        $es = json_decode($es, true);
        foreach ($es as $sectionKey => $section) {
            foreach ($section as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $mainKey = $sectionKey . '.' . $key . '.' . $subKey;

                        echo $mainKey . ': ' . $subValue . PHP_EOL;

                        $translation = $this->em->getRepository(Dictionary::class)->findOneBy(['wordKey' => $mainKey]);
                        $translation->setLocale('es');
                        $translation->setValue($subValue);
                        $this->em->persist($translation);
                        $this->em->flush();
                    }
                } else {
                    $mainKey = $sectionKey . '.' . $key;

                    echo $mainKey . ': ' . $value . PHP_EOL;

                    $translation = $this->em->getRepository(Dictionary::class)->findOneBy(['wordKey' => $mainKey]);
                    $translation->setLocale('es');
                    $translation->setValue($value);
                    $this->em->persist($translation);
                    $this->em->flush();
                }
            }
        }

        $this->io->success('Done');

        $this->release();

        return null;
    }
}
