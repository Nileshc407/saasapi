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
	
		
		$phash = new PassHash();
		
		
	
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
		
		
		 $comp = new CompHandler();
		 $dbHandlerObj = new DbHandler();

		// Verifying Authorization Header
		if (isset($headers['Authorization'])) {
			
			

			// get the api key
			$api_key = $headers['Authorization'];
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
					
					
					
					//echo "---superSellerDetails-----".$result["Company_id"];
					// $company_details = $this->Igain_model->get_company_details($Company_id);
					$superSeller= $dbHandlerObj->superSellerDetails();
					//print_r($superSeller);
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
	$app->post('/credit','authenticate', function() use ($app) {
           
			// echo "---phonecode---".$_SESSION["phonecode"];
			// die;
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			
			
			// check for required params
            verifyRequiredParams(array('membershipid','points'),$request_array);			
            $response = array();
				
			// print_r($request_array);
			// die;
			
            // reading post params
			$param['membershipid'] = $request_array['membershipid'];			
			$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];			
			$param['points'] = $request_array['points'];
			$param['Company_name'] = $_SESSION["company_name"];
			$param['Website'] = $_SESSION["Website"];
			$param['Loyalty_program_name'] = $_SESSION["company_name"];
			
			
			
			
			$userObj = new UserHandler();
			$dbHandlerObj = new DbHandler();
			
            
				// if ($param['membershipid'] && $param['points']) 
				if ($param) 
				{
					
						
						
						$user = $userObj->getUserByMembership($param['membershipid'],$param['phoneno']);
						// print_r($user);
						// die;
						
						if ($user != NULL) {
						
							 // echo "_SESSION-Company_id--".$_SESSION['company_id'];					 
							
							// $response["error"] = false;
							$id = $user['id'];
							$Membership_ID = $user['Membership_ID'];
							$Current_balance = $user['Current_balance'];
							$Total_topup_amt = $user['Total_topup_amt'];
							
							// echo "---id---".$id."----<br>";
							// echo "---Membership_ID---".$Membership_ID."----<br>";
							
							
								
								
								
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
								
								
								
								$TransPara['Trans_type']=2;
								$TransPara['Company_id']=$_SESSION["company_id"];
								$TransPara['Trans_date']=$Todays_date_time;
								$TransPara['Topup_amount']=$param['points'];
								$TransPara['Remarks']='TOPUP';
								$TransPara['Card_id']=$Membership_ID;
								$TransPara['Seller_name']=$_SESSION["seller_name"];
								$TransPara['Seller']=$_SESSION["seller_id"];
								$TransPara['Enrollement_id']=$id;
								$TransPara['Bill_no']=$tp_bill2;
								$TransPara['remark2']='Super Seller';
								
								// print_r($TransPara);
								
								
								$Topup = $userObj->insertTopup($TransPara);
								// print_r($Topup);
								if($Topup){
									
									
									$BillPara['seller_id']=$_SESSION["seller_id"];
									$BillPara['billno_withyear_ref']=$billno_withyear_ref;
									
									$TopupBill = $dbHandlerObj->updateTopupBillNo($BillPara);
									// $TopupBill = $dbHandlerObj->updateTopupBillNo($BillPara);
									// print_r($TopupBill);									
									// $MemberPara['id']=$user['id'];
									$MemberPara['Total_topup_amt']=$Total_topup_amt + $param['points'] ;								
									$MemberPara['Current_balance']=$Current_balance + $param['points'] ;								
									$MemberBalance = $dbHandlerObj->updateMemberBalance($MemberPara,$user['id']);
									//print_r($MemberBalance);
									
									
									
									
									$EmailParam["error"] = false;
									$EmailParam['id'] = $user['id'];
									$EmailParam['First_name'] = $user['fname'];
									$EmailParam['Last_name'] = $user['lname'];
									$EmailParam['User_name'] = $user['email'];	
									$EmailParam['Membership_ID'] = $user['Membership_ID'];	
									$EmailParam['Company_name'] = $_SESSION['company_name'];
									$EmailParam['Outlet_name'] = $_SESSION["seller_name"];
									$EmailParam['Credit_points'] = $param['points'];
									$EmailParam['Loyalty_program_name'] = $_SESSION['loyalty_program_name'];
									$EmailParam['Website'] = $_SESSION['website'];
									$Current_balance1 = $user['Current_balance']-($user['Blocked_points']+$user['Debit_points']);
									$EmailParam['Current_balance']=$Current_balance1 + $param['points'] ;
									$EmailParam['Email_template_id'] =3; 
									
									
									$email = $dbHandlerObj->sendEmail($EmailParam); 
									print_r($email);
									
									
									
									$response["error"] = 1001;
									$response["message"] = "Successfully ".$param['points']." point(s) credited into your accounts";
									
								} else if($Topup == FAIL){
									
									$response["error"] = 3108;
									$response["message"] = "Unable credit topup";
								}
								
								
							
							
							
						} else {
							
							// unknown error occurred
							$response['error'] = 2003;
							$response['message'] = "Unable to Locate membership id";
						}			
					
				} 
				else
				{
					$response["error"] = 3109;
					$response["message"] = "Invalid provide parameters";
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
        $response["error"] = true;
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