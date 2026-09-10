<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * NOTE: there is deliberately no `role` rule. Public registration always
     * creates a student; representatives are provisioned by seeders/admins.
     * Because only validated data is mass-assigned, `role` can never be
     * escalated through this endpoint.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
        ];
    }
}
