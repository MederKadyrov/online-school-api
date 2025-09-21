<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class TeacherController extends Controller
{

    // Привязать/сменить предметы учителя
    public function attachSubjects(Request $r, Teacher $teacher)
    {
        $payload = $r->validate([
            'subject_ids' => ['required','array','min:1'],
            'subject_ids.*' => ['integer','exists:subjects,id'],
        ]);
        $teacher->subjects()->sync($payload['subject_ids']);
        return response()->json(['message'=>'ok']);
    }
    /**
     * Display a listing of the resource.
     */
    /** Список учителей (поиск по ФИО/email/телефону), для выпадашек и таблицы */
    public function index(Request $r)
    {
        $q = Teacher::with(['user:id,last_name,first_name,middle_name,email,phone'])
            ->with('subjects:id,name'); // если надо сразу отдать предметы

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

        return $q->orderByDesc('id')->limit(200)->get()->map(function ($t) {
            $u = $t->user;
            $name = trim(implode(' ', array_filter([$u->last_name, $u->first_name, $u->middle_name])));
            return [
                'id'      => $t->id,
                'user_id' => $u->id,
                'name'    => $name,
                'email'   => $u->email,
                'phone'   => $u->phone,
                'subjects'=> $t->subjects?->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->values() ?? [],
            ];
        });
    }

    /** Просмотр одного учителя (для формы редактирования) */
    public function show($id)
    {
        $t = Teacher::with([
            'user:id,last_name,first_name,middle_name,email,phone,sex,pin,citizenship,address',
            'subjects:id,name'
        ])->findOrFail($id);

        $u = $t->user;
        return [
            'id'         => $t->id,
            'user_id'    => $u->id,
            'last_name'  => $u->last_name,
            'first_name' => $u->first_name,
            'middle_name'=> $u->middle_name,
            'email'      => $u->email,
            'phone'      => $u->phone,
            'sex'        => $u->sex,
            'pin'        => $u->pin,
            'citizenship'=> $u->citizenship,
            'address'    => $u->address,
            'subjects'   => $t->subjects->pluck('id')->all(), // для чекбоксов
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    // Создать преподавателя (admin only)
    public function store(Request $r)
    {
        $data = $r->validate([
            'last_name'   => ['required','string','max:60'],
            'first_name'  => ['required', 'string','max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'email'       => ['required','email','max:255', Rule::unique('users','email')],
            'password'    => 'required|string|min:8|confirmed',
            'phone'       => ['nullable','string','max:30'],
            'sex'         => ['required', Rule::in(['male','female'])],
            'pin'         => ['required','regex:/^\d{14}$/', Rule::unique('users','pin')],
            'citizenship' => ['required','string','max:10'],
            'address'     => ['nullable','string','max:255'],

            // НЕ обязательно, но удобно сразу привязать предметы:
            'subjects' => ['sometimes','array'],
            'subjects.*' => ['integer','exists:subjects,id'],
        ]);

        // создаём пользователя
        $user = User::create([
            'last_name'   => $data['last_name'],
            'first_name'  => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'email'=> $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'sex' => $data['sex'],
            'pin' => $data['pin'],
            'citizenship' => $data['citizenship'],
            'address' => $data['address'] ?? null,
        ]);

        // роль teacher + запись в teachers
        $user->assignRole('teacher');
        $teacher = Teacher::create(['user_id' => $user->id]);

        // привяжем предметы, если передали
        if (!empty($data['subjects'])) {
            // ВАЖНО: если у тебя пивот называется 'teacher_subject', в модели Teacher должна быть:
            // return $this->belongsToMany(Subject::class, 'teacher_subject');
            $teacher->subjects()->sync($data['subjects']);
        }

        return response()->json([
            'teacher' => [
                'id' => $teacher->id,
                'user_id' => $user->id,
                'email'=> $user->email,
            ]
        ], 201);
    }



    public function update(Request $r, $id)
    {
        $t = Teacher::with('user')->findOrFail($id);
        $u = $t->user;

        $data = $r->validate([
            'last_name'   => 'sometimes|required|string|max:60',
            'first_name'  => 'sometimes|required|string|max:60',
            'middle_name' => 'nullable|string|max:60',
            'email'       => ['sometimes','required','email','max:255', Rule::unique('users','email')->ignore($u->id)],
            'phone'       => 'nullable|string|max:30',
            'sex'         => ['sometimes','required', Rule::in(['male','female'])],
            'pin'         => ['sometimes','required','regex:/^\d{14}$/', Rule::unique('users','pin')->ignore($u->id)],
            'citizenship' => 'sometimes|required|string|max:10',
            'address'     => 'nullable|string|max:255',
            'password'    => 'nullable|string|min:8|confirmed',
            'subjects'    => 'array',
            'subjects.*'  => 'integer|exists:subjects,id',
        ]);

        DB::transaction(function () use ($data, $t, $u) {
            // обновляем User
            $update = array_intersect_key($data, array_flip([
                'last_name','first_name','middle_name','email','phone','sex','pin','citizenship','address'
            ]));
            if (!empty($update)) {
                $u->update($update);
            }
            if (!empty($data['password'])) {
                $u->update(['password' => Hash::make($data['password'])]);
            }

            // синхронизируем предметы, если передали
            if (array_key_exists('subjects', $data)) {
                $t->subjects()->sync($data['subjects'] ?? []);
            }
        });

        return response()->json(['message' => 'Teacher updated']);
    }

    /** Удаление */
    public function destroy($id)
    {
        $t = Teacher::with('user')->findOrFail($id);

        DB::transaction(function () use ($t) {
            // сначала отвяжем предметы
            $t->subjects()->detach();

            // вариант 1: удаляем оба (Teacher и связанного User)
            // (если по бизнес-правилам нужно «деактивировать», можно вместо delete → removeRole/soft delete)
            $user = $t->user;

            $t->delete();

            // убрать роль (на всякий случай), затем удалить пользователя
            if ($user && $user->hasRole('teacher')) {
                $user->removeRole('teacher');
            }
            $user?->delete();
        });

        return response()->json(['message' => 'Teacher deleted']);
    }


    /**
     * Display the specified resource.
     */

    /**
     * Update the specified resource in storage.
     */
}
