<?php
require_once '../include/DbHandler.php';
require_once '../include/OrderHandler.php';
require_once '../include/DiscountHandler.php';
require_once '../include/VoucherHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require_once '../include/SendEmailHandler.php';
require_once '../include/LogHandler.php';
require_once '../include/UserHandler.php';
require '.././libs/Slim/Slim.php';

use lib\Slim\Middleware\SessionCookie;
session_start();
// error_reporting(0);
// error_reporting(-1);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
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
				$comp = new OrderHandler();
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
					$_SESSION["Stamp_voucher_validity"] = $result["Stamp_voucher_validity"];		
					$_SESSION["Ecommerce_flag"] = $result["Company_ecommerce_flag"];		
					$_SESSION["First_trans_bonus_flag"] = $result["Company_first_trans_bonus_flag"];		
					$_SESSION["Bday_bonus_flag"] = $result["Company_bday_bonus_flag"];		
					$_SESSION["Block_points_flag"] = $result["Company_block_points_flag"];		
					// echo $api_key; exit;
					$superSeller= $comp->superSellerDetails();
					
					$_SESSION["seller_id"] = $superSeller["Enrollement_id"];
					$_SESSION["seller_name"] = $superSeller["First_name"].' '.$superSeller["Last_name"];
					$_SESSION["country"] = $superSeller["Country"];
					$_SESSION["state"] = $superSeller["State"];
					$_SESSION["city"] = $superSeller["City"];
					$_SESSION["topup_Bill_no"] = $superSeller["Topup_Bill_no"];
					$_SESSION["timezone_entry"] = $superSeller["timezone_entry"];
					
					/* $timezone_entry = $superSeller["timezone_entry"]; 
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
					echoRespnse($response); exit;
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
	$app->post('/fetchitem','authenticate', function() use ($app) 
	{  
		$json=$app->request->getbody();
		// to get an array so try following..
		$request_array=json_decode($json,true);
		$Company_id = $_SESSION["company_id"];

		verifyRequiredParams(array('membershipid','itemcode'),$request_array);

		$response = array();

		$param['membershipid'] = $request_array['membershipid'];
		$ItemCode = $request_array['itemcode'];
		$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];

		$param['Company_name'] = $_SESSION["company_name"];
		$param['Loyalty_program_name'] = $_SESSION["company_name"];
			
		// require_once dirname(__FILE__) . '/PassHash.php';
		$phash = new PassHash();
		$dbHandlerObj = new DbHandler();

		$orderObj = new OrderHandler();

		$user = $orderObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
			
		if($user != NULL) 
		{
			$Company_id = $user['Company_id'];
			$Enrollement_id = $user['Enrollement_id'];
			$Membership_ID = $user['Card_id'];
			$fname = $user['First_name'];
			$lname = $user['Last_name'];
			$Current_balance = $user['Current_balance'];
			$param['timezone_entry']=$user['timezone_entry'];
		
			$logtimezone = $param['timezone_entry'];
			$timezone = new DateTimeZone($logtimezone);
			$date = new DateTime();
			$date->setTimezone($timezone);
			$lv_date_time=$date->format('Y-m-d H:i:s');
			$Todays_date = $date->format('Y-m-d');
			
			$ItemDetails = $orderObj->Get_item_details($ItemCode,$Company_id);

			if($ItemDetails != NULL)
			{		
				$Merchandize_item_code = $ItemDetails['Company_merchandize_item_code'];
				$Item_name = $ItemDetails['Merchandize_item_name'];
				$Merchandize_item_description = $ItemDetails['Merchandise_item_description'];
				$Merchandize_Cost_price = $ItemDetails['Cost_price'];
				$Merchandize_Billing_price = $ItemDetails['Billing_price'];
				$Merchandize_Billing_price_in_points = $ItemDetails['Billing_price_in_points'];
				
				$result01 = $orderObj->check_scan_item($Company_id,$Enrollement_id,$Merchandize_item_code,$lv_date_time);
				
				if($result01 > 0)
				{
					$response["errorcode"] = 2038;
					$response["message"] = "The item code has already been scanned";
					echoRespnse($response); 
					exit;
				} 
				else
				{
					/* $data78['Company_id'] = $Company_id;
					$data78['Enrollment_id'] = $Enrollement_id;
					$data78['Item_code'] = $Merchandize_item_code;

					$orderObj->insert_scan_item($data78);
					 */
					$response["errorcode"] = 1001;
					$response["message"] = "Successful";
					$response["code"] = $Merchandize_item_code;
					$response["name"] = $Item_name;
					$response["description"] = $Merchandize_item_description;
					$response["price"] = $Merchandize_Billing_price;
					// $response["costprice"] = $Merchandize_Cost_price;
					// $response["pointprice"] = $Merchandize_Billing_price_in_points;
				}
			}
			else
			{
				$response["errorcode"] = 2037;
				$response["message"] = "items not exist";
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
$app->post('/applydiscount','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	// to get an array so try following..
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	
	verifyRequiredParams(array('membershipid','outletno','orderamount'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$Outlet_no = $request_array['outletno'];
	$Bill_Total = $request_array['orderamount'];
	$Bill_Total = str_replace( ',', '', $Bill_Total);
	$Item_details = $request_array['itemsdata'];
	
	$Pos_discount = 0;
	$API_flag_call = 87;
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$orderObj = new OrderHandler();
	$discountObj = new DiscountHandler();
	
	$user = $orderObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Cust_enrollement_id = $user['Enrollement_id'];
		$Card_id = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Memeber_name = $fname.' '.$lname;
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Tier_id = $user['Tier_id'];
		
		$Current_point_balance = $Current_balance-($Blocked_points+$Debit_points);
	
		if($Current_point_balance < 0)
		{
			$Current_point_balance=0;
		}
		else 
		{
			$Current_point_balance=$Current_point_balance;
		}
		
		$Outlet_details = $orderObj->get_outlet_details($Outlet_no,$Company_id);
			
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
			
			
			if($Bill_Total < $Pos_discount)
			{
				$response["errorcode"] = 1002;
				$response["message"] = "Invalid Order amount";
				echoRespnse($response); 
				exit;
			}
			/*******************declare variable*******************/
				$order_sub_total = 0;	
				$shipping_cost = 0;
				$DiscountAmt = 0;
				$TotalvoucherAmt = 0;
				$TotalDiscountAmt = 0;
				$tax = 0;	
				$i = 0;
			/*******************declare variable*******************/
				foreach($Item_details as $item)
				{ 
					$ItemCode = $item['code']; 
					$ItemQty = $item['quantity']; 
					$Item_price = $item['price'];
					
					$Item_price = str_replace( ',', '', $Item_price);
					$Item_Rate = str_replace( ',', '', $Item_price);
					$ItemQty = str_replace( ',', '', $ItemQty);
					
				/****************discount logic******************/
					$Item_price = $Item_price * $ItemQty;
					$order_sub_total = $order_sub_total + $Item_price;
					$i++;
					
				/*******************get item details*******************/
					$ItemDetails = $orderObj->Get_item_details($ItemCode,$Company_id);
					if($ItemDetails !=NULL)
					{
						$Itemcategory_id = $ItemDetails['Merchandize_category_id'];
						/***************11-7-2020*************/
						$Itemcategory_ids[] = $ItemDetails['Merchandize_category_id'];
						$Itemcategory_price[$Itemcategory_id] = $Item_Rate * $ItemQty;
						/***************11-7-2020*************/
					}
					else
					{	
						$response["errorcode"] = 3103;
						$response["message"] = "Invalid item code Or Item not exist.";
						$response["code"] = $ItemCode;
						echoRespnse($response); 
						exit;
					}
				/*******************get item details*******************/
					
					$DiscountResult = $discountObj->get_discount_value("",$ItemCode,$Item_price,$Company_id,$delivery_outlet,$Cust_enrollement_id,$Tier_id,0,$API_flag_call);
					
					$DisOpt = json_decode($DiscountResult,true);

					if($DisOpt["DiscountAmt"] > 0)
					{
						$TotalDiscountAmt = floor($TotalDiscountAmt + str_replace( ',', '', $DisOpt["DiscountAmt"]));
						
							$ItemDiscounts[$ItemCode] = $DisOpt["DiscountAmt"];
						
					}
					
					if(!empty($DisOpt["discountsArray"]) && is_array($DisOpt["discountsArray"]))
					{
						foreach($DisOpt["discountsArray"] as $k1)
						{
							$Discount_codes[] = $k1;
						}
					}
					if(!empty($DisOpt["discountsArray2"]) && is_array($DisOpt["discountsArray2"]))
					{
						foreach($DisOpt["discountsArray2"] as $k2)
						{
							$Discount_codes_2[] = $k2;
						}
					}
				}
				
				$Itemcategory_ids = array_unique($Itemcategory_ids);
				
				foreach($Itemcategory_ids as $Itemcategory_id)
				{
					$Item_price = $Itemcategory_price[$Itemcategory_id];
					
					$CatDiscountResult = $discountObj->get_category_discount_value($Itemcategory_id,"",$Item_price,$Company_id,$delivery_outlet,$Cust_enrollement_id,$Tier_id,0,0,$API_flag_call);
					
						$DisOpt22 = json_decode($CatDiscountResult,true);
						
						if($DisOpt22["DiscountAmt"] > 0)
						{
							$TotalDiscountAmt = floor($TotalDiscountAmt + $DisOpt22["DiscountAmt"]);
							
						}
						
						if(!empty($DisOpt22["discountsArray"]) && is_array($DisOpt22["discountsArray"]))
						{
								foreach($DisOpt22["discountsArray"] as $k1)
								{
									$Discount_codes[] = $k1;
								}
						}
						
						if(!empty($DisOpt22["discountsArray2"]) && is_array($DisOpt22["discountsArray2"]))
						{
							foreach($DisOpt22["discountsArray2"] as $k2)
							{
								$Discount_codes_2[] = $k2;
							}
						}
				}
				
				$DiscountResult12 = $discountObj->get_discount_value($Itemcategory_id,$ItemCode,$Item_price,$Company_id,$delivery_outlet,$Cust_enrollement_id,$Tier_id,$order_sub_total,$API_flag_call);
				
				$DisOpt12 = json_decode($DiscountResult12,true);
				
				if($DisOpt12["DiscountAmt"] > 0)
				{
				
					$number2 = filter_var($DisOpt12["DiscountAmt"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);//1989.34
				
					$TotalDiscountAmt = ($TotalDiscountAmt + str_replace( ',', '', $number2));		
					
					$_SESSION['BillDiscount'] = $DisOpt12["DiscountAmt"];
				}
				
				if(!empty($DisOpt12["discountsArray"]) && is_array($DisOpt12["discountsArray"]))
				{
					foreach($DisOpt12["discountsArray"] as $k)
					{
						$Discount_codes[] = $k;
					}
				}
				
				if(!empty($DisOpt12["discountsArray2"]) && is_array($DisOpt12["discountsArray2"]))
				{
					foreach($DisOpt12["discountsArray2"] as $k2)
					{
						$Discount_codes_2[] = $k2;
					}
				}
				
				if($DisOpt12["voucherValidity"] != null)
				{ 
					// $this->session->set_userdata('voucherValidity',$DisOpt12["voucherValidity"]);
				}
				
				$TotalDiscountAmt = str_replace( ',', '', $TotalDiscountAmt);
				$DiscountAmt = $TotalDiscountAmt;
				
				if(count($Discount_codes) > 0)
				{
					// $this->session->set_userdata('Discount_codes',$Discount_codes);
				}
			
				if($order_sub_total < $DiscountAmt)
				{
					$DiscountAmt = $order_sub_total;
				}
				$DiscountAmt = str_replace( ',', '', $DiscountAmt);
					
				$order_total = ($order_sub_total + $shipping_cost + $tax) - $DiscountAmt;
				$order_total = $order_total - $Pos_discount;
			
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["membershipid"] = $Card_id;
				$response["membername"] = $Memeber_name;
				$response["orderamount"] = number_format($order_sub_total,2);
				// $response["posdiscount"] = number_format(0,2);
				$response["discountamount"] = number_format($DiscountAmt,2);
				$response["balancedue"] = number_format($order_total,2);
				// $response["discountdetails"] = $Discount_codes;
				
				echoRespnse($response); 
				exit;				
		}
		else
		{
			$response["errorcode"] = 2009;
			$response["message"] = "Invalid outlet no.";
			echoRespnse($response); 
			exit;
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
		echoRespnse($response); 
		exit;
	}
	echoRespnse($response); 
	// exit;
});
$app->get('/getitems','authenticate', function() use ($app) 
{  
	$json=$app->request->getbody();
	// to get an array so try following..
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$orderObj = new OrderHandler();

	$user = $orderObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		
		$Online_Items = $orderObj->get_all_items($Company_id);
		// var_dump($Online_Items); exit;
		if($Online_Items != NULL)
		{
			$Itemsdata= array();
			foreach($Online_Items as $row)
			{	
				$Items_details = array("code" => $row["Company_merchandize_item_code"], "name" =>$row["Merchandize_item_name"],"description" => $row["Merchandise_item_description"],"price" => $row["Billing_price"],"imageurl" => $row["Item_image1"]);
				$Itemsdata[] =$Items_details;
										
			}
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["itemsdata"] = $Itemsdata;
		}
		else
		{
			$response["errorcode"] = 2037;
			$response["message"] = "items not exist";
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
$app->post('/placeorder','authenticate', function() use ($app) 
{  	 
	$Company_id = $_SESSION["company_id"];
	$Ecommerce_flag = $_SESSION["Ecommerce_flag"];
	$Block_points_flag = $_SESSION["Block_points_flag"];
	
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	verifyRequiredParams(array('membershipid','orderamount','outletno'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$bill_amount = $request_array['orderamount'];
	$bill_amount = str_replace( ',', '', $bill_amount);
	$outlet_no = $request_array['outletno'];
	$order_no = $request_array['orderno'];
	$Pos_bill_items = $request_array['itemsdata'];
	
	$voucher_details = $request_array['voucherdetails'];
	$Pos_voucher_no = $voucher_details['code'];
	// $Pos_voucher_no = "";
	$voucher_amount = $voucher_details['amount'];
	// $voucher_amount = 0;
	$Pos_voucher_amount = str_replace( ',', '', $voucher_amount);
	
	$gift_card_details = $request_array['giftcarddetails'];
	$Pos_giftcard_no = $gift_card_details['code'];
	$Pos_giftcard_no = "";
	$redeem_details = $request_array['redeemdetails'];
	$redeem_points = $redeem_details['points'];
	$redeem_amount = $redeem_details['amount'];
	$redeem_points = str_replace( ',', '', $redeem_points);
	$Pos_points_amount = str_replace( ',', '', $redeem_amount);
	$Pos_points_redeemed = $redeem_points;
	$loyaltydiscount = $request_array['loyaltydiscount'];
	// $loyaltydiscount = 0;
	$loyalty_discount = str_replace( ',', '', $loyaltydiscount);
	
	$payment_details = $request_array['paymentdetails']; 
	$payment_id = $payment_details['id'];
	$payment_reference = $payment_details['reference'];
	$payment_name = $payment_details['name'];
	$payment_amount = $payment_details['amount'];
	$payment_amount = str_replace( ',', '', $payment_amount);
	
	$subtotal = $bill_amount;
	$ChannelCompanyId = 0;
	$API_flag_call = 90;
	$gained_points_fag = 0;
	$Pos_order_no = $order_no;
	$Pos_bill_no = $order_no;
	$Pos_bill_amount = $bill_amount;  
	$Pos_outlet_id = $outlet_no;
	
	$Pos_discount = 0;
	$Pos_loyalty_discount = $loyalty_discount;
	
	$delivery_outlet = $Pos_outlet_id;
	$Cust_redeem_point = $Pos_points_redeemed;
	$EquiRedeem = $Pos_points_amount;
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	$Stamp_voucher_validity = $_SESSION["Stamp_voucher_validity"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$orderObj = new OrderHandler();
	$discountObj = new DiscountHandler();
	$voucherObj = new VoucherHandler();
	$sendEObj = new SendEmailHandler();
	$logHObj = new LogHandler();
	
	$user = $orderObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Cust_enrollement_id = $user['Enrollement_id'];
		$Customer_enroll_id = $user['Enrollement_id'];
		$cust_enrollment_id = $user['Enrollement_id'];
		$lv_member_Tier_id = $user['Tier_id'];
		$Membership_ID = $user['Card_id'];
		$CardId = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$User_email_id = $user['User_email_id'];
		$User_phone_no = $user['Phone_no'];
		$User_id = $user['User_id'];
		$bal = $user['Current_balance'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Total_reddems = $user['Total_reddems'];
		$total_purchase = $user['total_purchase'];
		$Total_topup_amt = $user['Total_topup_amt'];
		$Zipcode = $user['Zipcode'];
		$District = $user['District'];
		$Sex = $user['Sex'];
		$Age = $user['Age'];
		$Date_of_birth = $user['Date_of_birth'];
		$joined_date = $user['joined_date'];
		$Country_id = $user['Country'];
		$State_id = $user['State'];
		$City_id = $user['City'];
		$Member_name = $fname.' '.$lname;
		
		$param['timezone_entry']=$user['timezone_entry'];
		$logtimezone = $param['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');
		
		if($Block_points_flag == 1)
		{
			$Available_point_balance = $Current_balance - $Debit_points;
		}
		else
		{
			$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		}
		
		if($Available_point_balance<0)   
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
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
			if($Block_points_flag ==1)
			{
				if($Blocked_points < $redeem_points)
				{
					$response["errorcode"] = 3102;
					$response["message"] = "Insufficient block points to use";
					$response["blockpoints"] = $Blocked_points;
					echoRespnse($response); 
					exit;
				}
				
				$result02 = $orderObj->get_block_points_details($order_no,$outlet_no,$Company_id,$Enrollement_id,$redeem_points,$lv_date_time);
				
				if($result02 == Null)
				{
					$response["errorcode"] = 3105;
					$response["message"] = "Redeem points doesn't match with order no. or points has been already released or used!";
					echoRespnse($response); 
					exit;
				}
			}
			
			$bill_amount = $bill_amount-$Pos_loyalty_discount;
			$Reddem_amount = Validate_redeem_points($redeem_points,$Redemption_ratio,$bill_amount);
			if($Reddem_amount == 0000)
			{
				$Points_amount = 0;
				$response["errorcode"] = 2066;
				$response["message"] = "Equivalent Redeem Amount is More than Order Amount";
				echoRespnse($response); 
				exit;
			}
			else
			{
				$Points_amount = $Reddem_amount;
				$Cust_redeem_point = $redeem_points;
				$Pos_points_amount = $Reddem_amount;
				$EquiRedeem = $Pos_points_amount;
			}
		}
	/*******************************check bill no***************************/
		$result01 = $orderObj->check_order_bill_no($order_no,$outlet_no,$Company_id,$lv_date_time);
				
		if($result01 > 0)
		{
			//$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
			
			$response["errorcode"] = 2067;
			$response["message"] = "Order no. already exist.";
			echoRespnse($response); 
			exit;
		} 
	/*******************************check bill no***************************/
	/*******************************check bill amount***************************/
		if($Pos_bill_items != Null)
		{
			$check_price_array = array();
			foreach($Pos_bill_items as $item)
			{ 
				$Itemquantity = str_replace( ',', '', $item['quantity']);
				$ItemPrice = str_replace( ',', '', $item['price']);
				$Total_price = $Itemquantity*$ItemPrice;
				$check_price_array[] = $Total_price;
			}
			$Total_items_amaount = array_sum($check_price_array);
			
			if($Total_items_amaount != $Pos_bill_amount)
			{
				$response["errorcode"] = 1002;
				$response["message"] = "Order amount doesn't match with items price";
				echoRespnse($response); 
				exit;
			}
		}
	/*******************************check bill amount***************************/
		
		// $payment_amount = $bill_amount - $Points_amount;
		
		$Outlet_details = $orderObj->get_outlet_details($outlet_no,$Company_id);
		if($Outlet_details !=Null)
		{
			$seller_id = $Outlet_details['Enrollement_id'];
			$seller_fname = $Outlet_details['First_name'];
			$seller_lname = $Outlet_details['Last_name'];
			$seller_email_id = $Outlet_details['User_email_id'];
			$Pos_outlet_name = $Outlet_name=$Outlet_details['First_name'].' '.$Outlet_details['Last_name'];
			$Seller_Redemptionratio = $Outlet_details['Seller_Redemptionratio'];
			$Purchase_Bill_no = $Outlet_details['Purchase_Bill_no'];
			$Sub_seller_admin = $Outlet_details['Sub_seller_admin'];
			$Sub_seller_Enrollement_id = $Outlet_details['Sub_seller_Enrollement_id'];
			
			$Pos_outlet_id1 = $seller_id;
			$delivery_outlet =  $seller_id;
			
			if($Sub_seller_admin == 1) 
			{
				$Pos_outlet_id = $seller_id;
			}
			else 
			{
				$Pos_outlet_id = $Sub_seller_Enrollement_id;
			}
			
			if($Seller_Redemptionratio !=Null)
			{
				$Company_Redemptionratio = $Seller_Redemptionratio;
			}
			else
			{
				$Company_Redemptionratio = $Redemption_ratio;
			}
			
			$tp_db = $Purchase_Bill_no;
			$len = strlen($tp_db);
			$str = substr($tp_db,0,5);
			$bill = substr($tp_db,5,$len);

			$date = new DateTime();
			$lv_date_time=$date->format('Y-m-d H:i:s'); 
  
			$lv_date_time2 = $date->format('Y-m-d'); 

			// $Trans_type = 12;
			$Trans_type = 2;
			$Trans_Channel_id = 2;
			// $Trans_Channel_id = 12;
			$Payment_type_id = $Pos_payment_type;
			
			if($Payment_type_id == Null)
			{
				$Payment_type_id = 1;
			}
			// $Remarks = "Saas Api Online Order";
			$Remarks = "Loyalty Api Transaction";
			
			if($Sub_seller_admin == 1) 
			{
			  $seller_id = $seller_id;
			}
			else 
			{
			  $seller_id = $Sub_seller_Enrollement_id;
			}
		
			$order_total_loyalty_points = 0;
		/*********************16-9-2021***************************/	
			if($Pos_bill_items != Null)
			{
				foreach($Pos_bill_items as $item)
				{ 
					$ItemCode = $item['code']; 
					
					$ItemDetails = $orderObj->Get_item_details($ItemCode,$Company_id);
					
					if($ItemDetails !=NULL)
					{
						$Merchandize_item_code = $ItemDetails['Company_merchandize_item_code'];
						$Item_name = $ItemDetails['Merchandize_item_name'];
						
						
						$CheckItemTempCart = $orderObj->GetItemsDetails($Company_id,$Cust_enrollement_id,$ItemCode,$delivery_outlet,$ChannelCompanyId);
						
						if($CheckItemTempCart != Null)
						{
							$TempQty = $CheckItemTempCart['Item_qty'];
							
							$TempCartData["Item_qty"] = $TempQty+$item['quantity'];
							
							$orderObj->update_pos_temp_cart($TempCartData,$Company_id,$Cust_enrollement_id,$ItemCode,$delivery_outlet,$ChannelCompanyId);
						}
						else
						{
							$data78['Company_id'] = $Company_id;
							$data78['Enrollment_id'] = $Cust_enrollement_id;
							$data78['Seller_id'] = $delivery_outlet;
							$data78['Channel_id'] = $ChannelCompanyId;
							$data78['Item_code'] = $ItemCode;
							$data78['Item_qty'] = $item['quantity'];
							$data78['Item_price'] = str_replace( ',', '', $item['price']);
							
							// $this->Online_api_model->insert_item($data78);
							
							$orderObj->insert_item($data78);
						}
					}
					else
					{
						$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
						
						$response["errorcode"] = 3103;
						$response["message"] = "Invalid Item code Or Item not exist.";
						echoRespnse($response); 
						exit;
					}
				}
			}
			else
			{
				$response["errorcode"] = 3103;
				$response["message"] = "Invalid Item code Or Item not exist.";
				echoRespnse($response); 
				exit;
			}
			
			$Pos_bill_items = $orderObj->Get_temp_cart_items($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
		
		/***************************Validate voucher*********************************/
			if($Pos_voucher_no)
			{
				$Voucher_result = $discountObj->Validate_discount_voucher($CardId,$Company_id,$Pos_voucher_no,$Pos_voucher_amount);
				if($Voucher_result == Null)
				{
					$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
					
					$response["errorcode"] = 2069;
					$response["message"] = "Invalid Discount Voucher";
					echoRespnse($response); 
					exit;
				}
				else
				{
					$Pos_voucher_no = $Pos_voucher_no;
					$Pos_voucher_amount = $Voucher_result['Card_value'];
					$Card_Payment_Type_id = $Voucher_result['Payment_Type_id'];
					$Discount_percentage = $Voucher_result['Discount_percentage'];
					$Card_balance = $Voucher_result['Card_balance'];
					
					$Pos_voucher_amount = str_replace( ',', '', $Pos_voucher_amount);
					if($Card_Payment_Type_id == 997) //product voucher
					{	
						$Product_Voucher_Details = $discountObj->Get_Product_Voucher_Details($Pos_voucher_no,$Customer_enroll_id,$Company_id);
						
						$Product_Voucher_item_code = $Product_Voucher_Details['Company_merchandize_item_code'];
						$Product_Voucher_id = $Product_Voucher_Details['Voucher_id'];
						$Product_Voucher_Offer_code = $Product_Voucher_Details['Offer_code'];
						
						if($Product_Voucher_item_code !=Null) // product voucher in percentage
						{
							$Cust_Item_Num = array();
							foreach($Pos_bill_items as $item)
							{ 
								$ItemCode = $item['Item_Num']; 
								
								$ItemDetails = $orderObj->Get_item_details($ItemCode,$Company_id);
								
								if($ItemDetails !=NULL)
								{
									$Merchandize_item_code = $ItemDetails['Company_merchandize_item_code'];
									$Item_name = $ItemDetails['Merchandize_item_name'];
									
									$ItemCodeArr[$ItemCode]=$item['Item_Qty'];
									
									$Cust_Item_Num[] = $ItemCode;
									
									$Pos_Item_details = array("Item_Num" => $ItemCode, "Item_Name" =>$Item_name,"Quantity" => $item['Item_Qty'], "Item_Rate" => $item['Item_Rate']);
							
									$Pos_Item_details_array[] =$Pos_Item_details;
								}
								else
								{
									$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
									
									$response["errorcode"] = 3103;
									$response["message"] = "Invalid Item code Or Item not exist.";
									echoRespnse($response); 
									exit;
								}
							}
						/**********************stamp new logic**************************/
							$Get_lowest_sent_vouchers= $voucherObj->Get_lowest_sent_vouchers($Cust_enrollement_id,$Company_id,$Pos_voucher_no);
							
							if($Get_lowest_sent_vouchers != NULL)
							{
								$RemQTY=0;
								$lv_Voucher_code=0;
								$lowest_flag=1;
								$newpricearr = array();
								foreach($Get_lowest_sent_vouchers as $rec1)
								{
									if(array_key_exists($rec1['Company_merchandize_item_code'],$ItemCodeArr))
									{
										if(($lowest_flag == 0) && ($lv_Voucher_code == $rec1['Voucher_code']))
										{
											$RemQTY=0;
											$lowest_flag=1;
											$newpricearr = array();
											break;
										}
										
										$Cart_item_QTY=$ItemCodeArr[$rec1['Company_merchandize_item_code']];
										
										if($RemQTY!=0)
										{
											if($Cart_item_QTY >= $RemQTY )
											{
												$newpricearr[]=($RemQTY * $rec1['Voucher_Cost_price']);//1*260=260
												$Reduce_product_amt=array_sum($newpricearr);//220+260=480
												$ApllicableVoucher_code[]=$rec1['Voucher_code'];
												
												$data['Vouchers_price'][$rec1['Voucher_code']] = $Reduce_product_amt;
												if($lowest_flag!=0)
												{
													$data['Free_item_arr'][$rec1['Company_merchandize_item_code']] = $RemQTY;
												}
												// $data['Discount_percentage'][$rec1['Voucher_code']] = $rec1['Discount_percentage'];
												// $data['Offer_name'][$rec1['Voucher_code']] = $rec1['Offer_name'];
												$data['Voucher_Qty'][$rec1['Voucher_code']] = $rec1['Voucher_Qty'];
												
												$lowest_flag=0;
												$lv_Voucher_code=$rec1['Voucher_code'];
											}
										}
										if($Cart_item_QTY < $rec1['Voucher_Qty'] && $RemQTY==0)//
										{
											$newpricearr[]=($Cart_item_QTY*$rec1['Voucher_Cost_price']);//220
											
											$RemQTY= ($rec1['Voucher_Qty']-$Cart_item_QTY);//1
											if($lowest_flag!=0){$data['Free_item_arr'][$rec1['Company_merchandize_item_code']] = $Cart_item_QTY;}
											$lv_Voucher_code=$rec1['Voucher_code'];
										}
										if($Cart_item_QTY >= $rec1['Voucher_Qty'] && $lowest_flag==1)
										{
											$Reduce_product_amt=($rec1['Voucher_Qty']*$rec1['Voucher_Cost_price']);//660
											$lowest_flag=0;
											$lv_Voucher_code=$rec1['Voucher_code'];
											$ApllicableVoucher_code[]=$rec1['Voucher_code'];
							
											$data['Free_item_arr'][$rec1['Company_merchandize_item_code']] = $rec1['Voucher_Qty'];
											$data['Vouchers_price'][$rec1['Voucher_code']] = $Reduce_product_amt;
											// $data['Discount_percentage'][$rec1['Voucher_code']] = $rec1['Discount_percentage'];
											// $data['Offer_name'][$rec1['Voucher_code']] = $rec1['Offer_name'];
											$data['Voucher_Qty'][$rec1['Voucher_code']] = $rec1['Voucher_Qty'];
										}
										$Vouchers_min_price[$rec1['Voucher_code']] = $Reduce_product_amt;
									}
								}
							}
							$data['Unique_Vouchers_list'] = array_unique($ApllicableVoucher_code);
							
							$ReduceDiscountAmt = $data['Vouchers_price']["$Pos_voucher_no"];
							
						/**********************stamp new logic 02-05-2021**************************/
							if($ReduceDiscountAmt > 0)
							{
								$Reduce_product_amt = $ReduceDiscountAmt;
								$Pos_voucher_amount = number_format($Reduce_product_amt,2);
							}
							else
							{
								$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
								
								$response["errorcode"] = 2069;
								$response["message"] = "Invalid Discount Voucher";
								echoRespnse($response); 
								exit;
							}
						}
					}
					else if($Card_Payment_Type_id == 99 || $Card_Payment_Type_id == 998)
					{
						if($Card_balance > 0)
						{
							/****************12-7-2020****************/
							if($Discount_percentage > 0)
							{
								$Card_balance = (($bill_amount * $Discount_percentage)/100);
								$Card_balance = floor($Card_balance);
							}
							$Card_balance = str_replace( ',', '', $Card_balance);
							/****************12-7-2020****************/
							$Balance_due = $bill_amount - $Card_balance;
							if($Balance_due < 0)
							{
								$Balance_due = 0.00;
							}
							
							
							$Pos_voucher_amount = number_format($Card_balance,2);
						}
						else
						{
							$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
							
							$response["errorcode"] = 2069;
							$response["message"] = "Invalid Discount or Product Voucher";
							echoRespnse($response); 
							exit;
						}
					}
					$Pos_voucher_amount = str_replace( ',', '', $Pos_voucher_amount);
					
					$Pos_voucher_amount = floor($Pos_voucher_amount);  //discount / product voucher amount
				}
			}
			else
			{
				$Pos_voucher_no = Null;
				$Pos_voucher_amount = 0.00;
			}
		/********************validate gift card**********************/
			if($Pos_giftcard_no)
			{
				$Giftcard_result = $orderObj->Validate_gift_card($Company_id,$Pos_giftcard_no,$CardId);
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
						$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
						
						$response["errorcode"] = 3112;
						$response["message"] = "Invalid Gift Card Or No Balance In Gift Card.";
						echoRespnse($response); 
						exit;
					}
				}
				else
				{
					$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
					$response["errorcode"] = 3112;
					$response["message"] = "Invalid Gift Card Or No Balance In Gift Card.";
					echoRespnse($response); 
					exit;
				}
			}
			else
			{
				$Pos_giftcard_no = Null;
				$Pos_giftcard_amount = 0.00;
			}
		/*******************check discount, voucher, gift card, points amount****************************/
			$Pos_discount_amount = $Pos_discount+$Pos_loyalty_discount+$Pos_voucher_amount+$Pos_giftcard_amount; //09-04-2021
									
			$grand_total = ($Pos_bill_amount-$Pos_points_amount)-$Pos_discount_amount;
	
			if($grand_total < 0 )
			{
				$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
				
				$response["errorcode"] = 2066;
				$response["message"] = "Total discount amount and points amount is more than order amount";
				echoRespnse($response); 
				exit;
			}
			
		/*******************check discount, voucher, gift card, points amount****************************/
			$get_country = $orderObj->Fetch_country($Country_id);
			$get_state = $orderObj->Fetch_state($State_id);
			$get_city = $orderObj->Fetch_city($City_id);
			
			$Country_name = $get_country['Country_name'];
			$State_name = $get_state['State_name'];
			$City_name = $get_city['City_name'];
		/**************new logic with pos items********************/
			if($Pos_bill_items != Null)
			{
				$order_sub_total = 0;	
				$shipping_cost = 0;
				$DiscountAmt = 0;
				$TotalvoucherAmt = 0;
				$TotalDiscountAmt = 0;
				$ItemDiscounts = [];
				$tax = 0;	
				$i = 0;
		
				foreach($Pos_bill_items as $item)
				{ 
					$ItemCode = $item['Item_Num']; 
					$ItemQty = $item['Item_Qty']; 
					$Item_price = $item['Item_Rate'];
					
					$Item_price = str_replace( ',', '', $Item_price);
					
						$Item_price = $Item_price * $ItemQty;
						$order_sub_total = $order_sub_total + $Item_price;
						$i++;
						
					
						$ItemDetails = $orderObj->Get_item_details($ItemCode,$Company_id);
						if($ItemDetails !=NULL)
						{
							$Itemcategory_id = $ItemDetails['Merchandize_category_id'];
							
							$Itemcategory_ids[] = $ItemDetails['Merchandize_category_id'];
							// $Itemcategory_price[$Itemcategory_id] = $item['Item_Rate'] * $item['Item_Qty'];	
							$Itemcategory_price[$Itemcategory_id] = $Item_price;	
						}
						else
						{
							$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
						
							$response["errorcode"] = 3103;
							$response["message"] = "Invalid code Or Item not exist.";
							$response["code"] = $ItemCode;
							echoRespnse($response); 
							exit;
						}
						
						$DiscountResult = $discountObj->get_discount_value("",$ItemCode,$Item_price,$Company_id,$Pos_outlet_id,$Customer_enroll_id,$lv_member_Tier_id,0,$API_flag_call);
					
						$DisOpt = json_decode($DiscountResult,true);

						if($DisOpt["DiscountAmt"] > 0)
						{
							// $TotalDiscountAmt = floor($TotalDiscountAmt + $DisOpt["DiscountAmt"]);
							
							$TotalDiscountAmt = floor($TotalDiscountAmt + str_replace( ',', '', $DisOpt["DiscountAmt"]));
							
							$ItemDiscounts[$ItemCode] = $DisOpt["DiscountAmt"];
						}
						
						if(!empty($DisOpt["discountsArray"]) && is_array($DisOpt["discountsArray"]))
						{
							foreach($DisOpt["discountsArray"] as $k1)
							{
								$Discount_codes[] = $k1;
							}
						}
						if(!empty($DisOpt["discountsArray2"]) && is_array($DisOpt["discountsArray2"]))
						{
							foreach($DisOpt["discountsArray2"] as $k2)
							{
								$Discount_codes_2[] = $k2;
							}
						}
					/****************discount logic*********************/
				}
			/**************************************/
				$Itemcategory_ids = array_unique($Itemcategory_ids);
				foreach($Itemcategory_ids as $Itemcategory_id)
				{
					$Item_price = $Itemcategory_price[$Itemcategory_id];
					
					$CatDiscountResult = $discountObj->get_category_discount_value($Itemcategory_id,"",$Item_price,$Company_id,$Pos_outlet_id,$Customer_enroll_id,$lv_member_Tier_id,0,0,$API_flag_call);
						
						$DisOpt22 = json_decode($CatDiscountResult,true);
						
						if($DisOpt22["DiscountAmt"] > 0)
						{
							$TotalDiscountAmt = floor($TotalDiscountAmt + $DisOpt22["DiscountAmt"]);
						}
						
						if(!empty($DisOpt22["discountsArray"]) && is_array($DisOpt22["discountsArray"]))
						{
							//$Discount_codes[] = $DisOpt["discountsArray"];
								foreach($DisOpt22["discountsArray"] as $k1)
								{
									$Discount_codes[] = $k1;
								}
						}
						
						if(!empty($DisOpt22["discountsArray2"]) && is_array($DisOpt22["discountsArray2"]))
						{
							foreach($DisOpt22["discountsArray2"] as $k2)
							{
								$Discount_codes_2[] = $k2;
							}
						}
				}
			/****************category discount logic***************************/

			$DiscountResult12 = $discountObj->get_discount_value($Itemcategory_id,$ItemCode,$Item_price,$Company_id,$Pos_outlet_id,$Customer_enroll_id,$lv_member_Tier_id,$order_sub_total,$API_flag_call);
			
			$DisOpt12 = json_decode($DiscountResult12,true);
			
			if($DisOpt12["DiscountAmt"] > 0)
			{
				// $TotalDiscountAmt = floor($TotalDiscountAmt + $DisOpt12["DiscountAmt"]);
				
				$number2 = filter_var($DisOpt12["DiscountAmt"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
				$TotalDiscountAmt = ($TotalDiscountAmt + str_replace( ',', '', $number2));
				
				$_SESSION['BillDiscount'] = $DisOpt12["DiscountAmt"];
			}
			
			if(!empty($DisOpt12["discountsArray"]) && is_array($DisOpt12["discountsArray"]))
			{
				foreach($DisOpt12["discountsArray"] as $k)
				{
					$Discount_codes[] = $k;
				}
			}
		/**********************************/
			if(!empty($DisOpt12["discountsArray2"]) && is_array($DisOpt12["discountsArray2"]))
			{
				foreach($DisOpt12["discountsArray2"] as $k2)
				{
					$Discount_codes_2[] = $k2;
				}
			}
		/**********************************/
			$TotalDiscountAmt = str_replace( ',', '', $TotalDiscountAmt);
			$DiscountAmt = $TotalDiscountAmt;
			
			$_SESSION['DiscountAmt']= $TotalDiscountAmt;
			
			if(count($Discount_codes) > 0)
			{}
				
			if(count($ItemDiscounts) > 0)
			{
				// $this->session->set_userdata('ItemDiscounts',$ItemDiscounts);
			}

			if($order_sub_total < $DiscountAmt)
			{
				$DiscountAmt = $order_sub_total;
			}
			
			$order_total = ($order_sub_total + $shipping_cost + $tax) - $DiscountAmt;

		 $Pos_discount_amount = $Pos_discount+$Pos_loyalty_discount+$Pos_voucher_amount+$Pos_giftcard_amount; 
		
		$grand_total = ($Pos_bill_amount-$Pos_points_amount)-$Pos_discount_amount;
		
		if($grand_total < 0 )
		{
			$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
			
			$response["errorcode"] = 2066;
			$response["message"] = "Discount amount and points amount is more than order amount.";
			echoRespnse($response); 
			exit;
		}		
			
			$Extra_earn_points_Loyalty_pts = array();
				foreach ($Pos_bill_items as $item)
				{
					/********************************/
						$characters = 'A123B56C89';
						$string = '';
						$Voucher_no="";
						for ($i = 0; $i < 10; $i++) 
						{
							$Voucher_no .= $characters[mt_rand(0, strlen($characters) - 1)];
						}
						$Voucher_array1[]=$Voucher_no;
					/*************************************/
						$Item_code = $item['Item_Num'];
						$Pos_item_rate = $item['Item_Rate'];
						$Pos_item_rate = str_replace( ',', '', $Pos_item_rate);
						$Pos_item_qty = $item['Item_Qty'];
					/********Get Merchandize item name********/
					
						$result = $orderObj->Get_item_details($Item_code,$Company_id);
						
						$sellerID = $result->Seller_id;
						if($sellerID !=NULL || $sellerID !='0')
						{
							$sellerID = $sellerID; // apply item outlet rule
						}
						else
						{
							$sellerID = $seller_id; // apply POS outlet rule
						}
						
						$sellerID = $seller_id; // apply POS outlet rule
						
						$Merchandise_item_id = $result['Company_merchandise_item_id'];
						$Company_merchandize_item_code = $result['Company_merchandize_item_code'];
						$Merchandize_item_name = $result['Merchandize_item_name'];
						$Merchandize_category_id = $result['Merchandize_category_id'];
						$Stamp_item_flag = $result['Stamp_item_flag'];
						$Merchandize_partner_id = $result['Partner_id'];
						// $Item_cost_price = $result->Cost_price*$Pos_item_qty;
						
						$Item_cost_price = $Pos_item_rate*$Pos_item_qty;
						
						$Item_branch = $orderObj->get_items_branches($Company_merchandize_item_code,$Merchandize_partner_id,$Company_id);
						$Item_branch_code = $Item_branch['Branch_code'];
						
						if(count($ItemDiscounts) > 0)
						{
							$thisItemDiscount = $ItemDiscounts[$Company_merchandize_item_code];
							
						}
					/******************New Loyalty Rule Logic********************/ 
						$Extra_earn_points = 0;
						
						if($Stamp_item_flag == 1)
						{
							$Extra_earn_points = $result['Extra_earn_points']*$Pos_item_qty;
							$Extra_earn_points_Loyalty_pts[]=$Extra_earn_points;
						}
						if($sellerID!=0)
						{
						/**********Get Seller Details**********/
							$Seller_result = $orderObj->get_brand_details($sellerID,$Company_id);	
							$Seller_First_name = $Seller_result['First_name'];
							$Seller_Last_name = $Seller_result['Last_name'];
							$seller_name = $Seller_First_name.' '.$Seller_Last_name;
							$Purchase_Bill_no = $Seller_result['Purchase_Bill_no'];

							$tp_db = $Purchase_Bill_no;
							$len = strlen($tp_db);
							$str = substr($tp_db,0,5);
							$bill = substr($tp_db,5,$len);
						/**********Get Seller Details**********/
						
							$seller_id=$sellerID;
							
							$loyalty_prog = $orderObj->get_tierbased_loyalty($Company_id,$seller_id,$lv_member_Tier_id,$lv_date_time2);
							
							$points_array = array();

							$Applied_loyalty_id = array();
							if($loyalty_prog != NULL )
							{
								foreach($loyalty_prog as $prog)
								{
									$member_Tier_id = $lv_member_Tier_id;
									$value = array();
									$dis = array();
									$LoyaltyID_array = array();
									$Loyalty_at_flag = 0;	
									$lp_type=substr($prog['Loyalty_name'],0,2);
									$Todays_date = $lv_date_time;
									
									$prog = $prog['Loyalty_name'];
							
									$lp_details = $orderObj->get_loyalty_program_details($Company_id,$seller_id,$prog,$lv_date_time2);
								
									$lp_count = count($lp_details);

									foreach($lp_details as $lp_data)
									{
										$LoyaltyID = $lp_data['Loyalty_id'];
										$lp_name = $lp_data['Loyalty_name'];
										$lp_From_date = $lp_data['From_date'];
										$lp_Till_date = $lp_data['Till_date'];
										$Loyalty_at_value = $lp_data['Loyalty_at_value'];
										$Loyalty_at_transaction = $lp_data['Loyalty_at_transaction'];
										$discount = $lp_data['discount'];
										$lp_Tier_id = $lp_data['Tier_id'];
										$Category_flag = $lp_data['Category_flag'];
										$Category_id = $lp_data['Category_id'];
										$Segment_flag = $lp_data['Segment_flag'];
										$Segment_id	= $lp_data['Segment_id'];
									
								//*************channel and payment ***************
									$Trans_Payment_flag	= $lp_data['Payment_flag'];
									$Trans_Channel_flag	= $lp_data['Channel_flag'];
									$Trans_Channel	= $lp_data['Trans_Channel'];
									$Lp_Payment_Type_id	= $lp_data['Payment_Type_id'];
									
								//*************channel and payment ***************
								
										if($lp_Tier_id == 0)
										{
											$member_Tier_id = $lp_Tier_id;
										}
										if($Loyalty_at_value > 0)
										{
											$value[] = $Loyalty_at_value;	
											$dis[] = $discount;
											$LoyaltyID_array[] = $LoyaltyID;
											$Loyalty_at_flag = 1;
										}
										if($Loyalty_at_transaction > 0)
										{
											$value[] = $Loyalty_at_transaction;	
											$dis[] = $Loyalty_at_transaction;
											$LoyaltyID_array[] = $LoyaltyID;
											$Loyalty_at_flag = 2;
										}
									}
								
									if($lp_type == 'PA')
									{	
										$transaction_amt1=$Pos_item_qty * $Pos_item_rate;
										
										// $transaction_amtNew = cheque_format($transaction_amt1);
										$transaction_amtNew = $transaction_amt1;
										$transaction_amt = str_replace( ',', '', $transaction_amtNew);
									}
									if($lp_type == 'BA')
									{	
										// $grand_totalNew = cheque_format($grand_total);
										$grand_totalNew = $grand_total;
										$grand_totalNew = str_replace( ',', '', $grand_totalNew);
										$Purchase_amount=$Pos_item_qty * $Pos_item_rate;
										 $transaction_amt = (($grand_totalNew * $Purchase_amount ) / $subtotal);
									}
									
									
								//*************channel and payment***************
									if($Trans_Channel_flag==1)
									{
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1 && $Trans_Channel_id == $Trans_Channel )
										{
											for($i=0;$i<=count($value)-1;$i++)
											{
												if($i<count($value)-1 && $value[$i+1] != "")
												{
													if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;
														$gained_points_fag = 1;
														$points_array[] = $loyalty_points;
													}
												}
												else if($transaction_amt > $value[$i])
												{
													$loyalty_points = get_discount($transaction_amt,$dis[$i]);
													$gained_points_fag = 1;
													$trans_lp_id = $LoyaltyID_array[$i];
													$Applied_loyalty_id[]=$trans_lp_id;					
													$points_array[] = $loyalty_points;
												}
											}
										}
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2 && $Trans_Channel_id == $Trans_Channel )
										{
											$loyalty_points = get_discount($transaction_amt,$dis[0]);
											$points_array[] = $loyalty_points;
											$gained_points_fag = 1;
											$trans_lp_id = $LoyaltyID_array[0];
											$Applied_loyalty_id[]=$trans_lp_id;
										}						
									// unset($dis);
									}	
									if($Trans_Payment_flag == 1)
									{
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1 && $Lp_Payment_Type_id == $Payment_type_id )
										{
											for($i=0;$i<=count($value)-1;$i++)
											{
												if($i<count($value)-1 && $value[$i+1] != "")
												{
													if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;
														$gained_points_fag = 1;
														$points_array[] = $loyalty_points;
													}
												}
												else if($transaction_amt > $value[$i])
												{
													$loyalty_points = get_discount($transaction_amt,$dis[$i]);
													$gained_points_fag = 1;
													$trans_lp_id = $LoyaltyID_array[$i];
													$Applied_loyalty_id[]=$trans_lp_id;					
													$points_array[] = $loyalty_points;
												}
											}
										}
										
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2 && $Lp_Payment_Type_id == $Payment_type_id)
										{
											$loyalty_points = get_discount($transaction_amt,$dis[0]);
											$points_array[] = $loyalty_points;
											$gained_points_fag = 1;
											$trans_lp_id = $LoyaltyID_array[0];
											$Applied_loyalty_id[]=$trans_lp_id;
										}	
									}
								//************channel and payment ***************
									if($Category_flag==1)
									{
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1 && $Merchandize_category_id == $Category_id )
										{
											for($i=0;$i<=count($value)-1;$i++)
											{
												if($i<count($value)-1 && $value[$i+1] != "")
												{
													if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;
														$gained_points_fag = 1;
														$points_array[] = $loyalty_points;
													}
												}
												else if($transaction_amt > $value[$i])
												{
													$loyalty_points = get_discount($transaction_amt,$dis[$i]);
													$gained_points_fag = 1;
													$trans_lp_id = $LoyaltyID_array[$i];
													$Applied_loyalty_id[]=$trans_lp_id;					
													$points_array[] = $loyalty_points;
												}
											}
										}
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2 && $Merchandize_category_id == $Category_id )
										{
											$loyalty_points = get_discount($transaction_amt,$dis[0]);
											$points_array[] = $loyalty_points;
											$gained_points_fag = 1;
											$trans_lp_id = $LoyaltyID_array[0];
											$Applied_loyalty_id[]=$trans_lp_id;
										}						
									// unset($dis);
									}
									else if($Segment_flag==1)
									{											
										$Get_segments2 = $orderObj->edit_segment_id($Company_id,$Segment_id);
										
										$Customer_array=array();
										$Applicable_array[]=0;
										unset($Applicable_array);
										
										foreach($Get_segments2 as $Get_segments)
										{
											if($Get_segments['Segment_type_id']==1)  // 	Age 
											{
												$lv_Cust_value=date_diff(date_create($Date_of_birth), date_create('today'))->y;
											}												
											if($Get_segments['Segment_type_id']==2)//Sex
											{
												$lv_Cust_value=$Sex;
											}
											if($Get_segments['Segment_type_id']==3)//Country
											{
												$lv_Cust_value = $Country_name;
												if(strcasecmp($lv_Cust_value,$Get_segments['Value'])==0)
												{
													$Get_segments['Value']=$lv_Cust_value;
												}
											}
											if($Get_segments['Segment_type_id']==4)//District
											{
												$lv_Cust_value=$District;
												
												if(strcasecmp($lv_Cust_value,$Get_segments['Value'])==0)
												{
													$Get_segments['Value']=$lv_Cust_value;
												}
											}
											if($Get_segments['Segment_type_id']==5)//State
											{
												$lv_Cust_value=$State_name;	
												if(strcasecmp($lv_Cust_value,$Get_segments['Value'])==0)
												{
													$Get_segments['Value']=$lv_Cust_value;
												}
											}
											if($Get_segments['Segment_type_id']==6)//city
											{
												$lv_Cust_value=$City_name;
												
												if(strcasecmp($lv_Cust_value,$Get_segments['Value'])==0)
												{
													$Get_segments['Value']=$lv_Cust_value;
												}
											}
											if($Get_segments['Segment_type_id']==7)//Zipcode
											{
												$lv_Cust_value=$Zipcode;
												
											}
											if($Get_segments['Segment_type_id']==8)//Cumulative Purchase Amount
											{
												$lv_Cust_value=$total_purchase;	
											}
											if($Get_segments['Segment_type_id']==9)//Cumulative Points Redeem 
											{
												$lv_Cust_value=$Total_reddems;
											}
											if($Get_segments['Segment_type_id']==10)//Cumulative Points Accumulated
											{
												$start_date=$joined_date;
												$end_date=date("Y-m-d");
												$transaction_type_id = 12;
												$Tier_id=$lp_Tier_id;
												
												$Trans_Records = $orderObj->get_cust_trans_summary_all($Company_id,$Customer_enroll_id,$start_date,$end_date,$transaction_type_id,$Tier_id,'','');
												
												// foreach($Trans_Records as $Trans_Records)
												// {
													$lv_Cust_value=$Trans_Records['Total_Gained_Points'];
												// }											
											}
											if($Get_segments['Segment_type_id']==11)//Single Transaction  Amount
											{
												$start_date=$joined_date;
												$end_date=date("Y-m-d");
												$transaction_type_id = 12;
												$Tier_id=$lp_Tier_id;
												
												$Trans_Records1 = $orderObj->get_cust_trans_details($Company_id,$start_date,$end_date,$Customer_enroll_id,$transaction_type_id,$Tier_id,'','');
												
												/* foreach($Trans_Records as $Trans_Records)
												{
													$lv_Max_amt[]=$Trans_Records->Purchase_amount;
												}
												$lv_Cust_value=max($lv_Max_amt); */	
												
												$lv_Cust_value=$Trans_Records1['Purchase_amount'];				
											}
											if($Get_segments['Segment_type_id']==12)//Membership Tenor
											{
												$tUnixTime = time();
												list($year,$month, $day) = EXPLODE('-', $joined_date);
												$timeStamp = mktime(0, 0, 0, $month, $day, $year);
												$lv_Cust_value= ceil(abs($timeStamp - $tUnixTime) / 86400);
											}
											
											$Get_segments = Get_segment_based_customers($lv_Cust_value,$Get_segments['Operator'],$Get_segments['Value'],$Get_segments['Value1'],$Get_segments['Value2']);
											
											$Applicable_array[]=$Get_segments;
											
										}
										if(!in_array(0, $Applicable_array, true))
										{
											$Customer_array[]=$Customer_enroll_id;
											
											if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1)
											{
												for($i=0;$i<=count($value)-1;$i++)
												{
													if($i<count($value)-1 && $value[$i+1] != "")
													{
														if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
														{
															$loyalty_points = get_discount($transaction_amt,$dis[$i]);
															$trans_lp_id = $LoyaltyID_array[$i];
															$Applied_loyalty_id[]=$trans_lp_id;
															$gained_points_fag = 1;
															$points_array[] = $loyalty_points;
														}
													}
													else if($transaction_amt > $value[$i])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$gained_points_fag = 1;
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;					
														$points_array[] = $loyalty_points;
													}
												}
											}									
											if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2 )
											{	
												$loyalty_points = get_discount($transaction_amt,$dis[0]);
												$points_array[] = $loyalty_points;
												$gained_points_fag = 1;
												$trans_lp_id = $LoyaltyID_array[0];
												$Applied_loyalty_id[]=$trans_lp_id;	
											}
										} 
									}
									else
									{
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1  && $Trans_Channel == 0 && $Lp_Payment_Type_id == 0)
										{
											for($i=0;$i<=count($value)-1;$i++)
											{
												if($i<count($value)-1 && $value[$i+1] != "")
												{
													if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;
														$gained_points_fag = 1;
														$points_array[] = $loyalty_points;
													}
												}
												else if($transaction_amt > $value[$i])
												{
													$loyalty_points = get_discount($transaction_amt,$dis[$i]);
													$gained_points_fag = 1;
													$trans_lp_id = $LoyaltyID_array[$i];
													$Applied_loyalty_id[]=$trans_lp_id;					
													$points_array[] = $loyalty_points;
												}
											}
										}

										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2  && $Trans_Channel == 0 && $Lp_Payment_Type_id == 0)
										{
											$loyalty_points = get_discount($transaction_amt,$dis[0]);
											$points_array[] = $loyalty_points;
											$gained_points_fag = 1;
											$trans_lp_id = $LoyaltyID_array[0];
											$Applied_loyalty_id[]=$trans_lp_id;
										}
									}
								}
								if(count($Applied_loyalty_id) == 0)
								{
									$trans_lp_id=0;
								}											
							}
							if($gained_points_fag == 1)
							{
								$total_loyalty_points = array_sum($points_array);	
							
								$Email_points[]=$total_loyalty_points;
							}
							else
							{
								$total_loyalty_points = 0;
							}
						}
						else
						{
						/******************Get Supper Seller Details*********************/
							$result = $orderObj->superSellerDetails();				   
							$seller_id = $result['Enrollement_id'];
							$seller_fname = $result['First_name'];
							$seller_lname = $result['Last_name'];
							$seller_name = $seller_fname .' '. $seller_lname;
							$Seller_Redemptionratio = $result['Seller_Redemptionratio'];
							$Purchase_Bill_no = $result['Purchase_Bill_no'];

							$tp_db = $Purchase_Bill_no;
							$len = strlen($tp_db);
							$str = substr($tp_db,0,5);
							$bill = substr($tp_db,5,$len);
						/******************Get Supper Seller Details*********************/
							$total_loyalty_points=0;
							$Email_points[]=$total_loyalty_points;
						}
						
						$total_loyalty_points=$total_loyalty_points + $Extra_earn_points;
						if($Ecommerce_flag == 1)
						{
							$Voucher_status = 18; //'Ordered'
						}
						else
						{
							$Voucher_status = 20; //'Close'
						}
						
						$item_total_amount = $Pos_item_qty * $Pos_item_rate;
						
						if($sellerID!=0)
						{
							$Weighted_loyalty_points = $total_loyalty_points;
						}
						else
						{
							$Weighted_loyalty_points = $Extra_earn_points;
						}
						$Weighted_redeem_points1 = ($Cust_redeem_point * $item_total_amount) / $subtotal;
						
						$Weighted_points_amount1 = ($Pos_points_amount * $item_total_amount) / $subtotal;
						
						$Weighted_redeem_points = round(($Cust_redeem_point * $item_total_amount) / $subtotal);
						
						$Weighted_points_amount = round(($Pos_points_amount * $item_total_amount) / $subtotal);
						
						$Weighted_discount_amount = round(($Pos_discount_amount * $item_total_amount) / $subtotal);
					//***********allow to redeem 1 point extra****************/
						$Weighted_discount_amount1 = ($Pos_discount_amount * $item_total_amount) / $subtotal;
					//***********allow to redeem 1 point extra****************/	
						$Purchase_amount=$Pos_item_qty * $Pos_item_rate;
						
						$Balance_to_pay = (($grand_total * $Purchase_amount ) / $subtotal);
							
						$Total_Weighted_avg_shipping_cost = 0;
							
						$Shipping_cost=0;
						$Weighted_avg_shipping_cost=0;
				
						$RedeemAmt=$Weighted_redeem_points/$Company_Redemptionratio;
						$RedeemAmt1=$Weighted_redeem_points1/$Company_Redemptionratio;
						
						$PaidAmount=$Purchase_amount+$Weighted_avg_shipping_cost-$Weighted_points_amount-$Weighted_discount_amount;
						
					//***********allow to redeem 1 point extra****************/
						
						$PaidAmount1=$Purchase_amount+$Weighted_avg_shipping_cost-$Weighted_points_amount1-$Weighted_discount_amount1;
					
						$Weighted_Redeem_amount=(($Purchase_amount/$Pos_bill_amount)*$EquiRedeem);
						if($PaidAmount1 <= 0)
						{
							$PaidAmount1 = 0;
						}
						
						$PaidAmount1 = number_format($PaidAmount1,2); 
						$Weighted_redeem_points1 = number_format($Weighted_redeem_points1,2); 
						$Weighted_loyalty_points = number_format($Weighted_loyalty_points,2);
						
					//***********allow to redeem 1 point extra****************/
						$Total_discount1 = $Pos_loyalty_discount + $Pos_discount + $Pos_voucher_amount;
						
						$data123['Company_id']=$Company_id;
						$data123['Trans_type']=$Trans_type;
						$data123['Purchase_amount']=$Purchase_amount;
						$data123['Paid_amount']=str_replace( ',', '', $PaidAmount1);
						$data123['COD_Amount']=str_replace( ',', '', $PaidAmount1);
						$data123['Redeem_points']=str_replace( ',', '', $Weighted_redeem_points1);
						$data123['Redeem_amount']=$Weighted_Redeem_amount;
						$data123['Payment_type_id']=$Payment_type_id;
						$data123['Remarks']=$Remarks;
						$data123['Trans_date']=$lv_date_time;
						$data123['balance_to_pay']=str_replace( ',', '', $PaidAmount1);
						$data123['Enrollement_id']=$cust_enrollment_id;
						$data123['Bill_no']=$bill;
						$data123['Manual_billno']=$Pos_bill_no;
						$data123['Order_no']=$Pos_order_no;
						$data123['Card_id']=$CardId;
						$data123['Seller']=$Pos_outlet_id1;
						$data123['Seller_name']=$Pos_outlet_name;
						$data123['Item_code']=$Company_merchandize_item_code;
						$data123['Voucher_status']=$Voucher_status;
						$data123['Delivery_method']=28; // Pick Up
						$data123['Merchandize_Partner_id']=$Merchandize_partner_id; 
						//$data123['Merchandize_Partner_branch']=$Item_branch_code;
						$data123['Quantity']=$Pos_item_qty;  
						$data123['Loyalty_pts']=$Weighted_loyalty_points;
						$data123['Online_payment_method']="COD";
						$data123['Bill_discount']=$Pos_loyalty_discount;
						$data123['Pos_discount']=$Pos_discount;
						$data123['Total_discount']=$Total_discount1;
						$data123['Voucher_discount']=$Pos_voucher_amount;
						$data123['GiftCardNo']=$Pos_giftcard_no;
						$data123['Channel_id']=$ChannelCompanyId;
						$data123['Create_user_id']=$Pos_outlet_id1;
						$data123['Voucher_no']=$Pos_voucher_no;
					
						$Transaction_detail = $orderObj->Insert_online_purchase_transaction($data123);
						
						$Items_details = array("code" => $Company_merchandize_item_code, "name" =>$Merchandize_item_name,"quantity" => $Pos_item_qty,"Price" => $Purchase_amount); 
						
						$Itemsdetails[] = $Items_details;
						
						if($Transaction_detail) //== SUCCESS
						{}
					
						if(count($Applied_loyalty_id) != 0)
						{		
							for($l=0;$l<count($Applied_loyalty_id);$l++)
							{
								$Get_loyalty = $orderObj->Get_loyalty_details_for_online_purchase($Applied_loyalty_id[$l]);

								foreach($Get_loyalty as $rec)
								{
									$Loyalty_at_transaction = $rec['Loyalty_at_transaction'];
									$lp_type=substr($rec['Loyalty_name'],0,2);	
									$discount = $rec['discount'];

									if($lp_type == 'PA')
									{		
										if($Loyalty_at_transaction != 0.00)
										{
											$Calc_rewards_points=(($Purchase_amount*$Loyalty_at_transaction)/100);
										}
										else
										{
											$Calc_rewards_points=(($Purchase_amount*$discount)/100);
										}
									}

									if($lp_type == 'BA')
									{	
										if($Loyalty_at_transaction != 0.00)
										{
											$Calc_rewards_points=(($Balance_to_pay*$Loyalty_at_transaction)/100);
										}
										else
										{
											$Calc_rewards_points=(($Purchase_amount*$discount)/100);
										}
									}
								}
								
								$child_data['Company_id']=$Company_id;
								$child_data['Transaction_date']=$lv_date_time;
								$child_data['Seller']=$Pos_outlet_id1;
								$child_data['Enrollement_id']=$cust_enrollment_id;
								$child_data['Transaction_id']=$Transaction_detail;
								$child_data['Loyalty_id']=$Applied_loyalty_id[$l];
								$child_data['Reward_points']=$Calc_rewards_points;
								
								$child_result = $orderObj->insert_loyalty_transaction_child($child_data);
							}
						}
					/***************Update gift card and vouchers********************/
						$redeemed_discount_voucher = $Pos_voucher_no; 
	
						if($redeemed_discount_voucher != Null)
						{
							$giftData1['Card_balance'] = 0;								
							$giftData1['Update_user_id'] = $Pos_outlet_id1;	
							$giftData1['Update_date'] = date('Y-m-d H:i:s');	
							
							$update_voucher = $orderObj->update_voucher($giftData1,$redeemed_discount_voucher,$Company_id,$CardId);
							
							// $Voucher_array[] = $redeemed_discount_voucher;
							$Voucher_array = $redeemed_discount_voucher;
						
						//*******************Update Trans table Free_item_quantity*****************************/
							if($data['Free_item_arr'] != NULL)
							{
								foreach ($data['Free_item_arr'] as $key => $value) 
								{
									$FreeItemCode = $key;
									$Free_item_qty = $value;
									
									$updateData1['Free_item_quantity'] = $Free_item_qty;
									
									$update_quantity = $orderObj->update_Free_item_quantity($updateData1,$bill,$FreeItemCode);
								}
							}
						//*******************Update Trans table Free_item_quantity*****************************/
						}
					/***********update gift card******************/
						$redeemed_giftcard = $Pos_giftcard_no;
						
						if($redeemed_giftcard != Null)
						{
							$giftData2["Card_balance"] = 0;
							$giftData2["Update_user_id"] = $Pos_outlet_id1;
							$giftData2["Update_date"] = date('Y-m-d H:i:s');

							$update_giftcard = $orderObj->update_giftcard($giftData2,$redeemed_giftcard,$Company_id);
						}
					/***********update gift card******************/
					/***************Update gift card and vouchers********************/
						$Order_date = date('Y-m-d');	
						$lvp_date_time = date("Y-m-d H:i:s");
				/**********************Stamp offer logic**************************/
					if($Stamp_item_flag == 1)
					{ 
						$Product_offers = $orderObj->get_product_offers($Merchandise_item_id,$Merchandize_category_id,$Company_id,$seller_id);
						
						if($Product_offers != null)
						{		
							foreach($Product_offers as $offer)
							{	
								$Offers_items = $orderObj->get_offer_selected_items($offer['Offer_code'],$Company_id);
							
								$Total_item_purchase = array();
								if($Offers_items != NULL)
								{
									foreach($Offers_items as $rec)
									{
										$Total_item = $orderObj->get_item_purchase_count($rec['Company_merchandize_item_code'],$Company_id,$Customer_enroll_id,$offer['From_date'],$offer['Till_date']);
										
										$Total_item_purchase[] = $Total_item['product_qty'];
									}
								}
								
								$Total_count_item= array_sum($Total_item_purchase);	
								
								if($Total_count_item >= $offer['Buy_item'])
								{
							
									$Total_sent_voucher = $orderObj->member_sent_offers($Company_id,$Customer_enroll_id,$offer['Offer_code'],$offer['Free_item_id']);
									
									$Voucher_count = floor($Total_count_item/$offer['Buy_item']);
									
									$Need_to_Send_Voucher = ($Voucher_count-$Total_sent_voucher);
									
									for($A=1;$A<= $Need_to_Send_Voucher;$A++)
									{
										$characters = '0123456789';
										$string = '';
										$ProductVoucher_no="";
										for ($i = 0; $i < 10; $i++) 
										{
											$ProductVoucher_no .= $characters[mt_rand(0, strlen($characters) - 1)];
										}
										
										$FreeItem = $orderObj->get_offer_free_items($offer['Offer_code'],$Company_id);
										
										foreach($FreeItem as $FreeItem)
										{
											$data76['Company_id']=$Company_id;
											$data76['Enrollement_id']=$Customer_enroll_id;
											$data76['Offer_code']=$offer['Offer_code'];
											$data76['Voucher_type']=123;
											$data76['Voucher_code']=$ProductVoucher_no;
											$data76['Quantity']=$offer['Free_item'];
											$data76['Company_merchandise_item_id']=$FreeItem['Free_item_id'];
											$data76['Company_merchandize_item_code']=$FreeItem['Company_merchandize_item_code'];
											$data76['Item_name']=$FreeItem['Merchandize_item_name'];
											$data76['Cost_price']=$FreeItem['Billing_price'];
											$data76['Valid_from']=date("Y-m-d");
											$data76['Valid_till']=date("Y-m-d",strtotime("+$Stamp_voucher_validity days"));
											$data76['Active_flag']=1;
											$data76['Creation_date']=date("Y-m-d H:i:s");
								
											$orderObj->insert_product_voucher($data76);
										}
						
								//****insert in gift card tbl ***********
										$data77['Company_id'] = $Company_id;
										$data77['Gift_card_id'] = $ProductVoucher_no;
										
										$data77['Valid_till'] = date("Y-m-d",strtotime("+$Stamp_voucher_validity days"));
										$data77['Card_balance'] = 100;
										$data77['Discount_percentage'] = 100;
								
										$data77['Card_id'] = $CardId;
										$data77['Email'] = $User_email_id;
										$data77['Phone_no'] = $User_phone_no;
										$data77['Payment_Type_id'] = 997;
										$data77['Seller_id'] = $delivery_outlet;
										$data77['Create_date'] = date("Y-m-d");
										
										$orderObj->insert_voucher_in_gift_card($data77);
								//****insert in gift card tbl ***********
								//****now send product voucher notification *****************
								
										$ProductEmailParam["error"] = false;
										$ProductEmailParam['Brand_name'] = $seller_name;
										$ProductEmailParam['Order_no'] = $bill;	
										$ProductEmailParam['Product_voucher'] = $ProductVoucher_no;
										$ProductEmailParam['Voucher_validity'] = date("Y-m-d",strtotime("+$Stamp_voucher_validity days"));
										$ProductEmailParam['Description'] = "You have collected ".$offer['Buy_item']." Stamps from ".$seller_name." and recieved voucher for a ".$offer['Offer_name']." on us. <br><br> Present this voucher code to the cashier to redeem your ".$offer['Free_item']." ".$offer['Offer_name']."*";
										$ProductEmailParam['Email_template_id'] =25; 
										
										$email = $sendEObj->sendEmail($ProductEmailParam,$Customer_enroll_id);
									}
								}
							}
						}
					}
				/**********************Stamp offer logic**************************/
				}
				
				$Extra_earn_points = array_sum($Extra_earn_points_Loyalty_pts);
				$total_loyalty_email=(array_sum($Email_points)+ $Extra_earn_points);	
				
				// $total_loyalty_email = floor($total_loyalty_email); // 25/11/2020
				$total_loyalty_email = round($total_loyalty_email);
			}
		/**************new logic with pos items*****************/
				/************* Update Current Balance ******************/
				
					$cid = $CardId;							
					$redeem_point = $Cust_redeem_point;	
					if($Ecommerce_flag == 1)
					{
						$Update_Current_balance = ($bal - $redeem_point);
					}
					else
					{
						$Update_Current_balance = ($bal - $redeem_point + $total_loyalty_email);
					}
					$Update_total_purchase = $total_purchase + $subtotal;
					$Update_total_reddems = $Total_reddems + $Cust_redeem_point;
					
					/* $MemberPara['Total_reddems'] = $Update_total_reddems;								
					$MemberPara['total_purchase'] = $Update_total_purchase;								
					$MemberPara['Current_balance'] = $Update_Current_balance;	
				
					$update_balance=$orderObj->update_member_balance($MemberPara,$Enrollement_id); */
					
					$lvp_date_time = date("Y-m-d H:i:s"); 
				
				/**************************************************/
					if($Block_points_flag == 1)
					{
						$upblockData['Status']=1;
						$upblockData['Status_dec']="Used Points";
						$upblockData['Update_user_id']=$Pos_outlet_id1;
						$upblockData['Update_date']=$lvp_date_time;
						
						$valblockData['Company_id']=$Company_id;		
						$valblockData['Enrollment_id']=$Enrollement_id;
						$valblockData['Outlet_id']=$Pos_outlet_id1;
						$valblockData['Order_no']=$Pos_bill_no;						
						$valblockData['Points']=$redeem_point;								
						
						$updateblockData = $dbHandlerObj->update_block_points_status($upblockData,'igain_block_points',$valblockData);	
						
						$Total_blaock_Points = $Blocked_points - $redeem_point;
						$MemberPara['Blocked_points']=$Total_blaock_Points;
					}
					
					$MemberPara['Total_reddems'] = $Update_total_reddems;								
					$MemberPara['total_purchase'] = $Update_total_purchase;								
					$MemberPara['Current_balance'] = $Update_Current_balance;	
				
					$valData['Enrollement_id']=$Enrollement_id;
					$valData['Company_id']=$Company_id;								
					
					$updateData = $dbHandlerObj->updateData($MemberPara,'igain_enrollment_master',$valData);
				/**************************************************/	
					$bill_no = $bill + 1;
					$billno_withyear = $str.$bill_no;
					
					$result4 = $orderObj->updatePurchaseBillno($billno_withyear,$seller_id);
				/*********** Update Current Balance ***************/   
			 /******************sent nitification*****************/	
				$html.='<div class="table-responsive"> 
						<TABLE class="table" style="border: #dbdbdb 1px solid; WIDTH: 100%; border-collapse:collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;" class=rtable border=0 cellSpacing=0 cellPadding=0 align=center>
					<thead>
					<TR>
						<TH style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
										<b>Sr.No.</b>
									</TH>
						<TH style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
										<b>Item</b>
									</TH>
						<TH style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
										<b>Qty</b>
									</TH>
						<TH style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
										<b>Amount</b>
						</TH>	
					</TR>
					</thead>
					<tbody>';
				$i=0;	
				foreach($Itemsdetails as $item)
				{	
					$html .= '<TR>
					<TD style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
					   '.($i+1).')
						</TD>
						<TD style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
					   '.$item["name"].'
						</TD>
						<TD style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
						'.$item["quantity"].'
						</TD>
						<TD style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left>
							'.$item["Price"].'
						</TD>							
					</TR>';
					$i++;
				}
			$html .='</tbody></TABLE></div>';
		
		/******************************************************/
			$Paid_amount = $subtotal-$EquiRedeem-$Pos_discount_amount;
			
			$EmailParam["error"] = false;
			$EmailParam['Outlet_name'] = $Pos_outlet_name;
			$EmailParam['Bill_no'] = $Pos_bill_no;
			$EmailParam['Order_no'] = $Pos_bill_no;
			$EmailParam['Order_amount'] = number_format($subtotal, 2);
			$EmailParam['Redeem_points'] = round($Cust_redeem_point);	
			$EmailParam['Redeem_amount'] = number_format($EquiRedeem, 2);
			$EmailParam['Discount_amount'] = number_format($Pos_discount_amount, 2);
			$EmailParam['Balance_due'] = number_format($Paid_amount, 2);
			$EmailParam['Gained_points'] = round($total_loyalty_email);
			$EmailParam['Current_balance'] = $Update_Current_balance;
			$EmailParam['Discount_voucher'] = $Pos_voucher_no;
			$EmailParam['datatable'] = $html;
			$EmailParam['Email_template_id'] =5; 
			
			$email = $sendEObj->sendEmail($EmailParam,$Customer_enroll_id); 
			
			$DiscountResultVal = $discountObj->get_payment_type_discount_value($Payment_type_id,$Company_id,$Pos_outlet_id,$Customer_enroll_id,$lv_member_Tier_id,$grand_total);
			
			if($DiscountResultVal != Null) 
			{
				foreach($DiscountResultVal as $f)
				{
					$Discount_codes[] = $f;
				} 
			}
			
			if($Discount_codes != null)
			{
				foreach($Discount_codes as $y)
				{
					//	echo "Discount_voucher_code--".$y['Discount_voucher_code']; continue;
					if($y['Discount_voucher_code'] != "")
					{
						if($y['Discount_voucher_percentage'] > 0)
						{
							$giftData["Card_balance"] = $y['Discount_voucher_percentage'];
							$giftData["Discount_percentage"] = $y['Discount_voucher_percentage'];
						}
						else if($y['Discount_voucher_amt'] > 0)
						{
							$giftData["Card_balance"] = $y['Discount_voucher_amt'];
							$giftData["Card_value"] = $y['Discount_voucher_amt'];
							$giftData["Discount_percentage"] = 0.00;
						}
						$giftData["Company_id"] = $Company_id;
						$giftData["Gift_card_id"] = $y['Discount_voucher_code'];
						$giftData["Card_id"] = $CardId;
						$giftData["User_name"] = $fname.' '.$lname;
						$giftData["Email"] = $User_email_id;
						$giftData["Phone_no"] = $User_phone_no;
						$giftData["Payment_Type_id"] = 99;
						$giftData["Seller_id"] = $Pos_outlet_id1;
						$giftData["Valid_till"] = date("Y-m-d",strtotime($y['Discount_voucher_validity']));
						
						$orderObj->insert_voucher_in_gift_card($giftData);
					
						//**************discount voucher****************
						if($giftData["Discount_percentage"] > 0)
						{
							$Description = $giftData['Discount_percentage']." (%) Discount";
						}
						else
						{
							$Description = "worth ".$y['Discount_voucher_amt'];
						}	
						
						$DiscountEmailParam["error"] = false;
						$DiscountEmailParam['Outlet_name'] = $Pos_outlet_name;
						$DiscountEmailParam['Order_no'] = $bill;	
						$DiscountEmailParam['Voucher_no'] = $y['Discount_voucher_code'];
						$DiscountEmailParam['Reward_amt'] = $y['Discount_voucher_amt'];
						$DiscountEmailParam['Reward_percent'] = $giftData["Discount_percentage"];
						$DiscountEmailParam['Voucher_validity'] = date("Y-m-d",strtotime($y['Discount_voucher_validity']));
						$DiscountEmailParam['Description'] = $Description;
						$DiscountEmailParam['Email_template_id'] =24; 
						
						$email = $sendEObj->sendEmail($DiscountEmailParam,$Customer_enroll_id);
					//**************discount voucher*****************
					}
				}
			}
		
			$insert_transaction_id = 1 ;
			if($insert_transaction_id > 0)
			{
				$Enroll_details = $dbHandlerObj->get_enrollment_details($Enrollement_id,$Company_id);
				if($Enroll_details !=Null)
				{
					$Current_balance1 = $Enroll_details['Current_balance'];
					$Blocked_points1 = $Enroll_details['Blocked_points'];
					$Debit_points1 = $Enroll_details['Debit_points'];
					
					$Current_point_balance1 = $Current_balance1 - ($Blocked_points1 + $Debit_points1);

					if ($Current_point_balance1 < 0) 
					{
						$Available_Balance = 0;
					}
					else 
					{
						$Available_Balance = $Current_point_balance1;
					}
				}
				else
				{
					$Updateed_Balance=$Update_Current_balance-($Blocked_points+$Debit_points);	
			
					if($Updateed_Balance<0)
					{
						$Available_Balance=0; 
					}
					else
					{
						$Available_Balance=$Updateed_Balance;
					}
				}
			//*****************
				$_SESSION['DiscountAmt'] = "";
				$_SESSION['BillDiscount'] = "";
				$_SESSION['ItemDiscounts'] = "";
				
			//*****************
		
				$Pos_discount_amount = $Pos_discount_amount - $Pos_discount;
				
				$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
				
					$response["errorcode"] = 1001;
					$response["message"] = "Successful";
					$response["membershipid"] = $CardId;
					$response["membername"] = $Member_name;
					$response["orderno"] = $Pos_order_no;
					$response["orderamount"] = number_format($subtotal,2);
					$response["loyaltydiscount"] = number_format($loyalty_discount,2);
					$response["pointsamount"] = number_format($EquiRedeem,2);
					$response["voucheramount"] = number_format($Pos_voucher_amount,2);
					$response["giftcardamount"] = number_format($Pos_giftcard_amount,2);
					$response["balancedue"] = number_format($grand_total,2);
					$response["gainedpoints"] = $total_loyalty_email;
					$response["currentbalance"] = round($Available_Balance);
					// $response["note"] = "gainedpoints will be added into current balance when order will be closed";
					
				/******************insert JSON*****************/
					$APILogParam['Company_id'] =$Company_id;
					$APILogParam['Trans_type'] = $Trans_type;
					$APILogParam['Outlet_id'] = $delivery_outlet;
					$APILogParam['Bill_no'] = $Pos_order_no;
					$APILogParam['Card_id'] = $CardId;
					$APILogParam['Date'] = $lv_date_time;
					$APILogParam['Json_input'] = $json;
					$APILogParam['Json_output'] = json_encode($response);
					
					$APILog = $logHObj->insertAPILog($APILogParam); 
				/******************insert JSON*****************/			
					
					echoRespnse($response); 
					exit;
			}
			else    
			{
				$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
				
				$response["errorcode"] = 2068;
				$response["message"] = "Loyalty transaction failed";
				echoRespnse($response); 
				exit;
			}
			
		}
		else
		{
			$response["errorcode"] = 2009;
			$response["message"] = "Invalid or unable to locate outlet number";
			echoRespnse($response); 
			exit;
		}	
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
		echoRespnse($response); 
		exit;
	}
	echoRespnse($response); 
	//exit;
});
$app->post('/closeorder','authenticate', function() use ($app) 
{  	
	$Company_id = $_SESSION["company_id"];
	$Ecommerce_flag = $_SESSION["Ecommerce_flag"];
	$First_trans_bonus_flag = $_SESSION["First_trans_bonus_flag"];
	$Bday_bonus_flag = $_SESSION["Bday_bonus_flag"];
	
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	verifyRequiredParams(array('membershipid','orderno','outletno','orderdate','orderstatus'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$order_date = $request_array['orderdate'];
	$outlet_no = $request_array['outletno'];
	$order_no = $request_array['orderno'];
	$order_status = $request_array['orderstatus'];
	
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();

	$orderObj = new OrderHandler();
	$sendEObj = new SendEmailHandler();
	$logHObj = new LogHandler();
	$userObj = new UserHandler();
	
	$user = $orderObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$lv_member_Tier_id = $user['Tier_id'];
		$Membership_ID = $user['Card_id'];
		$CardId = $user['Card_id'];
		$Card_id = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$User_email_id = $user['User_email_id'];
		$User_phone_no = $user['Phone_no'];
		$User_id = $user['User_id'];
		$bal = $user['Current_balance'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Total_reddems = $user['Total_reddems'];
		$total_purchase = $user['total_purchase'];
		$Total_topup_amt = $user['Total_topup_amt'];
		$Zipcode = $user['Zipcode'];
		$District = $user['District'];
		$Sex = $user['Sex'];
		$Age = $user['Age'];
		$Date_of_birth = $user['Date_of_birth'];
		$joined_date = $user['joined_date'];
		$Country_id = $user['Country'];
		$State_id = $user['State'];
		$City_id = $user['City'];
		$Member_name = $fname.' '.$lname;
		
		$param['timezone_entry']=$user['timezone_entry'];
		$logtimezone = $param['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');
	
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
		}
		if($Ecommerce_flag == 1)
		{
			$Outlet_details = $orderObj->get_outlet_details($outlet_no,$Company_id);
			if($Outlet_details !=Null)
			{
				$seller_id = $Outlet_details['Enrollement_id'];
				$seller_name = $Outlet_details['First_name'].' '.$Outlet_details['Last_name'];
				$Purchase_Bill_no = $Outlet_details['Purchase_Bill_no'];
				$topup_Bill_no = $Outlet_details['Topup_Bill_no'];
				
				/* $len2 = strlen($topup_Bill_no);
				$str2 = substr($topup_Bill_no, 0, 5);
				$tp_bill2 = substr($topup_Bill_no, 5, $len2);
				$topup_BillNo2 = $tp_bill2 + 1;
				$billno_withyear_ref = $str2 . $topup_BillNo2;
				 */
				
				$tp_db = $topup_Bill_no;
				$len = strlen($tp_db);
				$str = substr($tp_db,0,5);
				$bill = substr($tp_db,5,$len);
				
				$bill_no = $bill + 1;
				$billno_withyear = $str.$bill_no;
									
			/*****************************************************/
				$result_order = $orderObj->Get_order_details($order_no,$Company_id,$Card_id,$Enrollement_id,$outlet_no,$order_date);
										
				if($result_order!=NULL) 
				{
					//$Item_details_array = array();
					$Redeem_pts = array();
					$Loyalty_pts = array();
					
					foreach($result_order as $row)
					{ 
						/* $Item_name=$row->Merchandize_item_name;
						$Item_quantity=$row->Quantity;
						$Item_purchase_amount=$row->Purchase_amount;
						$Trans_date=$row->Trans_date;
						$Manual_billno=$row->Manual_billno;
						 */
						$Item_redeem_pts=$row['Redeem_points'];
						$Item_loyalty_pts=$row['Loyalty_pts'];
						
						//$Item_details = array("Order_no" => $Order_no, "Item_name" =>$Item_name,"Quantity" => $Item_quantity, "Voucher_no" => $Item_voucher_no, "Purchase_amount" => $Item_purchase_amount, "Loyalty_points" => $Item_loyalty_pts, "Condiments_name" =>$Condiments_name, "Shipping_cost" => $Shipping_cost);
						
						//$Item_details_array[] =$Item_details;
						
						$Redeem_pts[]=$Item_redeem_pts;
						$Loyalty_pts[]=$Item_loyalty_pts;
					}
					
					//$Order_date=date("F j, Y",strtotime($Trans_date));
					$lv_date=date("Y-m-d");
					$lv_date_time=date("Y-m-d H:i:s");
					
					if($order_status == "1")	// Close Bill
					{	
						$order_status1 = "Closed";	
						$Debited_points1 = array_sum($Redeem_pts);
						$Creadited_points1 = array_sum($Loyalty_pts);
						
						// $Creadited_points = floor($Creadited_points1);
						$Creadited_points = round($Creadited_points1);
						$First_trans_bonus = round($Creadited_points1);
						$Bday_bonus = round($Creadited_points1);
						
						if($First_trans_bonus_flag == 1)
						{
							$close_bill_count = $orderObj->get_closed_bill_count($Enrollement_id,$Company_id);
							
							if($close_bill_count == 0)
							{
								$First_trans_topup = $First_trans_bonus;
								
								$TransPara['Trans_type']=1;
								$TransPara['Company_id']=$Company_id;
								$TransPara['Trans_date']=$lv_date_time;
								$TransPara['Topup_amount']=$First_trans_bonus;
								$TransPara['Remarks']='First Order Bonus';
								$TransPara['Card_id']=$Card_id;
								$TransPara['Seller_name']=$seller_name;
								$TransPara['Seller']=$seller_id;
								$TransPara['Enrollement_id']=$Enrollement_id;
								$TransPara['Bill_no']=$bill;
								//$TransPara['Order_no']=$order_no;
								$TransPara['remark2']=$order_no;
								
								$Creadited_points = $Creadited_points + $First_trans_bonus;
								
								$Topup = $userObj->insertTopup($TransPara);
								if($Topup)
								{
									$BillPara['seller_id']=$seller_id;
									$BillPara['billno_withyear_ref']=$billno_withyear;
									
									$TopupBill = $dbHandlerObj->updateTopupBillNo($BillPara);
								}
							}
							else
							{
								$First_trans_topup = 0;
							}	
						}
						else
						{
							$First_trans_topup = 0;
						}
						
						if($Bday_bonus_flag == 1)
						{
							$current_month = date("m", strtotime($lv_date));
							
							if($Date_of_birth !=Null)
							{
								$birth_month = date("m", strtotime($Date_of_birth));
							}
							else
							{
								$birth_month = Null;
							}							
							if($current_month == $birth_month)
							{	
								if($close_bill_count == 0)
								{
									$bill_no = $bill_no +1;
									$bill = $bill+1;
									$billno_withyear = $str.$bill_no;
								}
								else
								{
									$bill_no = $bill_no;
									$bill = $bill;
									$billno_withyear = $str.$bill_no;
								}
								
								$Bday_topup = $Bday_bonus;
								
								$TransPara1['Trans_type']=1;
								$TransPara1['Company_id']=$Company_id;
								$TransPara1['Trans_date']=$lv_date_time;
								$TransPara1['Topup_amount']=$Bday_bonus;
								$TransPara1['Remarks']='Birth day Bonus';
								$TransPara1['Card_id']=$Card_id;
								$TransPara1['Seller_name']=$seller_name;
								$TransPara1['Seller']=$seller_id;
								$TransPara1['Enrollement_id']=$Enrollement_id;
								$TransPara1['Bill_no']=$bill;
								//$TransPara1['Order_no']=$order_no;
								$TransPara1['remark2']=$order_no;
								
								$Creadited_points = $Creadited_points + $Bday_bonus;
								
								$BdayTopup = $userObj->insertTopup($TransPara1);
								if($BdayTopup)
								{
									$BillPara1['seller_id']=$seller_id;
									$BillPara1['billno_withyear_ref']=$billno_withyear;
									
									$TopupBill = $dbHandlerObj->updateTopupBillNo($BillPara1);
								}
							}
							else
							{
								$Bday_topup = 0;
							}
						}
						else
						{
							$Bday_topup = 0;
						}						
						
						$OrderPara['Voucher_status'] = 20;// Closed Bill					
						$OrderPara['Update_User_id'] = $seller_id;								
						$OrderPara['Update_date'] = $lv_date_time;	
						
						$result = $orderObj->Update_Order_Status($OrderPara,$Card_id,$Enrollement_id,$Company_id,$order_no,$outlet_no,$order_date);
						
						
						$Update_Current_balance= $Current_balance+$Creadited_points;
						
						$UpdateMemberPara['Current_balance']= $Update_Current_balance;
						$UpdateMemberPara['Total_topup_amt']= $Total_topup_amt + $First_trans_topup + $Bday_topup;	
						
						$MemberBalance = $dbHandlerObj->updateMemberBalance($UpdateMemberPara,$Enrollement_id);		
					}
					else if($order_status == "0")
					{
						$order_status1 = "Canceled";
						$Creadited_points = 0;
						$OrderPara['Voucher_status'] = 21;// Order Cancel				
						$OrderPara['Update_User_id'] = $seller_id;								
						$OrderPara['Update_date'] = $lv_date_time;	
						
						$result = $orderObj->Update_Order_Status($OrderPara,$Card_id,$Enrollement_id,$Company_id,$order_no,$outlet_no,$order_date);
					}
					else
					{
						$response["errorcode"] = 2010;
						$response["message"] = "Please provide valid order status";
						echoRespnse($response); 
						exit;
					}
					
					$Enroll_details = $orderObj->get_enrollment_details($Enrollement_id,$Company_id);
					if($Enroll_details !=Null)
					{
						$Current_balance1 = $Enroll_details['Current_balance'];
						$Blocked_points1 = $Enroll_details['Blocked_points'];
						$Debit_points1 = $Enroll_details['Debit_points'];
					}
					
					$Current_point_balance1 = $Current_balance1 - ($Blocked_points1 + $Debit_points1);

					if ($Current_point_balance1 < 0) 
					{
						$Current_point_balance = 0;
					}
					else 
					{
						$Current_point_balance = $Current_point_balance1;
					}
					
					$response["errorcode"] = 1001;
					$response["message"] = "Successful";
					$response["membershipid"] = $Card_id;
					$response["membername"] = $Member_name;
					$response["orderno"] = $order_no;
					$response["orderstatus"] = $order_status1;
					$response["gainedpoints"] = $Creadited_points;
					$response["currentbalance"] = $Current_point_balance;
					
					echoRespnse($response); 
					exit;
				}
				else
				{
					$response["errorcode"] = 3011;
					$response["message"] = "Please provide valid order number and date, order not exist or order has been already closed or canceled!";
					echoRespnse($response); 
					exit;
				}
			/*****************************************************/
			}
			else
			{
				$response["errorcode"] = 2009;
				$response["message"] = "Invalid or unable to locate outlet number";
				echoRespnse($response); 
				exit;
			}
		}
		else
		{
			$response["errorcode"] = 2008;
			$response["message"] = "Sorry, unable procced";
			echoRespnse($response); 
			exit;
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
		echoRespnse($response); 
		exit;
	}
	echoRespnse($response); 
});
$app->post('/pointsestimate','authenticate', function() use ($app) 
{  	
	$Company_id = $_SESSION["company_id"];
	$Ecommerce_flag = $_SESSION["Ecommerce_flag"];
	$First_trans_bonus_flag = $_SESSION["First_trans_bonus_flag"];
	$Bday_bonus_flag = $_SESSION["Bday_bonus_flag"];
	
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	verifyRequiredParams(array('membershipid','orderamount','outletno'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$bill_amount = $request_array['orderamount'];
	$bill_amount = str_replace( ',', '', $bill_amount);
	$outlet_no = $request_array['outletno'];
	$order_no = $request_array['orderno'];
	$Pos_bill_items = $request_array['itemsdata'];
	
	$voucher_details = $request_array['voucherdetails'];
	$Pos_voucher_no = $voucher_details['code'];
	$voucher_amount = $voucher_details['amount'];
	$Pos_voucher_amount = str_replace( ',', '', $voucher_amount);
	
	$gift_card_details = $request_array['giftcarddetails'];
	$Pos_giftcard_no = $gift_card_details['code'];
	
	$redeem_details = $request_array['redeemdetails'];
	$Pos_points_redeemed = $redeem_details['points'];
	$redeem_points = $redeem_details['points'];
	$redeem_amount = $redeem_details['amount'];
	$Pos_points_amount = str_replace( ',', '', $redeem_amount);
	
	$loyaltydiscount = $request_array['loyaltydiscount'];
	$loyalty_discount = str_replace( ',', '', $loyaltydiscount);
	
	$payment_details = $request_array['paymentdetails']; 
	$payment_id = $payment_details['id'];
	$payment_reference = $payment_details['reference'];
	$payment_name = $payment_details['name'];
	$payment_amount = $payment_details['amount'];
	$payment_amount = str_replace( ',', '', $payment_amount);
	
	$subtotal = $bill_amount;
	$ChannelCompanyId = 0;
	$API_flag_call = 90;
	$gained_points_fag = 0;
	$Pos_order_no = $order_no;
	$Pos_bill_no = $order_no;
	$Pos_bill_amount = $bill_amount;  
	$Pos_outlet_id = $outlet_no;
	
	$Pos_discount = 0;
	$Pos_loyalty_discount = $loyalty_discount;
	
	$delivery_outlet = $Pos_outlet_id;
	$Cust_redeem_point = $Pos_points_redeemed;
	$EquiRedeem = $Pos_points_amount;
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	$Stamp_voucher_validity = $_SESSION["Stamp_voucher_validity"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$orderObj = new OrderHandler();
	$discountObj = new DiscountHandler();
	$voucherObj = new VoucherHandler();
	$sendEObj = new SendEmailHandler();
	$logHObj = new LogHandler();
	
	$user = $orderObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{ 
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Cust_enrollement_id = $user['Enrollement_id'];
		$Customer_enroll_id = $user['Enrollement_id'];
		$cust_enrollment_id = $user['Enrollement_id'];
		$lv_member_Tier_id = $user['Tier_id'];
		$Membership_ID = $user['Card_id'];
		$CardId = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$User_email_id = $user['User_email_id'];
		$User_phone_no = $user['Phone_no'];
		$User_id = $user['User_id'];
		$bal = $user['Current_balance'];
		$Current_balance = $user['Current_balance'];
		$Blocked_points = $user['Blocked_points'];
		$Debit_points = $user['Debit_points'];
		$Total_reddems = $user['Total_reddems'];
		$total_purchase = $user['total_purchase'];
		$Total_topup_amt = $user['Total_topup_amt'];
		$Zipcode = $user['Zipcode'];
		$District = $user['District'];
		$Sex = $user['Sex'];
		$Age = $user['Age'];
		$Date_of_birth = $user['Date_of_birth'];
		$joined_date = $user['joined_date'];
		$Country_id = $user['Country'];
		$State_id = $user['State'];
		$City_id = $user['City'];
		$Member_name = $fname.' '.$lname;
		
		$param['timezone_entry']=$user['timezone_entry'];
		$logtimezone = $param['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');
	
		$Available_point_balance = $Current_balance-($Blocked_points+$Debit_points);
		
		if($Available_point_balance<0)
		{
			$Available_point_balance=0;
		}
		else
		{
			$Available_point_balance = $Available_point_balance;
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
			$bill_amount = $bill_amount-$Pos_loyalty_discount;
			$Reddem_amount = Validate_redeem_points($redeem_points,$Redemption_ratio,$bill_amount);
			if($Reddem_amount == 0000)
			{
				$Points_amount = 0;
				$response["errorcode"] = 2066;
				$response["message"] = "Equivalent Redeem Amount is More than Order Amount";
				echoRespnse($response); 
				exit;
			}
			else
			{
				$Points_amount = $Reddem_amount;
				$Cust_redeem_point = $redeem_points;
				$Pos_points_amount = $Reddem_amount;
				$EquiRedeem = $Pos_points_amount;
			}
		}
	
	/*******************************check bill amount***************************/
		if($Pos_bill_items != Null)
		{
			$check_price_array = array();
			foreach($Pos_bill_items as $item)
			{ 
				$Itemquantity = str_replace( ',', '', $item['quantity']);
				$ItemPrice = str_replace( ',', '', $item['price']);
				$Total_price = $Itemquantity*$ItemPrice;
				$check_price_array[] = $Total_price;
			}
			$Total_items_amaount = array_sum($check_price_array);
			
			if($Total_items_amaount != $Pos_bill_amount)
			{
				$response["errorcode"] = 1002;
				$response["message"] = "Order amount doesn't match with items price";
				echoRespnse($response); 
				exit;
			}
		}
	/*******************************check bill amount***************************/
		
		// $payment_amount = $bill_amount - $Points_amount;
		
		$Outlet_details = $orderObj->get_outlet_details($outlet_no,$Company_id);
		if($Outlet_details !=Null)
		{
			$seller_id = $Outlet_details['Enrollement_id'];
			$seller_fname = $Outlet_details['First_name'];
			$seller_lname = $Outlet_details['Last_name'];
			$seller_email_id = $Outlet_details['User_email_id'];
			$Pos_outlet_name = $Outlet_name=$Outlet_details['First_name'].' '.$Outlet_details['Last_name'];
			$Seller_Redemptionratio = $Outlet_details['Seller_Redemptionratio'];
			$Purchase_Bill_no = $Outlet_details['Purchase_Bill_no'];
			$Sub_seller_admin = $Outlet_details['Sub_seller_admin'];
			$Sub_seller_Enrollement_id = $Outlet_details['Sub_seller_Enrollement_id'];
			
			$Pos_outlet_id1 = $seller_id;
			$delivery_outlet =  $seller_id;
			
			if($Sub_seller_admin == 1) 
			{
				$Pos_outlet_id = $seller_id;
			}
			else 
			{
				$Pos_outlet_id = $Sub_seller_Enrollement_id;
			}
			
			if($Seller_Redemptionratio !=Null)
			{
				$Company_Redemptionratio = $Seller_Redemptionratio;
			}
			else
			{
				$Company_Redemptionratio = $Redemption_ratio;
			}
			
			$tp_db = $Purchase_Bill_no;
			$len = strlen($tp_db);
			$str = substr($tp_db,0,5);
			$bill = substr($tp_db,5,$len);

			$date = new DateTime();
			$lv_date_time=$date->format('Y-m-d H:i:s'); 
  
			$lv_date_time2 = $date->format('Y-m-d'); 

			$Trans_type = 12;
			$Trans_Channel_id = 12;
			$Payment_type_id = $Pos_payment_type;
			
			if($Payment_type_id == Null)
			{
				$Payment_type_id = 1;
			}
			$Remarks = "Saas Api Online Order";
			
			if($Sub_seller_admin == 1) 
			{
			  $seller_id = $seller_id;
			}
			else 
			{
			  $seller_id = $Sub_seller_Enrollement_id;
			}
		
			$order_total_loyalty_points = 0;
		/*********************16-9-2021***************************/	
			if($Pos_bill_items != Null)
			{
				foreach($Pos_bill_items as $item)
				{ 
					$ItemCode = $item['code']; 
					
					$ItemDetails = $orderObj->Get_item_details($ItemCode,$Company_id);
					
					if($ItemDetails !=NULL)
					{
						$Merchandize_item_code = $ItemDetails['Company_merchandize_item_code'];
						$Item_name = $ItemDetails['Merchandize_item_name'];
						
						
						$CheckItemTempCart = $orderObj->GetItemsDetails($Company_id,$Cust_enrollement_id,$ItemCode,$delivery_outlet,$ChannelCompanyId);
						
						if($CheckItemTempCart != Null)
						{
							$TempQty = $CheckItemTempCart['Item_qty'];
							
							$TempCartData["Item_qty"] = $TempQty+$item['quantity'];
							
							$orderObj->update_pos_temp_cart($TempCartData,$Company_id,$Cust_enrollement_id,$ItemCode,$delivery_outlet,$ChannelCompanyId);
						}
						else
						{
							$data78['Company_id'] = $Company_id;
							$data78['Enrollment_id'] = $Cust_enrollement_id;
							$data78['Seller_id'] = $delivery_outlet;
							$data78['Channel_id'] = $ChannelCompanyId;
							$data78['Item_code'] = $ItemCode;
							$data78['Item_qty'] = $item['quantity'];
							$data78['Item_price'] = str_replace( ',', '', $item['price']);
							
							// $this->Online_api_model->insert_item($data78);
							
							$orderObj->insert_item($data78);
						}
					}
					else
					{
						$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
						
						$response["errorcode"] = 3103;
						$response["message"] = "Invalid Item code Or Item not exist.";
						echoRespnse($response); 
						exit;
					}
				}
			}
			else
			{
				$response["errorcode"] = 3103;
				$response["message"] = "Invalid Item code Or Item not exist.";
				echoRespnse($response); 
				exit;
			}
			
			$Pos_bill_items = $orderObj->Get_temp_cart_items($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
		
		
			$Pos_voucher_no = Null;
			$Pos_voucher_amount = 0.00;
			
		/********************validate gift card**********************/
			$Pos_giftcard_no = Null;
			$Pos_giftcard_amount = 0.00;
			
		/*******************check discount, voucher, gift card, points amount****************************/
			$Pos_discount_amount = $Pos_discount+$Pos_loyalty_discount+$Pos_voucher_amount+$Pos_giftcard_amount; //09-04-2021
									
			$grand_total = ($Pos_bill_amount-$Pos_points_amount)-$Pos_discount_amount;
	
			if($grand_total < 0 )
			{
				$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
				
				$response["errorcode"] = 2066;
				$response["message"] = "Total discount amount and points amount is more than order amount";
				echoRespnse($response); 
				exit;
			}
			
			$get_country = $orderObj->Fetch_country($Country_id);
			$get_state = $orderObj->Fetch_state($State_id);
			$get_city = $orderObj->Fetch_city($City_id);
			$Country_name = $get_country['Country_name'];
			$State_name = $get_state['State_name'];
			$City_name = $get_city['City_name'];
		/**************new logic with pos items********************/
			if($Pos_bill_items != Null)
			{
				$order_sub_total = 0;	
				$shipping_cost = 0;
				$DiscountAmt = 0;
				$TotalvoucherAmt = 0;
				$TotalDiscountAmt = 0;
				$tax = 0;	
				$i = 0;
		
				$Pos_discount_amount = $Pos_discount+$Pos_loyalty_discount+$Pos_voucher_amount+$Pos_giftcard_amount; 
				
				$grand_total = ($Pos_bill_amount-$Pos_points_amount)-$Pos_discount_amount;
				
				if($grand_total < 0 )
				{
					$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
					
					$response["errorcode"] = 2066;
					$response["message"] = "Discount amount and points amount is more than order amount.";
					echoRespnse($response); 
					exit;
				}		
					
				$Extra_earn_points_Loyalty_pts = array();
				foreach ($Pos_bill_items as $item)
				{
					/********************************/
						$characters = 'A123B56C89';
						$string = '';
						$Voucher_no="";
						for ($i = 0; $i < 10; $i++) 
						{
							$Voucher_no .= $characters[mt_rand(0, strlen($characters) - 1)];
						}
						$Voucher_array1[]=$Voucher_no;
					/*************************************/
						$Item_code = $item['Item_Num'];
						$Pos_item_rate = $item['Item_Rate'];
						$Pos_item_rate = str_replace( ',', '', $Pos_item_rate);
						$Pos_item_qty = $item['Item_Qty'];
					/********Get Merchandize item name********/
					
						$result = $orderObj->Get_item_details($Item_code,$Company_id);
						
						$sellerID = $result->Seller_id;
						if($sellerID !=NULL || $sellerID !='0')
						{
							$sellerID = $sellerID; // apply item outlet rule
						}
						else
						{
							$sellerID = $seller_id; // apply POS outlet rule
						}
						
						$sellerID = $seller_id; // apply POS outlet rule
						
						$Merchandise_item_id = $result['Company_merchandise_item_id'];
						$Company_merchandize_item_code = $result['Company_merchandize_item_code'];
						$Merchandize_item_name = $result['Merchandize_item_name'];
						$Merchandize_category_id = $result['Merchandize_category_id'];
						$Stamp_item_flag = $result['Stamp_item_flag'];
						$Merchandize_partner_id = $result['Partner_id'];
						// $Item_cost_price = $result->Cost_price*$Pos_item_qty;
						
						$Item_cost_price = $Pos_item_rate*$Pos_item_qty;
						
					/******************New Loyalty Rule Logic********************/ 
						$Extra_earn_points = 0;
						
						if($Stamp_item_flag == 1)
						{
							$Extra_earn_points = $result['Extra_earn_points'];
							$Extra_earn_points_Loyalty_pts[]=$Extra_earn_points;
						}
						if($sellerID!=0)
						{
						/**********Get Seller Details**********/
							$Seller_result = $orderObj->get_brand_details($sellerID,$Company_id);	
							$Seller_First_name = $Seller_result['First_name'];
							$Seller_Last_name = $Seller_result['Last_name'];
							$seller_name = $Seller_First_name.' '.$Seller_Last_name;
							$Purchase_Bill_no = $Seller_result['Purchase_Bill_no'];

							$tp_db = $Purchase_Bill_no;
							$len = strlen($tp_db);
							$str = substr($tp_db,0,5);
							$bill = substr($tp_db,5,$len);
						/**********Get Seller Details**********/
						
							$seller_id=$sellerID;
							
							$loyalty_prog = $orderObj->get_tierbased_loyalty($Company_id,$seller_id,$lv_member_Tier_id,$lv_date_time2);
							
							$points_array = array();

							$Applied_loyalty_id = array();
							if($loyalty_prog != NULL )
							{
								foreach($loyalty_prog as $prog)
								{
									$member_Tier_id = $lv_member_Tier_id;
									$value = array();
									$dis = array();
									$LoyaltyID_array = array();
									$Loyalty_at_flag = 0;	
									$lp_type=substr($prog['Loyalty_name'],0,2);
									$Todays_date = $lv_date_time;
									
									$prog = $prog['Loyalty_name'];
							
									$lp_details = $orderObj->get_loyalty_program_details($Company_id,$seller_id,$prog,$lv_date_time2);
								
									$lp_count = count($lp_details);

									foreach($lp_details as $lp_data)
									{
										$LoyaltyID = $lp_data['Loyalty_id'];
										$lp_name = $lp_data['Loyalty_name'];
										$lp_From_date = $lp_data['From_date'];
										$lp_Till_date = $lp_data['Till_date'];
										$Loyalty_at_value = $lp_data['Loyalty_at_value'];
										$Loyalty_at_transaction = $lp_data['Loyalty_at_transaction'];
										$discount = $lp_data['discount'];
										$lp_Tier_id = $lp_data['Tier_id'];
										$Category_flag = $lp_data['Category_flag'];
										$Category_id = $lp_data['Category_id'];
										$Segment_flag = $lp_data['Segment_flag'];
										$Segment_id	= $lp_data['Segment_id'];
									
								//*************channel and payment ***************
									$Trans_Payment_flag	= $lp_data['Payment_flag'];
									$Trans_Channel_flag	= $lp_data['Channel_flag'];
									$Trans_Channel	= $lp_data['Trans_Channel'];
									$Lp_Payment_Type_id	= $lp_data['Payment_Type_id'];
									
								//*************channel and payment ***************
								
										if($lp_Tier_id == 0)
										{
											$member_Tier_id = $lp_Tier_id;
										}
										if($Loyalty_at_value > 0)
										{
											$value[] = $Loyalty_at_value;	
											$dis[] = $discount;
											$LoyaltyID_array[] = $LoyaltyID;
											$Loyalty_at_flag = 1;
										}
										if($Loyalty_at_transaction > 0)
										{
											$value[] = $Loyalty_at_transaction;	
											$dis[] = $Loyalty_at_transaction;
											$LoyaltyID_array[] = $LoyaltyID;
											$Loyalty_at_flag = 2;
										}
									}
								
									if($lp_type == 'PA')
									{	
										$transaction_amt1=$Pos_item_qty * $Pos_item_rate;
										
										// $transaction_amtNew = cheque_format($transaction_amt1);
										$transaction_amtNew = $transaction_amt1;
										$transaction_amt = str_replace( ',', '', $transaction_amtNew);
									}
									if($lp_type == 'BA')
									{	
										// $grand_totalNew = cheque_format($grand_total);
										$grand_totalNew = $grand_total;
										$grand_totalNew = str_replace( ',', '', $grand_totalNew);
										$Purchase_amount=$Pos_item_qty * $Pos_item_rate;
										 $transaction_amt = (($grand_totalNew * $Purchase_amount ) / $subtotal);
									}
									
									
								//*************channel and payment***************
									if($Trans_Channel_flag==1)
									{
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1 && $Trans_Channel_id == $Trans_Channel )
										{
											for($i=0;$i<=count($value)-1;$i++)
											{
												if($i<count($value)-1 && $value[$i+1] != "")
												{
													if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;
														$gained_points_fag = 1;
														$points_array[] = $loyalty_points;
													}
												}
												else if($transaction_amt > $value[$i])
												{
													$loyalty_points = get_discount($transaction_amt,$dis[$i]);
													$gained_points_fag = 1;
													$trans_lp_id = $LoyaltyID_array[$i];
													$Applied_loyalty_id[]=$trans_lp_id;					
													$points_array[] = $loyalty_points;
												}
											}
										}
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2 && $Trans_Channel_id == $Trans_Channel )
										{
											$loyalty_points = get_discount($transaction_amt,$dis[0]);
											$points_array[] = $loyalty_points;
											$gained_points_fag = 1;
											$trans_lp_id = $LoyaltyID_array[0];
											$Applied_loyalty_id[]=$trans_lp_id;
										}						
									// unset($dis);
									}	
									if($Trans_Payment_flag == 1)
									{
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1 && $Lp_Payment_Type_id == $Payment_type_id )
										{
											for($i=0;$i<=count($value)-1;$i++)
											{
												if($i<count($value)-1 && $value[$i+1] != "")
												{
													if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;
														$gained_points_fag = 1;
														$points_array[] = $loyalty_points;
													}
												}
												else if($transaction_amt > $value[$i])
												{
													$loyalty_points = get_discount($transaction_amt,$dis[$i]);
													$gained_points_fag = 1;
													$trans_lp_id = $LoyaltyID_array[$i];
													$Applied_loyalty_id[]=$trans_lp_id;					
													$points_array[] = $loyalty_points;
												}
											}
										}
										
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2 && $Lp_Payment_Type_id == $Payment_type_id)
										{
											$loyalty_points = get_discount($transaction_amt,$dis[0]);
											$points_array[] = $loyalty_points;
											$gained_points_fag = 1;
											$trans_lp_id = $LoyaltyID_array[0];
											$Applied_loyalty_id[]=$trans_lp_id;
										}	
									}
								//************channel and payment ***************
									if($Category_flag==1)
									{
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1 && $Merchandize_category_id == $Category_id )
										{
											for($i=0;$i<=count($value)-1;$i++)
											{
												if($i<count($value)-1 && $value[$i+1] != "")
												{
													if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;
														$gained_points_fag = 1;
														$points_array[] = $loyalty_points;
													}
												}
												else if($transaction_amt > $value[$i])
												{
													$loyalty_points = get_discount($transaction_amt,$dis[$i]);
													$gained_points_fag = 1;
													$trans_lp_id = $LoyaltyID_array[$i];
													$Applied_loyalty_id[]=$trans_lp_id;					
													$points_array[] = $loyalty_points;
												}
											}
										}
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2 && $Merchandize_category_id == $Category_id )
										{
											$loyalty_points = get_discount($transaction_amt,$dis[0]);
											$points_array[] = $loyalty_points;
											$gained_points_fag = 1;
											$trans_lp_id = $LoyaltyID_array[0];
											$Applied_loyalty_id[]=$trans_lp_id;
										}						
									// unset($dis);
									}
									else if($Segment_flag==1)
									{											
										$Get_segments2 = $orderObj->edit_segment_id($Company_id,$Segment_id);
										
										$Customer_array=array();
										$Applicable_array[]=0;
										unset($Applicable_array);
										
										foreach($Get_segments2 as $Get_segments)
										{
											if($Get_segments['Segment_type_id']==1)  // 	Age 
											{
												$lv_Cust_value=date_diff(date_create($Date_of_birth), date_create('today'))->y;
											}												
											if($Get_segments['Segment_type_id']==2)//Sex
											{
												$lv_Cust_value=$Sex;
											}
											if($Get_segments['Segment_type_id']==3)//Country
											{
												$lv_Cust_value = $Country_name;
												if(strcasecmp($lv_Cust_value,$Get_segments['Value'])==0)
												{
													$Get_segments['Value']=$lv_Cust_value;
												}
											}
											if($Get_segments['Segment_type_id']==4)//District
											{
												$lv_Cust_value=$District;
												
												if(strcasecmp($lv_Cust_value,$Get_segments['Value'])==0)
												{
													$Get_segments['Value']=$lv_Cust_value;
												}
											}
											if($Get_segments['Segment_type_id']==5)//State
											{
												$lv_Cust_value=$State_name;	
												if(strcasecmp($lv_Cust_value,$Get_segments['Value'])==0)
												{
													$Get_segments['Value']=$lv_Cust_value;
												}
											}
											if($Get_segments['Segment_type_id']==6)//city
											{
												$lv_Cust_value=$City_name;
												
												if(strcasecmp($lv_Cust_value,$Get_segments['Value'])==0)
												{
													$Get_segments['Value']=$lv_Cust_value;
												}
											}
											if($Get_segments['Segment_type_id']==7)//Zipcode
											{
												$lv_Cust_value=$Zipcode;
												
											}
											if($Get_segments['Segment_type_id']==8)//Cumulative Purchase Amount
											{
												$lv_Cust_value=$total_purchase;	
											}
											if($Get_segments['Segment_type_id']==9)//Cumulative Points Redeem 
											{
												$lv_Cust_value=$Total_reddems;
											}
											if($Get_segments['Segment_type_id']==10)//Cumulative Points Accumulated
											{
												$start_date=$joined_date;
												$end_date=date("Y-m-d");
												$transaction_type_id = 12;
												$Tier_id=$lp_Tier_id;
												
												$Trans_Records = $orderObj->get_cust_trans_summary_all($Company_id,$Customer_enroll_id,$start_date,$end_date,$transaction_type_id,$Tier_id,'','');
												
												// foreach($Trans_Records as $Trans_Records)
												// {
													$lv_Cust_value=$Trans_Records['Total_Gained_Points'];
												// }											
											}
											if($Get_segments['Segment_type_id']==11)//Single Transaction  Amount
											{
												$start_date=$joined_date;
												$end_date=date("Y-m-d");
												$transaction_type_id = 12;
												$Tier_id=$lp_Tier_id;
												
												$Trans_Records1 = $orderObj->get_cust_trans_details($Company_id,$start_date,$end_date,$Customer_enroll_id,$transaction_type_id,$Tier_id,'','');
												
												/* foreach($Trans_Records as $Trans_Records)
												{
													$lv_Max_amt[]=$Trans_Records->Purchase_amount;
												}
												$lv_Cust_value=max($lv_Max_amt); */	
												
												$lv_Cust_value=$Trans_Records1['Purchase_amount'];				
											}
											if($Get_segments['Segment_type_id']==12)//Membership Tenor
											{
												$tUnixTime = time();
												list($year,$month, $day) = EXPLODE('-', $joined_date);
												$timeStamp = mktime(0, 0, 0, $month, $day, $year);
												$lv_Cust_value= ceil(abs($timeStamp - $tUnixTime) / 86400);
											}
											
											$Get_segments = Get_segment_based_customers($lv_Cust_value,$Get_segments['Operator'],$Get_segments['Value'],$Get_segments['Value1'],$Get_segments['Value2']);
											
											$Applicable_array[]=$Get_segments;
											
										}
										if(!in_array(0, $Applicable_array, true))
										{
											$Customer_array[]=$Customer_enroll_id;
											
											if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1)
											{
												for($i=0;$i<=count($value)-1;$i++)
												{
													if($i<count($value)-1 && $value[$i+1] != "")
													{
														if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
														{
															$loyalty_points = get_discount($transaction_amt,$dis[$i]);
															$trans_lp_id = $LoyaltyID_array[$i];
															$Applied_loyalty_id[]=$trans_lp_id;
															$gained_points_fag = 1;
															$points_array[] = $loyalty_points;
														}
													}
													else if($transaction_amt > $value[$i])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$gained_points_fag = 1;
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;					
														$points_array[] = $loyalty_points;
													}
												}
											}									
											if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2 )
											{	
												$loyalty_points = get_discount($transaction_amt,$dis[0]);
												$points_array[] = $loyalty_points;
												$gained_points_fag = 1;
												$trans_lp_id = $LoyaltyID_array[0];
												$Applied_loyalty_id[]=$trans_lp_id;	
											}
										} 
									}
									else
									{
										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 1  && $Trans_Channel == 0 && $Lp_Payment_Type_id == 0)
										{
											for($i=0;$i<=count($value)-1;$i++)
											{
												if($i<count($value)-1 && $value[$i+1] != "")
												{
													if($transaction_amt > $value[$i] && $transaction_amt <= $value[$i+1])
													{
														$loyalty_points = get_discount($transaction_amt,$dis[$i]);
														$trans_lp_id = $LoyaltyID_array[$i];
														$Applied_loyalty_id[]=$trans_lp_id;
														$gained_points_fag = 1;
														$points_array[] = $loyalty_points;
													}
												}
												else if($transaction_amt > $value[$i])
												{
													$loyalty_points = get_discount($transaction_amt,$dis[$i]);
													$gained_points_fag = 1;
													$trans_lp_id = $LoyaltyID_array[$i];
													$Applied_loyalty_id[]=$trans_lp_id;					
													$points_array[] = $loyalty_points;
												}
											}
										}

										if($member_Tier_id == $lp_Tier_id  && $Loyalty_at_flag == 2  && $Trans_Channel == 0 && $Lp_Payment_Type_id == 0)
										{
											$loyalty_points = get_discount($transaction_amt,$dis[0]);
											$points_array[] = $loyalty_points;
											$gained_points_fag = 1;
											$trans_lp_id = $LoyaltyID_array[0];
											$Applied_loyalty_id[]=$trans_lp_id;
										}
									}
								}
								if(count($Applied_loyalty_id) == 0)
								{
									$trans_lp_id=0;
								}											
							}
							if($gained_points_fag == 1)
							{
								$total_loyalty_points = array_sum($points_array);	
							
								$Email_points[]=$total_loyalty_points;
							}
							else
							{
								$total_loyalty_points = 0;
							}
						}
						else
						{
							$total_loyalty_points=0;
							$Email_points[]=$total_loyalty_points;
						}
						
						$total_loyalty_points=$total_loyalty_points + $Extra_earn_points;
						$item_total_amount = $Pos_item_qty * $Pos_item_rate;
				}
				
				$Extra_earn_points = array_sum($Extra_earn_points_Loyalty_pts);
				$total_loyalty_email=(array_sum($Email_points)+ $Extra_earn_points);	
				
				// $total_loyalty_email = floor($total_loyalty_email); // 25/11/2020
				$total_loyalty_email = round($total_loyalty_email);
				
				if($First_trans_bonus_flag == 1)
				{
					$First_trans_bonus = $total_loyalty_email;
					
					$bill_count = $orderObj->get_bill_count($Enrollement_id,$Company_id);
					
					if($bill_count == 0)
					{
						$First_trans_topup = $First_trans_bonus;
					}
					else
					{
						$First_trans_topup = 0;
					}	
				}
				else
				{
					$First_trans_topup = 0;
				}
				
				if($Bday_bonus_flag == 1)
				{
					$Bday_bonus = $total_loyalty_email;
					
					$lv_date=date("Y-m-d");
					
					$current_month = date("m", strtotime($lv_date));
					
					if($Date_of_birth !=Null)
					{
						$birth_month = date("m", strtotime($Date_of_birth));
					}
					else
					{
						$birth_month = Null;
					}							
					if($current_month == $birth_month)
					{	
						$Bday_topup = $Bday_bonus;
					}
					else
					{
						$Bday_topup = 0;
					}
				}
				else
				{
					$Bday_topup = 0;
				}
				
				$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
			
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["membershipid"] = $CardId;
				$response["membername"] = $Member_name;
				$response["orderno"] = $Pos_order_no;
				$response["orderamount"] = number_format($subtotal,2);
				$response["pointsamount"] = number_format($EquiRedeem,2);
				$response["balancedue"] = number_format($grand_total,2);
				$response["gainedpoints"] = $total_loyalty_email;
				$response["firstorderpoints"] = $First_trans_topup;
				$response["bdaypoints"] = $Bday_topup;
				$response["currentbalance"] = round($Available_point_balance);
					
				echoRespnse($response); 
				exit;
			}
			else
			{
				$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$delivery_outlet,$ChannelCompanyId);
				
				$response["errorcode"] = 3103;
				$response["message"] = "Invalid Item code Or Item not exist.";
				echoRespnse($response); 
				exit;
			}	
		}
		else
		{
			$response["errorcode"] = 2009;
			$response["message"] = "Invalid or unable to locate outlet number";
			echoRespnse($response); 
			exit;
		}	
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
		echoRespnse($response); 
		exit;
	}
	echoRespnse($response); 
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
function cheque_format($amount, $decimals = true, $decimal_seperator = '.')
{
	$levels = array(1000000,100000, 10000, 1000, 100, 10, 5, 1);
	$decimal_levels = array(50, 20, 10, 5, 1);
	preg_match('/(?:\\' . $decimal_seperator . '(\d+))?(?:[eE]([+-]?\d+))?$/', (string)$amount, $match);
	$d = isset($match[1]) ? $match[1] : 0;

	foreach ( $levels as $level )
	{
		$level = (float)$level;
		$results[(string)$level] = $div = (int)(floor($amount) / $level);
		if ($div) $amount -= $level * $div;
	}

	if ( $decimals ) {
		$amount = $d;
		foreach ( $decimal_levels as $level )
		{
			$level = (float)$level;
			$results[$level < 10 ? '0.0'.(string)$level : '0.'.(string)$level] = $div = (int)(floor($amount) / $level);
			if ($div) $amount -= $level * $div;
		}
	}	
	if($results['1000000']>0){
	$num=$results['1000000'];
	} else {
		$num=0;
	}
	if($results['100000']>0){
		$num1=$results['100000'];	
	} else {
		$num1=0;
	}
	if($results['10000']>0){
		$num2=$results['10000'];
	} else {
		$num2=0;
	}
	if($results['1000']>0){
		$num3=$results['1000'];
	} else {
		$num3=0;
	}
	if($results['100']>0){
		$num4=$results['100'];
	} else {
		$num4=0;
	}
	$FnalAmt=$num.''.$num1.''.$num2.''.$num3.''.$num4.''.'00';
	$FnalAmt1=number_format($FnalAmt,2);
	return $FnalAmt1;
	//print_r($results);
}
function get_discount($transaction_amt,$discount)
{
	return ($transaction_amt/100) * $discount;
}
function Get_segment_based_customers($lv_Cust_value,$Operator,$Value,$Value1,$Value2)
{
	$access=0;
	if($Operator=="<")
	{
		if($lv_Cust_value<$Value)
		{
			$access=1;
		}
		
	}
	if($Operator=="=")
	{
		if($lv_Cust_value==$Value)
		{
			$access=1;
		}
	}
	if($Operator=="<=")
	{
		if($lv_Cust_value<=$Value)
		{
			$access=1;
		}
	}
	
	
	if($Operator==">")
	{
		if($lv_Cust_value>$Value)
		{
			$access=1;
		}
	}
	if($Operator==">=")
	{
		if($lv_Cust_value>=$Value)
		{
			$access=1;
		}
	}
	if($Operator=="!=")
	{
		if($lv_Cust_value!=$Value)
		{
			$access=1;
		}
	}
	
	if($Operator=="Between")
	{
		if($lv_Cust_value>=$Value1 && $lv_Cust_value<=$Value2)
		{
			$access=1;
		}
	}
	
	return $access;
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