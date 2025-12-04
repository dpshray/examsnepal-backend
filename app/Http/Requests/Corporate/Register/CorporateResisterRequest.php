<?php

namespace App\Http\Requests\Corporate\Register;

use Illuminate\Foundation\Http\FormRequest;

class CorporateResisterRequest extends FormRequest
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
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'username' => 'required|string|unique:users,username',
            'fullname' => 'required|string|max:255',
            'phone'=>'required|string',
        ];
    }
}
