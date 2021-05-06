<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Album\Enum\AlbumTypeEnum;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionMemberBuyAlbum;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionModelSoldAlbum;
use Yusmp\CoreComponent\Domain\Settings\Service\SettingsService;
use Yusmp\CoreComponent\Domain\User\Entity\UserMember;
use Doctrine\ORM\EntityManagerInterface;
use Yusmp\CoreComponent\Domain\Album\Entity\Album;

class TransactionBuyAlbumFactory
{
    private EntityManagerInterface $entityManager;
    private SettingsService $settingsService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SettingsService $settingsService
    ) {
        $this->entityManager = $entityManager;
        $this->settingsService = $settingsService;
    }

    public function create(UserMember $user, Album $album): TransactionMemberBuyAlbum
    {
        $room = $album->getUser()->getRoom();
        $transaction = new TransactionMemberBuyAlbum();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount(-$album->getPrice());
        $transaction->setTarget($album->getUser()->getAccount());
        $transaction->setAlbum($album);
        $transaction->setRoom($room);

        if ($album->getType()->equals(AlbumTypeEnum::VIDEO())) {
            $commission = $this->settingsService->commissionVideo();
        } else {
            $commission = $this->settingsService->commissionPhoto();
        }

        $commissionValue = ceil($album->getPrice() * ((float) $commission / 100));
        $amountValue = $album->getPrice() - $commissionValue;

        $relatedTransaction = new TransactionModelSoldAlbum();
        $relatedTransaction->setAccount($album->getUser()->getAccount());
        $relatedTransaction->setAmount($amountValue);
        $relatedTransaction->setCommission($commissionValue);
        $relatedTransaction->setSource($user->getAccount());
        $relatedTransaction->setAlbum($album);
        $relatedTransaction->setRoom($room);
        $relatedTransaction->setRelatedTransaction($transaction);
        $transaction->setRelatedTransaction($relatedTransaction);

        $this->entityManager->persist($transaction);
        $this->entityManager->persist($relatedTransaction);
        $this->entityManager->flush();

        return $transaction;
    }
}
