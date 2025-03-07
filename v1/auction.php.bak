<?php
require_once '../include/DbHandler.php';
require_once '../include/AuctionHandler.php';
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
	function authenticate(\Slim\Route $route) 
	{		
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
	$app->post('/auction','authenticate', function() use ($app) {
           
			//echo "---phonecode--".$_SESSION["phonecode"];
			// die;
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			// print_r($request_array);
			// die;
			
			// check for required params
            verifyRequiredParams(array('membershipid'),$request_array);			
            $response = array();

            // reading post params
			$param['membershipid'] = $request_array['membershipid'];			
			$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
			
			
			$userObj = new UserHandler();
			$dbHandlerObj = new DbHandler();
			$dbAuctionObj = new AuctionHandler();
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
						
			$user = $userObj->getMemberDetails($param['membershipid'],$param['phoneno']);
			
			/*  echo "---in Resend Pin password----<br>";
			print_r($user);
			die; */ 
			 
			if ($user != NULL) 
			{
					//  echo "_SESSION-Company_id--".$_SESSION['company_id'];					 
				
					// $response["error"] = false;
					$id = $user['id'];
					$Membership_ID = $user['Membership_ID'];
					$AuctionDetails = $dbAuctionObj->getAuction();
					
					// print_r($AuctionDetails); 
						$auctions=array();
						while ($auction = $AuctionDetails->fetch_array()) {
							
							$Auction_Max_Bid_val =$dbAuctionObj->Fetch_Auction_Max_Bid_Value($auction["Auction_id"]);

							// echo "---Auction_Max_Bid_val---".$Auction_Max_Bid_val["Bid_value"]."-<br>";

							if($Auction_Max_Bid_val["Bid_value"] > 0  ){
								$Bid_value=$Auction_Max_Bid_val["Bid_value"]+$auction["Min_increment"];
							} else {

								$Bid_value = $auction["Min_bid_value"] + $auction["Min_increment"];
							}

							// echo "---Min_bid_value---".$auction["Min_bid_value"]."-<br>";
							// echo "---Bid_value---".$Bid_value."-<br>";

							$auctions[] =  array(
								'id'    =>  $auction["Auction_id"],
								'name'    =>  $auction["Auction_name"],
								'fromdate'  => $auction["From_date"],
								'todate'  => $auction["To_date"],
								'endtime'  => $auction["End_time"],
								'prize'  => $auction["Prize"],
								'prizedescription'  => $auction["Prize_description"],
								'prizeimage'  => $auction["Prize_image"],
								'minbidvalue'  => $auction["Min_bid_value"],
								'minincrement'  => $auction["Min_increment"],
								'bidvalue'  => $Bid_value,
								'outlet'    =>  $auction["First_name"].' '.$auction["Last_name"],
								
							);

							
						}
						//print_r($auctions); 
						// print_r($hobby);
						$response["auctions"] = $auctions;
						$response["errorcode"] = 1001;
						$response["message"] = "OK";
				
				
			} else {
				
				// unknown error occurred
				$response['errorcode'] = 2003;
				$response['message'] = "Unable to Locate membership id";
			}			
					
				
            // echo json response
            echoRespnse(201, $response);
	});
	$app->post('/auctionbidding','authenticate', function() use ($app) {
           
			//echo "---phonecode--".$_SESSION["phonecode"];
			// die;
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			
			
			// check for required params
            verifyRequiredParams(array('membershipid','auctionid','bidvalue'),$request_array);			
            $response = array();

            // reading post params
			$param['membershipid'] = $request_array['membershipid'];						
			$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
			$param['auctionid'] = $request_array['auctionid'];
			$param['bidvalue'] = $request_array['bidvalue'];
			// validatePhone($request_array['phoneno']);
		

		/* 	$response1["auctionid"] = $request_array['auctionid'];
			$response1["membershipid"] = $request_array['membershipid'];
			$response1["bidvalue"] = $request_array['bidvalue'];
			echoRespnse(201, $response1);
			exit;
			 */
			// print_r($request_array);
			// die;
			
			$userObj = new UserHandler();
			$dbHandlerObj = new DbHandler();
			$dbAuctionObj = new AuctionHandler();
			$sendEObj = new SendEmailHandler();
			$logHObj = new LogHandler();
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
			
			// print_r($SellerDetails);
			$user = $userObj->getUserByMembership($param['membershipid'],$param['phoneno']);
			$Current_point_balance = $user['Current_balance']-($user['Blocked_points']+$user['Debit_points']);
			if($Current_point_balance < 0)
			{
				$Current_balance=0;
			}
			else 
			{
				$Current_balance=$Current_point_balance;
			} 						 
			if ($user != NULL) 
			{
				//  echo "_SESSION-Company_id--".$_SESSION['company_id'];						 
			
				// $response["error"] = false;
				$id = $user['id'];
				$Membership_ID = $user['Membership_ID'];

				// echo "---id---".$param["id"]."-<br>";

				$AuctionDetails = $dbAuctionObj->Fetch_Auction_Max_Bid_Value($param['auctionid']);
			
				$Auction_Details = $dbAuctionObj->Fetch_Auction_Datails($param['auctionid']);
				
				// print_r($Auction_Details);
				$Auction_id = $Auction_Details["id"];
				$Min_bid_value = $Auction_Details["Min_bid_value"];
				$Min_increment = $Auction_Details["Min_increment"];
				
				/* echo "---Min_bid_value---".$Min_bid_value."-<br>";
				echo "---Min_increment---".$Min_increment."-<br>";
				echo "---Bid_value---".$AuctionDetails["Bid_value"]."-<br>";  
				die; */
				
				if($AuctionDetails["Bid_value"] ==""){

					// $Bid_value= $AuctionDetails["Min_bid_value"] + $AuctionDetails["Min_increment"];
					$Bid_value = $Min_bid_value;
					// $Bid_value = $Min_bid_value + $Min_increment;

				} else {
					
					// $Bid_value= $AuctionDetails["Bid_value"] + $AuctionDetails["Min_increment"];
					$Bid_value = $AuctionDetails["Bid_value"] + $Min_increment;
				}
				// echo "---Bid_value--111---".$Bid_value."-<br>"; 
				// echo "---Current_balance--111---".$user['Current_balance']."-<br>"; 
			
				if($Bid_value <= $param['bidvalue'] )
				{
					if($Current_balance >= $Bid_value) 
					{
						$BidData['Auction_id']=$Auction_id;
						$BidData['Company_id']=$_SESSION["company_id"];
						$BidData['Enrollment_id']=$user['id'];
						$BidData['Prize']=$Auction_Details['Prize'];
						$BidData['Bid_value']=$param['bidvalue'];
						$BidData['Create_user_id']=$user['id'];
						$BidData['Creation_date']=$Todays_date_time;
						
						$InsertData = $dbHandlerObj->insertData($BidData,'igain_auction_winner');


						/* Older Bidder Member */
							$PreviousBidValue = $dbAuctionObj->Fetch_Auction_previous_Bid_Value($Auction_id);
							$previousMember=$dbHandlerObj->fetchEnrollmentDetails($PreviousBidValue['Enrollment_id']); 
							while ($member = $previousMember->fetch_array()) {
								
								$Blocked_points=$member["Blocked_points"];									
								$Enrollement_id=$member["Enrollement_id"];										
							}
							// echo "---Blocked_points---".$Blocked_points."-<br>";
							$total_blaockPoints = $Blocked_points - $PreviousBidValue['Bid_value'];
							if($total_blaockPoints < 0 ){
								$total_blaockPoints=0;
							} else {
								$total_blaockPoints=$total_blaockPoints;
							}

							$upData['Blocked_points']=$total_blaockPoints;
							$valData['Enrollement_id']=$Enrollement_id;
							$valData['Company_id']=$_SESSION["company_id"];								
							// echo "---Blocked_points---old-".$upData['Blocked_points']."-<br>";
							$updateData = $dbHandlerObj->updateData($upData,'igain_enrollment_master',$valData);
						
						/* Older Bidder Member */

						/* New Bidder Member */
							
							$upData1['Blocked_points']=$user['Blocked_points'] + $param['bidvalue'];
							$valData1['Enrollement_id']=$user['id'];
							$valData1['Company_id']=$_SESSION["company_id"];								
							// echo "---Blocked_points--new-".$upData1['Blocked_points']."-<br>";
							$updateData = $dbHandlerObj->updateData($upData1,'igain_enrollment_master',$valData1);

						/* New Bidder Member */

						$response["errorcode"] = 1001;
						$response["message"] = "OK";
					}   
					else 
					{
						$response["errorcode"] = 2014;
						$response["message"] = "You don't have sufficient balance!";
					}

				} else {

					$response["errorcode"] = 2070;
					$response["message"] = "Bid value should be greater or equal to minimum bid Amount!";
				}
				// print_r($AuctionDetails['Min_bid_value']); 

				/* $auctions=array();
				while ($auction = $AuctionDetails->fetch_array()) {
					// echo "---Hobbies---".$hobbies["Hobbies"]."-<br>";

					$auctions[] =  array(
						'outlet'    =>  $auction["First_name"].' '.$auction["Last_name"],
						'name'    =>  $auction["Auction_name"],
						'fromdate'  => $auction["From_date"],
						'todate'  => $auction["To_date"],
						'endtime'  => $auction["End_time"],
						'prize'  => $auction["Prize"],
						'prizedescription'  => $auction["Prize_description"],
						'prizeimage'  => $auction["Prize_image"],
						'minbidvalue'  => $auction["Min_bid_value"],
						'minincrement'  => $auction["Min_increment"]
					);
					
				} */

				// print_r($hobby);
				// $response["auctions"] = $auctions;
				
			} else {
				
				// unknown error occurred
				$response['errorcode'] = 2003;
				$response['message'] = "Unable to Locate membership id";
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