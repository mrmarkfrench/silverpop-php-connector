#!/usr/bin/php
<?php
require_once __DIR__.'/SilverpopConnector.php';

echo "Parsing credentials file...\n";
$credentials = parse_ini_file(__DIR__.'/authData.ini', true);

echo "Setting base URL...\n";
SilverpopConnector::getInstance($credentials['silverpop']['baseUrl']);
echo "Authenticating to XML API...\n";
SilverpopConnector::getInstance()->authenticateXml(
	$credentials['silverpop']['username'],
	$credentials['silverpop']['password']
	);
echo "Authenticating to REST API...\n";
SilverpopConnector::getInstance()->authenticateRest(
	$credentials['silverpop']['client_id'],
	$credentials['silverpop']['client_secret'],
	$credentials['silverpop']['refresh_token']
	);

echo "Retrieving lists...\n";
$result = SilverpopConnector::getInstance()->getLists();
if (count($result)) {
	echo " -- Found ".count($result)." lists.\n";
	$listId      = empty($result[0]['ID'])   ? null : (string)$result[0]['ID'];
	$listMembers = empty($result[0]['SIZE']) ? null : (string)$result[0]['SIZE'];
	foreach ($result as $list) {
		if ($listMembers <= 1 || ($list->SIZE > 1 && $list->SIZE < $listMembers)) {
			$listId      = (string)$list->ID;
			$listMembers = (string)$list->SIZE;
		}
	}
	if (empty($listMembers)) {
		echo " -- No populated lists found!\n";
		die("Exiting");
	} else {
		echo " -- Selected list {$listId}, with {$listMembers} members.\n";
	}
} else {
	echo " -- No lists found!\n";
	die("Exiting");
}

echo "Exporting list {$listId}...\n";
$result = SilverpopConnector::getInstance()->exportList($listId, 0, time());
$jobId    = $result['jobId'];
$filePath = $result['filePath'];
$filePath = str_replace('/download/', '', $filePath);

echo "Waiting for export job to complete...\n";
$done = false;
while ($done == false) {
	$result = SilverpopConnector::getInstance()->getJobStatus($jobId);
	echo " -- Job {$jobId} status is {$result}";
	switch ($result) {
		case 'COMPLETE':
			echo "\n";
			$done = true;
			break;
		case 'ERROR':
			echo "\n";
			exit;
		default:
			for ($i=0; $i<15; $i++) {
				echo '.';
				sleep(1);
			}
			echo ".\n";
	}
}

echo "Streaming exported file...\n";
$result = SilverpopConnector::getInstance()->streamExportFile($filePath, null);//'/tmp/SilverpopExportFile.csv');
echo " -- Parsing stream data...\n";
$csvLines = explode("\n", trim($result));
$header = str_getcsv(array_shift($csvLines));
$data = array();
foreach ($csvLines as $line) {
	$lineData = str_getcsv($line);
	$lineData = array_map('trim', $lineData);
	$data[] = array_combine($header, $lineData);
}
echo " -- Parsed ".count($data)." contacts from stream.\n";

echo "Selecting one contact to examine...\n";
$contact = $data[0];
echo " -- Contact email {$contact['Email']} (Name: {$contact['FirstName']} {$contact['LastName']}) selected.\n";

echo "Creating a new contact...\n";
$newContact = array(
	'Email' => 'silverpop.php.connector.test@example.com',
	'FirstName' => 'TestGuy',
	'LastName'  => 'ExampleFellow',
	);
$recipientId = SilverpopConnector::getInstance()->addRecipient($listId, $newContact);
echo " -- New contact created with ID {$recipientId}\n";

echo "Updating test contact...\n";
$updatedContact = $newContact;
$updatedContact['FirstName'] = "UpdatedTestValue";
SilverpopConnector::getInstance()->updateRecipient($listId, $recipientId, $updatedContact);

//echo "Adding Universal Behavior event to test contact...\n";
//$attributes = array(
//	'Email'        => $email,
//	'Tweet Id'     => '24',
//	'Author Id'    => '34',
//	'Retweeted Id' => '44',
//	'First Name'   => 'Richard',
//	'Last Name'    => 'Riddick',
//	'BrandTag'     => 'Silverpop',
//	'Entities'     => '#amplify',
//	);
//SilverpopConnector::getInstance()->createEvent(7, date('Y-m-d\TH:i:s.000P'), $attributes);

echo "Deleting test contact...\n";
$email = $newContact['Email'];
SilverpopConnector::getInstance()->removeRecipient($listId, $email, array('Email'=>$email));
