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

/* JWT  */
require_once 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
/* JWT  */

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
	// print_r($headers['Authorization']);
	// die;
    $response = array();
    $app = \Slim\Slim::getInstance();
    	
	$app->config('debug', true);
	
    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
		
        $comp = new CompHandler();

        // get the api key
        $api_key = $headers['Authorization'];
        $token = $headers['token'];
		
		// echo"\n----authenticate---api_key---".$api_key;
		// echo"\n----authenticate---token---".$token;
		
		
		$key = "saasapi2022";
		
		
		
		
		$token=isset($token) ? $token : "";

		// decode jwt here
		// if jwt is not empty
		if($token){

			// if decode succeed, show user details
			try {
				// decode jwt
				
				//$decoded = JWT::decode($token, $key, array('HS256'));
				
				$decoded = JWT::decode($token, new Key($key, 'HS256'));
				print_r($decoded);

				 // set response code
				/* http_response_code(1008);

				// show user details
				echo json_encode(array(
					"status" => "1008",
					"message" => "Access granted",
					"data" => $decoded->data
				)); */
				
				// echo"\n----authenticate---token---".$token;
				echo"\n----authenticate---try--";
				$response["errorcode"] = 1008;
				$response["status"] = false;
				$response["message"] = "Access granted";
				// $response["data"] =$e->getMessage();
				// $response["error"] =$e->getMessage();
				echoRespnse(1008, $response); 
				$app->stop();	

			}
			// catch will be here
			// if decode fails, it means jwt is invalid
			catch (Exception $e){

				echo"\n----authenticate---catch--";

				// set response code
				// http_response_code(1009);

				// tell the user access denied  & show error message
				/* echo json_encode(array(
					"status" => "1009",
					"message" => "Access denied",
					"error" => $e->getMessage()
				)); */
				
				
				$response["errorcode"] = 1009;
				$response["status"] = true;
				$response["message"] = "Access denied";
				$response["error"] =$e->getMessage();
				echoRespnse(1009, $response);
				$app->stop();	
				
				
			}
		}
		else
		{ 
			// error if jwt is empty will be here
			// show error message if jwt is empty

			// set response code
			// http_response_code(1009);

			// tell the user access denied
			// echo json_encode(array("message" => "Access denied"));
		}
		// die;
		
       /*  // validating api key
        if (!$comp->isValidApiKey($api_key)) {
           
			// api key is not present in users table
            $response["error"] = 2001;
            $response["message"] = "Access Denied Invalid Company Username";
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
				
				$response["status"] = true;
				$response["errorcode"] = 1008;
				$response["message"] = "Access granted";
				
                echoRespnse(200, $response);
				
				
            } else {
                $response["error"] = true;
                $response["message"] = "Invalid API Username";
                echoRespnse(404, $response);
            }
			
				// session_cache_limiter(false);			
				// $_SESSION['Company_id'] =  $Company_id;			
				// die;
				
        } */
		
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
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/','authenticate', function() use ($app) {			
			
			/* // echo "login---";
			
			
			$json=$app->request->getbody();
			// print_r($json);
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
            // check for required params
            verifyRequiredParams(array('email', 'password'),$request_array);

			
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


					$token = array(
					   "iat" => $issued_at,
					   "exp" => $expiration_time,
					   "iss" => $issuer,
					   "data" => array(
							"email" => $user['email'],
							"membershipid" =>$user['Membership_ID']						
						)
					);

					$jwt = JWT::encode($token, $key);
					
                    $response["status"] = true;
                    $response["errorcode"] = 1001;
                    $response["message"] = "OK";
                    // $response['id'] = $user['id'];
                    $response['fname'] = $user['fname'];
                    $response['lname'] = $user['lname'];
                    $response['email'] = $user['email'];
                    $response['membershipid'] = $user['Membership_ID'];
                    $response['pointbalance'] = $user['Current_balance'];
                    $response['blockpoint'] = $user['Blocked_points'];
                    $response['debitpoint'] = $user['Debit_points'];
                    $response['purchaseamount'] = $user['total_purchase'];
                    $response['bonuspoints'] = $user['Total_topup_amt'];
                    $response['redeempoints'] = $user['Total_reddems'];
                    $response['token'] = $jwt;
					
                } else {
					
                    // unknown error occurred
					$response["status"] = false;
                    $response['errorcode'] = 3119;
                    $response['message'] = "Incorrect Username or Password";
                }
				
            } else {
				
                // user credentials are wrong
				$response["status"] = false;
                $response['errorcode'] = 3119;
                $response['message'] = "Incorrect Username or Password";
            }

            echoRespnse(200, $response); */
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