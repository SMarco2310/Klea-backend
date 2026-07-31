<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'=>['required','string','max:255'],
            'slug'=>['required','string','max:255','unique:tenants,slug'],
            'semoa_api_key'=>['sometimes','nullable','string'],
            'semoa_merchant_id'=>['sometimes','nullable','string'],
            'status'=>['sometimes','in:active,inactive,suspended'],
            'settings'=>['sometimes','nullable','array']
        ];
    }
}
