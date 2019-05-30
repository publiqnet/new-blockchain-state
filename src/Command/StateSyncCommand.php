<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/24/18
 * Time: 3:33 PM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\Block;
use App\Entity\IndexNumber;
use App\Entity\Reward;
use App\Entity\Transaction;
use App\Service\BlockChain;
use Doctrine\ORM\EntityManager;
use PubliqAPI\Base\LoggingType;
use PubliqAPI\Base\NodeType;
use PubliqAPI\Model\BlockLog;
use PubliqAPI\Model\ContentUnit;
use PubliqAPI\Model\File;
use PubliqAPI\Model\LoggedTransaction;
use PubliqAPI\Model\LoggedTransactions;
use PubliqAPI\Model\Content;
use PubliqAPI\Model\RewardLog;
use PubliqAPI\Model\Role;
use PubliqAPI\Model\TransactionLog;
use PubliqAPI\Model\Transfer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StateSyncCommand extends ContainerAwareCommand
{
    const BATCH = 100;
    const ACTION_COUNT = 5000;

    use LockableTrait;

    protected static $defaultName = 'state:sync-new-blockchain';

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
                $authority = $action->getAuthority();
                $blockHash = $action->getBlockHash();
                $blockNumber = $action->getBlockNumber();
                $signTime = $action->getTimeSigned();
                $size = $action->getBlockSize();
                $transactions = $action->getTransactions();
                $rewards = $action->getRewards();

                //  get authority account
                $authorityAccount = $this->checkAccount($authority);

                $block = $this->em->getRepository(Block::class)->findOneBy(['hash' => $blockHash]);
                if (!$block) {
                    $block = new Block();
                    $block->setAccount($authorityAccount);
                    $block->setHash($blockHash);
                    $block->setNumber($blockNumber);
                    $block->setSignTime($signTime);
                    $block->setSize($size);
                    $this->em->persist($block);
                    $this->em->flush();
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

                        if ($transaction->getAction() instanceof File) {
                            /**
                             * @var File $file
                             */
                            $file = $transaction->getAction();

                            //  get file data
                            $authorAddress = $file->getAuthorAddresses()[0];
                            $uri = $file->getUri();

                            //  create objects
                            $authorAccount = $this->checkAccount($authorAddress);

                            if ($appliedReverted) {
                                //  add file record
                                $fileEntity = new \App\Entity\File();
                                $fileEntity->setAuthor($authorAccount);
                                $fileEntity->setUri($uri);
                                $this->em->persist($fileEntity);
                                $this->em->flush();

                                //  add transaction record with relation to file
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, $fileEntity);

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, false);
                            } else {
                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, true);
                            }
                        } elseif ($transaction->getAction() instanceof ContentUnit) {
                            /**
                             * @var ContentUnit $contentUnit
                             */
                            $contentUnit = $transaction->getAction();

                            //  get content unit data
                            $uri = $contentUnit->getUri();
                            $contentId = $contentUnit->getContentId();
                            $authorAddress = $contentUnit->getAuthorAddresses()[0];
                            $channelAddress = $contentUnit->getChannelAddress();
                            $fileUris = $contentUnit->getFileUris();

                            //  get content unit data from storage
                            $storageData = $this->blockChainService->getContentUnitData($uri);
                            if ($storageData === null) {
                                $contentUnitTitle = 'Mismatch content title';
                                $contentUnitText = 'Mismatch content text';
                            } else {
                                if (strpos($storageData, '</h1>')) {
                                    $contentUnitTitle = strip_tags(substr($storageData, 0, strpos($storageData, '</h1>') + 5));
                                    $contentUnitText = substr($storageData, strpos($storageData, '</h1>') + 5);
                                } else {
                                    $contentUnitTitle = 'Old content without title';
                                    $contentUnitText = $storageData;
                                }
                            }

                            //  create objects
                            $authorAccount = $this->checkAccount($authorAddress);
                            $channelAccount = $this->checkAccount($channelAddress);

                            if ($appliedReverted) {
                                //  add file record
                                $contentUnitEntity = new \App\Entity\ContentUnit();
                                $contentUnitEntity->setUri($uri);
                                $contentUnitEntity->setContentId($contentId);
                                $contentUnitEntity->setAuthor($authorAccount);
                                $contentUnitEntity->setChannel($channelAccount);
                                $contentUnitEntity->setTitle($contentUnitTitle);
                                $contentUnitEntity->setText($contentUnitText);
                                foreach ($fileUris as $uri) {
                                    $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $uri]);
                                    $contentUnitEntity->addFile($fileEntity);
                                }
                                $this->em->persist($contentUnitEntity);
                                $this->em->flush();

                                //  add transaction record with relation to file
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, $contentUnitEntity);

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, false);
                            } else {
                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, true);
                            }
                        } elseif ($transaction->getAction() instanceof Content) {
                            /**
                             * @var Content $content
                             */
                            $content = $transaction->getAction();

                            //  get content data
                            $contentId = $content->getContentId();
                            $channelAddress = $content->getChannelAddress();
                            $contentUnitUris = $content->getContentUnitUris();

                            //  create channel object
                            $channelAccount = $this->checkAccount($channelAddress);

                            if ($appliedReverted) {
                                //  add content record
                                $contentEntity = new \App\Entity\Content();
                                $contentEntity->setContentId($contentId);
                                $contentEntity->setChannel($channelAccount);
                                foreach ($contentUnitUris as $uri) {
                                    $contentUnitEntity = $this->em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
                                    $contentUnitEntity->setContent($contentEntity);
                                    $this->em->persist($contentUnitEntity);
                                }
                                $this->em->persist($contentEntity);
                                $this->em->flush();

                                //  add transaction record with relation to content
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, $contentEntity);

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($channelAccount, $feeWhole, $feeFraction, false);
                            } else {
                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($channelAccount, $feeWhole, $feeFraction, true);
                            }
                        } elseif ($transaction->getAction() instanceof Transfer) {
                            /**
                             * @var Transfer $transfer
                             */
                            $transfer = $transaction->getAction();

                            //  get transfer data
                            $from = $transfer->getFrom();
                            $to = $transfer->getTo();
                            $whole = $transfer->getAmount()->getWhole();
                            $fraction = $transfer->getAmount()->getFraction();
                            $message = $transfer->getMessage();

                            //  create from/to objects
                            $fromAccount = $this->checkAccount($from);
                            $toAccount = $this->checkAccount($to);

                            if ($appliedReverted) {
                                //  add transfer record
                                $transferEntity = new \App\Entity\Transfer();
                                $transferEntity->setFrom($fromAccount);
                                $transferEntity->setTo($toAccount);
                                $transferEntity->setWhole($whole);
                                $transferEntity->setFraction($fraction);
                                $transferEntity->setMessage($message);
                                $this->em->persist($transferEntity);
                                $this->em->flush();

                                //  add transaction record with relation to transfer
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, null, $transferEntity);

                                //  update account balances
                                $this->updateAccountBalance($fromAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($fromAccount, $whole, $fraction, false);
                                $this->updateAccountBalance($toAccount, $whole, $fraction, true);
                            } else {
                                //  update account balances
                                $this->updateAccountBalance($fromAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($fromAccount, $whole, $fraction, true);
                                $this->updateAccountBalance($toAccount, $whole, $fraction, false);
                            }
                        } elseif ($transaction->getAction() instanceof Role) {
                            /**
                             * @var Role $role
                             */
                            $role = $transaction->getAction();

                            //  get role data
                            $nodeAddress = $role->getNodeAddress();
                            $nodeType = $role->getNodeType();

                            $nodeAccount = $this->checkAccount($nodeAddress);

                            if ($appliedReverted) {
                                if ($nodeType == NodeType::channel) {
                                    $nodeAccount->setChannel(true);
                                } elseif ($nodeType == NodeType::storage) {
                                    $nodeAccount->setStorage(true);
                                } elseif ($nodeType == NodeType::blockchain) {
                                    $nodeAccount->setBlockchain(true);
                                }
                                $this->em->persist($nodeAccount);
                                $this->em->flush();

                                //  add transaction record with relation to content
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction);

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($nodeAccount, $feeWhole, $feeFraction, false);
                            } else {
                                if ($nodeType == NodeType::channel) {
                                    $nodeAccount->setChannel(false);
                                } elseif ($nodeType == NodeType::storage) {
                                    $nodeAccount->setStorage(false);
                                } elseif ($nodeType == NodeType::blockchain) {
                                    $nodeAccount->setBlockchain(false);
                                }
                                $this->em->persist($nodeAccount);
                                $this->em->flush();

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($nodeAccount, $feeWhole, $feeFraction, true);
                            }
                        }
                    }
                }

                if (is_array($rewards)) {
                    /**
                     * @var RewardLog $reward
                     */
                    foreach ($rewards as $reward) {
                        $to = $reward->getTo();
                        $whole = $reward->getAmount()->getWhole();
                        $fraction = $reward->getAmount()->getFraction();
                        $rewardType = $reward->getRewardType();

                        $toAccount = $this->checkAccount($to);

                        if ($appliedReverted) {
                            $this->addReward($block, $toAccount, $whole, $fraction, $rewardType);
                            $this->updateAccountBalance($toAccount, $whole, $fraction, true);
                        } else {
                            $this->updateAccountBalance($toAccount, $whole, $fraction, false);
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

                if ($action->getAction() instanceof File) {
                    /**
                     * @var File $file
                     */
                    $file = $action->getAction();

                    //  get file data
                    $authorAddress = $file->getAuthorAddresses()[0];
                    $uri = $file->getUri();

                    //  create objects
                    $authorAccount = $this->checkAccount($authorAddress);

                    if ($appliedReverted) {
                        //  add file record
                        $fileEntity = new \App\Entity\File();
                        $fileEntity->setAuthor($authorAccount);
                        $fileEntity->setUri($uri);
                        $this->em->persist($fileEntity);
                        $this->em->flush();

                        //  add transaction record with relation to file
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, $fileEntity);

                        //  update account balances
                        $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, false);
                    } else {
                        //  update account balances
                        $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, true);
                    }
                } elseif ($action->getAction() instanceof ContentUnit) {
                    /**
                     * @var ContentUnit $contentUnit
                     */
                    $contentUnit = $action->getAction();

                    //  get content unit data
                    $uri = $contentUnit->getUri();
                    $contentId = $contentUnit->getContentId();
                    $authorAddress = $contentUnit->getAuthorAddresses()[0];
                    $channelAddress = $contentUnit->getChannelAddress();
                    $fileUris = $contentUnit->getFileUris();

                    //  get content unit data from storage
                    $storageData = $this->blockChainService->getContentUnitData($uri);
                    if (strpos($storageData, '</h1>')) {
                        $contentUnitTitle = strip_tags(substr($storageData, 0, strpos($storageData, '</h1>') + 5));
                        $contentUnitText = substr($storageData, strpos($storageData, '</h1>') + 5);
                    } else {
                        $contentUnitTitle = 'Old content without title';
                        $contentUnitText = $storageData;
                    }

                    //  create objects
                    $authorAccount = $this->checkAccount($authorAddress);
                    $channelAccount = $this->checkAccount($channelAddress);

                    if ($appliedReverted) {
                        //  add file record
                        $contentUnitEntity = new \App\Entity\ContentUnit();
                        $contentUnitEntity->setUri($uri);
                        $contentUnitEntity->setContentId($contentId);
                        $contentUnitEntity->setAuthor($authorAccount);
                        $contentUnitEntity->setChannel($channelAccount);
                        $contentUnitEntity->setTitle($contentUnitTitle);
                        $contentUnitEntity->setText($contentUnitText);
                        foreach ($fileUris as $uri) {
                            $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $uri]);
                            $contentUnitEntity->addFile($fileEntity);
                        }
                        $this->em->persist($contentUnitEntity);
                        $this->em->flush();

                        //  add transaction record with relation to file
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, $contentUnitEntity);

                        //  update account balances
                        $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, false);
                    } else {
                        //  update account balances
                        $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, true);
                    }
                } elseif ($action->getAction() instanceof Content) {
                    /**
                     * @var Content $content
                     */
                    $content = $action->getAction();

                    //  get content data
                    $contentId = $content->getContentId();
                    $channelAddress = $content->getChannelAddress();
                    $contentUnitUris = $content->getContentUnitUris();

                    //  create channel object
                    $channelAccount = $this->checkAccount($channelAddress);

                    if ($appliedReverted) {
                        //  add content record
                        $contentEntity = new \App\Entity\Content();
                        $contentEntity->setContentId($contentId);
                        $contentEntity->setChannel($channelAccount);
                        $this->em->persist($contentEntity);
                        $this->em->flush();

                        foreach ($contentUnitUris as $uri) {
                            $contentUnitEntity = $this->em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
                            $contentUnitEntity->setContent($contentEntity);
                            $this->em->persist($contentUnitEntity);
                        }
                        $this->em->flush();

                        //  add transaction record with relation to content
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, $contentEntity);

                        //  update account balances
                        $this->updateAccountBalance($channelAccount, $feeWhole, $feeFraction, false);
                    } else {
                        $transaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $transactionHash]);
                        $contentUnits = $this->em->getRepository(\App\Entity\ContentUnit::class)->findBy(['content' => $transaction->getContent()]);
                        if ($contentUnits) {
                            foreach ($contentUnits as $contentUnit) {
                                $contentUnit->setContent(null);
                                $this->em->persist($contentUnit);
                            }
                            $this->em->flush();
                        }

                        //  update account balances
                        $this->updateAccountBalance($channelAccount, $feeWhole, $feeFraction, true);
                    }
                } elseif ($action->getAction() instanceof Transfer) {
                    /**
                     * @var Transfer $transfer
                     */
                    $transfer = $action->getAction();

                    $from = $transfer->getFrom();
                    $to = $transfer->getTo();
                    $whole = $transfer->getAmount()->getWhole();
                    $fraction = $transfer->getAmount()->getFraction();
                    $message = $transfer->getMessage();

                    //  create from/to objects
                    $fromAccount = $this->checkAccount($from);
                    $toAccount = $this->checkAccount($to);

                    if ($appliedReverted) {
                        //  add transfer record
                        $transferEntity = new \App\Entity\Transfer();
                        $transferEntity->setFrom($fromAccount);
                        $transferEntity->setTo($toAccount);
                        $transferEntity->setWhole($whole);
                        $transferEntity->setFraction($fraction);
                        $transferEntity->setMessage($message);
                        $this->em->persist($transferEntity);
                        $this->em->flush();

                        //  add transaction record with relation to transfer
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, null, $transferEntity);

                        //  update account balances
                        $this->updateAccountBalance($fromAccount, $feeWhole, $feeFraction, false);
                        $this->updateAccountBalance($fromAccount, $whole, $fraction, false);
                        $this->updateAccountBalance($toAccount, $whole, $fraction, true);
                    } else {
                        //  update account balances
                        $this->updateAccountBalance($fromAccount, $feeWhole, $feeFraction, true);
                        $this->updateAccountBalance($fromAccount, $whole, $fraction, true);
                        $this->updateAccountBalance($toAccount, $whole, $fraction, false);
                    }
                } elseif ($action->getAction() instanceof Role) {
                    /**
                     * @var Role $role
                     */
                    $role = $action->getAction();

                    //  get role data
                    $nodeAddress = $role->getNodeAddress();
                    $nodeType = $role->getNodeType();

                    $nodeAccount = $this->checkAccount($nodeAddress);

                    if ($appliedReverted) {
                        if ($nodeType == NodeType::channel) {
                            $nodeAccount->setChannel(true);
                        } elseif ($nodeType == NodeType::storage) {
                            $nodeAccount->setStorage(true);
                        } elseif ($nodeType == NodeType::blockchain) {
                            $nodeAccount->setBlockchain(true);
                        }
                        $this->em->persist($nodeAccount);
                        $this->em->flush();

                        //  add transaction record with relation to content
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction);

                        //  update account balances
                        $this->updateAccountBalance($nodeAccount, $feeWhole, $feeFraction, false);
                    } else {
                        if ($nodeType == NodeType::channel) {
                            $nodeAccount->setChannel(false);
                        } elseif ($nodeType == NodeType::storage) {
                            $nodeAccount->setStorage(false);
                        } elseif ($nodeType == NodeType::blockchain) {
                            $nodeAccount->setBlockchain(false);
                        }
                        $this->em->persist($nodeAccount);
                        $this->em->flush();

                        //  update account balances
                        $this->updateAccountBalance($nodeAccount, $feeWhole, $feeFraction, true);
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

        //  update accounts balances
        foreach ($this->balances as $address => $value) {
            $account = $this->checkAccount($address);

            $whole = $value['whole'];
            $fraction = $value['fraction'];

            if ($fraction > 99999999) {
                while ($fraction > 99999999) {
                    $whole++;
                    $fraction -= 100000000;
                }
            } elseif ($fraction < 0) {
                while ($fraction < 0) {
                    $whole--;
                    $fraction += 100000000;
                }
            }

            $account->setWhole($whole);
            $account->setFraction($fraction);

            $this->em->persist($account);
        }

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
        $account = $this->em->getRepository(Account::class)->findOneBy(['address' => $address]);
        if (!$account) {
            $account = new Account();
            $account->setAddress($address);
            $account->setWhole(0);
            $account->setFraction(0);

            $this->em->persist($account);
            $this->em->flush();
        }

        return $account;
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
     * @return null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, $file = null, $contentUnit = null, $content = null, $transfer = null)
    {
        $transaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $transactionHash]);
        if (!$transaction) {
            $transaction = new Transaction();

            if ($block) {
                $transaction->setBlock($block);
            }
            $transaction->setTransactionHash($transactionHash);
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

            $this->em->persist($transaction);
            $this->em->flush();
        }

        return null;
    }

    /**
     * @param $block
     * @param $toAccount
     * @param $whole
     * @param $fraction
     * @param $rewardType
     * @return null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function addReward($block, Account $toAccount, $whole, $fraction, $rewardType)
    {
        $reward = new Reward();

        $reward->setBlock($block);
        $reward->setTo($toAccount);
        $reward->setWhole($whole);
        $reward->setFraction($fraction);
        $reward->setRewardType($rewardType);

        $this->em->persist($reward);
        $this->em->flush();

        return null;
    }

    private function updateAccountBalance(Account $account, int $whole, int $fraction, bool $add)
    {
        $address = $account->getAddress();
        $sign = $add ? 1 : -1;

        if (!array_key_exists($account->getAddress(), $this->balances)) {
            $this->balances[$address]['whole'] = $account->getWhole();
            $this->balances[$address]['fraction'] = $account->getFraction();
        }
        $this->balances[$address]['whole'] += $whole * $sign;
        $this->balances[$address]['fraction'] += $fraction * $sign;
    }
}
