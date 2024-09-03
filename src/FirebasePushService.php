<?php
namespace Hijazi\FirebasePush;

use Google\Auth\Credentials\ServiceAccountJwtAccessCredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    protected $serverKey;
    protected $loggingEnabled;

    public function __construct($loggingEnabled = true, $serverKey)
    {
        $this->loggingEnabled = $loggingEnabled;
        $this->serverKey = $serverKey;
    }

    protected function logInfo($message, $context = [])
    {
        if ($this->loggingEnabled) {
            Log::info($message, $context);
        }
    }

    protected function logError($message, $context = [])
    {
        if ($this->loggingEnabled) {
            Log::error($message, $context);
        }
    }

    public function sendNotificationV1($title, $body, $tokens, $serviceAccountPath, $projectId, array $data = [])
    {
        if (!file_exists($serviceAccountPath)) {
            $this->logError('Service account JSON file not found at path: ' . $serviceAccountPath);
            return false;
        }

        $jsonKey = file_get_contents($serviceAccountPath);
        $decodedJson = json_decode($jsonKey, true);

        if ($decodedJson === null) {
            $this->logError('Failed to decode JSON from service account file.');
            return false;
        }

        $this->logInfo('Service account JSON successfully loaded.');

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = new ServiceAccountJwtAccessCredentials($decodedJson, $scopes);

        $client = new Client([
            'handler' => HandlerStack::create(),
            'auth'    => 'google_auth'
        ]);

        $middleware = new AuthTokenMiddleware($credentials);
        $client->getConfig('handler')->push($middleware);

        try {
            $tokenResponse = $credentials->fetchAuthToken();

            if (!is_array($tokenResponse)) {
                $this->logError('Token response is not an array: ' . print_r($tokenResponse, true));
                return false;
            }

            if (isset($tokenResponse['access_token'])) {
                $accessToken = $tokenResponse['access_token'];
            } else {
                $this->logError('Failed to fetch access token. Response: ' . print_r($tokenResponse, true));
                return false;
            }
        } catch (\Exception $e) {
            $this->logError('Exception occurred while fetching access token: ' . $e->getMessage());
            return false;
        }

        $this->logInfo('Access token successfully fetched.');

        $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ];

        if (empty($tokens)) {
            $this->logError('Tokens array is empty.');
            return false;
        }

        if (empty($title) || empty($body)) {
            $this->logError('Notification title or body is empty.');
            return false;
        }

        $notification = [
            "message" => [
                "token" => $tokens,
                "notification" => [
                    "title" => $title,
                    "body" => $body
                ],
                "data" => $data
            ]
        ];

        $this->logInfo('Notification payload prepared.', $notification);

        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $notification
            ]);

            $responseBody = json_decode($response->getBody(), true);
            $this->logInfo('Notification sent successfully.', $responseBody);

            return $responseBody;
        } catch (\Exception $e) {
            $this->logError('Failed to send notification: ' . $e->getMessage());
            return false;
        }
    }
}
