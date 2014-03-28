#!/usr/bin/php
<?php
require_once __DIR__.'/SilverpopConnector.php';

echo "Parsing credentials file...\n";
$credentials = parse_ini_file(__DIR__.'/authData.ini', true);

echo "Setting base URL...\n";
SilverpopConnector::getInstance($credentials['silverpop']['baseUrl']);
if (!empty($credentials['silverpop']['username'])) {
	echo "Authenticating to XML API...\n";
	SilverpopConnector::getInstance()->authenticateXml(
		$credentials['silverpop']['username'],
		$credentials['silverpop']['password']
		);
} else {
	echo "No XML credentials. Performing combined authentication against REST...\n";
}
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
	$listId      = null;
	$listMembers = PHP_INT_MAX;
	foreach ($result as $list) {
		if ($list->TYPE != 0) {
			// Only want a database, not some other list type
			continue;
		}
		if ($listMembers <= 1 || ($list->SIZE > 1 && $list->SIZE < $listMembers)) {
			$listId      = (string)$list->ID;
			$listMembers = (string)$list->SIZE;
			$listName    = (string)$list->NAME;
		}
	}
	if (empty($listId)) {
		echo " -- No populated lists found!\n";
		die("Exiting");
	} else {
		echo " -- Selected list \"{$listName}\" ({$listId}), with {$listMembers} members.\n";
	}
} else {
	echo " -- No lists found!\n";
	die("Exiting");
}

echo "Retrieving list meta data...\n";
$result = SilverpopConnector::getInstance()->getListMetaData($listId);
$columns = array();
foreach ($result->COLUMNS->COLUMN as $column) {
	$columns[] = (string)$column->NAME;
}
echo ' -- Found '.count($columns)." columns.\n";

echo "Exporting recipient data...\n";

$result = SilverpopConnector::getInstance()->rawRecipientDataExport();

$jobId    = $result['jobId'];
$filePath = $result['filePath'];
echo " -- Job Id: {$jobId}, file path: {$filePath} ";
//900k csv/90k zip took 1min

//SK 20140203 time page load, part 1 of 2
$time = microtime(); $time = explode(' ', $time); $time = $time[1] + $time[0];
$pageLoadStart = $time;

echo getJobStatusLoop($jobId);

//SK 20140203 time page load, part 2 of 2
$time = microtime(); $time = explode(' ', $time); $time = $time[1] + $time[0];
$pageLoadFinish = $time;
$pageLoadTotal = round(($pageLoadFinish - $pageLoadStart), 4);

echo 'Loop completed in '.$pageLoadTotal.' seconds on '.date('Y-m-d\TH:i:s.000P'); 

//See also: https://github.com/Boardroom/smart-popup/blob/master/test_sp_export_api.php
function getJobStatusLoop($jobId, $numAttempts = 600) {
	$isCompleted = false;
	$attempts = 0;
	$response = null;
	//sleep(300); // Sleep for the first five minutes no matter what.
	while (!$isCompleted && $attempts < $numAttempts) {
		$jobStatus = SilverpopConnector::getInstance()->getJobStatus($jobId);
		switch ($jobStatus) {
			case "COMPLETE":
				$isCompleted = true;
			break;
			case "RUNNING":
			case "WAITING":
				// Give the job time to complete.
				sleep(60);
			break;
			case "CANCELED": //yes Silverpop spelled this wrong.
			case "ERROR":
			default:
				$response = "\nError: Silverpop get job status execution failed. Attempts: {$attempts} ";
			exit(-1);
			break;
		}
		// Increment the attempts; limit attempts.
		$attempts++;
	}
	if ($isCompleted === false) {
		//$isCompleted = $response;
		$response .= "\nNot completed. {$attempts} attempts. ";
	} else { 
		$response .= "\nJob successfully completed. {$attempts} attempts. "; 
	}
	//return $isCompleted;
	return $response;
}
/*
echo "Exporting list {$listId}...\n";
$result = SilverpopConnector::getInstance()->exportList(
	$listId,
	0,
	time(),
	'ALL',
	'CSV',
	$columns);
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
$result = SilverpopConnector::getInstance()->streamExportFile($filePath, null);
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
*/

