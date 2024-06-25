<?php
require_once '../include/DbHandler.php';
require_once '../include/GiftCardHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require_once '../include/SendEmailHandler.php';
require_once '../include/LogHandler.php';
require '.././libs/Slim/Slim.php';


use lib\Slim\Middleware\SessionCookie;
// session_start();
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
	function authenticate(\Slim\Route $route)
	{
		
		// Getting request headers
		$headers = apache_request_headers();
		$response = array();
		$app = \Slim\Slim::getInstance();
		
		$app->config('debug', true);
		
		
		 $comp = new CompHandler();
		 
		 $dbHandlerObj = new DbHandler();

		// Verifying Authorization Header
		if (isset($headers['Authorization'])) 
		{
			// get the api key
			$api_key = $headers['Authorization'];
			// validating api key
			if (!$comp->isValidApiKey($api_key)) 
			{ 
				// api key is not present in users table
				$response["error"] = true;
				$response["message"] = "Access Denied. Invalid Api key";
				// $response["message"] =INVALID_KEY;
				echoRespnse($response);
				$app->stop();
			} 
			else 
			{	
				global $Company_id;
			
				$comp = new GiftCardHandler();
				$result = $comp->getCompanyDetails($api_key);
				
				if ($result != NULL) 
				{
					$_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];			
					$_SESSION["phonecode"] = $result["phonecode"];							
					$_SESSION["Company_Redemptionratio"] = $result["Redemptionratio"];		
					$_SESSION["Company_Currency"] = $result["Currency_name"];		
					$_SESSION["Points_used_flag"] = $result["Points_used_gift_card"];		
					$_SESSION["Min_gift_card_amount"] = $result["Minimum_gift_card_amount"];		
					$_SESSION["Gift_card_validity_days"] = $result["Gift_card_validity_days"];		
					
					$superSeller= $dbHandlerObj->superSellerDetails();
					
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
				} 
				else 
				{
					$response["error"] = true;
					$response["message"] = "Invalid API Username";
					echoRespnse($response);
				}
					session_cache_limiter(false);			
			}	
		} 
		else 
		{	
			// api key is missing in header
			$response["error"] = true;
			$response["message"] = "Api key is misssing";
			echoRespnse($response);
			$app->stop();
		}
	}
$app->post('/buy','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	// to get an array so try following..
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	// check for required params
	verifyRequiredParams(array('membershipid','amount'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	$gift_card_amount = $request_array['amount'];
	$gift_card_amount = str_replace( ',', '', $gift_card_amount);
	$redeem_details = $request_array['redeemdetails'];
	$redeem_points = $redeem_details['points'];
	$points_amount = $redeem_details['amount'];
	$payment_details = $request_array['paymentdetails'];
	$payment_id = $payment_details['id'];
	$payment_reference = $payment_details['reference'];
	$payment_name = $payment_details['name'];
	$payment_amount = $payment_details['amount'];
	$payment_amount = str_replace( ',', '', $payment_amount);

	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	$Points_used_flag = $_SESSION["Points_used_flag"];
	$Min_gift_card_amount = $_SESSION["Min_gift_card_amount"];
	$Gift_card_validity = $_SESSION["Gift_card_validity_days"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$giftcardObj = new GiftCardHandler();
	$sendEObj = new SendEmailHandler();
	$logHObj = new LogHandler();
	 // echo "Gift_card_validity--".$Gift_card_validity; exit;
	$user = $giftcardObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$User_email_id = $user['User_email_id'];
		$User_phone_no = $user['Phone_no'];
		$User_id = $user['User_id'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Member_name = $fname.' '.$lname;
		
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
		}
		if($gift_card_amount < $Min_gift_card_amount)
		{
			$response["errorcode"] = 3110;
			$response["message"] = "Gift Card Amount is less it should Be Minimum $Min_gift_card_amount";
			echoRespnse($response); 
			exit;
		}
		if($Points_used_flag == 0 && $redeem_points > 0)
		{
			$response["errorcode"] = 3111;
			$response["message"] = "Company Not Allowing To Redeem Points For Purchase Gift Card";
			echoRespnse($response); 
			exit;
		}
		if($Available_point_balance < $redeem_points)
		{
			$response["errorcode"] = 3101;
			$response["message"] = "Insufficient Current Balance";
			$response["currentbalance"] = $Available_point_balance;
			echoRespnse($response); 
			exit;
		}
		if($redeem_points > 0)
		{
			$Reddem_amount = Validate_redeem_points($redeem_points,$Redemption_ratio,$gift_card_amount);
			if($Reddem_amount == 0000)
			{
				$response["errorcode"] = 2066;
				$response["message"] = "Equivalent Redeem Amount is More than Bill Amount";
				echoRespnse($response); 
				exit;
			}
			else
			{
				$Points_amount = $Reddem_amount;
				$payment_id = 4; // Redeem points
			}
		}
		
		$payment_amount = $gift_card_amount - $Points_amount;
		
		$param['timezone_entry']=$user['timezone_entry'];
		$logtimezone = $param['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');
		
		$validity = $Gift_card_validity;
		$Valid_till = date("Y-m-d", strtotime($Todays_date. " + $validity days"));
		
		$SellerDetails=$giftcardObj->superSellerDetails();   
        $Super_Seller_id = $SellerDetails['id'];
        $Super_Seller_Name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
        $Seller_timezone_entry =$SellerDetails['timezone_entry'];
		$Purchase_Bill_no = $SellerDetails['Purchase_Bill_no'];
		$tp_db = $Purchase_Bill_no;
		$len = strlen($tp_db);
		$str = substr($tp_db,0,5);
		$bill = substr($tp_db,5,$len);
		
		$Gift_card_no = getVoucher();
		
		$giftData['Company_id']=$Company_id;
		$giftData['Gift_card_id']=$Gift_card_no;
		$giftData['Card_balance']=$gift_card_amount;
		$giftData['Card_value']=$gift_card_amount;
		$giftData['Card_id']=$Membership_ID;
		$giftData['User_name']=trim($Member_name);
		$giftData['Email']=$User_email_id;
		$giftData['Phone_no']=$User_phone_no;
		$giftData['Payment_Type_id']=$payment_id;
		$giftData['Seller_id']=$Super_Seller_id;
		$giftData['Valid_till']=$Valid_till;
		
		$Gift_card_detail = $giftcardObj->Insert_gift_card($giftData);		
		
		$transData['Company_id']=$Company_id;
		$transData['Trans_type']=4;
		$transData['Purchase_amount']=$gift_card_amount;
		$transData['Paid_amount']=$payment_amount;
		$transData['Mpesa_Paid_Amount']=$payment_amount;
		$transData['Mpesa_TransID']=$payment_reference;
		$transData['Redeem_points']=$redeem_points;
		$transData['Redeem_amount']=$Points_amount;
		$transData['Payment_type_id']=$payment_id;
		$transData['Remarks']="Purchase gift card";
		$transData['Trans_date']=$lv_date_time;
		$transData['Enrollement_id']=$Enrollement_id;
		$transData['Bill_no']=$bill;
		$transData['Card_id']=$Membership_ID;
		$transData['Seller']=$Super_Seller_id;
		$transData['Seller_name']=$Super_Seller_Name;
		$transData['Online_payment_method']=$payment_name;
		$transData['Credit_Cheque_number']=$DPOTransactionApproval1;
		$transData['Item_code']=$Gift_card_no;
		$transData['GiftCardNo']=$Gift_card_no;
		
		$Transaction_detail = $giftcardObj->Insert_giftcard_purchase_transaction($transData);
		
		if($Transaction_detail == SUCCESS)
		{
			if($redeem_points > 0)
			{
				$Enroll_details = $giftcardObj->get_enrollment_details($Enrollement_id);
				$Card_id=$Enroll_details['Card_id'];
				$Current_balance=$Enroll_details['Current_balance'];
				$Total_topup_amt=$Enroll_details['Total_topup_amt'];
				$Blocked_points =$Enroll_details['Blocked_points'];
				$Total_reddems =$Enroll_details['Total_reddems'];
				$First_name =$Enroll_details['First_name'];
				$Last_name =$Enroll_details['Last_name'];
				
				$Total_Current_Balance = $Current_balance - $redeem_points;
				$Total_reddems = $Total_reddems + $redeem_points;
				
				$MemberPara['Total_reddems'] = $Total_reddems;								
				$MemberPara['Current_balance'] = $Total_Current_Balance;	
				
				$update_balance=$giftcardObj->update_member_balance($MemberPara,$Enrollement_id);
			}
			
			$bill_no = $bill + 1;
			$billno_withyear = $str.$bill_no;
			$BillPara['Purchase_Bill_no'] = $billno_withyear;		
			$result4 = $giftcardObj->updatePurchaseBillno($BillPara,$Super_Seller_id);
			
			$EmailParam["error"] = false;
			$EmailParam['Order_no'] = $bill;
			$EmailParam['Gift_card_no'] = $Gift_card_no;
			$EmailParam['Gift_card_amount'] = number_format($gift_card_amount, 2);	
			$EmailParam['Redeem_points'] = $redeem_points;
			$EmailParam['Redeem_amount'] = number_format($Points_amount, 2);
			$EmailParam['Paid_amount'] = number_format($payment_amount, 2);
			$EmailParam['Valid_till'] = $Valid_till;
			$EmailParam['Email_template_id'] =6; 
			
			$email = $sendEObj->sendEmail($EmailParam,$Enrollement_id); 
			
			/*********************Insert Log*************************/
				$log_data['Company_id']=$Company_id;
				$log_data['From_enrollid']=$Enrollement_id;
				$log_data['From_emailid']=$User_email_id;
				$log_data['From_userid']=$User_id;
				$log_data['To_enrollid']=$Enrollement_id;
				$log_data['Transaction_by']=$Member_name;
				$log_data['Transaction_to']= $Member_name;
				$log_data['Transaction_type']= 'Purchase Gift Card';
				$log_data['Transaction_from']= 'Purchase Gift Card API';
				$log_data['Operation_type']= 1;
				$log_data['Operation_value']= 'Bill No : '.$bill.', Amount : '.$gift_card_amount; 
				$log_data['Date']= $Todays_date;
				
				$Log = $logHObj->insertLog($log_data);
			/**********************Insert Log*************************/	
			
			$response = array();
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			
			$APILogParam['Company_id'] =$Company_id;
			$APILogParam['Trans_type'] = 4;
			$APILogParam['Outlet_id'] = $Super_Seller_id;
			$APILogParam['Bill_no'] = $bill;
			$APILogParam['Card_id'] = $Membership_ID;
			$APILogParam['Date'] = $lv_date_time;
			$APILogParam['Json_input'] = $json;
			$APILogParam['Json_output'] = json_encode($response);
			$APILog = $logHObj->insertAPILog($APILogParam); 
		}
		else
		{
			$response = array();
			$response["errorcode"] = 2068;
			$response["message"] = "Unsuccessful";
		}		
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
	//exit;
});
$app->get('/getgiftcards','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$giftcardObj = new GiftCardHandler();
	
	$user = $giftcardObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Member_name = $fname.' '.$lname;
		
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
		}
		
		$GetGiftCards = $giftcardObj->Get_gift_cards($Membership_ID,$Company_id);
		
		if($GetGiftCards != Null)
		{
			$GiftCards_Details = array();
			foreach($GetGiftCards as $row) 
			{
				$GiftCards_Details[] = array("code"=>$row['Gift_card_id'],"amount"=>number_format($row['Card_balance'],2),"validity"=>$row['Valid_till']);		
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["giftcardsdata"] = $GiftCards_Details;
		}
		else
		{
			$response["errorcode"] = 2012;
			$response["message"] = "No Data Found";
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
	// exit;
}); 
$app->post('/validate','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid','outletno','orderamount'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$Outlet_no = $request_array['outletno'];
	$Order_amount = $request_array['orderamount'];
	$Order_amount = str_replace( ',', '', $Order_amount);
	
	$GiftCard_details = $request_array['giftcarddetails'];
	$GiftCard_code = $GiftCard_details['code'];
	
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$giftcardObj = new GiftCardHandler();
	
	$user = $giftcardObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Member_name = $fname.' '.$lname;
		
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
		}
		
		$Outlet_details = $giftcardObj->get_outlet_details($Outlet_no,$Company_id);
			
		if($Outlet_details!=NULL)
		{
			$Seller_id = $Outlet_details['Enrollement_id'];
			$Seller_name = $Outlet_details['First_name'].' '.$$Outlet_details['Last_name'];
			
			$timezone_entry=$Outlet_details['timezone_entry']; 
			$Sub_seller_admin = $Outlet_details['Sub_seller_admin'];
			$Sub_seller_Enrollement_id = $Outlet_details['Sub_seller_Enrollement_id'];
			
			if($Sub_seller_admin == 1) 
			{
				$delivery_outlet = $Seller_id;
			}
			else 
			{
				$delivery_outlet = $Sub_seller_Enrollement_id;
			}
			
			$timezone = new DateTimeZone($timezone_entry);
			$date = new DateTime();
			$date->setTimezone($timezone);
			$lv_date_time=$date->format('Y-m-d H:i:s');
			$Todays_date = $date->format('Y-m-d');
			
		}
		else
		{
			$response["errorcode"] = 2009;
			$response["message"] = "Invalid outlet no.";
			echoRespnse($response); 
			exit;
		}
		
		$Giftcard_result = $giftcardObj->Validate_gift_card($Company_id,$GiftCard_code,$Membership_ID);
		
		if($Giftcard_result != Null)
		{
			$Gift_card_id = $Giftcard_result['Gift_card_id'];
			$Card_value = $Giftcard_result['Card_value'];
			$GiftCard_balance = $Giftcard_result['Card_balance'];
			$Valid_till = $Giftcard_result['Valid_till'];
			$Card_Payment_Type_id = $Giftcard_result['Payment_Type_id'];
			$Discount_percentage = $Giftcard_result['Discount_percentage'];
			
			$GiftCard_balance = str_replace( ',', '', $GiftCard_balance);
			
			if($GiftCard_balance > 0)
			{
				$Pos_giftcard_amount = $GiftCard_balance;										
			}
			else
			{
				$response["errorcode"] = 3112;
				$response["message"] = "Invalid Gift Card Or No Balance In Gift Card.";
				echoRespnse($response); 
				exit;
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["membershipid"] = $Membership_ID;
			$response["membername"] = $Member_name;
			$response["orderamount"] = number_format($Order_amount,2);
			$response["giftcardcode"] = $Gift_card_id;
			$response["giftcardamount"] = number_format($Pos_giftcard_amount,2);
			echoRespnse($response); 
			exit;
		}
		else
		{
			$response["errorcode"] = 3112;
			$response["message"] = "Invalid Gift Card Or No Balance In Gift Card.";
			echoRespnse($response); 
			exit;
		}	
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
	// exit;
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
        echoRespnse($response);
        $app->stop();
    }
}
function Validate_redeem_points($Point_reedem,$Redemption_ratio,$Bill_amount)
{
	$Redeem_amount = ($Point_reedem/$Redemption_ratio); //.toFixed(2);
	
	$abc = round(1/$Redemption_ratio);	
	
	if($Point_reedem!="")
	{
		$Redeem_amount = ($Point_reedem/$Redemption_ratio);	
	}	
	
	$bb = ($Redeem_amount - $Bill_amount);  
	$Redeem_amount2 = $Bill_amount - $Redeem_amount;  
	if($bb >= $abc)
	{
		$Error_flag = 0000; //Equivalent Redeem Amount is More than Total Bill Amount
		$result12 =$Error_flag;
	}
	else if($Redeem_amount2 < 0)
	{
		$Redeem_amount = $Bill_amount;
		$result12 = $Redeem_amount;   //Adjust 1 point here..allow to redeem 1 point extra
	}
	else if($Redeem_amount<=$Bill_amount)
	{
		$result12 = $Redeem_amount; // Successfull
	}
	else if($Redeem_amount > $Bill_amount) 
	{
	  $Error_flag = 0000; //Equivalent Redeem Amount is More than Total Bill Amount
	 
	  $result12 =$Error_flag;
	}

	return $result12;
}
function getVoucher()
{
	$characters = '123456789';
	$string = '';
	$Voucher_no="";
	for ($i = 0; $i < 10; $i++) 
	{
		$Voucher_no .= $characters[mt_rand(0, strlen($characters) - 1)];
	}
	
	return $Voucher_no;
}
/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    // $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}
$app->run();
?>