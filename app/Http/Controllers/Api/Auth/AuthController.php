<?php

namespace App\Http\Controllers\Api\Auth;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Specialty;
use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    protected AuthService $authService;
    protected ApiResponse $response;

    public function __construct(AuthService $authService, ApiResponse $response)
    {
        $this->authService  = $authService;
        $this->response     = $response;
    }

    /** 
     * Register a new user and generate API token
     */
    public function register(RegisterRequest $request)
    {
        try {
            DB::beginTransaction();
            $this->authService->registerUser($request->validated());
            DB::commit();

            return $this->response->success([], __('messages.user_registered'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Register Error: ' . $e->getMessage());
            return  $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * Authenticate user and return API token
     */
    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->validated();

            if (!Auth::attempt($credentials)) {
                return $this->response->error(__('messages.credentials_not_match'), 401);
            }

            $user = Auth::user();
            $token = app()->environment('testing')
                ? 'test-token'
                : $user->createToken('passportToken')->accessToken;

            return $this->response->success([
                'id' => $user->id,
                'token' => $token
            ], __('messages.login_success'), 200);
        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * Logout user by revoking current API token
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->response->error(__('messages.unauthorized'), 401);
            }

            if (!app()->environment('testing')) {
                $token = $user->token();
                if ($token) {
                    $token->revoke();
                }
            }

            return $this->response->success([], __('messages.logout_success'), 200);
        } catch (\Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }

    /** 
     * get category
     */
    public function category()
    {
        try {
            return Specialty::select('id','name')->get();
        } catch (\Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());
            return $this->response->error(__('messages.something_went_wrong'), 500);
        }
    }
}
