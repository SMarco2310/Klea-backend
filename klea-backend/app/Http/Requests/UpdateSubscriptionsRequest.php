<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subscriber_id'=>['sometimes','exists:subscribers,id'],
            'plan_id'=>['sometimes','exists:plans,id'],
            'status' =>['sometimes', 'in:active,cancelled'],
            'starts_at'=>['sometimes','date'],
            'expires_at'=>['sometimes','date','after:starts_at'],
            'cancelled_at'=>['required_if:status,cancelled','nullable','date'],
            'environment'=>['sometimes','in:live,test']
        ];
    }
}
