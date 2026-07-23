<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Carbon\Carbon;
class AuthController extends Controller
{

    /**
     * Login User
     */
   public function login(Request $request)
{
    try {
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
        if(!$user->is_active){
            return response()->json([
                'message'=>'Account is disabled'
            ],403);
        }

        // Create Token
        $expiresAt = Carbon::now()->addHours(3);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'message'=>'Login successful',
            'user'=>[
                'id'=>$user->id,
                'name'=>$user->name,
                'email'=>$user->email,
                'role'=>$user->role,
                'role_label'=>$this->roleLabel($user->role),
                'camp_id'=>$user->camp_id,
                'camp'=>$user->camp?->name,
            ],
            'token'=>$token,
            'expires_at'=>$expiresAt->toDateTimeString(),
        ],200);

    } catch (\Throwable $e) {
        return response()->json([
            'debug_message' => $e->getMessage(),
            'debug_file' => $e->getFile(),
            'debug_line' => $e->getLine(),
        ], 500);
    }
}

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'مدير النظام',
            'manager' => 'مدير المنظمة',
            'data_entry' => 'موظف إدخال بيانات',
            default => $role,
        };
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
     * Update Current User Profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        foreach (['name', 'email', 'phone'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strip_tags($data[$field]);
            }
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Change Current User Password
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = $request->user();

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة'],
            ]);
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
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
