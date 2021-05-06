<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Games\Exception\GameNotEnabledException;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionMemberRollTheDice;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionModelRollTheDice;
use Yusmp\CoreComponent\Domain\Room\Entity\Room;
use Yusmp\CoreComponent\Domain\User\Entity\UserMember;
use Doctrine\ORM\EntityManagerInterface;

class TransactionRollTheDiceFactory
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function create(UserMember $user, Room $room, int $diceNumber): TransactionMemberRollTheDice
    {
        if (!$room->isRollTheDiceEnabled()) {
            throw new GameNotEnabledException('error.game-not-enabled');
        }

        $amount = $diceNumber == 2 ?
            $room->getRollTheDiceConfig()->getCost2() :
            $room->getRollTheDiceConfig()->getCost()
        ;

        $memberAccount = $user->getAccount();
        $modelAccount = $room->getUser()->getAccount();

        $transaction = new TransactionMemberRollTheDice();
        $transaction->setAccount($memberAccount);
        $transaction->setTarget($modelAccount);
        $transaction->setAmount(-$amount);
        $transaction->setRoom($room);
        $transaction->setDiceNumber($diceNumber);

        $relatedTransaction = new TransactionModelRollTheDice();
        $relatedTransaction->setAccount($modelAccount);
        $relatedTransaction->setSource($memberAccount);
        $relatedTransaction->setAmount($amount);
        $relatedTransaction->setRoom($room);
        $relatedTransaction->setRelatedTransaction($transaction);
        $transaction->setRelatedTransaction($relatedTransaction);

        $this->entityManager->persist($transaction);
        $this->entityManager->persist($relatedTransaction);
        $this->entityManager->flush();

        return $transaction;
    }
}
