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

    public function __construct($serverKey)
    {
        $this->serverKey = $serverKey;
    }

    public function sendNotificationV1($title, $body, $tokens, $serviceAccountPath, $projectId, array $data = [])
    {
        // Check if the service account JSON file exists
        if (!file_exists($serviceAccountPath)) {
            Log::error('Service account JSON file not found at path: ' . $serviceAccountPath);
            return false;
        }

        // Load the service account credentials
        $jsonKey = file_get_contents($serviceAccountPath);
        $decodedJson = json_decode($jsonKey, true);

        if ($decodedJson === null) {
            Log::error('Failed to decode JSON from service account file.');
            return false;
        }

        Log::info('Service account JSON successfully loaded.');

        // Add the required scopes for FCM
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $credentials = new ServiceAccountJwtAccessCredentials($decodedJson, $scopes);

        // Create a Guzzle HTTP client with the credentials
        $client = new Client([
            'handler' => HandlerStack::create(),
            'auth'    => 'google_auth'
        ]);

        $middleware = new AuthTokenMiddleware($credentials);
        $client->getConfig('handler')->push($middleware);

        try {
            // Fetch the OAuth token
            $tokenResponse = $credentials->fetchAuthToken();

            if (!is_array($tokenResponse)) {
                Log::error('Token response is not an array: ' . print_r($tokenResponse, true));
                return false;
            }

            if (isset($tokenResponse['access_token'])) {
                $accessToken = $tokenResponse['access_token'];
            } else {
                Log::error('Failed to fetch access token. Response: ' . print_r($tokenResponse, true));
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception occurred while fetching access token: ' . $e->getMessage());
            return false;
        }

        Log::info('Access token successfully fetched.');

        $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ];

        // Prepare the notification payload
        if (empty($tokens)) {
            Log::error('Tokens array is empty.');
            return false;
        }

        if (empty($title) || empty($body)) {
            Log::error('Notification title or body is empty.');
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

        Log::info('Notification payload prepared.', $notification);

        try {
            // Send the notification
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $notification
            ]);

            $responseBody = json_decode($response->getBody(), true);
            Log::info('Notification sent successfully.', $responseBody);

            return $responseBody;
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return false;
        }
    }
}
