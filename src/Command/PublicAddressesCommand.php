<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/24/19
 * Time: 6:41 PM
 */

namespace App\Command;

use App\Entity\Account;
use App\Service\BlockChain;
use Doctrine\ORM\EntityManager;
use PubliqAPI\Model\PublicAddressesInfo;
use PubliqAPI\Model\PublicAddressInfo;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PublicAddressesCommand extends ContainerAwareCommand
{
    const BATCH = 100;
    const ACTION_COUNT = 5000;

    use LockableTrait;

    protected static $defaultName = 'state:public-addresses';

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;
    /** @var EntityManager $em */
    private $em;
    /** @var SymfonyStyle $em */
    private $io;
    /** @var array $balances */
    private $balances = [];


    public function __construct(BlockChain $blockChain)
    {
        parent::__construct();

        $this->blockChainService = $blockChain;
    }

    protected function configure()
    {
        $this->setDescription('Update public addresses');
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

        /**
         * @var PublicAddressesInfo $publicAddresses
         */
        $publicAddresses = $this->blockChainService->getPublicAddresses();
        if ($publicAddresses->getAddressesInfo()) {
            /**
             * @var PublicAddressInfo $publicAddress
             */
            foreach ($publicAddresses->getAddressesInfo() as $publicAddress) {
                if ($publicAddress->getSecondsSinceChecked() > 3600) {
                    break;
                }

                $nodeAddress = $publicAddress->getNodeAddress();

                $nodeEntity = $this->em->getRepository(Account::class)->findOneBy(['address' => $nodeAddress]);
                if ($nodeEntity) {
                    $sslIpAddress = $publicAddress->getSslIpAddress()->getLocal()->getAddress();
                    $sslPort = $publicAddress->getSslIpAddress()->getLocal()->getPort();
                    if ($sslIpAddress) {
                        $url = 'https://' . $sslIpAddress;
                        if ($sslPort) {
                            $url .= ':' . $sslPort;
                        }
                    } else {
                        $ipAddress = $publicAddress->getIpAddress()->getLocal()->getAddress();
                        $port = $publicAddress->getIpAddress()->getLocal()->getPort();

                        $url = 'http://' . $ipAddress;
                        if ($port) {
                            $url .= ':' . $port;
                        }
                    }

                    $nodeEntity->setUrl($url);
                    $this->em->persist($nodeEntity);
                    $this->em->flush();
                }
            }
        }

        $this->io->success('Public addresses is synced now!');

        $this->release();

        return null;
    }
}
