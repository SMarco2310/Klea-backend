<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlansRequest extends FormRequest
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
            'application_id'=>['sometimes','exists:applications,id'],
            'name'=>['sometimes','string','max:255'],
            'price'=>['sometimes','numeric','min:0'],
            'currency'=>['sometimes','string','max:10'],
            'duration_days'=>['sometimes','integer','min:1'],
            'grace_period_days'=>['sometimes','integer','min:0'],
            'yearly_discount_percent'=>['sometimes','integer','min:0','max:100'],
            'is_active'=>['sometimes','boolean']
        ];
    }
}
