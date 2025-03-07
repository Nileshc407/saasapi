<?php
require_once '../include/DbHandler.php';
require_once '../include/RedemptionHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require_once '../include/SendEmailHandler.php';
require_once '../include/LogHandler.php';
require_once '../include/VoucherHandler.php';
require_once '../include/OrderHandler.php';
require_once '../include/DiscountHandler.php';
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
		
		if ($api_key !=Null) 
		{
			// get the api key
			//$api_key = $headers['Authorization'];
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
			
				$comp = new RedemptionHandler();
				$result = $comp->getCompanyDetails($api_key);
				
				if ($result != NULL) 
				{
					$_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];			
					$_SESSION["phonecode"] = $result["phonecode"];							
					$_SESSION["Company_Redemptionratio"] = $result["Redemptionratio"];		
					$_SESSION["Company_Currency"] = $result["Currency_name"];		
					$_SESSION["Symbol_of_currency"] = $result["Symbol_of_currency"];		
					
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
$app->post('/getdiscountvouchers','authenticate', function() use ($app) 
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
	
	$redemptionObj = new RedemptionHandler();
	
	$user = $redemptionObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
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
		
		$GetVouchers = $redemptionObj->Get_discount_vouchers($Membership_ID,$Company_id);
		
		if($GetVouchers != Null)
		{
			$Voucher_Details = array();
			foreach($GetVouchers as $row) 
			{	
				if($row['Card_value'] > 0)
				{
					$Voucher_Amount = $row['Card_value'];
					$Voucher_Type ="Value";  //Value Voucher
					$Currency_symbol =$_SESSION["Symbol_of_currency"];
				}
				else if($row['Discount_percentage'] > 0)
				{
					$Voucher_Amount = $row['Discount_percentage'];
					$Voucher_Type ="Percentage";  // Percentage Voucher
					$Currency_symbol = "%";
				}
				else
				{
					$Voucher_Amount = 0;
					$Voucher_Type ="Percentage";  // Percentage Voucher
					$Currency_symbol = "%";
				}
				
				
				$GetVouchersId = $redemptionObj->Get_voucher_id($row['Gift_card_id'],$Company_id,$Enrollement_id);
				$Voucher_id = $GetVouchersId['Voucher_id'];
				$Offer_code = $GetVouchersId['Offer_code'];
				
				$GetVouchersDetails = $redemptionObj->Get_voucher_details($Voucher_id,$Company_id);
				if($GetVouchersDetails != Null)
				{
					$name = $GetVouchersDetails['Voucher_name'];
					$description = $GetVouchersDetails['Voucher_description'];
					$Voucher_image = $GetVouchersDetails['Item_image1'];
				}
				else
				{
					$GetVouchersDetails2 = $redemptionObj->Get_voucher_details2($Offer_code,$Company_id);
					
					if($GetVouchersDetails2 !=Null){
						$name = $GetVouchersDetails2['Offer_name'];
						$description = $GetVouchersDetails2['Offer_description'];
						$Voucher_image = "no image";
					}
					else
					{
						$name = "Discount Voucher";
						$description = "Discount Voucher";
						$Voucher_image = "no image";
					}
				}
				$Voucher_Details[] = array("code"=>$row['Gift_card_id'],"type"=>$Voucher_Type,"currency"=>$Currency_symbol,"amount"=>number_format($Voucher_Amount,2),"validity"=>$row['Valid_till'],"name"=>$name,"description"=>$description,"image"=>$Voucher_image);		
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["voucherdata"] = $Voucher_Details;
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
$app->post('/getproductvouchers','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	$param['itemsdata'] = $request_array['itemsdata'];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$redemptionObj = new RedemptionHandler();
	
	$user = $redemptionObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
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
		if($param['itemsdata'] != NULL)
		{
			foreach($param['itemsdata'] as $row)
			{
				$Item_code=$row['code'];
				$Item_name=$row['name'];
				$Item_price=$row['price'];
				$Item_quantity=$row['quantity'];
				
				$ItemDetails = $redemptionObj->Get_item_details($Item_code,$Company_id);
				
				if($ItemDetails != NULL)
				{
					$ProductInPercentageVouchers = $redemptionObj->get_member_product_inprecentage_vouchers($Membership_ID,$Company_id,$Item_code,$Item_quantity);
					
					if($ProductInPercentageVouchers != NULL)
					{	
						$Voucher_Details = array();
						
						foreach($ProductInPercentageVouchers as $row1)
						{	
							$Voucher_Details1[] = array("code"=>$row1['Gift_card_id'],"amount"=>number_format($row1['Reduce_product_amt'],2),"validity"=>$row1['Valid_till']);	
						}
						
						$Voucher_Details[] = $Voucher_Details1;
					}
				}
				else
				{
					$response["errorcode"] = 3103;
					$response["message"] = "Invalid item code Or Item not exist.";
					$response["code"] = $Item_code;
					echoRespnse($response); 
					exit;
				}
			}
			if($Voucher_Details != null)
			{
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["voucherdata"] = $Voucher_Details;
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
			$response["errorcode"] = 3108;
			$response["message"] = "items data is blank";
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
$app->post('/validatevoucher','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	$param['itemsdata'] = $request_array['itemsdata'];
	$Voucher_details = $request_array['voucherdetails'];
	$Discount_voucher_code = $Voucher_details['code'];
	
	$Order_amount = $request_array['orderamount'];
	$Order_amount = str_replace( ',', '', $Order_amount);
	
	$Outlet_no = $request_array['outletno'];
	
	$ChannelCompanyId = 0;

	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$redemptionObj = new RedemptionHandler();
	$voucherObj = new VoucherHandler();
	$orderObj = new OrderHandler();
	$discountObj = new DiscountHandler();
	
	$user = $redemptionObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Cust_enrollement_id = $user['Enrollement_id'];
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
		}
		else
		{
			$response["errorcode"] = 2009;
			$response["message"] = "Invalid outlet no.";
			echoRespnse($response); 
			exit;
		}
		
		$Voucher_result = $voucherObj->Validate_discount_voucher($Membership_ID,$Company_id,$Discount_voucher_code);
		
		if($Voucher_result != Null)
		{
			$Gift_card_id = $Voucher_result['Gift_card_id'];
			$Card_value = $Voucher_result['Card_value'];
			$Card_balance = $Voucher_result['Card_balance'];
			$Valid_till = $Voucher_result['Valid_till'];
			$Card_Payment_Type_id = $Voucher_result['Payment_Type_id'];
			$Discount_percentage = $Voucher_result['Discount_percentage'];
			
			$Card_balance = str_replace( ',', '', $Card_balance);
			if($Card_Payment_Type_id == 997) //product voucher
			{
				$Cust_Item_Num = array();
				if($param['itemsdata'] != NULL)
				{
					foreach($param['itemsdata'] as $item)
					{ 
						$ItemCode = $item['code']; 
						
						$ItemDetails = $orderObj->Get_item_details($ItemCode,$Company_id);
						
						if($ItemDetails !=NULL)
						{
							$Merchandize_item_code = $ItemDetails['Company_merchandize_item_code'];
							$Item_name = $ItemDetails['Merchandize_item_name'];
							
							// $ItemCodeArr[$ItemCode]=$item['Item_Qty'];
							$CheckItemTempCart = $orderObj->GetItemsDetails($Company_id,$Cust_enrollement_id,$ItemCode,$Outlet_no,$ChannelCompanyId);
							if($CheckItemTempCart != Null)
							{
								$TempQty = $CheckItemTempCart['Item_qty'];
								
								// $TempCartData["Item_qty"] = $TempQty+$item['quantity'];
								
								$TempCartData = $TempQty+$item['quantity'];
								$update_cart = $orderObj->update_pos_temp_cart($TempCartData,$Company_id,$Cust_enrollement_id,$ItemCode,$Outlet_no,$ChannelCompanyId);
							}
							else
							{
								$data79['Company_id'] = $Company_id;
								$data79['Enrollment_id'] = $Cust_enrollement_id;
								$data79['Seller_id'] = $Outlet_no;
								$data79['Channel_id'] = $ChannelCompanyId;
								$data79['Item_code'] = $ItemCode;
								$data79['Item_qty'] = $item['quantity'];
								$data79['Item_price'] = str_replace( ',', '', $item['price']);
								
								$orderObj->insert_item($data79);
							}
							$Cust_Item_Num[] = $ItemCode;
						}
						else
						{
							$response["errorcode"] = 3103;
							$response["message"] = "Invalid code Or Item not exist.";
							echoRespnse($response); 
							exit;
						}
					}
					$GetItems= $voucherObj->Get_items($Company_id,$Cust_enrollement_id,$Outlet_no,$ChannelCompanyId);
					
					if($GetItems != Null)
					{
						foreach($GetItems as $row1)
						{
							$TempItemCode = $row1['Item_code'];
							$TempItemQty = $row1['Item_qty'];
							
							$ItemCodeArr[$TempItemCode]=$TempItemQty; 
						}
					}
				
					$lowest_sent_vouchers= $voucherObj->Get_lowest_sent_vouchers($Cust_enrollement_id,$Company_id,$Discount_voucher_code);
					
					if($lowest_sent_vouchers != NULL)
					{
						$RemQTY=0;
						$lv_Voucher_code=0;
						$lowest_flag=1;
						$newpricearr = array();
						foreach($lowest_sent_vouchers as $rec1)
						{
							if(($lowest_flag == 0) && ($lv_Voucher_code == $rec1['Voucher_code']))
							{
								$RemQTY=0;
								$lowest_flag=1;
								$newpricearr = array();
								break;
							}
							
							$Cart_item_QTY=$ItemCodeArr[$rec1['Company_merchandize_item_code']];
									
							if(array_key_exists($rec1['Company_merchandize_item_code'],$ItemCodeArr))
							{
								if($RemQTY!=0)//
								{
									if($Cart_item_QTY >= $RemQTY )
									{	
										$newpricearr[]=($RemQTY * $rec1['Voucher_Cost_price']);
										$Reduce_product_amt=array_sum($newpricearr);
										$ApllicableVoucher_code[]=$rec1['Voucher_code'];
										
										$data['Vouchers_price'][$rec1['Voucher_code']] = $Reduce_product_amt;
										
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
								}
								if($Cart_item_QTY >= $rec1['Voucher_Qty'] && $lowest_flag==1)
								{
									$Reduce_product_amt=($rec1['Voucher_Qty']*$rec1['Voucher_Cost_price']);//660
									$lowest_flag=0;
									$lv_Voucher_code=$rec1['Voucher_code'];
									$ApllicableVoucher_code[]=$rec1['Voucher_code'];
					
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
						
					$ReduceDiscountAmt = $data['Vouchers_price']["$Discount_voucher_code"];
					
					$delete_temp_cart = $orderObj->delete_pos_temp_cart_data($Company_id,$Cust_enrollement_id,$Outlet_no,$ChannelCompanyId);
				}
				else
				{
					$response["errorcode"] = 3108;
					$response["message"] = "items data is blank";
					echoRespnse($response); 
					exit;
				}
			}
			else if($Card_Payment_Type_id == 99 || $Card_Payment_Type_id == 998)
			{
				if($Card_balance > 0)
				{
					/****************12-7-2020****************/
					if($Discount_percentage > 0)
					{
						$Card_balance = (($Order_amount * $Discount_percentage)/100);
						$Card_balance = floor($Card_balance);
					}
					$Card_balance = str_replace( ',', '', $Card_balance);
					/****************12-7-2020****************/
					$Balance_due = $Order_amount - $Card_balance;
					if($Balance_due < 0)
					{
						$Balance_due = 0.00;
					}
					
					$response["errorcode"] = 1001;
					$response["message"] = "Successful";
					$response["membershipid"] = $Membership_ID;
					$response["membername"] = $Member_name;
					$response["orderamount"] = number_format($Order_amount,2);
					$response["vouchercode"] = $Discount_voucher_code;
					$response["voucheramount"] = number_format($Card_balance,2);
					echoRespnse($response); 
					exit;
				}
				else
				{
					$response["errorcode"] = 2069;
					$response["message"] = "Invalid Discount or Product Voucher";
					echoRespnse($response); 
					exit;
				}
			}
			else
			{
				$response["errorcode"] = 2069;
				$response["message"] = "Invalid Discount or Product Voucher";
				echoRespnse($response); 
				exit;
			}
			if($ReduceDiscountAmt > 0)
			{
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["membershipid"] = $Membership_ID;
				$response["membername"] = $Member_name;
				$response["orderamount"] = number_format($Order_amount,2);
				$response["vouchercode"] = $Discount_voucher_code;
				$response["voucheramount"] = number_format($ReduceDiscountAmt,2);
				echoRespnse($response); 
				exit;
			}
			else
			{
				$response["errorcode"] = 2069;
				$response["message"] = "Invalid Discount or Product Voucher";
				echoRespnse($response); 
				exit;
			}
		}
		else
		{
			$response["errorcode"] = 2069;
			$response["message"] = "Invalid Discount or Product Voucher";
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