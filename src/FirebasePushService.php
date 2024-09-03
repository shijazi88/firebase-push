<?php
namespace Hijazi\FirebasePush;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;


class FirebasePushService
{
    protected $serverKey;

    public function __construct($serverKey)
    {
        $this->serverKey = $serverKey;
    }

    // Send notification to multiple devices with the same message
    public function sendNotification($title, $body, $tokens)
    {
        if (count($tokens) === 0) {
            return;
        }

        $response = $this->makeRequest($title, $body, $tokens);

        return $this->handleResponse($response);
    }

    // Send different notifications to multiple devices
    public function sendToMultipleDevices(array $notifications)
    {
        $responses = [];

        foreach ($notifications as $notification) {
            $title = $notification['title'] ?? 'Default Title';
            $body = $notification['body'] ?? 'Default Body';
            $tokens = $notification['tokens'] ?? [];

            if (count($tokens) > 0) {
                $response = $this->makeRequest($title, $body, $tokens);
                $responses[] = $this->handleResponse($response);
            }
        }

        return $responses;
    }

    // Send notification to a single device
    public function sendToSingleDevice($title, $body, $token)
    {
        return $this->sendNotification($title, $body, [$token]);
    }

    // Subscribe a device to a topic
    public function subscribeToTopic($topic, $tokens)
    {
        $this->defineTopic($topic); // Ensure the topic is defined in the database

        $url = 'https://iid.googleapis.com/iid/v1:batchAdd';
        $data = [
            "to" => "/topics/" . $topic,
            "registration_tokens" => $tokens
        ];

        $response = Http::withHeaders($this->prepareHeaders())
            ->post($url, $data);

        return $this->handleResponse($response);
    }

    // Unsubscribe a device from a topic
    public function unsubscribeFromTopic($topic, $tokens)
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

    // Subscribe devices to a topic and send notification
    public function subscribeAndSendNotification($title, $body, $topic, $tokens)
    {
        $this->defineTopic($topic); // Ensure the topic is defined in the database

        if (!$this->isTopicActive($topic)) {
            Log::warning("Attempted to send notification to deleted topic: $topic");
            return false;
        }

        // First, subscribe the tokens to the topic
        $subscribeResponse = $this->subscribeToTopic($topic, $tokens);

        // Check if subscription was successful
        if ($subscribeResponse) {
            // If successful, send the notification to the topic
            return $this->sendToTopic($title, $body, $topic);
        } else {
            // Handle subscription failure
            Log::error('Failed to subscribe devices to topic: ' . $topic);
            return false;
        }
    }

    // Send notification to a Firebase topic
    public function sendToTopic($title, $body, $topic)
    {
        if (!$this->isTopicActive($topic)) {
            Log::warning("Attempted to send notification to deleted topic: $topic");
            return false;
        }

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

    // Send notification with custom data
    public function sendWithCustomData($title, $body, $tokens, array $customData = [])
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $data = [
            "registration_ids" => $tokens,
            "notification" => [
                "title" => $title,
                "body" => $body,
            ],
            "data" => $customData,
        ];

        $response = Http::withHeaders($this->prepareHeaders())
            ->post($url, $data);

        return $this->handleResponse($response);
    }

    // Define a topic in the database if it doesn't exist
    protected function defineTopic($topic)
    {
        $existingTopic = DB::table('firebase_topics')->where('topic_name', $topic)->first();

        if (!$existingTopic) {
            DB::table('firebase_topics')->insert([
                'topic_name' => $topic,
                'is_deleted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } elseif ($existingTopic->is_deleted) {
            // If the topic is marked as deleted, reactivate it
            DB::table('firebase_topics')->where('topic_name', $topic)->update([
                'is_deleted' => false,
                'updated_at' => now(),
            ]);
        }
    }

    // Check if a topic is active (not deleted)
    protected function isTopicActive($topic)
    {
        return DB::table('firebase_topics')
            ->where('topic_name', $topic)
            ->where('is_deleted', false)
            ->exists();
    }

    // Mark a topic as deleted
    public function markTopicAsDeleted($topic)
    {
        DB::table('firebase_topics')
            ->where('topic_name', $topic)
            ->update(['is_deleted' => true, 'updated_at' => now()]);
    }

    // Helper methods
    protected function makeRequest($title, $body, $tokens)
    {
        $url = 'https://fcm.googleapis.com/fcm/send';
        $data = $this->prepareData($title, $body, $tokens);

        return Http::withHeaders($this->prepareHeaders())
            ->post($url, $data);
    }

    protected function prepareData($title, $body, $tokens)
    {
        return [
            "registration_ids" => $tokens,
            "notification" => [
                "title" => $title,
                "body" => $body,
            ],
        ];
    }

    protected function prepareHeaders()
    {
        return [
            'Authorization' => 'key=' . $this->serverKey,
            'Content-Type' => 'application/json',
        ];
    }

    protected function handleResponse($response)
    {
        if ($response->failed()) {
            Log::error('Firebase notification failed', [
                'response' => $response->body(),
            ]);
            return false;
        }

        return $response->json();
    }

    public function sendNotificationV1($title, $body, $tokens, $serviceAccountPath, $projectId, array $data = [])
    {
        $client = new Client([
            'handler' => HandlerStack::create(),
            'auth'    => 'google_auth'
        ]);

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $jsonKey = json_decode(file_get_contents($serviceAccountPath), true);
        $credentials = ApplicationDefaultCredentials::makeCredentials($scopes, $jsonKey);

        $middleware = new AuthTokenMiddleware($credentials);
        $client->getConfig('handler')->push($middleware);

        $accessToken = $credentials->fetchAuthToken()['access_token'];

        $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ];

        $notification = [
            "message" => [
                "token" => $tokens, // This could be an array of tokens
                "notification" => [
                    "title" => $title,
                    "body" => $body
                ],
                "data" => $data // Additional data payload
            ]
        ];

        try {
            $response = $client->post($url, [
                'headers' => $headers,
                'json' => $notification
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return false;
        }
    }
}
