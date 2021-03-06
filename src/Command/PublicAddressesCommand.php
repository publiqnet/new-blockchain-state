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
use Doctrine\ORM\EntityManagerInterface;
use PubliqAPI\Model\PublicAddressesInfo;
use PubliqAPI\Model\PublicAddressInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PublicAddressesCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'state:public-addresses';

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $em */
    private $io;


    public function __construct(EntityManagerInterface $em, BlockChain $blockChain)
    {
        parent::__construct();

        $this->em = $em;
        $this->blockChainService = $blockChain;
    }

    protected function configure()
    {
        $this->setDescription('Update public addresses');
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

        $checkedNodeAddresses = [];

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
                    $nodeAddress = $publicAddress->getNodeAddress();
                    $nodeEntity = $this->em->getRepository(Account::class)->findOneBy(['publicKey' => $nodeAddress]);
                    if ($nodeEntity) {
                        $nodeEntity->setUrl(null);
                        $this->em->persist($nodeEntity);
                        $this->em->flush();
                    }
                } else {
                    $nodeAddress = $publicAddress->getNodeAddress();
                    $nodeEntity = $this->em->getRepository(Account::class)->findOneBy(['publicKey' => $nodeAddress]);
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

                $checkedNodeAddresses[] = $nodeAddress;
            }
        }

        /**
         * @var Account[] $nodes
         */
        $nodes = $this->em->getRepository(Account::class)->getNodesByPublicKeysWithException($checkedNodeAddresses);
        if ($nodes) {
            foreach ($nodes as $nodeEntity) {
                $nodeEntity->setUrl(null);
                $this->em->persist($nodeEntity);
                $this->em->flush();
            }
        }

        $this->io->success('Public addresses is synced now!');

        $this->release();

        return null;
    }
}
