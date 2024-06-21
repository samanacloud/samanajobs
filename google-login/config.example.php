<?php
return [
    'google' => [
        'client_id' => 'YOUR_GOOGLE_CLIENT_ID',
        'client_secret' => 'YOUR_GOOGLE_CLIENT_SECRET',
        'redirect_uri' => 'https://your-domain.com/google-login/callback.php'
    ],
    'database' => [
        'host' => 'YOUR_DATABASE_HOST',
        'db' => 'YOUR_DATABASE_NAME',
        'user' => 'YOUR_DATABASE_USER',
        'pass' => 'YOUR_DATABASE_PASSWORD'
    ],
    'google_sheets' => [
        'api_key' => 'YOUR_GOOGLE_SHEETS_API_KEY', // Replace with your Google Sheets API key
        'spreadsheet_id' => 'YOUR_GOOGLE_SHEETS_SPREADSHEET_ID',
    ]
];

function load_menu($directory = '.') {
    $files = scandir($directory);
    $modules = [];

    foreach ($files as $file) {
        if (preg_match('/^([a-zA-Z0-9]+)_([a-zA-Z0-9]+)\.php$/', $file, $matches) && strpos($file, '_') !== 0) {
            $section = ucfirst($matches[1]);
            $module = ucfirst(str_replace('_', ' ', $matches[2]));
            if (!isset($modules[$section])) {
                $modules[$section] = [];
            }
            $modules[$section][] = ['name' => $module, 'file' => $file];
        }
    }

    ksort($modules);

    // Add Home section at the beginning
    $home = ['Home' => [['name' => 'Home', 'file' => 'index.php']]];

    return $home + $modules;
}
?>

