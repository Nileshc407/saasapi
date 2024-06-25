<?php
require_once '../include/DbHandler.php';
require_once '../include/OffersHandler.php';
require_once '../include/UserHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

use lib\Slim\Middleware\SessionCookie;
session_start();
error_reporting(0);
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim(
	array(
		'cookies.encrypt' => true,
		'cookies.secret_key' => 'my_secret_key',
		'cookies.cipher' => MCRYPT_RIJNDAEL_256,
		'cookies.cipher_mode' => MCRYPT_MODE_CBC
    )
);

$app->add(new \Slim\Middleware\SessionCookie(array(
    'expires' => '20 minutes',
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => false,
    'name' => 'slim_session',
    'secret' => '',
    'cipher' => MCRYPT_RIJNDAEL_256,
    'cipher_mode' => MCRYPT_MODE_CBC
)));
	function authenticate(\Slim\Route $route) 
	{
		// Getting request headers
		$headers = apache_request_headers();
		$response = array();
		$app = \Slim\Slim::getInstance();
		
		$app->config('debug', true);
		$json=$app->request->getbody();
		$request_array=json_decode($json,true);
		
		$comp = new CompHandler();
		$dbHandlerObj = new DbHandler();
		
		if (isset($headers['Authorization'])) {
			$api_key = $headers['Authorization'];
		}
		else
		{
			$api_key = $request_array['Authorization'];
		}
		if ($api_key !=Null) 
		{
			if (!$comp->isValidApiKey($api_key)) 
			{
				$response["error"] = true;
				$response["message"] = "Access Denied. Invalid Api key";
				echoRespnse(401, $response);
				$app->stop();			
				
			} else {
				
				global $Company_id;
				$result = $comp->getCompanyDetails($api_key);

				if ($result != NULL) {
					
				} else {
					$response["error"] = true;
					$response["message"] = "Invalid API Username";
					echoRespnse(404, $response);
				}
				
					session_cache_limiter(false);			
			}
		} else {
			$response["error"] = true;
			$response["message"] = "Api key is misssing";
			echoRespnse(400, $response);
			$app->stop();
		}
	}
	$app->post('/getimages','authenticate', function() use ($app) 
	{
		$json=$app->request->getbody();
		$request_array=json_decode($json,true);
	
		verifyRequiredParams(array('membershipid'),$request_array);			
		$response = array();

		$param['membershipid'] = $request_array['membershipid'];			
		$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
		$userObj = new UserHandler();
		$dbOffersObj = new OffersHandler();
		$phashObj = new PassHash();
				
		$user = $userObj->getMemberDetails($param['membershipid'],$param['phoneno']);
					 
		if ($user != NULL) 
		{
			$id = $user['id'];
			$Membership_ID = $user['Membership_ID'];
			$Current_point_balance = $user['Current_balance']-($user['Blocked_points']+$user['Debit_points']);
			if($Current_point_balance < 0)
			{
				$Current_point_balance=0;
			}
			else 
			{
				$Current_point_balance=$Current_point_balance;
			}
			$OfferDetails = $dbOffersObj->getOffersImages();
			if($OfferDetails !=Null)
			{				
				$offers=array();
				foreach ($OfferDetails as  $row) 
				{
					$offer_details = array('Sequence' => $row["Sequence"],'imageurl' => $row["Spl_Image"]);
					$offers[] =	$offer_details;		
				}
			}
			else
			{
				$offers = Null;	
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "OK";
			$response["fname"] = $user['fname'];
			$response["lname"] = $user['lname'];
			$response["tier"] = $user['Tier_name'];
			$response["pointbalance"] = $Current_point_balance;
			$response["alisename"] =  $_SESSION["Alise_name"];
			$response["offerimages"] = $offers;
		} 
		else 
		{
			$response['errorcode'] = 2003;
			$response['message'] = "Unable to Locate membership id";
		}			
		echoRespnse(201, $response);
	});
	$app->get('/offers','authenticate', function() use ($app) 
	{
		$json=$app->request->getbody();
		
		$request_array=json_decode($json,true);
		
		verifyRequiredParams(array('membershipid'),$request_array);			
		$response = array();

		$param['membershipid'] = $request_array['membershipid'];			
		$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
		
		$userObj = new UserHandler();
		$dbHandlerObj = new DbHandler();
		$dbOffersObj = new OffersHandler();
		$phashObj = new PassHash();
		
		$SellerDetails=$dbHandlerObj->superSellerDetails();

		$Super_Seller_id = $SellerDetails['id'];
		$Super_Seller_Name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
		$Seller_timezone_entry =$SellerDetails['timezone_entry'];

		$timezone_entry = $Seller_timezone_entry;
		$logtimezone = $timezone_entry;
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$Todays_date_time = $date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');	
			
				
		$user = $userObj->getMemberDetails($param['membershipid'],$param['phoneno']);
	
		if ($user != NULL) 
		{
			$id = $user['id'];
			$Membership_ID = $user['Membership_ID'];
			$OfferDetails = $dbOffersObj->getOffers();
											
			$offers=array();
			while ($offrs = $OfferDetails->fetch_array()) 
			{
				$offers[] =  array(
					'outlet'    =>  $offrs["Last_name"].' '.$offrs["Last_name"],
					'name'    =>  $offrs["Offer_name"],
					'fromdate'  => $offrs["From_date"],
					'todate'  => $offrs["Till_date"],
					'buy'  => $offrs["Buy_item"],
					'free'  => $offrs["Free_item"]
				);			
			}
		
			$response["offers"] = $offers;
			$response["errorcode"] = 1001;
			$response["message"] = "OK";			
		} else 
		{
			$response['errorcode'] = 2003;
			$response['message'] = "Unable to Locate membership id";
		}			
		echoRespnse(201, $response);
	});
function verifyRequiredParams($required_fields,$request_array) 
{
    $error = false;
    $errorCount = 0;
    $error_fields = "";
    $request_params = array();
    // $request_params = $_REQUEST;
    $request_params = $request_array;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
		
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) 
       	{
				$error = true;
				$error_fields .= $field . ', ';			
        }
    }
	if ($error) {
        $response = array();
        $app = \Slim\Slim::getInstance();
		$response["errorcode"] = 3121;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}
function validatePhone($phoneno) {
    $app = \Slim\Slim::getInstance();
    $filtered_phone_number = filter_var($phoneno, FILTER_SANITIZE_NUMBER_INT);
    // Remove "-" from number
    $phone_to_check = str_replace("-", "", $filtered_phone_number);
  
    if (strlen($phone_to_check) < 10 || strlen($phone_to_check) > 14) {
        $response["errorcode"] = 3122;
        $response["message"] = 'Phone number is not valid';
        echoRespnse(400, $response);
        $app->stop();
    } 
}
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$response["errorcode"] = 3120;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    $app->status($status_code);
    $app->contentType('application/json');
    echo json_encode($response);
}
$app->run();
?>