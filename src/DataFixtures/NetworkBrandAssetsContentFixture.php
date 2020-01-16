<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/16/20
 * Time: 4:11 PM
 */

namespace App\DataFixtures;

use App\Entity\NetworkBrandAssetsContent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkBrandAssetsContentFixture extends Fixture implements FixtureGroupInterface
{
    const items = [
        'main' => 'Assets',
        'brand_logo' => 'Brand logo',
        'primary_font' => 'Primary font',
        'secondary_font' => 'Secondary font',
    ];

    public static function getGroups(): array
    {
        return ['networkBrandAssetsContent'];
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
            $networkPageObj = $manager->getRepository(NetworkBrandAssetsContent::class)->findOneBy(['slug' => $key]);
            if (!$networkPageObj) {
                $networkPageObj = new NetworkBrandAssetsContent();

                $networkPageObj->setSlug($key);
                $networkPageObj->setTitle($content);

                $manager->persist($networkPageObj);
            }
        }
        $manager->flush();
    }
}