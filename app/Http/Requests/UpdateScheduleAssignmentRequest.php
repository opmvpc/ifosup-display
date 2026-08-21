<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => 'required|exists:rooms,id',
            'date' => 'required|date_format:Y-m-d',
            'period' => 'required|in:morning,afternoon,evening',
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
            'room_id.required' => 'Le local est requis.',
            'room_id.exists' => 'Le local sélectionné est introuvable.',
            'date.required' => 'La date est requise.',
            'date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
            'period.required' => 'La période est requise.',
            'period.in' => 'La période doit être matin, après-midi ou soir.',
        ];
    }
}
