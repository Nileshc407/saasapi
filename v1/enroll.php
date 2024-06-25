<?php
// require '../vendor/autoload.php';
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

// User id from db - Global Variable
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
	
	if (isset($headers['Authorization'])) {
		$api_key = $headers['Authorization'];
	}
	else
	{
		$api_key = $request_array['Authorization'];
	}
	if ($api_key !=Null) 
	{
        $comp = new CompHandler();
      
        // validating api key
        if (!$comp->isValidApiKey($api_key)) {
           
			// api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            // $response["message"] =INVALID_KEY;
            echoRespnse(401, $response);
            $app->stop();			
        } 
		else 
		{
            global $Company_id;
            
            $result = $comp->getCompanyDetails($api_key);

            if ($result != NULL) 
			{
				// $response["error"] = false;
				/* $_SESSION["company_id"] = $result["Company_id"];
				$_SESSION["company_name"] = $result["Company_name"];
				$_SESSION["card_decsion"] = $result["card_decsion"];
				$_SESSION["next_card_no"] = $result["next_card_no"];
				$_SESSION["joining_bonus"] = $result["Joining_bonus"];
				$_SESSION["joining_points"] = $result["Joining_bonus_points"];
				$_SESSION["Website"] = $result["Website"]; 	 		 */
                // echoRespnse(200, $response);

            } else {

                $response["error"] = true;
                $response["message"] = "Invalid API Username";
                echoRespnse(404, $response);
            }
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
$app->post('/enroll','authenticate', function() use ($app) {
      $json=$app->request->getbody();
      // to get an array so try following..
      $request_array=json_decode($json,true);
      
      // check for required params
      verifyRequiredParams(array('name', 'email', 'phoneno'),$request_array);
      
      $response = array();

      // reading post params
      /* $name = $app->request->post('name');
      $email = $app->request->post('email');
      $password = $app->request->post('password'); */
      
      $param['name'] = $request_array['name'];
      $param['phone'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
      $param['email'] = strtolower($request_array['email']);
	  
	  if($request_array['password'] !=Null)
	  {
		$param['password'] = $request_array['password'];
	  }
	  else
	  {
		$param['password'] = $request_array['phoneno'];
	  }
      
      $param['next_card_no'] = $_SESSION["next_card_no"];
      $param['Company_name'] = $_SESSION["company_name"];
      $param['Website'] = $_SESSION["website"];
      $param['Loyalty_program_name'] = $_SESSION["company_name"];
      
      // require_once dirname(__FILE__) . '/PassHash.php';
      $phash = new PassHash();
      $dbHandlerObj = new DbHandler();
      $sendEObj = new SendEmailHandler();
      $logHObj = new LogHandler();
      $userObj = new UserHandler();
     
      validateEmail($param['email']);
      validatePhone($request_array['phoneno'] );
      
        $SellerDetails=$dbHandlerObj->superSellerDetails();
       
        $Super_Seller_id = $SellerDetails['id'];
        $Super_Seller_Name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
        $Seller_timezone_entry =$SellerDetails['timezone_entry'];

		$Todays_date=date("Y-m-d");
        $res = $userObj->createUser($param,$Seller_timezone_entry);
   
		if ($res == 0) 
		{ 
			//   if ($res == USER_CREATED_SUCCESSFULLY) 
		  $NextMembership = $userObj->setNextMembershipID($param['next_card_no']);
		  $user = $userObj->getUserByEmail($param['email']);
		 
			if ($user != NULL) 
			{ 
				$characters = 'A123B5C8';
				$string = '';
				$Set_pwd_code = "";
				for ($i = 0; $i < 8; $i++) {
					$Set_pwd_code .= $characters[mt_rand(0, strlen($characters) - 1)];
				  }
				  
				$UpdateMemberPara['Pwd_set_code'] = $Set_pwd_code;
				
				$MemberPwd_code = $dbHandlerObj->updateMemberBalance($UpdateMemberPara,$user['id']);		
				
				$EmailParam['Pwd_set_code'] = $Set_pwd_code;
				$EmailParam['Email_template_id'] =1; 
				
				$email = $sendEObj->sendEmail($EmailParam,$user['id']); 
				
				$response["errorcode"] = 1001;
				$response['message'] = "REGISTRATION DONE SUCCESSFULLY";

				$response['id'] = $user['id'];
				$response['fname'] = $user['fname'];
				$response['lname'] = $user['lname'];
				$response['email'] = $user['email'];
				$response['phoneno'] = $user['phoneno'];
				// $response['User_name'] = $user['email'];
				$response['membershipid'] = $user['Membership_ID'];
				// $response['password'] = $user['Password'];
				$response['pin'] = $user['Pin'];  
			} 
			else 
			{ 
			  // unknown error occurred
			  $response['errorcode'] = 2012;
			  $response['message'] = "No Data Found";
			}         
      }
	  else if ($res == 1) 
	  { 
		//  else if ($res == USER_CREATE_FAILED) 
          $response["errorcode"] = 3107;
          $response["message"] = "User registration failed";
          
      } 
	  else if ($res == 2) 
	  {
         //else if ($res == USER_ALREADY_EXISTED)  
          $response["errorcode"] = 2030;
          $response["message"] = "Email address already exist";
      }
	  else if ($res == 3) 
	  {
        //  else if ($res == USER_PHONE_ALREADY_EXISTED)   
          $response["errorcode"] = 3123;
          $response["message"] = "Phone number already exist";
      }
      // echo json response
      echoRespnse(201, $response); 

    });
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