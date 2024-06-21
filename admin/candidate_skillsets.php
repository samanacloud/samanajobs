<?php
session_start();
// Register here the page information
$config = include('../google-login/config.php');
$page_title = "Candidate Skillsets";
$page_level = 3; // Set the required admin level for this page
$manager_level = 3;
$modules = load_menu();  // Load the menu



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

if (!isset($_SESSION['email'])) {
    // Redirect to the home page if the user is not logged in
    header('Location: ../index.php'); 
    exit();
}

// Database connection parameters

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

// Start PHP Code Here

// Fetch all categories
$candidate_email = "Juan.otalvaro@samanagroup.co";

if (isset($_GET['candidate_email'])) {
    $candidate_email = $_GET['candidate_email'];
} elseif (isset($_POST['candidate_email'])) {
    $candidate_email = $_POST['candidate_email'];
}

// Fetch candidate information
$candidate_query = $db->query("SELECT * FROM candidate_profiles WHERE email = '" . $db->real_escape_string($candidate_email) . "'");

if (!$candidate_query) {
    error_log("Candidate query failed: " . $db->error);
    die("Candidate query failed: " . $db->error);
}

$candidate = $candidate_query->fetch_assoc();

if (!$candidate) {
    error_log("No candidate found with the provided email.");
    die("No candidate found with the provided email.");
}

// Fetch user_id if not set in session
if (!isset($_SESSION['user_id'])) {
    $user_email = $_SESSION['email'];
    $user_query = $db->query("SELECT id FROM users WHERE email = '" . $db->real_escape_string($user_email) . "'");
    if ($user_query) {
        $user_result = $user_query->fetch_assoc();
        if ($user_result) {
            $_SESSION['user_id'] = $user_result['id'];
        } else {
            error_log("No user found with the provided email.");
            die("No user found with the provided email.");
        }
    } else {
        error_log("User query failed: " . $db->error);
        die("User query failed: " . $db->error);
    }
}

// Fetch all categories
$categories = $db->query("SELECT * FROM job_categories");

if (!$categories) {
    error_log("Categories query failed: " . $db->error);
    die("Categories query failed: " . $db->error);
}

$selected_category_id = null;
$selected_category_name = null;
$skills = [];
$ratings = [];
$all_ratings = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['select_category'])) {
        $selected_category_id = (int)$_POST['category_id'];

        $selected_category_name_query = $db->query("SELECT category_name FROM job_categories WHERE id = $selected_category_id");

        if ($selected_category_name_query) {
            $selected_category_name_result = $selected_category_name_query->fetch_assoc();
            if ($selected_category_name_result) {
                $selected_category_name = $selected_category_name_result['category_name'];
            } else {
                error_log("No category found with the provided ID.");
                die("No category found with the provided ID.");
            }
        } else {
            error_log("Category name query failed: " . $db->error);
            die("Category name query failed: " . $db->error);
        }

        $skills_query = $db->query("SELECT * FROM job_skillsets WHERE category_id = $selected_category_id");

        if ($skills_query) {
            $skills = $skills_query->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log("Skills query failed: " . $db->error);
            die("Skills query failed: " . $db->error);
        }

        // Fetch existing ratings for the selected category and candidate
		$ratings_query = $db->query("
		SELECT * 
		FROM candidate_skillsets 
		WHERE candidate_id = $candidate[id] 
		AND category = '" . $db->real_escape_string($selected_category_name) . "' 
		AND reviewer_email = '" . $db->real_escape_string($_SESSION['email']) . "'
		");

        if ($ratings_query) {
            while ($row = $ratings_query->fetch_assoc()) {
                $ratings[$row['skillset']] = $row;
            }
        } else {
            error_log("Ratings query failed: " . $db->error);
            die("Ratings query failed: " . $db->error);
        }
    }

    if (isset($_POST['rate_candidate'])) {
        $candidate_id = (int)$_POST['candidate_id'];
        $email = $db->real_escape_string($_POST['email']);
        $category = $db->real_escape_string($_POST['category']);
        $skillset = $db->real_escape_string($_POST['skillset']);
        $rating = (int)$_POST['rating'];
        $reviewer_id = $_SESSION['user_id'];
        $reviewer_email = $db->real_escape_string($_POST['reviewer_email']);
        $comment = isset($_POST['comment']) ? $db->real_escape_string($_POST['comment']) : '';

        // Check if rating exists, update if so, insert otherwise
        $existing_rating_query = $db->query("SELECT * FROM candidate_skillsets WHERE candidate_id = $candidate_id AND category = '$category' AND skillset = '$skillset'");

        if ($existing_rating_query && $existing_rating_query->num_rows > 0) {
            // Update existing rating
            $db->query("UPDATE candidate_skillsets SET rating = $rating, reviewer_id = $reviewer_id, reviewer_email = '$reviewer_email', comment = '$comment' WHERE candidate_id = $candidate_id AND category = '$category' AND skillset = '$skillset'");
        } else {
            // Insert new rating
            $db->query("INSERT INTO candidate_skillsets (candidate_id, email, category, skillset, rating, reviewer_id, reviewer_email, comment) VALUES ($candidate_id, '$email', '$category', '$skillset', $rating, $reviewer_id, '$reviewer_email', '$comment')");
        }
    }
}

// Fetch all ratings for the candidate
$all_ratings_query = $db->query("SELECT * FROM candidate_skillsets WHERE candidate_id = $candidate[id]");
if ($all_ratings_query) {
    while ($row = $all_ratings_query->fetch_assoc()) {
        $all_ratings[$row['category']][] = $row;
    }
} else {
    error_log("All ratings query failed: " . $db->error);
    die("All ratings query failed: " . $db->error);
}
if (isset($_POST['delete_rating'])) {
    $candidate_id = (int)$_POST['candidate_id'];
    $category = $db->real_escape_string($_POST['category']);
    $skillset = $db->real_escape_string($_POST['skillset']);

    $db->query("DELETE FROM candidate_skillsets WHERE candidate_id = $candidate_id AND category = '$category' AND skillset = '$skillset'");
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
    .rating-list-group .list-group-item {
        padding: 5px 10px; /* Adjust the padding to reduce row spacing */
    }
    .rating-list-group .list-group-item span {
        display: flex;
        align-items: center;
        justify-content: space-between;
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
					  <p><strong>Name:</strong> <?php echo htmlspecialchars($candidate['name']); ?> <br>
					<strong>Email:</strong> <?php echo htmlspecialchars($candidate['email']); ?></p>
                </div>
                <div class="col text-right">
                    <?php if (isset($_SESSION['email'])): ?>
                        <span>Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                        <a href="https://jobs.samana.cloud/google-login/logout.php" class="btn btn-danger ml-2">Logout</a>
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

    <div class="container mt-5">
        <?php if ($is_authorized): ?>
            <!-- Authorized content goes here -->
	
            <!-- Modules Inserted Here -->


		
<div class="container mt-5">
    <div class="row">
        <div class="col-md-6">
           

            <h4>Manage Categories</h4>
            <ul class="list-group">
                <?php while ($category = $categories->fetch_assoc()): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                        <span>
                            <!-- Select Icon -->
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                <input type="hidden" name="candidate_id" value="<?php echo $candidate['id']; ?>">
                                <input type="hidden" name="candidate_email" value="<?php echo $candidate_email; ?>">
                                <button type="submit" name="select_category" class="btn btn-link"><i class="bi bi-eye"></i></button>
                            </form>
                        </span>
                    </li>
                <?php endwhile; ?>
            </ul>

            <?php if (isset($selected_category_name)): ?>
                <h4 class="mt-4">Skills in Selected Category: <?php echo htmlspecialchars($selected_category_name); ?></h4>
                <ul class="list-group">
                    <?php foreach ($skills as $skill): ?>
                        <li class="list-group-item">
                            <?php echo htmlspecialchars($skill['skillset_name']); ?>
                            <div class="rating-bar" data-skillset="<?php echo htmlspecialchars($skill['skillset_name']); ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi star <?php echo (isset($ratings[$skill['skillset_name']]) && $ratings[$skill['skillset_name']]['rating'] >= $i) ? 'bi-star-fill' : 'bi-star'; ?>" data-rating="<?php echo $i; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <h4>Ratings by Category</h4>
            <?php foreach ($all_ratings as $category => $skills): ?>
                <h5><?php echo htmlspecialchars($category); ?></h5>
                <ul class="list-group rating-list-group">
                    <?php foreach ($skills as $skill): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?php echo htmlspecialchars($skill['skillset']); ?></span>
                            <span>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi star <?php echo ($skill['rating'] >= $i) ? 'bi-star-fill' : 'bi-star'; ?>"></i>
                                <?php endfor; ?>
                                <button type="button" class="btn btn-link text-danger delete-rating" data-category="<?php echo htmlspecialchars($category); ?>" data-skillset="<?php echo htmlspecialchars($skill['skillset']); ?>"><i class="bi bi-trash"></i></button>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </div>
    </div>
</div>





		
		
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
    document.querySelectorAll('.rating-bar .star').forEach(star => {
        star.addEventListener('click', function () {
            const rating = this.getAttribute('data-rating');
            const skillset = this.closest('.rating-bar').getAttribute('data-skillset');

            // Submit rating via POST
            const formData = new FormData();
            formData.append('rate_candidate', true);
            formData.append('candidate_id', '<?php echo $candidate['id']; ?>');
            formData.append('email', '<?php echo $candidate_email; ?>');
            formData.append('category', '<?php echo $selected_category_name; ?>');
            formData.append('skillset', skillset);
            formData.append('rating', rating);
            formData.append('reviewer_id', '<?php echo $_SESSION['user_id']; ?>');
            formData.append('reviewer_email', '<?php echo $_SESSION['email']; ?>');

            fetch('candidate_skillsets.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    // Update the UI to reflect the new rating
                    const stars = this.closest('.rating-bar').querySelectorAll('.star');
                    stars.forEach(star => {
                        star.classList.remove('bi-star-fill');
                        star.classList.add('bi-star');
                        if (star.getAttribute('data-rating') <= rating) {
                            star.classList.remove('bi-star');
                            star.classList.add('bi-star-fill');
                        }
                    });
                }
            }).catch(error => {
                console.error('Error:', error);
            });
        });
    });

    document.querySelectorAll('.rating-list-group .delete-rating').forEach(button => {
        button.addEventListener('click', function () {
            const skillset = this.getAttribute('data-skillset');
            const category = this.getAttribute('data-category');

            // Submit deletion via POST
            const formData = new FormData();
            formData.append('delete_rating', true);
            formData.append('candidate_id', '<?php echo $candidate['id']; ?>');
            formData.append('category', category);
            formData.append('skillset', skillset);

            fetch('candidate_skillsets.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    // Remove the rating item from the DOM
                    this.closest('li').remove();
                }
            }).catch(error => {
                console.error('Error:', error);
            });
        });
    });
</script>


</body>
</html>
