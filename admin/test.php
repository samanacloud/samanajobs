<?php
require '../google-login/vendor/autoload.php';

use Google\Client;
use Google\Service\Sheets;

// Set up the client
$client = new Client();
$client->setApplicationName('Google Sheets API PHP Quickstart');
$client->setScopes([Sheets::SPREADSHEETS_READONLY]);
$client->setDeveloperKey('AIzaSyAzQELIaDqpgeKTq1Pqxb59wxb3E-xCY-o');

// Set up the Sheets service
$service = new Sheets($client);

// The ID of the spreadsheet to retrieve data from
$spreadsheetId = '2PACX-1vS1hZYyDvw2uQ9p6mHjzb0N23tF8sqxNpvQBCEu6YqHCf5AbxxkNdVipPhhbW0TFtjWhaU12qKewzVx';

// The range of data to retrieve
$range = 'Sheet1!A1:E'; // Adjust this range as needed

// Fetch the data from the spreadsheet
$response = $service->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();

if (empty($values)) {
    print "No data found.\n";
} else {
    foreach ($values as $row) {
        // Print or process each row
        print_r($row);
    }
}
?>
