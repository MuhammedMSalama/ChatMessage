<?php

namespace Tests\Feature\Chat;

use App\Enums\MessageEntityType;
use App\Enums\MessageType;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\MakesRoleUsers;

class SendMessageSenderSpoofingTest extends TestCase
{
    use RefreshDatabase;
    use MakesRoleUsers;

    public function test_sender_id_is_taken_from_the_authenticated_user_not_the_payload(): void
    {
        $attacker = $this->makeRegularUser();
        $spoofedSender = $this->makeRegularUser();
        $receiver = $this->makeRegularUser();

        $this->authenticateViaSanctum($attacker);

        $response = $this->postJson('/api/chat/send', [
            'sender_id' => $spoofedSender->id,
            'receiver_id' => $receiver->id,
            'message_text' => 'Hello from a spoofed sender',
            'message_type' => MessageType::TEXT->value,
            'entity_type' => MessageEntityType::PROJECT->value,
            'entity_id' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('chat_messages', [
            'sender_id' => $attacker->id,
            'receiver_id' => $receiver->id,
            'message_text' => 'Hello from a spoofed sender',
        ]);

        $this->assertDatabaseMissing('chat_messages', [
            'sender_id' => $spoofedSender->id,
        ]);

        $this->assertSame(
            $attacker->id,
            ChatMessage::first()->sender_id,
        );
    }

    public function test_user_cannot_send_a_message_to_themselves(): void
    {
        $user = $this->makeRegularUser();

        $this->authenticateViaSanctum($user);

        $response = $this->postJson('/api/chat/send', [
            'sender_id' => $user->id,
            'receiver_id' => $user->id,
            'message_text' => 'Talking to myself',
            'message_type' => MessageType::TEXT->value,
            'entity_type' => MessageEntityType::PROJECT->value,
            'entity_id' => 1,
        ]);

        $response->assertStatus(422);
    }
}
