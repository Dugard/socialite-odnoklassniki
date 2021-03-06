<?php

namespace JhaoDa\SocialiteProviders\Odnoklassniki;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'ODNOKLASSNIKI';

    /**
     * {@inheritdoc}
     */
    protected $scopes = ['GET_EMAIL'];

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            'https://connect.ok.ru/oauth/authorize', $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://api.odnoklassniki.ru/oauth/token.do';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $params = [
            'format'          => 'json',
            'method'          => 'users.getCurrentUser',
            'application_key' => $this->getConfig('client_public', \env('ODNOKLASSNIKI_PUBLIC')),
            'fields'          => 'uid,name,first_name,last_name,birthday,pic190x190,has_email,email',
        ];

        \ksort($params, SORT_STRING);

        $_params = \array_map(static function ($key, $value) {
            return "{$key}={$value}";
        }, \array_keys($params), \array_values($params));

        $params['sig'] = \md5(\implode('', $_params).\md5($token.$this->clientSecret));
        $params['access_token'] = $token;

        $response = $this->getHttpClient()->get(
            'https://api.ok.ru/fb.do?'.\http_build_query($params)
        );

        return \json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['uid'],
            'name'     => $user['name'],
            'nickname' => null,
            'email'    => Arr::get($user, 'email'),
            'avatar'   => Arr::get($user, 'pic190x190'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return \array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }

    public static function additionalConfigKeys()
    {
        return [
            'client_public',
        ];
    }
}
