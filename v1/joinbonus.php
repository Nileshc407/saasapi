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
					
					// $response["error"] = false;
					/* $_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];
					$_SESSION["card_decsion"] = $result["card_decsion"];
					$_SESSION["next_card_no"] = $result["next_card_no"];
					$_SESSION["joining_bonus"] = $result["Joining_bonus"];
					$_SESSION["joining_points"] = $result["Joining_bonus_points"];
					$_SESSION["Website"] = $result["Website"];					
					$_SESSION["phonecode"] = $result["phonecode"];	 */				
					// echoRespnse(200, $response);
					
					
					
					// echo "---superSellerDetails-----".$result["Company_id"];
					// $company_details = $this->Igain_model->get_company_details($Company_id);
					$superSeller= $dbHandlerObj->superSellerDetails();
					// print_r($superSeller);
					// die;
					// $dialCode = $this->Igain_model->get_dial_code($superSeller->Country);
					// $dialcode = $dialCode->phonecode;
					// $phoneNo = $dialcode . '' . $Phone_no;
					
					$_SESSION["seller_id"] = $superSeller["id"];
					$_SESSION["seller_name"] = $superSeller["fname"].' '.$superSeller["lname"];
					$_SESSION["country"] = $superSeller["country"];
					$_SESSION["state"] = $superSeller["state"];
					$_SESSION["city"] = $superSeller["city"];
					$_SESSION["topup_Bill_no"] = $superSeller["topup_Bill_no"];
					$_SESSION["timezone_entry"] = $superSeller["timezone_entry"];
					
					$timezone_entry = $superSeller["timezone_entry"];
					$logtimezone = $timezone_entry;
					$timezone = new DateTimeZone($logtimezone);
					$date = new DateTime();
					$date->setTimezone($timezone);
					$Todays_date_time = $date->format('Y-m-d H:i:s');
					$Todays_date = $date->format('Y-m-d');
					
					// print_r($Todays_date_time);
					// print_r($Todays_date);
					// die;	
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

	$app->post('/joinbonus','authenticate', function() use ($app) 
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
		
		$userObj = new UserHandler();
		$dbHandlerObj = new DbHandler();
		$sendEObj = new SendEmailHandler();
		$logHObj = new LogHandler();
		$userObj = new UserHandler();

		// validatePhone($request_array['phoneno'] );

		// $res = $userObj->createUser($param);
		
			if ($_SESSION["Joining_bonus"] == 1) 
			{	
				$user = $userObj->getUserByMembership($param['membershipid'],$param['phoneno']);
				
				if ($user != NULL) 
				{
				
					// $response["error"] = false;
					$id = $user['id'];
					$Membership_ID = $user['Membership_ID'];
					
					// echo "---id---".$id."----<br>";
					// echo "---Membership_ID---".$Membership_ID."----<br>";
					
					$validateBonus = $userObj->validateBonus($id,$Membership_ID);
					
					if($validateBonus == 0) 
					{
						// echo "---topup_Bill_no---".$_SESSION["topup_Bill_no"]."----<br>";
						$len2 = strlen($_SESSION["topup_Bill_no"]);
						$str2 = substr($_SESSION["topup_Bill_no"], 0, 5);
						$tp_bill2 = substr($_SESSION["topup_Bill_no"], 5, $len2);
						$topup_BillNo2 = $tp_bill2 + 1;
						$billno_withyear_ref = $str2 . $topup_BillNo2;
						// echo $tp_bill2;
						
						
						$timezone_entry = $_SESSION["timezone_entry"];
						$logtimezone = $timezone_entry;
						$timezone = new DateTimeZone($logtimezone);
						$date = new DateTime();
						$date->setTimezone($timezone);
						$Todays_date_time = $date->format('Y-m-d H:i:s');
						$Todays_date = $date->format('Y-m-d');
						
						$TransPara['Trans_type']=1;
						$TransPara['Company_id']=$_SESSION["company_id"];
						$TransPara['Trans_date']=$Todays_date_time;
						$TransPara['Topup_amount']=$_SESSION["joining_points"];
						$TransPara['Remarks']='Joining Bonus';
						$TransPara['Card_id']=$Membership_ID;
						$TransPara['Seller_name']=$_SESSION["seller_name"];
						$TransPara['Seller']=$_SESSION["seller_id"];
						$TransPara['Enrollement_id']=$id;
						$TransPara['Bill_no']=$tp_bill2;
						$TransPara['remark2']='Super Seller';
						
						// print_r($TransPara);
						
						
						$Topup = $userObj->insertTopup($TransPara);
						/* print_r($Topup); */
						if($Topup)
						{
							$BillPara['seller_id']=$_SESSION["seller_id"];
							$BillPara['billno_withyear_ref']=$billno_withyear_ref;
							
							$TopupBill = $dbHandlerObj->updateTopupBillNo($BillPara);

							/*  print_r($TopupBill); */ 	
							// $MemberPara['id']=$user['id'];
							$MemberPara['Total_topup_amt']=$user['Total_topup_amt'] + $_SESSION["joining_points"];						
							$MemberPara['Current_balance']= $user['Current_balance'] + $_SESSION["joining_points"];								
							$MemberBalance = $dbHandlerObj->updateMemberBalance($MemberPara,$user['id']);
							/* print_r($MemberBalance);  */
							
							$EmailParam['Joining_bonus_points'] = $_SESSION["joining_points"];
							$EmailParam['Email_template_id'] =2; 									
							$email = $sendEObj->sendEmail($EmailParam, $user['id']); 
							/* API Erro Log */

							$APILogParam['Company_id'] =$_SESSION['company_id'];
							$APILogParam['Trans_type'] = 1;
							$APILogParam['Outlet_id'] = $_SESSION["seller_id"];
							$APILogParam['Bill_no'] = 0;
							$APILogParam['Card_id'] = $user['Membership_ID'];
							$APILogParam['Date'] = $Todays_date_time;
							$APILogParam['Json_input'] = $json;
							$APILogParam['Json_output'] = json_encode($response);
							$APILog = $logHObj->insertAPILog($APILogParam,$user['id']); 
		
						  /* API Erro Log */
						  /* Log Entry  */
						  $LogParam['Company_id'] =$_SESSION['company_id'];
						  $LogParam['From_enrollid'] = $user['id'];
						  $LogParam['From_emailid'] = $user['email'];
						  $LogParam['From_userid'] = 1;
						  $LogParam['To_enrollid'] = 0;
						  $LogParam['Transaction_by'] = $user['fname'].' '.$user['lname'];
						  $LogParam['Transaction_to'] = $user['fname'].' '.$user['lname'];
						  $LogParam['Transaction_type'] = 'joining bonus ';
						  $LogParam['Transaction_from'] = 'API';
						  $LogParam['Operation_type'] = '1';
						  $LogParam['Operation_value'] = $user['fname'].' '.$user['lname'].' , Membership: '.$user['Membership_ID'].', joining points: '.$_SESSION["joining_points"];
						  $LogParam['Date'] = $Todays_date_time;
						  $Log = $logHObj->insertLog($LogParam,$user['id']);
						  /* Log Entry  */

						  $response["errorcode"] = 1001;
						  $response['message'] = "Ok";
						  $response["bonuspoints"] = $_SESSION["joining_points"];
							
						} else if($Topup == FAIL){
							
							$response["errorcode"] = 3112;
							$response["message"] = "An error occurred. Please try again";
						}
					} else {
						
						// unknown error occurred
						$response['errorcode'] = 3111;
						$response['message'] = "Member already got joining bonus";
					}
				} else {
					
					// unknown error occurred
					$response['errorode'] = 2003;
					$response['message'] = "Unable to locate membership id";
				}			
			} 
			else
			{
				$response["errorode"] = 3110;
				$response["message"] = "Sorry, unable procced to joining bonus";
			}
		// echo json response
		echoRespnse(201, $response);
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