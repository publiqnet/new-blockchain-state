<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/9/20
 * Time: 11:44 AM
 */

namespace App\Command;

use App\Service\BlockChain;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PubliqAPI\Base\UriProblemType;
use PubliqAPI\Model\Content;
use PubliqAPI\Model\StorageFileAddress;
use PubliqAPI\Model\TransactionDone;
use PubliqAPI\Model\UriError;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MirrorRssCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'state:mirror-rss';

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
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        $header = ['authorization: Basic cHVibGlxOm9iNEQ1TGpr'];

        $ch = curl_init('https://reach-feeds-prod.tm-awx.com/news/mirror?groupName=tmsyndication');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $body = str_replace("<content:encoded>", "<contentEncoded>", $body);
        $body = str_replace("</content:encoded>", "</contentEncoded>", $body);

        $body = str_replace("<media:content", "<mediaContent>", $body);
        $body = str_replace("</media:content>", "</mediaContent>", $body);

        $data = simplexml_load_string($body);
        if (!$data) {
            $this->io->error('Cannot load URL');
            $this->release();

            return null;
        }

        foreach ($data->channel->item as $item) {
            $title = $item->title;
            $description = $item->description;
            $guid = (int)$item->guid;
            $content = (string)$item->contentEncoded;
            $media = $item->mediaContent;

            $attributes = $media->attributes;
            if ($attributes) {
                /**
                 * @var \DOMAttr $attribute
                 */
                foreach ($attributes as $attribute) {
                    echo $attribute->nodeName . ' - ' . $attribute->nodeValue . PHP_EOL;
                }
                exit();
            }


            $dom = new \DOMDocument();
            @$dom->loadHTML('<div>' . $content . '</div>');

            /**
             * @var \DOMNodeList $child
             */
            $divChild = $dom->getElementsByTagName('div');
            $childNodes = $divChild->item(0)->childNodes;

            $contentUnit = '';
            $fileUris = [];

            //  CREATE CONTENT UNIT
            //  title
            $contentUnit .= '<h1>' . $title . '</h1>';

            //  description
            $uri = $this->uploadFile('<p>' . $description . '</p>', 'text/html');
            if ($uri === null) {
                $this->io->error('Error on file sign/broadcast');
                $this->release();

                return null;
            }

            $fileUris[] = $uri;
            $contentUnit .= ' ' . $uri;

            //  text, video & images
            /**
             * @var \DOMNode $childNode
             */
            foreach ($childNodes as $childNode) {
                if ($childNode->nodeName == '#text') {
                    continue;
                }

                if ($childNode->nodeName == 'img') {
                    /**
                     * @var \DOMNamedNodeMap $attributes
                     */
                    $attributes = $childNode->attributes;

                    /**
                     * @var \DOMAttr $attribute
                     */
                    foreach ($attributes as $attribute) {
                        if ($attribute->nodeName == 'src') {
                            $tempImageName = substr($attribute->nodeValue, strrpos($attribute->nodeValue, '/') + 1);
                            copy($attribute->nodeValue, 'public/uploads/' . $tempImageName);

                            $fileObj = new \Symfony\Component\HttpFoundation\File\File('public/uploads/' . $tempImageName);
                            $fileData = file_get_contents($fileObj->getRealPath());

                            //  upload file into channel storage
                            $uri = $this->uploadFile($fileData, $fileObj->getMimeType());
                            if ($uri === null) {
                                $this->io->error('Error on file sign/broadcast');
                                $this->release();

                                return null;
                            }

                            $fileUris[] = $uri;
                            $contentUnit .= ' <img src="' . $uri . '" />';
                            unlink('public/uploads/' . $tempImageName);

                            break;
                        }
                    }
                } else {
                    $uri = $this->uploadFile($childNode->ownerDocument->saveHTML($childNode), 'text/html');
                    if ($uri === null) {
                        $this->io->error('Error on file sign/broadcast');
                        $this->release();

                        return null;
                    }

                    $fileUris[] = $uri;
                    $contentUnit .= ' ' . $uri;
                }
            }

            //  CONTENT UNIT
            $fileUris = array_unique($fileUris);
            $uri = $this->uploadContentUnit($contentUnit, $fileUris, $guid);
            if ($uri === null) {
                $this->io->error('Error on content unit sign/broadcast');
                $this->release();

                return null;
            }

            //  CONTENT
            $content = new Content();
            $content->setContentId($guid);
            $content->addContentUnitUris($uri);

            $broadcastResult = $this->blockChainService->signContent($content);
            if (!($broadcastResult instanceof TransactionDone)) {
                $this->io->error('Error on content sign/broadcast');
                $this->release();

                return null;
            }

            exit();
        }

        $this->io->success('Data received');

        $this->release();

        return null;
    }

    /**
     * @param $data
     * @param $mimeType
     * @return null
     * @throws \Exception
     */
    private function uploadFile($data, $mimeType)
    {
        $uploadResult = $this->blockChainService->uploadFile($data, $mimeType);

        echo 'File upload result' . PHP_EOL;
        var_dump($uploadResult);

        if ($uploadResult instanceof StorageFileAddress) {
            $uri = $uploadResult->getUri();
            $broadcastResult = $this->blockChainService->signFile($uri);

            echo 'File sign result' . PHP_EOL;
            var_dump($broadcastResult);

            if (!($broadcastResult instanceof TransactionDone)) {
                return null;
            } else {
                return $uri;
            }
        } elseif (($uploadResult instanceof UriError) && $uploadResult->getUriProblemType() === UriProblemType::duplicate) {
            $uri = $uploadResult->getUri();

            return $uri;
        } else {
            return null;
        }
    }

    /**
     * @param $data
     * @param $fileUris
     * @param $guid
     * @return null
     * @throws \Exception
     */
    private function uploadContentUnit($data, $fileUris, $guid)
    {
        $uploadResult = $this->blockChainService->uploadFile($data, 'text/html');

        echo 'Content unit upload result' . PHP_EOL;
        var_dump($uploadResult);

        if ($uploadResult instanceof StorageFileAddress) {
            $uri = $uploadResult->getUri();
            $broadcastResult = $this->blockChainService->signContentUnit($uri, $fileUris, $guid);

            echo 'Content unit sign result' . PHP_EOL;
            var_dump($broadcastResult);

            if (!($broadcastResult instanceof TransactionDone)) {
                return null;
            } else {
                return $uri;
            }
        } elseif (($uploadResult instanceof UriError) && $uploadResult->getUriProblemType() === UriProblemType::duplicate) {
            $uri = $uploadResult->getUri();

            return $uri;
        } else {
            return null;
        }
    }
}
