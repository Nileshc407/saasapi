<?php
require_once '../include/DbHandler.php';
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

	
	/* Class and Object  listing */
	/**
	 * Adding Middle Layer to authenticate every request
	 * Checking if the request has valid api key in the 'Authorization' header
	 */
	function authenticate(\Slim\Route $route) {
		
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
			// validating api key
			if (!$comp->isValidApiKey($api_key)) {
			   
				// api key is not present in users table
				$response["error"] = true;
				$response["message"] = "Access Denied. Invalid Api key";
				// $response["message"] =INVALID_KEY;
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
		} 
		else 
		{
			// api key is missing in header
			$response["error"] = true;
			$response["message"] = "Api key is misssing";
			echoRespnse(400, $response);
			$app->stop();
		}
	}
	$app->post('/member','authenticate', function() use ($app) 
	{     
		$json=$app->request->getbody();
		// to get an array so try following..
		$request_array=json_decode($json,true);
		
		// check for required params
		verifyRequiredParams(array('membershipid'),$request_array);			
		$response = array();

		// reading post params
		$param['membershipid'] = $request_array['membershipid'];			
		$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
		// validatePhone($request_array['phoneno'] );

		$userObj = new UserHandler();
		$dbHandlerObj = new DbHandler();
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
			
			if($user['Dob'] == Null)
			{
				$Date_of_birth = Null;	
			}
			else if($user['Dob'] == "1970-01-01 00:00:00")
			{
				$Date_of_birth = Null;
			}
			else
			{
				$Date_of_birth = date("Y-m-d", strtotime($user['Dob']));
			}
			
			if($user['Wedding_annversary_date'] == Null)
			{
				$Wedding_annversary_date = Null;	
			}
			else if($user['Wedding_annversary_date'] == "1970-01-01 00:00:00")
			{
				$Wedding_annversary_date = Null;
			}
			else
			{
				$Wedding_annversary_date = date("Y-m-d", strtotime($user['Wedding_annversary_date']));
			}
			
			
			$gainpoints = $userObj->get_cust_total_gain_points($user['id'],$user['Membership_ID']);
			
			$pendingpoints = $userObj->get_cust_total_pending_points($user['id'],$user['Membership_ID']);
			
			// $exp=explode($_SESSION["phonecode"],$user['phoneno']);
			// $phoneno1 = $exp[1];
			
			$dialcode_length=strlen($_SESSION["phonecode"]);
			$phoneno1 = substr($user['phoneno'],$dialcode_length);
			
			$Current_point_balance = $user['Current_balance']-($user['Blocked_points']+$user['Debit_points']);
			if($Current_point_balance < 0)
			{
				$Current_point_balance=0;
			}
			else 
			{
				$Current_point_balance=$Current_point_balance;
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "OK";
			$response["fname"] = $user['fname'];
			$response["lname"] = $user['lname'];
			$response["membershipid"] = $user['Membership_ID'];
			$response["email"] = $user['email'];
			// $response["phoneno"] = $user['phoneno'];
			$response["phoneno"] = $phoneno1;
			$response["tier"] = $user['Tier_name'];
			$response["tierredemptionratio"] = $user['Tier_redemption_ratio'];
			$response["companyredemptionratio"] = $_SESSION['redemption_ratio'];
			$response["gender"] = $user['Gender'];
			$response["dob"] = $Date_of_birth;
			$response["anniversarydate"] = $Wedding_annversary_date;
			$response["city"] = $user['city_name'];
			$response["state"] = $user['state_name'];
			$response["country"] = $user['country_name'];
			$response["pointbalance"] = $Current_point_balance;
			$response["redeempoints"] = $user['Total_reddems'];
			$response["purchaseamount"] = number_format($user['total_purchase'],2);
			$response["gainpoints"] = $gainpoints;
			$response["debitpoints"] = $user['Debit_points'];
			$response["blockpoints"] = $user['Blocked_points'];
			$response["bonuspoints"] = $user['Total_topup_amt'];
			$response["pendingpoints"] = $pendingpoints;
		} 
		else 
		{
			$response['errorode'] = 2003;
			$response['message'] = "Unable to locate membership id";
		}				
        echoRespnse(201, $response);
	});
	$app->post('/mytransaction','authenticate', function() use ($app) 
	{   
		$json=$app->request->getbody();
		$request_array=json_decode($json,true);
		$Company_id = $_SESSION["company_id"];

		verifyRequiredParams(array('membershipid'),$request_array);
		
		$response = array();

		$param['membershipid'] = $request_array['membershipid'];
		$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
		//$phash = new PassHash();
		$dbHandlerObj = new DbHandler();
		
		
		$userObj = new UserHandler();

		$user = $userObj->getMemberDetails($param['membershipid'],$param['phoneno']);
	
		if($user != NULL) 
		{
			$Enrollement_id = $user['id'];
			$Membership_ID = $user['Membership_ID'];
			
			$result = $userObj->my_transaction($Enrollement_id,$Company_id);
			if($result != Null)
			{
				$Report_Details = array();
				
				foreach($result as $row)
				{
					if($row['Trans_type'] == 12)
					{
						$Trans_type = "Online";
					}
					else if($row['Trans_type'] == 2)
					{
						$Trans_type = "POS";
					}
					$Report_Details[] = array("date"=> date("Y-M-d",strtotime($row['Trans_date'])),"ref"=> $row['Manual_billno'],"seller"=> $row['Seller_name'],"amount"=> number_format($row['Purchase_amount'],2),"type"=>$Trans_type);
				}
				
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["transdata"] = $Report_Details;
				
				
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
	 echoRespnse(201, $response);
		// exit;
	});
	$app->post('/mypoints','authenticate', function() use ($app) 
	{   
		$json=$app->request->getbody();
		$request_array=json_decode($json,true);
		$Company_id = $_SESSION["company_id"];

		verifyRequiredParams(array('membershipid'),$request_array);
		
		$response = array();

		$param['membershipid'] = $request_array['membershipid'];
		$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
		//$phash = new PassHash();
		$dbHandlerObj = new DbHandler();
		
		
		$userObj = new UserHandler();

		$user = $userObj->getMemberDetails($param['membershipid'],$param['phoneno']);
	
		if($user != NULL) 
		{
			$Enrollement_id = $user['id'];
			$Membership_ID = $user['Membership_ID'];
			
			$result = $userObj->my_points($Enrollement_id,$Company_id);
			if($result != Null)
			{
				$Report_Details = array();
				
				foreach($result as $row)
				{
					if($row['Trans_type'] == 12)
					{
						$Trans_type = "Online";
						$earnpoints = round($row['Loyalty_pts']);
					}
					else if($row['Trans_type'] == 2)
					{
						$Trans_type = "POS";
						$earnpoints = round($row['Loyalty_pts']);
					}
					else if($row['Trans_type'] == 1)
					{
						$Trans_type = "TopUp";
						$earnpoints = $row['Topup_amount'];
					}
					
					$Report_Details[] = array("date"=> date("Y-M-d",strtotime($row['Trans_date'])),"ref"=> $row['Manual_billno'],"seller"=> $row['Seller_name'],"redeempoints"=> round($row['Redeem_points']),"earnpoints"=> $earnpoints,"type"=>$Trans_type,"remark" => $row['Remarks']);
				}
				
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["transdata"] = $Report_Details;
				
				
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
	 echoRespnse(201, $response);
		// exit;
	}); 
/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields,$request_array) {
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
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
		$response["errorcode"] = 3121;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["errorcode"] = 3120;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}


function validatePhone($phoneno) {
    $app = \Slim\Slim::getInstance();
    
     // Allow +, - and . in phone number
    $filtered_phone_number = filter_var($phoneno, FILTER_SANITIZE_NUMBER_INT);
    // Remove "-" from number
    $phone_to_check = str_replace("-", "", $filtered_phone_number);
    // Check the lenght of number
    // This can be customized if you want phone number from a specific country
    // echo"---phone_to_check----".$phone_to_check."----<br>";
    if (strlen($phone_to_check) < 10 || strlen($phone_to_check) > 14) {
        $response["errorcode"] = 3122;
        $response["message"] = 'Phone number is not valid';
        echoRespnse(400, $response);
        $app->stop();
    } 
   

}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>