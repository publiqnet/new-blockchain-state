<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 4/9/19
 * Time: 5:17 PM
 */

namespace App\DataFixtures;

use App\Entity\NotificationType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\Persistence\ObjectManager;

class NotificationTypeFixture extends Fixture implements FixtureGroupInterface
{

    public static function getGroups(): array
    {
        return ['notificationType'];
    }

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $notificationTypes = NotificationType::TYPES;

        foreach ($notificationTypes as $notificationType) {
            $notificationTypeObj = $manager->getRepository(NotificationType::class)->findOneBy(['keyword' => $notificationType['key']]);
            if (!$notificationTypeObj) {
                $notificationTypeObj = new NotificationType();
                $notificationTypeObj->setKeyword($notificationType['key']);
                $notificationTypeObj->setBodyEn($notificationType['en']);
                $notificationTypeObj->setBodyEs($notificationType['es']);
                $notificationTypeObj->setBodyJp($notificationType['jp']);

                $manager->persist($notificationTypeObj);
            }
        }
        $manager->flush();
    }
}