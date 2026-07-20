<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyController extends Controller
{
    

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

    $request->validate([

        'national_id'=>'required|string|max:20',

        'head_name'=>'required|string|max:255',

        'phone'=>'required|string|max:20',

        'birth_date'=>'nullable|date',

        'members'=>'nullable|array',

        'members.*.name'=>'required|string|max:255',

        'members.*.gender'=>'required|in:male,female',

    ]);



    $exists = Family::where(
        'national_id',
        $request->national_id
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


            'national_id'=>strip_tags($request->national_id),

            'head_name'=>strip_tags($request->head_name),

            'phone'=>strip_tags($request->phone),

            'birth_date'=>$request->birth_date,


            'created_by'=>auth()->id(),


            'original_governorate'=>strip_tags($request->original_governorate),


            'original_city'=>strip_tags($request->original_city),


            'camp_id'=>auth()->user()->camp_id,


            'shelter_number'=>strip_tags($request->shelter_number),


            'members_count'=>$request->members_count,


            'adults_count'=>$request->adults_count,


            'children_count'=>$request->children_count,


            'pwd_count'=>$request->pwd_count,

            'has_pwd'=>$request->has_pwd ?? (($request->pwd_count ?? 0) > 0),


            'is_female_headed'=>$request->is_female_headed,


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
    $camp = $family->camp;

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

            'message'=>$e->getMessage()

        ],500);

       }


}

//index method to list all families with their members, filtered by the user's role and camp if applicable
public function index()
{

    $this->authorize('viewAny', Family::class);


    $user = auth()->user();


    if($user->role === 'data_entry'){

        $families = Family::where('camp_id',$user->camp_id)
            ->latest()
            ->get();

    }else{

        $families = Family::latest()->get();

    }



   return response()->json([
    'status' => true,
    'data' => FamilyResource::collection($families)
]);

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

    $request->validate([
        'head_name' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'birth_date' => 'nullable|date',

        // Prevent National ID editing
        'national_id' => 'prohibited',
    ]);


    $family->update([
        'head_name'  => strip_tags($request->head_name),
        'phone'      => strip_tags($request->phone),
        'birth_date' => $request->birth_date,
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

    $family->increment('members_count');

    if ($request->filled('birth_date')) {
        if (now()->subYears(18)->greaterThanOrEqualTo($request->date('birth_date'))) {
            $family->increment('adults_count');
        } else {
            $family->increment('children_count');
        }
    }

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
    ]);


    $member->update([
        'name'=>strip_tags($request->name),
        'gender'=>$request->gender,
        'birth_date'=>$request->birth_date,
    ]);


    $member->family->calculateVulnerability();


    return response()->json([
        'status'=>true,
        'message'=>'Member updated successfully',
        'data'=>$member
    ]);

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
    $wasAdult = $member->birth_date && now()->subYears(18)->greaterThanOrEqualTo($member->birth_date);
    $hadDisability = (bool) $member->has_disability;

    $this->authorize('update', $family);


    // حذف العضو
    $member->delete();

    $family->camp?->decrement('current_population');


    $family->members_count = max(0, $family->members_count - 1);

    if ($member->birth_date) {
        if ($wasAdult) {
            $family->adults_count = max(0, $family->adults_count - 1);
        } else {
            $family->children_count = max(0, $family->children_count - 1);
        }
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
