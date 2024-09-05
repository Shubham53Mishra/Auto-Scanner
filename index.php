<?php
// Include required libraries
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "segment_message_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle file upload and segment creation
$message = '';
if (isset($_POST['create_segment'])) {
    $segment_name = $_POST['segment_name'];
    $file = $_FILES['file']['tmp_name'];

    if (is_uploaded_file($file)) {
        // Load Excel file
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray();

        // Save segment and mobile numbers to the database
        $stmt = $conn->prepare("INSERT INTO segments (segment_name) VALUES (?)");
        $stmt->bind_param("s", $segment_name);
        $stmt->execute();
        $segment_id = $stmt->insert_id;
        $stmt->close();

        foreach ($data as $row) {
            $mobile_number = $row[0]; // Assuming mobile number is in the first column

            if (!empty($mobile_number)) {
                $stmt = $conn->prepare("INSERT INTO segment_users (segment_id, mobile_number) VALUES (?, ?)");
                $stmt->bind_param("is", $segment_id, $mobile_number);
                $stmt->execute();
            }
        }
        $message = "Segment created successfully.";
    } else {
        $message = "Error uploading file.";
    }
}

// Handle sending messages
if (isset($_POST['send_message'])) {
    $segment_id = $_POST['segment'];
    $template_name = $_POST['template_name'];
    $Name = 'Ridobiko'; // Sample data
    $Date = '20 Aug 2024'; // Sample data
    $message = '';

    // Fetch phone numbers from the selected segment
    $stmt = $conn->prepare("SELECT mobile_number FROM segment_users WHERE segment_id = ?");
    $stmt->bind_param("i", $segment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $number = $row['mobile_number'];

            if (!empty($number)) {
                // URL for the API endpoint
                $url = 'https://graph.facebook.com/v20.0/430568443461658/messages'; // Replace with your API endpoint

                // Prepare the data for the API request
                $data = [
                    'messaging_product' => 'whatsapp',
                    'to' => $number,
                    'type' => 'template',
                    'template' => [
                        'name' => $template_name, // Use the selected template name
                        'language' => [
                            'code' => 'en_US' // Replace with the appropriate language code
                        ],
                        'components' => [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $Name],        // Corresponds to {{1}}
                                    ['type' => 'text', 'text' => $Date],        // Corresponds to {{2}}
                                ]
                            ]
                        ]
                    ]
                ];

                // Initialize cURL session
                $ch = curl_init($url);

                // Set cURL options
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Encode data here
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'Authorization: Bearer EAARMxPiCSEQBOw85ZA1Nnjt70yaFJmSSb1a8orVvYLjofNAzYV3FZAyVj7guanLQOUBdZBLLBnP2ZA90GtDKYslssvCEuOCZBU0p8lZBFhNdiJkiJJsFZC5Bg02vVvKhEFt26nCLGe7lCGo5zpNDENfZCEms7wU1JObP58b33br9PlbPUFoFZB4ZBZBdC02FA9A22JxF5L75aE5tjF1gkchuBgZD', // Replace with a valid access token
                    'Content-Type: application/json'
                ]);

                // Execute cURL request and capture the response
                $response = curl_exec($ch);

                // Check for cURL errors
                if (curl_errno($ch)) {
                    $message .= "cURL Error: " . curl_error($ch) . "<br>";
                } else {
                    // Get HTTP status code and response
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $response_data = json_decode($response, true);

                    // Handle API response based on HTTP status code
                    if ($http_code == 200) {
                        // Message sent successfully
                        $message .= "Message sent successfully to $number.<br>";
                    } else {
                        // Display error message with response details for debugging
                        $message .= "Failed to send message to $number. HTTP Status Code: $http_code<br>";
                        $message .= "<pre>" . print_r($response_data, true) . "</pre>";
                    }
                }

                // Close cURL session
                curl_close($ch);
            }
        }
    } else {
        $message = "No phone numbers found for the selected segment.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Segment & Message System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding-top: 20px;
        }
        .container {
            max-width: 800px;
        }
        .form-control, .form-control:focus {
            border-color: #ced4da;
            box-shadow: none;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .alert {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2 class="mt-5">Segment & Message System</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Form to create a segment -->
    <form method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="file">Choose File:</label>
            <input type="file" name="file" id="file" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="segment_name">Segment Name:</label>
            <input type="text" name="segment_name" id="segment_name" class="form-control" required>
        </div>
        <button type="submit" name="create_segment" class="btn btn-primary">Create Segment</button>
    </form>

    <hr>

    <!-- Form to send a message -->
    <form method="post">
        <div class="form-group">
            <label for="segment">Choose Segment:</label>
            <select name="segment" id="segment" class="form-control" required>
                <?php
                $result = $conn->query("SELECT id, segment_name FROM segments");
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='{$row['id']}'>{$row['segment_name']}</option>";
                    }
                } else {
                    echo "<option value=''>No segments available</option>";
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="template_name">Select Template:</label>
            <select name="template_name" id="template_name" class="form-control" required>
                <option value="booking_extend_confirmation">Booking Extend Confirmation</option>
                <option value="cancel">Cancellation Notice</option>
                <option value="payment_reminders">Payment Reminders</option>
            </select>
        </div>
        <button type="submit" name="send_message" class="btn btn-primary">Send Message</button>
    </form>

    <hr>

    <!-- Button to show Webhook Message Display -->
    <form method="post" action="webhook_display.php">
        <button type="submit" class="btn btn-secondary">View Webhook Message Display</button>
    </form>

</div>
</body>
</html>
