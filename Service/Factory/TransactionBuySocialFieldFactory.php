<?php

namespace Yusmp\PaymentComponent\Service\Factory;

use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionMemberBuySocialField;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionModelSoldSocialField;
use Yusmp\CoreComponent\Domain\Settings\Service\SettingsService;
use Yusmp\CoreComponent\Domain\User\Entity\User;
use Yusmp\CoreComponent\Domain\User\Entity\UserModel;
use Yusmp\CoreComponent\Domain\User\Enum\SocialFieldEnum;
use Doctrine\ORM\EntityManagerInterface;

class TransactionBuySocialFieldFactory
{
    private EntityManagerInterface $entityManager;
    private SettingsService $settingsService;

    public function __construct(
        EntityManagerInterface $entityManager,
        SettingsService $settingsService
    ) {
        $this->entityManager = $entityManager;
        $this->settingsService = $settingsService;
    }

    public function create(User $user, SocialFieldEnum $field, UserModel $model): TransactionMemberBuySocialField
    {
        $room = $model->getRoom();
        $transaction = new TransactionMemberBuySocialField();
        $transaction->setAccount($user->getAccount());
        $transaction->setAmount(-$model->getRoom()->getSocialFieldPrice($field));
        $transaction->setTarget($model->getAccount());
        $transaction->setField($field);
        $transaction->setRoom($room);

        $commission = $this->settingsService->commissionSocialLinks();

        $price = $model->getRoom()->getSocialFieldPrice($field);
        $commissionValue =
            ceil(
                $price *
                ((float) $commission / 100)
            );
        $amountValue = $price - $commissionValue;

        $relatedTransaction = new TransactionModelSoldSocialField();
        $relatedTransaction->setAccount($model->getAccount());
        $relatedTransaction->setAmount($amountValue);
        $relatedTransaction->setCommission($commissionValue);
        $relatedTransaction->setSource($user->getAccount());
        $relatedTransaction->setField($field);
        $relatedTransaction->setRelatedTransaction($transaction);
        $relatedTransaction->setRoom($room);
        $transaction->setRelatedTransaction($relatedTransaction);

        $this->entityManager->persist($transaction);
        $this->entityManager->persist($relatedTransaction);
        $this->entityManager->flush();

        return $transaction;
    }
}
