<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterStudentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->seed(\Database\Seeders\AdminUserSeeder::class);
    }

    public function test_admin_can_register_student_with_guardian(): void
    {
        $admin = User::where('email','admin@example.com')->first();
        $token = $admin->createToken('spa')->plainTextToken;

        $payload = [
            'guardian' => [
                'name' => 'Parent A',
                'pin'  => '12345678901234',
                'citizenship' => 'KG',
                'phone' => '+996700000001',
                'email' => 'parent@example.com',
                'sex'   => 'male',
                'address'=> 'Address',
            ],
            'student' => [
                'name' => 'Student A',
                'pin'  => '98765432109876',
                'citizenship' => 'KG',
                'phone' => '+996700000002',
                'email' => 'student@example.com',
                'sex'   => 'male',
                'birth_date' => '2013-05-10',
                'grade' => 6,
                'class_letter' => 'А',
            ],
        ];

        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/register-student', $payload)
            ->assertCreated();

        $this->assertArrayHasKey('student', $res->json());
        $this->assertArrayHasKey('guardians', $res->json());
    }
}
