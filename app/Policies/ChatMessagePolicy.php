<?php

namespace App\Policies;

use App\Models\User;

class ChatMessagePolicy
{
    /**
     * Determine whether the user may view the conversation between the two given ids.
     *
     * @param User $user
     * @param int $senderId
     * @param int $receiverId
     * @return bool
     */
    public function viewHistory(User $user, int $senderId, int $receiverId): bool
    {
        return $this->isParticipant($user, $senderId, $receiverId);
    }

    /**
     * Determine whether the user may mark the conversation between the two given ids as read.
     *
     * @param User $user
     * @param int $senderId
     * @param int $receiverId
     * @return bool
     */
    public function markAsRead(User $user, int $senderId, int $receiverId): bool
    {
        return $this->isParticipant($user, $senderId, $receiverId);
    }

    /**
     * Determine whether the user may view the contacts belonging to the given user id.
     *
     * @param User $user
     * @param int $userId
     * @return bool
     */
    public function viewContacts(User $user, int $userId): bool
    {
        return $user->id === $userId;
    }

    /**
     * @param User $user
     * @param int $senderId
     * @param int $receiverId
     * @return bool
     */
    private function isParticipant(User $user, int $senderId, int $receiverId): bool
    {
        return $user->id === $senderId || $user->id === $receiverId;
    }
}
