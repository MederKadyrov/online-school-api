<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Paragraph};
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\QuizOption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    private function authorizeParagraph(Paragraph $paragraph) {
        $course = $paragraph->chapter->module->course;
        $this->authorize('manage', $course);
    }
    private function authorizeQuiz(Quiz $quiz) {
        $this->authorize('manage', $quiz->paragraph->chapter->module->course);
    }

    public function store(Request $r, Paragraph $paragraph) {
        $this->authorizeParagraph($paragraph);
        $data = $r->validate([
            'title'         => 'required|string|max:150',
            'instructions'  => 'nullable|string',
            'time_limit_sec'=> 'nullable|integer|min:30|max:7200',
            'max_attempts'  => 'nullable|integer|min:1|max:10',
            'shuffle'       => 'boolean',
        ]);
        // правило "1 тест на параграф" обеспечено unique(paragraph_id)
        $quiz = Quiz::create([
            'paragraph_id'  => $paragraph->id,
            'title'         => $data['title'],
            'instructions'  => $data['instructions'] ?? null,
            'time_limit_sec'=> $data['time_limit_sec'] ?? null,
            'max_attempts'  => $data['max_attempts'] ?? null,
            'shuffle'       => $data['shuffle'] ?? false,
            'status'        => 'draft',
            'max_points'    => 0,
        ]);
        return response()->json($quiz, 201);
    }

    public function show(Request $r, Quiz $quiz) {
        $this->authorizeQuiz($quiz);
        return $quiz->load(['questions.options' => fn($q) => $q->orderBy('position')])
            ->load(['questions' => fn($q) => $q->orderBy('position')]);
    }

    public function update(Request $r, Quiz $quiz) {
        $this->authorizeQuiz($quiz);
        $data = $r->validate([
            'title'         => 'sometimes|required|string|max:150',
            'instructions'  => 'sometimes|nullable|string',
            'time_limit_sec'=> 'sometimes|nullable|integer|min:30|max:7200',
            'max_attempts'  => 'sometimes|nullable|integer|min:1|max:10',
            'shuffle'       => 'sometimes|boolean',
            'status'        => ['sometimes', Rule::in(['draft','published'])],
        ]);
        $quiz->update($data);
        return $quiz->fresh();
    }

    public function publish(Request $r, Quiz $quiz) {
        $this->authorizeQuiz($quiz);
        $sum = (int) $quiz->questions()->sum('points');
        $quiz->update(['status'=>'published', 'max_points'=>$sum]);
        return ['message'=>'ok', 'max_points'=>$sum];
    }

    public function addQuestion(Request $r, Quiz $quiz) {
        $this->authorizeQuiz($quiz);
        $data = $r->validate([
            'type'     => ['required', Rule::in(['single','multiple','text'])],
            'text'     => 'required|string',
            'points'   => 'nullable|integer|min:1|max:100',
        ]);
        $pos = (int) $quiz->questions()->max('position') + 1;
        $q = QuizQuestion::create([
            'quiz_id'  => $quiz->id,
            'type'     => $data['type'],
            'text'     => $data['text'],
            'points'   => $data['points'] ?? 1,
            'position' => $pos,
        ]);
        return response()->json($q, 201);
    }

    public function updateQuestion(Request $r, QuizQuestion $question) {
        $this->authorizeQuiz($question->quiz);
        $data = $r->validate([
            'text'   => 'sometimes|required|string',
            'points' => 'sometimes|integer|min:1|max:100',
        ]);
        $question->update($data);
        return $question->fresh()->load('options');
    }

    public function destroyQuestion(Request $r, QuizQuestion $question) {
        $this->authorizeQuiz($question->quiz);
        $quiz = $question->quiz;
        DB::transaction(function() use ($question, $quiz) {
            $question->delete();
            // сжать позиции
            $ids = $quiz->questions()->orderBy('position')->orderBy('id')->pluck('id')->all();
            $pos=1;
            foreach($ids as $id) {
                QuizQuestion::where('id',$id)->update(['position'=>$pos++]);
            }
        });
        return ['message'=>'deleted'];
    }

    public function addOption(Request $r, QuizQuestion $question) {
        $this->authorizeQuiz($question->quiz);
        if ($question->type === 'text') {
            return response()->json([
                'message' => 'Для текстового вопроса нельзя добавлять варианты.'
            ], 422);
        }

        $data = $r->validate([
            'text'       => 'required|string',
            'is_correct' => 'boolean',
        ]);
        $maxPos = $question->options()->max('position');
        $pos = (int) ($maxPos ?? 0) + 1;
        $opt = QuizOption::create([
            'question_id' => $question->id,
            'text'        => $data['text'],
            'is_correct'  => $data['is_correct'] ?? false,
            'position'    => $pos,
        ]);
        return response()->json($opt->fresh(), 201);
    }

    public function updateOption(Request $r, QuizOption $option) {
        $this->authorizeQuiz($option->question->quiz);

        $data = $r->validate([
            'text'       => 'sometimes|required|string',
            'is_correct' => 'sometimes|boolean',
        ]);

        // обычное обновление текста
        if (array_key_exists('text', $data)) {
            $option->text = $data['text'];
        }

        // если меняем корректность
        if (array_key_exists('is_correct', $data)) {
            $question = $option->question; // load question
            if ($question->type === 'single' && $data['is_correct'] === true) {
                // одиночный правильный: выключаем другие
                \DB::transaction(function() use ($option, $question, $data) {
                    QuizOption::where('question_id', $question->id)
                        ->where('id', '!=', $option->id)
                        ->update(['is_correct' => false]);
                    $option->is_correct = true;
                    $option->save();
                });
                return $option->fresh();
            } else {
                $option->is_correct = (bool)$data['is_correct'];
            }
        }

        $option->save();
        return $option->fresh();
    }

    public function destroyOption(Request $r, QuizOption $option) {
        $this->authorizeQuiz($option->question->quiz);
        $q = $option->question;
        DB::transaction(function() use ($option, $q) {
            $option->delete();
            $ids = $q->options()->orderBy('position')->orderBy('id')->pluck('id')->all();
            $pos=1;
            foreach($ids as $id) {
                QuizOption::where('id',$id)->update(['position'=>$pos++]);
            }
        });
        return ['message'=>'deleted'];
    }

    public function byParagraph(Request $r, \App\Models\Paragraph $paragraph)
    {
        // проверяем, что учитель управляет этим курсом
        $course = $paragraph->chapter->module->course;
        $this->authorize('manage', $course);

        // вернём тест (draft или published), если есть
        $quiz = $paragraph->quiz()->with(['questions.options' => fn($q)=>$q->orderBy('position')])
            ->with(['questions' => fn($q)=>$q->orderBy('position')])
            ->first();

        return $quiz ?: response()->json(null);
    }
}
