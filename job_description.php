<?php
session_start();
// Register here the page information
$page_title = "Job Description";

function checkSessionTimeout() {
    $timeout_duration = 6000; // 10 minutes in seconds

    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
        // Last request was more than 1 hour ago
        session_unset();     // Unset $_SESSION variable for the run-time 
        session_destroy();   // Destroy session data in storage
        header("Location: google-login/logout.php");
        exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time(); // Update last activity time stamp
}

checkSessionTimeout();

if (!isset($_SESSION['email'])) {
    // Redirect to the home page if the user is not logged in or not an admin
    header('Location: index.php');
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['email']);
$name = $isLoggedIn ? $_SESSION['name'] : '';
$logoutUrl = 'https://jobs.samana.cloud/google-login/logout.php';

// Include the database configuration file
$config = include('google-login/config.php');
$db = new mysqli($config['database']['host'], $config['database']['user'], $config['database']['pass'], $config['database']['db']);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$job_details = null;
$error_message = null;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error_message = "Job not found, try again.";
} else {
    $job_id = $_GET['id'];

    $sql = "SELECT job_category, job_title, job_type, job_details, post_date, linkedin_url, workplace_type, role_description, responsibilities, preferred_certifications, qualifications, salary_range FROM job_postings WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $job_details = $result->fetch_assoc();
    } else {
        $error_message = "Job not found, try again.";
    }
    $stmt->close();
}

function formatListItems($text) {
    $lines = explode("\n", $text);
    $formattedText = '';
    $inList = false;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        if (preg_match('/^[0-9]+\./', $trimmedLine) || preg_match('/^(\*|\-)/', $trimmedLine)) {
            if (!$inList) {
                $formattedText .= '<ul>';
                $inList = true;
            }
            $formattedLine = preg_replace('/^(\*|\-|\d+\.)/', '', $trimmedLine); // Remove leading list indicators
            $formattedText .= '<li>' . htmlspecialchars($formattedLine) . '</li>';
        } else {
            if ($inList) {
                $formattedText .= '</ul>';
                $inList = false;
            }
            $formattedText .= '<p>' . nl2br(htmlspecialchars($trimmedLine)) . '</p>';
        }
    }

    if ($inList) {
        $formattedText .= '</ul>';
    }

    return $formattedText;
}
$db->close();
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
        .btn-menu {
            margin: 5px;
        }
        @media (max-width: 767.98px) {
            .header .logo {
                max-width: 100px;
            }
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

 <!-- Modules Inserted Here -->


<div class="container mt-4">
    <div class="form-container">
        <h2><?php echo htmlspecialchars($job_details['job_title']); ?></h2>
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Category</h5>
                        <p class="card-text"><?php echo htmlspecialchars($job_details['job_category']); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Job Type</h5>
                        <p class="card-text"><?php echo htmlspecialchars($job_details['job_type']); ?></p>
                    </div>
                </div>
				<div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Employment Type</h5>
                        <p class="card-text"><?php echo htmlspecialchars($job_details['workplace_type']); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Salary Range</h5>
                        <p class="card-text"><?php echo htmlspecialchars($job_details['salary_range']); ?></p>
                    </div>
                </div>
				<div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Qualifications</h5>
                        <div class="card-text"><?php echo formatListItems($job_details['qualifications']); ?></div>
                    </div>
                </div>
				<div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Preferred Certifications</h5>
                        <div class="card-text"><?php echo formatListItems($job_details['preferred_certifications']); ?></div>
                    </div>
                </div>
        
            </div>
            <div class="col-md-6 mb-3">

				<div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Details</h5>
                        <div class="card-text"><?php echo formatListItems($job_details['job_details']); ?></div>
                    </div>
                </div>
				<div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Role Description</h5>
                        <div class="card-text"><?php echo formatListItems($job_details['role_description']); ?></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Responsibilities</h5>
                        <div class="card-text"><?php echo formatListItems($job_details['responsibilities']); ?></div>
                    </div>
                </div>
				<div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Post Date</h5>
                        <p class="card-text"><?php echo htmlspecialchars($job_details['post_date']); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">LinkedIn URL</h5>
                        <p class="card-text"><a href="<?php echo htmlspecialchars($job_details['linkedin_url']); ?>" target="_blank">View on LinkedIn</a></p>
                    </div>
                </div>  
            </div>
        </div>
               <button class="btn btn-primary mt-3" onclick="window.open('https://docs.google.com/forms/d/e/1FAIpQLSd9pgQ9fPMbh3vsbXZ8VseTjZFT7fI9wh363BosTbUwhGwHwg/viewform', '_blank')">Apply Now</button>
		                         
        <button class="btn btn-secondary mt-3" onclick="shareJob()">Share</button>
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
	<script>
function shareJob() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo htmlspecialchars($job_details['job_title']); ?>',
            text: 'Check out this job opportunity at Samana Group: <?php echo htmlspecialchars($job_details['job_title']); ?>',
            url: window.location.href
        }).then(() => {
            console.log('Thanks for sharing!');
        }).catch(console.error);
    } else {
        alert('Your browser does not support the Web Share API. Please copy the URL manually.');
    }
}
</script>
</body>
</html>

