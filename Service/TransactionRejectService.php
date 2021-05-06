<?php

namespace Yusmp\PaymentComponent\Service;

use ApiPlatform\Core\Validator\ValidatorInterface;
use Yusmp\CoreComponent\Domain\Payment\Entity\Transaction;
use Yusmp\CoreComponent\Domain\Payment\Enum\TransactionStatusEnum;
use Yusmp\CoreComponent\Domain\Payment\Event\AccountBalanceChangedEvent;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;
use Yusmp\PaymentComponent\Exception\PaymentException;
use Yusmp\PaymentComponent\Interfaces\Rejectable;
use Yusmp\PaymentComponent\Service\Handler\TransactionHandlerInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TransactionRejectService extends TransactionService
{
    public function __construct(
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
        \Traversable $transactionHandlers,
        ValidatorInterface $validator,
        LoggerInterface $transactionLogger
    ) {
        $this->entityManager = $entityManager;
        // assert manager is ORM manager
        assert($this->entityManager instanceof EntityManager);
        $this->tokenStorage = $tokenStorage;
        $this->transactionHandlers = $transactionHandlers;
        $this->validator = $validator;
        $this->logger = $transactionLogger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function reject(TransactionInterface $transaction)
    {
        if (!($transaction instanceof Rejectable)) {
            throw new PaymentException('error.not-rejectable-transaction');
        }

        if (!$transaction->getStatus()->equals(TransactionStatusEnum::COMPLETE())) {
            throw new PaymentException('error.cant-reject-transaction');
        }

        $this->logger->debug($transaction->getId() . ': transaction reject begin');
        $prevBalance = $transaction->getAccount()->getBalance();

        $this->entityManager->beginTransaction();

        $this->executeTransactionReject($transaction);

        $this->entityManager->flush();
        $this->entityManager->commit();


        $this->entityManager->refresh($transaction->getAccount());
        $this->logger->debug(sprintf(
            '%s: %s transaction %d reject commited (%d -> %d)',
            $transaction->getId(),
            $transaction->getType(),
            $transaction->getAmount(),
            $prevBalance,
            $transaction->getAccount()->getBalance()
        ));
    }

    protected function executeTransactionReject(TransactionInterface $transaction): void
    {
        list($transaction, $account) = $this->reloadTransactionAndAccountWithBlocking($transaction);

        $this->logger->debug($transaction->getId() . ': start validation');
        // Валидацию вызываем до того как поменяли баланс

        $prevBalance = $account->getBalance();
        $newBalance = $account->getBalance() - $transaction->getAmount();
        $account->setBalance($newBalance);

        $this->logger->debug($transaction->getId() . ": validation passed, account #{$account->getId()} balance change: {$prevBalance} => {$newBalance}");

        $transaction->setStatus(TransactionStatusEnum::REJECTED());

        $this->eventDispatcher->dispatch(new AccountBalanceChangedEvent($account, $prevBalance, $transaction));

        $this->entityManager->persist($transaction);
        $this->entityManager->persist($account);

        $this->entityManager->flush();

        foreach ($this->transactionHandlers as $handler) {
            if ($handler->supports($transaction)) {
                $this->logger->debug($transaction->getId() . ': start calling handler '. get_class($handler));
                $handler->handle($transaction);
            }
        }
    }
}
