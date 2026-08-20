<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est requis.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'email.required' => "L'adresse e-mail est requise.",
            'email.email' => "L'adresse e-mail n'est pas valide.",
            'email.max' => "L'adresse e-mail ne peut pas dépasser 255 caractères.",
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
        ];
    }
}
