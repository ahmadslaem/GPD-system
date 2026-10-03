<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FamilyResource;
use App\Models\Camp;
use App\Models\Family;
use App\Models\AuditLog;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FamilyController extends Controller
{
    

    /**
     * سجل تغييرات الأسرة وأفرادها (UC-09: Data History).
     */
    public function auditLogs(Request $request, Family $family)
    {
        $this->authorize('view', $family);

        $memberIds = $family->members()->pluck('id');

        $logs = AuditLog::with('user:id,name,role')
            ->where(function ($query) use ($family) {
                $query->where('auditable_type', $family->getMorphClass())
                      ->where('auditable_id', $family->id);
            })
            ->orWhere(function ($query) use ($memberIds) {
                $query->where('auditable_type', (new FamilyMember)->getMorphClass())
                      ->whereIn('auditable_id', $memberIds);
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $logs->map(fn (AuditLog $log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'user'       => $log->user?->name,
                'role'       => $log->user?->role,
                'target'     => $log->auditable_type === FamilyMember::class ? 'member' : 'family',
                'target_id'  => $log->auditable_id,
                'changes'    => $log->changes,
                'ip'         => $log->ip_address,
                'at'         => $log->created_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    /**
     * معاينة درجة الضعف بدون حفظ — المصدر الوحيد للحساب في الواجهة
     * (يستخدم computeVulnerability() نفسها حتى لا تتكرر المعادلة في الـ JS).
     */
    public function previewVulnerability(Request $request)
    {
        $data = $request->validate([
            'members_count'    => 'required|integer|min:1|max:99',
            'adults_count'     => 'required|integer|min:0|max:99',
            'children_count'   => 'required|integer|min:0|max:99',
            'pwd_count'        => 'required|integer|min:0|max:99',
            'is_female_headed' => 'required|boolean',
        ]);

        $preview = new Family($data);
        $result  = $preview->computeVulnerability();

        return response()->json([
            'status' => true,
            'data'   => $result,
        ]);
    }

    public function checkNationalId($national_id)
    {
        $family = Family::where('national_id', $national_id)->first();

    if($family){

        if($family->camp_id == auth()->user()->camp_id){

            return response()->json([
                'message'=>'Registered in current camp'
            ], 200);

        } else {

            return response()->json([
                'message'=>'Registered in another camp',
                'camp'=>$family->camp->name
            ], 200);

        }

    }

    return response()->json([
        'message'=>'Not Registered'
    ], 404);

       

    }


 public function store(Request $request)
   {         $this->authorize('create', Family::class);

    $validated = $request->validate([

        'national_id'=>'required|string|max:20',

        'head_name'=>'required|string|max:255',

        'phone'=>'required|string|max:20',

        'birth_date'=>'nullable|date',

        'camp_id'=>[
            'required',
            'integer',
            Rule::exists('camps', 'id')->where('is_active', true),
        ],

        'members'=>'nullable|array',

        'members.*.name'=>'required|string|max:255',

        'members.*.gender'=>'required|in:male,female',

        'members_count'=>'required|integer|min:1',

        'adults_count'=>'nullable|integer|min:0',

        'children_count'=>'nullable|integer|min:0',

        'pwd_count'=>'nullable|integer|min:0',

        'is_female_headed'=>'nullable|boolean',

        // FR-DE: لو الأسرة برئاسة أنثى، السبب إلزامي من القائمة المعرفة
        'fhh_reason'=>[
            'nullable',
            'string',
            Rule::requiredIf(fn () => $request->boolean('is_female_headed')),
            'in:widow,divorced,husband_absent,other',
        ],

        'has_pwd'=>'nullable|boolean',

        'original_governorate'=>'required|string|max:255',

        'original_city'=>'required|string|max:255',

        'shelter_number'=>'nullable|string|max:255',

    ]);

    $user = auth()->user();
    $campId = (int) $validated['camp_id'];

    if ($user->role === 'data_entry' && !$user->camp_id) {
        return response()->json([
            'status'=>false,
            'message'=>'A camp must be selected before registering families'
        ],422);
    }

    if ($user->role === 'data_entry' && (int) $user->camp_id !== $campId) {
        return response()->json([
            'status'=>false,
            'message'=>'Selected camp must match the current user camp'
        ],422);
    }

    $nationalId = strip_tags($request->national_id);


    $exists = Family::where(
        'national_id',
        $nationalId
    )->exists();



    if($exists)
    {
        return response()->json([

            'status'=>false,

            'message'=>'Family already registered'

        ],409);
    }



    DB::beginTransaction();


    try{


        $family = Family::create([


            'national_id'=>$nationalId,

            'head_name'=>strip_tags($request->head_name),

            'phone'=>strip_tags($request->phone),

            'birth_date'=>$request->birth_date,


            'created_by'=>auth()->id(),


            'original_governorate'=>strip_tags($request->original_governorate),


            'original_city'=>strip_tags($request->original_city),


            'camp_id'=>$campId,


            'shelter_number'=>strip_tags($request->shelter_number),


            'members_count'=>$request->integer('members_count'),


            'adults_count'=>$request->integer('adults_count'),


            'children_count'=>$request->integer('children_count'),


            'pwd_count'=>$request->integer('pwd_count'),

            'has_pwd'=>($request->pwd_count ?? 0) > 0 || $request->boolean('has_pwd'),


            'is_female_headed'=>$request->boolean('is_female_headed'),


            'fhh_reason'=>$request->fhh_reason ? strip_tags($request->fhh_reason) : null,

            'pwd_type'=>$request->pwd_type ? strip_tags($request->pwd_type) : null,

            'pwd_cause'=>$request->pwd_cause ? strip_tags($request->pwd_cause) : null,

        ]);
        




        foreach($request->members ?? [] as $member)
        {


            $family->members()->create([

                'name'=>strip_tags($member['name']),

                'national_id'=>isset($member['national_id']) ? strip_tags($member['national_id']) : null,

                'birth_date'=>$member['birth_date'] ?? null,

                'gender'=>$member['gender'],

                'has_disability'=>$member['has_disability'] ?? false

            ]);
        
        }
    $family->calculateVulnerability();
    // Update camp population
    $camp = Camp::find($campId);

    $camp->increment(
    'current_population',
    $family->members_count
    );

        DB::commit();



        return response()->json([

            'status'=>true,

            'message'=>'Family registered successfully',

            'data'=>$family->load('members')

        ],201);



       }
        catch(\Exception $e)
       {


        DB::rollBack();


        return response()->json([

            'status'=>false,

            'message'=>'Unable to register family'

        ],500);

       }


}

//index method to list all families with their members, filtered by the user's role and camp if applicable
public function index(Request $request)
{

    $this->authorize('viewAny', Family::class);


    $user = auth()->user();


    $query = Family::query();

    if($user->role === 'data_entry'){

        $query->where('camp_id', $user->camp_id);

    }

    // فلاتر السيرفر (NFR-01/06): تُنفّذ على الداتابيز مع الفهارس
    if ($request->filled('camp_id')) {
        $query->where('camp_id', (int) $request->camp_id);
    }

    if ($request->filled('vulnerability_level')) {
        $query->where('vulnerability_level', $request->vulnerability_level);
    }

    if ($request->filled('search')) {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $request->string('search')->trim());
        $query->where(function ($q) use ($escaped, $request) {
            $q->where('head_name', 'like', '%' . $escaped . '%')
              ->orWhere('phone', 'like', '%' . $escaped . '%');

            $numericId = ltrim(str_ireplace('F-', '', $request->string('search')->trim()), '0');
            if (is_numeric($numericId) && $numericId !== '') {
                $q->orWhere('id', (int) $numericId);
            }
        });
    }

    // ترتيب آمن من قائمة بيضاء (الافتراضي: الأحدث أولاً)
    $sortMap = [
        'head_name' => 'head_name',
        'phone' => 'phone',
        'members_count' => 'members_count',
        'vulnerability_score' => 'vulnerability_score',
        'created_at' => 'created_at',
    ];
    $sortCol = $sortMap[$request->query('sort_by')] ?? 'created_at';
    $sortDir = strtolower($request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    $query->orderBy($sortCol, $sortDir);

    // الترقيم الحقيقي: N+1 يُحل بـ eager loading، والصف تُحسب في الداتابيز
    $families = $query->with('members')->paginate(
        perPage: max(1, min((int) $request->query('per_page', 50), 200)),
        page: max(1, (int) $request->query('page', 1))
    );

   return $this->paginatedJson(
       $request,
       $families,
       fn ($family) => (new FamilyResource($family))->resolve(),
       ['status' => true]
   );

}

//show method to display a specific family with its members
public function show($id)
{

    $family = Family::with('members')->find($id);


    if(!$family){

        return response()->json([
            'status'=>false,
            'message'=>'Family not found'
        ],404);

    }


    $this->authorize('view',$family);



    return response()->json([
        'status'=>true,
        'data' => new FamilyResource($family)
    ]);

}
//UPDATE FAMILY ONLY
public function update(Request $request, Family $family)
{
    $this->authorize('update', $family);

    $user = $request->user();
    $canEditNid = in_array($user->role, ['admin', 'manager']); // FR-DD-04

    $request->validate([
        'head_name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'birth_date' => 'nullable|date',

        // الرقم القومي مقفل على موظف الإدخال، ومتاح للمدير والأدمن فقط (FR-DD-04)
        'national_id' => $canEditNid
            ? ['sometimes', 'string', 'max:20', Rule::unique('families', 'national_id')->ignore($family->id)]
            : ['prohibited'],
    ]);


    $family->update([
        'head_name'  => strip_tags($request->head_name),
        'phone'      => strip_tags($request->phone),
        'birth_date' => $request->birth_date,
        ...($request->has('national_id') ? ['national_id' => strip_tags($request->national_id)] : []),
    ]);


    $family->calculateVulnerability();


    return response()->json([
        'status'=>true,
        'message'=>'Family updated successfully',
        'data'=>$family->load('members')
    ]);
}

// Add a new member to a family
public function addMember(Request $request, Family $family)
{

    $this->authorize('update',$family);


    $request->validate([
        'name'=>'required|string|max:255',
        'gender'=>'required|in:male,female',
        'birth_date'=>'nullable|date',
        'national_id'=>'nullable|string',
        'has_disability'=>'nullable|boolean'
    ]);


    $member = $family->members()->create([
        'name'=>strip_tags($request->name),
        'gender'=>$request->gender,
        'birth_date'=>$request->birth_date,
        'national_id'=>$request->national_id ? strip_tags($request->national_id) : null,
        'has_disability'=>$request->has_disability ?? false
    ]);

    // تصنيف العمر: العضو من غير تاريخ ميلاد بيتحسب بالغ افتراضياً — كل عضو جديد بيهبط على بالغين أو أطفال
    if ($this->isMemberAdult($member)) {
        $family->increment('adults_count');
    } else {
        $family->increment('children_count');
    }

    $family->increment('members_count');

    if ($member->has_disability) {
        $family->increment('pwd_count');
        $family->has_pwd = true;
        $family->save();
    }

    $family->camp?->increment('current_population');

    $family->refresh();


    $family->calculateVulnerability();


    return response()->json([
        'status'=>true,
        'message'=>'Member added successfully',
        'data'=>$member
    ]);

}

//update member details
public function updateMember(Request $request, $memberId)
{

    $member = FamilyMember::findOrFail($memberId);


    $this->authorize('update',$member->family);


    $request->validate([
        'name'=>'required|string|max:255',
        'gender'=>'required|in:male,female',
        'birth_date'=>'nullable|date',
        'has_disability'=>'nullable|boolean',
    ]);


    $family = $member->family;

    $wasAdult = $this->isMemberAdult($member);
    $wasDisabled = (bool) $member->has_disability;

    $attributes = [
        'name'=>strip_tags($request->name),
        'gender'=>$request->gender,
        'birth_date'=>$request->birth_date,
    ];

    if ($request->has('has_disability')) {
        $attributes['has_disability'] = $request->boolean('has_disability');
    }

    $member->update($attributes);

    // مزامنة العدادات لو تصنيف العمر اتغير (طفل بقى بالغ أو العكس)
    if ($this->isMemberAdult($member) !== $wasAdult) {
        if ($wasAdult) {
            $family->decrement('adults_count');
            $family->increment('children_count');
        } else {
            $family->decrement('children_count');
            $family->increment('adults_count');
        }
    }

    // مزامنة عداد الإعاقة لو حالة الإعاقة اتغيرت
    $hasDisability = (bool) $member->has_disability;
    if ($hasDisability !== $wasDisabled) {
        $family->pwd_count = max(0, (int) $family->pwd_count + ($hasDisability ? 1 : -1));
        $family->has_pwd = $family->pwd_count > 0;
        $family->save();
    }

    $family->refresh();

    $family->calculateVulnerability();


    return response()->json([
        'status'=>true,
        'message'=>'Member updated successfully',
        'data'=>$member
    ]);

}


// تصنيف العمر: بالغ (18 سنة أو أكثر) أو طفل — العضو من غير تاريخ ميلاد بيتحسب بالغ
private function isMemberAdult(FamilyMember $member): bool
{
    if (!$member->birth_date) {
        return true;
    }

    return now()->subYears(18)->greaterThanOrEqualTo($member->birth_date);
}

//delete member from family
public function deleteMember($memberId)
{
     $member = FamilyMember::find($memberId);

    if (!$member) {
        return response()->json([
            'status' => false,
            'message' => 'Member not found'
        ], 404);
    }

    $family = $member->family;
    $wasAdult = $this->isMemberAdult($member);
    $hadDisability = (bool) $member->has_disability;

    $this->authorize('update', $family);


    // حذف العضو
    $member->delete();

    $family->camp?->decrement('current_population');


    $family->members_count = max(0, $family->members_count - 1);

    if ($wasAdult) {
        $family->adults_count = max(0, $family->adults_count - 1);
    } else {
        $family->children_count = max(0, $family->children_count - 1);
    }

    if ($hadDisability) {
        $family->pwd_count = max(0, $family->pwd_count - 1);
    }


    // تحديث حالة الإعاقة
    $family->has_pwd = $family->pwd_count > 0;


    // إعادة حساب مستوى الضعف
    $family->calculateVulnerability();


    // حفظ التحديثات
    $family->save();


    return response()->json([
        'status'=>true,
        'message'=>'Member deleted and family statistics updated successfully'
    ]);
}


//DELETE FAMILY
public function destroy($id)
{
    $family = Family::with('members','camp')->find($id);

    if (!$family) {

        return response()->json([
            'status' => false,
            'message' => 'Family not found'
        ],404);

    }


    $this->authorize('delete',$family);


    DB::transaction(function () use ($family) {


        // المخيم المرتبطة به الأسرة
        $camp = $family->camp;


        // العدد الحقيقي للأفراد
        $membersCount = $family->members_count;


        // حذف أفراد الأسرة أولاً
        $family->members()->delete();


        // حذف الأسرة
        $family->delete();


        // تحديث تعداد المخيم
    if($camp){

            $camp->update([
                'current_population' => max(0, $camp->current_population - $membersCount),
            ]);

        }


    });


    return response()->json([
        'status'=>true,
        'message'=>'Family deleted successfully'
    ]);
}
}
