<?php
require_once '../include/DbHandler.php';
require_once '../include/UserHandler.php';
require_once '../include/SendEmailHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/TransferPointsHandler.php';
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
		if (isset($headers['Authorization'])) {
			$api_key = $headers['Authorization'];
		}
		else
		{
			$api_key = $request_array['Authorization'];
		}
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

	$app->post('/transferpoints','authenticate', function() use ($app) {
           
			//echo "---phonecode--".$_SESSION["phonecode"];
			// die;
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			// print_r($request_array);
			// die;			
			// check for required params
            verifyRequiredParams(array('membershipid','tomembershipid','points'),$request_array);			
            $response = array();

            // reading post params
			$param['membershipid'] = $request_array['membershipid'];			
			$param['tomembershipid'] = $request_array['tomembershipid'];			
			$param['points'] = $request_array['points'];						
			$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
			$param['tophoneno'] = $_SESSION["phonecode"].''.$request_array['tomembershipid'];
			// validatePhone($request_array['phoneno']);
			// validatePhone1($request_array['tophoneno']);
			
			$userObj = new UserHandler();
			$dbHandlerObj = new DbHandler();
			$sendEObj = new SendEmailHandler();
			$logHObj = new LogHandler();
			$transPObj = new TransferPointsHandler();
			$userObj = new UserHandler();
			$phashObj = new PassHash();
			
			$SellerDetails=$dbHandlerObj->superSellerDetails();
			// print_r($SellerDetails);    
			$Super_Seller_id = $SellerDetails['id'];
			$Super_Seller_Name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
			$Seller_timezone_entry =$SellerDetails['timezone_entry'];
			$topup_Bill_no =$SellerDetails['topup_Bill_no'];
	
	
			$timezone_entry = $Seller_timezone_entry;
			$logtimezone = $timezone_entry;
			$timezone = new DateTimeZone($logtimezone);
			$date = new DateTime();
			$date->setTimezone($timezone);
			$Todays_date_time = $date->format('Y-m-d H:i:s');
			$Todays_date = $date->format('Y-m-d');	
			
			if($param['membershipid'] == $param['tomembershipid'])
			{
				$response['errorcode'] = 2003;
				$response['message'] = "Enter valid to membership id";
				echoRespnse(201, $response);
				exit;
			}
				
			
			$user = $userObj->getUserByMembership($param['membershipid'],$param['phoneno']);
			
			 
			if ($user != NULL) 
			{
					$id = $user['id'];
					$User_phoneno = $user['phoneno'];
					
					if($User_phoneno == $param['tophoneno'])
					{
						$response['errorcode'] = 2003;
						$response['message'] = "Enter valid to membership id";
						echoRespnse(201, $response);
						exit;
					}
					
					$Membership_ID = $user['Membership_ID'];
					$Current_point_balance = $user['Current_balance']-($user["Blocked_points"]+$user["Debit_points"]);

					if($Current_point_balance < 0)
					{
						$Current_balance=0;
					}
					else 
					{
						$Current_balance=$Current_point_balance;
					}
					$touser = $userObj->getUserByMembership($param['tomembershipid'],$param['tophoneno']);
				/* 	echo "---touser----<br>";
					print_r($touser);  */
					if($touser){

						$toid = $touser['id'];
						$toMembership_ID = $touser['Membership_ID'];
						$toCurrent_balance = $touser['Current_balance'];
						$toTotal_topup_amt = $touser['Total_topup_amt'];

						// echo "---toCurrent_balance---".$toCurrent_balance."----<br>";

						if($Current_balance >= $param['points'] && $param['points'] > 0){

							// echo "---Current_balance---2222----".$Current_balance."----<br>";


							$TransPara['Trans_type']=8;
							$TransPara['Company_id']=$_SESSION["company_id"];
							$TransPara['Trans_date']=$Todays_date_time;
							$TransPara['Transfer_points']=$param['points'];
							$TransPara['Remarks']='Transfer Points';
							$TransPara['Card_id']=$Membership_ID;
							$TransPara['Enrollement_id']=$user['id'];
							$TransPara['Enrollement_id2']=$toid;
							$TransPara['Card_id2']=$toMembership_ID;
							$TransPara['remark2']='Super Seller';
							
							// print_r($TransPara);
							
							
							$Topup = $dbHandlerObj->insertData($TransPara,'igain_transaction'); 

							
							
							$len2 = strlen($topup_Bill_no);
							$str2 = substr($topup_Bill_no, 0, 5);
							$tp_bill2 = substr($topup_Bill_no, 5, $len2);
							$topup_BillNo2 = $tp_bill2 + 1;
							$billno_withyear_ref = $str2 . $topup_BillNo2;
							
							$TransPoints['Trans_type']=1;
							$TransPoints['Company_id']=$_SESSION["company_id"];
							$TransPoints['Trans_date']=$Todays_date_time;
							$TransPoints['Topup_amount']=$param['points'];
							$TransPoints['Remarks']='Get by Transfer Points';
							$TransPoints['Card_id']=$toMembership_ID;
							$TransPoints['Seller_name']=$Super_Seller_Name;
							$TransPoints['Seller']=$Super_Seller_id;
							$TransPoints['Enrollement_id']=$toid;
							$TransPoints['Enrollement_id2']=$user['id'];
							$TransPoints['Card_id2']=$Membership_ID;
							$TransPoints['Bill_no']=$tp_bill2;
							$TransPoints['remark2']='Super Seller';
							
							
							
							$Topup = $dbHandlerObj->insertData($TransPoints,'igain_transaction');	
						
							$BillPara['seller_id']=$Super_Seller_id;
							$BillPara['billno_withyear_ref']=$billno_withyear_ref;					
							$TopupBill = $dbHandlerObj->updateTopupBillNo($BillPara);


							/* Member */
							$MemberPara['Current_balance']= $user['Current_balance'] - $param['points'];
							$MemberBalance = $dbHandlerObj->updateMemberBalance($MemberPara,$user['id']);
						



							/* To Member */
							$toMemberPara['Total_topup_amt']=$toTotal_topup_amt + $param['points'];	
							$toMemberPara['Current_balance']=$toCurrent_balance + $param['points'];	
							$toMemberBalance = $dbHandlerObj->updateMemberBalance($toMemberPara,$toid);


							$EmailParam['Email_template_id'] =11; 
							$EmailParam['Transfered_points'] =$param['points']; 
							$EmailParam['Transferred_to'] =$touser['fname'].' '.$touser['lname']; 
							$email = $sendEObj->sendEmail($EmailParam,$user['id']); 
							//print_r($email);



							/* Get Transfer Points  */
							$EmailParam['Email_template_id'] =23; 
							$EmailParam['Received_points'] =$param['points']; 
							$EmailParam['Received_from'] =$user['fname'].' '.$user['lname']; 
							$email = $sendEObj->sendEmail($EmailParam,$touser['id']); 

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
							
							
							

						} else {

							$response["errorcode"] = 2018;
							$response["message"] = "Invalid Transfer Points";
						}

					} else {									

						$response["errorcode"] = 2017;
						$response["message"] = "Invalid Transfer to membership ID";
					}
				
				
				
			} else {
				
				// unknown error occurred
				$response['errorcode'] = 2003;
				$response['message'] = "Unable to Locate membership id";
			}			
					
				
            // echo json response
            echoRespnse(201, $response);
	});
	$app->post('/history','authenticate', function() use ($app) 
	{
		$json=$app->request->getbody();
			
		$request_array=json_decode($json,true);
			
        verifyRequiredParams(array('membershipid'),$request_array);			
        $response = array();
 
		$param['membershipid'] = $request_array['membershipid'];									
		$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
				
		$userObj = new UserHandler();
		$dbHandlerObj = new DbHandler();
		$sendEObj = new SendEmailHandler();
		$logHObj = new LogHandler();
		$transPObj = new TransferPointsHandler();
		$userObj = new UserHandler();
		$phashObj = new PassHash();
			
		//$Todays_date = $date->format('Y-m-d');	
				
		$user = $userObj->getUserByMembership($param['membershipid'],$param['phoneno']);
		if ($user != NULL) 
		{
			$id = $user['id'];
			$Membership_ID = $user['Membership_ID'];
			$result = $transPObj->getPointhistory($id,$Membership_ID,$_SESSION["company_id"]);
			
			$pointHistory = array();
			if($result !=Null)
			{
				foreach($result as $row)
				{
					$tomembershipid = $row['Card_id2'];
					$tomembername = $row['Transfer_to_member'];
					$transferpoints = $row['Transfer_points'];
					$date = date("Y-M-d",strtotime($row['Trans_date']));
					if($row['Trans_type'] == '8'){
						$transtype ="Transfered";
					}
					else if($row['Trans_type'] == '1')
					{
						$transtype ="Received";
					}
					
					$pointHistory[] = array("membershipid"=>$tomembershipid,"membername"=>$tomembername,"points"=>$transferpoints,"date"=>$date);
					
				}
				
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["pointsdata"] = $pointHistory;
			}
			else
			{
				$response["errorcode"] = 2012;
				$response["message"] = "No Data Found";
			}	
		} else {
			$response['errorcode'] = 2003;
			$response['message'] = "Unable to Locate membership id";
		}			
		echoRespnse(201, $response);
	});
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
function validatePhone1($phoneno) {
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
        $response["errorcode"] = 3123;
        $response["message"] = 'To Phone number is not valid';
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