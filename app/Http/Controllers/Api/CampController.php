<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Camp;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;


class CampController extends Controller
{


    public function index(Request $request)
    {
        $query = Camp::query();

        if ($request->boolean('active')) {
            $query->where('is_active', true);
        }

        return response()->json(
            $query->withCount('families')->orderBy('id')->get()
        );
    }



    public function store(Request $request)
    {

        $data = $request->validate([

            'name'=>'required|string',

            'location'=>'required|string',

            'capacity'=>'nullable|integer',

            'is_active'=>'sometimes|boolean'

        ]);

        foreach (['name', 'location'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strip_tags($data[$field]);
            }
        }

        $camp = Camp::create($data);


        return response()->json([

            'message'=>'Camp created successfully',

            'camp'=>$camp

        ],201);

    }




    public function show(Camp $camp)
    {

        return response()->json($camp);

    }





    public function update(Request $request, Camp $camp)
    {

        $data = $request->validate([

            'name'=>'sometimes|string',

            'location'=>'sometimes|string',

            'capacity'=>'sometimes|integer',

            'is_active'=>'sometimes|boolean'

        ]);

        foreach (['name', 'location'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strip_tags($data[$field]);
            }
        }

        $camp->update($data);


        return response()->json([

            'message'=>'Camp updated',

            'camp'=>$camp

        ]);

    }





    public function destroy(Camp $camp)
    {

        try {
            $camp->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'لا يمكن حذف المخيم لأنه مرتبط بأسر مسجلة'
            ], 409);
        }


        return response()->json([

            'message'=>'Camp deleted'

        ]);

    }


}
