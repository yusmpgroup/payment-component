<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionHold;
use Yusmp\CoreComponent\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TransactionHoldFactory
{
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
    }

    public function createByAmount(User $user, int $amount): TransactionHold
    {
        $transaction = new TransactionHold();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount(-$amount);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }
}
