<?php


namespace Hijazi\FirebasePush;

use Google\Auth\Credentials\ServiceAccountJwtAccessCredentials;
use Google\Auth\Middleware\AuthTokenMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    protected $serverKey;
    protected $loggingEnabled;
    protected $serviceAccountPath;
    protected $projectId;

    public function __construct()
    {
        // Load config values
        $this->loggingEnabled = config('firebase_push.logging');
        $this->serverKey = config('firebase_push.server_key');
        $this->serviceAccountPath = storage_path(config('firebase_push.service_account_path'));
        $this->projectId = config('firebase_push.project_id');
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

    // ----------- Legacy (Current) API Implementation ------------

    public function sendNotificationLegacy($title, $body, $tokens, array $data = [])
    {
        if (empty($tokens)) {
            $this->logError('Tokens array is empty.');
            return false;
        }

        $url = 'https://fcm.googleapis.com/fcm/send';
        $notification = [
            "registration_ids" => $tokens,
            "notification" => [
                "title" => $title,
                "body" => $body,
            ],
            "data" => $data,
        ];

        $headers = [
            'Authorization' => 'key=' . $this->serverKey,
            'Content-Type' => 'application/json',
        ];

        $this->logInfo('Notification payload (Legacy) prepared.', $notification);

        try {
            $response = Http::withHeaders($headers)->post($url, $notification);

            if ($response->failed()) {
                $this->logError('Legacy Firebase notification failed', ['response' => $response->body()]);
                return false;
            }

            $responseBody = $response->json();
            $this->logInfo('Notification (Legacy) sent successfully.', $responseBody);

            return $responseBody;
        } catch (\Exception $e) {
            $this->logError('Failed to send notification (Legacy): ' . $e->getMessage());
            return false;
        }
    }

    // ----------- FCM V1 API Implementation ------------

    public function sendNotificationV1($title, $body, $token, array $data = [])
    {
        if (!file_exists($this->serviceAccountPath)) {
            $this->logError('Service account JSON file not found at path: ' . $this->serviceAccountPath);
            return false;
        }

        // Load the service account credentials
        $jsonKey = file_get_contents($this->serviceAccountPath);
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

        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ];

        if (empty($token)) {
            $this->logError('Token is empty.');
            return false;
        }

        if (empty($title) || empty($body)) {
            $this->logError('Notification title or body is empty.');
            return false;
        }

        $notification = [
            "message" => [
                "token" => $token,
                "notification" => [
                    "title" => $title,
                    "body" => $body
                ],
                "data" => $data ?: new \stdClass()
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

    public function subscribeToTopicV1($topic, $tokens)
    {
        return $this->topicManagementV1('projects/' . $this->projectId . '/messages:subscribeToTopic', $topic, $tokens);
    }

    public function unsubscribeFromTopicV1($topic, $tokens)
    {
        return $this->topicManagementV1('projects/' . $this->projectId . '/messages:unsubscribeFromTopic', $topic, $tokens);
    }

    private function topicManagementV1($topic, $tokens)
    {
        if (!file_exists($this->serviceAccountPath)) {
            $this->logError('Service account JSON file not found at path: ' . $this->serviceAccountPath);
            return false;
        }

        $jsonKey = file_get_contents($this->serviceAccountPath);
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

        // Correct URL for topic management
        $url = 'https://iid.googleapis.com/v1/projects/' . $this->projectId . '/rel/topics/' . $topic;

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ];

        $payload = [
            "registration_tokens" => $tokens, // Use 'registration_tokens' for multiple tokens
        ];

        $this->logInfo('Topic management payload prepared.', $payload);

        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $payload
            ]);

            $responseBody = json_decode($response->getBody(), true);
            $this->logInfo('Topic management action completed successfully.', $responseBody);

            return $responseBody;
        } catch (\Exception $e) {
            $this->logError('Failed to perform topic management action: ' . $e->getMessage());
            return false;
        }
    }


    public function sendToTopicV1($title, $body, $topic, array $data = [])
    {
        if (!file_exists($this->serviceAccountPath)) {
            $this->logError('Service account JSON file not found at path: ' . $this->serviceAccountPath);
            return false;
        }

        $jsonKey = file_get_contents($this->serviceAccountPath);
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

        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ];

        $notification = [
            "message" => [
                "topic" => $topic,
                "notification" => [
                    "title" => $title,
                    "body" => $body
                ],
                "data" => $data
            ]
        ];

        $this->logInfo('Notification payload for topic prepared.', $notification);

        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $notification
            ]);

            $responseBody = json_decode($response->getBody(), true);
            $this->logInfo('Notification sent to topic successfully.', $responseBody);

            return $responseBody;
        } catch (\Exception $e) {
            $this->logError('Failed to send notification to topic: ' . $e->getMessage());
            return false;
        }
    }

    public function markTopicAsDeleted($topic)
    {
        $topicRecord = Topic::where('name', $topic)->first();
        if ($topicRecord) {
            $topicRecord->deleted_at = now();
            $topicRecord->save();
            $this->logInfo("Topic marked as deleted: " . $topic);
        } else {
            $this->logError("Topic not found: " . $topic);
        }
    }
}
