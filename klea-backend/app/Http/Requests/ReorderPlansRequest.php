<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_ids' => ['required', 'array', 'min:1'],
            'plan_ids.*' => ['integer', 'distinct', 'exists:plans,id'],
        ];
    }
}
