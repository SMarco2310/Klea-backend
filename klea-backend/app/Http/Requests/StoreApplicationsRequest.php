<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationsRequest extends FormRequest
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
            'slug'=>['required','string','max:255','unique:applications,slug'],
            'status'=>['sometimes','in:active,inactive'],
            'webhook_url'=>['sometimes','nullable','url'],
            'webhook_secret'=>['sometimes','nullable','string']
        ];
    }
}
