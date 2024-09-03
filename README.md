# Firebase Push Notification Package for Laravel

This package provides a comprehensive solution for managing and sending Firebase push notifications in Laravel applications. It includes functionality for topic management, sending notifications, subscribing devices to topics, and ensuring that notifications are only sent to active (non-deleted) topics. The package supports both the Legacy (Current) API and the Firebase Cloud Messaging (FCM) V1 API.

## Table of Contents

1. [Features](#features)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Usage](#usage)
   - [Sending Notifications](#sending-notifications)
   - [Managing Topics](#managing-topics)
   - [Subscribing Devices to Topics](#subscribing-devices-to-topics)
   - [Using the FCM V1 API](#using-the-fcm-v1-api)
5. [Explanation of Modules](#explanation-of-modules)
6. [Contributing](#contributing)
7. [License](#license)

## Features

- Send notifications to single or multiple devices.
- Manage Firebase topics, including defining and tracking them in a database.
- Automatically prevent notifications from being sent to deleted topics.
- Supports both Legacy (Current) API and FCM V1 API.
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

## Configuration

### 1. Firebase Server Key

Add your Firebase Server Key to your .env file:

```bash
FIREBASE_SERVER_KEY=your-firebase-server-key
```

This key is necessary for authenticating requests to Firebase Cloud Messaging (FCM) when using the Legacy API.

### 2. Config File

The configuration file `config/firebase_push.php` is published to your Laravel application. It contains basic configuration options, including the server key and other relevant settings.

## Usage

### 1. Sending Notifications

#### Send Notification to Multiple Devices (Legacy API)

To send the same notification to multiple devices using the Legacy API, use the `sendNotificationLegacy` method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$title = "Notification Title";
$body = "This is the body of the notification.";
$tokens = ['device_token1', 'device_token2'];

$response = $firebasePushService->sendNotificationLegacy($title, $body, $tokens);

if ($response) {
    echo "Notification sent successfully.";
} else {
    echo "Failed to send notification.";
}
```

#### Send Notification to Multiple Devices (FCM V1 API)

To send the same notification to multiple devices using the FCM V1 API, use the `sendNotificationV1` method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$title = "Notification Title";
$body = "This is the body of the notification.";
$tokens = ['device_token1', 'device_token2'];
$serviceAccountPath = storage_path('your-service-account.json');
$projectId = "your-firebase-project-id";

$response = $firebasePushService->sendNotificationV1($title, $body, $tokens, $serviceAccountPath, $projectId);

if ($response) {
    echo "Notification sent successfully using FCM V1 API.";
} else {
    echo "Failed to send notification using FCM V1 API.";
}
```

### 2. Managing Topics

The package automatically manages topics in your database. You can define topics, check if they are active, and mark them as deleted.

#### Send Notification to a Topic (Legacy API)

To send a notification to all devices subscribed to a topic using the Legacy API, use the `sendToTopicLegacy` method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$topic = "news";
$title = "Breaking News";
$body = "This is a news update.";

$response = $firebasePushService->sendToTopicLegacy($title, $body, $topic);

if ($response) {
    echo "Notification sent successfully to the topic (Legacy API).";
} else {
    echo "Failed to send notification to the topic (Legacy API).";
}
```

#### Send Notification to a Topic (FCM V1 API)

To send a notification to all devices subscribed to a topic using the FCM V1 API, use the `sendToTopicV1` method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$topic = "news";
$title = "Breaking News";
$body = "This is a news update.";
$serviceAccountPath = storage_path('your-service-account.json');
$projectId = "your-firebase-project-id";

$response = $firebasePushService->sendToTopicV1($title, $body, $topic, $serviceAccountPath, $projectId);

if ($response) {
    echo "Notification sent successfully to the topic (FCM V1 API).";
} else {
    echo "Failed to send notification to the topic (FCM V1 API).";
}
```

### 3. Subscribing Devices to Topics

#### Subscribe Devices to a Topic (Legacy API)

To subscribe devices to a topic using the Legacy API, use the `subscribeToTopicLegacy` method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$topic = "news";
$tokens = ['device_token1', 'device_token2'];

$response = $firebasePushService->subscribeToTopicLegacy($topic, $tokens);

if ($response) {
    echo "Devices subscribed to topic successfully (Legacy API).";
} else {
    echo "Failed to subscribe devices to topic (Legacy API).";
}
```

#### Subscribe Devices to a Topic (FCM V1 API)

To subscribe devices to a topic using the FCM V1 API, use the `subscribeToTopicV1` method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$topic = "news";
$tokens = ['device_token1', 'device_token2'];
$serviceAccountPath = storage_path('your-service-account.json');
$projectId = "your-firebase-project-id";

$response = $firebasePushService->subscribeToTopicV1($topic, $tokens, $serviceAccountPath, $projectId);

if ($response) {
    echo "Devices subscribed to topic successfully (FCM V1 API).";
} else {
    echo "Failed to subscribe devices to topic (FCM V1 API).";
}
```

### 4. Mark Topic as Deleted

To mark a topic as deleted, preventing further notifications from being sent to that topic, use the `markTopicAsDeleted` method:

```php
use Hijazi\FirebasePush\FirebasePushService;

$firebasePushService = app(FirebasePushService::class);
$topic = "news";

$firebasePushService->markTopicAsDeleted($topic);

echo "Topic marked as deleted.";
```

### 5. Explanation of Modules

1. **FirebasePushService:** The core service that handles notifications and topic management, supporting both the Legacy API and FCM V1 API.
2. **FirebasePushServiceProvider:** Integrates the package into Laravel, providing configuration and publishing migration files.
3. **Configuration File:** Manages Firebase server key and logging settings.
4. **Migration File:** Creates the `firebase_topics` table in the database for tracking topics.

### Contributing

If you encounter any issues or have suggestions for improvements, feel free to open an issue or submit a pull request.

### License

This package is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
