<?php
ob_start();
session_start();
// Register here the page information
$config = include('../google-login/config.php');
$page_title = "Manage Jobs";
$page_level = 4; // Set the required admin level for this page
$manager_level = 3;
// Load the menu
$modules = load_menu();

function checkSessionTimeout() {
    $timeout_duration = 600; // 10 minutes in seconds

    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: ../google-login/logout.php");
        exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

checkSessionTimeout();

if (!isset($_SESSION['email']) || !$_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['email']);
$name = $isLoggedIn ? $_SESSION['name'] : '';
$logoutUrl = 'https://jobs.samana.cloud/google-login/logout.php';

// Include the database configuration file
$db = new mysqli($config['database']['host'], $config['database']['user'], $config['database']['pass'], $config['database']['db']);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

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

// Fetch all job postings
$jobs = [];
$result = $db->query("SELECT id, job_category, job_title, job_details, job_type, linkedin_url, role_description, responsibilities, preferred_certifications, qualifications, salary_range, post_date, workplace_type, enabled FROM job_postings ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}

// Encode job details in JavaScript object
echo '<script>var jobDetails = ' . json_encode($jobs) . ';</script>';

// Handle enable/disable action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_enabled'])) {
    $jobId = intval($_POST['job_id']);
    $currentStatus = intval($_POST['current_status']);
    $newStatus = $currentStatus ? 0 : 1;

    $stmt = $db->prepare("UPDATE job_postings SET enabled = ? WHERE id = ?");
    $stmt->bind_param("ii", $newStatus, $jobId);

    if ($stmt->execute()) {
        echo "Job status updated successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    // Refresh to reflect changes
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle job posting submission and editing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_job']) || isset($_POST['edit_job']))) {
    $job_category = $_POST['job_category'];
    $job_title = $_POST['job_title'];
    $job_details = $_POST['job_details'];
    $job_type = $_POST['job_type'];
    $linkedin_url = $_POST['linkedin_url'];
    $role_description = $_POST['role_description'];
    $responsibilities = $_POST['responsibilities'];
    $preferred_certifications = $_POST['preferred_certifications'];
    $qualifications = $_POST['qualifications'];
    $salary_range = $_POST['salary_range'];
    $workplace_type = $_POST['workplace_type'];
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $post_date = date('Y-m-d'); // Current date

    if (isset($_POST['edit_job'])) {
        $job_id = $_POST['job_id'];
        $stmt = $db->prepare("UPDATE job_postings SET job_category = ?, job_title = ?, job_details = ?, job_type = ?, linkedin_url = ?, role_description = ?, responsibilities = ?, preferred_certifications = ?, qualifications = ?, salary_range = ?, post_date = ?, workplace_type = ?, enabled = ? WHERE id = ?");
        $stmt->bind_param("ssssssssssssii", $job_category, $job_title, $job_details, $job_type, $linkedin_url, $role_description, $responsibilities, $preferred_certifications, $qualifications, $salary_range, $post_date, $workplace_type, $enabled, $job_id);
    } else {
        $stmt = $db->prepare("INSERT INTO job_postings (job_category, job_title, job_details, job_type, linkedin_url, role_description, responsibilities, preferred_certifications, qualifications, salary_range, post_date, workplace_type, enabled) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssssi", $job_category, $job_title, $job_details, $job_type, $linkedin_url, $role_description, $responsibilities, $preferred_certifications, $qualifications, $salary_range, $post_date, $workplace_type, $enabled);
    }

    if ($stmt->execute()) {
        echo "Job " . (isset($_POST['edit_job']) ? "updated" : "added") . " successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    // Refresh to reflect changes
    header("Location: " . $_SERVER['PHP_SELF']);
    ob_end_flush(); // Flush the output buffer and send output to browser
    exit();
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    $jobId = intval($_POST['job_id']);

    $stmt = $db->prepare("DELETE FROM job_postings WHERE id = ?");
    $stmt->bind_param("i", $jobId);

    if ($stmt->execute()) {
        echo "Job deleted successfully.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    // Refresh to reflect changes
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
ob_end_flush(); // Ensure this is added at the end of your script
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

	
<div class="container mt-4">
    <?php if ($is_authorized): ?>
        <!-- Authorized content goes here -->

        <!-- Add job posting -->
        <div class="form-container">
            <h2>Add Job Posting</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="job_category">Job Category</label>
                    <select class="form-control" id="job_category" name="job_category" required>
                        <option value="AWS">AWS</option>
                        <option value="Citrix" selected="selected">Citrix</option>
                        <option value="NetScaler">NetScaler</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="job_title">Job Title</label>
                    <input type="text" class="form-control" id="job_title" name="job_title" required>
                </div>
                <div class="form-group">
                    <label for="job_details">Job Details</label>
                    <textarea class="form-control" id="job_details" name="job_details" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="job_type">Job Type</label>
                    <select class="form-control" id="job_type" name="job_type" required>
                        <option value="Full-time" selected="selected">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                        <option value="Temporary">Temporary</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="linkedin_url">LinkedIn URL</label>
                    <input type="url" class="form-control" id="linkedin_url" name="linkedin_url">
                </div>
                <div class="form-group">
                    <label for="role_description">Role Description</label>
                    <textarea class="form-control" id="role_description" name="role_description" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="responsibilities">Responsibilities</label>
                    <textarea class="form-control" id="responsibilities" name="responsibilities" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="preferred_certifications">Preferred Certifications</label>
                    <textarea class="form-control" id="preferred_certifications" name="preferred_certifications" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label for="qualifications">Qualifications</label>
                    <textarea class="form-control" id="qualifications" name="qualifications" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="salary_range">Salary Range</label>
                    <input type="text" class="form-control" id="salary_range" name="salary_range">
                </div>
                <div class="form-group">
                    <label for="workplace_type">Workplace Type</label>
                    <select class="form-control" id="workplace_type" name="workplace_type" required>
                        <option value="On-Site">On-Site</option>
                        <option value="Hybrid">Hybrid</option>
                        <option value="Remote" selected="selected">Remote</option>
                    </select>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" class="form-check-input" id="enabled" name="enabled" checked>
                    <label class="form-check-label" for="enabled">Enabled</label>
                </div>
                <button type="submit" class="btn btn-primary" name="add_job">Add Job</button>
            </form>
        </div>

        <!-- List Jobs Module -->
        <div class="container mt-4">
            <div class="form-container">
                <h2>Available Jobs</h2>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Job Details</th>
                                <th>Salary Range</th>
                                <th>Post Date</th>
                                <th>Enabled</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($job['job_title']); ?></td>
                                    <td><?php echo htmlspecialchars($job['job_details']); ?></td>
                                    <td><?php echo htmlspecialchars($job['salary_range']); ?></td>
                                    <td><?php echo htmlspecialchars($job['post_date']); ?></td>
                                    <td><?php echo $job['enabled'] ? 'Enabled' : 'Disabled'; ?></td>
                                    <td>
                                        <form method="POST" action="" style="display:inline;">
                                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $job['enabled']; ?>">
                                            <button type="submit" class="btn btn-secondary" name="toggle_enabled">
                                                <?php echo $job['enabled'] ? 'Disable' : 'Enable'; ?>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-primary" onclick="editJob(<?php echo $job['id']; ?>)">Edit</button>
                                        <?php if ($_SESSION['is_admin'] == 'yes'): ?>
                                            <form id="deleteForm-<?php echo $job['id']; ?>" method="POST" action="" style="display:inline;">
                                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                <input type="hidden" name="delete_job" value="1">
                                                <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $job['id']; ?>)">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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
function editJob(jobId) {
    // Ensure jobDetails is available
    if (!jobDetails) {
        console.error('Job details not available.');
        return;
    }

    var job = jobDetails.find(job => job.id == jobId);

    if (job) {
        // Populate form with job details
        document.getElementById('job_category').value = job.job_category;
        document.getElementById('job_title').value = job.job_title;
        document.getElementById('job_details').value = job.job_details || ''; // Ensure job_details is set
        document.getElementById('job_type').value = job.job_type;
        document.getElementById('linkedin_url').value = job.linkedin_url || '';
        document.getElementById('role_description').value = job.role_description || '';
        document.getElementById('responsibilities').value = job.responsibilities || '';
        document.getElementById('preferred_certifications').value = job.preferred_certifications || '';
        document.getElementById('qualifications').value = job.qualifications || '';
        document.getElementById('salary_range').value = job.salary_range || '';
        document.getElementById('workplace_type').value = job.workplace_type;
        document.getElementById('enabled').checked = job.enabled == 1;

        // Add a hidden input to store job_id
        var jobIdInput = document.createElement('input');
        jobIdInput.type = 'hidden';
        jobIdInput.name = 'job_id';
        jobIdInput.value = job.id;
        var form = document.querySelector('form');
        form.appendChild(jobIdInput);

        // Change form action to edit mode
        form.querySelector('button[name="add_job"]').style.display = 'none';
        var editButton = form.querySelector('button[name="edit_job"]');
        if (!editButton) {
            editButton = document.createElement('button');
            editButton.type = 'submit';
            editButton.name = 'edit_job';
            editButton.className = 'btn btn-primary';
            editButton.textContent = 'Edit Job';
            form.appendChild(editButton);
        }
    }
}
	function confirmDelete(jobId) {
        if (confirm("Are you sure you want to delete this job?")) {
            document.getElementById('deleteForm-' + jobId).submit();
        }
    }
</script>
</body>
</html>
