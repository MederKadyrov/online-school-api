<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{Course, Module, Chapter, Paragraph, Resource};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResourceController extends Controller
{
    protected function authorizeParagraph(Paragraph $paragraph, Request $r): void
    {
        $course = $paragraph->chapter->module->course;
        $this->authorize('manage', $course);
    }

    public function index(Request $r, Paragraph $paragraph)
    {
        $this->authorizeParagraph($paragraph, $r);
        return $paragraph->resources()->orderBy('position')->get([
            'id','type','title','url','path','mime','size_bytes','external_provider','text_content','duration_sec','position'
        ]);
    }

    public function store(Request $r, Paragraph $paragraph)
    {
        $this->authorizeParagraph($paragraph, $r);

        $data = $r->validate([
            'type'            => ['required', Rule::in(['video','file','link','presentation','text'])],
            'title'           => ['nullable','string','max:150'],
            'url'             => ['nullable','string','max:2000'],
            'path'            => ['nullable','string','max:500'],
            'mime'            => ['nullable','string','max:120'],
            'size_bytes'      => ['nullable','integer','min:0'],
            'external_provider'=> ['nullable','string','max:50'],
            'text_content'    => ['nullable','string'],
            'duration_sec'    => ['nullable','integer','min:0'],
        ]);

        $maxPos = (int) $paragraph->resources()->max('position');
        $data['position'] = $maxPos + 1;
        $data['paragraph_id'] = $paragraph->id;

        $res = Resource::create($data);
        return response()->json($res, 201);
    }

    public function update(Request $r, Resource $resource)
    {
        // авторизация через родительский курс
        $this->authorize('manage', $resource->paragraph->chapter->module->course);

        $data = $r->validate([
            'title'           => ['sometimes','nullable','string','max:150'],
            'url'             => ['sometimes','nullable','string','max:2000'],
            'text_content'    => ['sometimes','nullable','string'],
            'duration_sec'    => ['sometimes','nullable','integer','min:0'],
            'position'        => ['sometimes','integer','min:1'],
            'external_provider'=> ['sometimes','nullable','string','max:50'],
        ]);

        $resource->update($data);
        return $resource->fresh();
    }

    public function destroy(Request $r, Resource $resource)
    {
        $this->authorize('manage', $resource->paragraph->chapter->module->course);
        $resource->delete();
        return response()->json(['message'=>'deleted']);
    }

    /** Загрузка файла ресурса (multipart/form-data) */
    public function uploadFile(Request $r)
    {
        $r->validate([
            'file' => ['required','file','max:102400'], // до ~100MB (настрой: php.ini upload_max_filesize/post_max_size)
        ]);

        $user = $r->user();
        if (!$user || !$user->hasRole('teacher')) {
            abort(403, 'Only teachers can upload resources');
        }

        $file = $r->file('file');
        $path = $file->store('resources/'.date('Y/m/d'), 'public'); // диск "public"
        $mime = $file->getClientMimeType();
        $size = $file->getSize();
        $url  = Storage::disk('public')->url($path);

        return response()->json([
            'path'       => $path,
            'mime'       => $mime,
            'size_bytes' => $size,
            'url'        => $url,
            'title'      => $file->getClientOriginalName(),
        ], 201);
    }
}
