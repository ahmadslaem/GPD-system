<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\TransferRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferRequestController extends Controller
{


public function store(Request $request)
{


$data=$request->validate([


'family_id'=>'required|exists:families,id',

'from_camp_id'=>'required|exists:camps,id',

'to_camp_id'=>'required|exists:camps,id',

'reason'=>'required|string'


]);

return DB::transaction(function () use ($request, $data) {
    $family = Family::whereKey($data['family_id'])->lockForUpdate()->firstOrFail();

    if ($family->camp_id !== (int) $data['from_camp_id']) {
        return response()->json([
            'message' => 'The selected family does not belong to the source camp'
        ], 422);
    }

    if ($request->user()->role === 'data_entry' && $family->camp_id !== $request->user()->camp_id) {
        return response()->json([
            'message' => 'Forbidden'
        ], 403);
    }

    if ((int) $data['from_camp_id'] === (int) $data['to_camp_id']) {
        return response()->json([
            'message' => 'Target camp must be different from source camp'
        ], 422);
    }

    $duplicatePending = TransferRequest::where('family_id', $data['family_id'])
        ->where('from_camp_id', $data['from_camp_id'])
        ->where('to_camp_id', $data['to_camp_id'])
        ->where('status', 'pending')
        ->exists();

    if ($duplicatePending) {
        return response()->json([
            'message' => 'يوجد طلب نقل معلق لهذه الأسرة إلى نفس المخيم'
        ], 409);
    }

    $data['reason'] = strip_tags($data['reason']);

    $data['requested_by']=auth()->id();



    $transfer=TransferRequest::create($data);



    return response()->json([

    'message'=>'Transfer request created',

    'data'=>$transfer

    ],201);
});



}
/*
|--------------------------------------------------------------------------
| Index: قائمة الطلبات + الكروت العلوية (الإجمالي / مرفوضة / موافق عليها / معلقة)
|--------------------------------------------------------------------------
*/
public function index(Request $request)
{
 
    $summaryQuery = TransferRequest::query();

    $query = TransferRequest::with([
        'family',
        'fromCamp',
        'toCamp',
        'requester',
    ]);

    if ($request->user()->role === 'data_entry') {
        $summaryQuery->where('requested_by', $request->user()->id);
        $query->where('requested_by', $request->user()->id);
    }
 
 
    // فلترة حسب الحالة (تبويبات: الكل / معلق / موافق / مرفوض)
    if ($request->filled('status') && $request->status !== 'all') {
 
        $query->where('status', $request->status);
 
    }
 
 
    // البحث باسم رب الأسرة أو رقم الطلب
    if ($request->filled('keyword')) {
 
        $keyword = $request->keyword;
 
        $numericId = ltrim(str_ireplace('T-', '', $keyword), '0');
 
 
        $query->where(function ($q) use ($keyword, $numericId) {
 
            $q->whereHas('family', function ($fq) use ($keyword) {
 
                $fq->where('head_name', 'like', '%' . $keyword . '%');
 
            });
 
 
            if (is_numeric($numericId) && $numericId !== '') {
 
                $q->orWhere('id', $numericId);
 
            }
 
        });
 
    }
 
 
    $requests = $query->latest()->get();
 
 
    /*
    |--------------------------------------------------------------------------
    | الكروت العلوية (تُحسب على كامل الجدول، بدون تأثير فلاتر البحث/الحالة)
    |--------------------------------------------------------------------------
    */
    $summary = [
 
        'total' => (clone $summaryQuery)->count(),
 
        'approved' => (clone $summaryQuery)->where('status', 'approved')->count(),
 
        'rejected' => (clone $summaryQuery)->where('status', 'rejected')->count(),
 
        'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
 
    ];
 
 
    /*
    |--------------------------------------------------------------------------
    | تنسيق قائمة الطلبات
    |--------------------------------------------------------------------------
    */
    $data = $requests->map(function ($transfer) {
 
        return [
 
            'id' => $transfer->id,
 
            'request_number' => 'T-' . str_pad($transfer->id, 3, '0', STR_PAD_LEFT),
 
            'status' => $transfer->status,
 
            'head_name' => $transfer->family->head_name ?? null,

            'national_id' => $transfer->family->national_id ?? null,

            'vulnerability_level' => $transfer->family->vulnerability_level ?? null,

            'family' => $transfer->family ? [
                'id' => $transfer->family->id,
                'national_id' => $transfer->family->national_id,
                'head_name' => $transfer->family->head_name,
                'camp_id' => $transfer->family->camp_id,
                'vulnerability_level' => $transfer->family->vulnerability_level,
            ] : null,
 
            'from_camp' => $transfer->fromCamp->name ?? null,
 
            'to_camp' => $transfer->toCamp->name ?? null,
 
            'requested_by' => $transfer->requester->name ?? null,
 
            'reason' => $transfer->reason,
 
            'manager_note' => $transfer->manager_note,
 
            'created_at' => optional($transfer->created_at)->format('Y-m-d'),
 
        ];
 
    });
 
 
    return response()->json([
 
        'status' => true,
 
        'summary' => $summary,
 
        'data' => $data,
 
    ]);
 
}




public function approve(Request $request, $id)
{
    $transfer = TransferRequest::findOrFail($id);


    // التأكد أن الطلب ما زال pending
    if ($transfer->status !== 'pending') {

        return response()->json([
            'message' => 'This transfer request has already been reviewed'
        ], 400);

    }


    DB::transaction(function () use ($transfer, $request) {

        $family = $transfer->family;
        $membersCount = $family->members_count ?? 0;

        if ($transfer->fromCamp) {
            $transfer->fromCamp->update([
                'current_population' => max(0, $transfer->fromCamp->current_population - $membersCount),
            ]);
        }

        if ($transfer->toCamp) {
            $transfer->toCamp->increment('current_population', $membersCount);
        }


        // تحديث مكان العائلة
        $family->update([
            'camp_id' => $transfer->to_camp_id
        ]);


        // تحديث حالة الطلب
        $transfer->update([

            'status' => 'approved',

            'manager_note' => $request->manager_note ? strip_tags($request->manager_note) : null,

            'reviewed_by' => auth()->id()

        ]);


    });


    return response()->json([

        'message' => 'Transfer approved and family moved successfully'

    ]);

}




public function reject(Request $request, $id)
{

    $transfer = TransferRequest::findOrFail($id);


    // التأكد أن الطلب pending
    if ($transfer->status !== 'pending') {

        return response()->json([
            'message' => 'This transfer request has already been reviewed'
        ], 400);

    }


    $transfer->update([

        'status' => 'rejected',

        'manager_note' => $request->manager_note ? strip_tags($request->manager_note) : null,

        'reviewed_by' => auth()->id()

    ]);


    return response()->json([

        'message' => 'Transfer rejected'

    ]);

}



}
