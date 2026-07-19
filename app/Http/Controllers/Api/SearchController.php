<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Family;
use Illuminate\Http\Request;

class SearchController extends Controller
{


    /*
Local Search
البحث داخل مخيم المستخدم فقط
*/

public function local(Request $request)
{

    $request->validate([
        'keyword'=>'required|string'
    ]);


    $campId = auth()->user()->camp_id;

    $keyword = $request->keyword;

    // لو المستخدم كتب رقم الأسرة بصيغة F-00004 أو حتى بدون F-
    $numericId = ltrim(str_ireplace('F-', '', $keyword), '0');


    $families = Family::with('members')
        ->where('camp_id',$campId)
        ->where(function($query) use ($keyword, $numericId){

            $query->where(
                'national_id',
                'like',
                '%'.$keyword.'%'
            )

            ->orWhere(
                'head_name',
                'like',
                '%'.$keyword.'%'
            );

            if(is_numeric($numericId) && $numericId !== ''){

                $query->orWhere('id', $numericId);

            }

        })
        ->get();



    return response()->json([

        'status'=>true,

        'data'=>$families

    ]);

}





/*
Global Search
للمدير فقط
*/

public function global(Request $request)
{

    $request->validate([
        'keyword'=>'required|string'
    ]);


    $keyword = $request->keyword;

    $numericId = ltrim(str_ireplace('F-', '', $keyword), '0');


    $families = Family::with([
        'members',
        'camp'
    ])

    ->where(function($query) use ($keyword, $numericId){


        $query->where(
            'national_id',
            'like',
            '%'.$keyword.'%'
        )


        ->orWhere(
            'head_name',
            'like',
            '%'.$keyword.'%'
        );


        if(is_numeric($numericId) && $numericId !== ''){

            $query->orWhere('id', $numericId);

        }


    })

    ->get();



    return response()->json([

        'status'=>true,

        'data'=>$families

    ]);

}


}