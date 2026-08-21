<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:courses,code',
            'teacher_id' => 'nullable|exists:teachers,id',
            'groups' => 'array',
            'groups.*' => 'exists:groups,id',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du cours est requis.',
            'name.string' => 'Le nom du cours doit être une chaîne de caractères.',
            'name.max' => 'Le nom du cours ne peut pas dépasser 255 caractères.',
            'code.required' => 'Le code du cours est requis.',
            'code.string' => 'Le code du cours doit être une chaîne de caractères.',
            'code.max' => 'Le code du cours ne peut pas dépasser 50 caractères.',
            'code.unique' => 'Ce code de cours est déjà utilisé.',
            'teacher_id.required' => "L'ID de l'enseignant est requis.",
            'teacher_id.exists' => "L'ID de l'enseignant doit correspondre à un enseignant existant.",
            'groups.array' => "Les groupes doivent être un tableau d'IDs de groupes.",
            'groups.*.exists' => 'Chaque ID de groupe doit correspondre à un groupe existant.',
        ];
    }
}
