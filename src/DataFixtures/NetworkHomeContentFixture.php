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
        'users' => ['title' => 'Users', 'route' => 'showcase'],
        'creatives' => ['title' => 'Creatives', 'route' => 'showcase'],
        'channel-nodes' => ['title' => 'Channel Nodes', 'route' => 'docs'],
        'blockchain-nodes' => ['title' => 'Blockchain Nodes', 'route' => 'docs'],
        'storage-nodes' => ['title' => 'Storage Nodes', 'route' => 'docs'],
        'advertisers' => ['title' => 'Advertisers', 'route' => 'showcase']
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
                $networkHomeContentObj->setTitle($content['title']);
                $networkHomeContentObj->setRoute($content['route']);

                $manager->persist($networkHomeContentObj);
            }
        }
        $manager->flush();
    }
}