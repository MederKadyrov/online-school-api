<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin','staff']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'guardian.name'        => 'required|string|max:255',
            'guardian.pin'         => 'required|digits:14|unique:users,pin',
            'guardian.citizenship' => 'required|string|max:100',
            'guardian.phone'       => 'required|string|max:50',
            'guardian.email'       => 'required|email|max:255|unique:users,email',
            'guardian.sex'         => 'required|in:male,female,other',
            'guardian.address'     => 'nullable|string|max:500',

            'student.name'         => 'required|string|max:255',
            'student.pin'          => 'required|digits:14|unique:users,pin',
            'student.citizenship'  => 'required|string|max:100',
            'student.phone'        => 'nullable|string|max:50',
            'student.email'        => 'nullable|email|max:255|unique:users,email',
            'student.sex'          => 'required|in:male,female,other',
            'student.birth_date'   => 'required|date',
            'student.grade'        => 'required|integer|min:1|max:11',
            'student.class_letter' => 'nullable|string|max:2',
        ];
    }
}
