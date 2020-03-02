<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 8/22/19
 * Time: 4:08 PM
 */

namespace App\Service;

use App\Entity\File;
use App\Entity\Account;
use App\Entity\UserViewLog;
use App\Entity\ContentUnit;
use App\Entity\UserViewLogHistory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPImageWorkshop\ImageWorkshop;
use Symfony\Component\HttpFoundation\Request;

class Custom
{
    /**
     * @var EntityManager
     */
    private $em;

    private $captchaSecretKey;
    private $thumbnailPath;

    function __construct(EntityManagerInterface $em, $captchaSecretKey, $thumbnailPath)
    {
        $this->em = $em;
        $this->captchaSecretKey = $captchaSecretKey;
        $this->thumbnailPath = $thumbnailPath;
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
     * @param File $file
     * @return array
     */
    public function getRandomFileStorage(File $file)
    {
        $randomStorage = null;

        /**
         * @var Account[] $fileStorages
         */
        $fileStorages = $file->getStorages();

        if (count($fileStorages)) {
            $fileStoragesSelected = [];

            foreach ($fileStorages as $fileStorage) {
                if ($fileStorage->getUrl() && $fileStorage->isStorage()) {
                    $fileStoragesSelected[] = $fileStorage;
                }
            }

            if (count($fileStoragesSelected) > 0) {
                $randomStorageIndex = rand(0, count($fileStoragesSelected) - 1);
                $randomStorage = $fileStoragesSelected[$randomStorageIndex];
            }
        }

        return $randomStorage;
    }

    /**
     * @param Request $request
     * @param ContentUnit $contentUnit
     * @return string
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function viewLog(Request $request, ContentUnit $contentUnit)
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
        $userInfo['language'] = $request->getPreferredLanguage();
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
        } else {
            if (($date->getTimestamp() - $viewLog->getDatetime()) > 3600) {
                $viewLog->setDatetime($date->getTimestamp());
            }
        }

        $this->em->persist($viewLog);

        //  insert data into history
        $viewLogHistory = new UserViewLogHistory();
        $viewLogHistory->setContentUnit($contentUnit);
        $viewLogHistory->setUserIdentifier($userIdentifier);
        $viewLogHistory->setIp($request->getClientIp());
        $viewLogHistory->setDatetime($date->getTimestamp());
        $this->em->persist($viewLogHistory);
        $this->em->flush();

        return $userIdentifier;
    }

    /**
     * @param string $token
     * @return boolean
     */
    public function verifyCaptcha(string $token)
    {
        $data = ['secret' => $this->captchaSecretKey, 'response' => $token];

        $ch = curl_init("https://www.google.com/recaptcha/api/siteverify");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result, true);

        return $result['success'];
    }

    /**
     * @param File $cover
     * @param string $relativePath
     * @return bool|string
     */
    function createThumbnail(File $cover, $relativePath = '')
    {
        $imagePath = $relativePath . $this->thumbnailPath;
        $imageName = $cover->getUri() . '-thumbnail-' . rand(1111, 9999) . '.jpg';

        try {
            /**
             * @var Account $channel
             */
            $channel = $this->em->getRepository(Account::class)->getCoverFirstChannel($cover);
            if ($channel && $channel->getUrl()) {
                $tempImage = $imagePath . '/temp_' . rand(1, 99999) . '.jpg';
                copy($channel->getUrl() . '/storage?file=' . $cover->getUri(), $tempImage);

                //  create instance of ImageWorkshop from cover
                $coverWorkshop = ImageWorkshop::initFromPath($tempImage);
                $coverWorkshop->resizeInPixel(600, null, true);
                $coverWorkshop->save($imagePath, $imageName, false, null, 80);

                if (isset($tempImage)) {
                    unlink($tempImage);
                }

                $cover->setThumbnail($this->thumbnailPath . '/' . $imageName);
                $cover->setThumbnailWidth($coverWorkshop->getWidth());
                $cover->setThumbnailHeight($coverWorkshop->getHeight());
                $this->em->persist($cover);
                $this->em->flush();
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}