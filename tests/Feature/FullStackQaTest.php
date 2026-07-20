<?php

namespace Tests\Feature;

use App\Models\Camp;
use App\Models\Family;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FullStackQaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private User $dataEntry;

    private Camp $sourceCamp;

    private Camp $targetCamp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('email', 'admin@gpd.com')->firstOrFail();
        $this->manager = User::where('email', 'manager@gpd.com')->firstOrFail();
        $this->dataEntry = User::where('email', 'data@gpd.com')->firstOrFail();
        $this->sourceCamp = Camp::where('name', 'مخيم جباليا')->firstOrFail();
        $this->targetCamp = Camp::where('name', 'مخيم خان يونس')->firstOrFail();
    }

    public function test_authentication_scenarios_and_protected_routes(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@gpd.com',
            'password' => 'ahmad-123',
        ])->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonStructure(['token']);

        $this->postJson('/api/login', [
            'email' => 'manager@gpd.com',
            'password' => 'manager-123',
        ])->assertOk()->assertJsonPath('user.role', 'manager')->assertJsonStructure(['token']);

        $login = $this->postJson('/api/login', [
            'email' => 'data@gpd.com',
            'password' => 'data-12345',
        ])->assertOk()->assertJsonPath('user.role', 'data_entry')->assertJsonStructure(['token']);
        $dataEntryTokenId = (int) str($login->json('token'))->before('|')->toString();

        $this->postJson('/api/login', [
            'email' => 'data@gpd.com',
            'password' => 'bad-password',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ])->assertUnprocessable();

        $this->dataEntry->update(['is_active' => false]);
        $this->postJson('/api/login', [
            'email' => 'data@gpd.com',
            'password' => 'data-12345',
        ])->assertForbidden();
        $this->dataEntry->update(['is_active' => true]);

        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/users')->assertUnauthorized();

        $headers = ['Authorization' => 'Bearer '.$login->json('token')];
        $this->withHeaders($headers)->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.email', 'data@gpd.com');
        $this->withHeaders($headers)->getJson('/api/users')->assertForbidden();
        $this->withHeaders($headers)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $dataEntryTokenId]);
    }

    public function test_family_member_transfer_and_population_consistency(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $family = $this->postJson('/api/families', $this->familyPayload())
            ->assertCreated()
            ->assertJsonPath('status', true);

        $familyId = $family->json('data.id');
        $this->sourceCamp->refresh();
        $this->assertSame(3, $this->sourceCamp->current_population);

        $member = $this->postJson("/api/families/{$familyId}/members", [
            'name' => 'QA Member',
            'gender' => 'female',
            'birth_date' => '2018-01-01',
            'national_id' => '999-2026-2000',
            'has_disability' => true,
        ])->assertOk();

        $memberId = $member->json('data.id');
        $this->assertDatabaseHas('family_members', ['id' => $memberId, 'name' => 'QA Member']);
        $this->assertSame(4, Family::find($familyId)->members_count);
        $this->assertSame(4, $this->sourceCamp->fresh()->current_population);

        $this->putJson("/api/members/{$memberId}", [
            'name' => 'QA Member Updated',
            'gender' => 'female',
            'birth_date' => '2017-01-01',
        ])->assertOk();
        $this->assertDatabaseHas('family_members', ['id' => $memberId, 'name' => 'QA Member Updated']);

        $this->deleteJson("/api/members/{$memberId}")->assertOk();
        $this->assertSame(3, Family::find($familyId)->members_count);
        $this->assertSame(3, $this->sourceCamp->fresh()->current_population);

        $transfer = $this->postJson('/api/transfer-requests', [
            'family_id' => $familyId,
            'from_camp_id' => $this->sourceCamp->id,
            'to_camp_id' => $this->targetCamp->id,
            'reason' => 'QA transfer',
        ])->assertCreated();

        $this->getJson('/api/transfer-requests')
            ->assertOk()
            ->assertJsonPath('summary.pending', 1)
            ->assertJsonPath('data.0.head_name', 'QA Family');

        Sanctum::actingAs($this->manager);

        $this->patchJson('/api/transfer-requests/'.$transfer->json('data.id').'/approve', [
            'manager_note' => 'Approved in QA',
        ])->assertOk();

        $this->assertSame($this->targetCamp->id, Family::find($familyId)->camp_id);
        $this->assertSame(0, $this->sourceCamp->fresh()->current_population);
        $this->assertSame(3, $this->targetCamp->fresh()->current_population);

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/families/{$familyId}")->assertOk();
        $this->assertSame(0, $this->targetCamp->fresh()->current_population);
    }

    public function test_users_camps_reports_and_validation(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $this->getJson('/api/camps')->assertOk()->assertJsonFragment(['name' => 'مخيم جباليا']);
        $this->patchJson('/api/user/select-camp', ['camp_id' => $this->targetCamp->id])
            ->assertOk()
            ->assertJsonPath('camp_id', $this->targetCamp->id);

        $this->postJson('/api/families', [])->assertUnprocessable();

        Sanctum::actingAs($this->admin);

        $user = $this->postJson('/api/users', [
            'name' => 'QA User',
            'email' => 'qa.user@gpd.com',
            'password' => 'password123',
            'role' => 'data_entry',
            'camp_id' => $this->sourceCamp->id,
            'phone' => '059-111-1111',
        ])->assertCreated();

        $userId = $user->json('user.id');
        $this->assertTrue(User::find($userId)->hasRole('data_entry'));

        $this->putJson("/api/users/{$userId}", [
            'name' => 'QA User Updated',
            'email' => 'qa.user@gpd.com',
            'role' => 'manager',
            'phone' => '059-222-2222',
        ])->assertOk();
        $this->assertTrue(User::find($userId)->hasRole('manager'));

        $this->putJson("/api/users/{$userId}", [
            'email' => 'admin@gpd.com',
        ])->assertUnprocessable();

        $this->postJson("/api/users/{$userId}/toggle-status")->assertOk();
        $this->assertFalse(User::find($userId)->is_active);

        $this->deleteJson("/api/users/{$userId}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $userId]);

        foreach (['demographic', 'vulnerability', 'transfers', 'periodic'] as $report) {
            $this->getJson("/api/reports/{$report}")->assertOk();
            $this->get("/api/reports/{$report}/export/excel")->assertOk();
            $this->get("/api/reports/{$report}/export/pdf")->assertOk();
        }
    }

    public function test_text_inputs_are_sanitized_at_api_boundaries(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $family = $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '<b>999</b>',
            'head_name' => '<script>alert(1)</script>QA XSS',
            'phone' => '<i>059-333-3333</i>',
            'original_city' => '<b>غزة</b>',
            'members' => [
                [
                    'name' => '<script>alert(2)</script>Member XSS',
                    'gender' => 'female',
                    'national_id' => '<b>999-2026-3001</b>',
                ],
            ],
        ]))->assertCreated();

        $familyId = $family->json('data.id');
        $this->assertDatabaseHas('families', [
            'id' => $familyId,
            'national_id' => '999',
            'head_name' => 'alert(1)QA XSS',
            'phone' => '059-333-3333',
        ]);
        $this->assertDatabaseHas('family_members', [
            'family_id' => $familyId,
            'name' => 'alert(2)Member XSS',
            'national_id' => '999-2026-3001',
        ]);

        $transfer = $this->postJson('/api/transfer-requests', [
            'family_id' => $familyId,
            'from_camp_id' => $this->sourceCamp->id,
            'to_camp_id' => $this->targetCamp->id,
            'reason' => '<b>Needs move</b>',
        ])->assertCreated();
        $this->assertDatabaseHas('transfer_requests', [
            'id' => $transfer->json('data.id'),
            'reason' => 'Needs move',
        ]);

        Sanctum::actingAs($this->manager);
        $this->patchJson('/api/transfer-requests/'.$transfer->json('data.id').'/reject', [
            'manager_note' => '<i>No space</i>',
        ])->assertOk();
        $this->assertDatabaseHas('transfer_requests', [
            'id' => $transfer->json('data.id'),
            'manager_note' => 'No space',
        ]);
    }

    private function familyPayload(array $overrides = []): array
    {
        return array_merge([
            'national_id' => '999-2026-1000',
            'head_name' => 'QA Family',
            'phone' => '059-000-0000',
            'birth_date' => '1980-01-01',
            'original_governorate' => 'غزة',
            'original_city' => 'غزة',
            'shelter_number' => 'QA-1',
            'members_count' => 3,
            'adults_count' => 2,
            'children_count' => 1,
            'pwd_count' => 0,
            'is_female_headed' => false,
            'has_pwd' => false,
        ], $overrides);
    }
}
