<?php

namespace Yusmp\PaymentComponent\Interfaces;

use Yusmp\CoreComponent\Domain\Payment\Entity\Transaction;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;

interface RelatedAware
{
    public function getRelatedTransaction(): ?TransactionInterface;
    public function setRelatedTransaction(TransactionInterface $relatedTransaction);
}
