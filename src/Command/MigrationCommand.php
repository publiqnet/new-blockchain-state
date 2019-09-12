<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/11/19
 * Time: 12:16 PM
 */

namespace App\Command;

use App\Entity\Draft;
use App\Service\BlockChain;
use App\Service\Custom;
use Doctrine\ORM\EntityManager;
use PubliqAPI\Base\UriProblemType;
use PubliqAPI\Model\StorageFileAddress;
use PubliqAPI\Model\UriError;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationCommand extends ContainerAwareCommand
{
    use LockableTrait;

    const CHANNEL_TO_EXCEPT = ['stage-pbwm', 'pbwm', 'stage-tagesschau', 'tagesschau', 'bolshoy-sport', 'stage-funk', 'funk', 'stage-forbes', 'forbes'];

    protected static $defaultName = 'state:migrate';

    /** @var \App\Service\Custom $customService */
    private $customService;

    /** @var \App\Service\BlockChain $blockChainService */
    private $blockChainService;

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $em */
    private $io;


    public function __construct(Custom $custom, BlockChain $blockChainService)
    {
        parent::__construct();

        $this->customService = $custom;
        $this->blockChainService = $blockChainService;
    }

    protected function configure()
    {
        $this->setDescription('Migrate data from old to new');
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
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        $channelStorageEndpoint = $this->getContainer()->getParameter('channel_storage_endpoint');

        $authors = $this->customService->searchAuthors();
        foreach ($authors as $author) {
            $publicKey = $author['name'];

            $articles = $this->customService->getAuthorArticles($publicKey);
            if ($articles) {
                foreach ($articles as $article) {
                    $channel = $article['meta']['channel_id'];
                    if (in_array($channel, self::CHANNEL_TO_EXCEPT)) {
                        continue;
                    }

                    $dsId = $article['ds_id'];
                    $draft = $this->em->getRepository(Draft::class)->findOneBy(['dsId' => $dsId]);
                    if ($draft) {
                        continue;
                    }

                    $title = $article['meta']['title'];
                    $headline = $article['meta']['headline'];
                    $tags = $article['meta']['tags'];

                    //  get article text & convert into string
                    $articleContent = $this->customService->getArticle($dsId);
                    $binaryString = implode(array_map("chr", $articleContent));
                    $content = hex2bin($binaryString);

                    $content = json_decode($content, true);
                    $contentUris = [];
                    if (isset($content['text']) && $content['text']) {
                        $content = $content['text'];

                        //  get images from text, send into channel storage & replace in text
                        libxml_use_internal_errors(true);
                        $dom = new \DOMDocument();
                        $dom->loadHTML($content);

                        $images = $dom->getElementsByTagName('img');
                        /**
                         * @var \DOMNodeList $images
                         */
                        foreach ($images as $image) {
                            $imageSrc = $image->getAttribute('src');
                            if (!$imageSrc) {
                                continue;
                            }

                            $fileHeaders = @get_headers($imageSrc, 1);
                            if ($fileHeaders === false) {
                                continue;
                            }

                            $imageType = $this->getImageMimeType($imageSrc);
                            $fileData = file_get_contents($imageSrc);

                            $uploadResult = $this->blockChainService->uploadFile($fileData, $imageType);
                            if ($uploadResult instanceof StorageFileAddress || ($uploadResult instanceof UriError && $uploadResult->getUriProblemType() === UriProblemType::duplicate)) {
                                $imageUri = $uploadResult->getUri();
                                $imageUrl = $channelStorageEndpoint . '/storage?file=' . $uploadResult->getUri();
                            } else {
                                exit();
                            }

                            $contentUris[$imageUri] = $imageUrl;

                            $content = str_replace($imageSrc, $imageUrl . '" data-uri="' . $imageUri, $content);
                        }
                    } else {
                        $content = '';
                    }

                    $content = '<p>' . $headline . '</p>' . $content;

                    $draft = new Draft();
                    $draft->setContent($content);
                    $draft->setTitle($title);
                    if (is_array($tags)) {
                        $draft->setTags($tags);
                    }
                    $draft->setContentUris($contentUris);
                    $draft->setPublicKey($publicKey);
                    $draft->setDsId($dsId);

                    $this->em->persist($draft);
                    $this->em->flush();
                }
            }
        }

        $this->release();

        return null;
    }

    /**
     * @param $imagePath
     * @return bool|mixed
     */
    private function getImageMimeType($imagePath)
    {
        echo $imagePath . PHP_EOL;

        $mimes = array(
            IMAGETYPE_GIF => "image/gif",
            IMAGETYPE_JPEG => "image/jpeg",
            IMAGETYPE_PNG => "image/png",
            IMAGETYPE_SWF => "image/swf",
            IMAGETYPE_PSD => "image/psd",
            IMAGETYPE_BMP => "image/bmp",
            IMAGETYPE_TIFF_II => "image/tiff",
            IMAGETYPE_TIFF_MM => "image/tiff",
            IMAGETYPE_JPC => "image/jpc",
            IMAGETYPE_JP2 => "image/jp2",
            IMAGETYPE_JPX => "image/jpx",
            IMAGETYPE_JB2 => "image/jb2",
            IMAGETYPE_SWC => "image/swc",
            IMAGETYPE_IFF => "image/iff",
            IMAGETYPE_WBMP => "image/wbmp",
            IMAGETYPE_XBM => "image/xbm",
            IMAGETYPE_ICO => "image/ico");

        if (($image_type = @exif_imagetype($imagePath)) && (array_key_exists($image_type, $mimes))) {
            return $mimes[$image_type];
        } else {
            return FALSE;
        }
    }
}