<?php

namespace Yusmp\PaymentComponent\Service\Handler;

use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionMemberBuySocialField;
use Yusmp\CoreComponent\Domain\User\Entity\UserModel;
use Yusmp\CoreComponent\Domain\User\Entity\UserModelProfileAccess;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;
use Yusmp\PaymentComponent\Exception\PaymentException;
use Yusmp\PaymentComponent\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;

class BuySocialFieldHandler implements TransactionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private TransactionService $transactionService;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionService $transactionService
    ) {
        $this->entityManager = $entityManager;
        $this->transactionService = $transactionService;
    }

    public function supports(TransactionInterface $transaction): bool
    {
        return $transaction instanceof TransactionMemberBuySocialField;
    }

    public function handle(TransactionInterface $transaction)
    {
        assert($transaction instanceof TransactionMemberBuySocialField);
        $model = $transaction->getTarget()->getUser();
        assert($model instanceof UserModel);

        $access = new UserModelProfileAccess();
        $access->setBuyer($transaction->getAccount()->getUser());
        $access->setModel($model);
        $access->setField($transaction->getField());
        try {
            $this->entityManager->persist($access);
            $this->entityManager->flush();
        } catch (\Exception $ex) {
            throw new PaymentException('error.payment-duplicate', 0, $ex);
        }
    }
}
