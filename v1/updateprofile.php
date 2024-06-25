<?php
require_once '../include/DbHandler.php';
require_once '../include/UserHandler.php';
require_once '../include/SendEmailHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/LogHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

use lib\Slim\Middleware\SessionCookie;
session_start();
// error_reporting(0);
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

	function authenticate(\Slim\Route $route) {
		// Getting request headers
		$headers = apache_request_headers();
		$response = array();
		$app = \Slim\Slim::getInstance();
		
		$app->config('debug', true);
		
		$json=$app->request->getbody();
		$request_array=json_decode($json,true);
		if (isset($headers['Authorization'])) {
			$api_key = $headers['Authorization'];
		}
		else
		{
			$api_key = $request_array['Authorization'];
		}
		
		 $comp = new CompHandler();
		 $dbHandlerObj = new DbHandler();

		// Verifying Authorization Header
		// if (isset($headers['Authorization'])) {
			if ($api_key !=Null) {

			// get the api key
			//$api_key = $headers['Authorization'];
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
				// get user primary key id
				// $Company_id = $comp->getCompanyDetails($api_key);
				$result = $comp->getCompanyDetails($api_key);
				// print_r($result);
				// fetch task
				// $result = $db->getTask($task_id, $user_id);

				if ($result != NULL) {
					
					/* // $response["error"] = false;
					$_SESSION["company_id"] = $result["Company_id"];
									
					// echoRespnse(200, $response); */
				} 
				else 
				{
					$response["error"] = true;
					$response["message"] = "Invalid API Username";
					echoRespnse(404, $response);
				}
				session_cache_limiter(false);			
			}
			
		} else {
			
			// api key is missing in header
			$response["error"] = true;
			$response["message"] = "Api key is misssing";
			echoRespnse(400, $response);
			$app->stop();
		}
	}
	$app->post('/updateprofile','authenticate', function() use ($app) {
          
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			// check for required params
            verifyRequiredParams(array('membershipid'),$request_array);			
            verifyRequiredParams(array('phoneno','email'),$request_array['profile_data']);			
            $response = array();

            // reading post params

			$param['membershipid'] = $request_array['membershipid'];
			$param['fname'] = $request_array['profile_data']['fname'];							
			$param['lname'] = $request_array['profile_data']['lname'];							
			$param['email'] = strtolower($request_array['profile_data']['email']);							
			$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['profile_data']['phoneno'];
			$param['sex'] = $request_array['profile_data']['sex'];	
			$param['anniversarydate'] = $request_array['profile_data']['anniversarydate'];				
			$param['dob'] = $request_array['profile_data']['dob'];	
			
			/*$param['married'] = $request_array['profile_data']['married'];	
			$param['anniversarydate'] = $request_array['profile_data']['anniversarydate'];	

			$param['address1'] = $request_array['address_data']['address1'];	
			$param['address2'] = $request_array['address_data']['address2'];	
			$param['address3'] = $request_array['address_data']['address3'];	
			$param['address4'] = $request_array['address_data']['address4'];	
			$param['city'] = $request_array['address_data']['city'];	
			$param['state'] = $request_array['address_data']['state'];	
			$param['country'] = $request_array['address_data']['country'];	
			$param['pincode'] = $request_array['address_data']['pincode'];	
			 */
			
			validateEmail($param['email']);
      		validatePhone($request_array['profile_data']['phoneno']);
			
			
			$userObj = new UserHandler();
			$dbHandlerObj = new DbHandler();
			$sendEObj = new SendEmailHandler();
			$logHObj = new LogHandler();
			$userObj = new UserHandler();
			$phashObj = new PassHash();
			
			$SellerDetails=$dbHandlerObj->superSellerDetails();
			// print_r($SellerDetails);    
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
				
			$user = $userObj->getUserByMembership($param['membershipid'],$param['phoneno']);

			if ($user != NULL) 
			{
				 // echo "_SESSION-Company_id--".$_SESSION['company_id'];					 
			
				// $response["error"] = false;
				$id = $user['id'];
				$Membership_ID = $user['Membership_ID'];
				
				if($param['fname']){
					
					$MemberPara['First_name']=$param['fname'];
				}							
				if($param['lname']){
					
					$MemberPara['Last_name']=$param['lname'];
				}								
				if($param['email']){
					if($user['email'] != $param['email'])
					{
						$MemberPara['User_email_id']=$phashObj->string_encrypt($param['email']);
					}
				}
				if($param['phoneno']){
					if($user['phoneno'] != $param['phoneno'])
					{
						$MemberPara['Phone_no']=$phashObj->string_encrypt($param['phoneno']);
					}
				}
				
				if($param['sex']){
					
					$MemberPara['Sex']=$param['sex'];
				}
				if($param['dob']){
					
					$MemberPara['Date_of_birth']=date('Y-m-d',strtotime($param['dob']));
				}
				if($param['anniversarydate']){
					
					$MemberPara['Wedding_annversary_date']=date('Y-m-d',strtotime($param['anniversarydate']));
				}
				/* if(!empty($param['address1']) || !empty($param['address2'] || !empty($param['address3']) || !empty($param['address4']))  ){
					
					$Current_address=$param['address1'].','.$param['address2'].','.$param['address3'].','.$param['address4'];
				}
				if($Current_address){
					
					$MemberPara['Current_address']=$phashObj->string_encrypt($Current_address);;
				}
				
				if($param['married']){
					
					$MemberPara['Married']=$param['married'];
				}
				if($param['anniversarydate']){
					
					$MemberPara['Wedding_annversary_date']=date('Y-m-d',strtotime($param['anniversarydate']));
				}
				if($param['city']){
					
					$MemberPara['City']=$param['city'];	
				}
				if($param['state']){
					
					$MemberPara['State']=$param['state'];	
				}
				if($param['country']){
					
					$MemberPara['Country']=$param['country'];
					$MemberPara['Country_id']=$param['country'];
				}
				if($param['pincode']){
					
					$MemberPara['Zipcode']=$param['pincode'];
				}*/	
				
				$MemberPara['Update_date']=$Todays_date_time;								
				
				$MemberBalance = $dbHandlerObj->updateMemberProfile($MemberPara,$user['id']);
				// if($MemberBalance){
				if ($MemberBalance == USER_PROFILE_UPDATED_SUCCESSFULLY) 
				{
					$response["errorcode"] = 1001;
					$response["message"] = "Profile Updated Successfully";


					/* API Erro Log */

						$APILogParam['Company_id'] =$_SESSION['company_id'];
						$APILogParam['Trans_type'] = 999;
						$APILogParam['Outlet_id'] = $Super_Seller_id;
						$APILogParam['Bill_no'] = 0;
						$APILogParam['Card_id'] = $user['Membership_ID'];
						$APILogParam['Date'] = $Todays_date_time;
						$APILogParam['Json_input'] = $json;
						$APILogParam['Json_output'] = json_encode($response);
						// $APILog = $logHObj->insertAPILog($APILogParam,$user['id']); 
	
					/* API Erro Log */
					/* Log Entry  */
						$LogParam['Company_id'] =$_SESSION['company_id'];
						$LogParam['From_enrollid'] = $user['id'];
						$LogParam['From_emailid'] = $user['email'];
						$LogParam['From_userid'] = 1;
						$LogParam['To_enrollid'] = 0;
						$LogParam['Transaction_by'] = $user['fname'].' '.$user['lname'];
						$LogParam['Transaction_to'] = $user['fname'].' '.$user['lname'];
						$LogParam['Transaction_type'] = 'Update Profile';
						$LogParam['Transaction_from'] = 'API';
						$LogParam['Operation_type'] = '1';
						$LogParam['Operation_value'] = $user['fname'].' '.$user['lname'].' , Membership: '.$user['Membership_ID'];
						$LogParam['Date'] = $Todays_date_time;
						// $Log = $logHObj->insertLog($LogParam,$user['id']);
					/* Log Entry  */
				} 
				else if ($MemberBalance == USER_ALREADY_EXISTED) 
				{
          
				  $response["errorcode"] = 2030;
				  $response["message"] = "Email address already exist";
				}
				else if ($MemberBalance == USER_PHONE_ALREADY_EXISTED) 
				{
				  
				  $response["errorcode"] = 3123;
				  $response["message"] = "Phone number already exist";
				}
				else 
				{
					$response['errorcode'] = 3117;
					$response['message'] = "Unable to process at this time. Please try again";
				}	
			} 
			else 
			{
				// unknown error occurred
				$response['errorode'] = 2003;
				$response['message'] = "Unable to locate membership id";
			}				
            // echo json response
            echoRespnse(201, $response);
	});
	$app->post('/updatetoken','authenticate', function() use ($app) {
          
		$json=$app->request->getbody();
			
		$request_array=json_decode($json,true);
			
        verifyRequiredParams(array('membershipid','token'),$request_array);			
        	
        $response = array();

		$param['membershipid'] = $request_array['membershipid'];
		
		$userObj = new UserHandler();
		$dbHandlerObj = new DbHandler();
		
		$user = $userObj->getUserByMembership($param['membershipid'],$param['membershipid']);

		if ($user != NULL) 
		{
			$id = $user['id'];
			$Membership_ID = $user['Membership_ID'];
			
			$MemberPara['Fcm_token'] = $request_array['token'];						
			
			$MemberBalance = $dbHandlerObj->updateDeviceToken($MemberPara,$user['id']);
			
			if ($MemberBalance == USER_PROFILE_UPDATED_SUCCESSFULLY) 
			{
				$response["errorcode"] = 1001;
				$response["message"] = "Token Updated Successfully";
			} 
			else 
			{
				$response['errorcode'] = 3117;
				$response['message'] = "Unable to process at this time. Please try again";
			}	
		} 
		else 
		{
			$response['errorode'] = 2003;
			$response['message'] = "Unable to locate membership id";
		}				  
        echoRespnse(201, $response);
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
	// echo"---phoneno----".$phoneno."----<br>";
     // Allow +, - and . in phone number
    $filtered_phone_number = filter_var($phoneno, FILTER_SANITIZE_NUMBER_INT);
    // Remove "-" from number
    $phone_to_check = str_replace("-", "", $filtered_phone_number);
    // Check the lenght of number
    // This can be customized if you want phone number from a specific country
    // echo"---phone_to_check----".strlen($phone_to_check)."----<br>";
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