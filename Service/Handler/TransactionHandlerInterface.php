<?php

namespace Yusmp\PaymentComponent\Service\Handler;

use Yusmp\CoreComponent\Domain\Payment\Entity\Transaction;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;

interface TransactionHandlerInterface
{
    public function supports(TransactionInterface $transaction): bool;

    public function handle(TransactionInterface $transaction);
}
