<?php namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Paragraph;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function index(Request $r, Paragraph $paragraph)
    {
        $user = $r->user();
        abort_unless($user && $user->hasRole('student'), 403);
        $student = $user->student;
        $groupId = $student?->group_id ?: ($student?->group->id ?? null);
        abort_unless($groupId, 403); // доступ к курсу параграфа по группе
        $course = $paragraph->chapter->module->course;
        $allowed = $course->groups()->where('groups.id', $groupId)->exists();
        abort_unless($allowed, 404);
        return $paragraph->resources()
            ->orderBy('position')
            ->get([ 'id','type','title','url','path','mime','size_bytes','external_provider','text_content','duration_sec','position' ]);
    }
}

