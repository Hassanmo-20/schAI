<?php

namespace App\Http\Requests\Auth;

use App\Enums\BatchYear;
use App\Enums\Department;
use App\Enums\Role;
use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registration input: identity + the group the user is joining + their role.
 *
 * A group is submitted as the (batch_year, department) PAIR rather than a raw
 * `batch_id`, so the client can never point a new account at an arbitrary row
 * — the pair is validated against the enums and resolved server-side by
 * {@see Batch::resolveGroup()}.
 *
 * NOTE ON ROLE: the product spec requires the registration form to offer
 * "Student" or "Batch Representative", so `role` IS accepted here and is
 * self-selected. That is a deliberate product decision, not an oversight:
 * anyone may register as the representative of a group. Every representative
 * power remains scoped to their OWN group by TaskPolicy, so the worst case is
 * a bogus rep inside one group — never cross-group access. If this ever needs
 * locking down, do it here (invite code / admin approval) and nothing else in
 * the app has to change.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'batch_year' => ['required', Rule::enum(BatchYear::class)],
            'department' => ['required', Rule::enum(Department::class)],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'batch_year.required' => 'Please choose your batch.',
            'department.required' => 'Please choose your department.',
            'role.required' => 'Please choose whether you are a student or a batch representative.',
        ];
    }

    public function batchYear(): BatchYear
    {
        return BatchYear::from($this->validated('batch_year'));
    }

    public function department(): Department
    {
        return Department::from($this->validated('department'));
    }

    public function role(): Role
    {
        return Role::from($this->validated('role'));
    }

    /** The group this registration joins, created on first use. */
    public function group(): Batch
    {
        return Batch::resolveGroup($this->batchYear(), $this->department());
    }
}
