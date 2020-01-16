<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/16/20
 * Time: 12:08 PM
 */

namespace App\DataFixtures;

use App\Entity\NetworkBrandTypographyContent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkBrandTypographyContentFixture extends Fixture implements FixtureGroupInterface
{
    const items = [
        'main' => 'Typography',
        'styles' => 'Styles',
    ];

    public static function getGroups(): array
    {
        return ['networkBrandTypographyContent'];
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
            $networkPageObj = $manager->getRepository(NetworkBrandTypographyContent::class)->findOneBy(['slug' => $key]);
            if (!$networkPageObj) {
                $networkPageObj = new NetworkBrandTypographyContent();

                $networkPageObj->setSlug($key);
                $networkPageObj->setTitle($content);

                $manager->persist($networkPageObj);
            }
        }
        $manager->flush();
    }
}