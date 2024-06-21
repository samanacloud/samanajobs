<?php

// Register here the page information
$page_title = "404 Page Not Found";

// Include PHP Scripts HERE

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
        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 70vh;
            text-align: center;
        }
        .error-container img {
            max-width: 100%;
            height: auto;
            margin-bottom: 20px;
        }
        .error-container h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        .error-container p {
            font-size: 1.5rem;
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
                </div>
            </div>
        </div>
    </header>

    <!-- Error Content -->
    <div class="container error-container">
        <img src="../images/404.png" alt="404 Error">
        <h1>Page Not Found</h1>
        <p>Sorry, but the page you are looking for does not exist.</p>
		 <button type="button" class="btn btn-warning" onclick="location.href='index.php';">Home</button>
    </div>
    <!-- End of Error Content -->

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