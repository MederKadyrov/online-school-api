<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterStudentRequest;
use App\Http\Resources\StudentRegistrationResource;
use App\Services\RegisterStudentService;
use Illuminate\Http\JsonResponse;

class RegisterStudentController extends Controller
{
    public function __construct(
        protected RegisterStudentService $service
    ) {}

    public function store(RegisterStudentRequest $request): JsonResponse
    {
        $student = $this->service->handle(
            guardianData: $request->validated()['guardian'],
            studentData:  $request->validated()['student']
        );

        return (new StudentRegistrationResource($student))
            ->response()
            ->setStatusCode(201);
    }
}
