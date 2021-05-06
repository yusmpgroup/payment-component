<?php

namespace Yusmp\PaymentComponent\Service\Handler;

use Yusmp\CoreComponent\Domain\Chat\Enum\ChatMessageTypeEnum;
use Yusmp\CoreComponent\Domain\Chat\Exception\ChatException;
use Yusmp\CoreComponent\Domain\Chat\Service\RoomChatService;
use Yusmp\CoreComponent\Domain\Payment\Entity\Transaction;
use Yusmp\CoreComponent\Domain\Payment\Entity\TransactionTip;
use Yusmp\CoreComponent\Domain\Payment\Event\ModelGetTipEvent;
use Yusmp\CoreComponent\Domain\Room\Entity\Room;
use Yusmp\CoreComponent\Domain\Room\Event\GoalProgressEvent;
use Yusmp\CoreComponent\Domain\Room\Event\GoalReachedEvent;
use Yusmp\CoreComponent\Domain\Settings\Service\SettingsService;
use Yusmp\CoreComponent\Domain\User\Entity\UserMember;
use Yusmp\CoreComponent\Domain\User\Entity\UserModel;
use Yusmp\CoreComponent\Interfaces\TransactionInterface;
use Yusmp\PaymentComponent\Service\TransactionService;
use Yusmp\CoreComponent\Services\MercurePublisher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TipHandler implements TransactionHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private TransactionService $transactionService;
    private RoomChatService $chatService;
    private MercurePublisher $mercure;
    private SettingsService $settingsService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionService $transactionService,
        RoomChatService $chatService,
        MercurePublisher $mercure,
        SettingsService $settingsService,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->transactionService = $transactionService;
        $this->chatService = $chatService;
        $this->mercure = $mercure;
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    public function supports(TransactionInterface $transaction): bool
    {
        return $transaction instanceof TransactionTip;
    }

    public function handle(TransactionInterface $transaction)
    {
        assert($transaction instanceof TransactionTip);
        $targetAccount = $transaction->getTarget();
        $targetUser = $targetAccount->getUser();
        assert($targetUser instanceof UserModel);
        $member = $transaction->getAccount()->getUser();
        $room = $targetUser->getRoom();
        $isSpy = false;
        try {
            $chatRoomId = null;
            // Если юзер в привате и типает модели, то пишем в чат приватного шоу.
            if ($session = $room->getActiveStreamSession()) {
                if ($session->isPrivate() || $session->isTruePrivate() || $session->isGroup() || $session->isTicket()) {
                    $viewSession = $session->getViewSession($member);
                    if (null != $viewSession && null == $viewSession->getEndedAt()) {
                        $chatRoomId = $session->getChatRoomId();
                        $isSpy = $viewSession->getIsSpy();
                    }
                }
            }
            if (!$isSpy) { // CWDEV-3047 dont send spy tip messages.
                $this->chatService->sendSystemMessage(
                    $room,
                    ChatMessageTypeEnum::TIP(),
                    [
                        'username' => $member->getName(),
                        '%count%' => abs($transaction->getAmount()),
                    ],
                    $chatRoomId
                );
            }
            if (!$room->isOffline() && !$isSpy) {
                if ($tipAutoMessage = $this->getTipAutoMessage()) {
                    $this->chatService->sendSystemMessage(
                        $room,
                        ChatMessageTypeEnum::TIP_AUTO_MESSAGE(),
                        [
                            'username' => $member->getName(),
                            'message' => $tipAutoMessage,
                            '%count%' => abs($transaction->getAmount()),
                        ],
                        $chatRoomId
                    );
                }
            }
        } catch (ChatException $e) {
            $this->logger->emergency(sprintf(
                'Error sending tip message to chat: %s',
                $e->getMessage()
            ));
        }
        if ($room->hasEnabledGoal()) {
            // @TODO: не сдвигать цель если комната в приватном шоу
            $goal = $room->getGoal();
            $goal->adjustTokens(abs($transaction->getAmount()));
            if ($goal->isWasReachedNow()) {
                $this->handleGoalReached($room, $transaction);
            }
            elseif (!$goal->isReached()) {
                $this->handleGoalProgress($room, $transaction);
            }
        }//end if

        try {
            // Если модель в привате, то кидать это событие тока если типнул участник привата (но не шпион)
            // Это нужно чтобы если кто-то из не участников шоу (или шпион) типнет модели во время шоу,
            // то в чат шоу не придет сообщение о типе и не должно посылаться событие, иначе
            // у модели проиграет звук типа (а сообщения в чате не будет)
            $isParticipant = false;
            $isInPrivate = $room->isInPrivateShow() || $room->isInGroupShow() || $room->isInTruePrivateShow() || $room->isInTicketShow();
            if ($isInPrivate) {
                assert($member instanceof UserMember);
                $isParticipant = !$isSpy && null != $room->getActiveStreamViewSesion($member);
            }
            $this->logger->debug(sprintf('Send model tip event in room %s (member %s), isParticipant=%d', $room->getName(), $member->getName(), $isParticipant));
            $event = new ModelGetTipEvent($room, $transaction->getRelatedTransaction(), $isParticipant, $isInPrivate);
            $this->mercure->publishEvent($event);
        } catch (\Throwable $e) {
            $this->logger->emergency(sprintf(
                'Error sending model get tip event to Mercure: %s',
                $e->getMessage()
            ));
        }
    }

    public static function getTipMessage(TransactionTip $tip): string
    {
        $member = $tip->getUser();
        assert($member instanceof UserMember);
        return sprintf('%s tipped %d token', $member->getName(), abs($tip->getAmount()));
    }

    protected function handleGoalReached(Room $room, Transaction $transaction): void
    {
        $goal = $room->getGoal();
        try {
            $this->chatService->sendSystemMessage(
                $room,
                ChatMessageTypeEnum::GOAL_REACHED(),
                [
                    'username' => $transaction->getAccount()->getUser()->getName(),
                    '%count%' => abs($transaction->getAmount()),
                    'goal' => $goal->getDescription()
                ]
            );
        } catch (ChatException $e) {
            $this->logger->emergency(sprintf(
                'Error sending reach goal message to chat: %s',
                $e->getMessage()
            ));
        }
        try {
            $event = new GoalReachedEvent($room, $transaction);
            $this->mercure->publishEvent($event);
        } catch (\Throwable $e) {
            $this->logger->emergency(sprintf(
                'Error sending reach goal event to Mercure: %s',
                $e->getMessage()
            ));
        }
        $this->entityManager->persist($goal);
        $this->entityManager->flush();
    }

    protected function handleGoalProgress(Room $room, Transaction $transaction): void
    {
        try {
            $event = new GoalProgressEvent($room, $transaction);
            $this->mercure->publishEvent($event);
        } catch (\Throwable $e) {
            $this->logger->emergency(sprintf(
                'Error sending goal progress event to Mercure: %s',
                $e->getMessage()
            ));
        }
    }

    protected function getTipAutoMessage(): ?string
    {
        $answers = $this->settingsService->getAutoAnswersForTips();
        $answer = null;
        if ($answers) {
            $key = array_rand($answers);
            $answer = $answers[$key];
        }
        $answer = $answer . "💋💋💋";
        return $answer;
    }

    protected function getAnonTipAutoMessage(): ?string
    {
        $answers = $this->settingsService->getAutoAnswersForNewUser();
        $answer = null;
        if ($answers) {
            $key = array_rand($answers);
            $answer = $answers[$key];
        }
        return $answer;
    }
}
