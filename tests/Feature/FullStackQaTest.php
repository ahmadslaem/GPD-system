<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Camp;
use App\Models\Family;
use App\Models\FamilyMember;
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
        $this->withHeaders($headers)->getJson('/api/users/statistics')->assertForbidden();
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

        $this->getJson('/api/users/statistics')
            ->assertOk()
            ->assertJson([
                'total_users' => 3,
                'active_users' => 3,
                'inactive_users' => 0,
                'data_entry_users' => 1,
            ]);

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

        $this->getJson('/api/users/statistics')
            ->assertOk()
            ->assertJsonPath('total_users', 4)
            ->assertJsonPath('data_entry_users', 2);

        $this->putJson("/api/users/{$userId}", [
            'name' => 'QA User Updated',
            'email' => 'qa.user@gpd.com',
            'role' => 'manager',
            'phone' => '059-222-2222',
        ])->assertOk();
        $this->assertTrue(User::find($userId)->hasRole('manager'));

        $this->getJson('/api/users/statistics')
            ->assertOk()
            ->assertJsonPath('data_entry_users', 1);

        $this->putJson("/api/users/{$userId}", [
            'email' => 'admin@gpd.com',
        ])->assertUnprocessable();

        $this->postJson("/api/users/{$userId}/toggle-status")->assertOk();
        $this->assertFalse(User::find($userId)->is_active);

        $this->getJson('/api/users/statistics')
            ->assertOk()
            ->assertJsonPath('active_users', 3)
            ->assertJsonPath('inactive_users', 1);

        $this->deleteJson("/api/users/{$userId}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $userId]);

        $this->getJson('/api/users/statistics')
            ->assertOk()
            ->assertJsonPath('total_users', 3);

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

    public function test_transfer_requests_require_valid_family_scope_and_distinct_camps(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $family = $this->postJson('/api/families', $this->familyPayload())
            ->assertCreated()
            ->json('data');

        $this->postJson('/api/transfer-requests', [
            'family_id' => $family['id'],
            'from_camp_id' => $this->targetCamp->id,
            'to_camp_id' => $this->sourceCamp->id,
            'reason' => 'Invalid source',
        ])->assertUnprocessable();

        $this->postJson('/api/transfer-requests', [
            'family_id' => $family['id'],
            'from_camp_id' => $this->sourceCamp->id,
            'to_camp_id' => $this->sourceCamp->id,
            'reason' => 'Same camp',
        ])->assertUnprocessable();

        $otherFamily = Family::create([
            'national_id' => '999-2026-4000',
            'head_name' => 'Other Camp Family',
            'phone' => '059-400-0000',
            'original_governorate' => 'غزة',
            'original_city' => 'خان يونس',
            'camp_id' => $this->targetCamp->id,
            'members_count' => 1,
            'adults_count' => 1,
            'children_count' => 0,
            'pwd_count' => 0,
            'is_female_headed' => false,
            'has_pwd' => false,
            'created_by' => $this->admin->id,
        ]);

        $this->postJson('/api/transfer-requests', [
            'family_id' => $otherFamily->id,
            'from_camp_id' => $this->targetCamp->id,
            'to_camp_id' => $this->sourceCamp->id,
            'reason' => 'Out of scope',
        ])->assertForbidden();
    }

    public function test_family_registration_requires_active_current_camp_selection(): void
    {
        $inactiveCamp = Camp::create([
            'name' => 'Inactive QA Camp',
            'location' => 'QA',
            'capacity' => 100,
            'current_population' => 0,
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->dataEntry);

        $this->getJson('/api/camps?active=1')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Inactive QA Camp']);

        $this->patchJson('/api/user/select-camp', ['camp_id' => $inactiveCamp->id])
            ->assertUnprocessable();

        $this->postJson('/api/families', $this->familyPayload([
            'camp_id' => $inactiveCamp->id,
            'national_id' => '999-2026-5000',
        ]))->assertUnprocessable();

        $this->postJson('/api/families', $this->familyPayload([
            'camp_id' => $this->targetCamp->id,
            'national_id' => '999-2026-5001',
        ]))->assertUnprocessable();

        $family = $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-5002',
        ]))->assertCreated();

        $this->assertDatabaseHas('families', [
            'id' => $family->json('data.id'),
            'camp_id' => $this->sourceCamp->id,
        ]);
        $this->assertSame(3, $this->sourceCamp->fresh()->current_population);
    }

    public function test_search_supports_phone_and_secures_global_scope(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $family = $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-6000',
            'phone' => '059-600-6000',
        ]))->assertCreated()->json('data');

        $this->getJson('/api/search/local?keyword=059-600')
            ->assertOk()
            ->assertJsonPath('data.0.id', $family['id']);

        $this->getJson('/api/search/global?keyword=059-600')
            ->assertForbidden();

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/search/global?keyword=059-600')
            ->assertOk()
            ->assertJsonPath('data.0.id', $family['id']);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/search/global?keyword=059-600')
            ->assertOk()
            ->assertJsonPath('data.0.id', $family['id']);
    }

    public function test_report_filters_and_exports_use_registered_family_counts(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-7000',
            'members_count' => 5,
            'adults_count' => 2,
            'children_count' => 3,
        ]))->assertCreated();

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/reports/demographic?camp_id='.$this->sourceCamp->id.'&vulnerability_level=low')
            ->assertOk()
            ->assertJsonPath('summary.total_families', 1)
            ->assertJsonPath('summary.total_individuals', 5);

        $this->get('/api/reports/demographic/export/excel?camp_id='.$this->sourceCamp->id)
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->get('/api/reports/demographic/export/pdf?camp_id='.$this->sourceCamp->id)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_add_member_without_birth_date_is_counted_as_adult(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-8000',
            'members_count' => 1,
            'adults_count' => 1,
            'children_count' => 0,
        ]))->assertCreated();

        $this->postJson('/api/families/1/members', [
            'name' => 'عضو من غير تاريخ ميلاد',
            'gender' => 'male',
        ])->assertOk();

        $family = Family::where('national_id', '999-2026-8000')->firstOrFail();

        $this->assertSame(2, (int) $family->members_count);
        $this->assertSame(2, (int) $family->adults_count);
        $this->assertSame(0, (int) $family->children_count);
    }

    public function test_update_member_birth_date_reclassifies_adults_and_children_counts(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-8001',
            'members_count' => 2,
            'adults_count' => 1,
            'children_count' => 1,
        ]))->assertCreated();

        $memberId = $this->postJson('/api/families/1/members', [
            'name' => 'طفل بيكبر',
            'gender' => 'male',
            'birth_date' => '2010-01-01',
        ])->assertOk()->json('data.id');

        // العضو كان طفل: 2 بالغ، 2 طفل، 3 أفراد
        $family = Family::where('national_id', '999-2026-8001')->firstOrFail();
        $this->assertSame([3, 1, 2], [(int) $family->members_count, (int) $family->adults_count, (int) $family->children_count]);

        // تعديل تاريخ الميلاد ليبقى بالغ: الطفل ينتقل لعدّاد البالغين
        $this->putJson('/api/members/'.$memberId, [
            'name' => 'طفل بيكبر',
            'gender' => 'male',
            'birth_date' => '1990-01-01',
        ])->assertOk();

        $family->refresh();
        $this->assertSame(3, (int) $family->members_count);
        $this->assertSame(2, (int) $family->adults_count);
        $this->assertSame(1, (int) $family->children_count);
    }

    public function test_update_member_disability_syncs_pwd_count_and_vulnerability(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-8002',
            'pwd_count' => 0,
            'has_pwd' => false,
            'is_female_headed' => true,
            'fhh_reason' => 'widow',
        ]))->assertCreated();

        $memberId = $this->postJson('/api/families/1/members', [
            'name' => 'عضو ذو إعاقة',
            'gender' => 'female',
            'birth_date' => '1985-05-05',
            'has_disability' => true,
        ])->assertOk()->json('data.id');

        $family = Family::where('national_id', '999-2026-8002')->firstOrFail();
        $this->assertSame(1, (int) $family->pwd_count);
        $this->assertTrue((bool) $family->has_pwd);
        $this->assertSame('medium', $family->vulnerability_level);

        // إلغاء الإعاقة يزيلها من العدادات ويعيد حساب الهشاشة
        $this->putJson('/api/members/'.$memberId, [
            'name' => 'عضو ذو إعاقة',
            'gender' => 'female',
            'birth_date' => '1985-05-05',
            'has_disability' => false,
        ])->assertOk();

        $family->refresh();
        $this->assertSame(0, (int) $family->pwd_count);
        $this->assertFalse((bool) $family->has_pwd);
        $this->assertSame('low', $family->vulnerability_level);
    }

    public function test_vulnerability_score_uses_unified_100_point_scale(): void
    {
        Sanctum::actingAs($this->dataEntry);

        // نفس الحالة اللي بتعرضها معاينة التسجيل: FHH + إعاقتين + اعتماد عالي + أسرة كبيرة + أطفال كتير
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9000',
            'members_count' => 10,
            'adults_count' => 3,
            'children_count' => 7,
            'pwd_count' => 2,
            'has_pwd' => true,
            'is_female_headed' => true,
            'fhh_reason' => 'widow',
        ]))->assertCreated();

        $family = Family::where('national_id', '999-2026-9000')->firstOrFail();

        // 20 (FHH) + 30 (2×PWD) + 15 (اعتماد) + 15 (حجم) + 10 (أطفال) = 90
        $this->assertSame(90, (int) $family->vulnerability_score);
        $this->assertSame('high', $family->vulnerability_level);

        // حالة بسيطة: رئاسة أنثى فقط = 20 نقطة → منخفض
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9001',
            'is_female_headed' => true,
            'fhh_reason' => 'widow',
        ]))->assertCreated();

        $simple = Family::where('national_id', '999-2026-9001')->firstOrFail();
        $this->assertSame(20, (int) $simple->vulnerability_score);
        $this->assertSame('low', $simple->vulnerability_level);
    }

    public function test_camps_index_returns_plain_array_for_frontend_compatibility(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $response = $this->getJson('/api/camps')->assertOk();

        // الواجهة بتتعامل مع الرد كمصفوفة مباشرة (activeCamps.map ...)
        $this->assertIsArray($response->json());
        $this->assertArrayHasKey('name', $response->json('0'));
    }

    public function test_families_index_supports_server_side_pagination_and_filters(): void
    {
        Sanctum::actingAs($this->dataEntry);

        // أنشئ 15 أسرة في نفس المخيم
        for ($i = 1; $i <= 15; $i++) {
            $this->postJson('/api/families', $this->familyPayload([
                'national_id' => '999-2027-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]))->assertCreated();
        }

        // الترقيم: صفحة 1 بحجم 10 من 15 (الأسرة الافتراضية من setUp غير موجودة في هذه القاعدة)
        $res = $this->getJson('/api/families?per_page=10&page=1')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertSame(10, count($res->json('data')));
        $this->assertSame(10, $res->json('meta.per_page'));
        $this->assertSame(2, $res->json('meta.last_page'));

        $page1Ids = collect($res->json('data'))->pluck('id')->all();

        // صفحة 2 فيها الـ 5 الباقين وبدون تكرار
        $page2 = $this->getJson('/api/families?per_page=10&page=2')->assertOk();
        $this->assertSame(5, count($page2->json('data')));
        $this->assertCount(0, array_intersect($page1Ids, collect($page2->json('data'))->pluck('id')->all()));

        // فلتر مستوى الضعف على السيرفر: رئاسة أنثى وحدها = low؛ سجل حالة high
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2027-9990',
            'members_count' => 10,
            'adults_count' => 2,
            'children_count' => 8,
            'pwd_count' => 2,
            'has_pwd' => true,
            'is_female_headed' => true,
            'fhh_reason' => 'widow',
        ]))->assertCreated();

        $high = $this->getJson('/api/families?per_page=100&vulnerability_level=high')->assertOk();
        $this->assertGreaterThanOrEqual(1, count($high->json('data')));
        foreach ($high->json('data') as $item) {
            $this->assertSame('high', $item['level']);
        }

        // بحث نصي بالاسم على السيرفر
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2027-9991',
            'head_name' => 'شخص مميز بالبحث',
        ]))->assertCreated();

        $search = $this->getJson('/api/families?per_page=50&search='.urlencode('مميز'))
            ->assertOk();
        $this->assertSame(1, count($search->json('data')));
        $this->assertSame('شخص مميز بالبحث', $search->json('data.0.name'));

        // ترتيب بالاسم تصاعدي
        $sorted = $this->getJson('/api/families?per_page=200&sort_by=head_name&sort_dir=asc')->assertOk();
        $names = collect($sorted->json('data'))->pluck('name')->all();
        $sortedNames = $names;
        sort($names, SORT_LOCALE_STRING);
        $this->assertSame(array_values($names), array_values($sortedNames));
    }

    public function test_families_index_default_compatibility_returns_all_rows(): void
    {
        Sanctum::actingAs($this->dataEntry);

        for ($i = 1; $i <= 7; $i++) {
            $this->postJson('/api/families', $this->familyPayload([
                'national_id' => '999-2028-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]))->assertCreated();
        }

        // بدون per_page — الوضع القديم: كل الصفوف بدون meta (توافق مع الواجهات الأخرى)
        $res = $this->getJson('/api/families')->assertOk();
        $this->assertSame(7, count($res->json('data')));
        $this->assertNull($res->json('meta'));
    }

    public function test_dashboard_metrics_are_cached_and_invalidated_on_writes(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9700',
        ]))->assertCreated();

        $total = Family::count();

        // أول طلب يحسب ويكاشي، والتاني مباشرة يقرأ من الكاش (نفس generated_at)
        $first = $this->getJson('/api/dashboard')->assertOk()->json('data');
        $this->assertSame($total, $first['cards']['total_families']['count']);

        $second = $this->getJson('/api/dashboard')->assertOk()->json('data');
        $this->assertSame($first['generated_at'], $second['generated_at'], 'الطلب الثاني يجب أن يقرأ من الكاش');

        // كتابة جديدة → Observer يبطل الكاش → الطلب القادم يحسب القيمة الجديدة
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9701',
        ]))->assertCreated();

        $fresh = $this->getJson('/api/dashboard')->assertOk()->json('data');
        $this->assertSame($total + 1, $fresh['cards']['total_families']['count'], 'بعد الكتابة يجب إعادة الحساب بقيمة جديدة');
    }

    public function test_audit_log_records_family_and_member_changes(): void
    {
        Sanctum::actingAs($this->dataEntry);

        // إنشاء أسرة يوثّق
        $family = $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9600',
        ]))->assertCreated()->json('data');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Family::class,
            'auditable_id' => $family['id'],
            'action' => 'created',
            'user_id' => $this->dataEntry->id,
        ]);

        // تعديل الأسرة يوثّق القيم القديمة والجديدة
        $this->putJson('/api/families/'.$family['id'], [
            'head_name' => 'الاسم المعدّل',
            'phone' => '059-111-2222',
        ])->assertOk();

        $log = AuditLog::where('auditable_type', Family::class)
            ->where('auditable_id', $family['id'])
            ->where('action', 'updated')
            ->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('QA Family', $log->changes['old']['head_name']);
        $this->assertSame('الاسم المعدّل', $log->changes['new']['head_name']);

        // إضافة عضو يوثّق على family_member
        $this->postJson("/api/families/{$family['id']}/members", [
            'name' => 'عضو موثّق',
            'gender' => 'male',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => FamilyMember::class,
            'action' => 'created',
            'user_id' => $this->dataEntry->id,
        ]);

        // حذف عضو يوثّق
        $member = FamilyMember::where('family_id', $family['id'])->latest('id')->first();
        $this->deleteJson('/api/members/'.$member->id)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => FamilyMember::class,
            'auditable_id' => $member->id,
            'action' => 'deleted',
        ]);

        // endpoint السجل يرجع التغييرات بترتيب تنازلي ويلتزم صلاحية المخيم
        $res = $this->getJson('/api/families/'.$family['id'].'/audit-logs')
            ->assertOk()
            ->assertJsonPath('status', true);
        $this->assertNotEmpty($res->json('data'));

        Sanctum::actingAs($this->manager);
        $this->getJson('/api/families/'.$family['id'].'/audit-logs')->assertOk();

        // موظف إدخال من مخيم آخر مرفوض — أنشئ مستخدم بمخيم مختلف
        $otherCamp = Camp::create([
            'name' => 'مخيم اختبار التدقيق',
            'location' => 'غزة',
            'capacity' => 100,
            'current_population' => 0,
            'is_active' => true,
        ]);
        $outsider = User::create([
            'name' => 'موظف مخيم آخر',
            'email' => 'outside@gpd.com',
            'password' => 'secret-123',
            'role' => 'data_entry',
            'camp_id' => $otherCamp->id,
            'is_active' => true,
        ]);
        Sanctum::actingAs($outsider);
        $this->getJson('/api/families/'.$family['id'].'/audit-logs')->assertForbidden();
    }

    public function test_fhh_reason_is_required_when_female_headed(): void
    {
        Sanctum::actingAs($this->dataEntry);

        // رئاسة أنثى بدون سبب → مرفوض
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9601',
            'is_female_headed' => true,
            'fhh_reason' => null,
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('fhh_reason');

        // رئاسة أنثى مع سبب خارج القائمة → مرفوض
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9602',
            'is_female_headed' => true,
            'fhh_reason' => 'سبب غير معروف',
        ]))->assertUnprocessable();

        // رئاسة أنثى مع سبب صحيح → مقبول
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9603',
            'is_female_headed' => true,
            'fhh_reason' => 'widow',
        ]))->assertCreated();

        // بدون رئاسة أنثى، السبب اختياري (null مسموح)
        $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9604',
            'is_female_headed' => false,
            'fhh_reason' => null,
        ]))->assertCreated();
    }

    public function test_national_id_editing_is_manager_only(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $family = $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9605',
        ]))->assertCreated()->json('data');

        // موظف الإدخال ممنوع من تعديل الرقم القومي
        $this->putJson('/api/families/'.$family['id'], [
            'head_name' => 'QA Family',
            'phone' => '059-000-0000',
            'national_id' => '999-2026-9999',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('national_id');

        // المدير يقدر يعدّله لقيمة غير مستخدمة
        Sanctum::actingAs($this->manager);
        $this->putJson('/api/families/'.$family['id'], [
            'head_name' => 'QA Family',
            'phone' => '059-000-0000',
            'national_id' => '999-2026-9610',
        ])->assertOk();
        $this->assertDatabaseHas('families', [
            'id' => $family['id'],
            'national_id' => '999-2026-9610',
        ]);

        // المدير لا يستطيع استخدام رقم مستخدم من أسرة أخرى — أنشئ أسرة ثانية أولاً
        $this->postJson('/api/families', $this->familyPayload())
            ->assertCreated(); // national_id الافتراضي: 999-2026-1000

        $this->putJson('/api/families/'.$family['id'], [
            'head_name' => 'QA Family',
            'phone' => '059-000-0000',
            'national_id' => '999-2026-1000',
        ])->assertUnprocessable();
    }

    public function test_vulnerability_preview_endpoint_uses_server_computation(): void
    {
        Sanctum::actingAs($this->dataEntry);

        // نفس حالة اختبار المعادلة الموحدة: 20+30+15+15+10 = 90 عالٍ
        $this->postJson('/api/families/preview-vulnerability', [
            'members_count' => 10,
            'adults_count' => 3,
            'children_count' => 7,
            'pwd_count' => 2,
            'is_female_headed' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.score', 90)
            ->assertJsonPath('data.level', 'high')
            ->assertJsonPath('data.factors.pwd', 30)
            ->assertJsonStructure([
                'status',
                'data' => ['score', 'level', 'factors'],
            ]);

        // حالة بسيطة: رئاسة أنثى فقط = 20 منخفض
        $this->postJson('/api/families/preview-vulnerability', [
            'members_count' => 1,
            'adults_count' => 1,
            'children_count' => 0,
            'pwd_count' => 0,
            'is_female_headed' => true,
        ])->assertOk()->assertJsonPath('data.level', 'low')->assertJsonPath('data.score', 20);

        // فالديشن: قيم ناقصة أو غير منطقية ترفض
        $this->postJson('/api/families/preview-vulnerability', [
            'members_count' => 0,
        ])->assertUnprocessable();
    }

    public function test_vulnerability_preview_requires_authentication(): void
    {
        $this->postJson('/api/families/preview-vulnerability', [])
            ->assertUnauthorized();
    }

    public function test_recalculate_vulnerability_command_recomputes_stale_scores(): void
    {
        Sanctum::actingAs($this->dataEntry);

        $family = $this->postJson('/api/families', $this->familyPayload([
            'national_id' => '999-2026-9500',
            'is_female_headed' => true,
            'fhh_reason' => 'widow',
        ]))->assertCreated()->json('data');

        // محاكاة بيانات قديمة محفوظة بمعادلة قديمة (سلم 0-12)
        Family::where('id', $family['id'])->update([
            'vulnerability_score' => 3,
            'vulnerability_level' => 'medium',
        ]);

        // dry-run: لازم يبلغ عن تغيير بدون ما يحفظ
        $this->artisan('families:recalculate-vulnerability', ['--dry-run' => true])
            ->assertSuccessful();
        $this->assertDatabaseHas('families', [
            'id' => $family['id'],
            'vulnerability_score' => 3,
            'vulnerability_level' => 'medium',
        ]);

        // التشغيل الفعلي: يعيد الحساب بالمعادلة الجديدة (رئاسة أنثى = 20 → منخفض)
        $this->artisan('families:recalculate-vulnerability')->assertSuccessful();

        $this->assertDatabaseHas('families', [
            'id' => $family['id'],
            'vulnerability_score' => 20,
            'vulnerability_level' => 'low',
        ]);

        // --camp لمخيم غير موجود يفشل بأمان
        $this->artisan('families:recalculate-vulnerability', ['--camp' => 99999])
            ->assertFailed();
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
            'camp_id' => $this->sourceCamp->id,
        ], $overrides);
    }
}
