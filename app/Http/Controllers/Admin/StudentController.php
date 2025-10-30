<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function index(Request $r)
    {
        $q = Student::with(['user:id,last_name,first_name,middle_name,email,phone','level'])
            ->orderByDesc('id');

        if ($r->filled('search')) {
            $term = '%'.$r->string('search')->toString().'%';
            $q->whereHas('user', function ($uq) use ($term) {
                $uq->where('last_name','like',$term)
                    ->orWhere('first_name','like',$term)
                    ->orWhere('middle_name','like',$term)
                    ->orWhere('email','like',$term)
                    ->orWhere('phone','like',$term);
            });
        }

        if ($r->filled('level_id')) {
            $q->where('level_id', $r->integer('level_id'));
        }
        // опциональная совместимость:
        if ($r->filled('grade')) {
            $q->whereHas('level', fn($qq) => $qq->where('number', (int)$r->input('grade')));
        }

        return $q->limit(100)->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->user->name, // аксессор склеит ФИО
            'email' => $s->user->email,
            'phone' => $s->user->phone,
            'level' => $s->level ? [
                'id' => $s->level->id,
                'number' => $s->level->number,
                'title' => $s->level->title,
            ] : null,
        ]);
    }

    /**
     * Список всех студентов с фильтрацией для админ-панели
     */
    public function list(Request $r)
    {
        $q = Student::with(['user', 'level', 'group.level'])
            ->orderBy('id');

        // Фильтрация по ФИО и PIN
        if ($r->filled('search')) {
            $term = '%' . $r->input('search') . '%';
            $q->whereHas('user', function ($uq) use ($term) {
                $uq->where('last_name', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('middle_name', 'like', $term)
                    ->orWhere('pin', 'like', $term);
            });
        }

        // Фильтрация по полу
        if ($r->filled('gender')) {
            $q->whereHas('user', function ($uq) use ($r) {
                $uq->where('sex', $r->input('gender'));
            });
        }

        // Фильтрация по классу (level)
        if ($r->filled('level_id')) {
            $q->where('level_id', $r->input('level_id'));
        }

        // Фильтрация по группе
        if ($r->filled('group_id')) {
            $q->where('group_id', $r->input('group_id'));
        }

        // Получаем с пагинацией
        $perPage = $r->input('per_page', 50);
        $students = $q->paginate($perPage);

        $data = $students->getCollection()->map(function($s) {
            $level = $s->level;
            $group = $s->group;
            $user = $s->user;

            return [
                'id' => $s->id,
                'full_name' => $user ? trim("{$user->last_name} {$user->first_name} {$user->middle_name}") : '',
                'gender' => $user->sex ?? '',
                'gender_display' => $user->sex === 'male' ? 'Мужской' : ($user->sex === 'female' ? 'Женский' : ($user->sex === 'other' ? 'Другое' : '—')),
                'level' => $level ? $level->number : '',
                'level_id' => $level ? $level->id : null,
                'group' => $group ? ($group->level->number . $group->class_letter) : '',
                'group_id' => $group ? $group->id : null,
                'birth_date' => $s->birth_date ?? '',
                'pin' => $user->pin ?? '',
            ];
        });

        return response()->json([
            'data' => $data,
            'current_page' => $students->currentPage(),
            'last_page' => $students->lastPage(),
            'per_page' => $students->perPage(),
            'total' => $students->total(),
        ]);
    }

    /**
     * Детальная информация о студенте
     */
    public function show($id)
    {
        $student = Student::with([
            'user',
            'level',
            'group.level',
            'guardians.user',
            'studentDocument'
        ])->findOrFail($id);

        $user = $student->user;
        $level = $student->level;
        $group = $student->group;

        return response()->json([
            'id' => $student->id,
            'full_name' => $user ? trim("{$user->last_name} {$user->first_name} {$user->middle_name}") : '',
            'first_name' => $user->first_name ?? '',
            'last_name' => $user->last_name ?? '',
            'middle_name' => $user->middle_name ?? '',
            'email' => $user->email ?? '',
            'phone' => $user->phone ?? '',
            'gender' => $user->sex ?? '',
            'gender_display' => $user->sex === 'male' ? 'Мужской' : ($user->sex === 'female' ? 'Женский' : ($user->sex === 'other' ? 'Другое' : '—')),
            'birth_date' => $student->birth_date ?? '',
            'pin' => $user->pin ?? '',
            'level' => $level ? [
                'id' => $level->id,
                'number' => $level->number,
                'title' => $level->title,
            ] : null,
            'group' => $group ? [
                'id' => $group->id,
                'display_name' => $group->level->number . $group->class_letter,
                'class_letter' => $group->class_letter,
            ] : null,
            'guardians' => $student->guardians->map(function($g) {
                $relationshipLabels = [
                    'father' => 'Отец',
                    'mother' => 'Мать',
                    'guardian' => 'Опекун',
                    'other' => 'Другое',
                ];
                return [
                    'id' => $g->id,
                    'full_name' => $g->user ? trim("{$g->user->last_name} {$g->user->first_name} {$g->user->middle_name}") : '',
                    'phone' => $g->user->phone ?? '',
                    'email' => $g->user->email ?? '',
                    'relationship' => $relationshipLabels[$g->relationship] ?? ($g->relationship ?? '—'),
                ];
            }),
            'document' => $student->studentDocument ? [
                'document_type' => $student->studentDocument->document_type ?? '',
                'document_number' => $student->studentDocument->document_number ?? '',
                'issue_date' => $student->studentDocument->issue_date ?? '',
                'issuing_authority' => $student->studentDocument->issuing_authority ?? '',
            ] : null,
        ]);
    }

    /**
     * Экспорт списка студентов в CSV
     */
    public function export(Request $r)
    {
        $q = Student::with(['user', 'level', 'group.level'])
            ->orderBy('id');

        // Применяем те же фильтры что и в list()
        if ($r->filled('search')) {
            $term = '%' . $r->input('search') . '%';
            $q->whereHas('user', function ($uq) use ($term) {
                $uq->where('last_name', 'like', $term)
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('middle_name', 'like', $term)
                    ->orWhere('pin', 'like', $term);
            });
        }

        if ($r->filled('gender')) {
            $q->whereHas('user', function ($uq) use ($r) {
                $uq->where('sex', $r->input('gender'));
            });
        }

        if ($r->filled('level_id')) {
            $q->where('level_id', $r->input('level_id'));
        }

        if ($r->filled('group_id')) {
            $q->where('group_id', $r->input('group_id'));
        }

        $students = $q->get();

        $filename = "students_" . date('Y-m-d') . ".csv";

        $callback = function() use ($students) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM для Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Заголовки таблицы
            fputcsv($file, ['№', 'ФИО', 'Пол', 'Класс', 'Группа', 'Дата рождения', 'PIN']);

            // Данные студентов
            foreach ($students as $index => $s) {
                $user = $s->user;
                $level = $s->level;
                $group = $s->group;

                $fullName = $user ? trim("{$user->last_name} {$user->first_name} {$user->middle_name}") : '';
                $genderDisplay = $user->sex === 'male' ? 'Мужской' : ($user->sex === 'female' ? 'Женский' : ($user->sex === 'other' ? 'Другое' : ''));
                $levelNumber = $level ? $level->number : '';
                $groupName = $group ? ($group->level->number . $group->class_letter) : '';

                fputcsv($file, [
                    $index + 1,
                    $fullName,
                    $genderDisplay,
                    $levelNumber,
                    $groupName,
                    $s->birth_date,
                    $user->pin ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
