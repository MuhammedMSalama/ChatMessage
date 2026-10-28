<?php

namespace Tests\Unit\Http\Controllers;

use App\Helper\ResponseHelper;
use App\Http\Controllers\ChatMessageController;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\User;
use App\Services\ChatMessageService;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;
use Tests\Traits\MakesSendMessagePayload;

class ChatMessageControllerTest extends TestCase
{
    use MakesSendMessagePayload;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function actingAsUserWithId(int $id): void
    {
        $user = new User();
        $user->id = $id;

        Auth::setUser($user);
    }

    public function test_send_message_forwards_validated_payload_to_service(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $authenticatedUserId = 99;
        $payload = $this->makeSendMessagePayload();
        unset($payload['sender_id']);

        $request = Mockery::mock(SendMessageRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($payload);
        $request->shouldReceive('user')->once()->andReturn((object) ['id' => $authenticatedUserId]);

        $expectedPayload = array_merge($payload, ['sender_id' => $authenticatedUserId]);

        $expectedResponse = ResponseHelper::success(
            'success',
            'Message sent successfully',
            ['example' => true],
        );

        $service->shouldReceive('sendMessage')->once()->with($expectedPayload)->andReturn($expectedResponse);

        $response = $controller->sendMessage($request);

        $this->assertSame($expectedResponse, $response);
    }

    public function test_send_message_ignores_spoofed_sender_id_from_payload(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $authenticatedUserId = 7;
        $spoofedSenderId = 123;

        $payload = $this->makeSendMessagePayload(['sender_id' => $spoofedSenderId]);
        unset($payload['sender_id']);

        $request = Mockery::mock(SendMessageRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($payload);
        $request->shouldReceive('user')->once()->andReturn((object) ['id' => $authenticatedUserId]);

        $forwardedData = null;
        $service->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function (array $data) use (&$forwardedData) {
                $forwardedData = $data;

                return true;
            }))
            ->andReturn(ResponseHelper::success('success', 'Message sent successfully'));

        $controller->sendMessage($request);

        $this->assertSame($authenticatedUserId, $forwardedData['sender_id']);
        $this->assertNotSame($spoofedSenderId, $forwardedData['sender_id']);
    }

    public function test_get_chat_history_forwards_ids_to_service_for_a_participant(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $senderId = 1;
        $receiverId = 2;

        $this->actingAsUserWithId($senderId);

        $expectedResponse = ResponseHelper::success(
            'success',
            'Chat history retrieved',
            ['example' => true],
        );

        $service->shouldReceive('getChatHistory')->once()->with($senderId, $receiverId)->andReturn($expectedResponse);

        $response = $controller->getChatHistory($senderId, $receiverId);

        $this->assertSame($expectedResponse, $response);
    }

    public function test_get_chat_history_rejects_a_non_participant(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $this->actingAsUserWithId(999);

        $service->shouldNotReceive('getChatHistory');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->getChatHistory(1, 2);
    }

    public function test_mark_as_read_forwards_ids_to_service_for_a_participant(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $senderId = 1;
        $receiverId = 2;

        $this->actingAsUserWithId($receiverId);

        $expectedResponse = ResponseHelper::success(
            'success',
            'Messages marked as read',
        );

        $service->shouldReceive('markAsRead')->once()->with($senderId, $receiverId)->andReturn($expectedResponse);

        $response = $controller->markAsRead($senderId, $receiverId);

        $this->assertSame($expectedResponse, $response);
    }

    public function test_mark_as_read_rejects_a_non_participant(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $this->actingAsUserWithId(999);

        $service->shouldNotReceive('markAsRead');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->markAsRead(1, 2);
    }

    public function test_contacts_forwards_user_id_to_service_for_the_owner(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $userId = 10;

        $this->actingAsUserWithId($userId);

        $expectedResponse = ResponseHelper::success(
            'success',
            'Contacts retrieved',
            ['example' => true],
        );

        $service->shouldReceive('getContacts')->once()->with($userId)->andReturn($expectedResponse);

        $response = $controller->contacts($userId);

        $this->assertSame($expectedResponse, $response);
    }

    public function test_contacts_rejects_a_non_owner(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $this->actingAsUserWithId(999);

        $service->shouldNotReceive('getContacts');

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $controller->contacts(10);
    }

    public function test_get_all_chats_forwards_to_service(): void
    {
        $service = Mockery::mock(ChatMessageService::class);
        $controller = new ChatMessageController($service);

        $expectedResponse = ResponseHelper::success(
            'success',
            'All chat messages fetched successfully',
            ['example' => true],
        );

        $service->shouldReceive('getAllChats')->once()->andReturn($expectedResponse);

        $response = $controller->getAllChats();

        $this->assertSame($expectedResponse, $response);
    }
}

