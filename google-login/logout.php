<?php
session_start();
session_destroy();
header('Location: https://jobs.samana.cloud');
exit();
?>
