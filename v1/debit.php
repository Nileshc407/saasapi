<?php
require_once '../include/DbHandler.php';
require_once '../include/UserHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/TransactionHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';


use lib\Slim\Middleware\SessionCookie;
//session_start();
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
					$_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];
					$_SESSION["card_decsion"] = $result["card_decsion"];
					$_SESSION["next_card_no"] = $result["next_card_no"];
					$_SESSION["joining_bonus"] = $result["Joining_bonus"];
					$_SESSION["joining_points"] = $result["Joining_bonus_points"];
					$_SESSION["Website"] = $result["Website"];					
					$_SESSION["phonecode"] = $result["phonecode"];					
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

	$app->post('/','authenticate', function() use ($app) {
           
			// echo "---joining_bonus joining_bonus---".$_SESSION["joining_bonus"];
			// die;
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			
					
			// print_r($request_array);
			// die;
			
			// check for required params
            verifyRequiredParams(array('membershipid','billno','amount','cancelamount'),$request_array);			
            $response = array();
		
			
            // reading post params
			$param['membershipid'] = $request_array['membershipid'];
			$param['billno'] = $request_array['billno'];
			$param['amount'] = $request_array['amount'];
			$param['cancelamount'] = $request_array['cancelamount'];
			$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
			$param['Company_name'] = $_SESSION["company_name"];
			$param['Website'] = $_SESSION["Website"];
			$param['Loyalty_program_name'] = $_SESSION["company_name"];
			
			
			
			
			$userObj = new UserHandler();
			$dbHandlerObj = new DbHandler();
			$dbTransObj = new TransactionHandler();
			
            
				if ($param['membershipid'] && $param['billno'] && $param['amount'] && $param['cancelamount']) {
					
						
						
						$user = $userObj->getUserByMembership($param['membershipid'],$param['phoneno']);
						// print_r($user);
						// die;
						
						if($user != NULL) {
						
							$Transaction = $dbTransObj->getMemberTransaction($param['billno'],$param['membershipid']);
							$DebitTransaction = $dbTransObj->getMemberDebitTransaction($param['billno'],$param['membershipid']);
							
							
							if($Transaction->num_rows > 0) {
										
								// echo "Here...";
								
													
								$TotalPurcahseAmount = 0;								
								$Loyalty_pts = 0;								
								$Redeem_points = 0;								
								while ($Trans = $Transaction->fetch_assoc()) {
									
									// $tmp = array();
									$Company_id = $Trans["Company_id"];
									$Trans_date = $Trans["Trans_date"];
									// $tmp["Purchase_amount"] = $Trans["Purchase_amount"];
									$TotalPurcahseAmount = $TotalPurcahseAmount + $Trans["Purchase_amount"];
									$Loyalty_pts = $Loyalty_pts + $Trans["Loyalty_pts"];
									$Redeem_points = $Redeem_points+ $Trans["Redeem_points"];
									$Manual_billno = $Trans["Manual_billno"];
									$Bill_no = $Trans["Bill_no"];
									$Seller = $Trans["Seller"];
									$Seller_name = $Trans["Seller_name"];
									// array_push($response["Transaction"], $tmp);
								}
								
								// echo "----Company_id------".$Company_id."---Trans_date----".$Trans_date."----Manual_billno----".$Manual_billno."----Bill_no----".$Bill_no."----Seller----".$Seller."----Seller_name----".$Seller_name."----<br>";
								
								$DebitTotalPurcahseAmount = 0;								
								$DebitLoyalty_pts = 0;								
								$DebitRedeem_points = 0;								
								while ($Trans = $DebitTransaction->fetch_assoc()) {
									
									$DebitTotalPurcahseAmount = $DebitTotalPurcahseAmount + $Trans["Purchase_amount"];
									$DebitLoyalty_pts = $DebitLoyalty_pts + $Trans["Loyalty_pts"];
									$DebitRedeem_points = $DebitRedeem_points+ $Trans["Redeem_points"];
								}
								
								// print_r($DebitTotalPurcahseAmount);
								// print_r($DebitLoyalty_pts);
								// print_r($DebitRedeem_points);
								
								
								// echo "----TotalPurcahseAmount------".$TotalPurcahseAmount."---DebitTotalPurcahseAmount----".$DebitTotalPurcahseAmount."----<br>";
								// die;
								if($TotalPurcahseAmount > $DebitTotalPurcahseAmount){						
									
									
									
									// echo "----cancelamount------".$param['cancelamount']."---Loyalty_pts----".$Loyalty_pts."--TotalPurcahseAmount--".$TotalPurcahseAmount."---<br>";
									
									$Debit_loyalty_pts = number_format((($param['cancelamount'] * $Loyalty_pts) / $TotalPurcahseAmount),2);
									$Debit_redeem_pts = number_format((($param['cancelamount'] * $Redeem_points) / $TotalPurcahseAmount),2);
									
									
									$total_cancelle_amt = $DebitTotalPurcahseAmount;
									$total_Purchase_amount = $TotalPurcahseAmount;


										$new_total_cancelle_amt = $total_cancelle_amt + $param['cancelamount'];
										if ($new_total_cancelle_amt > $total_Purchase_amount) {
											
											$response['error'] = true;
											$response['message'] = "Cancellation amount is greater than remaining amount. Please try again";
											
										} else {
									
											// echo "----Debit_loyalty_pts------".$Debit_loyalty_pts."---Debit_redeem_pts----".$Debit_redeem_pts."--total_cancelle_amt--".$total_cancelle_amt."----total_Purchase_amount--".$total_Purchase_amount."----<br>";
											
											
											// echo "----You can cancel----<br>";
											// die;
											
											// $response["error"] = false;
											$id = $user['id'];
											$Membership_ID = $user['Membership_ID'];
											$Current_balance = $user['Current_balance'];
											
											// echo "---id---".$id."----<br>";
											// echo "---Membership_ID---".$Membership_ID."----<br>";
																						
											
											$timezone_entry = $_SESSION["timezone_entry"];
											$logtimezone = $timezone_entry;
											$timezone = new DateTimeZone($logtimezone);
											$date = new DateTime();
											$date->setTimezone($timezone);
											$Todays_date_time = $date->format('Y-m-d H:i:s');
											$Todays_date = $date->format('Y-m-d');
											
											
											
											$TransPara['Trans_type']=26;
											$TransPara['Company_id']=$_SESSION["company_id"];
											$TransPara['Trans_date']=$Todays_date_time;
											$TransPara['Purchase_amount']=$param['cancelamount'];
											$TransPara['Remarks']='Debit Transaction';
											$TransPara['Card_id']=$Membership_ID;
											$TransPara['Seller_name']=$Seller_name;
											$TransPara['Seller']=$Seller;
											$TransPara['Enrollement_id']=$id;
											$TransPara['Bill_no']=$Bill_no;
											$TransPara['Manual_billno']=$Manual_billno;
											$TransPara['Loyalty_pts']=$Debit_loyalty_pts;
											$TransPara['Redeem_points']=$Debit_redeem_pts;
											
											$insertDebitTransaction = $dbTransObj->insertDebitTransaction($TransPara);
											
											
											
											print_r($insertDebitTransaction);
											
											if($insertDebitTransaction == SUCCESS){
												
												
												
												/* $MemberPara['id']=$user['id'];
												$MemberPara['Debit_points']=$user['Debit_points'];
												$MemberPara['Total_topup']=$Current_balance + $param['points'] ; */
												

													

												$Update_debit = $user['Debit_points'] + $Debit_loyalty_pts;

												/* $reddem_amount = $user['Total_reddems'] + $Debit_redeem_pts;
												// $reddem_amount = $reddem_amt + $Debit_redeem_pts;
												$new_purchase_amount = $total_purchase_amt;
												$Curent_balance = round($user['Current_balance'] - $Debit_loyalty_pts);
												// $Topup_amt = $topup;
												// $Blocked_points = $Blocked_points;

												$CustomerData1 = array(
													'total_purchase' => $new_purchase_amount,
													'Current_balance' => $Curent_balance,
													'Debit_points' => $Update_debit
												);


												$result2 = $this->Transactions_model->update_customer_debit($Customer_enroll_id, $Card_id, $Company_id, $CustomerData1); */




												$MemberPara['Debit_points']=$Update_debit;
												$MemberPara['Current_balance']=$user['Current_balance'];
												$MemberBalance = $dbTransObj->updateMemberBalance($MemberPara,$user['id']);
												
												
												
											  
											  
												$user = $userObj->getUserByMembership($param['membershipid'],$param['phoneno']);
											  
												$EmailParam["error"] = false;
												$EmailParam['id'] = $user['id'];
												$EmailParam['First_name'] = $user['fname'];
												$EmailParam['Last_name'] = $user['lname'];
												$EmailParam['User_name'] = $user['email'];	
												$EmailParam['Membership_ID'] = $user['Membership_ID'];	
												$EmailParam['Company_name'] = $_SESSION['Company_name'];
												$EmailParam['Loyalty_program_name'] = $_SESSION['Company_name'];
												$EmailParam['Website'] = $_SESSION['Website'];
												$Current_balance1 = $user['Current_balance']-($user['Blocked_points']+$user['Debit_points']);
												$EmailParam['Current_balance']=$Current_balance1;
												$EmailParam['Purchase_date']=$Trans_date;
												$EmailParam['Cancellation_date']=$Todays_date_time;
												$EmailParam['Purchase_amount']=$TotalPurcahseAmount;
												$EmailParam['Cancelled_amount']=$param['cancelamount'];
												$EmailParam['Debited_points']=round($Debit_loyalty_pts);
												$EmailParam['Bill_no']=$Manual_billno;
												
												$EmailParam['Email_template_id'] =4; 
												
												// echo "<br>here....";
												$email = $dbHandlerObj->sendEmail($EmailParam);										
												$response["error"] = false;
												$response["message"] = "Successfully procced debit transaction";
												// echo "<br>here..11..";
											  
											  
											  
												
												
											} else {
												
												$response["error"] = false;
												$response["message"] = "An error occurred. Please try again";
											}
											
											
											
											
											/* $Topup = $userObj->insertTopup($TransPara);								
											if($Topup == SUCCESS){
												
												
												$BillPara['seller_id']=$_SESSION["seller_id"];
												$BillPara['billno_withyear_ref']=$billno_withyear_ref;
												
												$TopupBill = $dbHandlerObj->updateTopupBillNo($BillPara);		
												$MemberPara['id']=$user['id'];
												$MemberPara['Total_topup']=$Current_balance + $param['points'] ;								
												$MemberBalance = $dbHandlerObj->updateMemberBalance($MemberPara);
												
												
												$EmailParam["error"] = false;
												$EmailParam['id'] = $user['id'];
												$EmailParam['First_name'] = $user['fname'];
												$EmailParam['Last_name'] = $user['lname'];
												$EmailParam['User_name'] = $user['email'];	
												$EmailParam['Membership_ID'] = $user['Membership_ID'];	
												$EmailParam['Company_name'] = $_SESSION['Company_name'];
												$EmailParam['Outlet_name'] = $_SESSION["seller_name"];
												$EmailParam['Credit_points'] = $param['points'];
												$EmailParam['Loyalty_program_name'] = $_SESSION['Company_name'];
												$EmailParam['Website'] = $_SESSION['Website'];
												$Current_balance1 = $user['Current_balance']-($user['Blocked_points']+$user['Debit_points']);
												$EmailParam['Current_balance']=$Current_balance1 + $param['points'] ;
												$EmailParam['Email_template_id'] =3; 
												

												$email = $dbHandlerObj->sendEmail($EmailParam);										
												$response["error"] = false;
												$response["message"] = "Successfully procced debit transaction";
												
												
											} else if($Topup == FAIL){
												
												$response["error"] = false;
												$response["message"] = "An error occurred. Please try again";
											} */
											
										}

								} else {
									
									$response['error'] = true;
									$response['message'] = "Cancellation amount is greater than remaining amount. Please try again";
								}								
								
							} else {
							
								// unknown error occurred
								$response['error'] = true;
								$response['message'] = "Transaction not found. Please try again";
							}
								
							
						} else {
							
							// unknown error occurred
							$response['error'] = true;
							$response['message'] = "An error occurred. Please try again";
						}			
					
				} 
				else
				{
					$response["error"] = true;
					$response["message"] = "Sorry, unable procced to debit transaction";
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