<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
$config = include( '../google-login/config.php' );
$page_title = "Candidates List";
$page_level = 3; // Set the required admin level for this page
$manager_level = 6;

// Load the menu
$modules = load_menu();


function checkSessionTimeout() {
  $timeout_duration = 6000; // 10 minutes in seconds

  if ( isset( $_SESSION[ 'LAST_ACTIVITY' ] ) && ( time() - $_SESSION[ 'LAST_ACTIVITY' ] ) > $timeout_duration ) {
    // Last request was more than 1 hour ago
    session_unset(); // Unset $_SESSION variable for the run-time 
    session_destroy(); // Destroy session data in storage
    header( "Location: /google-login/logout.php" );
    exit();
  }
  $_SESSION[ 'LAST_ACTIVITY' ] = time(); // Update last activity time stamp
}

checkSessionTimeout();


if ( !isset( $_SESSION[ 'email' ] ) || !$_SESSION[ 'is_admin' ] ) {
  // Redirect to the home page if the user is not logged in or not an admin
  header( 'Location: /index.php' );
  exit();
}

// Check if user is logged in
$isLoggedIn = isset( $_SESSION[ 'email' ] );
$name = $isLoggedIn ? $_SESSION[ 'name' ] : '';
$logoutUrl = 'https://jobs.samana.cloud/google-login/logout.php';




// Include the database configuration file

$db = new mysqli( $config[ 'database' ][ 'host' ], $config[ 'database' ][ 'user' ], $config[ 'database' ][ 'pass' ], $config[ 'database' ][ 'db' ] );

if ( $db->connect_error ) {
  die( "Connection failed: " . $db->connect_error );
}


// Get user email from session
$user_email = $_SESSION[ 'email' ];
$user_name = $_SESSION[ 'name' ];

// Function to check user's admin level
function checkUserAdminLevel( $db, $email, $required_level ) {
  $stmt = $db->prepare( "SELECT admin FROM users WHERE email = ?" );
  if ( $stmt ) {
    $stmt->bind_param( "s", $email );
    $stmt->execute();
    $stmt->bind_result( $admin_level );
    $stmt->fetch();
    $stmt->close();
    return $admin_level >= $required_level;
  }
  return false;
}

// Check if user is authorized to view this page
$is_authorized = checkUserAdminLevel( $db, $user_email, $page_level );


// Function to generate reviewer initials
function generateReviewerInitials( $name ) {
  if ( empty( $name ) ) {
    return '';
  }
  $initials = '';
  $parts = explode( ' ', $name );
  foreach ( $parts as $part ) {
    $initials .= strtoupper( $part[ 0 ] );
  }
  return $initials;
}

// Check if user is a manager
$isManager = checkUserAdminLevel($db, $user_email, $manager_level);

// Start PHP Code Here



// Function to fetch candidate profiles based on active status
function fetchCandidateReviews($db, $email) {
    $query = "SELECT process, interviewer_name, review_date, approved, interview FROM candidate_review WHERE email = ? ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Fetch the role for the first interview
    $roleQuery = "SELECT process FROM candidate_review WHERE email = ? AND interview = 'First Interview' LIMIT 1";
    $stmt = $db->prepare($roleQuery);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($process_id);
    $stmt->fetch();
    $stmt->close();
    
    // Fetch the job title from job_postings table
    $role = null;
    if (!empty($process_id)) {
        $roleQuery = "SELECT job_title FROM job_postings WHERE id = ?";
        $stmt = $db->prepare($roleQuery);
        $stmt->bind_param("i", $process_id);
        $stmt->execute();
        $stmt->bind_result($role);
        $stmt->fetch();
        $stmt->close();
    }
    
    return ['reviews' => $reviews, 'role' => $role];
}

function fetchCandidateProfiles($db, $active) {
    $query = "SELECT cp.id, cp.name, cp.email, cp.phone_number, cp.location, cp.english_level, cp.profile_photo, cp.candidate_cv, cp.created_at, cp.enabled, u.profile_image 
              FROM candidate_profiles cp
              LEFT JOIN users u ON cp.email = u.email
              WHERE cp.enabled = ?
              ORDER BY cp.name ASC";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $active);
    $stmt->execute();
    $result = $stmt->get_result();
    $profiles = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch review details for each candidate
    foreach ($profiles as &$profile) {
        $reviewData = fetchCandidateReviews($db, $profile['email']);
        $profile['reviews'] = $reviewData['reviews'];
        $profile['role'] = $reviewData['role'];
    }

    return $profiles;
}


// Fetch active and inactive candidates
$activeCandidates = fetchCandidateProfiles($db, 1);
$inactiveCandidates = fetchCandidateProfiles($db, 0);





// Handle AJAX request for updating candidate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_candidate') {
    header('Content-Type: application/json'); // Ensure the response is JSON
    $response = array('success' => false);

    // Validate required fields
    if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['email']) || empty($_POST['phone_number']) || empty($_POST['location']) || empty($_POST['english_level'])) {
        $response['message'] = 'All fields are required.';
        echo json_encode($response);
        error_log(json_encode($response)); // Log the response
        exit();
    }

    $id = $_POST['id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $location = $_POST['location'];
    $english_level = $_POST['english_level'];
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $profile_photo = isset($_POST['profile_photo']) ? $_POST['profile_photo'] : '';

    // Check if profile_photo contains a valid URL
    if (!filter_var($profile_photo, FILTER_VALIDATE_URL)) {
        $stmt = $db->prepare("SELECT google_id, profile_image FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($google_id, $profile_image);
        $stmt->fetch();
        $stmt->close();

        if (!empty($profile_image)) {
            $profile_photo = $profile_image;
        } else if (!empty($google_id)) {
            // Fetch profile image from Google using the google_id
            $googleProfileUrl = "https://www.googleapis.com/oauth2/v3/userinfo?alt=json&id_token={$google_id}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $googleProfileUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            curl_close($ch);
            $googleProfile = json_decode($result, true);

            if (isset($googleProfile['picture'])) {
                $profile_photo = $googleProfile['picture'];
            }
        }
    }

    $stmt = $db->prepare("UPDATE candidate_profiles SET name = ?, email = ?, phone_number = ?, location = ?, english_level = ?, enabled = ?, profile_photo = ? WHERE id = ?");
    if ($stmt === false) {
        $response['message'] = 'Prepare statement failed: ' . $db->error;
        echo json_encode($response);
        error_log('Prepare statement failed: ' . $db->error);
        exit();
    }
    
    $stmt->bind_param("ssssssii", $name, $email, $phone_number, $location, $english_level, $enabled, $profile_photo, $id);
    if (!$stmt->execute()) {
        $response['message'] = 'Execute statement failed: ' . $stmt->error;
        echo json_encode($response);
        error_log('Execute statement failed: ' . $stmt->error);
        $stmt->close();
        exit();
    }

    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['name'] = $name;
        $response['email'] = $email;
        $response['phone_number'] = $phone_number;
        $response['location'] = $location;
        $response['english_level'] = $english_level;
        $response['enabled'] = $enabled;
        $response['profile_photo'] = $profile_photo;
    } else {
        $response['message'] = 'No rows updated.';
    }

    $stmt->close();
    echo json_encode($response);
    error_log(json_encode($response)); // Log the response
    exit();
}


function convertGoogleDriveLink($link) {
    if (strpos($link, 'drive.google.com/file/d/') !== false) {
        $fileId = explode('/', $link)[5];
        return "https://drive.google.com/file/d/$fileId/preview";
    } elseif (strpos($link, 'drive.google.com/open?id=') !== false) {
        $fileId = explode('=', $link)[1];
        return "https://drive.google.com/file/d/$fileId/preview";
    }
    return $link;
}

$candidateCV = '';
if (!empty($candidate) && isset($candidate['candidate_cv'])) {
    $candidateCV = convertGoogleDriveLink($candidate['candidate_cv']);
}



?>

<!DOCTYPE html>
<html>
<head>
<title><?php echo $page_title; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css">
<style>.header, .footer {
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

/* Custom CSS starts Here */
.candidate-card {
    display: block;
    width: 100%;
    margin-bottom: 20px;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    background-color: #fff;
    box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
    transition: background-color 0.3s, transform 0.3s;
}

.candidate-card:hover {
    background-color: #e0f7fa;
    transform: scale(1.02);
}

.card-title {
    font-weight: bold;
    font-size: 1.2em; /* Adjust font size */
}

.card-text {
    margin-bottom: 5px;
    font-size: 1em; /* Adjust font size */
}

.edit-icon {
    position: absolute;
    top: 10px;
    right: 10px;
    color: #007bff;
    font-size: 1.2em;
    cursor: pointer;
    z-index: 1;
}

.edit-icon:hover {
    color: #0056b3;
}

.edit-form {
    display: none;
}

.d-flex {
    display: flex;
    align-items: center;
}

.rounded-circle {
    border: 2px solid #ccc;
}

/* Add custom styles for the profile image */
.rounded-circle {
    border: 2px solid #ccc;
}
.reviews h6 {
    font-weight: bold;
    margin-top: 10px;
}

.reviews ul {
    padding-left: 20px;
    list-style-type: none;
}

.reviews ul li {
    margin-bottom: 10px;
}

.reviews ul li p {
    margin-bottom: 0;
}

.review-column {
    border-left: 1px solid #ccc;
    padding-left: 10px;
    max-width: 90%;
    font-size: 0.8em; /* Adjust font size */
}
.table-sm {
    width: 90%;
    font-size: 0.8em; /* Adjust font size */
}
.table-sm thead th, .table-sm tbody td {
    padding: 4px;
    text-align: left;
}
.table-sm .text-center {
    text-align: center;
}
.bi-check-circle-fill {
    color: green;
}
.bi-x-circle-fill {
    color: red;
}

.cv-iframe {
    margin-top: 10px;
}
	
	
	
</style>
	
	</head>
<body>
<header class="header">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-auto"> <img src="../images/samana-logo.png" alt="Samana Group Logo" class="logo"><br>
      </div>
      <div class="col">
        <h3 class='mb-0'>Candidates List</h3>
      </div>
      <div class="col text-right">
        <?php if ($isLoggedIn): ?>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</span> <a href="<?php echo $logoutUrl; ?>" class="btn btn-danger ml-2">Logout</a>
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
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation"> <span class="navbar-toggler-icon"></span> </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav">
        <?php foreach ($modules as $section => $items): ?>
        <li class="nav-item dropdown"> <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown_<?php echo htmlspecialchars($section); ?>" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"> <?php echo htmlspecialchars($section); ?> </a>
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
	
    <!-- Modules Inserted Here --> 
  <h3>Active Candidates</h3>
<div class="row">
    <?php foreach ($activeCandidates as $candidate): ?>
<div class="col-12">
    <div class="card mb-4 candidate-card" id="candidate-card-<?php echo $candidate['id']; ?>">
        <?php if ($isManager): ?>
            <a href="javascript:void(0);" class="edit-icon" onclick="showEditForm(<?php echo $candidate['id']; ?>)">
                <i class="fas fa-edit"></i>
            </a>
        <?php endif; ?>
        <div class="row no-gutters">
            <div class="col-md-6">
                <div class="card-body display-form">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($candidate['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($candidate['profile_image']); ?>" class="rounded-circle mr-3" alt="Profile Image" style="width: 50px; height: 50px;">
                        <?php endif; ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($candidate['name']); ?></h5>
                    </div>
                    <p class="card-text" data-type="email"><strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?></p>
                    <p class="card-text" data-type="phone"><strong>Phone:</strong> <?php echo htmlspecialchars($candidate['phone_number']); ?></p>
                    <p class="card-text" data-type="location"><strong>Location:</strong> <?php echo htmlspecialchars($candidate['location']); ?></p>
                    <p class="card-text" data-type="english_level"><strong>English Level:</strong> <?php echo htmlspecialchars($candidate['english_level']); ?></p>
                    <p class="card-text" data-type="enabled"><strong>Enabled:</strong> <?php echo $candidate['enabled'] ? 'Yes' : 'No'; ?></p>
					<p class="card-text" data-type="cv"><strong>CV:</strong> <a href="<?php echo htmlspecialchars($candidate['candidate_cv']); ?>" target="_blank">View CV</a></p>

                   <button class="btn btn-primary" onclick="toggleCV('<?php echo 'cv-' . $candidate['id']; ?>', '<?php echo htmlspecialchars($candidate['candidate_cv']); ?>')">View CV</button>


                    <form action="candidate_profile.php" method="post" style="display:inline;">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($candidate['email']); ?>">
                        <button type="submit" class="btn btn-secondary">Profile</button>
                    </form>
                </div>
                <div class="card-body edit-form" style="display:none;">
                    <form onsubmit="updateCandidate(event, <?php echo $candidate['id']; ?>)">
                        <input type="hidden" name="id" value="<?php echo $candidate['id']; ?>">
                        <label>Name:</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($candidate['name']); ?>"><br>
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($candidate['email']); ?>"><br>
                        <label>Phone Number:</label>
                        <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($candidate['phone_number']); ?>"><br>
                        <label>Location:</label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($candidate['location']); ?>"><br>
                        <label>English Level:</label>
                        <input type="text" name="english_level" class="form-control" value="<?php echo htmlspecialchars($candidate['english_level']); ?>"><br>
                        <div class="row align-items-center">
                            <div class="col">
                                <label>Enabled:</label>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enabled" id="enabled-<?php echo $candidate['id']; ?>" <?php echo $candidate['enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enabled-<?php echo $candidate['id']; ?>"></label>
                                </div>
                            </div>
                        </div>
                        <input type="submit" class="btn btn-primary" value="Save">
                        <button type="button" class="btn btn-secondary" onclick="cancelEditForm(<?php echo $candidate['id']; ?>)">Cancel</button>
                    </form>
                </div>
            </div>
            <div class="col-md-6 review-column">
                <div class="card-body">
                    <h6 class="mb-0" style="margin-top: 10px;">Process Progress</h6>
                    <?php if (!empty($candidate['role'])): ?>
                        <p><strong>Role:</strong> <?php echo htmlspecialchars($candidate['role']); ?></p>
                    <?php else: ?>
                        <p>Role: Candidate not reviewed yet</p>
                    <?php endif; ?>
                    <?php if (empty($candidate['reviews'])): ?>
                        <p class="review-status pending">Pending</p>
                    <?php else: ?>
                        <?php 
                        $uniqueInterviews = [];
                        foreach ($candidate['reviews'] as $review) {
                            $interview = explode(' ', $review['interview'])[0];
                            if (!isset($uniqueInterviews[$interview])) {
                                $uniqueInterviews[$interview] = [];
                            }
                            $uniqueInterviews[$interview][] = $review;
                        }
                        ?>
                        <table class="table table-sm mx-auto">
                            <thead>
                                <tr>
                                    <th>Interview</th>
                                    <th>Result</th>
                                    <th>Reviewer</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($uniqueInterviews as $interview => $reviews): ?>
                                    <?php foreach ($reviews as $review): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($interview); ?></td>
                                            <td class="text-center">
                                                <?php if (isset($review['approved']) && $review['approved'] == 'Yes'): ?>
                                                    <i class="bi bi-check-circle-fill" style="color: green;"></i>
                                                <?php elseif (isset($review['approved']) && $review['approved'] == 'No'): ?>
                                                    <i class="bi bi-x-circle-fill" style="color: red;"></i>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(generateReviewerInitials($review['interviewer_name'])); ?></td>
                                            <td><?php echo htmlspecialchars(date('m/d/Y', strtotime($review['review_date']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div id="<?php echo 'cv-' . $candidate['id']; ?>" class="cv-iframe" style="display:none;">
				
                <iframe src="<?php echo htmlspecialchars($candidateCV ?? ''); ?>" width="100%" height="400px"></iframe>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>




</div>



    
   <h3>Inactive Candidates</h3>
<div class="row">
    <?php foreach ($inactiveCandidates as $candidate): ?>
<div class="col-12">
    <div class="card mb-4 candidate-card" id="candidate-card-<?php echo $candidate['id']; ?>">
        <?php if ($isManager): ?>
            <a href="javascript:void(0);" class="edit-icon" onclick="showEditForm(<?php echo $candidate['id']; ?>)">
                <i class="fas fa-edit"></i>
            </a>
        <?php endif; ?>
        <div class="row no-gutters">
            <div class="col-md-6">
                <div class="card-body display-form">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($candidate['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($candidate['profile_image']); ?>" class="rounded-circle mr-3" alt="Profile Image" style="width: 50px; height: 50px;">
                        <?php endif; ?>
                        <h5 class="card-title"><?php echo htmlspecialchars($candidate['name']); ?></h5>
                    </div>
                    <p class="card-text" data-type="email"><strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?></p>
                    <p class="card-text" data-type="phone"><strong>Phone:</strong> <?php echo htmlspecialchars($candidate['phone_number']); ?></p>
                    <p class="card-text" data-type="location"><strong>Location:</strong> <?php echo htmlspecialchars($candidate['location']); ?></p>
                    <p class="card-text" data-type="english_level"><strong>English Level:</strong> <?php echo htmlspecialchars($candidate['english_level']); ?></p>
                    <p class="card-text" data-type="enabled"><strong>Enabled:</strong> <?php echo $candidate['enabled'] ? 'Yes' : 'No'; ?></p>
                    <button class="btn btn-primary" onclick="toggleCV('<?php echo 'cv-' . $candidate['id']; ?>', '<?php echo htmlspecialchars($candidate['candidate_cv']); ?>')">View CV</button>

                    <form action="candidate_profile.php" method="post" style="display:inline;">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($candidate['email']); ?>">
                        <button type="submit" class="btn btn-secondary">Profile</button>
                    </form>
                </div>
                <div class="card-body edit-form" style="display:none;">
                    <form onsubmit="updateCandidate(event, <?php echo $candidate['id']; ?>)">
                        <input type="hidden" name="id" value="<?php echo $candidate['id']; ?>">
                        <label>Name:</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($candidate['name']); ?>"><br>
                        <label>Email:</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($candidate['email']); ?>"><br>
                        <label>Phone Number:</label>
                        <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($candidate['phone_number']); ?>"><br>
                        <label>Location:</label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($candidate['location']); ?>"><br>
                        <label>English Level:</label>
                        <input type="text" name="english_level" class="form-control" value="<?php echo htmlspecialchars($candidate['english_level']); ?>"><br>
                        <div class="row align-items-center">
                            <div class="col">
                                <label>Enabled:</label>
                            </div>
                            <div class="col-auto">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enabled" id="enabled-<?php echo $candidate['id']; ?>" <?php echo $candidate['enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enabled-<?php echo $candidate['id']; ?>"></label>
                                </div>
                            </div>
                        </div>
                        <input type="submit" class="btn btn-primary" value="Save">
                        <button type="button" class="btn btn-secondary" onclick="cancelEditForm(<?php echo $candidate['id']; ?>)">Cancel</button>
                    </form>
                </div>
            </div>
            <div class="col-md-6 review-column">
                <div class="card-body">
                    <h6 class="mb-0" style="margin-top: 10px;">Process Progress</h6>
                    <?php if (!empty($candidate['role'])): ?>
                        <p><strong>Role:</strong> <?php echo htmlspecialchars($candidate['role']); ?></p>
                    <?php else: ?>
                        <p>Role: Candidate not reviewed yet</p>
                    <?php endif; ?>
                    <?php if (empty($candidate['reviews'])): ?>
                        <p class="review-status pending">Pending</p>
                    <?php else: ?>
                        <?php 
                        $uniqueInterviews = [];
                        foreach ($candidate['reviews'] as $review) {
                            $interview = explode(' ', $review['interview'])[0];
                            if (!isset($uniqueInterviews[$interview])) {
                                $uniqueInterviews[$interview] = [];
                            }
                            $uniqueInterviews[$interview][] = $review;
                        }
                        ?>
                        <table class="table table-sm mx-auto">
                            <thead>
                                <tr>
                                    <th>Interview</th>
                                    <th>Result</th>
                                    <th>Reviewer</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($uniqueInterviews as $interview => $reviews): ?>
                                    <?php foreach ($reviews as $review): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($interview); ?></td>
                                            <td class="text-center">
                                                <?php if (isset($review['approved']) && $review['approved'] == 'Yes'): ?>
                                                    <i class="bi bi-check-circle-fill" style="color: green;"></i>
                                                <?php elseif (isset($review['approved']) && $review['approved'] == 'No'): ?>
                                                    <i class="bi bi-x-circle-fill" style="color: red;"></i>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(generateReviewerInitials($review['interviewer_name'])); ?></td>
                                            <td><?php echo htmlspecialchars(date('m/d/Y', strtotime($review['review_date']))); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div id="<?php echo 'cv-' . $candidate['id']; ?>" class="cv-iframe" style="display:none;">
                <iframe src="<?php echo htmlspecialchars($candidateCV ?? ''); ?>" width="100%" height="400px"></iframe>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>


 
</div>

  









  
  <!-- End of Modules Here -->
  <?php else: ?>
  <div class="alert alert-danger" role="alert"> You are not authorized to view this page. </div>
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
function showEditForm(id) {
    const card = document.getElementById('candidate-card-' + id);
    const editForm = card.querySelector('.edit-form');
    const displayForm = card.querySelector('.card-body');
    editForm.style.display = 'block';
    displayForm.style.display = 'none';
}

function cancelEditForm(id) {
    const card = document.getElementById('candidate-card-' + id);
    const editForm = card.querySelector('.edit-form');
    const displayForm = card.querySelector('.card-body');
    editForm.style.display = 'none';
    displayForm.style.display = 'block';
}

function updateCandidate(event, id) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);

    formData.append('action', 'update_candidate');

    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())  // Change to .text() to see the raw response
    .then(data => {
        console.log('Raw Response:', data);  // Log the raw response for debugging

        // Try to parse the response as JSON
        let json;
        try {
            json = JSON.parse(data);
        } catch (error) {
            console.error('Error parsing JSON:', error);
            alert('Error updating candidate: Response is not valid JSON');
            return;
        }

        if (json.success) {
            const card = document.getElementById('candidate-card-' + id);
            const displayForm = card.querySelector('.display-form');

            displayForm.querySelector('.card-title').innerText = json.name;
            displayForm.querySelector('.card-text[data-type="email"]').innerText = `Email: ${json.email}`;
            displayForm.querySelector('.card-text[data-type="phone"]').innerText = `Phone: ${json.phone_number}`;
            displayForm.querySelector('.card-text[data-type="location"]').innerText = `Location: ${json.location}`;
            displayForm.querySelector('.card-text[data-type="english_level"]').innerText = `English Level: ${json.english_level}`;
            displayForm.querySelector('.card-text[data-type="enabled"]').innerText = `Enabled: ${json.enabled ? 'Yes' : 'No'}`;
            cancelEditForm(id);
        } else {
            alert('Error updating candidate: ' + (json.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating candidate: ' + error.message);
    });
}

	
function toggleCV(id, cvUrl) {
    var cvElement = document.getElementById(id);
    var iframe = cvElement.querySelector('iframe');

    if (cvElement.style.display === "none") {
        iframe.src = cvUrl;  // Set the src attribute of the iframe
        cvElement.style.display = "block";
    } else {
        cvElement.style.display = "none";
        iframe.src = "";  // Clear the src attribute when hiding
    }
}


</script>




</body>
</html>
