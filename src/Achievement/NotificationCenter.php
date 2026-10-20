<?php

namespace App\Achievement;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée les notifications envoyées aux utilisateurs (la cloche du menu et les toasts).
 */
class NotificationCenter
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Prépare une notification ; elle est enregistrée au prochain flush.
     */
    public function notify(User $user, string $title, string $message, ?string $link = null, ?string $icon = null, ?string $tone = null): Notification
    {
        $notification = (new Notification())
            ->setUser($user)
            ->setTitle($title)
            ->setMessage($message)
            ->setLink($link)
            ->setIcon($icon)
            ->setTone($tone);
        $this->em->persist($notification);

        return $notification;
    }
}
