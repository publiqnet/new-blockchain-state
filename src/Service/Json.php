<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 9/9/19
 * Time: 11:05 AM
 */

namespace App\Service;

use App\Entity\Dictionary;
use Doctrine\ORM\EntityManager;

class Json
{
    private $em;
    private $folderPath;

    public function __construct(EntityManager $em, string $folderPath)
    {
        $this->em = $em;
        $this->folderPath = $folderPath;
    }

    public function updateJsons($locale)
    {
        //  en
        $jsonArray = [];

        $dictionaries = $this->em->getRepository(Dictionary::class)->findAll();
        /**
         * @var Dictionary $dictionary
         */
        foreach ($dictionaries as $dictionary) {
            $key = $dictionary->getWordKey();
            $key = explode('.', $key);

            switch (count($key)) {
                case 2: $jsonArray[$key[0]][$key[1]] = $dictionary->getValue(); break;
                case 3: $jsonArray[$key[0]][$key[1]][$key[2]] = $dictionary->getValue(); break;
                case 4: $jsonArray[$key[0]][$key[1]][$key[2]][$key[3]] = $dictionary->getValue(); break;
                default: $jsonArray[$key[0]] = $dictionary->getValue();
            }
        }
        $json = json_encode($jsonArray, JSON_UNESCAPED_UNICODE);

        file_put_contents($this->folderPath . $locale . '.json', $json);
    }
}