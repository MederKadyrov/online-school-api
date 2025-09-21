<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LessonStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin','staff','teacher']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'group_id'   => 'required|exists:groups,id',
            'starts_at'  => 'required|date|before:ends_at',
            'ends_at'    => 'required|date|after:starts_at',
            'room'       => 'nullable|string|max:100',
            'meeting_url'=> 'nullable|url|max:255',
            'meeting_provider' => 'nullable|in:jitsi,bbb,zoom,meet,custom',
        ];
    }
}
