<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 12/26/19
 * Time: 7:35 PM
 */

namespace App\DataFixtures;

use App\Entity\NetworkHomeContent;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkHomeContentFixture extends Fixture implements FixtureGroupInterface
{
    const items = [
        'users' => 'Users',
        'channel-nodes' => 'Channel Nodes',
        'blockchain-nodes' => 'Blockchain Nodes',
        'storage-nodes' => 'Storage Nodes',
        'advertisers' => 'Advertisers'
    ];

    public static function getGroups(): array
    {
        return ['networkHomeContent'];
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
            $networkHomeContentObj = $manager->getRepository(NetworkHomeContent::class)->findOneBy(['slug' => $key]);
            if (!$networkHomeContentObj) {
                $networkHomeContentObj = new NetworkHomeContent();

                $networkHomeContentObj->setSlug($key);
                $networkHomeContentObj->setTitle($content);

                $manager->persist($networkHomeContentObj);
            }
        }
        $manager->flush();
    }
}