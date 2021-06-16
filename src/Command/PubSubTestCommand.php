<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/16/21
 * Time: 1:15 PM
 */

namespace App\Command;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Google\Cloud\PubSub\MessageBuilder;
use Google\Cloud\PubSub\PubSubClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PubSubTestCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'state:pub-sub-test';

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;


    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
    }

    protected function configure()
    {
        $this->setDescription('Test Google PubSub');
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
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        $this->googlePubSub('http://www.forbes.com/sites/christopherhelman/2020/05/21/how-this-billionaire-backed-crypto-startup-gets-paid-to-not-mine-bitcoin/', 'H47Jaj419ggDGmuoaBpBUe15M46MEhmxspPJ3BjhBwDy');

        $this->io->success('Done');
        $this->release();

        return null;
    }

    private function googlePubSub($canonicalUrl, $transactionHash)
    {
        $url = 'https://www.forbes.com/forbesapi/content/uri.json?uri=' . $canonicalUrl . '&type=ng&shortcodes=true';

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);

            $response = curl_exec($ch);

            $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $body = substr($response, $headerSize);

            curl_close($ch);

            if ($headerStatusCode == 200) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $naturalId = $data['content']['naturalId'];

                    $message = [
                        'naturalId' => $naturalId,
                        'publiqUrl' => $_ENV['PUBLIQ_EXPLORER_URL'] . '/t/' . $transactionHash
                    ];

                    $pubSub = new PubSubClient();
                    $topic = $pubSub->topic($_ENV['GOOGLE_PUBSUB_TOPIC_ID']);
                    $publishRes = $topic->publish((new MessageBuilder)->setData(json_encode($message))->build());

                    file_put_contents(__DIR__ . '/../../var/log/pubsub.txt', $canonicalUrl . ' - ' . $publishRes['messageIds'][0] . PHP_EOL, FILE_APPEND);
                } else {
                    file_put_contents(__DIR__ . '/../../var/log/pubsub.txt', $canonicalUrl . ' - error: incorrect response' . PHP_EOL, FILE_APPEND);
                }
            } else {
                file_put_contents(__DIR__ . '/../../var/log/pubsub.txt', $canonicalUrl . ' - error: incorrect response header' . PHP_EOL, FILE_APPEND);
            }
        } catch (\Exception $e) {
            file_put_contents(__DIR__ . '/../../var/log/pubsub.txt', $canonicalUrl . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }


        return null;
    }
}