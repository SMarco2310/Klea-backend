<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantsRequest extends FormRequest
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
            'name'=>['sometimes','string','max:255'],
            'slug'=>['sometimes','string','max:255', Rule::unique('tenants','slug')->ignore($this->route('tenant'))],
            'semoa_api_key'=>['sometimes','nullable','string'],
            'semoa_merchant_id'=>['sometimes','nullable','string'],
            'status'=>['sometimes','in:active,inactive,suspended'],
            'settings'=>['sometimes','nullable','array']
        ];
    }
}
