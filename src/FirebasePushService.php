<?php

namespace Hijazi\FirebasePush;

class FirebasePushService
{
    protected $serverKey;

    public function __construct($serverKey)
    {
        $this->serverKey = $serverKey;
    }

    public function sendNotification($title, $body, $tokens)
    {
        if (count($tokens) === 0) {
            return;
        }

        $url = 'https://fcm.googleapis.com/fcm/send';
        $data = [
            "registration_ids" => $tokens,
            "notification" => [
                "title" => $title,
                "body" => $body,
            ]
        ];
        $encodedData = json_encode($data);

        $headers = [
            'Authorization:key=' . $this->serverKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);

        $result = curl_exec($ch);

        if ($result === FALSE) {
            \Log::info('Curl failed: ' . curl_error($ch));
        }

        curl_close($ch);
        return $result;
    }
}
