<?php

namespace FluentFormPro\Integrations\CleverReach;

use FluentForm\App\Services\ConditionAssesor;
use FluentForm\App\Services\Integrations\LogResponseTrait;
use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class API
{
    protected $clientId = null;

    protected $clientSecret = null;

    protected $callBackUrl = null;

    protected $settings = [];

    public function __construct($settings)
    {
        $this->clientId = $settings['client_id'];
        $this->clientSecret = $settings['client_secret'];
        $this->callBackUrl = admin_url('?ff_cleverreach_auth=1');
    }

    public function redirectToAuthServer()
    {
        $url = 'https://rest.cleverreach.com/oauth/authorize.php?client_id=' . $this->clientId . '&grant=basic&response_type=code&redirect_uri=' . $this->callBackUrl;

        wp_redirect($url);
        exit();
    }

    public function generateAccessToken($code, $settings)
    {
        $response = wp_remote_post('https://rest.cleverreach.com/oauth/token.php', [
            'body' => [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->callBackUrl,
                'code'          => $code
            ]
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body, true);

        if (isset($body['error_description'])) {
            return new \WP_Error('invalid_client', $body['error_description']);
        }

        $settings['access_token'] = $body['access_token'];
        $settings['refresh_token'] = $body['refresh_token'];
        $settings['expire_at'] = time() + intval($body['expires_in']);
        return $settings;
    }

    public function makeRequest($url, $bodyArgs, $type = 'GET', $headers = false)
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        $args = [
            'headers' => $headers
        ];

        if ($bodyArgs) {
            $args['body'] = $bodyArgs;
        }

        $args['method'] = $type;

        $request = wp_remote_request($url, $args);

        if (is_wp_error($request)) {
            $message = $request->get_error_message();
            return new \WP_Error(423, $message);
        }

        $body = json_decode(wp_remote_retrieve_body($request), true);

        if (!empty($body['error'])) {
            $error = 'Unknown Error';
            if (isset($body['error_description'])) {
                $error = $body['error_description'];
            } else if (!empty($body['error']['message'])) {
                $error = $body['error']['message'];
            }
            return new \WP_Error(423, $error);
        }

        return $body;
    }

    public function generateAccessKey($token)
    {
        $body = [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirect,
            'grant_type'    => 'authorization_code',
            'code'          => $token
        ];
        return $this->makeRequest('https://rest.cleverreach.com/oauth/token.php', $body, 'POST');
    }

    public function getAccessToken()
    {
        $tokens = get_option($this->optionKey);

        if (!$tokens) {
            return false;
        }

        if (($tokens['created_at'] + $tokens['expires_in'] - 30) < time()) {
            // It's expired so we have to re-issue again    
            $refreshTokens = $this->refreshToken($tokens);

            if (!is_wp_error($refreshTokens)) {
                $tokens['access_token'] = $refreshTokens['access_token'];
                $tokens['expires_in'] = $refreshTokens['expires_in'];
                $tokens['created_at'] = time();
                update_option($this->optionKey, $tokens, 'no');
            } else {
                return false;
            }
        }

        return $tokens['access_token'];
    }

    private function refreshToken($tokens)
    {
        $args = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $tokens['refresh_token'],
            'grant_type' => 'refresh_token'
        ];

        return $this->makeRequest('https://rest.cleverreach.com/oauth/token.php', $args, 'POST');
    }


    public function subscribe($subscriber)
    {
        $settings = get_option('_fluentform_cleverreach_settings');
        $token = $settings['access_token'];

        $response = $this->makeRequest('https://rest.cleverreach.com/groups/'.$subscriber['list_id'].'/receivers', $subscriber, 'POST', ['Authorization' => 'Bearer '.$token]);

        if ($response) {
            return $response;
        }

        return new \WP_Error('error', $response['errors']);
    }
}
