<?php

namespace Yusmp\PaymentComponent\Service\Handler;

use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionMemberRollTheDice;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;
use Yusmp\CoreComponent\Services\Games\RollTheDiceService;

class RollTheDiceHandler implements TransactionHandlerInterface
{
    private RollTheDiceService $rollTheDice;

    public function __construct(
        RollTheDiceService $rollTheDice
    ) {
        $this->rollTheDice = $rollTheDice;
    }

    public function supports(TransactionInterface $transaction): bool
    {
        return $transaction instanceof TransactionMemberRollTheDice;
    }

    public function handle(TransactionInterface $transaction)
    {
        assert($transaction instanceof TransactionMemberRollTheDice);

        $rollTheDiceResult = $this->rollTheDice->roll($transaction->getUser(), $transaction->getRoom(), $transaction->getDiceNumber());
        $transaction->setRollTheDice($rollTheDiceResult);
    }
}
