<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Course, Module, Chapter, Paragraph, Resource};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StructureController extends Controller
{
    public function createModule(Request $r, Course $course)
    {
        $data = $r->validate([
            'title' => 'nullable|string|max:150'
        ]);

        // вычислим number/position = max+1
        $max = (int) $course->modules()->max('number');
        $n = $max + 1;
        $m = Module::create([
            'course_id' => $course->id,
            'number'    => $n,
            'position'  => $n,
            'title'     => $data['title'] ?? "Модуль {$n}",
        ]);

        return response()->json($m, 201);
    }

    public function createChapter(Request $r, Module $module)
    {
        $data = $r->validate(['title'=>'required|string|max:150']);

        $max = (int) $module->chapters()->max('position');
        $n = $max + 1;

        $c = Chapter::create([
            'module_id' => $module->id,
            'number'    => $n,
            'position'  => $n,
            'title'     => $data['title'],
        ]);

        return response()->json($c, 201);
    }

    public function createParagraph(Request $r, Chapter $chapter)
    {
        $data = $r->validate([
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string',
        ]);

        $max = (int) $chapter->paragraphs()->max('position');
        $n = $max + 1;

        $p = Paragraph::create([
            'chapter_id'  => $chapter->id,
            'number'      => $n,
            'position'    => $n,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($p, 201);
    }

//    public function createResource(Request $r, Paragraph $paragraph)
//    {
//        $data = $r->validate([
//            'type'            => 'required|in:video,file,link,presentation,text',
//            'title'           => 'nullable|string|max:150',
//            'url'             => 'nullable|string|max:2000',
//            'path'            => 'nullable|string|max:500',
//            'mime'            => 'nullable|string|max:120',
//            'size_bytes'      => 'nullable|integer|min:0',
//            'external_provider'=> 'nullable|string|max:50',
//            'text_content'    => 'nullable|string',
//            'duration_sec'    => 'nullable|integer|min:0',
//        ]);
//
//        $max = (int) $paragraph->resources()->max('position');
//        $pos = $max + 1;
//
//        $res = Resource::create(array_merge($data, [
//            'paragraph_id' => $paragraph->id,
//            'position'     => $pos,
//        ]));
//
//        return response()->json($res, 201);
//    }

    // По желанию — простые реордеры:
    public function reorderChapters(\Illuminate\Http\Request $r, \App\Models\Module $module)
    {
        $this->authorize('manage', $module->course);

        $data = $r->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:chapters,id',
        ]);

        $foreignIds = \App\Models\Chapter::whereIn('id', $data['ids'])
            ->where('module_id', '!=', $module->id)
            ->pluck('id')->all();

        if (!empty($foreignIds)) {
            return response()->json([
                'message' => 'Некоторые главы принадлежат другому модулю',
                'foreign_ids' => $foreignIds,
            ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($module, $data) {

            $ids = $data['ids'];

            // временный сдвиг
            \App\Models\Chapter::where('module_id', $module->id)
                ->whereIn('id', $ids)
                ->update([
                    'position' => \Illuminate\Support\Facades\DB::raw('position + 1000'),
                    'number'   => \Illuminate\Support\Facades\DB::raw('number + 1000'),
                ]);

            // финальная нумерация
            $pos = 1;
            foreach ($ids as $id) {
                \App\Models\Chapter::where('module_id', $module->id)
                    ->where('id', $id)
                    ->update(['position' => $pos, 'number' => $pos]);
                $pos++;
            }
        });

        return ['message' => 'ok'];
    }

    public function reorderParagraphs(\Illuminate\Http\Request $r, \App\Models\Chapter $chapter)
    {
        $this->authorize('manage', $chapter->module->course);

        $data = $r->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:paragraphs,id',
        ]);

        // Проверим, что все id реально из этой главы
        $foreignIds = \App\Models\Paragraph::whereIn('id', $data['ids'])
            ->where('chapter_id', '!=', $chapter->id)
            ->pluck('id')->all();

        if (!empty($foreignIds)) {
            return response()->json([
                'message' => 'Некоторые параграфы принадлежат другой главе',
                'foreign_ids' => $foreignIds,
            ], 422);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($chapter, $data) {

            $ids = $data['ids'];

            // 1) ВРЕМЕННЫЙ СДВИГ, чтобы не ловить коллизии уникального индекса
            \App\Models\Paragraph::where('chapter_id', $chapter->id)
                ->whereIn('id', $ids)
                ->update([
                    'position' => \Illuminate\Support\Facades\DB::raw('position + 1000'),
                    'number'   => \Illuminate\Support\Facades\DB::raw('number + 1000'),
                ]);

            // 2) ФИНАЛЬНАЯ НУМЕРАЦИЯ: position == number
            $pos = 1;
            foreach ($ids as $id) {
                \App\Models\Paragraph::where('chapter_id', $chapter->id)
                    ->where('id', $id)
                    ->update(['position' => $pos, 'number' => $pos]);
                $pos++;
            }
        });

        return ['message' => 'ok'];
    }


    public function reorderResources(Request $r, Paragraph $paragraph)
    {
        $this->authorize('manage', $paragraph->chapter->module->course);

        $data = $r->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:resources,id',
        ]);

        DB::transaction(function() use ($paragraph, $data) {
            $pos = 1;
            foreach ($data['ids'] as $id) {
                Resource::where('paragraph_id', $paragraph->id)
                    ->where('id', $id)
                    ->update(['position' => $pos++]);
            }
        });

        return ['message' => 'ok'];
    }

    public function listChapters(\Illuminate\Http\Request $r, \App\Models\Module $module)
    {
        // защита: владеет ли учитель курсом?
        $this->authorize('manage', $module->course);
        $chapters = $module->chapters()
            ->orderBy('position')->orderBy('number')
            ->get(['id','module_id','number','position','title']);
        return $chapters;
    }

    public function listParagraphs(\Illuminate\Http\Request $r, \App\Models\Chapter $chapter)
    {
        $this->authorize('manage', $chapter->module->course);

        $items = $chapter->paragraphs()
            ->withCount('resources') // посчитаем количество ресурсов
            ->withExists([
                'assignment as has_assignment', // проверим наличие задания
                'quiz as has_quiz',             // проверим наличие теста
            ])
            // подгрузим статусы теста и задания для UI
            ->with(['quiz:id,paragraph_id,status', 'assignment:id,paragraph_id,status'])
            ->orderBy('position')
            ->orderBy('number')
            ->get([
                'id',
                'chapter_id',
                'number',
                'position',
                'title',
                'description'
            ])
            ->map(function ($p) {
                return [
                    'id'               => $p->id,
                    'chapter_id'       => $p->chapter_id,
                    'number'           => $p->number,
                    'position'         => $p->position,
                    'title'            => $p->title,
                    'description'      => $p->description,
                    'resources_count'  => (int) $p->resources_count,
                    'has_assignment'   => (bool) $p->has_assignment,
                    'has_quiz'         => (bool) $p->has_quiz,
                    'quiz_status'      => optional($p->quiz)->status, // null|draft|published
                    'assignment_status'=> optional($p->assignment)->status, // null|draft|published
                ];
            });

        return response()->json($items);
    }

    public function updateChapter(\Illuminate\Http\Request $r, \App\Models\Chapter $chapter)
    {
        $this->authorize('manage', $chapter->module->course);
        $data = $r->validate([
            'title'    => 'sometimes|required|string|max:150',
            'number'   => 'sometimes|integer|min:1',
            'position' => 'sometimes|integer|min:1',
        ]);
        $chapter->update($data);
        return $chapter->fresh(['module:id,course_id,number']);
    }

    public function destroyChapter(\Illuminate\Http\Request $r, \App\Models\Chapter $chapter)
    {
        $this->authorize('manage', $chapter->module->course);
        $module = $chapter->module;
        $chapter->delete();

        $this->renumberChapters($module); // ⬅︎
        return response()->json(['message'=>'deleted']);
    }

    public function updateParagraph(\Illuminate\Http\Request $r, \App\Models\Paragraph $paragraph)
    {
        $this->authorize('manage', $paragraph->chapter->module->course);
        $data = $r->validate([
            'title'       => 'sometimes|required|string|max:150',
            'description' => 'sometimes|nullable|string',
            'number'      => 'sometimes|integer|min:1',
            'position'    => 'sometimes|integer|min:1',
        ]);
        $paragraph->update($data);
        return $paragraph->fresh(['chapter:id,module_id,number']);
    }

    public function destroyParagraph(\Illuminate\Http\Request $r, \App\Models\Paragraph $paragraph)
    {
        $this->authorize('manage', $paragraph->chapter->module->course);
        $chapter = $paragraph->chapter;
        $paragraph->delete();

        $this->renumberParagraphs($chapter); // ⬅︎
        return response()->json(['message'=>'deleted']);
    }

    private function renumberParagraphs(\App\Models\Chapter $chapter): void
    {
        \Illuminate\Support\Facades\DB::transaction(function() use ($chapter) {
            // возьмём в текущем порядке
            $ids = \App\Models\Paragraph::where('chapter_id', $chapter->id)
                ->orderBy('position')->orderBy('id')
                ->pluck('id')->all();

            // временный сдвиг
            if ($ids) {
                \App\Models\Paragraph::whereIn('id', $ids)->update([
                    'position' => \Illuminate\Support\Facades\DB::raw('position + 1000'),
                    'number'   => \Illuminate\Support\Facades\DB::raw('number + 1000'),
                ]);

                // финальная нумерация
                $pos = 1;
                foreach ($ids as $id) {
                    \App\Models\Paragraph::where('id', $id)->update(['position'=>$pos,'number'=>$pos]);
                    $pos++;
                }
            }
        });
    }

    private function renumberChapters(\App\Models\Module $module): void
    {
        \Illuminate\Support\Facades\DB::transaction(function() use ($module) {
            $ids = \App\Models\Chapter::where('module_id', $module->id)
                ->orderBy('position')->orderBy('id')
                ->pluck('id')->all();

            if ($ids) {
                \App\Models\Chapter::whereIn('id', $ids)->update([
                    'position' => \Illuminate\Support\Facades\DB::raw('position + 1000'),
                    'number'   => \Illuminate\Support\Facades\DB::raw('number + 1000'),
                ]);

                $pos = 1;
                foreach ($ids as $id) {
                    \App\Models\Chapter::where('id', $id)->update(['position'=>$pos,'number'=>$pos]);
                    $pos++;
                }
            }
        });
    }

}
