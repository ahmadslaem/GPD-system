<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;


class UserController extends Controller
{


    // عرض كل المستخدمين
    public function index()
    {
        return response()->json([
            'users'=>User::all()
        ]);
    }

    public function statistics()
    {
        return response()->json([
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'data_entry_users' => User::where('role', 'data_entry')->count(),
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

            'name'=>strip_tags($request->name),

            'email'=>strip_tags($request->email),

            'password'=>Hash::make($request->password),

            'role'=>$request->role,
            'phone'=>$request->phone ? strip_tags($request->phone) : null,
            'camp_id'=>$request->camp_id,
            'is_active'=>true

        ]);

        $user->syncRoles([$request->role]);
        



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

            'email'=>'sometimes|email|unique:users,email,' . $user->id,

            'role'=>'sometimes|in:admin,manager,data_entry',
            'phone'=>'sometimes|string',
            'camp_id'=>'sometimes|exists:camps,id'

        ]);



        $data = $request->only([
            'name',
            'email',
            'role',
            'phone',
            'camp_id',
        ]);

        foreach (['name', 'email', 'phone'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strip_tags($data[$field]);
            }
        }

        $user->update($data);

        if ($request->filled('role')) {
            $user->syncRoles([$request->role]);
        }



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
        'camp_id' => [
            'required',
            'integer',
            Rule::exists('camps', 'id')->where('is_active', true),
        ],
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
