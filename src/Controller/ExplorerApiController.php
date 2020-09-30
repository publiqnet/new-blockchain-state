<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 11/4/19
 * Time: 2:08 PM
 */

namespace App\Controller;

use App\Entity\Account;
use App\Entity\Block;
use App\Entity\Reward;
use App\Entity\Transaction;
use App\Entity\Transfer;
use Doctrine\ORM\EntityManager;
use PubliqAPI\Base\RewardType;
use PubliqAPI\Base\Rtt;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ExplorerApiController
 * @package AppBundle\Controller
 *
 * @Route("/api/explorer")
 */
class ExplorerApiController extends AbstractController
{
    /**
     * @Route("/search/{search}", methods={"GET"})
     * @SWG\Get(
     *     summary="Search for block / account / transaction",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Explorer")
     * @param $search
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function search($search)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();
        $serializer = $this->get('serializer');

        try {
            $block = $em->getRepository(Block::class)->findBlock($search);
            if ($block) {
                $block->setTransactionsCount(count($block->getTransactions()));

                //  get block confirmations count
                $blockSummary = $em->getRepository(Block::class)->getBlockConfirmations($block);
                $block->setConfirmationsCount($blockSummary[0]['confirmationsCount']);

                //  get previous block
                /**
                 * @var Block $previousBlock
                 */
                $previousBlock = $em->getRepository(Block::class)->getPreviousBlock($block);
                $block->setPreviousBlockHash($previousBlock->getHash());

                //  get total fees for block
                $feeSummary = $em->getRepository(Transaction::class)->getBlockFeeSummary($block);

                //  get total transfers for block
                $transferSummary = $em->getRepository(Transfer::class)->getBlockTransfersSummary($block);

                //  get block last 10 transactions
                $transactions = $em->getRepository(Transaction::class)->getBlockTransactions($block, 0, 10);
                $transactions = $this->get('serializer')->normalize($transactions, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);

                //  format data
                $block = $this->get('serializer')->normalize($block, null, ['groups' => ['explorerBlock', 'explorerAccountLight', 'explorerReward']]);

                $block['fee'] = ['whole' => intval($feeSummary['totalFeeWhole']), 'fraction' => intval($feeSummary['totalFeeFraction'])];
                $block['transfer'] = ['whole' => intval($transferSummary['totalWhole']), 'fraction' => intval($transferSummary['totalFraction'])];
                $block['transactions'] = $transactions;

                return new JsonResponse(['object' => $block, 'type' => 'block']);
            }

            $transaction = $em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $search]);
            if ($transaction) {
                /**
                 * @var Block $block
                 */
                $block = $transaction->getBlock();

                if ($block) {
                    $blockSummary = $em->getRepository(Block::class)->getBlockConfirmations($block);
                    $block->setConfirmationsCount($blockSummary[0]['confirmationsCount'] + 1);
                }

                $transaction = $serializer->normalize($transaction, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);

                return new JsonResponse(['object' => $transaction, 'type' => 'transaction']);
            }

            $account = $em->getRepository(Account::class)->findOneBy(['publicKey' => $search]);
            if ($account) {
                $accountTransactions = $em->getRepository(Transaction::class)->getAccountTransactions($account, null, 11);
                $accountTransactions = $this->get('serializer')->normalize($accountTransactions, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);
                $moreTransactions = 0;
                if (count($accountTransactions) > 10) {
                    unset($accountTransactions[10]);
                    $moreTransactions = 1;
                }

                $accountRewards= $em->getRepository(Reward::class)->findBy(['to' => $account], ['id' => 'DESC'], 11);
                $accountRewards = $this->get('serializer')->normalize($accountRewards, null, ['groups' => ['explorerRewardLight', 'explorerBlockLight']]);
                $moreRewards = 0;
                if (count($accountRewards) > 10) {
                    unset($accountRewards[10]);
                    $moreRewards = 1;
                }

                $initialReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::initial);
                $minerReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::miner);
                $authorReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::author);
                $channelReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::channel);
                $storageReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::storage);
                $sponsoredReturnReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::sponsored_return);
                $feeReward = $em->getRepository(Transaction::class)->getAccountFeeRewardSummary($account);

                $account = $serializer->normalize($account, null, ['groups' => ['explorerAccount']]);

                $account['transactions'] = $accountTransactions;
                $account['moreTransactions'] = $moreTransactions;
                $account['rewards'] = $accountRewards;
                $account['moreRewards'] = $moreRewards;

                $account['minerReward'] = ['whole' => $minerReward['totalWhole'], 'fraction' => $minerReward['totalFraction']];
                $account['initialReward'] = ['whole' => $initialReward['totalWhole'], 'fraction' => $initialReward['totalFraction']];
                $account['authorReward'] = ['whole' => $authorReward['totalWhole'], 'fraction' => $authorReward['totalFraction']];
                $account['channelReward'] = ['whole' => $channelReward['totalWhole'], 'fraction' => $channelReward['totalFraction']];
                $account['storageReward'] = ['whole' => $storageReward['totalWhole'], 'fraction' => $storageReward['totalFraction']];
                $account['sponsoredReturnReward'] = ['whole' => $sponsoredReturnReward['totalWhole'], 'fraction' => $sponsoredReturnReward['totalFraction']];
                $account['feeReward'] = ['whole' => $feeReward['totalWhole'], 'fraction' => $feeReward['totalFraction']];

                return new JsonResponse(['object' => $account, 'type' => 'account']);
            }

            return new JsonResponse('', Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/blocks/{count}/{fromHash}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get blocks (with pagination)",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Explorer")
     * @param int $count
     * @param string|null $fromHash
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getBlocks(int $count, string $fromHash = null)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        $block = null;
        if ($fromHash) {
            $block = $em->getRepository(Block::class)->findOneBy(['hash' => $fromHash]);
        }

        try {
            $blocks = $em->getRepository(Block::class)->getBlocks($block, $count + 1);
            if ($blocks) {
                /**
                 * @var Block $block
                 */
                foreach ($blocks as $block) {
                    $blockSummary = $em->getRepository(Block::class)->getBlockConfirmations($block);
                    /**
                     * @var Block $previousBlock
                     */
                    $previousBlock = $em->getRepository(Block::class)->getPreviousBlock($block);

                    $block->setTransactionsCount(count($block->getTransactions()));
                    $block->setConfirmationsCount($blockSummary[0]['confirmationsCount']);
                    if ($previousBlock) {
                        $block->setPreviousBlockHash($previousBlock->getHash());
                    }
                }
            }
            $blocks = $this->get('serializer')->normalize($blocks, null, ['groups' => ['explorerBlockLight']]);

            //  check if more blocks exist
            $moreBlocks = 0;
            if (count($blocks) > $count) {
                unset($blocks[$count]);

                $moreBlocks = 1;
            }

            return new JsonResponse(['blocks' => $blocks, 'more' => $moreBlocks]);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/blocks/{year}/{month}/{day}/{count}/{fromHash}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get blocks for given date (with pagination)",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Explorer")
     * @param int $year
     * @param int $month
     * @param int $day
     * @param int $count
     * @param string $fromHash
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getBlocksByDate(int $year, int $month, int $day, int $count, string $fromHash)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        $block = null;
        if ($fromHash) {
            $block = $em->getRepository(Block::class)->findOneBy(['hash' => $fromHash]);
        }

        try {
            $blocks = $em->getRepository(Block::class)->getBlocksByDate($year, $month, $day, $block, $count + 1);
            if ($blocks) {
                /**
                 * @var Block $block
                 */
                foreach ($blocks as $block) {
                    $blockSummary = $em->getRepository(Block::class)->getBlockConfirmations($block);
                    /**
                     * @var Block $previousBlock
                     */
                    $previousBlock = $em->getRepository(Block::class)->getPreviousBlock($block);

                    $block->setTransactionsCount(count($block->getTransactions()));
                    $block->setConfirmationsCount($blockSummary[0]['confirmationsCount']);
                    if ($previousBlock) {
                        $block->setPreviousBlockHash($previousBlock->getHash());
                    }
                }
            }
            $blocks = $this->get('serializer')->normalize($blocks, null, ['groups' => ['explorerBlockLight']]);

            //  check if more blocks exist
            $moreBlocks = 0;
            if (count($blocks) > $count) {
                unset($blocks[$count]);

                $moreBlocks = 1;
            }

            return new JsonResponse(['blocks' => $blocks, 'more' => $moreBlocks]);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/block/{blockHash}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get single block",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Explorer")
     * @param string $blockHash
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getBlock(string $blockHash)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        try {
            //  check if block exist
            $block = $em->getRepository(Block::class)->findOneBy(['hash' => $blockHash]);
            if (!$block) {
                return new JsonResponse(null, Response::HTTP_NOT_FOUND);
            }

            $block->setTransactionsCount(count($block->getTransactions()));

            //  get block confirmations count
            $blockSummary = $em->getRepository(Block::class)->getBlockConfirmations($block);
            $block->setConfirmationsCount($blockSummary[0]['confirmationsCount']);

            //  get previous block
            /**
             * @var Block $previousBlock
             */
            $previousBlock = $em->getRepository(Block::class)->getPreviousBlock($block);
            $block->setPreviousBlockHash($previousBlock->getHash());

            //  get total fees for block
            $feeSummary = $em->getRepository(Transaction::class)->getBlockFeeSummary($block);

            //  get total transfers for block
            $transferSummary = $em->getRepository(Transfer::class)->getBlockTransfersSummary($block);

            //  get block last 10 transactions
            $transactions = $em->getRepository(Transaction::class)->getBlockTransactions($block, 0, 10);
            $transactions = $this->get('serializer')->normalize($transactions, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);

            //  format data
            $block = $this->get('serializer')->normalize($block, null, ['groups' => ['explorerBlock', 'explorerAccountLight', 'explorerReward']]);

            $block['fee'] = ['whole' => intval($feeSummary['totalFeeWhole']), 'fraction' => intval($feeSummary['totalFeeFraction'])];
            $block['transfer'] = ['whole' => intval($transferSummary['totalWhole']), 'fraction' => intval($transferSummary['totalFraction'])];
            $block['transactions'] = $transactions;

            return new JsonResponse($block);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/block/{blockHash}/transactions/{from}/{count}", methods={"GET"})
     * @Route("/block/{blockHash}/transactions/{rtt}/{from}/{count}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get given block transactions (with pagination)",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Explorer")
     * @param string $blockHash
     * @param int $rtt
     * @param int $from
     * @param int $count
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getBlockTransactions(string $blockHash, int $rtt = null, int $from = 0, int $count = 10)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        try {
            $block = $em->getRepository(Block::class)->findOneBy(['hash' => $blockHash]);
            if (!$block) {
                return new JsonResponse(null, Response::HTTP_NOT_FOUND);
            }

            if (!isset(Rtt::types[$rtt])) {
                $rtt = null;
            }

            $transactions = $em->getRepository(Transaction::class)->getBlockTransactions($block, $from, $count + 1, $rtt);
            $transactions = $this->get('serializer')->normalize($transactions, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);

            //  check if more transactions exist
            $moreTransactions = false;
            if (count($transactions) > $count) {
                unset($transactions[$count]);
                $moreTransactions = true;
            }

            return new JsonResponse(['transactions' => $transactions, 'more' => ($moreTransactions ? 1: 0)]);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/transaction/{count}/{fromHash}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get transactions (with pagination)",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Explorer")
     * @param int $count
     * @param string|null $fromHash
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getTransactions(int $count, string $fromHash)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        $transaction = null;
        if ($fromHash) {
            $transaction = $em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $fromHash]);
        }

        try {
            /**
             * @var Transaction[] $transactions
             */
            $transactions = $em->getRepository(Transaction::class)->getTransactions($transaction, $count + 1);
            if ($transactions) {
                foreach ($transactions as $transaction) {
                    /**
                     * @var Block $block
                     */
                    $block = $transaction->getBlock();

                    if ($block) {
                        $blockSummary = $em->getRepository(Block::class)->getBlockConfirmations($block);
                        $block->setConfirmationsCount($blockSummary[0]['confirmationsCount'] + 1);
                    }
                }
            }
            $transactions = $this->get('serializer')->normalize($transactions, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);

            //  check if more transactions exist
            $moreTransactions = 0;
            if (count($transactions) > $count) {
                unset($transactions[$count]);

                $moreTransactions = 1;
            }

            return new JsonResponse(['transactions' => $transactions, 'more' => $moreTransactions]);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }
    }

    /**
     * @Route("/transaction/{transactionHash}", methods={"GET"})
     * @SWG\Get(
     *     summary="Get transaction",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Tag(name="Explorer")
     * @param Transaction $transaction
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getTransaction(Transaction $transaction)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();
        $serializer = $this->get('serializer');

        /**
         * @var Block $block
         */
        $block = $transaction->getBlock();

        if ($block) {
            $blockSummary = $em->getRepository(Block::class)->getBlockConfirmations($block);
            $block->setConfirmationsCount($blockSummary[0]['confirmationsCount'] + 1);
        }

        $transaction = $serializer->normalize($transaction, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);

        return new JsonResponse($transaction);
    }

    /**
     * @Route("/account/{publicKey}", methods={"GET"}, name="get_account_balance")
     * @SWG\Get(
     *     summary="Get account",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=404, description="Account not found")
     * @SWG\Tag(name="Explorer")
     * @param string $publicKey
     * @return JsonResponse
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getAccount(string $publicKey)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$account) {
            return new JsonResponse('', Response::HTTP_NOT_FOUND);
        }

        $accountTransactions = $em->getRepository(Transaction::class)->getAccountTransactions($account, null, 11);
        $accountTransactions = $this->get('serializer')->normalize($accountTransactions, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);
        $moreTransactions = 0;
        if (count($accountTransactions) > 10) {
            unset($accountTransactions[10]);
            $moreTransactions = 1;
        }

        $accountRewards= $em->getRepository(Reward::class)->findBy(['to' => $account], ['id' => 'DESC'], 11);
        $accountRewards = $this->get('serializer')->normalize($accountRewards, null, ['groups' => ['explorerRewardLight', 'explorerBlockLight']]);
        $moreRewards = 0;
        if (count($accountRewards) > 10) {
            unset($accountRewards[10]);
            $moreRewards = 1;
        }

        $initialReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::initial);
        $minerReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::miner);
        $authorReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::author);
        $channelReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::channel);
        $storageReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::storage);
        $sponsoredReturnReward = $em->getRepository(Reward::class)->getAccountRewardSummary($account, RewardType::sponsored_return);
        $feeReward = $em->getRepository(Transaction::class)->getAccountFeeRewardSummary($account);

        $account = $this->get('serializer')->normalize($account, null, ['groups' => ['explorerAccount']]);

        $account['transactions'] = $accountTransactions;
        $account['moreTransactions'] = $moreTransactions;
        $account['rewards'] = $accountRewards;
        $account['moreRewards'] = $moreRewards;

        $account['minerReward'] = ['whole' => $minerReward['totalWhole'], 'fraction' => $minerReward['totalFraction']];
        $account['initialReward'] = ['whole' => $initialReward['totalWhole'], 'fraction' => $initialReward['totalFraction']];
        $account['authorReward'] = ['whole' => $authorReward['totalWhole'], 'fraction' => $authorReward['totalFraction']];
        $account['channelReward'] = ['whole' => $channelReward['totalWhole'], 'fraction' => $channelReward['totalFraction']];
        $account['storageReward'] = ['whole' => $storageReward['totalWhole'], 'fraction' => $storageReward['totalFraction']];
        $account['sponsoredReturnReward'] = ['whole' => $sponsoredReturnReward['totalWhole'], 'fraction' => $sponsoredReturnReward['totalFraction']];
        $account['feeReward'] = ['whole' => $feeReward['totalWhole'], 'fraction' => $feeReward['totalFraction']];

        return new JsonResponse($account);
    }

    /**
     * @Route("/account/{publicKey}/transactions/{rtt}/{count}/{fromHash}", methods={"GET"}, name="get_account_transactions")
     * @SWG\Get(
     *     summary="Get user transactions",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Tag(name="Explorer")
     * @param string $publicKey
     * @param $rtt
     * @param int $count
     * @param string|null $fromHash
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getAccountTransactions(string $publicKey, $rtt, int $count, string $fromHash = null)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$account) {
            return new JsonResponse('', Response::HTTP_NOT_FOUND);
        }

        $transaction = null;
        if ($fromHash) {
            $transaction = $em->getRepository(Transaction::class)->findOneBy(['transactionHash' => $fromHash]);
        }

        if (!isset(Rtt::types[$rtt])) {
            $rtt = null;
        }

        $accountTransactions = $em->getRepository(Transaction::class)->getAccountTransactions($account, $transaction, $count + 1, $rtt);
        $accountTransactions = $this->get('serializer')->normalize($accountTransactions, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);

        //  check if more transactions exist
        $moreTransactions = 0;
        if (count($accountTransactions) > $count) {
            unset($accountTransactions[$count]);

            $moreTransactions = 1;
        }

        return new JsonResponse(['transactions' => $accountTransactions, 'more' => $moreTransactions]);
    }

    /**
     * @Route("/account/{publicKey}/transactions-rewards/{count}/{fromHash}", methods={"GET"}, name="get_account_transactions_rewards")
     * @SWG\Get(
     *     summary="Get user transactions & rewards",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Tag(name="Explorer")
     * @param string $publicKey
     * @param int $count
     * @param int $from
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getAccountTransactionsRewards(string $publicKey, int $count, int $from)
    {
        /**
         * @var EntityManager $em
         */
        $em = $this->getDoctrine()->getManager();
        $conn = $this->getDoctrine()->getConnection();

        /**
         * @var Account $account
         */
        $account = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$account) {
            return new JsonResponse('', Response::HTTP_NOT_FOUND);
        }

        $sql = '
            (
                select t.id as transaction_id, t.time_signed as datetime, NULL as reward_id 
                from transaction t 
                left join transfer tr on (t.transfer_id = tr.id)
                where 
                    t.account_id = :account or 
                    tr.from_id = :account or
                    tr.to_id = :account
            )
            union all
            (
                select NULL as transaction_id, b.sign_time as datetime, r.id as reward_id 
                from reward r left join block b on (r.block_id = b.id) 
                where r.account_id = :account
            )
            order by datetime desc, transaction_id desc, reward_id desc 
            limit ' . $from . ', ' . ($count + 1) . '
        ';
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('account' => $account->getId()));

        $result = $stmt->fetchAll();

        $more = false;
        if (count($result) > $count) {
            $more = true;
            unset($result[$count]);
        }

        $returnRes = [];
        foreach ($result as $resultSingle) {
            if ($resultSingle['transaction_id']) {
                $transaction = $em->getRepository(Transaction::class)->find($resultSingle['transaction_id']);
                $transaction = $this->get('serializer')->normalize($transaction, null, ['groups' => ['explorerTransaction', 'explorerBlockLight', 'explorerAccountLight', 'explorerFile', 'explorerContentUnit', 'explorerContent', 'explorerTransfer', 'explorerRole', 'explorerStorageUpdate', 'explorerServiceStatistics', 'explorerBoostedContentUnit', 'explorerCancelBoostedContentUnit']]);
                $returnRes[] = ['type' => 'transaction', 'data' => $transaction];
            } else {
                $reward = $em->getRepository(Reward::class)->find($resultSingle['reward_id']);
                $reward = $this->get('serializer')->normalize($reward, null, ['groups' => ['explorerRewardLight', 'explorerBlockLight']]);
                $returnRes[] = ['type' => 'reward', 'data' => $reward];
            }
        }

        return new JsonResponse(['data' => $returnRes, 'more' => $more]);
    }

    /**
     * @Route("/account/{publicKey}/rewards/{count}/{from}", methods={"GET"}, name="get_account_rewards")
     * @SWG\Get(
     *     summary="Get user rewards",
     *     consumes={"application/json"},
     *     produces={"application/json"},
     * )
     * @SWG\Response(response=200, description="Success")
     * @SWG\Response(response=401, description="Unauthorized user")
     * @SWG\Tag(name="Explorer")
     * @param string $publicKey
     * @param int $count
     * @param int $from
     * @return JsonResponse
     * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
     */
    public function getAccountRewards(string $publicKey, int $count, int $from = 0)
    {
        $em = $this->getDoctrine()->getManager();

        /**
         * @var Account $account
         */
        $account = $em->getRepository(Account::class)->findOneBy(['publicKey' => $publicKey]);
        if (!$account) {
            return new JsonResponse('', Response::HTTP_NOT_FOUND);
        }

        $accountRewards= $em->getRepository(Reward::class)->findBy(['to' => $account], ['id' => 'DESC'], $count + 1, $from);
        $accountRewards = $this->get('serializer')->normalize($accountRewards, null, ['groups' => ['explorerRewardLight', 'explorerBlockLight']]);

        //  check if more transactions exist
        $moreRewards = 0;
        if (count($accountRewards) > $count) {
            unset($accountRewards[$count]);

            $moreRewards = 1;
        }

        return new JsonResponse(['rewards' => $accountRewards, 'more' => $moreRewards]);
    }
}