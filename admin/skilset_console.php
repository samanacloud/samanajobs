<?php
session_start();
// Register here the page information
$config = include('../google-login/config.php');
$page_title = "Skillset Management Console";
$page_level = 5; // Set the required admin level for this page
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



// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $category_name = $db->real_escape_string($_POST['category_name']);
        $db->query("INSERT INTO job_categories (category_name) VALUES ('$category_name')");
    }

    if (isset($_POST['edit_category'])) {
        $category_id = (int)$_POST['category_id'];
        $category_name = $db->real_escape_string($_POST['category_name']);
        $db->query("UPDATE job_categories SET category_name = '$category_name' WHERE id = $category_id");
    }

    if (isset($_POST['delete_category'])) {
        $category_id = (int)$_POST['category_id'];
        // Delete related skillsets
        $db->query("DELETE FROM job_skillsets WHERE category_id = $category_id");
        // Delete the category
        $db->query("DELETE FROM job_categories WHERE id = $category_id");
    }

    if (isset($_POST['select_category']) || isset($_POST['add_skillset']) || isset($_POST['delete_skillset'])) {
        $selected_category_id = (int)$_POST['category_id'];

        if (isset($_POST['add_skillset'])) {
            $skillset_name = $db->real_escape_string($_POST['skillset_name']);
            $db->query("INSERT INTO job_skillsets (category_id, skillset_name) VALUES ($selected_category_id, '$skillset_name')");
        }

        if (isset($_POST['delete_skillset'])) {
            $skillset_id = (int)$_POST['skillset_id'];
            $db->query("DELETE FROM job_skillsets WHERE id = $skillset_id");
        }

        $selected_category_name_query = $db->query("SELECT category_name FROM job_categories WHERE id = $selected_category_id ");

        if ($selected_category_name_query) {
            $selected_category_name_result = $selected_category_name_query->fetch_assoc();
            if ($selected_category_name_result) {
                $selected_category_name = $selected_category_name_result['category_name'];
            } else {
                die("No category found with the provided ID.");
            }
        } else {
            die("Category name query failed: " . $db->error);
        }

        $skills_query = $db->query("SELECT * FROM job_skillsets WHERE category_id = $selected_category_id ");

        if ($skills_query) {
            $skills = $skills_query->fetch_all(MYSQLI_ASSOC);
        } else {
            die("Skills query failed: " . $db->error);
        }
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);
    $userEmail = $_POST['user_email'];
    
    if ($userEmail !== $_SESSION['email']) {
        // Delete records from related tables
        $tables = [
            'candidate_certifications',
            'candidate_db',
            'candidate_profiles',
            'candidate_review',
            'candidate_skillset',
            'users'
        ];

        foreach ($tables as $table) {
            $deleteSql = "DELETE FROM $table WHERE email = ?";
            if ($deleteStmt = $db->prepare($deleteSql)) {
                $deleteStmt->bind_param('s', $userEmail);
                $deleteStmt->execute();
                $deleteStmt->close();
            } else {
                echo "Error preparing statement: " . $db->error;
            }
        }

        // Redirect after deletion
        header("Location: user_administration.php");
        exit();
    }
}
// Fetch all categories
$categories = $db->query("SELECT * FROM job_categories ORDER BY category_name ASC");

if (!$categories) {
    die("Categories query failed: " . $db->error);
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
		
		
 <!-- Categories Module -->		

	
		
		
		
		
		
		<div class="row">
    <!-- Manage Categories Column -->
    <div class="col-md-6 form-container">
        <h4>Manage Categories</h4>
        <ul class="list-group">
            <?php while ($category = $categories->fetch_assoc()): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                    <span>
                        <!-- Select Icon -->
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                            <button type="submit" name="select_category" class="btn btn-link"><i class="bi bi-eye"></i></button>
                        </form>
                        <!-- Edit Icon -->
                        <a href="#editCategoryModal" class="edit" data-toggle="modal" data-id="<?php echo $category['id']; ?>" data-name="<?php echo htmlspecialchars($category['category_name']); ?>">
                            <i class="bi bi-pencil-square"></i>
                        </a>
                        <!-- Delete Icon -->
                        <a href="#deleteCategoryModal" class="delete" data-toggle="modal" data-id="<?php echo $category['id']; ?>">
                            <i class="bi bi-trash"></i>
                        </a>
                    </span>
                </li>
            <?php endwhile; ?>
        </ul>
        <h4 class="mt-4">Add New Category</h4>
        <form method="post" action="">
            <div class="form-group">
                <label for="category_name">Category Name</label>
                <input type="text" class="form-control" id="category_name" name="category_name" required>
            </div>
            <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
        </form>
    </div>

    <!-- Selected Category and Skills Column -->
    <div class="col-md-6 form-container">
        <?php if (isset($selected_category_name)): ?>
            <h4>Skillsets of <?php echo htmlspecialchars($selected_category_name); ?></h4>
          
            <ul class="list-group">
                <?php foreach ($skills as $skill): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?php echo htmlspecialchars($skill['skillset_name']); ?></span>
                        <span>
                            <!-- Delete Icon -->
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="skillset_id" value="<?php echo $skill['id']; ?>">
                                <input type="hidden" name="category_id" value="<?php echo $selected_category_id; ?>">
                                <button type="submit" name="delete_skillset" class="btn btn-link"><i class="bi bi-trash"></i></button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <h4 class="mt-4">Add New Skillset</h4>
            <form method="post" action="">
                <input type="hidden" name="category_id" value="<?php echo $selected_category_id; ?>">
                <div class="form-group">
                    <label for="skillset_name">Skillset Name</label>
                    <input type="text" class="form-control" id="skillset_name" name="skillset_name" required>
                </div>
                <button type="submit" name="add_skillset" class="btn btn-primary">Add Skillset</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="form-group">
                        <label for="edit_category_name">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    <button type="submit" name="edit_category" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" role="dialog" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Delete Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" action="">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <p>Are you sure you want to delete this category and all the skillsets of it?</p>
                    <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                </form>
            </div>
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
    $('#editCategoryModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var name = button.data('name');

        var modal = $(this);
        modal.find('#edit_category_id').val(id);
        modal.find('#edit_category_name').val(name);
    });

    $('#deleteCategoryModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');

        var modal = $(this);
        modal.find('#delete_category_id').val(id);
    });
</script>

		

</body>
</html>
