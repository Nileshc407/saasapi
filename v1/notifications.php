<?php
require_once '../include/DbHandler.php';
require_once '../include/NotifyHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';


use lib\Slim\Middleware\SessionCookie;
// session_start();
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


/* Class and Object  listing */ 
	$phash = new PassHash();
/* Class and Object  listing */


/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
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
		// Verifying Authorization Header
		if ($api_key !=Null)
		{
			// get the api key
			//$api_key = $headers['Authorization'];
			// validating api key
			if (!$comp->isValidApiKey($api_key)) 
			{ 
				// api key is not present in users table
				$response["error"] = true;
				$response["message"] = "Access Denied. Invalid Api key";
				// $response["message"] =INVALID_KEY;
				echoRespnse($response);
				$app->stop();
			} 
			else 
			{	
				global $Company_id;
			
				$comp = new NotifyHandler();
				$result = $comp->getCompanyDetails($api_key);
				
				if ($result != NULL) 
				{
					$_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];			
					$_SESSION["phonecode"] = $result["phonecode"];							
					$_SESSION["Company_Redemptionratio"] = $result["Redemptionratio"];		
					$_SESSION["Company_Currency"] = $result["Currency_name"];		
					$_SESSION["Points_used_flag"] = $result["Points_used_gift_card"];		
					$_SESSION["Min_gift_card_amount"] = $result["Minimum_gift_card_amount"];		
					$_SESSION["Gift_card_validity_days"] = $result["Gift_card_validity_days"];		
					
					$superSeller= $dbHandlerObj->superSellerDetails();
					
					$_SESSION["seller_id"] = $superSeller["id"];
					$_SESSION["seller_name"] = $superSeller["fname"].' '.$superSeller["lname"];
					$_SESSION["country"] = $superSeller["country"];
					$_SESSION["state"] = $superSeller["state"];
					$_SESSION["city"] = $superSeller["city"];
					$_SESSION["topup_Bill_no"] = $superSeller["topup_Bill_no"];
					$_SESSION["timezone_entry"] = $superSeller["timezone_entry"];
					
					// $timezone_entry = $superSeller["timezone_entry"];
					// $logtimezone = $timezone_entry;
					// $timezone = new DateTimeZone($logtimezone);
					$date = new DateTime();
					// $date->setTimezone($timezone);
					$Todays_date_time = $date->format('Y-m-d H:i:s');
					$Todays_date = $date->format('Y-m-d');
				} 
				else 
				{
					$response["error"] = true;
					$response["message"] = "Invalid API Username";
					echoRespnse($response);
				}
					session_cache_limiter(false);			
			}	
		} 
		else 
		{	
			// api key is missing in header
			$response["error"] = true;
			$response["message"] = "Api key is misssing";
			echoRespnse($response);
			$app->stop();
		}
	}
$app->post('/getunread','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$notificationObj = new NotifyHandler();
	
	$user = $notificationObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Member_name = $fname.' '.$lname;
		
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
		}
		
		$UnreadNotifications = $notificationObj->Get_unread_notifications($Enrollement_id,$Company_id);
		
		if($UnreadNotifications != Null)
		{
			$Notification_Details = array();
			foreach($UnreadNotifications as $row) 
			{
				
				$Notification_Details[] = array("id"=>$row['Id'],"subject"=>$row['Offer'],"date"=>date("d M Y", strtotime($row['Date'])));		
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["notificationdata"] = $Notification_Details;
		}
		else
		{
			$response["errorcode"] = 2012;
			$response["message"] = "No Data Found";
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
	// exit;
});
$app->post('/getread','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$notificationObj = new NotifyHandler();
	
	$user = $notificationObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Member_name = $fname.' '.$lname;
		
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
		}
		
		$UnreadNotifications = $notificationObj->Get_read_notifications($Enrollement_id,$Company_id);
		
		if($UnreadNotifications != Null)
		{
			$Notification_Details = array();
			foreach($UnreadNotifications as $row) 
			{
				$Notification_Details[] = array("id"=>$row['Id'],"subject"=>$row['Offer'],"date"=>$row['Date']);		
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["notificationdata"] = $Notification_Details;
		}
		else
		{
			$response["errorcode"] = 2012;
			$response["message"] = "No Data Found";
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
	// exit;
});
$app->post('/getdetails','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid','id'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$notification_id = $request_array['id'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$notificationObj = new NotifyHandler();
	
	$user = $notificationObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Member_name = $fname.' '.$lname;
		
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
		}
		
		$NotifDetails = $notificationObj->FetchNotifications($notification_id,$Company_id,$Enrollement_id);
		
		if($NotifDetails != Null)
		{
			$NotifUp = $notificationObj->Update_Notification($notification_id,$Company_id,$Enrollement_id);
			
			$Description = $NotifDetails['Offer_description'];
			
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["details"] = $Description;
			// echo $Description; exit;
		}
		else
		{
			$response["errorcode"] = 2012;
			$response["message"] = "No Data Found";
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
	// exit;
});
$app->post('/delete','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid','id'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$notification_id = $request_array['id'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$notificationObj = new NotifyHandler();
	
	$user = $notificationObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Member_name = $fname.' '.$lname;
		
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
		}
		
	
		$NotifDel = $notificationObj->Delete_Notification($notification_id,$Company_id,$Enrollement_id);
		if($NotifDel == true)
		{
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
		}
		else
		{
			$response["errorcode"] = 2012;
			$response["message"] = "No Data Found";
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
	// exit;
});
/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields,$request_array) {
    $error = false;
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
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["errorcode"] = 3121;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse($response);
        $app->stop();
    }
}
function Validate_redeem_points($Point_reedem,$Redemption_ratio,$Bill_amount)
{
	$Redeem_amount = ($Point_reedem/$Redemption_ratio); //.toFixed(2);
	
	$abc = round(1/$Redemption_ratio);	
	
	if($Point_reedem!="")
	{
		$Redeem_amount = ($Point_reedem/$Redemption_ratio);	
	}	
	
	$bb = ($Redeem_amount - $Bill_amount);  
	$Redeem_amount2 = $Bill_amount - $Redeem_amount;  
	if($bb >= $abc)
	{
		$Error_flag = 0000; //Equivalent Redeem Amount is More than Total Bill Amount
		$result12 =$Error_flag;
	}
	else if($Redeem_amount2 < 0)
	{
		$Redeem_amount = $Bill_amount;
		$result12 = $Redeem_amount;   //Adjust 1 point here..allow to redeem 1 point extra
	}
	else if($Redeem_amount<=$Bill_amount)
	{
		$result12 = $Redeem_amount; // Successfull
	}
	else if($Redeem_amount > $Bill_amount) 
	{
	  $Error_flag = 0000; //Equivalent Redeem Amount is More than Total Bill Amount
	 
	  $result12 =$Error_flag;
	}

	return $result12;
}
function getVoucher()
{
	$characters = '123456789';
	$string = '';
	$Voucher_no="";
	for ($i = 0; $i < 10; $i++) 
	{
		$Voucher_no .= $characters[mt_rand(0, strlen($characters) - 1)];
	}
	
	return $Voucher_no;
}
/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    // $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}
$app->run();
?>