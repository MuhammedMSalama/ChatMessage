<?php

namespace App\Http\Requests\Chat;

use App\Enums\MessageEntityType;
use App\Enums\MessageType;
use App\Helper\ResponseHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'receiver_id' => [
                'required',
                'exists:users,id',
                Rule::notIn([$this->user()?->id]),
            ],
            'message_text' => 'required|string',
            'message_type' => ['required', Rule::in(MessageType::values())],
            'attachment_url' => 'nullable|string',
            'entity_type' => [
                'required',
                'string',
                Rule::in(MessageEntityType::values())
            ],
            'entity_id' => 'required|integer',
        ];
    }


    /**
     * Override failed validation response to add custom validation message
     *
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return \Illuminate\Http\Response
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if ($this->sender === $this->receiver) {
            return ResponseHelper::error(
                'error',
                'Sender and receiver cannot be the same user.',
                422
            );
        }

        if ($this->entity_type && !$this->entity_id) {
            return ResponseHelper::error(
                'error',
                'Entity ID is required when Entity Type is provided.',
                422
            );
        }

        if ($this->entity_type === MessageEntityType::PROJECT && !$this->entity_id) {
            return ResponseHelper::error(
                'error',
                'Entity ID is required for the selected entity type.',
                422
            );
        }

        parent::failedValidation($validator);
    }
}
