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

    public function __construct($loggingEnabled = true, $serverKey = null)
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

    // ----------- Legacy (Current) API Implementation ------------

    // Send notification using the legacy FCM API
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

    // Subscribe devices to a topic using the legacy FCM API
    public function subscribeToTopicLegacy($topic, $tokens)
    {
        $url = 'https://iid.googleapis.com/iid/v1:batchAdd';
        $data = [
            "to" => "/topics/" . $topic,
            "registration_tokens" => $tokens
        ];

        $response = Http::withHeaders($this->prepareHeaders())
            ->post($url, $data);

        return $this->handleResponse($response);
    }

    // Unsubscribe devices from a topic using the legacy FCM API
    public function unsubscribeFromTopicLegacy($topic, $tokens)
    {
        $url = 'https://iid.googleapis.com/iid/v1:batchRemove';
        $data = [
            "to" => "/topics/" . $topic,
            "registration_tokens" => $tokens
        ];

        $response = Http::withHeaders($this->prepareHeaders())
            ->post($url, $data);

        return $this->handleResponse($response);
    }

    // Send notification to a topic using the legacy FCM API
    public function sendToTopicLegacy($title, $body, $topic)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $data = [
            "to" => "/topics/" . $topic,
            "notification" => [
                "title" => $title,
                "body" => $body,
            ],
        ];

        $response = Http::withHeaders($this->prepareHeaders())
            ->post($url, $data);

        return $this->handleResponse($response);
    }

    // ----------- FCM V1 API Implementation ------------

    // Send notification using FCM V1 API
    public function sendNotificationV1($title, $body, $token, $serviceAccountPath, $projectId, array $data = [])
{
    // Check if the service account JSON file exists
    if (!file_exists($serviceAccountPath)) {
        $this->logError('Service account JSON file not found at path: ' . $serviceAccountPath);
        return false;
    }

    // Load the service account credentials
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

    // Ensure the token is not empty
    if (empty($token)) {
        $this->logError('Token is empty.');
        return false;
    }

    // Ensure title and body are not empty
    if (empty($title) || empty($body)) {
        $this->logError('Notification title or body is empty.');
        return false;
    }

    // Prepare notification payload
    $notification = [
        "message" => [
            "token" => $token, // Single token as a string
            "notification" => [
                "title" => $title,
                "body" => $body
            ],
            "data" => $data ?: new \stdClass() // Make sure data is an associative array or empty object
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


    // Subscribe devices to a topic using FCM V1 API
    public function subscribeToTopicV1($topic, $tokens, $serviceAccountPath, $projectId)
    {
        return $this->topicManagementV1('projects/' . $projectId . '/messages:subscribeToTopic', $topic, $tokens, $serviceAccountPath);
    }

    // Unsubscribe devices from a topic using FCM V1 API
    public function unsubscribeFromTopicV1($topic, $tokens, $serviceAccountPath, $projectId)
    {
        return $this->topicManagementV1('projects/' . $projectId . '/messages:unsubscribeFromTopic', $topic, $tokens, $serviceAccountPath);
    }

    // Topic management helper for V1 API
    private function topicManagementV1($urlSuffix, $topic, $tokens, $serviceAccountPath)
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

        $url = 'https://fcm.googleapis.com/v1/' . $urlSuffix;
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ];

        $payload = [
            "topic" => $topic,
            "tokens" => $tokens,
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

    // Send notification to a topic using FCM V1 API
    public function sendToTopicV1($title, $body, $topic, $serviceAccountPath, $projectId, array $data = [])
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

    // Mark a topic as deleted in the database
    public function markTopicAsDeleted($topic)
    {
        // Assume there is a model Topic that tracks topics in the database
        $topicRecord = Topic::where('name', $topic)->first();
        if ($topicRecord) {
            $topicRecord->deleted_at = now();
            $topicRecord->save();
            $this->logInfo("Topic marked as deleted: " . $topic);
        } else {
            $this->logError("Topic not found: " . $topic);
        }
    }

    // Helper method to prepare headers for Firebase requests
    protected function prepareHeaders()
    {
        return [
            'Authorization' => 'key=' . $this->serverKey,
            'Content-Type' => 'application/json',
        ];
    }

    // Helper method to handle Firebase responses
    protected function handleResponse($response)
    {
        if ($response->failed()) {
            $this->logError('Firebase notification failed', ['response' => $response->body()]);
            return false;
        }

        return $response->json();
    }
}
