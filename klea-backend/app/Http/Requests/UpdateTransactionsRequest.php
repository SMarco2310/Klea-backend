<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionsRequest extends FormRequest
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
            'subscription_id'=>['sometimes','exists:subscriptions,id'],
            'amount'=>['sometimes','numeric','min:0'],
            'currency'=>['sometimes','string','max:4'],
            'provider_tx_id'=>['sometimes','nullable','string'],
            'payment_method'=>['sometimes','string','max:50'],
            'phone_number'=>['sometimes','string','max:30'],
            'status'=>['sometimes','in:pending,successful,failed'],
            'error_message'=>['sometimes','nullable','string'],
            'environment'=>['sometimes','in:live,test']
        ];
    }
}
