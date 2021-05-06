<?php

namespace Yusmp\PaymentComponent\Service\Handler;

use Yusmp\CoreComponent\Domain\Album\Entity\AlbumUserAccess;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionMemberBuyAlbum;
use Yusmp\CoreComponent\Domain\User\Entity\UserMember;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;
use Yusmp\PaymentComponent\Exception\PaymentException;
use Yusmp\PaymentComponent\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;

class BuyAlbumHandler implements TransactionHandlerInterface
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
        return $transaction instanceof TransactionMemberBuyAlbum;
    }

    public function handle(TransactionInterface $transaction)
    {
        assert($transaction instanceof TransactionMemberBuyAlbum);
        $access = new AlbumUserAccess();
        $user = $transaction->getAccount()->getUser();
        assert($user instanceof UserMember);

        $access->setBuyer($user);
        $access->setAlbum($transaction->getAlbum());
        try {
            $this->entityManager->persist($access);
            $this->entityManager->flush();
        } catch (\Exception $ex) {
            throw new PaymentException('error.payment-duplicate', 0, $ex);
        }
    }
}
