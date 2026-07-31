<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscribersRequest extends FormRequest
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
            'tenant_id'=>['required','exists:tenants,id'],
            'external_id'=>['required','string','max:255','unique:subscribers,external_id'],
            'phone_number'=>['required','string','max:30'],
            'email'=>['sometimes','nullable','email','max:255'],
            'environment'=>['required','in:live,test']
        ];
    }
}
