<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 1/9/20
 * Time: 4:11 PM
 */

namespace App\DataFixtures;

use App\Entity\NetworkPage;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NetworkPageFixture extends Fixture implements FixtureGroupInterface
{
    const items = [
        'pbq' => 'PBQ Utility token',
        'publiq' => 'PUBLIQ Network',
        'publiq_daemon' => 'PUBLIQ Daemon',
        'publiq_daemon_mainnet' => 'Mainnet',
        'publiq_daemon_testnet' => 'Testnet',
        'showcase' => 'PUBLIQ Protocol dApps',
        'brand' => 'Brand',
        'docs' => 'Docs',
    ];

    public static function getGroups(): array
    {
        return ['networkPage'];
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
            $networkPageObj = $manager->getRepository(NetworkPage::class)->findOneBy(['slug' => $key]);
            if (!$networkPageObj) {
                $networkPageObj = new NetworkPage();

                $networkPageObj->setSlug($key);
                $networkPageObj->setTitle($content);

                $manager->persist($networkPageObj);
            }
        }
        $manager->flush();
    }
}