<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/16/20
 * Time: 12:00 PM
 */

namespace App\DataFixtures;

use App\Entity\NetworkBrandColourContent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkBrandColourContentFixture extends Fixture implements FixtureGroupInterface
{
    const items = [
        'main' => 'Colours',
        'secondary_colours' => 'Secondary Colours',
    ];

    public static function getGroups(): array
    {
        return ['networkBrandColourContent'];
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
            $networkPageObj = $manager->getRepository(NetworkBrandColourContent::class)->findOneBy(['slug' => $key]);
            if (!$networkPageObj) {
                $networkPageObj = new NetworkBrandColourContent();

                $networkPageObj->setSlug($key);
                $networkPageObj->setTitle($content);

                $manager->persist($networkPageObj);
            }
        }
        $manager->flush();
    }
}