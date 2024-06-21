
<?php 
session_start();
// Register here the page information
$page_title = "Candidate REsponses";
$page_level = 5; // Set the required admin level for this page
$manager_level = 6;
$config = include('../google-login/config.php');
// Load the menu
$modules = load_menu();



function checkSessionTimeout() {
    $timeout_duration = 6000; // 10 minutes in seconds

    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
        // Last request was more than 1 hour ago
        session_unset();     // Unset $_SESSION variable for the run-time 
        session_destroy();   // Destroy session data in storage
        header("Location: ../google-login/logout.php");
        exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time(); // Update last activity time stamp
}

checkSessionTimeout();

if (!isset($_SESSION['email']) || !$_SESSION['is_admin']) {
    // Redirect to the home page if the user is not logged in or not an admin
    header('Location: ../index.php');
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['email']);
$name = $isLoggedIn ? $_SESSION['name'] : '';
$logoutUrl = 'https://jobs.samana.cloud/google-login/logout.php';
$loginUrl = 'https://jobs.samana.cloud/google-login/login.php'; // Add the login URL

// Include the database configuration file
$db = new mysqli($config['database']['host'], $config['database']['user'], $config['database']['pass'], $config['database']['db']);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Ensure the admin check query correctly fetches the admin status
$admin_status_query = "SELECT admin FROM users WHERE email = ?";
$stmt = $db->prepare($admin_status_query);
if ($stmt === false) {
    die("Failed to prepare statement: " . $db->error);
}
$stmt->bind_param('s', $_SESSION['email']);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();

// Fetch admin status of the logged-in user
$loggedInUserEmail = $_SESSION['email'];
$adminCheckSql = "SELECT admin FROM users WHERE email = ?";
$adminCheckStmt = $db->prepare($adminCheckSql);
$adminCheckStmt->bind_param('s', $loggedInUserEmail);
$adminCheckStmt->execute();
$adminCheckStmt->bind_result($loggedInUserAdminStatus);
$adminCheckStmt->fetch();
$adminCheckStmt->close();


// Function to check user's admin level
function checkUserAdminLevel($db, $email, $required_level) {
    $stmt = $db->prepare("SELECT admin FROM users WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($admin_level);
        $stmt->fetch();
        $stmt->close();
        return $admin_level >= $required_level;
    }
    return false;
}

// Get user email from session
$user_email = $_SESSION['email'];
$user_name = $_SESSION['name'];

// Check if user is authorized to view this page
$is_authorized = checkUserAdminLevel($db, $user_email, $page_level);


// Start PHP Functions Here


// Start PHP Functions Here
require '../google-login/vendor/autoload.php';
use Google\Client;
use Google\Service\Sheets;

// Set up the client
$client = new Client();
$client->setApplicationName('Google Sheets API PHP Quickstart');
$client->setScopes([Sheets::SPREADSHEETS_READONLY]);
$client->setDeveloperKey($config['google_sheets']['api_key']);

// Set up the Sheets service
$service = new Sheets($client);

// The ID of the spreadsheet to retrieve data from
$spreadsheetId = '1FKfomdn1aH4umwNU2dqkmINddqgxYdkDVmSdVJepGMk';

// The range of data to retrieve
$range = 'Form Responses 1!A1:AH'; // Adjust this range as needed

// Fetch the data from the spreadsheet
$response = $service->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();

if (empty($values)) {
    die("No data found in the spreadsheet.");
}

// Extract headers and data
$headers = $values[0];
$data = array_slice($values, 1);

$candidates = [];
foreach ($data as $row) {
    $candidate = [];
    foreach ($headers as $index => $header) {
        $candidate[$header] = isset($row[$index]) ? $row[$index] : '';
    }
    $candidates[] = $candidate;
}
?>




<!DOCTYPE html>
<html>
<head>
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Default Title'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
          .header, .footer {
            background-color: #143154;
            color: #f8f9fa;
            padding: 10px 0;
        }
        .header .logo {
            max-width: 150px;
        }
        .footer {
            text-align: center;
        }
        .form-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
        }
	.scrollable-table {
        max-height: 800px; /* Adjust height as needed */
        overflow-y: auto;
        display: block;
    }

	</style>
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="../images/samana-logo.png" alt="Samana Group Logo" class="logo"><br>
                   			<button type="button" class="btn btn-warning" onclick="location.href='index.php';">Back</button>
                </div>
                <div class="col">
                    <h3 class='mb-0'><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Default Title'; ?></h3>
                </div>
                <div class="col text-right">
                    <?php if ($isLoggedIn): ?>
                        <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span>
                        <a href="<?php echo $logoutUrl; ?>" class="btn btn-danger ml-2">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo $loginUrl; ?>" class="btn btn-primary ml-2">Sign In</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
	
	 <!-- Menu Module -->
	
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <?php foreach ($modules as $section => $items): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown_<?php echo htmlspecialchars($section); ?>" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?php echo htmlspecialchars($section); ?>
                            </a>
                            <div class="dropdown-menu" aria-labelledby="navbarDropdown_<?php echo htmlspecialchars($section); ?>">
                                <?php foreach ($items as $item): ?>
                                    <a class="dropdown-item" href="<?php echo htmlspecialchars($item['file']); ?>"><?php echo htmlspecialchars($item['name']); ?></a>
                                <?php endforeach; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </nav>


	
	
<div class="container form-container mt-5">
        <?php if ($is_authorized): ?>
            <!-- Authorized content goes here -->

<!-- Modules Inserted Here -->
            <div class="row">
                <div class="scrollable-table">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $index => $candidate): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($candidate['Timestamp']); ?></td>
                                    <td>
                                        <a href="#" onclick="showDetails(<?php echo $index; ?>); return false;"><?php echo htmlspecialchars($candidate['Full Name']); ?></a>
                                        <br>
                                        <small><?php echo htmlspecialchars($candidate['Email Address']); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <div id="candidate-details" style="display:none;">
                        <h3>Candidate Details</h3>
                        <div id="details"></div>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('candidate-details').style.display='none';">Close</button>
                        <form id="enrollmentForm" action="user_enrollment.php" method="POST">
                            <input type="hidden" name="full_name" id="full_name">
                            <input type="hidden" name="location" id="location">
                            <input type="hidden" name="phone_number" id="phone_number">
                            <input type="hidden" name="english_proficiency_level" id="english_proficiency_level">
                            <input type="hidden" name="cv_url" id="cv_url">
                            <button type="submit" class="btn btn-primary mt-3">Enroll Candidate</button>
                        </form>
                    </div>
                </div>
            </div>
<!-- End of Modules Here -->
	
	
            <!-- End of Modules Here -->
        <?php else: ?>
            <div class="alert alert-danger" role="alert">
                You are not authorized to view this page.
            </div>
        <?php endif; ?>
    </div>
    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container">
            <p>&copy; 2024 Samana Group. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    const candidates = <?php echo json_encode($candidates); ?>;

    // Function to convert a numeric value into star icons
    function getStars(rating) {
        let stars = '';
        for (let i = 0; i < rating; i++) {
            stars += '<i class="bi bi-star-fill text-warning"></i>';
        }
        return stars;
    }

    // Function to show candidate details and populate form
    function showDetails(index) {
        const candidate = candidates[index];
        const detailsDiv = document.getElementById('details');
        detailsDiv.innerHTML = '';

        const table = document.createElement('table');
        table.classList.add('table', 'table-striped');

        let cvUrl = '';

        for (const [key, value] of Object.entries(candidate)) {
            const row = document.createElement('tr');

            // Remove everything after the colon in the left column, including the colon itself
            const cleanKey = key.split(':')[0];

            const keyCell = document.createElement('td');
            keyCell.textContent = cleanKey;
            row.appendChild(keyCell);

            const valueCell = document.createElement('td');
            let parsedValue = value;

            // Check if the value matches the format "Number - Text"
            const match = value.match(/^(\d+)\s*-\s*(.*)$/);
            if (match && key !== 'Salary Expectation (USD/COP)') {
                const number = parseInt(match[1], 10);
                // Show only the stars
                parsedValue = `${getStars(number)}`;
            }

            // Search for the CV URL
            if (value.includes('https://drive.google.com/')) {
                cvUrl = value;
            }

            valueCell.innerHTML = parsedValue;
            row.appendChild(valueCell);

            table.appendChild(row);
        }

        detailsDiv.appendChild(table);

        document.getElementById('candidate-details').style.display = 'block';

        // Populate the form
        document.getElementById('full_name').value = candidate['Full Name'] || '';
        document.getElementById('location').value = candidate['Location'] || '';
        document.getElementById('phone_number').value = candidate['Phone Number (Include Country Code)'] || '';
        
        // Ensure English Proficiency Level is handled correctly
        const englishProficiencyMatch = candidate['English Proficiency Level'] ? candidate['English Proficiency Level'].match(/^\d+/) : null;
        document.getElementById('english_proficiency_level').value = englishProficiencyMatch ? englishProficiencyMatch[0] : '';

        // Ensure CV URL is handled correctly
        document.getElementById('cv_url').value = cvUrl;

        document.getElementById('enrollmentForm').style.display = 'block';
    }
</script>





</body>
</html>
