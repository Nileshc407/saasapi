<?php
header("Access-Control-Allow-Origin: https://saasapi.igainapp.in/v1/");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// require '../vendor/autoload.php';

require_once '../include/DbHandler.php';
// echo"DbHandler------";
require_once '../include/UserHandler.php';
// echo"UserHandler------";
require_once '../include/CompHandler.php';
// echo"CompHandler------";
require_once '../include/PassHash.php';
// echo"PassHash------";
require '.././libs/Slim/Slim.php';
// echo"Slim------";


use lib\Slim\Middleware\SessionCookie;
// session_start();
error_reporting(0);
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim(
	array(
		'cookies.encrypt' => true,
		'cookies.secret_key' => 'abc12345',
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

// print_r($app);

// User id from db - Global Variable
$user_id = NULL;
$Company_id = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */

function authenticate(\Slim\Route $route) {
	
	// echo"authenticate";
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
    	
	$app->config('debug', true);
	
    // Verifying Authorization Header
	
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	
	if (isset($headers['Authorization'])) {
		$api_key = $headers['Authorization'];
	}
	else
	{
		$api_key = $request_array['Authorization'];
	}
    // if (isset($headers['Authorization'])) {
    if ($api_key !=Null) {
		
        $comp = new CompHandler();

        // get the api key
        // $api_key = $headers['Authorization'];
		
        // validating api key
         if (!$comp->isValidApiKey($api_key)) {
            $response["error"] = 2001;
            $response["message"] = "Access Denied. Invalid Api key";
            echoRespnse(401, $response);
            $app->stop();			
			
        } else {
			 
            //global $Company_id;
            // get user primary key id
            // $Company_id = $comp->getCompanyDetails($api_key);
            $result = $comp->getCompanyDetails($api_key);
			 // print_r($result);
			 // die;
			// fetch task
            // $result = $db->getTask($task_id, $user_id);

            if ($result != NULL) {
				// $response["error"] = false;
				/* $_SESSION["company_id"] = $result["Company_id"];
				$_SESSION["company_name"] = $result["Company_name"];
				$_SESSION["card_decsion"] = $result["card_decsion"];
				$_SESSION["next_card_no"] = $result["next_card_no"];
				$_SESSION["joining_bonus"] = $result["Joining_bonus"];
				$_SESSION["joining_points"] = $result["Joining_bonus_points"];
				$_SESSION["Website"] = $result["Website"];
				$_SESSION["phonecode"] = $result["phonecode"]; */
				
                // echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Invalid API Username";
                echoRespnse(404, $response);
				 $app->stop();
            }
			
				// session_cache_limiter(false);			
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
    // die;
}

/**
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
// echo"Slim--register----";
$app->post('/register','authenticate', function() use ($app) {
           
		    // echo "register";
            // die;
			
            $json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			// check for required params
            verifyRequiredParams(array('name', 'email', 'password'),$request_array);
			
            $response = array();

            // reading post params
            /* $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password'); */
			
			$param['name'] = $request_array['name'];
			$param['phone'] = $_SESSION["phonecode"].''.$request_array['phone'];
            $param['email'] = $request_array['email'];
            $param['password'] = $request_array['password'];
			
            $param['next_card_no'] = $_SESSION["next_card_no"];
			$param['Company_name'] = $_SESSION["company_name"];
			$param['Website'] = $_SESSION["Website"];
			$param['Loyalty_program_name'] = $_SESSION["company_name"];
			
			
			
			
			// require_once dirname(__FILE__) . '/PassHash.php';
			$phash = new PassHash();
			$dbHandlerObj = new DbHandler();
			
			// echo "email---".$param['email']."----<br>";
		
			// $email = $phash->string_decrypt($param['email']);
			
			 // echo "email---".$email."----<br>";
            // validating email address
            validateEmail($param['email']);
			
			

            $userObj = new UserHandler();
            $res = $userObj->createUser($param);
			
            if ($res == USER_CREATED_SUCCESSFULLY) {
				
					
					$NextMembership = $userObj->setNextMembershipID($param['next_card_no']);
					$user = $userObj->getUserByEmail($param['email']);
					
					
					 if ($user != NULL) {
					
						 // echo "login-Company_id--".$_SESSION['company_id'];					 
						
						$response["errorcode"] = 1001;
                        $response['message'] = "Ok";

						$response['id'] = $user['id'];
						$response['fname'] = $user['fname'];
						$response['lname'] = $user['lname'];
						$response['email'] = $user['email'];
						// $response['User_name'] = $user['email'];
						$response['Membership_ID'] = $user['Membership_ID'];
						$response['password'] = $user['Password'];
						$response['pin'] = $user['Pin'];
						
                        
						$EmailParam['id'] = $user['id'];
						$EmailParam['First_name'] = $user['fname'];
						$EmailParam['Last_name'] = $user['lname'];
						$EmailParam['email'] = $user['email'];
						$EmailParam['User_email_id'] = $user['email'];
						$EmailParam['User_name'] = $user['email'];
						$EmailParam['Membership_ID'] = $user['Membership_ID'];
						$EmailParam['Password'] = $user['Password'];	
						$EmailParam['Pin'] = $user['Pin'];
						$EmailParam['facebook_link'] = $_SESSION["facebook_link"];
						$EmailParam['twitter_link'] = $_SESSION["twitter_link"];
						$EmailParam['linkedin_link'] = $_SESSION["linkedin_link"];
						$EmailParam['googlplus_link'] = $_SESSION["googlplus_link"];
						$EmailParam['Company_name'] = $_SESSION["company_name"];
						$EmailParam['Cust_apk_link'] = $_SESSION["Cust_apk_link"];
						$EmailParam['Cust_ios_link'] = $_SESSION["Cust_ios_link"];

						$EmailParam['Email_template_id'] =1; 
						$email = $dbHandlerObj->sendEmail($EmailParam); 
						
                        // $response["error"] = false;
                        // $response["message"] = "You are successfully registered";
						
					} else {
						
						// unknown error occurred
						$response['errorcode'] = 2012;
						$response['message'] = "No Data Found                        ";
					}
					 				
               
						
            } else if ($res == USER_CREATE_FAILED) {
				
                $response["errorcode"] = 3107;
                $response["message"] = "User registration failed";
				
            } else if ($res == USER_ALREADY_EXISTED) {
				
                $response["errorcode"] = 2030;
                $response["message"] = "Email ID Already Exist";
            }
            // echo json response
            echoRespnse(201, $response);
    });

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login','authenticate', function() use ($app) {			
			
			// echo "login---";
			
			
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
            // check for required params
            verifyRequiredParams(array('email', 'password'),$request_array);

           
            // reading post params
           /*  $email = $app->request()->post('email');
            $password = $app->request()->post('password'); */
			
			// reading post params
			$email = $request_array['email'];
            $password = $request_array['password'];

            validateEmail($email);
            
		
            $response = array();

            $userObj = new UserHandler();
			  
            // check for correct email and password
            if ($userObj->checkLogin($email, $password)) {
                // get the user by email
                $user = $userObj->getUserByEmail($email);

                if ($user != NULL) {
					
					 // echo "login-Company_id--".$_SESSION['company_id'];					 
					$Current_point_balance = $user['Current_balance']-($user['Blocked_points']+$user['Debit_points']);
					if($Current_point_balance < 0)
					{
						$Current_point_balance=0;
					}
					else 
					{
						$Current_point_balance=$Current_point_balance;
					}
					
					$user1 = $userObj->getMemberDetails($user['Membership_ID'],$user['Membership_ID']);
					
                    $response["errorcode"] = 1001;
                    $response["message"] = "OK";
                    // $response['id'] = $user['id'];
                    $response['fname'] = $user['fname'];
                    $response['lname'] = $user['lname'];
                    $response['email'] = $user['email'];
                    $response['membershipid'] = $user['Membership_ID'];
                    $response['pointbalance'] = $Current_point_balance;
                    $response['blockpoint'] = $user['Blocked_points'];
                    $response['debitpoint'] = $user['Debit_points'];
                    $response['purchaseamount'] = $user['total_purchase'];
                    $response['bonuspoints'] = $user['Total_topup_amt'];
                    $response['redeempoints'] = $user['Total_reddems'];
                    $response['currency'] = $_SESSION["Currency_name"];
					$response["tier"] = $user1['Tier_name'];
					
                } else {
					
                    // unknown error occurred
                    $response['errorcode'] = 3119;
                    $response['message'] = "Incorrect Username or Password";
                }
				
            } else {
                // user credentials are wrong
                $response['errorcode'] = 3119;
                $response['message'] = "Incorrect Username or Password";
            }

            echoRespnse(200, $response);
    });
$app->post('/test_api', function() use ($app) {			
			$headers = apache_request_headers();
			$json=$app->request->getbody();
			
			$request_array=json_decode($json,true);
			
			$email = $request_array['email'];
            $password = $request_array['password'];

            $response = array();

			$response['errorcode'] = 1001;
			$response['email'] = $email;
			$response['password'] = $password;
			$response['Authorization'] = $headers['Authorization'];
			$response['message'] = "test api call successfully..";

            echoRespnse(200, $response);
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