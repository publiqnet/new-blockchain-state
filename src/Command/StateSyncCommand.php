<?php
/**
 * Created by PhpStorm.
 * User: grigor
 * Date: 9/24/18
 * Time: 3:33 PM
 */

namespace App\Command;

use App\Entity\Account;
use App\Entity\AccountContentUnit;
use App\Entity\AccountExchange;
use App\Entity\AccountFile;
use App\Entity\Block;
use App\Entity\BoostedContentUnit;
use App\Entity\BoostedContentUnitSpending;
use App\Entity\CancelBoostedContentUnit;
use App\Entity\CanonicalUrl;
use App\Entity\ContentUnitTag;
use App\Entity\ContentUnitViews;
use App\Entity\IndexNumber;
use App\Entity\PublicationArticle;
use App\Entity\Reward;
use App\Entity\Tag;
use App\Entity\Transaction;
use App\Event\ArticleBoostedByOtherEvent;
use App\Event\ArticleNewEvent;
use App\Event\ArticleShareEvent;
use App\Event\ExchangeCompletedEvent;
use App\Service\BlockChain;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PubliqAPI\Base\LoggingType;
use PubliqAPI\Base\NodeType;
use PubliqAPI\Base\UpdateType;
use PubliqAPI\Model\BlockLog;
use PubliqAPI\Model\CancelSponsorContentUnit;
use PubliqAPI\Model\ContentUnit;
use PubliqAPI\Model\ContentUnitImpactLog;
use PubliqAPI\Model\ContentUnitImpactPerChannel;
use PubliqAPI\Model\File;
use PubliqAPI\Model\LoggedTransaction;
use PubliqAPI\Model\LoggedTransactions;
use PubliqAPI\Model\Content;
use PubliqAPI\Model\RewardLog;
use PubliqAPI\Model\Role;
use PubliqAPI\Model\ServiceStatistics;
use PubliqAPI\Model\SponsorContentUnit;
use PubliqAPI\Model\SponsorContentUnitApplied;
use PubliqAPI\Model\StorageUpdate;
use PubliqAPI\Model\TransactionLog;
use PubliqAPI\Model\Transfer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class StateSyncCommand extends Command
{
    const BATCH = 100;
    const ACTION_COUNT = 5000;

    use LockableTrait;

    protected static $defaultName = 'state:sync-new-blockchain';

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;

    /** @var \App\Service\Custom $customService */
    private $customService;

    /** @var EventDispatcherInterface $eventDispatcher */
    private $eventDispatcher;

    /** @var Container] $container */
    private $container;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;

    /** @var array $balances */
    private $balances = [];


    public function __construct(EntityManagerInterface $em, BlockChain $blockChain, Custom $custom, EventDispatcherInterface $eventDispatcher, ContainerInterface $container)
    {
        parent::__construct();

        $this->em = $em;
        $this->blockChainService = $blockChain;
        $this->customService = $custom;
        $this->eventDispatcher = $eventDispatcher;
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setDescription('Sync state database with BlockChain');
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

        //  delete all temporary transactions
        /**
         * @var Transaction[] $transactions
         */
        $transactions = $this->em->getRepository(Transaction::class)->findBy(['transactionSize' => 0], ['timeSigned' => 'DESC', 'id' => 'DESC']);
        if ($transactions) {
            foreach ($transactions as $transaction) {
                if ($transaction->getFile()) {
                    /**
                     * @var \App\Entity\File $fileEntity
                     */
                    $fileEntity = $transaction->getFile();
                    $fileEntity->setTransaction(null);
                    $this->em->persist($fileEntity);
                    $this->em->flush();
                }

                $this->em->remove($transaction);
                $this->em->flush();
            }
        }

        //  get FOCUS channel
        $focusChannel = $this->em->getRepository(Account::class)->findOneBy(['publicKey' => $this->container->getParameter('focccus_channels_addresses')]);

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
                $unitUriImpacts = $action->getUnitUriImpacts();
                $appliedSponsorItems = $action->getAppliedSponsorItems();

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
                            $authorAddresses = $file->getAuthorAddresses();
                            $uri = $file->getUri();

                            if ($appliedReverted) {
                                //  add file record
                                $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $uri]);
                                if (!$fileEntity) {
                                    $fileEntity = new \App\Entity\File();
                                    $fileEntity->setUri($uri);
                                } else {
                                    $fileTransaction = $this->em->getRepository(Transaction::class)->findOneBy(['file' => $fileEntity]);
                                    if ($fileTransaction) {
                                        $fileTransaction->setTransactionHash($transactionHash);
                                        $this->em->persist($fileTransaction);
                                        $this->em->flush();
                                    }
                                }
                                $fileEntity->setTransaction(null);
                                $this->em->persist($fileEntity);
                                $this->em->flush();

                                foreach ($authorAddresses as $authorAddress) {
                                    $authorAccount = $this->checkAccount($authorAddress);

                                    $accountFileEntity = $this->em->getRepository(AccountFile::class)->findOneBy(['account' => $authorAccount, 'file' => $fileEntity]);
                                    if (!$accountFileEntity) {
                                        $accountFileEntity = new AccountFile();
                                        $accountFileEntity->setAccount($authorAccount);
                                        $accountFileEntity->setFile($fileEntity);
                                    }
                                    $accountFileEntity->setSigned(true);
                                    $this->em->persist($accountFileEntity);
                                }

                                //  add transaction record with relation to file
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, $fileEntity);

                                //  update account balances
                                $authorAccount = $this->checkAccount($authorAddresses[0]);
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, false);
                            } else {
                                //  update account balances
                                $authorAccount = $this->checkAccount($authorAddresses[0]);
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
                            $authorAddresses = $contentUnit->getAuthorAddresses();
                            $channelAddress = $contentUnit->getChannelAddress();
                            $fileUris = $contentUnit->getFileUris();
                            $fileUris = array_unique($fileUris);
                            $coverUri = null;

                            $channelAccount = $this->checkAccount($channelAddress);

                            //  get content unit data from storage
                            $contentUnitTitle = 'Unknown';
                            $contentUnitText = null;
                            if ($channelAccount->getUrl()) {
                                try {
                                    $storageData = file_get_contents($channelAccount->getUrl() . '/storage?file=' . $uri);
                                    if (!mb_check_encoding($storageData, 'UTF-8')) {
                                        $storageData = utf8_encode($storageData);
                                    }

                                    if ($storageData) {
                                        if (strpos($storageData, '</h1>')) {
                                            if (strpos($storageData, '<h1>') > 0) {
                                                $coverPart = substr($storageData, 0, strpos($storageData, '<h1>'));
                                                $coverPart = substr($coverPart, strpos($coverPart,'src="') + 5);
                                                $coverUri = substr($coverPart, 0, strpos($coverPart, '"'));
                                            }
                                            $contentUnitTitle = trim(strip_tags(substr($storageData, 0, strpos($storageData, '</h1>') + 5)));
                                            $contentUnitText = substr($storageData, strpos($storageData, '</h1>') + 5);
                                        } else {
                                            $contentUnitText = $storageData;
                                        }
                                    }
                                } catch (\Exception $e) {
                                    //  do nothing to continue sync
                                }
                            }

                            if ($appliedReverted) {
                                //  add content unit record
                                $contentUnitEntity = $this->em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
                                if (!$contentUnitEntity) {
                                    $contentUnitEntity = new \App\Entity\ContentUnit();
                                    $contentUnitEntity->setUri($uri);
                                }
                                $contentUnitEntity->setContentId($contentId);
                                $contentUnitEntity->setChannel($channelAccount);
                                $contentUnitEntity->setTitle($contentUnitTitle);
                                $contentUnitEntity->setText($contentUnitText);
                                $contentUnitEntity->setTextWithData($contentUnitText);
                                foreach ($fileUris as $fileUri) {
                                    $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $fileUri]);
                                    $contentUnitEntity->addFile($fileEntity);
                                }
                                if ($coverUri) {
                                    $coverFileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $coverUri]);
                                    if ($coverFileEntity) {
                                        $contentUnitEntity->setCover($coverFileEntity);
                                    } else {
                                        $contentUnitEntity->setCoverExternalUrl($coverUri);
                                    }
                                }

                                //  check for related Publication
                                $publicationArticle = $this->em->getRepository(PublicationArticle::class)->findOneBy(['uri' => $uri]);
                                if ($publicationArticle) {
                                    $contentUnitEntity->setPublication($publicationArticle->getPublication());
                                    $this->em->remove($publicationArticle);
                                }

                                //  check for canonical URL
                                $canonicalUrl = $this->em->getRepository(CanonicalUrl::class)->findOneBy(['uri' => $uri]);
                                if ($canonicalUrl) {
                                    $contentUnitEntity->setCanonicalUrl($canonicalUrl->getUrl());
                                }

                                $this->em->persist($contentUnitEntity);

                                foreach ($authorAddresses as $authorAddress) {
                                    $authorAccount = $this->checkAccount($authorAddress);

                                    $accountContentUnitEntity = $this->em->getRepository(AccountContentUnit::class)->findOneBy(['account' => $authorAccount, 'contentUnit' => $contentUnitEntity]);
                                    if (!$accountContentUnitEntity) {
                                        $accountContentUnitEntity = new AccountContentUnit();
                                        $accountContentUnitEntity->setAccount($authorAccount);
                                        $accountContentUnitEntity->setContentUnit($contentUnitEntity);
                                    }
                                    $accountContentUnitEntity->setSigned(true);
                                    $this->em->persist($accountContentUnitEntity);
                                }

                                $this->em->flush();

                                //  check for related tags
                                $contentUnitTags = $this->em->getRepository(ContentUnitTag::class)->findBy(['contentUnitUri' => $uri]);
                                if ($contentUnitTags) {
                                    foreach ($contentUnitTags as $contentUnitTag) {
                                        $contentUnitTag->setContentUnit($contentUnitEntity);
                                        $this->em->persist($contentUnitTag);
                                    }

                                    $this->em->flush();
                                } elseif ($channelAccount == $focusChannel) {
                                    //  GET TAGS
                                    while (strpos($contentUnitText, '</h3>')){
                                        $contentUnitTag = trim(strip_tags(substr($contentUnitText, 0, strpos($contentUnitText, '</h3>') + 5)));
                                        $contentUnitText = substr($contentUnitText, strpos($contentUnitText, '</h3>') + 5);

                                        $contentUnitTag = explode(' ', $contentUnitTag);
                                        foreach ($contentUnitTag as $contentUnitTagSingle) {
                                            $tagEntity = $this->em->getRepository(Tag::class)->findOneBy(['name' => $contentUnitTagSingle]);
                                            if (!$tagEntity) {
                                                $tagEntity = new Tag();
                                                $tagEntity->setName($contentUnitTagSingle);
                                                $this->em->persist($tagEntity);
                                                $this->em->flush();
                                            }

                                            $contentUnitTagEntity = new ContentUnitTag();
                                            $contentUnitTagEntity->setContentUnit($contentUnitEntity);
                                            $contentUnitTagEntity->setContentUnitUri($contentUnitEntity->getUri());
                                            $contentUnitTagEntity->setTag($tagEntity);
                                            $this->em->persist($contentUnitTagEntity);
                                            $this->em->flush();
                                        }
                                    }
                                }

                                //  add transaction record with relation to content unit
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, $contentUnitEntity);

                                //  update account balances
                                $authorAccount = $this->checkAccount($authorAddresses[0]);
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, false);
                            } else {
                                //  check for related tags
                                $contentUnitTags = $this->em->getRepository(ContentUnitTag::class)->findBy(['contentUnitUri' => $uri]);
                                if ($contentUnitTags) {
                                    foreach ($contentUnitTags as $contentUnitTag) {
                                        $contentUnitTag->setContentUnit(null);
                                        $this->em->persist($contentUnitTag);
                                    }

                                    $this->em->flush();
                                }

                                //  update account balances
                                $authorAccount = $this->checkAccount($authorAddresses[0]);
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

                                //  add transaction record without relation
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
                        } elseif ($transaction->getAction() instanceof StorageUpdate) {
                            /**
                             * @var StorageUpdate $storageUpdate
                             */
                            $storageUpdate = $transaction->getAction();

                            $status = $storageUpdate->getStatus();
                            $storageAddress = $storageUpdate->getStorageAddress();
                            $fileUri = $storageUpdate->getFileUri();

                            $storageAddressAccount = $this->checkAccount($storageAddress);

                            if ($appliedReverted) {
                                $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $fileUri]);
                                if ($status == UpdateType::store) {
                                    $storageAddressAccount->addStorageFile($fileEntity);
                                } else {
                                    $storageAddressAccount->removeStorageFile($fileEntity);
                                }
                                $this->em->persist($storageAddressAccount);
                                $this->em->flush();

                                //  add transaction record without relation
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction);

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($storageAddressAccount, $feeWhole, $feeFraction, false);
                            } else {
                                $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $fileUri]);
                                if ($status == UpdateType::store) {
                                    $storageAddressAccount->removeStorageFile($fileEntity);
                                } else {
                                    $storageAddressAccount->addStorageFile($fileEntity);
                                }
                                $this->em->persist($storageAddressAccount);
                                $this->em->flush();

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($storageAddressAccount, $feeWhole, $feeFraction, true);
                            }
                        } elseif ($transaction->getAction() instanceof ServiceStatistics) {
                            /**
                             * @var ServiceStatistics $serviceStatistics
                             */
                            $serviceStatistics = $transaction->getAction();

                            $serverAddress = $serviceStatistics->getServerAddress();
                            $serverAddressAccount = $this->checkAccount($serverAddress);

                            if ($appliedReverted) {
                                //  add transaction record without relation
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction);

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($serverAddressAccount, $feeWhole, $feeFraction, false);
                            } else {
                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($serverAddressAccount, $feeWhole, $feeFraction, true);
                            }
                        } elseif ($transaction->getAction() instanceof SponsorContentUnit) {
                            /**
                             * @var SponsorContentUnit $sponsorContentUnit
                             */
                            $sponsorContentUnit = $transaction->getAction();

                            $sponsorAddress = $sponsorContentUnit->getSponsorAddress();
                            $uri = $sponsorContentUnit->getUri();
                            $startTimePoint = $sponsorContentUnit->getStartTimePoint();
                            $hours = $sponsorContentUnit->getHours();
                            $whole = $sponsorContentUnit->getAmount()->getWhole();
                            $fraction = $sponsorContentUnit->getAmount()->getFraction();

                            $sponsorAddressAccount = $this->checkAccount($sponsorAddress);

                            if ($appliedReverted) {
                                $contentUnitEntity = $this->em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);

                                $boostedContentUnitEntity = new BoostedContentUnit();
                                $boostedContentUnitEntity->setSponsor($sponsorAddressAccount);
                                $boostedContentUnitEntity->setContentUnit($contentUnitEntity);
                                $boostedContentUnitEntity->setStartTimePoint($startTimePoint);
                                $boostedContentUnitEntity->setHours($hours);
                                $boostedContentUnitEntity->setWhole($whole);
                                $boostedContentUnitEntity->setFraction($fraction);
                                $boostedContentUnitEntity->setEndTimePoint($startTimePoint + $hours * 3600);
                                $this->em->persist($boostedContentUnitEntity);
                                $this->em->flush();

                                //  add transaction record without relation
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, null, null, $boostedContentUnitEntity);

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($sponsorAddressAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($sponsorAddressAccount, $whole, $fraction, false);
                            } else {
                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($sponsorAddressAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($sponsorAddressAccount, $whole, $fraction, true);
                            }
                        } elseif ($transaction->getAction() instanceof CancelSponsorContentUnit) {
                            /**
                             * @var CancelSponsorContentUnit $cancelSponsorContentUnit
                             */
                            $cancelSponsorContentUnit = $transaction->getAction();

                            $boostTransactionHash = $cancelSponsorContentUnit->getTransactionHash();
                            $sponsorAddress = $cancelSponsorContentUnit->getSponsorAddress();

                            $sponsorAddressAccount = $this->checkAccount($sponsorAddress);
                            $boostTransaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $boostTransactionHash]);

                            if ($appliedReverted) {
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

                                /**
                                 * @var \App\Entity\ContentUnit $contentUnitEntity
                                 */
                                $contentUnitEntity = $boostedContentUnitEntity->getContentUnit();
                                $isBoosted = $this->em->getRepository(BoostedContentUnit::class)->isContentUnitBoosted($contentUnitEntity);
                                if (!$isBoosted) {
                                    $contentUnitEntity->setHighlight(false);
                                }

                                //  add transaction record without relation
                                $this->addTransaction($block, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, null, null, null, $cancelBoostedContentUnitEntity);

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, true);
                                $this->updateAccountBalance($sponsorAddressAccount, $feeWhole, $feeFraction, false);
                            } else {
                                /**
                                 * @var BoostedContentUnit $boostedContentUnitEntity
                                 */
                                $boostedContentUnitEntity = $boostTransaction->getBoostedContentUnit();
                                $boostedContentUnitEntity->setCancelled(false);

                                $endTime = $boostedContentUnitEntity->getStartTimePoint() + $boostedContentUnitEntity->getHours() * 3600;
                                $boostedContentUnitEntity->setEndTimePoint($endTime);
                                $this->em->persist($boostedContentUnitEntity);
                                $this->em->flush();

                                //  update account balances
                                $this->updateAccountBalance($authorityAccount, $feeWhole, $feeFraction, false);
                                $this->updateAccountBalance($sponsorAddressAccount, $feeWhole, $feeFraction, true);
                            }
                        } else {
                            var_dump($transaction->getAction());
                            exit();
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

                if (is_array($unitUriImpacts)) {
                    /**
                     * @var ContentUnitImpactLog $unitUriImpact
                     */
                    foreach ($unitUriImpacts as $unitUriImpact) {
                        $contentUnitUri = $unitUriImpact->getContentUnitUri();
                        $contentUnitEntity = $this->em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $contentUnitUri]);
                        if ($contentUnitEntity) {
                            $viewCount = 0;
                            /**
                             * @var ContentUnitImpactPerChannel[] $viewsPerChannels
                             */
                            $viewsPerChannels = $unitUriImpact->getViewsPerChannel();
                            foreach ($viewsPerChannels as $viewsPerChannel) {
                                $viewCount += $viewsPerChannel->getViewCount();

                                $views = $viewsPerChannel->getViewCount();
                                $channelAddress = $viewsPerChannel->getChannelAddress();

                                //  create channel object
                                $channelAccount = $this->checkAccount($channelAddress);

                                if ($appliedReverted) {
                                    $contentUnitViews = new ContentUnitViews();
                                    $contentUnitViews->setChannel($channelAccount);
                                    $contentUnitViews->setContentUnit($contentUnitEntity);
                                    $contentUnitViews->setBlock($block);
                                    $contentUnitViews->setViewsCount($views);
                                    $contentUnitViews->setViewsTime($block->getSignTime());
                                    $this->em->persist($contentUnitViews);
                                    $this->em->flush();
                                }
                            }

                            if ($appliedReverted) {
                                $contentUnitEntity->plusViews($viewCount);
                            } else {
                                $contentUnitEntity->minusViews($viewCount);
                            }

                            $this->em->persist($contentUnitEntity);
                            $this->em->flush();
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

                if ($action->getAction() instanceof File) {
                    /**
                     * @var File $file
                     */
                    $file = $action->getAction();

                    //  get file data
                    $authorAddresses = $file->getAuthorAddresses();
                    $uri = $file->getUri();

                    if ($appliedReverted) {
                        //  add file record
                        $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $uri]);
                        if (!$fileEntity) {
                            $fileEntity = new \App\Entity\File();
                            $fileEntity->setUri($uri);
                        }

                        foreach ($authorAddresses as $authorAddress) {
                            $authorAccount = $this->checkAccount($authorAddress);

                            $accountFileEntity = $this->em->getRepository(AccountFile::class)->findOneBy(['account' => $authorAccount, 'file' => $fileEntity]);
                            if (!$accountFileEntity) {
                                $accountFileEntity = new AccountFile();
                                $accountFileEntity->setAccount($authorAccount);
                                $accountFileEntity->setFile($fileEntity);
                            }
                            $accountFileEntity->setSigned(true);
                            $this->em->persist($accountFileEntity);
                        }

                        $fileEntity->setTransaction(null);
                        $this->em->persist($fileEntity);
                        $this->em->flush();

                        //  add transaction record with relation to file
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, $fileEntity);

                        //  update account balances
                        $authorAccount = $this->checkAccount($authorAddresses[0]);
                        $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, false);
                    } else {
                        //  update account balances
                        $authorAccount = $this->checkAccount($authorAddresses[0]);
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
                    $authorAddresses = $contentUnit->getAuthorAddresses();
                    $channelAddress = $contentUnit->getChannelAddress();
                    $fileUris = $contentUnit->getFileUris();
                    $fileUris = array_unique($fileUris);
                    $coverUri = null;

                    $channelAccount = $this->checkAccount($channelAddress);

                    //  get content unit data from storage
                    $contentUnitTitle = 'Unknown';
                    $contentUnitText = null;
                    if ($channelAccount->getUrl()) {
                        try {
                            $storageData = file_get_contents($channelAccount->getUrl() . '/storage?file=' . $uri);
                            if (!mb_check_encoding($storageData, 'UTF-8')) {
                                $storageData = utf8_encode($storageData);
                            }

                            if ($storageData) {
                                if (strpos($storageData, '</h1>')) {
                                    if (strpos($storageData, '<h1>') > 0) {
                                        $coverPart = substr($storageData, 0, strpos($storageData, '<h1>'));
                                        $coverPart = substr($coverPart, strpos($coverPart,'src="') + 5);
                                        $coverUri = substr($coverPart, 0, strpos($coverPart, '"'));
                                    }
                                    $contentUnitTitle = trim(strip_tags(substr($storageData, 0, strpos($storageData, '</h1>') + 5)));
                                    $contentUnitText = substr($storageData, strpos($storageData, '</h1>') + 5);
                                } else {
                                    $contentUnitText = $storageData;
                                }
                            }
                        } catch (\Exception $e) {
                            //  do nothing to continue sync
                        }
                    }

                    if ($appliedReverted) {
                        //  add ContentUnit record
                        $contentUnitEntity = $this->em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
                        if (!$contentUnitEntity) {
                            $contentUnitEntity = new \App\Entity\ContentUnit();
                            $contentUnitEntity->setUri($uri);
                        }
                        $contentUnitEntity->setContentId($contentId);
                        $contentUnitEntity->setChannel($channelAccount);
                        $contentUnitEntity->setTitle($contentUnitTitle);
                        $contentUnitEntity->setText($contentUnitText);
                        $contentUnitEntity->setTextWithData($contentUnitText);
                        foreach ($fileUris as $fileUri) {
                            $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $fileUri]);
                            $contentUnitEntity->addFile($fileEntity);
                        }
                        if ($coverUri) {
                            $coverFileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $coverUri]);
                            if ($coverFileEntity) {
                                $contentUnitEntity->setCover($coverFileEntity);
                            } else {
                                $contentUnitEntity->setCoverExternalUrl($coverUri);
                            }
                        }

                        //  check for related Publication
                        $publicationArticle = $this->em->getRepository(PublicationArticle::class)->findOneBy(['uri' => $uri]);
                        if ($publicationArticle) {
                            $contentUnitEntity->setPublication($publicationArticle->getPublication());
                        }

                        //  check for canonical URL
                        $canonicalUrl = $this->em->getRepository(CanonicalUrl::class)->findOneBy(['uri' => $uri]);
                        if ($canonicalUrl) {
                            $contentUnitEntity->setCanonicalUrl($canonicalUrl->getUrl());
                        }

                        $this->em->persist($contentUnitEntity);

                        foreach ($authorAddresses as $authorAddress) {
                            $authorAccount = $this->checkAccount($authorAddress);

                            $accountContentUnitEntity = $this->em->getRepository(AccountContentUnit::class)->findOneBy(['account' => $authorAccount, 'contentUnit' => $contentUnitEntity]);
                            if (!$accountContentUnitEntity) {
                                $accountContentUnitEntity = new AccountContentUnit();
                                $accountContentUnitEntity->setAccount($authorAccount);
                                $accountContentUnitEntity->setContentUnit($contentUnitEntity);
                            }
                            $accountContentUnitEntity->setSigned(true);
                            $this->em->persist($accountContentUnitEntity);
                        }

                        $this->em->flush();

                        //  check for related tags
                        $contentUnitTags = $this->em->getRepository(ContentUnitTag::class)->findBy(['contentUnitUri' => $uri]);
                        if ($contentUnitTags) {
                            foreach ($contentUnitTags as $contentUnitTag) {
                                $contentUnitTag->setContentUnit($contentUnitEntity);
                                $this->em->persist($contentUnitTag);
                            }

                            $this->em->flush();
                        }

                        //  add transaction record with relation to content unit
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, $contentUnitEntity);

                        //  update account balances
                        $authorAccount = $this->checkAccount($authorAddresses[0]);
                        $this->updateAccountBalance($authorAccount, $feeWhole, $feeFraction, false);
                    } else {
                        //  check for related tags
                        $contentUnitTags = $this->em->getRepository(ContentUnitTag::class)->findBy(['contentUnitUri' => $uri]);
                        if ($contentUnitTags) {
                            foreach ($contentUnitTags as $contentUnitTag) {
                                $contentUnitTag->setContentUnit(null);
                                $this->em->persist($contentUnitTag);
                            }

                            $this->em->flush();
                        }

                        //  update account balances
                        $authorAccount = $this->checkAccount($authorAddresses[0]);
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
                        foreach ($contentUnitUris as $uri) {
                            $contentUnitEntity = $this->em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);
                            $contentUnitEntity->setContent($contentEntity);
                            $this->em->persist($contentUnitEntity);
                        }
                        $this->em->persist($contentEntity);
                        $this->em->flush();

                        //  add transaction record with relation to content
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, $contentEntity);

                        //  update account balances
                        $this->updateAccountBalance($channelAccount, $feeWhole, $feeFraction, false);
                    } else {
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

                        //  add transaction record without relation
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
                } elseif ($action->getAction() instanceof StorageUpdate) {
                    /**
                     * @var StorageUpdate $storageUpdate
                     */
                    $storageUpdate = $action->getAction();

                    $status = $storageUpdate->getStatus();
                    $storageAddress = $storageUpdate->getStorageAddress();
                    $fileUri = $storageUpdate->getFileUri();

                    $storageAddressAccount = $this->checkAccount($storageAddress);

                    if ($appliedReverted) {
                        $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $fileUri]);
                        if ($status == UpdateType::store) {
                            $storageAddressAccount->addStorageFile($fileEntity);
                        } else {
                            $storageAddressAccount->removeStorageFile($fileEntity);
                        }
                        $this->em->persist($storageAddressAccount);
                        $this->em->flush();

                        //  add transaction record without relation
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction);

                        //  update account balances
                        $this->updateAccountBalance($storageAddressAccount, $feeWhole, $feeFraction, false);
                    } else {
                        $fileEntity = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['uri' => $fileUri]);
                        if ($status == UpdateType::store) {
                            $storageAddressAccount->removeStorageFile($fileEntity);
                        } else {
                            $storageAddressAccount->addStorageFile($fileEntity);
                        }
                        $this->em->persist($storageAddressAccount);
                        $this->em->flush();

                        //  update account balances
                        $this->updateAccountBalance($storageAddressAccount, $feeWhole, $feeFraction, true);
                    }
                } elseif ($action->getAction() instanceof ServiceStatistics) {
                    /**
                     * @var ServiceStatistics $serviceStatistics
                     */
                    $serviceStatistics = $action->getAction();

                    $serverAddress = $serviceStatistics->getServerAddress();
                    $serverAddressAccount = $this->checkAccount($serverAddress);

                    if ($appliedReverted) {
                        //  add transaction record without relation
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction);

                        //  update account balances
                        $this->updateAccountBalance($serverAddressAccount, $feeWhole, $feeFraction, false);
                    } else {
                        //  update account balances
                        $this->updateAccountBalance($serverAddressAccount, $feeWhole, $feeFraction, true);
                    }
                } elseif ($action->getAction() instanceof SponsorContentUnit) {
                    /**
                     * @var SponsorContentUnit $sponsorContentUnit
                     */
                    $sponsorContentUnit = $action->getAction();

                    $sponsorAddress = $sponsorContentUnit->getSponsorAddress();
                    $uri = $sponsorContentUnit->getUri();
                    $startTimePoint = $sponsorContentUnit->getStartTimePoint();
                    $hours = $sponsorContentUnit->getHours();
                    $whole = $sponsorContentUnit->getAmount()->getWhole();
                    $fraction = $sponsorContentUnit->getAmount()->getFraction();

                    $sponsorAddressAccount = $this->checkAccount($sponsorAddress);

                    if ($appliedReverted) {
                        $contentUnitEntity = $this->em->getRepository(\App\Entity\ContentUnit::class)->findOneBy(['uri' => $uri]);

                        $boostedContentUnitEntity = new BoostedContentUnit();
                        $boostedContentUnitEntity->setSponsor($sponsorAddressAccount);
                        $boostedContentUnitEntity->setContentUnit($contentUnitEntity);
                        $boostedContentUnitEntity->setStartTimePoint($startTimePoint);
                        $boostedContentUnitEntity->setHours($hours);
                        $boostedContentUnitEntity->setWhole($whole);
                        $boostedContentUnitEntity->setFraction($fraction);
                        $boostedContentUnitEntity->setEndTimePoint($startTimePoint + $hours * 3600);
                        $this->em->persist($boostedContentUnitEntity);
                        $this->em->flush();

                        //  add transaction record without relation
                        $this->addTransaction(null, $transactionHash, $transactionSize, $timeSigned, $feeWhole, $feeFraction, null, null, null, null, $boostedContentUnitEntity);

                        //  update account balances
                        $this->updateAccountBalance($sponsorAddressAccount, $feeWhole, $feeFraction, false);
                        $this->updateAccountBalance($sponsorAddressAccount, $whole, $fraction, false);
                    } else {
                        //  update account balances
                        $this->updateAccountBalance($sponsorAddressAccount, $feeWhole, $feeFraction, true);
                        $this->updateAccountBalance($sponsorAddressAccount, $whole, $fraction, true);
                    }
                } elseif ($action->getAction() instanceof CancelSponsorContentUnit) {
                    /**
                     * @var CancelSponsorContentUnit $cancelSponsorContentUnit
                     */
                    $cancelSponsorContentUnit = $action->getAction();

                    $boostTransactionHash = $cancelSponsorContentUnit->getTransactionHash();
                    $sponsorAddress = $cancelSponsorContentUnit->getSponsorAddress();

                    $sponsorAddressAccount = $this->checkAccount($sponsorAddress);

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

                        //  update account balances
                        $this->updateAccountBalance($sponsorAddressAccount, $feeWhole, $feeFraction, false);
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

                        //  update account balances
                        $this->updateAccountBalance($sponsorAddressAccount, $feeWhole, $feeFraction, true);
                    }
                } else {
                    var_dump($action->getAction());
                    exit();
                }

                //  delete transaction with all data
                if (!$appliedReverted) {
                    /**
                     * @var Transaction $transaction
                     */
                    $transaction = $this->em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $transactionHash]);
                    if ($transaction) {
                        if ($transaction->getFile()) {
                            /**
                             * @var \App\Entity\File $fileEntity
                             */
                            $fileEntity = $transaction->getFile();
                            $fileEntity->setTransaction(null);
                            $this->em->persist($fileEntity);
                            $this->em->flush();
                        }
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

        $this->em->commit();

        $this->io->writeln(sprintf('Finished at with index=%s: %s', $index, date('Y-m-d H:i:s')));
        $this->io->success('BlockChain is synced now!');

        //  send notifications
        $this->sendNotifications();

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
        if (!$transaction) {
            $transaction = new Transaction();
            $transaction->setTransactionHash($transactionHash);
        }

        $transaction->setBlock($block);
        $transaction->setTransactionSize($transactionSize);
        $transaction->setTimeSigned($timeSigned);
        $transaction->setFeeWhole($feeWhole);
        $transaction->setFeeFraction($feeFraction);
        $transaction->setFile($file);
        $transaction->setContentUnit($contentUnit);
        $transaction->setContent($content);
        $transaction->setTransfer($transfer);
        $transaction->setBoostedContentUnit($boostedContentUnit);
        $transaction->setCancelBoostedContentUnit($cancelBoostedContentUnit);

        $this->em->persist($transaction);
        $this->em->flush();

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
        $address = $account->getPublicKey();
        $sign = $add ? 1 : -1;

        if (!array_key_exists($account->getPublicKey(), $this->balances)) {
            $this->balances[$address]['whole'] = $account->getWhole();
            $this->balances[$address]['fraction'] = $account->getFraction();
        }
        $this->balances[$address]['whole'] += $whole * $sign;
        $this->balances[$address]['fraction'] += $fraction * $sign;
    }

    /**
     * @return null
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function sendNotifications()
    {
        /**
         * get the last index number - if not exist set default as 0
         * @var IndexNumber $indexNumber
         */
        $indexNumber = $this->em->getRepository(IndexNumber::class)->findOneBy([], ['id' => 'DESC']);
        if (!$indexNumber) {
            $this->io->success('Done');
            $this->release();

            return null;
        }

        $timezone = new \DateTimeZone('UTC');
        $datetimeObj = new \DateTime();
        $datetimeObj->setTimezone($timezone);

        $datetime = $indexNumber->getLastNotifyTime();
        if ($datetime == 0) {
            $indexNumber->setLastNotifyTime($datetimeObj->getTimestamp());
            $this->em->persist($indexNumber);
            $this->em->flush();

            $this->io->success('Done');
            $this->release();

            return null;
        }

        //  enable channel exclude filter
        $this->em->getFilters()->enable('channel_exclude_filter');

        /**
         * @var \App\Entity\ContentUnit[] $articles
         */
        $articles = $this->em->getRepository(\App\Entity\ContentUnit::class)->getArticlesConfirmedAfterDate($datetime);
        if ($articles) {
            foreach ($articles as $article) {
                // notify author to share
                $this->eventDispatcher->dispatch(
                    new ArticleShareEvent($article),
                    ArticleShareEvent::NAME
                );

                // notify subscribed users
                $this->eventDispatcher->dispatch(
                    new ArticleNewEvent($article),
                    ArticleNewEvent::NAME
                );
            }
        }

        /**
         * @var BoostedContentUnit[] $boostedArticles
         */
        $boostedArticles = $this->em->getRepository(BoostedContentUnit::class)->getBoostsConfirmedAfterDate($datetime);
        if ($boostedArticles) {
            foreach ($boostedArticles as $boostedArticle) {
                $sponsor = $boostedArticle->getSponsor();

                /**
                 * @var \App\Entity\ContentUnit $article
                 */
                $article = $boostedArticle->getContentUnit();

                /**
                 * @var AccountContentUnit[] $authors
                 */
                $authors = $article->getAuthors();
                $isOwner = false;
                foreach ($authors as $author) {
                    if ($sponsor === $author->getAccount()) {
                        $isOwner = true;
                        break;
                    }
                }

                if (!$isOwner) {
                    // notify authors about boost by other user
                    $this->eventDispatcher->dispatch(
                        new ArticleBoostedByOtherEvent($sponsor, $article),
                        ArticleBoostedByOtherEvent::NAME
                    );
                }
            }
        }

        /**
         * @var \App\Entity\Transfer[] $transfers
         */
        $transfers = $this->em->getRepository(\App\Entity\Transfer::class)->getTransfersConfirmedAfterDate($datetime);
        if ($transfers) {
            foreach ($transfers as $transfer) {
                /**
                 * @var Account $toAccount
                 */
                $toAccount = $transfer->getTo();

                $exchanges = $this->em->getRepository(AccountExchange::class)->findBy(['account' => $toAccount]);
                if ($exchanges) {
                    foreach ($exchanges as $exchange) {
                        $exchangeStatus = file_get_contents($this->container->getParameter('ataix_api_endpoint') . '/exchange?id=' . $exchange->getExchangeId());
                        if ($exchangeStatus !== false) {
                            $exchangeStatus = json_decode($exchangeStatus, true);
                            if ($exchangeStatus['status']) {
                                $exchangeStatus = $exchangeStatus['result']['status'];

                                $exchange->setStatus(AccountExchange::STATUSES[$exchangeStatus]);
                                $this->em->persist($exchange);

                                // notify account about completed transfer
                                if ($exchangeStatus === AccountExchange::STATUSES['completed']) {
                                    $this->eventDispatcher->dispatch(
                                        new ExchangeCompletedEvent($exchange),
                                        ExchangeCompletedEvent::NAME
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }

        $indexNumber->setLastNotifyTime($datetimeObj->getTimestamp() - 30);
        $this->em->persist($indexNumber);
        $this->em->flush();

        $this->io->success('Notifications sent');

        return null;
    }
}