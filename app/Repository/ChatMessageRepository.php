<?php

namespace App\Repository;

use App\Interface\ChatMessageInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use App\Models\ChatMessage;

class ChatMessageRepository implements ChatMessageInterface
{
    /**
     * @param array $data
     * @return ChatMessage
     */
    public function sendMessage(array $data): ChatMessage
    {
        $data['sent_at'] = $data['sent_at'] ?? now();
        return ChatMessage::create($data);
    }

    /**
     * @param int $senderId
     * @param int $receiverId
     * @return Collection
     */
    public function getChatHistory(int $senderId, int $receiverId): Collection
    {
        return ChatMessage::with(['sender', 'receiver'])
            ->where(function ($query) use ($senderId, $receiverId) {
            $query->where('sender_id', $senderId)->where('receiver_id', $receiverId);
        })->orWhere(function ($query) use ($senderId, $receiverId) {
            $query->where('sender_id', $receiverId)->where('receiver_id', $senderId);
        })
            ->orderBy('sent_at', 'asc')
            ->get();
    }

    /**
     * @param int $senderId
     * @param int $receiverId
     * @return mixed
     */
    public function markAsRead(int $senderId, int $receiverId)
    {
        return ChatMessage::where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * @param int $userId
     * @return mixed
     */
    public function getContacts(int $userId)
    {
        $contactIds = ChatMessage::where('sender_id', $userId)
            ->pluck('receiver_id')
            ->merge(
                ChatMessage::where('receiver_id', $userId)->pluck('sender_id')
            )
            ->unique()
            ->toArray();

        return User::whereIn('id', $contactIds)
            ->with(['lastMessage' => function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('chat_messages.sender_id', $userId)
                        ->orWhere('chat_messages.receiver_id', $userId);
                })
                    ->latest('chat_messages.sent_at');
            }])
            ->get();
    }

    /**
     * Get all chat messages.
     *
     * @return mixed
     */
    public function getAllChats()
    {
        return ChatMessage::with(['sender', 'receiver'])
            ->orderBy('sent_at', 'desc')
            ->get();
    }
}
