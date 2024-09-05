<?php
// webhook_display.php

$webhookUrl = "https://whatapp-api-cheak.onrender.com/webhook";

// Initialize cURL session for webhook URL
$ch = curl_init($webhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    $error = curl_error($ch);
    $data = [
        'status' => 'error',
        'message' => 'Failed to fetch data from webhook. cURL Error: ' . $error
    ];
    curl_close($ch);
} else {
    curl_close($ch);

    // Decode the JSON response
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = [
            'status' => 'error',
            'message' => 'Failed to decode JSON response. Error: ' . json_last_error_msg()
        ];
    } elseif (!isset($data['status'])) {
        $data = [
            'status' => 'error',
            'message' => 'Unexpected JSON structure received.'
        ];
    }
}

// API URL for fetching messages
$apiUrl = "https://whatapp-api-cheak.onrender.com/messages";

// Initialize cURL session for messages API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    $messagesError = 'cURL Error: ' . curl_error($ch);
} else {
    $messages = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $messagesError = 'Failed to decode JSON response. Error: ' . json_last_error_msg();
    }
}

curl_close($ch);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhook Message Display</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .message-container {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }
        .message-sent {
            background-color: #e1ffc7;
            align-self: flex-end;
        }
        .message-received {
            background-color: #f1f1f1;
            align-self: flex-start;
        }
        .message-body {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .message-sender {
            font-size: 12px;
            color: #555;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">Webhook Message Display</h1>
    <div id="messageDisplay">
        <?php if (isset($data['status']) && $data['status'] === 'success'): ?>
            <div class="message-container message-received">
                <p class="message-body"><?php echo htmlspecialchars($data['body']); ?></p>
                <p class="message-sender">From: <?php echo htmlspecialchars($data['from']); ?></p>
            </div>
        <?php else: ?>
            <div class="alert alert-danger" role="alert">
                Error: <?php echo htmlspecialchars($data['message']); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (isset($messagesError)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($messagesError); ?>
        </div>
    <?php endif; ?>

    <div class="message-list mt-4">
        <?php if (isset($messages) && is_array($messages) && !empty($messages)): ?>
            <?php foreach ($messages as $message): ?>
                <div class="message-container message-sent">
                    <p class="message-body"><?php echo htmlspecialchars($message['messageBody']); ?></p>
                    <p class="message-sender">Sender Number: <?php echo htmlspecialchars($message['sender_Number']); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No messages found or unable to fetch data.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
