<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationsRequest extends FormRequest
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
            'slug'=>['sometimes','string','max:255', Rule::unique('applications','slug')->ignore($this->route('application'))],
            'status'=>['sometimes','in:active,inactive'],
            'webhook_url'=>['sometimes','nullable','url'],
            'webhook_secret'=>['sometimes','nullable','string']
        ];
    }
}
