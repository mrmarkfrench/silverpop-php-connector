<?php
//ini_set('display_errors',1);
//ini_set('display_startup_errors',1);
//error_reporting(-1);

/*
//SK 20140306 added
	@param notify_email	string	optional e-mail address to notify - in authData.ini
	@param sftp_config	array	in authData.ini (or sftpData.ini) with 
	@param sftpUrl	string	url of sftp server
	@param username	string	optional - username to connect to ftp IF different from API connection
	@param password	string	optional - password to connect to ftp IF different from API connection
	@param mail_to	string	required - e-mail address to send exported file to 
	@param mail_from	string	e-mail address to send exported file from
	@param mail_cc	string	optional - e-mail address to send exported file to
	@param mail_bcc	string	optional - e-mail address to send exported file to

	@param sftp	SFTP connection using phpseclib (see GitHub)
*/

//SK Using calendar functions 
date_default_timezone_set('GMT');

if (!defined('__DIR__')) define('__DIR__', dirname(__FILE__)); //SK 20140304 older php versions

require_once __DIR__ .'/SilverpopConnector.php'; #php5.3+


//SK 20140228 Use phpseclib to get the exported file from the SFTP server
//SK 20140228 http://phpseclib.sourceforge.net/sftp/intro.html
//GitHub https://github.com/phpseclib/phpseclib

//Library needs to be in your include_path:
//set_include_path(get_include_path() . PATH_SEPARATOR . 'phpseclib/phpseclib');
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ .'/phpseclib/phpseclib');

include('Net/SFTP.php');
define('NET_SFTP_LOGGING', NET_SFTP_LOG_COMPLEX); //Turn on Logging - echo $sftp->getSFTPLog();

echo "\nScript started ".date('d-m-Y H:i:s')."\n";
echo "Parsing credentials file (authData.ini)...\n";
$credentials = parse_ini_file(__DIR__.'/authData.ini', true);
if (!empty($credentials['sftp_config'])) $sftp_config = $credentials['sftp_config'];
//$sftp_config = parse_ini_file(__DIR__.'/sftpData.ini', true); //use this if you want a separate file for SFTP details.

echo "Setting base URL...\n";
SilverpopConnector::getInstance($credentials['silverpop']['baseUrl']);
//please make sure the sftpUrl matches the silverpop baseUrl (engage0 > transfer0)

/*
//Only use when oAuth not available and permissions are set up correctly.
if (!empty($credentials['silverpop']['username'])) {
	echo "Authenticating to XML API...\n";
	SilverpopConnector::getInstance()->authenticateXml(
		$credentials['silverpop']['username'],
		$credentials['silverpop']['password']
		);
} else {
	echo "No XML credentials. Performing combined authentication against REST...\n";
}
*/
//SK TODO Cache access token 
$accessToken = ""; $expiry = time() + (3 * 60 * 60);
if (!empty($accessToken)) { 
	echo "Authenticating to REST API with existing session...\n";	
	SilverpopConnector::getInstance()->initialiseRest($accessToken, $expiry);
	SilverpopConnector::getInstance()->setAuthParams(
		$credentials['silverpop']['client_id'],
		$credentials['silverpop']['client_secret'],
		$credentials['silverpop']['refresh_token']
	);
} else {
	echo "Authenticating to REST API...\n";
	SilverpopConnector::getInstance()->authenticateRest(
		$credentials['silverpop']['client_id'],
		$credentials['silverpop']['client_secret'],
		$credentials['silverpop']['refresh_token']
		);
}

$token = SilverpopConnector::getInstance()->getAccessToken();
echo "My current access token: {$token} \n";

echo "Exporting recipient data...\n";

//get everything from the last 30 days:
//$result = SilverpopConnector::getInstance()->rawRecipientDataExport();

$flags = array();
$optParams = array();

$flags[] = "MOVE_TO_FTP"; //Please leave this flag to allow the file to be sent via e-mail.


$flags[] = "HARD_BOUNCES";
$flags[] = "SOFT_BOUNCES";

//$flags[] = "ALL_NON_EXPORTED";

//SK 20140228 use details from $sftp_config if they exist, else try credentials 
if (!empty($sftp_config['notify_email'])) {
	$optParams["EMAIL"] = $sftp_config['notify_email'];
} elseif (!empty($credentials['silverpop']['notify_email'])) {
	$optParams["EMAIL"] = $credentials['silverpop']['notify_email'];
}

//Use flags & optional parameters:
$result = SilverpopConnector::getInstance()->rawRecipientDataExport(null,null,null,$flags,$optParams);

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

echo "\nLoop completed in ".$pageLoadTotal." seconds on ".date('Y-m-d \T H:i:s.000P'); 




//SK 20140228 TODO Move into Silverpop Connector
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
				sleep(10); //number of seconds
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
		$response .= "\nJob successfully completed. Looped {$attempts} times. "; 
	}
	//return $isCompleted;
	return $response;
}



echo "\nDownloading file {$filePath} from SFTP..."; 

$sftp_url = null; $sftp_user = null; $sftp_pass = null;
$mail_to = ''; $mail_from = 'no-reply@domain.com'; 
$mail_cc = null; $mail_bcc = null; //only use if specified


if (!empty($sftp_config)) { 
	if (!empty($sftp_config['sftpUrl']))	$sftp_url = $sftp_config['sftpUrl']; ///www.domain.tld
	if (!empty($sftp_config['mail_to']))	$mail_to = $sftp_config['mail_to']; 
	if (!empty($sftp_config['mail_from']))	$mail_from = $sftp_config['mail_from']; //maybe use notify_email if it exists
	if (!empty($sftp_config['mail_cc']))	$mail_cc = $sftp_config['mail_cc']; 
	if (!empty($sftp_config['mail_bcc']))	$mail_bcc = $sftp_config['mail_bcc'];  

//If no specific SFTP user/pass found (needs to be an admin account!), Silverpop user and pass used. 
	if (!empty($sftp_config['username']) && !empty($sftp_config['password'])) { 
		$sftp_user = $sftp_config['username'];
		$sftp_pass = $sftp_config['password'];
	} else {
		$sftp_user = $credentials['silverpop']['username'];
		$sftp_pass = $credentials['silverpop']['password'];
	}
} else { exit('No SFTP details found. Please configure the data file.'); }

$sftp_dir = 'download';
$filename = $filePath; //silverpop returns filename, not actual path.
$filepath = $sftp_url.'/'.$sftp_dir.'/'.$filename; //only used in message

//SK 20140227 Needs a $file to attach or a $filepath
//memory stuff: contents of file max 64kb, see also file_get_contents or use output buffering (ob_start)
$file = null;

if (!empty($filename)) {
	//SK 2014028 uses phpseclib, see includes at the top.
	$sftp = new Net_SFTP($sftp_url);
	if (!$sftp->login($sftp_user, $sftp_pass)) {
	    exit('Login Failed');
	}
	//change directory
	if (!empty($sftp_dir)) $sftp->chdir($sftp_dir);

	echo "\n--Made sftp connection \n";
	if (empty($mail_to)) { 
		//save on server - Check permissions & security
		//$file = $sftp->get($filename, $filename);
		//	//get contents of local file and use those
		//if (!empty($file)) $att_contents = file_get_contents($filename);
		//$file = $att_contents; 
		//if (file_exists($filename)) unlink($filename); //try to remove local file
		echo "No e-mail address found to send the file to. The file is stored on the SFTP server ({$filepath}).";
	} else {
		//save in string
		$file = $sftp->get($filename);
		if (empty($file))  { echo $sftp->getSFTPLog(); } 
		//else { echo "\n\n--File found: \n".$file; }
	}

}


if (empty($mail_to)) { exit('No file or e-mail address found. End of script.'); }

echo "\nSending file {$filePath} via e-mail..."; 

	//SK 20140227 Send e-mail, initialise vars.
	$headers = array();	$mail_subject = ''; 
	$mail_message = array(); $mail_body = ''; 
	

	$headers[] = 'From: '.	$mail_from;  //ALWAYS include a FROM address
	$headers[] = 'Reply-To: '.	$mail_from;  	
	$headers[] = 'Return-Path: '.	$mail_from;
	if (!empty($mail_cc)) $headers[] = 'CC: '.	$mail_cc;
	if (!empty($mail_bcc)) $headers[] = 'BCC: '.	$mail_bcc;

	$mail_subject = "Silverpop - A new file has been exported.";

	$mail_message[] = "Hi";
	$mail_message[] = "An export file has been created.";
	

	if (!empty($file)) { 
		$mail_message[] = "Filename: {$filename}";
		$mail_message[] = "Please find the file attached.";
	}
	
	if (!empty($filepath)) {
		$sftp_address = str_replace($filename,"",$filepath);
		$mail_message[] = "The file {$filename}";
		$mail_message[] = "is stored on the SFTP server at {$sftp_address}";
		if (!empty($sftp_user)) $mail_message[] = "and can be downloaded by ".$sftp_user; 
	}
	
	$mail_message[] = ""; //extra new line
	
	
	$mail_body = implode("\r\n", $mail_message);

	//SK 20140228 	use contents of remote file
	echo sendMail($mail_to, $mail_subject, $mail_body, $file, $filename, $headers);	
	
	

//SK 20140228 Send an e-mail with an attachment.		
//Based on http://php.net/manual/en/function.mail.php
//see also http://stackoverflow.com/questions/9519588/send-php-html-mail-with-attachments	
    function sendMail($to, $subject, $message, $file_contents='', $file_name = '', $_headers = array()) {
        $rn = "\r\n";
        //SK 20140227 Message is text only so need one boundary identifier for 2x Content-Type
        $boundary = 'PHP-mixed-'.md5(rand()); 

// Headers
		$headers = 'X-Mailer: PHP v' . phpversion() . $rn; //SK
        $headers .= 'Mime-Version: 1.0' . $rn;		          
		//$headers .= 'X-Originating-IP: ' . $_SERVER['SERVER_ADDR'] . $rn; //SK
		
		if (!empty($_headers)) {
		$headers .= implode($_headers, $rn); //SK
		}
		
		$headers .= $rn;        
        $headers .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . $rn;
        
//Body 
        $msg = "";//$rn;
        $msg .= '--' . $boundary . $rn; 
        $msg .= 'Content-Type: text/plain; format=flowed; charset="iso-8859-1"' . $rn; //SK
        $msg .= 'Content-Transfer-Encoding: 7bit' . $rn; //SK
        $msg .= $rn . $message . $rn;//$rn . $message . $rn;

//Attachment
        //TODO phpseclib can also get filetype etc, currently zip hardcoded
        if (!empty($file_contents)  && !empty($file_name)) {

        	$attachment = chunk_split(base64_encode($file_contents));
 
            	if (!empty($attachment))   {
        $msg .= $rn . '--' . $boundary . $rn;
        $msg .= 'Content-Type: application/zip; name="' . basename($file_name) . '"' . $rn; //SK
        $msg .= 'Content-Transfer-Encoding: base64' . $rn;            
        $msg .= 'Content-Disposition: attachment' .$rn;
        $msg .= $rn . $attachment;// . $rn . $rn;
        		}            
        }
        
// Fin
        $msg .= $rn . '--' . $boundary . '--' . $rn;

// Function mail()
        return mail($to, $subject, $msg, $headers);
    }	




?>
