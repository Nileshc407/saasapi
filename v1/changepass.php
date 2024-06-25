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



	/* Class and Object  listing */ 
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
		$api_key = $request_array['Authorization'];
		
		 $comp = new CompHandler();
		 $dbHandlerObj = new DbHandler();
		
		
		// Verifying Authorization Header
		// if (isset($headers['Authorization'])) {
		if ($api_key !=Null) {
			
			// get the api key
			// $api_key = $headers['Authorization'];
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
				} else {
					$response["error"] = true;
					$response["message"] = "Invalid API Username";
					echoRespnse(404, $response);
				}
				
					session_cache_limiter(false);			
					// $_SESSION['Company_id'] =  $Company_id;			
					// die;	
			}
			
		} else {
			
			// api key is missing in header
			$response["error"] = true;
			$response["message"] = "Api key is misssing";
			echoRespnse(400, $response);
			$app->stop();
		}
		// echo"----Company_id--in authenticate---".$_SESSION['company_id'];
	}

	$app->post('/changepassword','authenticate', function() use ($app) {
           
			// echo "---phonecode--".$_SESSION["phonecode"];
			// die;
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			// print_r($request_array);
			// die;
			
			// check for required params
            verifyRequiredParams(array('membershipid','oldpassword','newpassword','confirmpassword'),$request_array);			
            $response = array();

            // reading post params
			$param['membershipid'] = $request_array['membershipid'];
			$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
			$param['oldpassword'] = $request_array['oldpassword'];			
			$param['newpassword'] = $request_array['newpassword'];			
			$param['confirmpassword'] = $request_array['confirmpassword'];
			$param['Company_name'] = $_SESSION["company_name"];
			$param['Website'] = $_SESSION["Website"];
			$param['Loyalty_program_name'] = $_SESSION["company_name"];
			
			// validatePhone($request_array['phoneno']);
			
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
			/*  echo "---in forgot password----<br>";
			print_r($user);
			die; 
			 */
			if ($user != NULL) {

				// echo "---Password---".$user['Password']."----<br>";

				if(strcmp($param['newpassword'],$param['confirmpassword']) !=0 ){
					
					$response["error"] = 3106;
					$response["message"] = "New password and Confirm new password does not match!";
					

				} else if(strcmp($user['Password'],$param['oldpassword']) !=0 ){
					
					$response["error"] = 2011;
					$response["message"] = "Old password does not match!";
					

				} else if(strcmp($user['Password'],$param['newpassword']) ==0 ){
					
					$response["error"] = 3015;
					$response["message"] = "Old Password and New Password are Same!";
					

				} else {

					 // echo "_SESSION-Company_id--".$_SESSION['company_id'];					 
				
					// $response["error"] = false;
					$id = $user['id'];
					$Membership_ID = $user['Membership_ID'];


					$newpassword = $phashObj->string_encrypt($param['newpassword']);

					
					$MemberPara['User_pwd']=$newpassword;								
					$MemberBalance = $dbHandlerObj->updateMemberBalance($MemberPara,$user['id']);
				// print_r($MemberBalance);
					// echo "---id---".$id."----<br>";
					// echo "---Membership_ID---".$Membership_ID."----<br>";
					
					
					$EmailParam['Email_template_id'] =15; 
					$email = $sendEObj->sendEmail($EmailParam,$user['id']); 
					// print_r($email);

					$response["errorcode"] = 1001;
					$response["message"] = "OK";


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
					$LogParam['Transaction_type'] = 'Change Password ';
					$LogParam['Transaction_from'] = 'API';
					$LogParam['Operation_type'] = '1';
					$LogParam['Operation_value'] = $user['fname'].' '.$user['lname'].' , Membership: '.$user['Membership_ID'];
					$LogParam['Date'] = $Todays_date_time;
					// $Log = $logHObj->insertLog($LogParam,$user['id']);
					/* Log Entry  */
				}
			} else {
				$response['errorode'] = 2003;
				$response['message'] = "Unable to locate membership id";
			}					
            // echo json response
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
        $response["error"] = true;
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