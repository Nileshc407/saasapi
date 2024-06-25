<?php
require_once '../include/DbHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require_once '../include/LogHandler.php';
require_once '../include/ReportHandler.php';
require '.././libs/Slim/Slim.php';


use lib\Slim\Middleware\SessionCookie;
session_start();
error_reporting(1);
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
		{
			if (!$comp->isValidApiKey($api_key)) 
			{ 
				$response["error"] = true;
				$response["message"] = "Access Denied. Invalid Api key";
				
				echoRespnse($response);
				$app->stop();
			} 
			else 
			{	
				global $Company_id;
			
				$comp = new ReportHandler();
				$result = $comp->getCompanyDetails($api_key);
				
				if ($result != NULL) 
				{
					$_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];			
					$_SESSION["phonecode"] = $result["phonecode"];							
					$_SESSION["Company_Redemptionratio"] = $result["Redemptionratio"];		
					$_SESSION["Company_Currency"] = $result["Currency_name"];		
					
					/* $superSeller= $dbHandlerObj->superSellerDetails();
					
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
					$Todays_date = $date->format('Y-m-d'); */
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
/*$app->get('/getreport','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"]; 
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	// echo "company---".$Company_id; exit;	
	verifyRequiredParams(array('membershipid','transtype','fromdate','todate'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$Trans_type = $request_array['transtype'];
	$From_date = $request_array['fromdate'];
	$To_date = $request_array['todate'];
	
	$From_date=date('Y-m-d 00:00:00',strtotime($From_date));
	$To_date=date('Y-m-d 23:59:59',strtotime($To_date));	
	
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$reportObj = new ReportHandler();
	
	$user = $reportObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
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
		if($Trans_type ==12) //Online Purchase
		{
			$OnlinePurchaseDetails = $reportObj->get_online_purchase_report($Enrollement_id,$Company_id,$From_date,$To_date);
			if($OnlinePurchaseDetails != NULL)
			{
				$Report_Details = array();
				
				foreach($OnlinePurchaseDetails as $row)
				{
					
					
					$Report_Details[] = array("date"=>$row['Trans_date'],"billno"=>$row['Bill_no'],"orderno"=>$row['Order_no'],"seller"=>$row['Seller_name'],"orderamount"=>number_format($row['Purchase_amount'],2),"redeemamount"=>number_format($row['Redeem_amount'],2),"discountamount"=>number_format($row['Total_discount'],2),"paidamount"=>number_format($row['Paid_amount'],2),"loyaltypoints"=>round($row['Loyalty_pts']));
				}
				
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["reportdata"] = $Report_Details;
				echoRespnse($response);
				exit;
			}
			else
			{
				$response["errorcode"] = 2012;
				$response["message"] = "No Data Found";
				echoRespnse($response);
				exit;
			}
		}
		else if($Trans_type == 10) //Redemption
		{
			$RedeemptionDetails = $reportObj->get_redemption_report($Enrollement_id,$Company_id,$From_date,$To_date);
			if($RedeemptionDetails != NULL)
			{
				$Report_Details = array();
				
				foreach($RedeemptionDetails as $row)
				{
					if($row['Voucher_status'] == 30)
					{
						$Voucher_status = "Issued";
					}
					else if($row['Voucher_status'] == 3)
					{
						$Voucher_status = "Used";
					}
					else
					{
						$Voucher_status = "";
					}
					
					$Report_Details[] = array("date"=>$row['Trans_date'],"billno"=>$row['Bill_no'],"seller"=>$row['Seller_name'],"item"=>$row['Merchandize_item_name'],"quantity"=>$row['Quantity'],"points"=>$row['Redeem_points'],"voucherno"=>$row['Voucher_no'],"voucherstatus"=>$Voucher_status);
				}
				
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["reportdata"] = $Report_Details;
				echoRespnse($response);
				exit;
			}
			else
			{
				$response["errorcode"] = 2012;
				$response["message"] = "No Data Found";
				echoRespnse($response);
				exit;
			}
		}
		else
		{
			$response["errorcode"] = 2012;
			$response["message"] = "No Data Found";
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
}); */

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