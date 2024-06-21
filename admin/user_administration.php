
<?php 
session_start();
// Register here the page information
$page_title = "Users Administration";
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

// Fetch all users
$sql = "SELECT * FROM users ORDER BY name ASC";
$result = $db->query($sql);

if ($result === false) {
    die("Error fetching users: " . $db->error);
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);
    $userEmail = $_POST['user_email'];
    
    if ($userEmail !== $_SESSION['email']) {
        echo "Deleting user: " . htmlspecialchars($userEmail) . "<br>"; // Debugging line

        // Delete records from related tables
        $tables = [
            'candidate_certifications',
            'candidate_db',
            'candidate_profiles',
            'candidate_review',
			'candidate_reviews',
            'candidate_skillset',
			'candidate_skillsets',
            'users'
        ];

        foreach ($tables as $table) {
            $deleteSql = "DELETE FROM $table WHERE email = ?";
            if ($deleteStmt = $db->prepare($deleteSql)) {
                $deleteStmt->bind_param('s', $userEmail);
                if ($deleteStmt->execute()) {
                    echo "Deleted from $table<br>"; // Debugging line
                } else {
                    echo "Error executing delete from $table: " . $deleteStmt->error . "<br>"; // Debugging line
                }
                $deleteStmt->close();
            } else {
                echo "Error preparing statement for $table: " . $db->error . "<br>"; // Debugging line
            }
        }

        // Redirect after deletion
        header("Location: user_administration.php");
        exit();
    } else {
        echo "Cannot delete own user.<br>"; // Debugging line
    }
}

// Handle role change request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $userId = intval($_POST['user_id']);
    $newRole = intval($_POST['role']);

    $updateRoleSql = "UPDATE users SET admin = ? WHERE id = ?";
    if ($updateRoleStmt = $db->prepare($updateRoleSql)) {
        $updateRoleStmt->bind_param('ii', $newRole, $userId);
        $updateRoleStmt->execute();
        $updateRoleStmt->close();
        header("Location: user_administration.php");
        exit();
    } else {
        error_log("Error preparing statement: " . $db->error);
    }
}

// Fetch users with 'samanagroup.co' email
$samanagroupUsersSql = "SELECT * FROM users WHERE email LIKE '%samanagroup.co%' ORDER BY name ASC";
$samanagroupUsersResult = $db->query($samanagroupUsersSql);
if ($samanagroupUsersResult === false) {
    die("Error fetching users: " . $db->error);
}
// Fetch users with email not containing 'samanagroup.co'
$gmailUsersSql = "SELECT * FROM users WHERE email NOT LIKE '%@samanagroup.co%' ORDER BY name ASC";
$gmailUsersResult = $db->query($gmailUsersSql);
if ($gmailUsersResult === false) {
    die("Error fetching users: " . $db->error);
}

// Check enrollment and process for each user
$usersData = [];
while ($row = $gmailUsersResult->fetch_assoc()) {
    // Check if user is enrolled
    $enrollmentQuery = "SELECT COUNT(*) as count FROM candidate_profiles WHERE email = ?";
    $stmt = $db->prepare($enrollmentQuery);
    $stmt->bind_param("s", $row['email']);
    $stmt->execute();
    $stmt->bind_result($isEnrolled);
    $stmt->fetch();
    $stmt->close();

    // Check the process and get job title
    $processQuery = "
        SELECT jp.job_title
        FROM candidate_review cr
        JOIN job_postings jp ON cr.process = jp.id
        WHERE cr.email = ? LIMIT 1
    ";
    $stmt = $db->prepare($processQuery);
    $stmt->bind_param("s", $row['email']);
    $stmt->execute();
    $stmt->bind_result($jobTitle);
    $stmt->fetch();
    $stmt->close();

    // Store user data
    $row['isEnrolled'] = $isEnrolled > 0 ? 'Yes' : '<b>No</b>';
    $row['process'] = !empty($jobTitle) ? $jobTitle : 'No';
    $usersData[] = $row;
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

		.action-buttons {
    display: flex;
    justify-content: flex-end;
}

.table-striped .action-buttons {
    text-align: right;
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


	
	
<div class="container form-container mt-5">
        <?php if ($is_authorized): ?>
            <!-- Authorized content goes here -->
 <!-- Modules Inserted Here -->

<!-- Modules Inserted Here -->

<div class="container">
    <h4>Samana Group Users</h4>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Created at</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $samanagroupUsersResult->fetch_assoc()): ?>
                    <tr>
                        <td data-label="ID"><?php echo htmlspecialchars($row['id']); ?></td>
                        <td data-label="Name">
                            <b><?php echo htmlspecialchars($row['name']); ?></b><br>
                            <?php echo htmlspecialchars($row['email']); ?>
                        </td>
                        <td data-label="Created At"><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td data-label="Role">
                            <?php 
                                switch ($row['admin']) {
                                    case 0:
                                        echo 'Registered User';
                                        break;
                                    case 1:
                                        echo 'Employee';
                                        break;
                                    case 2:
                                        echo 'Reviewer';
                                        break;
                                    case 3:
                                        echo 'Manager';
                                        break;
                                    case 4:
                                        echo 'Admin';
                                        break;
                                    case 5:
                                        echo 'Super Admin';
                                        break;
                                    default:
                                        echo '';
                                        break;
                                }
                            ?>
                        </td>
                        <td data-label="Actions">
                            <div class="action-buttons">
                               <?php if ($row['email'] !== $_SESSION['email'] && $loggedInUserAdminStatus >= $manager_level): ?>
                                <form id="deleteForm-<?php echo $row['id']; ?>" method="POST" action="user_administration.php" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($row['email']); ?>">
                                    <input type="hidden" name="delete_user" value="">
                                    <button type="button"  class="btn btn-danger mb-2" onclick="confirmDelete(<?php echo $row['id']; ?>)">Delete User</button>
                                </form>
                                
                                <button type="button" class="btn btn-info mb-2" onclick="showRoleForm(<?php echo $row['id']; ?>)">Change Role</button>
                                <?php endif; ?>
              <!--                  <form id="updateProfileForm-<?php //echo $row['id']; ?>" method="POST" action="user_enrollment.php" style="display:inline;">
                                    <input type="hidden" name="candidate_email" value="<?php //echo htmlspecialchars($row['email']); ?>">
                                    <input type="hidden" name="candidate_name" value="<?php //echo htmlspecialchars($row['name']); ?>">
                                    <button type="submit" class="btn btn-success mb-2">Enroll</button>
                                </form>-->
                                
                            </div>
                            <div id="roleForm-<?php echo $row['id']; ?>" style="display:none; margin-top: 10px;">
                                <form method="POST" action="user_administration.php">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="0" <?php if ($row['admin'] == 0) echo 'checked'; ?>> Registered User
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="1" <?php if ($row['admin'] == 1) echo 'checked'; ?>> Employee
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="2" <?php if ($row['admin'] == 2) echo 'checked'; ?>> Reviewer
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="3" <?php if ($row['admin'] == 3) echo 'checked'; ?>> Manager
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="4" <?php if ($row['admin'] == 4) echo 'checked'; ?>> Admin
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="5" <?php if ($row['admin'] == 5) echo 'checked'; ?>> Super Admin
                                            </label>
                                        </div>
                                        <button type="submit" name="change_role" class="btn btn-success ml-2">Accept</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

	
	<!-- Modules Inserted Here -->
<div class="container">
    <h4>Registered Users</h4>
    <div class="table-responsive scrollable-table">
        <table class="table table-striped ">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Created at</th>
                    <th>Enrolled</th>
                    <th>Process</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usersData as $row): ?>
                    <tr>
                        <td data-label="ID"><?php echo htmlspecialchars($row['id']); ?></td>
                        <td data-label="Name">
                            <b><?php echo htmlspecialchars($row['name']); ?></b><br>
                            <?php echo htmlspecialchars($row['email']); ?>
                        </td>
                        <td data-label="Created At"><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td data-label="Enrolled"><?php echo $row['isEnrolled']; ?></td>
                        <td data-label="Process"><?php echo htmlspecialchars($row['process']); ?></td>
                        <td data-label="Actions">
                            <div class="action-buttons">
                               <?php if ($row['email'] !== $_SESSION['email'] && $loggedInUserAdminStatus >= $manager_level): ?>
                                <form id="deleteForm-<?php echo $row['id']; ?>" method="POST" action="user_administration.php" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="user_email" value="<?php echo htmlspecialchars($row['email']); ?>">
                                    <input type="hidden" name="delete_user" value="">
                                    <button type="button"  class="btn btn-danger mb-2" onclick="confirmDelete(<?php echo $row['id']; ?>)">Delete User</button>
                                </form>
                                
                              <!--  <button type="button" class="btn btn-info mb-2" onclick="showRoleForm(<?php //echo $row['id']; ?>)">Change Role</button> -->
                                <?php endif; ?>
                                <form id="updateProfileForm-<?php echo $row['id']; ?>" method="POST" action="user_responses.php" style="display:inline;">
                                    <input type="hidden" name="candidate_email" value="<?php echo htmlspecialchars($row['email']); ?>">
                                    <input type="hidden" name="candidate_name" value="<?php echo htmlspecialchars($row['name']); ?>">
                                    <button type="submit" class="btn btn-success mb-2">Enroll</button>
                                </form>
                                
                            </div>
                            <div id="roleForm-<?php echo $row['id']; ?>" style="display:none; margin-top: 10px;">
                                <form method="POST" action="user_administration.php">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <div class="form-group">
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="0" <?php if ($row['admin'] == 0) echo 'checked'; ?>> Registered User
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="1" <?php if ($row['admin'] == 1) echo 'checked'; ?>> Employee
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="2" <?php if ($row['admin'] == 2) echo 'checked'; ?>> Reviewer
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="3" <?php if ($row['admin'] == 3) echo 'checked'; ?>> Manager
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="4" <?php if ($row['admin'] == 4) echo 'checked'; ?>> Admin
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <label class="form-check-label">
                                                <input type="radio" class="form-check-input" name="role" value="5" <?php if ($row['admin'] == 5) echo 'checked'; ?>> Super Admin
                                            </label>
                                        </div>
                                        <button type="submit" name="change_role" class="btn btn-success ml-2">Accept</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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
