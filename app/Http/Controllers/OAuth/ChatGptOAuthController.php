<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\WorkoutMemory\ChatGptOAuthServer;
use App\Services\WorkoutMemory\CurrentUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ChatGptOAuthController extends Controller
{
    public function protectedResource(ChatGptOAuthServer $oauth, ?string $path = null): JsonResponse
    {
        return response()->json($oauth->protectedResourceMetadata($path));
    }

    public function authorizationServer(ChatGptOAuthServer $oauth): JsonResponse
    {
        return response()->json($oauth->authorizationServerMetadata());
    }

    public function register(Request $request, ChatGptOAuthServer $oauth): JsonResponse
    {
        try {
            return response()->json($oauth->registerClient($request->all()), 201);
        } catch (InvalidArgumentException $exception) {
            return $this->oauthError('invalid_request', $exception->getMessage());
        }
    }

    public function authorize(Request $request, ChatGptOAuthServer $oauth, CurrentUserResolver $users): RedirectResponse|JsonResponse
    {
        try {
            $authorization = $oauth->validateAuthorizationRequest($request->query());
        } catch (InvalidArgumentException $exception) {
            return $this->oauthError('invalid_request', $exception->getMessage());
        }

        $code = $oauth->issueAuthorizationCode($authorization, $users->user());

        return redirect()->away($oauth->redirectUriWithCode(
            (string) $authorization['redirect_uri'],
            $code,
            $authorization['state'],
        ));
    }

    public function token(Request $request, ChatGptOAuthServer $oauth): JsonResponse
    {
        try {
            return response()->json($oauth->redeemAuthorizationCode($request->all()));
        } catch (InvalidArgumentException $exception) {
            return $this->oauthError('invalid_grant', $exception->getMessage());
        }
    }

    private function oauthError(string $error, string $description, int $status = 400): JsonResponse
    {
        return response()->json([
            'error' => $error,
            'error_description' => $description,
        ], $status);
    }
}
