<?php

namespace App\Http\Controllers\API\v1;

use App\Enum\MastodonVisibility;
use App\Enum\StatusVisibility;
use App\Exceptions\RateLimitExceededException;
use App\Http\Controllers\Backend\SettingsController as BackendSettingsController;
use App\Http\Resources\UserProfileSettingsResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/settings/profile",
     *     tags={"Settings"},
     *     summary="Get the current user's profile settings",
     *     description="Get the current user's profile settings",
     *     @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\JsonContent(
     *              @OA\Property(property="data", type="object", ref="#/components/schemas/UserProfileSettings")
     *          )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     security={{"passport": {"read-settings"}}, {"token": {}}}
     * )
     *
     */
    public function getProfileSettings(): UserProfileSettingsResource {
        return new UserProfileSettingsResource(auth()->user());
    }

    /**
     * @throws ValidationException
     */
    public function updateMail(Request $request): UserProfileSettingsResource|JsonResponse {
        $validated = $request->validate(['email'    => ['required',
                                                        'string',
                                                        'email:rfc,dns',
                                                        'max:255',
                                                        'unique:users'],
                                         'password' => ['required', 'string']
                                        ]);
        if (!Hash::check($validated['password'], auth()->user()->password)) {
            throw ValidationException::withMessages([__('auth.password')]);
        }
        unset($validated['password']);

        try {
            return new UserProfileSettingsResource(BackendSettingsController::updateSettings($validated));
        } catch (RateLimitExceededException) {
            return $this->sendError(error: __('email.verification.too-many-requests'), code: 400);
        }
    }

    public function resendMail(): void {
        try {
            auth()->user()->sendEmailVerificationNotification();
            $this->sendResponse('', 204);
        } catch (RateLimitExceededException) {
            $this->sendError(error: __('email.verification.too-many-requests'), code: 429);
        }
    }

    /**
     * @throws ValidationException
     */
    public function updatePassword(Request $request): UserProfileSettingsResource|JsonResponse {
        $userHasPassword = auth()->user()->password !== null;

        $validated = $request->validate([
                                            'currentPassword' => [Rule::requiredIf($userHasPassword)],
                                            'password'        => ['required', 'string', 'min:8', 'confirmed']
                                        ]);

        if ($userHasPassword && !Hash::check($validated['currentPassword'], auth()->user()->password)) {
            throw ValidationException::withMessages([__('controller.user.password-wrong')]);
        }

        $validated['password'] = Hash::make($validated['password']);

        try {
            return new UserProfileSettingsResource(BackendSettingsController::updateSettings($validated));
        } catch (RateLimitExceededException) {
            return $this->sendError(error: __('email.verification.too-many-requests'), code: 400);
        }
    }

    /**
     * @OA\Put(
     *     path="/settings/profile",
     *     tags={"Settings"},
     *     summary="Update the current user's profile settings",
     *     description="Update the current user's profile settings",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="username", type="string", example="gertrud123", maxLength=25),
     *             @OA\Property(property="displayName", type="string", example="Gertrud", maxLength=50),
     *             @OA\Property(property="privateProfile", type="boolean", example=false, nullable=true),
     *             @OA\Property(property="preventIndex", type="boolean", example=false, nullable=true),
     *             @OA\Property(property="privacyHideDays", type="integer", example=1, nullable=true),
     *             @OA\Property(
     *                  property="defaultStatusVisibility",
     *                  type="integer",
     *                  nullable=true,
     *                  @OA\Schema(ref="#/components/schemas/VisibilityEnum")
     *              ),
     *             @OA\Property(
     *                  property="mastodonVisibility",
     *                  type="integer",
     *                  nullable=true,
     *                  @OA\Schema(ref="#/components/schemas/MastodonVisibilityEnum")
     *              )
     *         )
     *    ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object", ref="#/components/schemas/UserProfileSettings")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Unprocessable Entity"),
     *     @OA\Response(response=400, description="Bad Request"),
     *     security={
     *          {"passport": {"write-settings"}}, {"token": {}}
     *     }
     *     )
     */
    public function updateSettings(Request $request): UserProfileSettingsResource|JsonResponse {
        $validated = $request->validate([
                                            'username'                => ['required',
                                                                          'string',
                                                                          'max:25',
                                                                          'regex:/^[a-zA-Z0-9_]*$/'],
                                            'displayName'             => ['required', 'string', 'max:50'],
                                            'privateProfile'          => ['boolean', 'nullable'],
                                            'preventIndex'            => ['boolean', 'nullable'],
                                            'privacyHideDays'         => ['integer', 'nullable', 'gte:1'],
                                            'defaultStatusVisibility' => [
                                                'nullable',
                                                new Enum(StatusVisibility::class),
                                            ],
                                            'mastodonVisibility'      => [
                                                'nullable',
                                                new Enum(MastodonVisibility::class),
                                            ]
                                        ]);

        try {
            return new UserProfileSettingsResource(BackendSettingsController::updateSettings($validated));
        } catch (RateLimitExceededException) {
            return $this->sendError(error: __('email.verification.too-many-requests'), code: 400);
        }
    }

    /**
     * Undocumented and unofficial API Endpoint
     *
     * @return JsonResponse
     */
    public function deleteProfilePicture(): JsonResponse {
        if (BackendSettingsController::deleteProfilePicture(user: auth()->user())) {
            return $this->sendResponse(['message' => __('settings.profilePicture.deleted')]);
        }

        return $this->sendError(__('messages.exception.general'), 400);
    }

    /**
     * Undocumented and unofficial API Endpoint
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function uploadProfilePicture(Request $request): JsonResponse {
        if (BackendSettingsController::updateProfilePicture($request->input('image'))) {
            return $this->sendResponse(['message' => __('settings.saved')]);
        }
        return $this->sendError('', 400);
    }
}
