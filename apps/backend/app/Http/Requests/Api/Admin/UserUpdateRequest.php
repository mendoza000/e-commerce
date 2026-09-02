<?php

namespace App\Http\Requests\Api\Admin;

use App\Domain\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->targetUser()->getKey()),
            ],
            // Absent means "leave the password alone"; present means replace it.
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', Password::defaults()],
            'role' => ['sometimes', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $target = $this->targetUser();

                $demotingLastOwner = $this->filled('role')
                    && $this->input('role') !== Role::Owner->value
                    && $target->isLastActiveOwner();

                if ($demotingLastOwner) {
                    $validator->errors()->add(
                        'role',
                        'No puedes quitarle el rol de dueño a la única cuenta de dueño activa.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe una cuenta con ese correo.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }

    private function targetUser(): User
    {
        return $this->route('user');
    }
}
