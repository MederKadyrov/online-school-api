<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceBulkRequest extends FormRequest
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
            'lesson_id' => 'required|exists:lessons,id',
            'items' => 'required|array|min:1',
            'items.*.student_id' => 'required|exists:students,id',
            'items.*.status' => 'required|in:present,late,excused_absence,unexcused_absence',
            'items.*.comment' => 'nullable|string|max:255'
        ];
    }
}
