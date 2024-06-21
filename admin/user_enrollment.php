
<?php 
session_start();
// Register here the page information
$page_title = "Users Enrollment";
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

// Function to get candidate data
function getCandidateData($db, $email) {
    $stmt = $db->prepare("SELECT * FROM candidate_profiles WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    return null;
}

// Function to enroll or update candidate
function enrollOrUpdateCandidate($db, $name, $email, $phone_number, $location, $english_level, $profile_photo, $candidate_cv) {
    // Check if candidate exists
    $candidateData = getCandidateData($db, $email);
    if ($candidateData) {
        // Update existing candidate
        $stmt = $db->prepare("UPDATE candidate_profiles SET name = IFNULL(name, ?), phone_number = IFNULL(phone_number, ?), location = IFNULL(location, ?), english_level = IFNULL(english_level, ?), profile_photo = IFNULL(profile_photo, ?), candidate_cv = IFNULL(candidate_cv, ?) WHERE email = ?");
        $stmt->bind_param("sssssss", $name, $phone_number, $location, $english_level, $profile_photo, $candidate_cv, $email);
    } else {
        // Insert new candidate
        $stmt = $db->prepare("INSERT INTO candidate_profiles (name, email, phone_number, location, english_level, profile_photo, candidate_cv, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssssss", $name, $email, $phone_number, $location, $english_level, $profile_photo, $candidate_cv);
    }
    $stmt->execute();
    $stmt->close();
}

// Get candidate data if email is provided
$candidate_email = isset($_POST['email']) ? $_POST['email'] : '';
$candidateData = getCandidateData($db, $candidate_email);

// Handle form submission
$enrollmentSuccess = false; // Initialize the variable
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_candidate'])) {
    $name = $_POST['name'] ?? ($candidateData['name'] ?? '');
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'] ?? ($candidateData['phone_number'] ?? '');
    $location = $_POST['location'] ?? ($candidateData['location'] ?? '');
    $english_level = $_POST['english_level'] ?? ($candidateData['english_level'] ?? '');
    $profile_photo = $_POST['profile_photo'] ?? ($candidateData['profile_photo'] ?? '');
    $candidate_cv = $_POST['candidate_cv'] ?? ($candidateData['candidate_cv'] ?? '');

    enrollOrUpdateCandidate($db, $name, $email, $phone_number, $location, $english_level, $profile_photo, $candidate_cv);
    $enrollmentSuccess = true; // Set the variable to true
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
	</style>
</head>

<body>
    <header class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="../images/samana-logo.png" alt="Samana Group Logo" class="logo"><br>
                   			<button type="button" class="btn btn-warning" onclick="location.href='user_responses.php';">Back</button>
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
<?php if ($enrollmentSuccess): ?>
    <p>Candidate Enrolled, please go to Candidate list to start the interview process.</p>
<?php else: ?>
    <?php if (!empty($candidate_email)): ?>
        <form method="POST" action="">
			
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($candidateData['name'] ?? ($_POST['full_name'] ?? '')); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email (cannot be edited)</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($candidate_email); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($candidateData['phone_number'] ?? ($_POST['phone_number'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($candidateData['location'] ?? ($_POST['location'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="english_level">English Level</label>
                                    <select name="english_level" id="english_level" class="form-control">
                                        <option value="A1">A1 - Beginner</option>
                                        <option value="B1">B1 - Intermediate</option>
                                        <option value="B2">B2 - Upper Intermediate</option>
                                        <option value="C1">C1 - Advanced</option>
                                        <option value="C2">C2 - Proficient</option>
                                    </select>
            </div>
            <div class="form-group">
                <label for="profile_photo">Profile Photo</label>
                <input type="text" class="form-control" id="profile_photo" name="profile_photo" value="<?php echo htmlspecialchars($candidateData['profile_photo'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="candidate_cv">Candidate CV</label>
                <input type="text" class="form-control" id="candidate_cv" name="candidate_cv" value="<?php echo htmlspecialchars($candidateData['candidate_cv'] ?? ($_POST['cv_url'] ?? '')); ?>">
            </div>
            <button type="submit" class="btn btn-primary" name="enroll_candidate">Enroll Candidate</button>
        </form>
    <?php else: ?>
        <p>No candidate selected to enroll. Go to the User-Responses to select the candidate to enroll.</p>
    <?php endif; ?>
<?php endif; ?>
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
  function confirmDelete(userId) {
    if (confirm("Are you sure you want to delete this user?")) {
        document.getElementById('deleteForm-' + userId).submit();
    }
}


    function showRoleForm(userId) {
        var form = document.getElementById('roleForm-' + userId);
        if (form.style.display === 'none') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }
</script>
</body>
</html>
