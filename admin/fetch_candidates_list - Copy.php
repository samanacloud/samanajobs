<?php
session_start();
$config = include('../google-login/config.php');

// Include the database configuration file
$db = new mysqli($config['database']['host'], $config['database']['user'], $config['database']['pass'], $config['database']['db']);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

if (!isset($_POST['role'])) {
    echo json_encode([]);
    exit();
}

$role = $_POST['role'];

// Fetch candidates based on the role
if ($role === "") {
    // If no role is selected, fetch all candidates
    $query = "SELECT cp.id, cp.name, cp.email, cp.phone_number, cp.location, cp.english_level, cp.profile_photo, cp.candidate_cv, cp.created_at, cp.enabled, u.profile_image 
              FROM candidate_profiles cp
              LEFT JOIN users u ON cp.email = u.email
              ORDER BY cp.name ASC";
    $stmt = $db->prepare($query);
} else {
    // If a role is selected, fetch candidates with that role
    $query = "SELECT cp.id, cp.name, cp.email, cp.phone_number, cp.location, cp.english_level, cp.profile_photo, cp.candidate_cv, cp.created_at, cp.enabled, u.profile_image 
              FROM candidate_profiles cp
              LEFT JOIN users u ON cp.email = u.email
              JOIN candidate_review cr ON cp.email = cr.email
              JOIN job_postings jp ON cr.process = jp.id
              WHERE jp.job_title = ?
              ORDER BY cp.name ASC";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $role);
}

$stmt->execute();
$result = $stmt->get_result();
$candidates = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch review details for each candidate
foreach ($candidates as &$candidate) {
    $reviewData = fetchCandidateReviews($db, $candidate['email']);
    $candidate['reviews'] = $reviewData['reviews'];
    $candidate['role'] = $reviewData['role'];
}

echo json_encode($candidates);

// Function to fetch candidate reviews
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
?>
