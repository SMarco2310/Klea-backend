<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantInvitationsRequest extends FormRequest
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
            'email'=>['sometimes','email','max:255'],
            'role'=>['sometimes','string','max:255'],
            'token'=>['sometimes','string', Rule::unique('tenant_invitations','token')->ignore($this->route('tenant_invitation'))],
            'invited_by'=>['sometimes','nullable','exists:users,id'],
            'status'=>['sometimes','in:pending,accepted,declined,expired'],
            'expires_at'=>['sometimes','date'],
            'accepted_at'=>['sometimes','nullable','date']
        ];
    }
}
