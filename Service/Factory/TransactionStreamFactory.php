<?php
declare(strict_types=1);

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Payment\Entity\StreamMicroTransaction;
use Yusmp\CoreComponent\Domain\Payment\Entity\StreamTransactionDeposit;
use Yusmp\CoreComponent\Domain\Payment\Entity\StreamTransactionWithdraw;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionStream;
use Yusmp\CoreComponent\Domain\Payment\Enum\TransactionStatusEnum;
use Yusmp\CoreComponent\Domain\Room\Entity\Room;
use Yusmp\CoreComponent\Domain\Room\Entity\StreamSession;
use Yusmp\CoreComponent\Domain\Room\Entity\StreamViewSession;
use Yusmp\CoreComponent\Domain\Room\Enum\StreamTransactionTypeEnum;
use Yusmp\CoreComponent\Domain\User\Entity\UserMember;
use Yusmp\CoreComponent\Domain\User\Entity\UserModel;
use Doctrine\ORM\EntityManagerInterface;

class TransactionStreamFactory
{
    public function create(int $amount, int $frame, int $billedFrame, StreamViewSession $viewSession): StreamMicroTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException(sprintf('Expected amount to be gt 0, given %s for view session %s, frame %d, billed frame %d',
                $amount,
                $viewSession->getId(),
                $frame,
                $billedFrame
            ));
        }
        $transaction = new StreamMicroTransaction();

        $member = $viewSession->getUser();
        assert($member instanceof UserMember);

        $model = $viewSession->getStreamable()->getUser();
        assert($model instanceof UserModel);

        $transaction->setAccount($member->getAccount());
        $transaction->setAmount(-$amount);
        $transaction->setTarget($model->getAccount());
        $transaction->setFrame($frame);
        $transaction->setStream($viewSession->getSession());
        $transaction->setViewer($viewSession);
        $transaction->setRoom($viewSession->getSession()->getRoom());
        $transaction->setBilledFrame($billedFrame);
        $transaction->setStatus(TransactionStatusEnum::COMPLETE());

        $relatedTransaction = new StreamMicroTransaction();
        $relatedTransaction->setAccount($model->getAccount());
        $relatedTransaction->setAmount($amount);
        $relatedTransaction->setRoom($viewSession->getSession()->getRoom());
        $relatedTransaction->setStream($viewSession->getSession());
        $relatedTransaction->setViewer($viewSession);
        $relatedTransaction->setSource($member->getAccount());
        $relatedTransaction->setFrame($frame);
        $relatedTransaction->setBilledFrame($billedFrame);
        $relatedTransaction->setStatus(TransactionStatusEnum::COMPLETE());
        $relatedTransaction->setRelatedTransaction($transaction);
        $transaction->setRelatedTransaction($relatedTransaction);


        return $transaction;
    }

    public function createHold(int $amount, int $frame, int $billedFrame, StreamViewSession $viewSession): StreamMicroTransaction
    {
        // @TODO: Убрать relatedTransaction у брони?
        $transaction = $this->create($amount, $frame, $billedFrame, $viewSession);
        $transaction->setIsHold(true);
        $transaction->getRelatedTransaction()->setIsHold(true);
        return $transaction;
    }

    /**
     * @param StreamSession $session
     * @param StreamViewSession $viewSession
     * @param StreamMicroTransaction[] $microTransactions
     */
    public function mergeMicroTransactions(StreamSession $session, StreamViewSession $viewSession, array $microTransactions): StreamTransactionWithdraw
    {
        $model = $session->getRoom()->getUser();
        assert($model instanceof UserModel);
        $member = $viewSession->getUser();
        assert($member instanceof UserMember);


        $withdraw = new StreamTransactionWithdraw();
        $withdraw->setStatus(TransactionStatusEnum::COMPLETE());
        $withdraw->setStreamType(StreamTransactionTypeEnum::getForViewSession($viewSession));
        $withdraw->setAccount($member->getAccount());
        $withdraw->setTarget($model->getAccount());
        $withdraw->setRoom($session->getRoom());

        $deposit = new StreamTransactionDeposit();
        $deposit->setStatus(TransactionStatusEnum::COMPLETE());
        $deposit->setStreamType(StreamTransactionTypeEnum::getForViewSession($viewSession));
        $deposit->setRoom($session->getRoom());
        $deposit->setAccount($model->getAccount());
        $deposit->setSource($member->getAccount());

        $deposit->setRelatedTransaction($withdraw);
        $withdraw->setRelatedTransaction($deposit);

        $amount = 0;
        foreach($microTransactions as $microTransaction) {
            if ($microTransaction->getStatus()->equals(TransactionStatusEnum::COMPLETE())) {
                // Микротранзакции идут на списание, так что amount < 0
                assert($microTransaction->getAmount() < 0);
                // делаем его положительным
                $amount += 0 - $microTransaction->getAmount();
                $microTransaction->setDepositTransaction($deposit);
                $microTransaction->setWithdrawTransaction($withdraw);
                $microTransaction->getRelatedTransaction()->setDepositTransaction($deposit);
            }
        }
        $withdraw->setAmount(-$amount);
        $deposit->setAmount($amount);
        return $withdraw;
    }

    public function createForTicketShow(Room $room, UserMember $member, int $amount): StreamTransactionWithdraw
    {
        assert($amount > 0);
        $model = $room->getUser();


        $withdraw = new StreamTransactionWithdraw();
        $withdraw->setStatus(TransactionStatusEnum::NEW());
        $withdraw->setStreamType(StreamTransactionTypeEnum::TICKET());
        $withdraw->setAccount($member->getAccount());
        $withdraw->setTarget($model->getAccount());
        $withdraw->setRoom($room);

        $deposit = new StreamTransactionDeposit();
        $deposit->setStatus(TransactionStatusEnum::NEW());
        $deposit->setStreamType(StreamTransactionTypeEnum::TICKET());
        $deposit->setRoom($room);
        $deposit->setAccount($model->getAccount());
        $deposit->setSource($member->getAccount());

        $deposit->setRelatedTransaction($withdraw);
        $withdraw->setRelatedTransaction($deposit);

        $withdraw->setAmount(-$amount);
        $deposit->setAmount($amount);
        return $withdraw;
    }
}
