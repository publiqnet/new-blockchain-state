<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/15/20
 * Time: 11:45 AM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\Block;
use App\Entity\ContentUnit;
use App\Entity\File;
use App\Entity\ServiceStatisticsDetail;
use App\Entity\Transaction;
use App\Service\BlockChain;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use PubliqAPI\Base\LoggingType;
use PubliqAPI\Model\BlockLog;
use PubliqAPI\Model\LoggedTransaction;
use PubliqAPI\Model\LoggedTransactions;
use PubliqAPI\Model\ServiceStatistics;
use PubliqAPI\Model\ServiceStatisticsCount;
use PubliqAPI\Model\ServiceStatisticsFile;
use PubliqAPI\Model\TransactionLog;
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


    public function __construct(BlockChain $blockChain, Custom $custom)
    {
        parent::__construct();

        $this->blockChainService = $blockChain;
        $this->customService = $custom;
    }

    protected function configure()
    {
        $this->setDescription('Sync state database with BlockChain');
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
     * @throws \Doctrine\Common\Persistence\Mapping\MappingException
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

        $this->em->beginTransaction();

        $index = 0;
        $this->io->writeln(sprintf('Started at with index=%s: %s', ($index ? $index + 1 : $index), date('Y-m-d H:i:s')));

        /**
         * @var LoggedTransactions $loggedTransactions
         */
        $loggedTransactions = $this->blockChainService->getLoggedTransactions($index ? $index + 1 : $index);

        $this->io->writeln(sprintf('Data received at: %s', date('Y-m-d H:i:s')));

        /**
         * @var LoggedTransaction $loggedTransaction
         */
        foreach ($loggedTransactions->getActions() as $loggedTransaction) {
            $action = $loggedTransaction->getAction();

            $appliedReverted = $loggedTransaction->getLoggingType() === LoggingType::apply;
            $index = $loggedTransaction->getIndex();

            if ($action instanceof BlockLog) {
                $blockHash = $action->getBlockHash();
                $transactions = $action->getTransactions();

                $block = $this->em->getRepository(Block::class)->findOneBy(['hash' => $blockHash]);
                if (!$block) {
                    continue;
                }

                if (is_array($transactions)) {
                    /**
                     * @var TransactionLog $transaction
                     */
                    foreach ($transactions as $transaction) {
                        //  get transaction data
                        $transactionHash = $transaction->getTransactionHash();

                        if ($transaction->getAction() instanceof ServiceStatistics && $appliedReverted) {
                            /**
                             * @var ServiceStatistics $serviceStatistics
                             */
                            $serviceStatistics = $transaction->getAction();

                            $serverAddress = $serviceStatistics->getServerAddress();
                            $serverAddressAccount = $this->checkAccount($serverAddress);

                            if ($serverAddressAccount->isChannel()) {
                                $servedFiles = $serviceStatistics->getFileItems();

                                $transactionEntity = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $transactionHash]);
                                if ($transactionEntity && $transactionEntity->getServiceStatistic()) {
                                    $serviceStatistic = $transactionEntity->getServiceStatistic();

                                    /**
                                     * @var ServiceStatisticsFile $servedFile
                                     */
                                    foreach ($servedFiles as $servedFile) {
                                        $servedFileUri = $servedFile->getFileUri();
                                        $servedFileUnitUri = $servedFile->getUnitUri();

                                        $servedFileEntity = $this->em->getRepository(File::class)->findOneBy(['uri' => $servedFileUri]);
                                        $servedFileUnitEntity = $this->em->getRepository(ContentUnit::class)->findOneBy(['uri' => $servedFileUnitUri]);

                                        if ($servedFileEntity && $servedFileUnitEntity) {
                                            /**
                                             * @var ServiceStatisticsCount[] $servedFileCounts
                                             */
                                            $servedFileCounts = $servedFile->getCountItems();
                                            foreach ($servedFileCounts as $servedFileCount) {
                                                $count = $servedFileCount->getCount();
                                                $storageAddress = $servedFileCount->getPeerAddress();

                                                $storageEntity = $this->checkAccount($storageAddress);

                                                $serviceStatisticsDetail = new ServiceStatisticsDetail();
                                                $serviceStatisticsDetail->setServiceStatistics($serviceStatistic);
                                                $serviceStatisticsDetail->setStorage($storageEntity);
                                                $serviceStatisticsDetail->setContentUnit($servedFileUnitEntity);
                                                $serviceStatisticsDetail->setFile($servedFileEntity);
                                                $serviceStatisticsDetail->setCount($count);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $this->em->flush();
        $this->em->clear();

        $this->em->commit();

        $this->io->writeln(sprintf('Finished at with index=%s: %s', $index, date('Y-m-d H:i:s')));
        $this->io->success('BlockChain is synced now!');

        $this->release();

        return null;
    }

    /**
     * @param string $address
     * @return Account|null|object
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function checkAccount(string $address)
    {
        $account = $this->em->getRepository(Account::class)->findOneBy(['publicKey' => $address]);
        if (!$account) {
            $account = new Account();
            $account->setPublicKey($address);
            $account->setWhole(0);
            $account->setFraction(0);

            $this->em->persist($account);
            $this->em->flush();
        }

        return $account;
    }
}