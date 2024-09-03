<?php
namespace Hijazi\FirebasePush;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function subscribeToTopic($topic, $tokens)
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

    // Subscribe devices to a topic and send notification
    public function subscribeAndSendNotification($title, $body, $topic, $tokens)
    {
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

    // Send notification to a Firebase topic
    public function sendToTopic($title, $body, $topic)
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
}
