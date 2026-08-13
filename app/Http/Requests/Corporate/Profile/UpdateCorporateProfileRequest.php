<?php

namespace App\Http\Requests\Corporate\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCorporateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'org' => 'sometimes|nullable|string|max:255',
            'about' => 'sometimes|nullable|string|max:2000',
            'location' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:50',
            'facebook' => 'sometimes|nullable|string|max:255',
            'twitter' => 'sometimes|nullable|string|max:255',
            'linkedin' => 'sometimes|nullable|string|max:255',
            'logo' => 'sometimes|nullable|image|max:2048',
            'banner' => 'sometimes|nullable|image|max:4096',
        ];
    }
}
