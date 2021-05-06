<?php

namespace Yusmp\PaymentComponent\Event;

use Yusmp\CoreComponent\Domain\Payment\Entity\Transaction;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;
use Symfony\Contracts\EventDispatcher\Event as ContractsEvent;

class TransactionDoneEvent extends ContractsEvent
{
    private TransactionInterface $transaction;

    public function __construct(TransactionInterface $transaction)
    {
        $this->transaction = $transaction;
    }

    public function getTransaction(): TransactionInterface
    {
        return $this->transaction;
    }

    public function setTransaction(TransactionInterface $transaction): self
    {
        $this->transaction = $transaction;
        return $this;
    }
}
