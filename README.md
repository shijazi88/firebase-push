# Firebase Push Notification Package for Laravel

This package provides a comprehensive solution for managing and sending Firebase push notifications in Laravel applications. It includes functionality for topic management, sending notifications, subscribing devices to topics, and ensuring that notifications are only sent to active (non-deleted) topics.

## Table of Contents

1. [Features](#features)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Usage](#usage)
   - [Sending Notifications](#sending-notifications)
   - [Managing Topics](#managing-topics)
   - [Subscribing Devices to Topics](#subscribing-devices-to-topics)
5. [Explanation of Modules](#explanation-of-modules)
6. [Contributing](#contributing)
7. [License](#license)

## Features

- Send notifications to single or multiple devices.
- Manage Firebase topics, including defining and tracking them in a database.
- Automatically prevent notifications from being sent to deleted topics.
- Seamless integration with Laravel’s configuration and service container.

## Installation

### 1. Require the Package

To install the package, run the following command in your Laravel project:

```bash
composer require hijazi/firebase-push
```

### 2. Publish the Configuration and Migration

Next, publish the configuration file and migration using Artisan commands:

```bash
php artisan vendor:publish --provider="Hijazi\FirebasePush\FirebasePushServiceProvider" --tag="firebase-push-config"
php artisan vendor:publish --provider="Hijazi\FirebasePush\FirebasePushServiceProvider" --tag="migrations"
```

### 3. Run the Migration

Once the migration is published, run it to create the firebase_topics table:

```bash
php artisan migrate
```

## 4. Configuration

### 1. Firebase Server Key

Add your Firebase Server Key to your .env file:

```bash
FIREBASE_SERVER_KEY=your-firebase-server-key
```

This key is necessary for authenticating requests to Firebase Cloud Messaging (FCM).

### 2. Config File

The configuration file config/firebase_push.php is published to your Laravel application. It contains basic configuration options, including the server key and other relevant settings.

## 5. Usage

## 1. Sending Notifications

Send Notification to Multiple Devices
To send the same notification to multiple devices, use the sendNotification method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$title = "Notification Title";
$body = "This is the body of the notification.";
$tokens = ['device_token1', 'device_token2'];

$response = $firebasePushService->sendNotification($title, $body, $tokens);

if ($response) {
echo "Notification sent successfully.";
} else {
echo "Failed to send notification.";
}
```

## 2. Send Different Notifications to Multiple Devices

To send different notifications to multiple devices, use the sendToMultipleDevices method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);

$notifications = [
[
'title' => 'First Notification',
'body' => 'This is the first message',
'tokens' => ['device_token1', 'device_token2']
],
[
'title' => 'Second Notification',
'body' => 'This is the second message',
'tokens' => ['device_token3', 'device_token4']
],
];

$responses = $firebasePushService->sendToMultipleDevices($notifications);

foreach ($responses as $response) {
    if ($response) {
echo "Notification sent successfully.";
} else {
echo "Failed to send notification.";
}
}

```

## 3. Send Notification to a Single Device

To send a notification to a single device, use the sendToSingleDevice method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$title = "Single Notification";
$body = "This is the body of the notification.";
$token = 'device_token1';

$response = $firebasePushService->sendToSingleDevice($title, $body, $token);

if ($response) {
echo "Notification sent successfully.";
} else {
echo "Failed to send notification.";
}
```

## 4. Send Notification with Custom Data

To send custom data along with the notification, use the sendWithCustomData method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$title = "Notification with Custom Data";
$body = "This notification includes custom data.";
$tokens = ['device_token1', 'device_token2'];
$customData = ['key1' => 'value1', 'key2' => 'value2'];

$response = $firebasePushService->sendWithCustomData($title, $body, $tokens, $customData);

if ($response) {
echo "Notification sent successfully with custom data.";
} else {
echo "Failed to send notification.";
}
```

## Managing Topics

The package automatically manages topics in your database. You can define topics, check if they are active, and mark them as deleted.

### 1. Send Notification to a Topic

To send a notification to all devices subscribed to a topic, use the sendToTopic method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$topic = "news";
$title = "Breaking News";
$body = "This is a news update.";

$response = $firebasePushService->sendToTopic($title, $body, $topic);

if ($response) {
echo "Notification sent successfully to the topic.";
} else {
echo "Failed to send notification to the topic.";
}
```

### 2. Subscribe Devices to a Topic and Send Notification

To subscribe devices to a topic and then send a notification to that topic, use the subscribeAndSendNotification method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$topic = "news";
$title = "Breaking News";
$body = "This is a news update.";
$tokens = ['device_token1', 'device_token2'];

$response = $firebasePushService->subscribeAndSendNotification($title, $body, $topic, $tokens);

if ($response) {
echo "Devices subscribed and notification sent successfully.";
} else {
echo "Failed to subscribe devices or send notification.";
}
```

### 3. Mark Topic as Deleted

To mark a topic as deleted, preventing further notifications from being sent to that topic, use the markTopicAsDeleted method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$topic = "news";

$firebasePushService->markTopicAsDeleted($topic);

echo "Topic marked as deleted.";
```
