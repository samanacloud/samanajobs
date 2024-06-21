<?php 
session_start();
// Register here the page information
$config = include('../google-login/config.php');
$page_title = "Candidate Profile";
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
$loginUrl = 'https://jobs.samana.cloud/google-login/login.php'; // Add the login URL

// Include the database configuration file
$db = new mysqli($config['database']['host'], $config['database']['user'], $config['database']['pass'], $config['database']['db']);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get candidate email from POST
$email = isset($_POST['email']) ? $_POST['email'] : (isset($_GET['email']) ? $_GET['email'] : '');
if (empty($email)) {
    header('Location: candidate_list.php');
    exit();
}

// Fetch candidate information based on email
$candidateEmail = $email;
$candidate_email = $email;
$candidateInfo = [];
// Fetch candidate information based on email
// Fetch candidate information based on email
$candidateEmail = $email;
$candidate_email = $email;
$candidateInfo = [];

if ($candidateEmail) {
    $stmt = $db->prepare("SELECT * FROM candidate_profiles WHERE email = ?");
    $stmt->bind_param("s", $candidateEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidateInfo = $result->fetch_assoc();
    $stmt->close();
}


// Function to generate the skillset rating bar
function generateRatingBar($rating) {
    $output = '<div class="rating-bar">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $output .= '<i class="bi bi-star-fill star"></i>'; // Filled star
        } else {
            $output .= '<i class="bi bi-star star"></i>'; // Empty star
        }
    }
    $output .= '</div>';
    return $output;
}


// Calculate average skillset rating
if (!empty($candidateSkillset) && isset($candidateSkillset[0])) {
    $skillsetRatings = array_map('intval', array_filter($candidateSkillset[0], function($key) {
        return $key !== 'email' && $key !== 'last_modified';
    }, ARRAY_FILTER_USE_KEY));

    $averageRating = !empty($skillsetRatings) ? array_sum($skillsetRatings) / count($skillsetRatings) : 0;
    $averageRating = round($averageRating); // Round to the nearest whole number
} else {
    $averageRating = 0; // Default to 0 if there are no skillset records
}


// Fetch candidate certifications based on email
$candidateCertifications = [];

if ($candidateEmail) {
    $stmt = $db->prepare("SELECT certification FROM candidate_certifications WHERE email = ?");
    $stmt->bind_param("s", $candidateEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $candidateCertifications[] = $row['certification'];
    }
    $stmt->close();
}


// Modules Interview Review
$firstInterviewReviews = [];
$secondInterviewReviews = [];
$additionalInterviewReviews = [];

if ($candidateEmail) {
    $stmt = $db->prepare("SELECT * FROM candidate_review WHERE email = ? AND interview = ?");
    
    // Fetch First Interview reviews
    $interviewType = "First Interview";
    $stmt->bind_param("ss", $candidateEmail, $interviewType);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $firstInterviewReviews[] = $row;
    }
    
    // Fetch Second Interview reviews
    $interviewType = "Second Interview";
    $stmt->bind_param("ss", $candidateEmail, $interviewType);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $secondInterviewReviews[] = $row;
    }
    
    // Fetch Additional Interview reviews
    $interviewType = "Additional Interview";
    $stmt->bind_param("ss", $candidateEmail, $interviewType);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $additionalInterviewReviews[] = $row;
    }
    
    $stmt->close();
}

//GET The process Name
function getProcessName($processId, $db) {
    $stmt = $db->prepare("SELECT job_title FROM job_postings WHERE id = ?");
    $stmt->bind_param("i", $processId);
    $stmt->execute();
    $stmt->bind_result($jobTitle);
    $stmt->fetch();
    $stmt->close();
    return $jobTitle;
}


// Fetch First Interview data
$first_interview_data_query = "SELECT process, salary_expectation, availability FROM candidate_review WHERE email = ? AND interview = 'First Interview'";
$stmt = $db->prepare($first_interview_data_query);
$stmt->bind_param('s', $candidateEmail);
$stmt->execute();
$result = $stmt->get_result();
$first_interview_data = $result->fetch_assoc();
$stmt->close();


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







// Check admin status of the logged-in user
$admin_status_query = "SELECT admin FROM users WHERE email = ?";
$stmt = $db->prepare($admin_status_query);
if ($stmt === false) {
    die("Failed to prepare statement: " . $db->error);
}
$stmt->bind_param('s', $_SESSION['email']);
$stmt->execute();
$stmt->bind_result($admin_level);
$stmt->fetch();
$stmt->close();

// Debugging admin status
 $admin_level; // Temporary debugging statement
//Convert the Process ID in Job_title
if ($first_interview_data && $first_interview_data['process']) {
    $process_id = $first_interview_data['process'];
    $process_query = "SELECT job_title FROM job_postings WHERE id = ?";
    $stmt = $db->prepare($process_query);
    $stmt->bind_param('i', $process_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $first_interview_data['process'] = $row['job_title'];
    }
    $stmt->close();
}

//Convert the Salary expectation in Currency Format
if (isset($first_interview_data['salary_expectation'])) {
    $formatted_salary = number_format($first_interview_data['salary_expectation'], 0, '.', ',');
} else {
    // Handle the case where salary_expectation is not set
    $formatted_salary = "N/A"; // Or some other default value
}

// Function to generate reviewer initials
function generateReviewerInitials($name) {
    return implode('', array_map(function($part) {
        return strtoupper($part[0]);
    }, explode(' ', $name)));
}

// Fetch candidate skillsets information based on email
$candidateSkillsets = [];
$reviewerInitials = [];

if ($candidateEmail) {
    $stmt = $db->prepare("SELECT cs.*, u.name as reviewer_name 
                          FROM candidate_skillsets cs 
                          LEFT JOIN users u ON cs.reviewer_id = u.id 
                          WHERE cs.email = ? 
                          ORDER BY cs.category, cs.skillset");
    $stmt->bind_param("s", $candidateEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $category = $row['category'];
        $skillset = $row['skillset'];
        if (!isset($candidateSkillsets[$category])) {
            $candidateSkillsets[$category] = [];
        }
        if (!isset($candidateSkillsets[$category][$skillset])) {
            $candidateSkillsets[$category][$skillset] = [];
        }
        $row['reviewer_initials'] = generateReviewerInitials($row['reviewer_name']); // Add initials to row
        $candidateSkillsets[$category][$skillset][] = $row;
    }
    $stmt->close();
}


// Start PHP Functions Here

// Function to fetch candidate's CV link
function getCandidateCV($email, $db) {
    $stmt = $db->prepare("SELECT candidate_cv FROM candidate_profiles WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($candidateCV);
    $stmt->fetch();
    $stmt->close();
    return $candidateCV;
}

// Fetch candidate CV link
$candidateCV = getCandidateCV($candidateEmail, $db);

// Convert Google Drive link to embed link if necessary
function convertGoogleDriveLink($link) {
    if (strpos($link, 'drive.google.com/open?id=') !== false) {
        $fileId = explode('=', $link)[1];
        return "https://drive.google.com/file/d/$fileId/preview";
    }
    return $link;
}

$candidateCV = convertGoogleDriveLink($candidateCV);

// Send Whatsapp Message to Candidate
$message = 'Hello ' . $candidateInfo['name'] . ', Good afternoon. My name is ' . htmlspecialchars($_SESSION['name']) . ' and I am writing from Samana Group. You recently applied for a position we opened for Service Desk Engineer, and I would like to know if you have a few minutes for a short call to ask you a few questions about your resume.';


// Create a Google Calendar Invitation with the Applicant Information

$interviewerName = ''; // Initialize the interviewer's name variable
if (!empty($firstInterviewReviews)) {
    // Assuming the first review contains the interviewer's name
    $interviewerName = $firstInterviewReviews[0]['interviewer_name'];
}

$calendarUrl = "https://calendar.google.com/calendar/r/eventedit?";
$calendarUrl .= "text=" . $first_interview_data['process'] . " - " . urlencode($candidateInfo['name']);

$description = "
Hello " . $candidateInfo['name'] . ",<br><br>
<a href='$candidateCV'>CV</a><br><br>
<a href='https://jobs.samana.cloud/admin/candidate_profile.php?email=$email'>Candidate Profile</a><br><br>
Thank you for your application for the " . $first_interview_data['process'] . " position. As discussed in our recent conversation, your skills and experience align well with the requirements of this role. The next step in our recruitment process is a video interview with the rest of the team.<br><br>
Please feel free to contact us if you have any questions.<br><br>
Best regards,<br><br>" . $_SESSION['name'] . "<br>" . $_SESSION['email'] . "<br>Samana Group LLC";

$calendarUrl .= "&details=" . urlencode($description);
$calendarUrl .= "&location=Online"; // Modify the location if necessary
$calendarUrl .= "&trp=false";
$calendarUrl .= "&sprop=" . urlencode("recruiting@samanagroup.co");
$calendarUrl .= "&add=" . urlencode($email) . "," . urlencode($_SESSION['email']) . ",recruiting@samanagroup.co";
											  

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
		.review-section {
			align-content:     margin-top: 20px;
		}
		.table td, .table th {
			padding: 5px;
		}
  .category-section {
        margin-bottom: 1.5rem;
    }
    .category-title {
        font-weight: bold;
        font-size: 1rem; /* Smaller font size for the category */
        margin-bottom: 0.5rem;
    }
    .skillset-section {
        margin-left: 0.5rem; /* Indentation for skillsets */
    }
    .skillset-title {
        font-size: 1rem; /* Normal font size for skillsets */
        margin-bottom: 0.2rem;
    }
    .rating-section {
        display: flex;
        align-items: center;
        margin-bottom: 0.2rem;
    }
    .rating-bar {
        display: flex;
        align-items: center;
    }
    .star {
        font-size: 1rem; /* Adjust the size of the stars */
        color: #ffc107; /* Bootstrap's warning color */
        margin-right: 0.1rem; /* Space between stars */
    }
    .rating-bar small {
        margin-left: 0.5rem; /* Space between stars and initials */
    }
    .ratings {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }
		.bi-whatsapp {
    margin-left: 5px; /* Adjust the space between the phone number and the icon */
}
.bi-calendar {
    margin-left: 5px; /* Adjust the space between the Certifications button and the icon */
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

	
	
 <!-- Modules Candidate Profile and Interview -->
<div class="container mt-4">
    <div class="row">
        <div class="col-md-6">
            <div class="form-container">
                <h2>Candidate Profile</h2>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($candidateInfo['name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($candidateInfo['email']); ?></p>
				<p><strong>Phone Number:</strong>     <a href="https://web.whatsapp.com/send?phone=<?php echo htmlspecialchars($candidateInfo['phone_number']); ?>&text=<?php echo urlencode($message); ?>" target="_blank">        <?php echo htmlspecialchars($candidateInfo['phone_number']); ?>  <i class="bi bi-whatsapp" style="font-size: 1.5rem; color: #25D366;"></i>    </a></p>
                <p><strong>Location:</strong> <?php echo htmlspecialchars($candidateInfo['location']); ?></p>
                <p><strong>English Level:</strong> <?php echo htmlspecialchars($candidateInfo['english_level']); ?></p>
                <p><strong>Created At:</strong> <?php echo htmlspecialchars($candidateInfo['created_at']); ?></p>
				<div class="btn-group">
					<form action="candidate_review.php" method="GET" class="d-flex">
						
						<input type="hidden" name="candidate_email" value="<?php echo htmlspecialchars($candidateEmail); ?>">
						<button type="submit" class="btn btn-success">Interview</button>
						
						</form>	
					
				
					
					<form action="candidate_skillsets.php" method="POST" class="d-flex">
						
						<input type="hidden" name="candidate_email" value="<?php echo htmlspecialchars($candidateEmail); ?>">
						<input type="hidden" name="email" value="<?php echo htmlspecialchars($candidateEmail); ?>">
						<button type="submit" class="btn btn-warning">Skillsets</button>
						</form>	
					
					    <form action="user_certifications.php" method="GET" class="d-flex">
						<input type="hidden" name="candidate_email" value="<?php echo htmlspecialchars($candidateEmail); ?>">
						<button type="submit" class="btn btn-info">Certifications</button>
					
						</form>
					<a href="<?php echo $calendarUrl; ?>" target="_blank" class="ml-2">
						<i class="bi bi-calendar" style="font-size: 1.5rem; color: #4285F4;"></i>
					</a>
					
					 
				</div>	
            </div>
			<?php if ($admin_level > $manager_level && $first_interview_data): ?>
			<div class="form-container">
				<h2>Interview Details</h2>
				<p><strong>Process:</strong> <?php echo htmlspecialchars($first_interview_data['process']); ?></p>
				 <p><strong>Monthly Salary Expectation :</strong> <?php echo '$' . number_format($first_interview_data['salary_expectation'], 2) . " USD"; ?></p>
				<p><strong>Availability:</strong> <?php echo htmlspecialchars($first_interview_data['availability']); ?></p>
			</div>
			<?php endif; ?>

		
        </div>
 
		
		
        <div class="col-md-6 review-section">
             <div class="form-container">
        <h2>Interview Review</h2>
        
        <!-- First Interview Section -->
        <h3>First Interview</h3>
        <?php if (!empty($firstInterviewReviews)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Evaluation Field</th>
                        <th class="text-center">Rating</th>
                        <th class="text-center">Approved</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($firstInterviewReviews as $review): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($review['evaluation_field']); ?></td>
                        <td class="text-center">
							<div class="d-flex justify-content-center"><?php echo generateRatingBar($review['rating']); ?></div>
						</td>
                        <td class="text-center">
							<?php if ($review['approved'] === 'Yes'): ?>
							<i class="bi bi-check-circle-fill text-success"></i>
							<?php else: ?>
							<i class="bi bi-x-circle-fill text-danger"></i>
							<?php endif; ?>
						</td>

                    </tr>
                    <tr>
                        <td colspan="3">
                            <strong>Observations:</strong> <?php echo htmlspecialchars($review['observations']); ?><br>
                            <small><strong>Interviewer Name:</strong> <?php echo htmlspecialchars($review['interviewer_name']); ?>, <strong>Review Date:</strong> <?php echo htmlspecialchars($review['review_date']); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No First Interview records found for this candidate.</p>
        <?php endif; ?>
        
        <!-- Second Interview Section -->
        <h3>Second Interview</h3>
        <?php if (!empty($secondInterviewReviews)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Evaluation Field</th>
                        <th class="text-center">Rating</th>
                        <th class="text-center">Approved</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($secondInterviewReviews as $review): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($review['evaluation_field']); ?></td>
                        <td class="text-center">
							<div class="d-flex justify-content-center"><?php echo generateRatingBar($review['rating']); ?></div>
						</td>
                        <td class="text-center">
							<?php if ($review['approved'] === 'Yes'): ?>
							<i class="bi bi-check-circle-fill text-success"></i>
							<?php else: ?>
							<i class="bi bi-x-circle-fill text-danger"></i>
							<?php endif; ?>
						</td>

                    </tr>
                    <tr>
                        <td colspan="3">
                            <strong>Observations:</strong> <?php echo htmlspecialchars($review['observations']); ?><br>
                            <small><strong>Interviewer Name:</strong> <?php echo htmlspecialchars($review['interviewer_name']); ?>, <strong>Review Date:</strong> <?php echo htmlspecialchars($review['review_date']); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No Second Interview records found for this candidate.</p>
        <?php endif; ?>
        
        <!-- Additional Interview Section -->
        <h3>Additional Interview</h3>
        <?php if (!empty($additionalInterviewReviews)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Evaluation Field</th>
                        <th class="text-center">Rating</th>
                        <th class="text-center">Approved</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($additionalInterviewReviews as $review): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($review['evaluation_field']); ?></td>
                        <td class="text-center">
							<div class="d-flex justify-content-center"><?php echo generateRatingBar($review['rating']); ?></div>
						</td>
                        <td class="text-center">
							<?php if ($review['approved'] === 'Yes'): ?>
							<i class="bi bi-check-circle-fill text-success"></i>
							<?php else: ?>
							<i class="bi bi-x-circle-fill text-danger"></i>
							<?php endif; ?>
						</td>

                    </tr>
                    <tr>
                        <td colspan="3">
                            <strong>Observations:</strong> <?php echo htmlspecialchars($review['observations']); ?><br>
                            <small><strong>Interviewer Name:</strong> <?php echo htmlspecialchars($review['interviewer_name']); ?>, <strong>Review Date:</strong> <?php echo htmlspecialchars($review['review_date']); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No Additional Interview records found for this candidate.</p>
        <?php endif; ?>
    </div>
        </div>
    </div>
</div>

	
	

	
	
<!-- Candidate Skillsets Module -->

	
<!-- Candidate Skillsets Module -->
<div class="container mt-4">
    <div class="form-container">
        <h2>Candidate Skillsets</h2>
        <?php if (!empty($candidateSkillsets)): ?>
            <?php foreach ($candidateSkillsets as $category => $skills): ?>
                <div class="category-section mb-3">
                    <div class="category-title"><strong><?php echo htmlspecialchars($category); ?></strong></div>
                    <div class="row">
                        <?php $counter = 0; ?>
                        <?php foreach ($skills as $skillset => $reviews): ?>
                            <div class="col-md-6">
                                <div class="skillset-section d-flex justify-content-between align-items-center">
                                    <div class="skillset-title"><?php echo htmlspecialchars($skillset); ?></div>
                                    <div class="ratings">
                                        <?php foreach ($reviews as $review): ?>
                                            <div class="rating-section d-flex align-items-center mb-1">
                                                <div class="rating-bar" style="display: flex; align-items: center;">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi star <?php echo ($i <= $review['rating']) ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <small class="ml-2"><?php echo htmlspecialchars($review['reviewer_initials']); ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $counter++;
                            if ($counter % 2 == 0) echo '</div><div class="row">';
                            ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No skillsets rated for this candidate.</p>
        <?php endif; ?>
    </div>
</div>
	
	
<!-- End of Modules Here -->

	
	
	

<!-- Certifications Module -->
<div class="container mt-4">
    <div class="form-container">
        <h2>Certifications</h2>
        <?php if (!empty($candidateCertifications)): ?>
            <div class="row">
                <?php
                $counter = 0;
                foreach ($candidateCertifications as $certification):
                    if ($counter % 2 == 0 && $counter > 0): ?>
                        </div><div class="row">
                    <?php endif; ?>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><?php echo htmlspecialchars($certification); ?></div>
                        </div>
                    </div>
                    <?php
                    $counter++;
                endforeach; ?>
            </div>
        <?php else: ?>
            <p>No certifications registered</p>
        <?php endif; ?>
    </div>
</div>

	
<!-- Candidate CV Module -->
<?php if (!empty($candidateCV)): ?>
<div class="container mt-4">
    <div class="form-container">
        <h2>Candidate CV</h2>
        <div class="embed-responsive embed-responsive-16by9">
            <iframe class="embed-responsive-item" src="<?php echo htmlspecialchars($candidateCV); ?>" allowfullscreen></iframe>
        </div>
    </div>
</div>
<?php endif; ?>
	
	
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
