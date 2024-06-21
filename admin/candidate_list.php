<?php
session_start();
$config = include('../google-login/config.php');
$page_title = "Candidates List";
$page_level = 3; // Set the required admin level for this page
$manager_level = 5;

// Load the menu
$modules = load_menu();






function checkSessionTimeout() {
    $timeout_duration = 6000; // 10 minutes in seconds

    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
        // Last request was more than 1 hour ago
        session_unset();     // Unset $_SESSION variable for the run-time 
        session_destroy();   // Destroy session data in storage
        header("Location: /google-login/logout.php");
        exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time(); // Update last activity time stamp
}

checkSessionTimeout();


if (!isset($_SESSION['email']) || !$_SESSION['is_admin']) {
    // Redirect to the home page if the user is not logged in or not an admin
    header('Location: /index.php');
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


// Get user email from session
$user_email = $_SESSION['email'];
$user_name = $_SESSION['name'];

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

// Check if user is authorized to view this page
$is_authorized = checkUserAdminLevel($db, $user_email, $page_level);

// Fetch enabled job titles
$jobTitlesQuery = "SELECT id, job_title FROM job_postings WHERE enabled = 1";
$jobTitlesResult = $db->query($jobTitlesQuery);
$jobTitles = [];
if ($jobTitlesResult->num_rows > 0) {
    while ($row = $jobTitlesResult->fetch_assoc()) {
        $jobTitles[] = $row;
    }
}

// Initialize result variable
$result = null;

// Initialize candidates array
$candidates = [];

// Fetch candidates information based on the request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['list_all_candidates'])) {
    // Fetch all candidates
    $query = "
        SELECT cp.name, cp.email, 
        COALESCE(MAX(jp.job_title), 'Candidate not reviewed yet') AS job_title,
        GROUP_CONCAT(CONCAT(cr.interview, '|', cr.approved, '|', cr.interviewer_name) SEPARATOR ',') AS interviews
        FROM candidate_profiles cp
        LEFT JOIN candidate_review cr ON cp.email = cr.email
        LEFT JOIN job_postings jp ON cr.process = jp.id
        WHERE cp.email NOT LIKE '%samanagroup.co%'
        GROUP BY cp.email
        ORDER BY cp.name ASC
    ";
    $result = $db->query($query);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['not_interviewed'])) {
    // Fetch candidates not interviewed yet
    $query = "
        SELECT cp.name, cp.email, 
        'Candidate not reviewed yet' AS job_title,
        NULL AS interviews
        FROM candidate_profiles cp
        WHERE cp.email NOT IN (SELECT DISTINCT email FROM candidate_review)
        AND cp.email NOT LIKE '%samanagroup.co%'
        ORDER BY cp.name ASC
    ";
    $result = $db->query($query);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_posting_id'])) {
    $jobPostingId = intval($_POST['job_posting_id']);

    // Fetch candidates associated with the selected job posting
    $query = "
        SELECT cp.name, cp.email, 
        COALESCE(MAX(jp.job_title), 'Candidate not reviewed yet') AS job_title,
        GROUP_CONCAT(CONCAT(cr.interview, '|', cr.approved, '|', cr.interviewer_name) SEPARATOR ',') AS interviews
        FROM candidate_profiles cp
        JOIN candidate_review cr ON cp.email = cr.email
        JOIN job_postings jp ON cr.process = jp.id
        WHERE cr.process = ? AND cp.email NOT LIKE '%samanagroup.co%'
        GROUP BY cp.email
        ORDER BY cp.name ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $jobPostingId);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Default query to fetch all candidates when the page loads
    $query = "
        SELECT cp.name, cp.email, 
        COALESCE(MAX(jp.job_title), 'Candidate not reviewed yet') AS job_title,
        GROUP_CONCAT(CONCAT(cr.interview, '|', cr.approved, '|', cr.interviewer_name) SEPARATOR ',') AS interviews
        FROM candidate_profiles cp
        LEFT JOIN candidate_review cr ON cp.email = cr.email
        LEFT JOIN job_postings jp ON cr.process = jp.id
        WHERE cp.email NOT LIKE '%samanagroup.co%'
        GROUP BY cp.email
        ORDER BY cp.name ASC
    ";
    $result = $db->query($query);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if (!is_null($row['interviews'])) {
            $row['interviews'] = array_map(function($interview) {
                list($interviewType, $approved, $interviewerName) = explode('|', $interview);
                return ['interview' => $interviewType, 'approved' => $approved, 'interviewer_name' => $interviewerName];
            }, explode(',', $row['interviews']));
        } else {
            $row['interviews'] = [];
        }
        $candidates[] = $row;
    }
}

if (isset($stmt)) {
    $stmt->close();
}



// Function to generate reviewer initials
function generateReviewerInitials($name) {
    if (empty($name)) {
        return '';
    }
    $initials = '';
    $parts = explode(' ', $name);
    foreach ($parts as $part) {
        $initials .= strtoupper($part[0]);
    }
    return $initials;
}

// Fetch candidates' interview details
$interviewDetails = [];

foreach ($candidates as &$candidate) {
    $stmt = $db->prepare("
        SELECT interview, interviewer_name, approved 
        FROM candidate_review 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $candidate['email']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Initialize an empty array to store interview details
    $candidate['interviews'] = [];
    
    while ($row = $result->fetch_assoc()) {
        $candidate['interviews'][] = $row;
    }
    
    // Check if there are no interview records
    if (empty($candidate['interviews'])) {
        $candidate['interviews'][] = [
            'interview' => 'Pending',
            'approved' => '',
            'interviewer_name' => ''
        ];
    }
    
    $stmt->close();
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Candidates List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
    .job-icon {
        font-size: 2em;
        margin-right: 10px;
    }
    .job-category-netscaler {
        color: #ff5733; /* Example color for netscaler */
    }
    .job-category-citrix {
        color: #33c1ff; /* Example color for citrix */
    }
    .job-category-aws {
        color: #ffbb33; /* Example color for aws */
    }
    .job-posting {
        margin-bottom: 20px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        background-color: #fff;
    }
    .job-posting .job-title {
        font-weight: bold;
    }
    .job-posting .post-date {
        font-size: 0.9em;
        color: #888;
    }
    .job-posting .job-details {
        font-size: 1em;
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
    .job-title-bubble {
        display: inline-block;
        padding: 3px 5px;
        margin: 5px;
        border-radius: 50px;
        background-color: #25A2B8;
        color: #fff;
        cursor: pointer;
        border: thick;
        font-size: 0.9em;
    }
    .job-title-bubble:hover {
        background-color: #3BD1FF;
    }
    .rating-bar {
        display: inline-flex;
    }
    .star {
        font-size: 24px;
        color: #ffc107; /* Bootstrap's warning color */
        margin-right: 5px;
    }
    .table td, .table th {
        padding: 5px;
    }
    .text-center {
        text-align: center;
    }
    .bi-check-circle-fill {
        color: #28a745; /* Green color */
    }
    .bi-x-circle-fill {
        color: #dc3545; /* Red color */
    }
    .interview-status td {
        vertical-align: middle; /* Center vertically */
        text-align: center; /* Center horizontally */
    }
    .interview-status .bi-check-circle-fill,
    .interview-status .bi-x-circle-fill {
        font-size: 1.5rem; /* Adjust icon size */
    }
    .interview-table {
        font-size: 0.8em; /* Smaller font size */
        border: none; /* Hide borders */
    }
    .interview-table th, .interview-table td {
        border: none; /* Hide borders */
    }
    .table {
        width: 100%;
    }
    .table-responsive {
        overflow-x: auto;
    }
    /* Other styles */

    .small-text {
        font-size: 1em; /* Default size for larger screens */
    }

    @media (max-width: 767.98px) {
        .small-text {
            font-size: 0.8em; /* Smaller size for mobile screens */
        }
    }
</style>
</style>


	  <script>
        function showSkillset(email) {
            document.getElementById('skillset-container').style.display = 'block';
            document.getElementById('candidate-email').value = email;
        }

        function clearSkillset() {
            document.getElementById('skillset-form').reset();
        }
    </script>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="../images/samana-logo.png" alt="Samana Group Logo" class="logo"><br>
					</div>
                <div class="col">
                    <h3 class='mb-0'>Candidates List</h3>
					 
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

	
	
	
<!-- Authorized Content -->
	
    <div class="container form-container mt-4">
        <?php if ($is_authorized): ?>	
 <!-- Modules Inserted Here -->


	
<!-- Job Titles Section -->

<div id="job-titles" class="mb-4">
    <form method="POST" action="" style="display: inline;">
        <input type="hidden" name="list_all_candidates" value="1">
        <button type="submit" class="job-title-bubble">
            List All Candidates
        </button>
    </form>
    <form method="POST" action="" style="display: inline;">
        <input type="hidden" name="not_interviewed" value="1">
        <button type="submit" class="job-title-bubble">
            Not Interviewed Yet
        </button>
    </form>

</div>



<!-- Candidates List Section -->
<?php if (!empty($candidates)): ?>
    <h4 class="mt-4">Candidates List</h4>
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>Name</th>
                <th>Process</th>
                <th>Interview Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($candidates as $candidate): ?>
                <tr>
					<td class="small-text"><b><?php echo htmlspecialchars($candidate['name']); ?></b><br>
						<?php echo htmlspecialchars($candidate['email']); ?></td>

                    <td class="small-text"><?php echo htmlspecialchars($candidate['job_title']); ?></td>
                    <td style="font-size: small;">
                        <?php if (!empty($candidate['interviews'])): ?>
                            <table class="table table-borderless table-sm">
                           
                                <tbody>
                                    <?php
                                    $interviewTypes = [];
                                    foreach ($candidate['interviews'] as $interview) {
                                        $interviewTypes[$interview['interview']][] = $interview;
                                    }
                                    foreach ($interviewTypes as $type => $interviews): ?>
                                        <tr>
                                            <td>
												<?php 
												$firstWord = explode(' ', $type)[0]; // Get the first word of the interview type
												echo htmlspecialchars($firstWord); 
												?>
											</td>
                                            <td>
                                                <?php foreach ($interviews as $interview): ?>
                                                    <?php if ($interview['approved'] === 'Yes'): ?>
                                                        <i class="fas fa-check-circle text-success"></i>
                                                    <?php elseif ($interview['approved'] === 'No'): ?>
                                                        <i class="fas fa-times-circle text-danger"></i>
                                                    <?php endif; ?>
                                                    <br>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <?php foreach ($interviews as $interview): ?>
                                                    <?php echo generateReviewerInitials($interview['interviewer_name']); ?><br>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Not interviewed yet</p>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <form method="POST" action="candidate_profile.php" style="display:inline;">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($candidate['email']); ?>">
                            <button type="submit" class="btn btn-info btn-sm">Profile</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

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
</body>
</html>
