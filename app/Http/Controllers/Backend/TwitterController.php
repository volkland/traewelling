<?php

namespace App\Http\Controllers\Backend;

use App\Exceptions\NotConnectedException;
use App\Http\Controllers\Backend\Social\AbstractTwitterController;
use App\Models\Status;
use App\Models\User;
use Coderjerk\BirdElephant\BirdElephant;
use Coderjerk\BirdElephant\Compose\Tweet;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Smolblog\OAuth2\Client\Provider\Twitter;

class TwitterController extends AbstractTwitterController
{

    /**
     * @param User $user
     *
     * @return BirdElephant
     * @throws IdentityProviderException
     * @throws NotConnectedException
     */
    public static function getApi(User $user): BirdElephant {
        $socialProfile = $user->socialProfile;
        if ($socialProfile?->twitter_id === null
            || $socialProfile?->twitter_token === null
            || $socialProfile->twitter_refresh_token === null
            || $socialProfile->twitter_token_expires_at === null
        ) {
            throw new NotConnectedException();
        }

        $accessToken = $socialProfile->twitter_token;
        if (Date::now()->isAfter($socialProfile->twitter_token_expires_at)) {
            $provider = new Twitter([
                                        'clientId'     => config('trwl.twitter_id'),
                                        'clientSecret' => config('trwl.twitter_secret'),
                                        'redirectUri'  => config('trwl.twitter_redirect'),
                                    ]);

            $token = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $socialProfile->twitter_refresh_token
            ]);
            $socialProfile->update([
                                       'twitter_token'            => $token->getToken(),
                                       'twitter_refresh_token'    => $token->getRefreshToken(),
                                       'twitter_token_expires_at' => Date::createFromTimestamp($token->getExpires())
                                   ]);
            $accessToken = $token->getToken();
            Log::info("Refreshed twitter access token for {$socialProfile->twitter_id}");
        }

        $credentials = [
            'consumer_key'    => config('trwl.twitter_id'),
            'consumer_secret' => config('trwl.twitter_secret'),
            'auth_token'      => $accessToken
        ];
        return new BirdElephant($credentials);
    }

    /**
     * @param Status $status
     * @param string $socialText
     *
     * @return string
     * @throws IdentityProviderException
     * @throws NotConnectedException
     */
    public function postTweet(Status $status, string $socialText): string {
        $socialProfile = $status->user->socialProfile;
        if ($socialProfile?->twitter_id === null || $socialProfile?->twitter_token === null) {
            throw new NotConnectedException();
        }

        $twitterApi  = self::getApi($status->user);
        $newTweet    = (new Tweet)->text($socialText);
        $postedTweet = $twitterApi->tweets()->tweet($newTweet)->data;
        return $postedTweet?->id_str ?? $postedTweet->id;
    }
}
