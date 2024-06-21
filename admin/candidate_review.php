<?php 
session_start();
// Register here the page information
$config = include('../google-login/config.php');
$page_title = "Candidate Interview";
$page_level = 3; // Set the required admin level for this page
$manager_level = 3;
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

// New PHP Modules Here


// Include the database configuration file
$db = new mysqli($config['database']['host'], $config['database']['user'], $config['database']['pass'], $config['database']['db']);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Include PHP Scripts HERE
// Handle form submission

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $process = $_POST['process'] ?? null;
    $salary_expectation = $_POST['salary_expectation'] ?? null;
    $availability = $_POST['availability'] ?? null;
    $interview = $_POST['interview'] ?? null;
    $interviewer_name = $_POST['interviewer_name'] ?? null;
    $interviewer_email = $_POST['interviewer_email'] ?? null;
    $evaluation_field = $_POST['evaluation_field'] ?? null;
    $rating = $_POST['rating'] ?? null;
    $observations = $_POST['observations'] ?? '';
	$observations = htmlspecialchars($observations, ENT_QUOTES, 'UTF-8'); 
    $approved = $_POST['approved'] ?? null;
    $review_date = $_POST['review_date'] ?? null;


	
    $stmt = $db->prepare("INSERT INTO candidate_review (email, process, salary_expectation, availability, interview, interviewer_name, interviewer_email, evaluation_field, rating, observations, approved, review_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssisssssisss', $email, $process, $salary_expectation, $availability, $interview, $interviewer_name, $interviewer_email, $evaluation_field, $rating, $observations, $approved, $review_date);
    if ($stmt->execute()) {
        $success_message = "Review submitted successfully!";
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    $stmt->close();
}


// Fetch job postings with enabled=1
$job_postings_query = "SELECT id, job_title FROM job_postings WHERE enabled=1";
$job_postings_result = $db->query($job_postings_query);
$job_postings = [];
if ($job_postings_result && $job_postings_result->num_rows > 0) {
    while ($row = $job_postings_result->fetch_assoc()) {
        $job_postings[] = $row;
    }
}


// Check if candidate_email is provided via GET
if (!isset($_GET['candidate_email'])) {
    header('Location: candidate_profile.php');
    exit();
}

$candidate_email = $_GET['candidate_email'];
$candidate_data_query = "SELECT salary_expectation, availability, process FROM candidate_review WHERE email = ?";
$stmt = $db->prepare($candidate_data_query);
$stmt->bind_param('s', $candidate_email);
$stmt->execute();
$result = $stmt->get_result();
$candidate_data = $result->fetch_assoc();
$stmt->close();

$process_job_title = null;
if ($candidate_data && $candidate_data['process']) {
    $process_id = $candidate_data['process'];
    $process_query = "SELECT job_title FROM job_postings WHERE id = ?";
    $stmt = $db->prepare($process_query);
    $stmt->bind_param('i', $process_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $process_job_title = $row['job_title'];
    }
    $stmt->close();
}




// Check for existing reviews
$interview_query = "SELECT interview, interviewer_email FROM candidate_review WHERE email = ?";
$stmt = $db->prepare($interview_query);
$stmt->bind_param('s', $candidate_email);
$stmt->execute();
$result = $stmt->get_result();
$existing_interviews = [];
while ($row = $result->fetch_assoc()) {
    $existing_interviews[] = $row;
}
$stmt->close();

$interview_options = ["First Interview", "Second Interview", "Additional Interview"];
$interview_messages = [];
$unique_messages = [];

$has_first_review = false;
$has_second_review_by_interviewer = false;
$has_second_review = false;
$has_additional_review = false;

foreach ($existing_interviews as $interview) {
    if ($interview['interview'] === "First Interview") {
        $has_first_review = true;
        $interview_options = array_diff($interview_options, ["First Interview"]);
    }
    if ($interview['interview'] === "Second Interview") {
        if ($interview['interviewer_email'] === $_SESSION['email']) {
            $has_second_review_by_interviewer = true;
            $interview_options = array_diff($interview_options, ["Second Interview"]);
        }
        $has_second_review = true;
    }
    if ($interview['interview'] === "Additional Interview") {
        $has_additional_review = true;
        $interview_options = array_diff($interview_options, ["Additional Interview"]);
    }
}

if ($has_first_review) {
    $unique_messages['First Review Completed'] = true;
}

if ($has_second_review_by_interviewer) {
    $unique_messages['Second Review Completed'] = true;
}

if ($has_second_review && $has_additional_review) {
    $hide_form = true;
    $unique_messages['All reviews completed for this Candidate'] = true;
} else {
    $hide_form = false;
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

<!-- Job review module -->

<div class="container mt-5 form-container">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php elseif (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php foreach (array_keys($unique_messages) as $message): ?>
        <div class="alert alert-info"><?php echo $message; ?></div>
    <?php endforeach; ?>

    <?php if (!$hide_form): ?>
    <form action="candidate_review.php" method="post">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="email">Candidate Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($candidate_email); ?>" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="process">Process</label>
                    <?php if ($process_job_title): ?>
                        <input type="text" class="form-control" id="process" name="process" value="<?php echo htmlspecialchars($process_job_title); ?>" readonly>
                        <input type="hidden" name="process" value="<?php echo htmlspecialchars($process_id); ?>">
                    <?php else: ?>
                        <select class="form-control" id="process" name="process">
                            <?php foreach ($job_postings as $job): ?>
                                <option value="<?php echo $job['id']; ?>"><?php echo htmlspecialchars($job['job_title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!$candidate_data || !$candidate_data['salary_expectation']): ?>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="salary_expectation">Salary Expectation</label>
                    <input type="number" class="form-control" id="salary_expectation" name="salary_expectation">
                </div>
            </div>
            <?php endif; ?>
            <?php if (!$candidate_data || !$candidate_data['availability']): ?>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="availability">Availability</label>
                    <select class="form-control" id="availability" name="availability">
                        <option value="Immediately">Immediately</option>
                        <option value="Within two weeks">Within two weeks</option>
                        <option value="Within one month">Within one month</option>
                        <option value="More than a month">More than a month</option>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="interview">Interview</label>
                    <select class="form-control" id="interview" name="interview">
                        <?php foreach ($interview_options as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="interviewer_name">Interviewer Name</label>
                    <input type="text" class="form-control" id="interviewer_name" name="interviewer_name" value="<?php echo htmlspecialchars($_SESSION['name']); ?>" readonly>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="interviewer_email">Interviewer Email</label>
                    <input type="email" class="form-control" id="interviewer_email" name="interviewer_email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" readonly>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="evaluation_field">Evaluation Field</label>
                    <select class="form-control" id="evaluation_field" name="evaluation_field">
                        <option value="Initial filter">HR - Technical/Communications Evaluation</option>
                        <option value="Technical Evaluation">CTO - Technical Evaluation</option>
                        <option value="Portfolio Review">SDD - Portfolio Review</option>
                        <option value="Problem-Solving Skills">SDM -Problem-Solving Skills</option>
                        <option value="Communication Skills">SDM -Communication Skills</option>
                        <option value="Alignment with Company Vision">CEO - Alignment with Company Vision</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="rating">Rating</label>
                    <select class="form-control" id="rating" name="rating">
                        <option value="1">1 - Unsatisfactory</option>
                        <option value="2">2 - Below Expectations</option>
                        <option value="3" selected="selected">3 - Meets Expectations</option>
                        <option value="4">4 - Exceeds Expectations</option>
                        <option value="5">5 - Outstanding</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="approved">Pass</label>
                    <select class="form-control" id="approved" name="approved">
                        <option value="Yes">Yes</option>
                        <option value="No">No</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label for="observations">Observations</label>
                    <textarea class="form-control" id="observations" name="observations" rows="4"></textarea>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    <label for="review_date">Review Date</label>
                    <input type="datetime-local" class="form-control" id="review_date" name="review_date" value="<?php echo date('Y-m-d\TH:i'); ?>" readonly>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Submit Review</button>
    </form>
    <?php else: ?>
        <div class="alert alert-info"><?php echo implode('<br>', array_keys($unique_messages)); ?></div>
    <?php endif; ?>
</div>
<!-- End of Modules Here -->
	
	
	
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
