<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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



$data['requested_by']=auth()->id();



$transfer=TransferRequest::create($data);



return response()->json([

'message'=>'Transfer request created',

'data'=>$transfer

],201);



}





// public function index()
// {

// $requests=TransferRequest::with(
// [
// 'family',
// 'fromCamp',
// 'toCamp'
// ]
// )->latest()->get();



// return response()->json($requests);

// }
/*
|--------------------------------------------------------------------------
| Index: قائمة الطلبات + الكروت العلوية (الإجمالي / مرفوضة / موافق عليها / معلقة)
|--------------------------------------------------------------------------
*/
public function index(Request $request)
{
 
    $query = TransferRequest::with([
        'family',
        'fromCamp',
        'toCamp',
        'requester',
    ]);
 
 
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
 
        'total' => TransferRequest::count(),
 
        'approved' => TransferRequest::where('status', 'approved')->count(),
 
        'rejected' => TransferRequest::where('status', 'rejected')->count(),
 
        'pending' => TransferRequest::where('status', 'pending')->count(),
 
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


        // تحديث مكان العائلة
        $transfer->family->update([
            'camp_id' => $transfer->to_camp_id
        ]);


        // تحديث حالة الطلب
        $transfer->update([

            'status' => 'approved',

            'manager_note' => $request->manager_note,

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

        'manager_note' => $request->manager_note,

        'reviewed_by' => auth()->id()

    ]);


    return response()->json([

        'message' => 'Transfer rejected'

    ]);

}



}