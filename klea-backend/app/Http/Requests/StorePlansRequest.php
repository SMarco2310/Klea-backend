<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlansRequest extends FormRequest
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
            'application_id'=>['required','exists:applications,id'],
            'name'=>['required','string','max:255'],
            'price'=>['required','numeric','min:0'],
            'currency'=>['required','string','max:10'],
            'duration_days'=>['required','integer','min:1'],
            'grace_period_days'=>['sometimes','integer','min:0'],
            'yearly_discount_percent'=>['sometimes','integer','min:0','max:100'],
            'is_active'=>['sometimes','boolean']
        ];
    }
}
