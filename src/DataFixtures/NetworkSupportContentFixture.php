<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/27/19
 * Time: 1:07 PM
 */

namespace App\DataFixtures;

use App\Entity\NetworkSupportContent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkSupportContentFixture extends Fixture implements FixtureGroupInterface
{
    const items = [
        'privacy' => 'Privacy',
        'terms' => 'Terms',
        'cookies' => 'Cookies'
    ];

    public static function getGroups(): array
    {
        return ['networkSupportContent'];
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
            $networkHomeContentObj = $manager->getRepository(NetworkSupportContent::class)->findOneBy(['slug' => $key]);
            if (!$networkHomeContentObj) {
                $networkHomeContentObj = new NetworkSupportContent();

                $networkHomeContentObj->setSlug($key);
                $networkHomeContentObj->setTitle($content);

                $manager->persist($networkHomeContentObj);
            }
        }
        $manager->flush();
    }
}