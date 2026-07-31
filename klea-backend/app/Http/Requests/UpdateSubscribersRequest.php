<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscribersRequest extends FormRequest
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
            'tenant_id'=>['sometimes','exists:tenants,id'],
            'external_id'=>['sometimes','string','max:255', Rule::unique('subscribers','external_id')->ignore($this->route('subscriber'))],
            'phone_number'=>['sometimes','string','max:30'],
            'email'=>['sometimes','nullable','email','max:255'],
            'environment'=>['sometimes','in:live,test']
        ];
    }
}
