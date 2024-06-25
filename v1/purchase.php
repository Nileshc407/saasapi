<?php
require_once '../include/DbHandler.php';
require_once '../include/PurchaseHandler.php';
require_once '../include/DiscountHandler.php';
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
			
				$comp = new PurchaseHandler();
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
	$Order_no = $request_array['orderno'];
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
	
	$purchaseObj = new PurchaseHandler();
	$discountObj = new DiscountHandler();
	
	$user = $purchaseObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
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
		
		$Outlet_details = $purchaseObj->get_outlet_details($Outlet_no,$Company_id);
			
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
					$ItemDetails = $purchaseObj->Get_item_details($ItemCode,$Company_id);
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
	
	$purchaseObj = new PurchaseHandler();
	
	$user = $purchaseObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		
		$Online_Items = $purchaseObj->get_all_items($Company_id);
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
	$itemsdata = $request_array['itemsdata'];
	
	$voucher_details = $request_array['voucherdetails'];
	$voucher_code = $voucher_details['code'];
	$voucher_amount = $voucher_details['amount'];
	$voucher_amount = str_replace( ',', '', $voucher_amount);
	
	$redeem_details = $request_array['redeemdetails'];
	$redeem_points = $redeem_details['points'];
	$redeem_amount = $redeem_details['amount'];
	$redeem_amount = str_replace( ',', '', $redeem_amount);
	
	$payment_details = $request_array['paymentdetails'];
	$payment_id = $payment_details['id'];
	$payment_reference = $payment_details['reference'];
	$payment_name = $payment_details['name'];
	$payment_amount = $payment_details['amount'];
	$payment_amount = str_replace( ',', '', $payment_amount);

	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$purchaseObj = new PurchaseHandler();
	$sendEObj = new SendEmailHandler();
	$logHObj = new LogHandler();
	
	$user = $purchaseObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
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
			$Reddem_amount = Validate_redeem_points($redeem_points,$Redemption_ratio,$bill_amount);
			if($Reddem_amount == 0000)
			{
				$Points_amount = 0;
				$response["errorcode"] = 2066;
				$response["message"] = "Equivalent Redeem Amount is More than Bill Amount";
				echoRespnse($response); 
				exit;
			}
			else
			{
				$Points_amount = $Reddem_amount;
			}
		}
		
		$payment_amount = $bill_amount - $Points_amount;
		
		$param['timezone_entry']=$user['timezone_entry'];
		$logtimezone = $param['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');
	
		$SellerDetails=$purchaseObj->superSellerDetails();   
        $Super_Seller_id = $SellerDetails['id'];
        $Super_Seller_Name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
        $Seller_timezone_entry =$SellerDetails['timezone_entry'];
		$Purchase_Bill_no = $SellerDetails['Purchase_Bill_no'];
		$tp_db = $Purchase_Bill_no;
		$len = strlen($tp_db);
		$str = substr($tp_db,0,5);
		$bill = substr($tp_db,5,$len);
		$Brand_id = $Super_Seller_id;
		
		$Outlet_details = $purchaseObj->get_outlet_details($outlet_no,$Company_id);
		if($Outlet_details !=Null)
		{
			$Outlet_id=$Outlet_details['Enrollement_id'];
			$Outlet_name=$Outlet_details['First_name'].' '.$$Outlet_details['Last_name'];
			
			$Super_seller_flag=$Outlet_details['Super_seller'];
			$Sub_seller_admin_flag=$Outlet_details['Sub_seller_admin'];
			$Sub_seller_Enrollement_id=$Outlet_details['Sub_seller_Enrollement_id'];
			
			$Purchase_Bill_no=$Outlet_details['Purchase_Bill_no'];
			$tp_db = $Purchase_Bill_no;
			$len = strlen($tp_db);
			$str = substr($tp_db,0,5);
			$bill = substr($tp_db,5,$len);
			
			if($Super_seller_flag == 0 and $Sub_seller_admin_flag == 0)
			{
				$Brand_details = $purchaseObj->get_brand_details($Sub_seller_Enrollement_id,$Company_id);
				$Brand_id=$Brand_details['Enrollement_id'];
				$Brand_name=$Brand_details['First_name'].' '.$Brand_details['Last_name'];
				
				$Purchase_Bill_no=$Brand_details['Purchase_Bill_no'];
				$tp_db = $Purchase_Bill_no;
				$len = strlen($tp_db);
				$str = substr($tp_db,0,5);
				$bill = substr($tp_db,5,$len);
			}
			else
			{
				$Brand_id = $Outlet_id;
			}
		}
		else
		{
			$response["errorcode"] = 2009;
			$response["message"] = "Invalid or unable to locate outlet number";
			echoRespnse($response); 
			exit;
		}
		
		// echo "brand id : $Brand_id";
		echo "-----exit loop!!";
		exit;
		
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
		
		$Gift_card_detail = $purchaseObj->Insert_gift_card($giftData);		
		
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
		
		$Transaction_detail = $purchaseObj->Insert_giftcard_purchase_transaction($transData);
		
		if($Transaction_detail == SUCCESS)
		{
			if($redeem_points > 0)
			{
				$Enroll_details = $purchaseObj->get_enrollment_details($Enrollement_id,$Company_id);
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
				
				$update_balance=$purchaseObj->update_member_balance($MemberPara,$Enrollement_id);
			}
			
			$bill_no = $bill + 1;
			$billno_withyear = $str.$bill_no;
			$BillPara['Purchase_Bill_no'] = $billno_withyear;		
			$result4 = $purchaseObj->updatePurchaseBillno($BillPara,$Super_Seller_id);
			
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