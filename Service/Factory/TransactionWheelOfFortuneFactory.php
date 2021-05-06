<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Games\Exception\GameNotEnabledException;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionMemberWheelOfFortune;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionModelWheelOfFortune;
use Yusmp\CoreComponent\Domain\Room\Entity\Room;
use Yusmp\CoreComponent\Domain\User\Entity\UserMember;
use Doctrine\ORM\EntityManagerInterface;

class TransactionWheelOfFortuneFactory
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function create(UserMember $user, Room $room): TransactionMemberWheelOfFortune
    {
        if (!$room->isWheelOfFortuneEnabled()) {
            throw new GameNotEnabledException('error.game-not-enabled');
        }

        $transaction = new TransactionMemberWheelOfFortune();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount(-$room->getWheelOfFortuneConfig()->getCost());
        $transaction->setTarget($room->getUser()->getAccount());
        $transaction->setRoom($room);

        $relatedTransaction = new TransactionModelWheelOfFortune();
        $relatedTransaction->setAccount($room->getUser()->getAccount());
        $relatedTransaction->setAmount($room->getWheelOfFortuneConfig()->getCost());
        $relatedTransaction->setSource($user->getAccount());
        $relatedTransaction->setRoom($room);
        $relatedTransaction->setRelatedTransaction($transaction);
        $transaction->setRelatedTransaction($relatedTransaction);

        $this->entityManager->persist($transaction);
        $this->entityManager->persist($relatedTransaction);
        $this->entityManager->flush();

        return $transaction;
    }
}
