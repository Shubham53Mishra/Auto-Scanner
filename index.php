<?php
// Include required libraries
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

include './db/connect_db.php';

// Database connection
$conn = getDBConnection();

$TOKEN = "Authorization:Bearer EAARMxPiCSEQBOZBzPDNMiZAj5RrwDzxcaU0ZCgOLI8fezNLbovPj8B2L4NZCJgm963qXp0WRoKNOeZAeZAjaCzOhNUG7zw32ZCO0HTj9IoYS159gIrrvRrHIFHhwZBgwDkK1dhBLeqw07Xeia3eO0Dn3NmWLk3gDuXB07ZC6FJS5s345er51MXjq0J4QZBX6JC1Fw9Cq6N5yCAkc6DwsksMIBrJQxufKEGjpK1ginnYRCR";

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
        
        // Check if the prepare() function failed
        if ($stmt === false) {
            die("Error in preparing statement: " . $conn->error); // Debugging output
        }
    
        $stmt->bind_param("i", $segment_id);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $number = $row['mobile_number'];
    
                if (!empty($number)) {
                    // URL for the API endpoint
                    $url = 'https://graph.facebook.com/v20.0/430568443461658/messages'; 
    
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
                        $TOKEN, // Replace with a valid access token
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
                            $message .= "Message sent successfully to $number.<br>";
                            $newnumber = "91$number";

                            $messageBody = "Template Name : $template_name<br>Segment ID : $segment_id<br>Parameters : [Name:  $Name, Date: $Date]";
                            $stmt = $conn->prepare("INSERT INTO messages (mobile_number, message, sender) VALUES (?, ?, 'admin')");
                            $stmt->bind_param("ss", $newnumber, $messageBody);
                            $stmt->execute();
                            $stmt->close();

                        } else {
                            $message .= "Failed to send message to $number. HTTP Status Code: $http_code<br>";
                            $message .= "<pre>" . print_r($response_data, true) . "</pre>";
                        }
                    }
    
                    // Close cURL session
                    curl_close($ch);
    
                    // Store the segment and template information in the segment_template database
                    $stmt_store = $conn->prepare("INSERT INTO segment_template (segment_id, template_name, mobile_number) VALUES (?, ?, ?)");
    
                    // Check if the prepare() function failed
                    if ($stmt_store === false) {
                        die("Error in preparing insert statement: " . $conn->error); // Debugging output
                    }
    
                    $stmt_store->bind_param("iss", $segment_id, $template_name, $number);
                    $stmt_store->execute();
                }
            }
                      // Fetch segment templates from the database
                     $template_details_query = "SELECT segment_template.segment_id, segment_template.template_name, segments.segment_name
                     FROM segment_template
                     INNER JOIN segments ON segment_template.segment_id = segments.id";
                      $templates_result = $conn->query($template_details_query);

        } else {
            $message = "No phone numbers found for the selected segment.";
        }
    }
    
    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Segment & Message System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./css/style.css" />
    
</head>
<body>

<div class="container">
    <h2 class="text-center">Segment & Message System</h2>

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
        <button type="submit" name="create_segment" class="btn btn-primary w-100">Create Segment</button>
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
        <button type="submit" name="send_message" class="btn btn-primary w-100">Send Message</button>
    </form>

    <hr>

    <!-- Button group to show Webhook Message Display and View History side by side -->
    <div class="btn-group w-100" role="group">
        <form method="get" action="chat_room.php" class="w-50">
            <button type="submit" class="btn btn-success w-100">Ridobiko Whatsapp Chatroom</button>
        </form>
        <div>....</div>
        <form method="get" action="history.php" class="w-50">
            <button type="submit" class="btn btn-warning w-100">Templates Sent History</button>
        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
