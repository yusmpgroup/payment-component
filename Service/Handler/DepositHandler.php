<?php

namespace Yusmp\PaymentComponent\Service\Handler;

use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionDeposit;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry as Workflow;

class DepositHandler implements TransactionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private Workflow $workflows;

    public function __construct(
        EntityManagerInterface $entityManager,
        Workflow $workflows
    ) {
        $this->entityManager = $entityManager;
        $this->workflows = $workflows;
    }

    public function supports(TransactionInterface $transaction): bool
    {
        return $transaction instanceof TransactionDeposit;
    }

    public function handle(TransactionInterface $transaction)
    {
        assert($transaction instanceof TransactionDeposit);

        $payment = $transaction->getPayment();
        if ($payment != null) {
            $paymentWorkflow = $this->workflows->get($payment, 'payment');
            $paymentWorkflow->apply($payment, 'complete');
        }
    }
}
