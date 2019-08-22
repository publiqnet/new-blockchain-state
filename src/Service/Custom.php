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

class Custom
{
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
}