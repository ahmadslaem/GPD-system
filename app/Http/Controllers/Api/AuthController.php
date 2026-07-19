<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Carbon\Carbon;
class AuthController extends Controller
{

    /**
     * Login User
     */
    public function login(Request $request)
    {
        // Validate input
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);
 
 
        // Find user
        $user = User::where('email', $request->email)->first();
 
 
        // Check user and password
        if (!$user || !Hash::check($request->password, $user->password)) {
 
            return response()->json([
                'message' => 'Invalid email or password'
            ], 401);
 
        }
 
 
        // Check account status
        if(isset($user->status) && $user->status == 'inactive'){
 
            return response()->json([
                'message'=>'Account is disabled'
            ],403);
 
        }
 
 
        // Create Token - تنتهي صلاحيته بعد 3 ساعات من تسجيل الدخول
        $expiresAt = Carbon::now()->addHours(3);
 
        $token = $user->createToken(
            'auth_token',
            ['*'],
            $expiresAt
        )->plainTextToken;
 
 
        return response()->json([
 
            'message'=>'Login successful',
 
            'user'=>[
                'id'=>$user->id,
                'name'=>$user->name,
                'email'=>$user->email,
                'role'=>$user->role
            ],
 
            'token'=>$token,
 
            'expires_at'=>$expiresAt->toDateTimeString(),
 
        ],200);
 
    }



    /**
     * Get Current User Profile
     */
    public function profile(Request $request)
    {

        return response()->json([

            'user'=>$request->user()

        ],200);

    }



    /**
     * Logout User
     */
    public function logout(Request $request)
    {

        // Delete current token
        $request->user()->currentAccessToken()->delete();


        return response()->json([

            'message'=>'Logout successful'

        ]);

    }

}