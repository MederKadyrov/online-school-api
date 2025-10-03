<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\{Paragraph, Quiz, QuizAttempt, QuizAnswer, QuizQuestion, QuizOption};
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    private function meStudentId(Request $r): int {
        $student = $r->user()->student;
        abort_unless($student, 403);
        return $student->id;
    }
    private function toFiveScale(float $score, float $max): int {
        if ($max <= 0) return 2;
        $pct = 100 * $score / $max;
        return $pct >= 90 ? 5 : ($pct >= 75 ? 4 : ($pct >= 60 ? 3 : 2));
    }

    public function getQuiz(Request $r, Paragraph $paragraph) {
        $quiz = $paragraph->quiz()->where('status','published')->first();
        if (!$quiz) return response()->json(null);
        $q = $quiz->load(['questions.options' => fn($x)=>$x->orderBy('position')])
            ->load(['questions'=> fn($x)=>$x->orderBy('position')]);
        // Шафл по желанию
        if ($quiz->shuffle) {
            $q->questions = $q->questions->shuffle()->values();
            foreach ($q->questions as $qq) $qq->options = $qq->options->shuffle()->values();
        }
        return $q;
    }

    public function start(Request $r, Quiz $quiz) {
        abort_unless($quiz->status==='published', 403);
        $sid = $this->meStudentId($r);

        // лимит попыток
        if ($quiz->max_attempts) {
            $count = QuizAttempt::where('quiz_id',$quiz->id)->where('student_id',$sid)->count();
            abort_if($count >= $quiz->max_attempts, 403, 'Достигнут лимит попыток');
        }

        $attempt = QuizAttempt::create([
            'quiz_id'    => $quiz->id,
            'student_id' => $sid,
            'started_at' => now(),
            'status'     => 'in_progress',
            'score'      => 0,
        ]);
        return $attempt;
    }

    public function answer(Request $r, QuizAttempt $attempt) {
        $sid = $this->meStudentId($r);
        abort_unless($attempt->student_id === $sid, 403);
        abort_if($attempt->status !== 'in_progress', 403, 'Попытка завершена');

        $data = $r->validate([
            'question_id'        => 'required|integer|exists:quiz_questions,id',
            'selected_option_ids'=> 'nullable|array',
            'selected_option_ids.*'=> 'integer|exists:quiz_options,id',
            'text_answer'        => 'nullable|string',
        ]);

        $question = QuizQuestion::findOrFail($data['question_id']);
        abort_unless($question->quiz_id === $attempt->quiz_id, 422, 'Вопрос не из этого теста');

        $payload = [
            'selected_option_ids' => $question->type==='text' ? null : ($data['selected_option_ids'] ?? []),
            'text_answer'         => $question->type==='text' ? ($data['text_answer'] ?? null) : null,
        ];

        QuizAnswer::updateOrCreate(
            ['attempt_id'=>$attempt->id, 'question_id'=>$question->id],
            $payload
        );

        return ['message'=>'saved'];
    }

    public function finish(Request $r, QuizAttempt $attempt) {
        $sid = $this->meStudentId($r);
        abort_unless($attempt->student_id === $sid, 403);
        abort_if($attempt->status !== 'in_progress', 403);

        $quiz = $attempt->quiz()->with(['questions.options'])->first();
        $answers = $attempt->answers()->get()->keyBy('question_id');

        DB::transaction(function() use ($attempt, $quiz, $answers) {
            $score = 0;

            foreach ($quiz->questions as $q) {
                $ans = $answers->get($q->id);
                if (!$ans) continue;

                if (in_array($q->type, ['single','multiple'])) {
                    $correct = $q->options->where('is_correct', true)->pluck('id')->sort()->values();
                    $selected = collect($ans->selected_option_ids ?? [])->sort()->values();
                    $isRight = $correct->count() > 0 && $correct->toJson() === $selected->toJson();
                    $auto = $isRight ? $q->points : 0;
                    $ans->auto_score = $auto;
                    $ans->save();
                    $score += $auto;
                } else {
                    $ans->auto_score = 0; // текст — вручную если потребуется
                    $ans->save();
                }
            }

            $attempt->score       = $score;
            $attempt->finished_at = now();
            $attempt->autograded  = ($quiz->questions->where('type','text')->count()===0);
            $attempt->grade_5     = $this->toFiveScale($score, max(1, $quiz->max_points));
            $attempt->status      = $attempt->autograded ? 'graded' : 'submitted';
            $attempt->save();
        });

        return $attempt->fresh();
    }

    public function myAttempts(Request $r, Quiz $quiz) {
        $sid = $this->meStudentId($r);
        return $quiz->attempts()->where('student_id',$sid)->orderByDesc('id')->get();
    }
}
