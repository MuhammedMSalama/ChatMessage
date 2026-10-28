<?php

namespace Tests\Feature\Chat;

use App\Enums\MessageType;
use App\Models\ChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MakesRoleUsers;

class ChatOwnershipTest extends TestCase
{
    use RefreshDatabase;
    use MakesRoleUsers;

    private function makeMessage(int $senderId, int $receiverId, array $overrides = []): ChatMessage
    {
        return ChatMessage::create(array_merge([
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message_text' => 'Hello',
            'message_type' => MessageType::TEXT->value,
            'is_read' => false,
            'sent_at' => now(),
        ], $overrides));
    }

    public function test_outsider_cannot_view_history_between_two_other_users(): void
    {
        $userA = $this->makeRegularUser();
        $userB = $this->makeRegularUser();
        $outsider = $this->makeRegularUser();

        $this->makeMessage($userA->id, $userB->id);

        $this->authenticateViaSanctum($outsider);

        $response = $this->getJson("/api/chat/history/{$userA->id}/{$userB->id}");

        $response->assertStatus(403);
    }

    public function test_participant_can_view_their_own_history(): void
    {
        $userA = $this->makeRegularUser();
        $userB = $this->makeRegularUser();

        $this->makeMessage($userA->id, $userB->id, ['message_text' => 'Hi there']);

        $this->authenticateViaSanctum($userA);

        $response = $this->getJson("/api/chat/history/{$userA->id}/{$userB->id}");

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }

    public function test_outsider_cannot_mark_other_users_messages_as_read(): void
    {
        $userA = $this->makeRegularUser();
        $userB = $this->makeRegularUser();
        $outsider = $this->makeRegularUser();

        $message = $this->makeMessage($userA->id, $userB->id);

        $this->authenticateViaSanctum($outsider);

        $response = $this->postJson("/api/chat/mark-as-read/{$userA->id}/{$userB->id}");

        $response->assertStatus(403);

        $this->assertFalse((bool) $message->refresh()->is_read);
    }

    public function test_participant_can_mark_their_own_conversation_as_read(): void
    {
        $userA = $this->makeRegularUser();
        $userB = $this->makeRegularUser();

        $message = $this->makeMessage($userA->id, $userB->id);

        $this->authenticateViaSanctum($userB);

        $response = $this->postJson("/api/chat/mark-as-read/{$userA->id}/{$userB->id}");

        $response->assertOk();
        $this->assertTrue((bool) $message->refresh()->is_read);
    }

    public function test_outsider_cannot_list_another_users_contacts(): void
    {
        $userA = $this->makeRegularUser();
        $userB = $this->makeRegularUser();
        $outsider = $this->makeRegularUser();

        $this->makeMessage($userA->id, $userB->id);

        $this->authenticateViaSanctum($outsider);

        $response = $this->getJson("/api/chat/contacts/{$userA->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_list_their_own_contacts(): void
    {
        $userA = $this->makeRegularUser();
        $userB = $this->makeRegularUser();

        $this->makeMessage($userA->id, $userB->id);

        $this->authenticateViaSanctum($userA);

        $response = $this->getJson("/api/chat/contacts/{$userA->id}");

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
    }
}
