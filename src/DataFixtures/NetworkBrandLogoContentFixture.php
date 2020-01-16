<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/16/20
 * Time: 11:43 AM
 */

namespace App\DataFixtures;

use App\Entity\NetworkBrandLogoContent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkBrandLogoContentFixture extends Fixture implements FixtureGroupInterface
{
    const items = [
        'main' => 'Logo',
        'glyph_wordmark' => 'Glyph & Wordmark',
        'alternatives' => 'Alternatives',
        'padding' => 'Padding',
        'no_no' => 'No-no\'s',
    ];

    public static function getGroups(): array
    {
        return ['networkBrandLogoContent'];
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
            $networkPageObj = $manager->getRepository(NetworkBrandLogoContent::class)->findOneBy(['slug' => $key]);
            if (!$networkPageObj) {
                $networkPageObj = new NetworkBrandLogoContent();

                $networkPageObj->setSlug($key);
                $networkPageObj->setTitle($content);

                $manager->persist($networkPageObj);
            }
        }
        $manager->flush();
    }
}