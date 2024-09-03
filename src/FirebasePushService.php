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

    public function sendNotification($title, $body, $tokens)
    {
        return 'sending notification';
        if (count($tokens) === 0) {
            return;
        }

        try {
            $response = $this->makeRequest($title, $body, $tokens);

            if ($response->failed()) {
                Log::error('Firebase notification failed', [
                    'response' => $response->body(),
                ]);
                return false;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Error sending Firebase notification', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

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
}
