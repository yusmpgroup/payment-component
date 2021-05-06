<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionTip;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionTipped;
use Yusmp\CoreComponent\Domain\User\Entity\User;
use Yusmp\CoreComponent\Domain\User\Entity\UserModel;
use Doctrine\ORM\EntityManagerInterface;

class TransactionTipFactory
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function createByAmount(User $user, int $amount, UserModel $model): TransactionTip
    {
        $room = $model->getRoom();

        $transaction = new TransactionTip();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount(-$amount);
        $transaction->setTarget($model->getAccount());
        $transaction->setRoom($room);

        $relatedTransaction = new TransactionTipped();
        $relatedTransaction->setAccount($model->getAccount());
        $relatedTransaction->setAmount($amount);
        $relatedTransaction->setSource($user->getAccount());
        $relatedTransaction->setRelatedTransaction($transaction);
        $relatedTransaction->setRoom($room);
        $transaction->setRelatedTransaction($relatedTransaction);

        $this->entityManager->persist($transaction);
        $this->entityManager->persist($relatedTransaction);

        $this->entityManager->flush();

        return $transaction;
    }
}
