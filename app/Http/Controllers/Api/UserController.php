<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;


class UserController extends Controller
{


    // عرض كل المستخدمين
    public function index()
    {
        return response()->json([
            'users'=>User::all()
        ]);
    }



    // إنشاء مستخدم جديد
    public function store(Request $request)
    {


        $request->validate([

            'name'=>'required|string',
            'email'=>'required|email|unique:users',
            'password'=>'required|min:8',
            'role'=>'required|in:admin,manager,data_entry',
            'phone'=>'nullable|string',
            'camp_id'=>'nullable|exists:camps,id'

        ]);



        $user = User::create([

            'name'=>$request->name,

            'email'=>$request->email,

            'password'=>Hash::make($request->password),

            'role'=>$request->role,
            'phone'=>$request->phone,
            'camp_id'=>$request->camp_id,
            'is_active'=>true

        ]);
        



        return response()->json([

            'message'=>'User created successfully',

            'user'=>$user

        ],201);


    }




    // عرض مستخدم معين
    public function show($id)
    {

        $user=User::findOrFail($id);


        return response()->json($user);

    }





    // تعديل مستخدم
    public function update(Request $request,$id)
    {


        $user=User::findOrFail($id);



        $request->validate([

            'name'=>'sometimes|string',

            'email'=>'sometimes|email',

            'role'=>'sometimes|in:admin,manager,data_entry',
            'phone'=>'sometimes|string',
            'camp_id'=>'sometimes|exists:camps,id'

        ]);



        $user->update($request->all());



        return response()->json([

            'message'=>'User updated successfully',

            'user'=>$user

        ]);

    }





    // حذف مستخدم
    public function destroy($id)
    {

        $user=User::findOrFail($id);


        $user->delete();



        return response()->json([

            'message'=>'User deleted successfully'

        ]);

    }





    // تفعيل وتعطيل الحساب
    public function toggleStatus($id)
    {


        $user=User::findOrFail($id);



        $user->is_active = !$user->is_active;


        $user->save();



        return response()->json([

            'message'=>'User status changed',

            'is_active'=>$user->is_active

        ]);

    }

    public function selectCamp(Request $request)
{
    $validated = $request->validate([
        'camp_id' => 'required|integer|exists:camps,id',
    ]);

    $request->user()->update([
        'camp_id' => $validated['camp_id'],
    ]);

    return response()->json([
        'success' => true,
        'message' => 'تم تغيير المخيم بنجاح',
        'camp_id' => $validated['camp_id'],
    ]);
}


}