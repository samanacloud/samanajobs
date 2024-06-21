<?php
session_start();
$config = include('../google-login/config.php');
$page_title = "Register Candidate Certifications";
$page_level = 3; // Set the required admin level for this page
$manager_level = 5;

// Load the menu
$modules = load_menu();



// Register here the page information

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

if (!isset($_SESSION['email']) ) {
    // Redirect to the home page if the user is not logged in or not an admin
    header('Location: ../index.php');
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['email']);
$name = $isLoggedIn ? $_SESSION['name'] : '';
$logoutUrl = 'https://jobs.samana.cloud/google-login/logout.php';

// New PHP Modules Here

// Include the database configuration file

$db = new mysqli($config['database']['host'], $config['database']['user'], $config['database']['pass'], $config['database']['db']);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Handle form submission to add certifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['certifications'])) {
    $candidate_email = $_POST['candidate_email'];
    $certifications = $_POST['certifications'];

    foreach ($certifications as $certification) {
        // Check if certification already exists
        $stmt = $db->prepare("SELECT * FROM candidate_certifications WHERE email = ? AND certification = ?");
        $stmt->bind_param("ss", $candidate_email, $certification);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 0) {
            $stmt = $db->prepare("INSERT INTO candidate_certifications (email, certification) VALUES (?, ?)");
            $stmt->bind_param("ss", $candidate_email, $certification);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// Handle certification deletion
if (isset($_GET['delete_certification'])) {
    $candidate_email = $_GET['candidate_email'];
    $certification_to_delete = $_GET['delete_certification'];
    $stmt = $db->prepare("DELETE FROM candidate_certifications WHERE email = ? AND certification = ?");
    $stmt->bind_param("ss", $candidate_email, $certification_to_delete);
    $stmt->execute();
    $stmt->close();
}

// Retrieve all certifications for the candidate
$candidate_email = $_GET['candidate_email'] ?? '';
$certifications_result = $db->prepare("SELECT certification FROM candidate_certifications WHERE email = ?");
$certifications_result->bind_param("s", $candidate_email);
$certifications_result->execute();
$certifications_result->store_result();
$certifications_result->bind_result($cert_name);
$certifications = [];
while ($certifications_result->fetch()) {
    $certifications[] = $cert_name;
}
$certifications_result->close();

// List of all possible certifications
$certification_list = [
    'CCA-N Citrix Certified Associate - Networking',
    'CCP-N Citrix Certified Professional - Networking',
    'CCE-N Citrix Certified Expert - Networking',
    'CCP-V Citrix Certified Professional - Virtualization',
    'CCE-V Citrix Certified Expert - Virtualization',
    'CCP-W Citrix Certified Professional - Workspace',
    'CCA-V Citrix Certified Associate - Virtualization',
	'CCA-AppDS - Citrix Certified Associate – App Delivery and Security',
	'CCP-AppDS - Citrix Certified Professional – App Delivery and Security',
    'AWS Certified Cloud Practitioner',
    'AWS Certified Solutions Architect – Associate',
    'AWS Certified Developer – Associate',
    'AWS Certified SysOps Administrator – Associate',
    'AWS Certified Solutions Architect – Professional',
    'AWS Certified DevOps Engineer – Professional',
    'Microsoft Certified: Azure Fundamentals',
    'Microsoft Certified: Azure Administrator Associate',
    'Microsoft Certified: Azure Solutions Architect Expert',
    'Microsoft Certified: Azure DevOps Engineer Expert',
    'Microsoft 365 Certified: Fundamentals',
    'Microsoft 365 Certified: Enterprise Administrator Expert',
    'Microsoft Certified: Security, Compliance, and Identity Fundamentals',
    'Microsoft Certified: Dynamics 365 Sales Functional Consultant Associate',
    'CompTIA A+',
    'CompTIA Network+',
    'CompTIA Security+',
    'CISSP',
    'CEH',
    'Google Professional Cloud Architect',
    'PMP',
    'Certified Kubernetes Administrator (CKA)',
	'CCNA-Cisco Certified Network Associate',
	'HCNA - Huawei technologies',
	'HCNA WLAN - Huawei technologies',
	'ITIL v3 Foundation Certified',
	'SCRUM MASTER PROFESSIONAL CERTIFICATE (SMPC)',
	'FortiAnalyzer 7.0 Administrator',
	'Fortinet Certified Professional Network Security',
	'IBM Cloud Technical Advocate Concepts',
	'IBM Cloud Technical Advocate Foundations',
	'OCI Foundations Associate'
];

// Sort the certification list alphabetically
sort($certification_list);

// Filter out already registered certifications
$available_certifications = array_diff($certification_list, $certifications);

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
        .file-list a {
            display: block;
            margin: 10px 0;
        }
        .btn-menu {
            margin: 5px;
        }
        @media (max-width: 767.98px) {
            .header .logo {
                max-width: 100px;
            }
        }
        .rating-bar {
            display: flex;
        }
        .star {
            font-size: 24px;
            color: #ffc107; /* Bootstrap's warning color */
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="../images/samana-logo.png" alt="Samana Group Logo" class="logo"><br>
                    					<form method="POST" action="candidate_profile.php" style="display:inline;">
						<input type="hidden" name="email" value="<?php echo htmlspecialchars($candidate_email); ?>">
						<button type="submit" class="btn btn-warning">Back</button>
							</form>
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


    <!-- Modules Inserted Here -->
    <div class="container mt-5">
        <div class="form-container">
            <form method="post">
                <input type="hidden" name="candidate_email" value="<?php echo htmlspecialchars($candidate_email); ?>">
                <div class="form-group">
                    <h4 for="certifications">Select Certifications:</h4>
                    <div class="row">
                        <?php
                        foreach ($available_certifications as $index => $certification) {
                            echo '<div class="col-12 col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="certifications[]" value="' . htmlspecialchars($certification) . '" id="cert_' . $index . '">
                                        <label class="form-check-label" for="cert_' . $index . '">
                                            ' . htmlspecialchars($certification) . '
                                        </label>
                                    </div>
                                  </div>';
                        }
                        ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Register Certifications</button>
            </form>

            <h4 class="mt-5">Registered Certifications:</h4>
            <ul class="list-group">
                <?php foreach ($certifications as $cert) : ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo htmlspecialchars($cert); ?>
                        <a href="?candidate_email=<?php echo urlencode($candidate_email); ?>&delete_certification=<?php echo urlencode($cert); ?>" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash"></i>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <!-- End of Modules Here -->

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container">
            <p>&copy; 2024 Samana Group. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
