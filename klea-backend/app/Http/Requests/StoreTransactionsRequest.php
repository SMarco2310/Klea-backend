<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionsRequest extends FormRequest
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
            'subscription_id'=>['required','exists:subscriptions,id'],
            'amount'=>['required','numeric','min:0'],
            'currency'=>['required','string','max:4'],
            'provider_tx_id'=>['sometimes','nullable','string'],
            'payment_method'=>['required','string','max:50'],
            'phone_number'=>['required','string','max:30'],
            'status'=>['required','in:pending,successful,failed'],
            'error_message'=>['sometimes','nullable','string'],
            'environment'=>['required','in:live,test']
        ];
    }
}
