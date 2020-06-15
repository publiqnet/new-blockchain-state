<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 6/9/20
 * Time: 11:44 AM
 */

namespace App\Command;

use App\Entity\ContentUnit;
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
use Symfony\Component\HttpFoundation\File\File;

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
        $this->setDescription('Get data from Mirror RSS');
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

        $body = str_replace("<media:content", "<mediaContent", $body);
        $body = str_replace("</media:content>", "</mediaContent>", $body);

        $body = str_replace(["media:description", "media:thumbnail", "media:title", "media:credit", "media:copyright"], ["mediaDescription", "mediaThumbnail", "mediaTitle", "mediaCredit", "mediaCopyright"], $body);

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

            //  checkout for existing
            $contentUnitEntity = $this->em->getRepository(ContentUnit::class)->findOneBy(['contentId' => $guid]);
            if ($contentUnitEntity) {
                continue;
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
            $videoContent = [];
            $hasCover = false;

            //  CREATE CONTENT UNIT
            //  cover & video
            if ($media) {
                foreach ($media as $mediaSingle) {
                    if ($mediaSingle['medium'] == 'video') {
                        $videoContent[] = ['video' => '<figure class="image gridsize-image"><video title="' . $mediaSingle->mediaTitle . '" controls poster="' . $mediaSingle->mediaThumbnail['url'] . '"><source type="' . $mediaSingle['type'] . '" src="' . $mediaSingle['url'] . '"/></video></figure>', 'thumbnail' => $mediaSingle->mediaThumbnail['url']];
                    } elseif ($mediaSingle['medium'] == 'image') {
                        $imageUrl = $mediaSingle['url'];

                        $tempImageName = substr($imageUrl, strrpos($imageUrl, '/') + 1);
                        copy($imageUrl, 'public/uploads/' . $tempImageName);

                        $fileObj = new File('public/uploads/' . $tempImageName);
                        $fileData = file_get_contents($fileObj->getRealPath());

                        //  upload file into channel storage
                        $uri = $this->uploadFile($fileData, $fileObj->getMimeType());
                        if ($uri === null) {
                            $this->io->error('Error on file sign/broadcast');
                            $this->release();

                            return null;
                        }

                        $fileUris[] = $uri;
                        $contentUnit .= '<img src="' . $uri . '" />';
                        unlink('public/uploads/' . $tempImageName);

                        $hasCover = true;
                    }
                }
            }

            if (!$hasCover && count($videoContent)) {
                $imageUrl = $videoContent[0]['thumbnail'];

                $tempImageName = substr($imageUrl, strrpos($imageUrl, '/') + 1);
                copy($imageUrl, 'public/uploads/' . $tempImageName);

                $fileObj = new File('public/uploads/' . $tempImageName);
                $fileData = file_get_contents($fileObj->getRealPath());

                //  upload file into channel storage
                $uri = $this->uploadFile($fileData, $fileObj->getMimeType());
                if ($uri === null) {
                    $this->io->error('Error on file sign/broadcast');
                    $this->release();

                    return null;
                }

                $fileUris[] = $uri;
                $contentUnit .= '<img src="' . $uri . '" />';
                unlink('public/uploads/' . $tempImageName);
            }

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

                            $fileObj = new File('public/uploads/' . $tempImageName);
                            $fileData = file_get_contents($fileObj->getRealPath());

                            //  upload file into channel storage
                            $uri = $this->uploadFile($fileData, $fileObj->getMimeType());
                            if ($uri === null) {
                                $this->io->error('Error on file sign/broadcast');
                                $this->release();

                                return null;
                            }

                            $fileUris[] = $uri;
                            $contentUnit .= ' <figure class="image gridsize-image"><img src="' . $uri . '" /></figure>';
                            unlink('public/uploads/' . $tempImageName);

                            break;
                        }
                    }
                } elseif ($childNode->nodeName == 'video') {
                    $uri = $this->uploadFile('<figure class="image gridsize-image">' . $childNode->ownerDocument->saveHTML($childNode) . '</figure>', 'text/html');
                    if ($uri === null) {
                        $this->io->error('Error on file sign/broadcast');
                        $this->release();

                        return null;
                    }

                    $fileUris[] = $uri;
                    $contentUnit .= ' ' . $uri;
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

            if (count($videoContent)) {
                foreach ($videoContent as $videoContentSingle) {
                    $uri = $this->uploadFile($videoContentSingle['video'], 'text/html');
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
                var_dump($broadcastResult);

                $this->io->error('Error on content sign/broadcast');
                $this->release();

                return null;
            }
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

        if ($uploadResult instanceof StorageFileAddress) {
            $uri = $uploadResult->getUri();
            $broadcastResult = $this->blockChainService->signFile($uri);

            if (($broadcastResult instanceof UriError) && $broadcastResult->getUriProblemType() === UriProblemType::duplicate) {
                $uri = $uploadResult->getUri();

                return $uri;
            } elseif (!($broadcastResult instanceof TransactionDone)) {
                $this->io->error($broadcastResult->convertToJson());

                return null;
            } else {
                return $uri;
            }
        } elseif (($uploadResult instanceof UriError) && $uploadResult->getUriProblemType() === UriProblemType::duplicate) {
            $uri = $uploadResult->getUri();

            return $uri;
        } else {
            $this->io->error($uploadResult->convertToJson());

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

        if ($uploadResult instanceof StorageFileAddress) {
            $uri = $uploadResult->getUri();
            $broadcastResult = $this->blockChainService->signContentUnit($uri, $fileUris, $guid);

            if (!($broadcastResult instanceof TransactionDone)) {
                $this->io->error($broadcastResult->convertToJson());

                return null;
            } else {
                return $uri;
            }
        } elseif (($uploadResult instanceof UriError) && $uploadResult->getUriProblemType() === UriProblemType::duplicate) {
            $uri = $uploadResult->getUri();

            return $uri;
        } else {
            $this->io->error($uploadResult->convertToJson());

            return null;
        }
    }
}
