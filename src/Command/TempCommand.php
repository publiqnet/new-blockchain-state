<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/27/19
 * Time: 5:22 PM
 */

namespace App\Command;

use App\Entity\Block;
use App\Entity\BoostedContentUnit;
use App\Entity\BoostedContentUnitSpending;
use App\Entity\CancelBoostedContentUnit;
use App\Entity\IndexNumber;
use App\Entity\Transaction;
use App\Service\BlockChain;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use PubliqAPI\Base\LoggingType;
use PubliqAPI\Model\BlockLog;
use PubliqAPI\Model\CancelSponsorContentUnit;
use PubliqAPI\Model\LoggedTransaction;
use PubliqAPI\Model\LoggedTransactions;
use PubliqAPI\Model\SponsorContentUnitApplied;
use PubliqAPI\Model\TransactionLog;
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
        $this->setDescription('Temporary command to get missing and/or currapt data');
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
        /**
         * get the last index number - if not exist set default as 0
         * @var IndexNumber $indexNumber
         */
        $indexNumber = $this->em->getRepository(IndexNumber::class)->findOneBy([], ['id' => 'DESC']);
        if ($indexNumber) {
            $index = $indexNumber->getId();
        }

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
                //  get block data
                $blockHash = $action->getBlockHash();
                $transactions = $action->getTransactions();
                $appliedSponsorItems = $action->getAppliedSponsorItems();

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
                        $transactionSize = $transaction->getTransactionSize();
                        $timeSigned = $transaction->getTimeSigned();
                        $feeWhole = $transaction->getFee()->getWhole();
                        $feeFraction = $transaction->getFee()->getFraction();

                        if ($transaction->getAction() instanceof CancelSponsorContentUnit) {
                            /**
                             * @var CancelSponsorContentUnit $cancelSponsorContentUnit
                             */
                            $cancelSponsorContentUnit = $transaction->getAction();

                            $boostTransactionHash = $cancelSponsorContentUnit->getTransactionHash();

                            if ($appliedReverted) {
                                $boostTransaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $boostTransactionHash]);

                                /**
                                 * @var BoostedContentUnit $boostedContentUnitEntity
                                 */
                                $boostedContentUnitEntity = $boostTransaction->getBoostedContentUnit();

                                $cancelBoostedContentUnitEntity = new CancelBoostedContentUnit();
                                $cancelBoostedContentUnitEntity->setBoostedContentUnit($boostedContentUnitEntity);
                                $this->em->persist($cancelBoostedContentUnitEntity);

                                $boostedContentUnitEntity->setCancelled(true);
                                $boostedContentUnitEntity->setCancelBoostedContentUnit($cancelBoostedContentUnitEntity);
                                $boostedContentUnitEntity->setEndTimePoint($timeSigned);
                                $this->em->persist($boostedContentUnitEntity);
                                $this->em->flush();

                                //  add transaction record without relation
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, null, null, null, $cancelBoostedContentUnitEntity);
                            } else {
                                $boostTransaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $boostTransactionHash]);

                                /**
                                 * @var BoostedContentUnit $boostedContentUnitEntity
                                 */
                                $boostedContentUnitEntity = $boostTransaction->getBoostedContentUnit();
                                $boostedContentUnitEntity->setCancelled(false);

                                $endTime = $boostedContentUnitEntity->getStartTimePoint() + $boostedContentUnitEntity->getHours() * 3600;
                                $boostedContentUnitEntity->setEndTimePoint($endTime);
                                $this->em->persist($boostedContentUnitEntity);
                                $this->em->flush();
                            }
                        }
                    }
                }

                if (is_array($appliedSponsorItems)) {
                    /**
                     * @var SponsorContentUnitApplied $appliedSponsorItem
                     */
                    foreach ($appliedSponsorItems as $appliedSponsorItem) {
                        $transactionHash = $appliedSponsorItem->getTransactionHash();
                        $amount = $appliedSponsorItem->getAmount();
                        $whole = $amount->getWhole();
                        $fraction = $amount->getFraction();

                        $transactionEntity = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $transactionHash]);
                        if ($transactionEntity) {
                            $boostedContentUnitEntity = $transactionEntity->getBoostedContentUnit();

                            $boostedContentUnitSpending = new BoostedContentUnitSpending();
                            $boostedContentUnitSpending->setBlock($block);
                            $boostedContentUnitSpending->setBoostedContentUnit($boostedContentUnitEntity);
                            $boostedContentUnitSpending->setWhole($whole);
                            $boostedContentUnitSpending->setFraction($fraction);
                            $this->em->persist($boostedContentUnitSpending);
                            $this->em->flush();
                        }
                    }
                }

                //  delete block with all data
                if (!$appliedReverted) {
                    $this->em->remove($block);
                    $this->em->flush();
                }
            } elseif ($action instanceof TransactionLog) {
                //  get transaction data
                $transactionHash = $action->getTransactionHash();
                $transactionSize = $action->getTransactionSize();
                $timeSigned = $action->getTimeSigned();
                $feeWhole = $action->getFee()->getWhole();
                $feeFraction = $action->getFee()->getFraction();

                if ($action->getAction() instanceof CancelSponsorContentUnit) {
                    /**
                     * @var CancelSponsorContentUnit $cancelSponsorContentUnit
                     */
                    $cancelSponsorContentUnit = $action->getAction();

                    $boostTransactionHash = $cancelSponsorContentUnit->getTransactionHash();

                    if ($appliedReverted) {
                        $boostTransaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $boostTransactionHash]);

                        /**
                         * @var BoostedContentUnit $boostedContentUnitEntity
                         */
                        $boostedContentUnitEntity = $boostTransaction->getBoostedContentUnit();

                        $cancelBoostedContentUnitEntity = new CancelBoostedContentUnit();
                        $cancelBoostedContentUnitEntity->setBoostedContentUnit($boostedContentUnitEntity);
                        $this->em->persist($cancelBoostedContentUnitEntity);

                        $boostedContentUnitEntity->setCancelled(true);
                        $boostedContentUnitEntity->setCancelBoostedContentUnit($cancelBoostedContentUnitEntity);
                        $boostedContentUnitEntity->setEndTimePoint($timeSigned);
                        $this->em->persist($boostedContentUnitEntity);
                        $this->em->flush();

                        //  add transaction record without relation
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, null, null, null, $cancelBoostedContentUnitEntity);
                    } else {
                        $boostTransaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $boostTransactionHash]);

                        /**
                         * @var BoostedContentUnit $boostedContentUnitEntity
                         */
                        $boostedContentUnitEntity = $boostTransaction->getBoostedContentUnit();
                        $boostedContentUnitEntity->setCancelled(false);

                        $endTime = $boostedContentUnitEntity->getStartTimePoint() + $boostedContentUnitEntity->getHours() * 3600;
                        $boostedContentUnitEntity->setEndTimePoint($endTime);
                        $this->em->persist($boostedContentUnitEntity);
                        $this->em->flush();
                    }
                }

                //  delete transaction with all data
                if (!$appliedReverted) {
                    $transaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $transactionHash]);
                    if ($transaction) {
                        $this->em->remove($transaction);
                        $this->em->flush();
                    }
                }
            } else {
                var_dump($action);
                exit();
            }
        }

        $this->em->flush();
        $this->em->clear();

        /**
         * @var IndexNumber $indexNumber
         */
        $indexNumber = $this->em->getRepository(IndexNumber::class)->findOneBy([], ['id' => 'DESC']);
        if (!$indexNumber) {
            $indexNumber = new IndexNumber();
        }
        $indexNumber->setId($index);
        $this->em->persist($indexNumber);
        $this->em->flush();

        $this->em->commit();

        $this->io->writeln(sprintf('Finished at with index=%s: %s', $index, date('Y-m-d H:i:s')));
        $this->io->success('BlockChain is synced now!');

        $this->release();

        return null;
    }

    /**
     * @param $block
     * @param $transactionHash
     * @param $transactionSize
     * @param $timeSigned
     * @param int $feeWhole
     * @param int $feeFraction
     * @param null $file
     * @param null $contentUnit
     * @param null $content
     * @param null $transfer
     * @param null $boostedContentUnit
     * @param null $cancelBoostedContentUnit
     * @return null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, $file = null, $contentUnit = null, $content = null, $transfer = null, $boostedContentUnit = null, $cancelBoostedContentUnit = null)
    {
        $transaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $transactionHash]);
        if ($transaction) {
            $this->em->remove($transaction);
            $this->em->flush();
        }

        $transaction = new Transaction();
        $transaction->setTransactionHash($transactionHash);
        $transaction->setFile(null);
        $transaction->setContentUnit(null);
        $transaction->setContent(null);
        $transaction->setTransfer(null);
        $transaction->setBoostedContentUnit(null);
        $transaction->setCancelBoostedContentUnit(null);

        if ($block) {
            $transaction->setBlock($block);
        }
        $transaction->setTransactionSize($transactionSize);
        $transaction->setTimeSigned($timeSigned);
        $transaction->setFeeWhole($feeWhole);
        $transaction->setFeeFraction($feeFraction);
        if ($file) {
            $transaction->setFile($file);
        }
        if ($contentUnit) {
            $transaction->setContentUnit($contentUnit);
        }
        if ($content) {
            $transaction->setContent($content);
        }
        if ($transfer) {
            $transaction->setTransfer($transfer);
        }
        if ($boostedContentUnit) {
            $transaction->setBoostedContentUnit($boostedContentUnit);
        }
        if ($cancelBoostedContentUnit) {
            $transaction->setCancelBoostedContentUnit($cancelBoostedContentUnit);
        }

        $this->em->persist($transaction);
        $this->em->flush();

        return null;
    }
}
