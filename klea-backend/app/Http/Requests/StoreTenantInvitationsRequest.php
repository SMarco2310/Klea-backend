<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTenantInvitationsRequest extends FormRequest
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
            'email'=>[
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (strcasecmp($value, $this->user()->email) === 0) {
                        $fail('You cannot invite yourself.');
                    }
                },
            ],
            'role'=>['sometimes','string','max:255'],
            'expires_at'=>['sometimes','date'],
        ];
    }
}
