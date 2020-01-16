<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/16/20
 * Time: 12:22 PM
 */

namespace App\DataFixtures;

use App\Entity\NetworkBrandCommunicationContent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkBrandCommunicationContentFixture extends Fixture implements FixtureGroupInterface
{
    const items = [
        'main' => 'Communication',
    ];

    public static function getGroups(): array
    {
        return ['networkBrandCommunicationContent'];
    }

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $contents = self::items;
        foreach ($contents as $key => $content) {
            $networkPageObj = $manager->getRepository(NetworkBrandCommunicationContent::class)->findOneBy(['slug' => $key]);
            if (!$networkPageObj) {
                $networkPageObj = new NetworkBrandCommunicationContent();

                $networkPageObj->setSlug($key);
                $networkPageObj->setTitle($content);

                $manager->persist($networkPageObj);
            }
        }
        $manager->flush();
    }
}