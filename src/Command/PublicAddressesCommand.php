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
use PubliqAPI\Base\PublicAddressType;
use PubliqAPI\Model\PublicAddressesInfo;
use PubliqAPI\Model\PublicAddressInfo;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PublicAddressesCommand extends ContainerAwareCommand
{
    const IP_API_KEY = '7497f55fbeb3c1ee6dccec11d2f44305';

    use LockableTrait;

    protected static $defaultName = 'state:tracker-public-addresses';

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
        $publicAddresses = $this->blockChainService->getPublicAddresses(PublicAddressType::p2p);
        if ($publicAddresses->getAddressesInfo()) {
            /**
             * @var PublicAddressInfo $publicAddress
             */
            foreach ($publicAddresses->getAddressesInfo() as $publicAddress) {
                $nodeAddress = $publicAddress->getNodeAddress();
                $nodeEntity = $this->em->getRepository(Account::class)->findOneBy(['publicKey' => $nodeAddress]);
                if ($nodeEntity) {
                    $currentUrl = $nodeEntity->getUrl();

                    $sslIpAddress = $publicAddress->getSslIpAddress()->getRemote()->getAddress();
                    $sslPort = $publicAddress->getSslIpAddress()->getRemote()->getPort();
                    if ($sslIpAddress) {
                        $url = 'https://' . $sslIpAddress;
                        if ($sslPort) {
                            $url .= ':' . $sslPort;
                        }

                        $locationCheckAddress = $sslIpAddress;
                    } else {
                        $ipAddress = $publicAddress->getIpAddress()->getRemote()->getAddress();
                        $port = $publicAddress->getIpAddress()->getRemote()->getPort();

                        $url = 'http://' . $ipAddress;
                        if ($port) {
                            $url .= ':' . $port;
                        }

                        $locationCheckAddress = $ipAddress;
                    }

                    $nodeEntity->setMiner(true);
                    $nodeEntity->setUrl($url);


                    //  get location if url has changed
                    if ($currentUrl != $url || $nodeEntity->getLat() === null) {
                        $locationData = file_get_contents('http://api.ipapi.com/' . $locationCheckAddress . '?access_key=' . self::IP_API_KEY . '&format=1');
                        if ($locationData) {
                            $locationData = json_decode($locationData, true);
                            $lat = $locationData['latitude'];
                            $lng = $locationData['longitude'];

                            if ($lat !== null && $lng !== null) {
                                $nodeEntity->setLat($lat);
                                $nodeEntity->setLng($lng);
                            }
                        }
                    }

                    $this->em->persist($nodeEntity);
                    $this->em->flush();
                }
            }
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
            }
        }

        $this->io->success('Public addresses is synced now!');
        $this->release();

        return null;
    }
}
