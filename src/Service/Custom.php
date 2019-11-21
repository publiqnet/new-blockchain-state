<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/22/19
 * Time: 4:08 PM
 */

namespace App\Service;

use App\Entity\Block;
use App\Entity\File;
use App\Entity\Account;
use App\Entity\Publication;
use App\Entity\Transaction;
use App\Entity\UserViewLog;
use App\Entity\ContentUnit;
use App\Entity\UserViewLogHistory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPImageWorkshop\ImageWorkshop;
use Symfony\Component\HttpFoundation\Request;

class Custom
{
    const FB_CLIENT_ID = '1949989915051606';
    const FB_CLIENT_SECRET = 'e9ea0e9284f61ac66d538ae56bbebab3';

    /**
     * @var EntityManager
     */
    private $em;

    private $endpoint;
    private $dsEndpoint;
    private $oldBackendEndpoint;
    private $socialAssetsPath;
    private $socialImagePath;
    private $thumbnailPath;
    private $channelStorageEndpoint;
    private $frontendEndpoint;

    function __construct(EntityManagerInterface $em, $endpoint, $dsEndpoint, $oldBackendEndpoint, $socialAssetsPath, $socialImagePath, $thumbnailPath, $channelStorageEndpoint, $frontendEndpoint)
    {
        $this->em = $em;
        $this->endpoint = $endpoint;
        $this->dsEndpoint = $dsEndpoint;
        $this->oldBackendEndpoint = $oldBackendEndpoint;
        $this->socialAssetsPath = $socialAssetsPath;
        $this->socialImagePath = $socialImagePath;
        $this->thumbnailPath = $thumbnailPath;
        $this->channelStorageEndpoint = $channelStorageEndpoint;
        $this->frontendEndpoint = $frontendEndpoint;
    }

    /**
     * @param File $file
     * @return array
     */
    public function getFileStoragesWithPublicAccess(File $file)
    {
        $fileStoragesWithPublicAccess = [];

        /**
         * @var Account[] $fileStorages
         */
        $fileStorages = $file->getStorages();

        if (count($fileStorages)) {
            foreach ($fileStorages as $fileStorage) {
                if ($fileStorage->getUrl()) {
                    $fileStoragesWithPublicAccess[] = $fileStorage;
                }
            }
        }

        return $fileStoragesWithPublicAccess;
    }

    /**
     * @param string $email
     * @return bool|string
     * @throws \Exception
     */
    public function getOldPublicKey($email)
    {
        $ch = curl_init($this->oldBackendEndpoint . '/' . $email);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type:application/json']
        );

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $data = json_decode($body, true);

        if ($headerStatusCode == 200) {
            return $data['publicKey'];
        }

        return false;
    }

    /**
     * @param string $from
     * @return bool|string
     * @throws \Exception
     */
    public function searchAuthors($from = '0.0.0')
    {
        $dataString = sprintf('{"id":1, "method":"call", "params":[0, "search_accounts", ["PBQ","","%s",1000]]}', $from);

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type:application/json', 'Content-Length: ' . strlen($dataString)]
        );

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $data = json_decode($body, true);

        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Connection failed: searchAuthors');
        }

        return $data['result'];
    }

    /**
     * @param $publicKey
     * @return bool|string
     * @throws \Exception
     * @return array
     */
    public function getAuthorArticles($publicKey)
    {
        $dataString = sprintf('{"id":1, "method":"call", "params":[0, "search_content", ["","",[],"","%s","","","-1"]]}', $publicKey);

        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type:application/json', 'Content-Length: ' . strlen($dataString)]
        );

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $data = json_decode($body, true);

        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Connection failed: getAuthorArticles');
        }

        return $data['result'];
    }

    /**
     * @param $articleId
     * @return array|string
     * @throws \Exception
     * @return array
     */
    public function getArticle($articleId)
    {
        $ch = curl_init($this->dsEndpoint . '/' . $articleId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,
            ['Content-Type:application/json']
        );

        $response = curl_exec($ch);

        $headerStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $data = json_decode($body, true);

        //  check for errors
        if ($headerStatusCode != 200 || isset($data['error'])) {
            throw new \Exception('Connection failed: getArticle');
        }

        return $data['content']['data'];
    }

    /**
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function getFee()
    {
        /**
         * @var Block $block
         */
        $block = $this->em->getRepository(Block::class)->getLastBlock();
        if ($block->getFeeWhole() === null) {
            $feeWhole = 0;
            $feeFraction = 0;

            /**
             * @var Transaction[] $transactions
             */
            $transactions = $block->getTransactions();
            if (count($transactions)) {
                foreach ($transactions as $transaction) {
                    $feeWhole += $transaction->getFeeWhole();
                    $feeFraction += $transaction->getFeeFraction();
                }

                if ($feeFraction > 99999999) {
                    while ($feeFraction > 99999999) {
                        $feeWhole++;
                        $feeFraction -= 100000000;
                    }
                }

                //  calculate average fee
                $fee = $feeWhole + $feeFraction / 100000000;
                $transactionsCount = count($transactions);
                $averageFee = $fee * 100000000 / $transactionsCount;

                $feeWhole = floor($averageFee / 100000000);
                $feeFraction = $averageFee % 100000000;
            }

            $block->setFeeWhole(intval($feeWhole));
            $block->setFeeFraction(intval($feeFraction));

            $this->em->persist($block);
            $this->em->flush();
        } else {
            $feeWhole = $block->getFeeWhole();
            $feeFraction = $block->getFeeFraction();
        }

        return [$feeWhole, $feeFraction];
    }

    /**
     * @param Request $request
     * @param ContentUnit $contentUnit
     * @param $account
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function viewLog(Request $request, ContentUnit $contentUnit, $account)
    {
        //  generate fingerprint by request
        $userInfo = [];
        $userInfo['userAgent'] = $request->headers->get('User-Agent');
        $userInfo['acceptableContentTypes'] = $request->getAcceptableContentTypes();
        $userInfo['clientIp'] = $request->getClientIp();
        $userInfo['mimeType'] = $request->getMimeType('string');
        $userInfo['charset'] = $request->getCharsets();
        $userInfo['encodings'] = $request->getEncodings();
        $userInfo['userInfo'] = $request->getUserInfo();
        $userIdentifier = md5(serialize($userInfo));

        $date = new \DateTime();
        $timezone = new \DateTimeZone('UTC');
        $date->setTimezone($timezone);

        $viewLog = $this->em->getRepository(UserViewLog::class)->findOneBy(['userIdentifier' => $userIdentifier, 'contentUnit' => $contentUnit]);
        if (!$viewLog) {
            $viewLog = new UserViewLog();
            $viewLog->setContentUnit($contentUnit);
            $viewLog->setUserIdentifier($userIdentifier);
            $viewLog->setDatetime($date->getTimestamp());

            $addView = true;
        } else {
            if (($date->getTimestamp() - $viewLog->getDatetime()) > 3600) {
                $viewLog->setDatetime($date->getTimestamp());

                $addView = true;
            } else {
                $addView = false;
            }
        }

        if ($account) {
            $viewLog->setUser($account);
        }
        $this->em->persist($viewLog);

        //  insert data into history
        $viewLogHistory = new UserViewLogHistory();
        $viewLogHistory->setContentUnit($contentUnit);
        $viewLogHistory->setUserIdentifier($userIdentifier);
        $viewLogHistory->setIp($request->getClientIp());
        $viewLogHistory->setDatetime($date->getTimestamp());
        if ($account) {
            $viewLogHistory->setUser($account);
        }
        $this->em->persist($viewLogHistory);
        $this->em->flush();

        return $addView;
    }

    /**
     * @param ContentUnit $contentUnit
     * @param string $relativePath
     * @return bool|string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \PHPImageWorkshop\Core\Exception\ImageWorkshopLayerException
     * @throws \PHPImageWorkshop\Exception\ImageWorkshopException
     * @throws \Exception
     */
    function createSocialImageOfArticle(ContentUnit $contentUnit, $relativePath = '')
    {
        $imagePath = $relativePath . $this->socialImagePath;
        $assetsPath = $relativePath . $this->socialAssetsPath;

        $font = $assetsPath . '/OpenSansCondensed-Bold.ttf';

        /**
         * @var Account $author
         */
        $author = $contentUnit->getAuthor();
        $authorName = trim($author->getFirstName() . ' ' . $author->getLastName());
        $authorBio = trim($author->getBio());

        $authorName = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $authorName);
        $authorBio = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $authorBio);

        if ($contentUnit->getCover() && $contentUnit->getCover()->getMimeType() != 'image/webp' && $contentUnit->getCover()->getMimeType() != 'image/gif') {
            /**
             * @var File $cover
             */
            $cover = $contentUnit->getCover();

            $tempImage = $imagePath . '/temp_' . rand(1, 99999) . '.jpg';
            copy($this->channelStorageEndpoint . '/storage?file=' . $cover->getUri(), $tempImage);

            //  COVER MANIPULATION
            //  create instance of ImageWorkshop from cover
            $coverWorkshop = ImageWorkshop::initFromPath($tempImage);

            //  resize cover width 1280px, height 670px
            $coverWorkshop->resizeInPixel(1280, null, true);
            if ($coverWorkshop->getHeight() < 670) {
                $coverWorkshop->resizeInPixel(null, 670, true);
            }

            //  crop
            if ($coverWorkshop->getWidth() == 1280) {
                $coverWorkshop->cropInPixel(1280, 670, 0, 0, 'LT');
            } else {
                $coverWorkshop->cropInPixel(1280, 670, ($coverWorkshop->getWidth() - 1280) / 2, 0, 'LT');
            }

            //  add main background
            $backgroundWorkshop = ImageWorkshop::initFromPath($assetsPath . '/background.png');
            $coverWorkshop->addLayerOnTop($backgroundWorkshop, 24, 24, 'LT');
        } else {
            //  COVER MANIPULATION
            //  create instance of ImageWorkshop for cover
            $coverWorkshop = ImageWorkshop::initVirginLayer(1280, 218, '3366FF');
        }

        //  add logo
        $logoWorkshop = ImageWorkshop::initFromPath($assetsPath . '/logo.png');
        $coverWorkshop->addLayerOnTop($logoWorkshop, 50, 50, 'RT');

        //  create author name & bio layers
        $authorNameLayer = ImageWorkshop::initTextLayer($authorName, $font, 32);
        if ($authorBio) {
            $authorBioLayer = ImageWorkshop::initTextLayer($authorBio, $font, 22);
        }

        //  CREATE SOCIAL IMAGE
        $socialImageName = 'article-' . md5(random_bytes(128)) . '.jpg';

        $authorImageMimeType = '';
        if ($author->getImage()) {
            $authorImage = new \Symfony\Component\HttpFoundation\File\File($relativePath . $author->getImage());
            $authorImageMimeType = $authorImage->getMimeType();
        }

        if ($author->getImage() && $authorImageMimeType != 'image/webp') {
            //  add author name & bio
            $coverWorkshop->addLayerOnTop($authorNameLayer, 200, 60, 'LT');
            if (isset($authorBioLayer)) {
                $coverWorkshop->addLayerOnTop($authorBioLayer, 200, 120, 'LT');
            }

            $coverWorkshop->save($imagePath, $socialImageName, false, null, 99);

            //  AUTHOR IMAGE MANIPULATION
            $authorImageWorkshop = ImageWorkshop::initFromPath($relativePath . $author->getImage());

            //  resize
            if ($authorImageWorkshop->getWidth() > $authorImageWorkshop->getHeight()) {
                $authorImageWorkshop->cropInPixel($authorImageWorkshop->getHeight(), $authorImageWorkshop->getHeight(), ($authorImageWorkshop->getWidth() - $authorImageWorkshop->getHeight()) / 2, 0, 'LT');
            } else {
                $authorImageWorkshop->cropInPixel($authorImageWorkshop->getWidth(), $authorImageWorkshop->getWidth(), 0, 0, 'LT');
            }
            $authorImageWorkshop->resizeInPixel(130, 130);

            $authorImageName = $author->getId() . '-author.jpg';
            $authorImageWorkshop->save($imagePath, $authorImageName, false, null, 99);

            $this->imageCreateCorners($imagePath . '/' . $authorImageName, $imagePath . '/' . $socialImageName, 48, 44);
            unlink($imagePath . '/' . $authorImageName);
        } else {
            //  add author name & bio
            if (isset($authorBioLayer)) {
                $coverWorkshop->addLayerOnTop($authorNameLayer, 48, 66, 'LT');
                $coverWorkshop->addLayerOnTop($authorBioLayer, 48, 120, 'LT');
            } else {
                $coverWorkshop->addLayerOnTop($authorNameLayer, 48, 88, 'LT');
            }

            $coverWorkshop->save($imagePath, $socialImageName, false, null, 99);
        }

        if (isset($tempImage)) {
            unlink($tempImage);
        }

        //  delete old image if exist
        if ($contentUnit->getSocialImage() && file_exists($imagePath . '/' . $contentUnit->getSocialImage())) {
            unlink($imagePath . '/' . $contentUnit->getSocialImage());
        }

        $contentUnit->setSocialImage($this->socialImagePath . '/' . $socialImageName);
        $contentUnit->setUpdateSocialImage(false);
        $this->em->persist($contentUnit);
        $this->em->flush();

        $articleUrl = $this->frontendEndpoint . '/s/' . $contentUnit->getUri();
        $this->scrapeUrl($articleUrl);

        return true;
    }

    /**
     * @param Publication $publication
     * @param string $relativePath
     * @return bool|string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \PHPImageWorkshop\Core\Exception\ImageWorkshopLayerException
     * @throws \PHPImageWorkshop\Exception\ImageWorkshopException
     * @throws \Exception
     */
    function createSocialImageOfPublication(Publication $publication, $relativePath = '')
    {
        $imagePath = $relativePath . $this->socialImagePath;
        $assetsPath = $relativePath . $this->socialAssetsPath;

        $font = $assetsPath . '/OpenSansCondensed-Bold.ttf';

        $publicationTitle = trim($publication->getTitle());
        $authorDescription = trim($publication->getDescription());

        $publicationTitle = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $publicationTitle);
        $authorDescription = preg_replace(array('/\s{2,}/', '/[\t\n]/'), ' ', $authorDescription);

        if ($publication->getCover()) {
            $cover = $publication->getCover();

            $tempImage = $imagePath . '/temp_' . rand(1, 99999) . '.jpg';
            copy($relativePath . $cover, $tempImage);

            //  COVER MANIPULATION
            //  create instance of ImageWorkshop from cover
            $coverWorkshop = ImageWorkshop::initFromPath($tempImage);

            //  resize cover width 1280px, height 670px
            $coverWorkshop->resizeInPixel(1280, null, true);
            if ($coverWorkshop->getHeight() < 670) {
                $coverWorkshop->resizeInPixel(null, 670, true);
            }

            //  crop
            if ($coverWorkshop->getWidth() == 1280) {
                $coverWorkshop->cropInPixel(1280, 670, 0, 0, 'LT');
            } else {
                $coverWorkshop->cropInPixel(1280, 670, ($coverWorkshop->getWidth() - 1280) / 2, 0, 'LT');
            }

            //  add main background
            $backgroundWorkshop = ImageWorkshop::initFromPath($assetsPath . '/background.png');
            $coverWorkshop->addLayerOnTop($backgroundWorkshop, 24, 24, 'LT');
        } else {
            //  COVER MANIPULATION
            //  create instance of ImageWorkshop for cover
            $coverWorkshop = ImageWorkshop::initVirginLayer(1280, 218, '3366FF');
        }

        //  add logo
        $logoWorkshop = ImageWorkshop::initFromPath($assetsPath . '/logo.png');
        $coverWorkshop->addLayerOnTop($logoWorkshop, 50, 50, 'RT');

        //  create author name & bio layers
        $authorNameLayer = ImageWorkshop::initTextLayer($publicationTitle, $font, 32);
        if ($authorDescription) {
            $authorBioLayer = ImageWorkshop::initTextLayer($authorDescription, $font, 22);
        }

        //  CREATE SOCIAL IMAGE
        $socialImageName = 'publication-' . md5(random_bytes(128)) . '.jpg';
        if ($publication->getLogo()) {
            //  add author name & bio
            $coverWorkshop->addLayerOnTop($authorNameLayer, 200, 60, 'LT');
            if (isset($authorBioLayer)) {
                $coverWorkshop->addLayerOnTop($authorBioLayer, 200, 120, 'LT');
            }

            $coverWorkshop->save($imagePath, $socialImageName, false, null, 99);

            //  AUTHOR IMAGE MANIPULATION
            $authorImageWorkshop = ImageWorkshop::initFromPath($relativePath . $publication->getLogo());

            //  resize
            if ($authorImageWorkshop->getWidth() > $authorImageWorkshop->getHeight()) {
                $authorImageWorkshop->cropInPixel($authorImageWorkshop->getHeight(), $authorImageWorkshop->getHeight(), ($authorImageWorkshop->getWidth() - $authorImageWorkshop->getHeight()) / 2, 0, 'LT');
            } else {
                $authorImageWorkshop->cropInPixel($authorImageWorkshop->getWidth(), $authorImageWorkshop->getWidth(), 0, 0, 'LT');
            }
            $authorImageWorkshop->resizeInPixel(130, 130);

            $authorImageName = $publication->getId() . '-publication.jpg';
            $authorImageWorkshop->save($imagePath, $authorImageName, false, null, 99);

            $this->imageCreateCorners($imagePath . '/' . $authorImageName, $imagePath . '/' . $socialImageName, 48, 44);
            unlink($imagePath . '/' . $authorImageName);
        } else {
            //  add author name & bio
            if (isset($authorBioLayer)) {
                $coverWorkshop->addLayerOnTop($authorNameLayer, 48, 66, 'LT');
                $coverWorkshop->addLayerOnTop($authorBioLayer, 48, 120, 'LT');
            } else {
                $coverWorkshop->addLayerOnTop($authorNameLayer, 48, 88, 'LT');
            }

            $coverWorkshop->save($imagePath, $socialImageName, false, null, 99);
        }

        if (isset($tempImage)) {
            unlink($tempImage);
        }

        //  delete old image if exist
        if ($publication->getSocialImage() && file_exists($imagePath . '/' . $publication->getSocialImage())) {
            unlink($imagePath . '/' . $publication->getSocialImage());
        }

        $publication->setSocialImage($this->socialImagePath . '/' . $socialImageName);
        $this->em->persist($publication);
        $this->em->flush();

        return true;
    }

    /**
     * @param File $cover
     * @param string $relativePath
     * @return bool|string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \PHPImageWorkshop\Core\Exception\ImageWorkshopLayerException
     * @throws \PHPImageWorkshop\Exception\ImageWorkshopException
     */
    function createThumbnail(File $cover, $relativePath = '')
    {
        $imagePath = $relativePath . $this->thumbnailPath;
        $imageName = $cover->getUri() . '-thumbnail.jpg';

        try {
            $tempImage = $imagePath . '/temp_' . rand(1, 99999) . '.jpg';
            copy($this->channelStorageEndpoint . '/storage?file=' . $cover->getUri(), $tempImage);

            //  create instance of ImageWorkshop from cover
            $coverWorkshop = ImageWorkshop::initFromPath($tempImage);
            $coverWorkshop->resizeInPixel(300, null, true);
            $coverWorkshop->save($imagePath, $imageName, false, null, 60);

            if (isset($tempImage)) {
                unlink($tempImage);
            }

            $cover->setThumbnail($this->thumbnailPath . '/' . $imageName);
            $this->em->persist($cover);
            $this->em->flush();
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    function imageCreateCorners($sourceImageFile, $destImageFile, $posX, $posY)
    {
        $info = getimagesize($destImageFile);

        // create destination image resource.
        switch ($info['mime']) {
            case 'image/jpeg':
                $dest = imagecreatefromjpeg($destImageFile);
                break;
            case 'image/gif':
                $dest = imagecreatefromgif($destImageFile);
                break;
            case 'image/png':
                $dest = imagecreatefrompng($destImageFile);
                break;
            default:
                return false;
        }

        // create source image resource and define transparent colour.
        $info = getimagesize($sourceImageFile);
        switch ($info['mime']) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($sourceImageFile);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($sourceImageFile);
                break;
            case 'image/png':
                $src = imagecreatefrompng($sourceImageFile);
                break;
            default:
                return false;
        }

        $src_width = imagesx($src);
        $src_height = imagesy($src);
        imagecolortransparent($src, imagecolorallocate($src, 255, 0, 255));

        // create a circular mask and use it to crop the source image.
        $mask = imagecreatetruecolor($src_width, $src_height);
        $black = imagecolorallocate($mask, 0, 0, 0);
        $magenta = imagecolorallocate($mask, 255, 0, 255);
        imagefill($mask, 0, 0, $magenta);
        $r = min($src_width, $src_height);
        imagefilledellipse($mask, ($src_width / 2), ($src_height / 2), $r, $r, $black);
        imagecolortransparent($mask, $black);
        imagecopymerge($src, $mask, 0, 0, 0, 0, $src_width, $src_height, 100);
        imagedestroy($mask);

        imagecopymerge($dest, $src, $posX, $posY, 0, 0, $src_width, $src_height, 100);

        $info = getimagesize($destImageFile);
        switch ($info['mime']) {
            case 'image/jpeg':
                imagejpeg($dest, $destImageFile, 100);
                break;
            case 'image/gif':
                imagegif($dest, $destImageFile);
                break;
            case 'image/png':
                imagepng($dest, $destImageFile, 9);
                break;
        }

        return true;
    }

    /**
     * @param String $link
     * @return bool
     */
    public function scrapeUrl(String $link)
    {
        $url = 'https://graph.facebook.com/oauth/access_token?client_id=' . self::FB_CLIENT_ID . '&client_secret=' . self::FB_CLIENT_SECRET . '&grant_type=client_credentials';
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Codular Sample cURL Request'
        ));
        $resp = curl_exec($curl);
        curl_close($curl);

        $fbAccessToken = '';
        if ($resp) {
            $result = json_decode($resp);

            if ($result->access_token) {
                $fbAccessToken = $result->access_token;
            }
        }

        if (!$fbAccessToken) {
            return false;
        }

        $url = 'https://graph.facebook.com/v3.1/';
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Codular Sample cURL Request',
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => ['scrape' => true, 'access_token' => $fbAccessToken, 'id' => $link]
        ]);
        $resp = curl_exec($curl);
        curl_close($curl);

        return $resp ? true : false;
    }
}