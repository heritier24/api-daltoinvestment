<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterFormRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:12', 'unique:users'],
            'password' => ['required', 'string', 'min:8'], // password_confirmation field required
            'promocode' => ['nullable', 'string', 'max:50', 'exists:users,promocode'], // Validate promocode if provided
            'role' => ['nullable', Rule::in(['user_client', 'admin', 'super_admin'])], // Optional, defaults to user_client
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'The email address is already taken.',
            'phone_number.unique' => 'The phone number is already taken.',
            'promocode.exists' => 'The provided promocode is invalid.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];
    }

     /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new ValidationException($validator, $this->buildFailedValidationResponse($validator));
    }

    /**
     * Build the response for failed validation.
     */
    protected function buildFailedValidationResponse(\Illuminate\Contracts\Validation\Validator $validator): JsonResponse
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
        ], 422);
    }
}
