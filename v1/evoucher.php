<?php
require_once '../include/DbHandler.php';
require_once '../include/EvoucherHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require_once '../include/SendEmailHandler.php';
require '.././libs/Slim/Slim.php';

use lib\Slim\Middleware\SessionCookie;
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


	$phash = new PassHash();

	function authenticate(\Slim\Route $route)
	{	
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
	
		if ($api_key !=Null)
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
			
				$comp = new EvoucherHandler();
				$result = $comp->getCompanyDetails($api_key);
				
				if ($result != NULL) 
				{
					$_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];			
					$_SESSION["phonecode"] = $result["phonecode"];							
					$_SESSION["Company_Redemptionratio"] = $result["Redemptionratio"];		
					$_SESSION["Company_Currency"] = $result["Currency_name"];		
					$_SESSION["Block_points_flag"] = $result["Company_block_points_flag"];		
					$_SESSION["Symbol_of_currency"] = $result["Symbol_of_currency"];
					$_SESSION["Gifting_enviornment_flag"] = $result["Gifting_enviornment_flag"];		
					$_SESSION["Gift_payment_balance"] = $result["Gift_payment_balance"];		
					$_SESSION["Gift_point_balance"] = $result["Gift_point_balance"];		
										
					
					$superSeller= $dbHandlerObj->superSellerDetails();
					
					$_SESSION["seller_id"] = $superSeller["id"];
					$_SESSION["seller_name"] = $superSeller["fname"].' '.$superSeller["lname"];
					$_SESSION["country"] = $superSeller["country"];
					$_SESSION["state"] = $superSeller["state"];
					$_SESSION["city"] = $superSeller["city"];
					$_SESSION["topup_Bill_no"] = $superSeller["topup_Bill_no"];
					$_SESSION["timezone_entry"] = $superSeller["timezone_entry"];
					
					
					$date = new DateTime();
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
			$response["error"] = true;
			$response["message"] = "Api key is misssing";
			echoRespnse($response);
			$app->stop();
		}
	}
$app->post('/getitems','authenticate', function() use ($app) 
{   

	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	
	$Gifting_enviornment_flag = $_SESSION["Gifting_enviornment_flag"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['voucher_category'] = $request_array['category'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$evoucherObj = new EvoucherHandler();

	$user = $evoucherObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
				
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		if($Gifting_enviornment_flag == 0)
		{
			$type = 1; //demo
		}
		else if($Gifting_enviornment_flag == 1)
		{
			$type = 2; //live
		}
	
		$Configuration = $evoucherObj->Fetch_thirdparty_evouchar_configuration_details($type);
		if($Configuration != NULL) 
		{
			$url_to_check = $Configuration['url'];
			$token = $Configuration['token'];

			
			$postarray= array(); 
			$Symbol_of_currency = $_SESSION['Symbol_of_currency'];
			if($param['voucher_category'] != Null)
			{
				$Merchandize_category_id = $param['voucher_category'];
			}
			else
			{
				$Merchandize_category_id = "";
			}
						
			$currencyCodearray = array('key'=>'currencyCode','value'=>$Symbol_of_currency);
			$codearray= array('key'=>'type','value'=>"code");
			$categoryarray= array('key'=>'voucher_category','value'=>$Merchandize_category_id);
			$deliveryarray= array('key'=>'deliveryType','value'=>'realtime');

			$filtersarray= array($currencyCodearray,$codearray,$categoryarray,$deliveryarray); 
			$variablesarray["data"]= array('limit'=>'0','page'=>'0','includeProducts'=>'','excludeProducts'=>'','filters'=>$filtersarray);  
			$postarray = array('query' =>'plumProAPI.mutation.getVouchers', 'tag' =>'plumProAPI', 'variables' =>$variablesarray);
			$input = json_encode($postarray);
			$curl = curl_init();				
			curl_setopt_array($curl,array(
					CURLOPT_URL => $url_to_check,
					CURLOPT_RETURNTRANSFER =>true,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_POSTFIELDS => $input,
					CURLOPT_HTTPHEADER => array('Authorization: Bearer '.$token.'',
					'Content-Type: application/json'),
					));
			$result = curl_exec($curl);
			curl_close($curl);
			$result = json_decode($result,true);

			if($result != NULL)
			{
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["itemsdata"] = $result;
			}
			else
			{
				$response["errorcode"] = 2037;
				$response["message"] = "Items not exist";
			}
		}
		else
		{
			$response["errorcode"] = 2037;
			$response["message"] = "Items not exist";
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
});
$app->post('/getcategory','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Gifting_enviornment_flag = $_SESSION["Gifting_enviornment_flag"];
		
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$evoucherObj = new EvoucherHandler();
	
	$user = $evoucherObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		if($Gifting_enviornment_flag == 0)
		{
			$type = 1; //demo
		}
		else if($Gifting_enviornment_flag == 1)
		{
			$type = 2; //live
		}
		$Configuration = $evoucherObj->Fetch_thirdparty_evouchar_configuration_details($type);
		if($Configuration != NULL) 
		{
			$url_to_check = $Configuration['url'];
			$token = $Configuration['token'];

			$curl = curl_init();
			$postarray= array(); 
			$Symbol_of_currency = $_SESSION['Symbol_of_currency'];			
						
			curl_setopt_array($curl,array(
					CURLOPT_URL => $url_to_check,
					CURLOPT_RETURNTRANSFER =>true,
					CURLOPT_CUSTOMREQUEST => 'POST',
					CURLOPT_POSTFIELDS => '{
								"query": "plumProAPI.mutation.getFilters",
								"tag": "plumProAPI",
								"variables": {
									"data":{
										"filterGroupCode": "",
										"includeFilters": "",
										"excludeFilters": ""
									}
								}
							}',
					CURLOPT_HTTPHEADER => array('Authorization: Bearer '.$token.'',
					'Content-Type: application/json'),
					));
			$result = curl_exec($curl);
			curl_close($curl);
			$result = json_decode($result,true);

			if($result != NULL)
			{
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["categorydata"] = $result;
			}
			else
			{
				$response["errorcode"] = 2037;
				$response["message"] = "No data found";
			}
		}
		else
		{
			$response["errorcode"] = 2037;
			$response["message"] = "No data found";
		}
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
});
$app->post('/redeemitems','authenticate', function() use ($app) 
{  
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Gifting_enviornment_flag = $_SESSION["Gifting_enviornment_flag"];
	$Gift_payment_balance = $_SESSION["Gift_payment_balance"];
	$Gift_point_balance = $_SESSION["Gift_point_balance"];
	$Redemptionratio = $_SESSION["Company_Redemptionratio"];
		
	verifyRequiredParams(array('membershipid','productid','quantity','price'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['productid'] = $request_array['productid'];
	$param['quantity'] = $request_array['quantity'];
	$param['Price'] = $request_array['price'];
	
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
	$phash = new PassHash();
	$sendEObj = new SendEmailHandler();
	$evoucherObj = new EvoucherHandler();
	
	$user = $evoucherObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
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
		$Tier_redemption_ratio = $user['Tier_redemption_ratio'];
	
		$User_email_id = $phash->string_decrypt($user['User_email_id']);
		$contact = $phash->string_decrypt($user['Phone_no']);
	
		$cnt=strlen($_SESSION["phonecode"]);
		$phone=substr($contact,$cnt);
		$contact1='+'.$_SESSION["phonecode"].'-'.$phone;

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
		
		$response = array();
		
		$SellerDetails=$evoucherObj->superSellerDetails();   
        $Super_Seller_id = $SellerDetails['id'];
        $Seller_id = $SellerDetails['id'];
        $Seller_name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
        $Seller_timezone_entry =$SellerDetails['timezone_entry'];
		$Purchase_Bill_no = $SellerDetails['Purchase_Bill_no'];
		$tp_db = $Purchase_Bill_no;
		$len = strlen($tp_db);
		$str = substr($tp_db,0,5);
		$bill = substr($tp_db,5,$len);
		
		$characters = 'A123B56C89';
		$Voucher_no="";
		for ($i = 0; $i < 16; $i++) 
		{
			$Voucher_no .= $characters[mt_rand(0, strlen($characters) - 1)];
		}
		
		$characters = 'A123B56C89';
		$Orderid="";
		for ($i = 0; $i <8; $i++) 
		{
			$Orderid .= $characters[mt_rand(0, strlen($characters) - 1)];
		}
		
		if($Gifting_enviornment_flag == 0)
		{
			$type = 1; //demo
		}
		else if($Gifting_enviornment_flag == 1)
		{
			$type = 2; //live
		}
			
		$Configuration = $evoucherObj->Fetch_thirdparty_evouchar_configuration_details($type);
		
		if($Configuration != NULL) 
		{
			$voucher_id=strip_tags($param['productid']);
			
			$url = $Configuration['url'];
			$token = $Configuration['token'];
			
			$temp_items_details=false;
			$postarray= array(); 
	
			$Symbol_of_currency = $_SESSION['Symbol_of_currency'];			
						
			$currencyCodearray = array('key'=>'currencyCode','value'=>$Symbol_of_currency);
			$codearray= array('key'=>'type','value'=>"code");				
			$deliveryarray= array('key'=>'deliveryType','value'=>'realtime');					
			$filtersarray= array($currencyCodearray,$codearray,$deliveryarray);  
			$variablesarray["data"]= array('limit'=>'1','page'=>'1','includeProducts'=>$voucher_id,'excludeProducts'=>'','filters'=>$filtersarray);  
			$postarray = array('query' =>'plumProAPI.mutation.getVouchers', 'tag' =>'plumProAPI', 'variables' =>$variablesarray);
			$arr1 = json_encode($postarray);				
			
			$curl1 = curl_init();
			curl_setopt_array($curl1, array(
			  CURLOPT_URL =>$url,
			  CURLOPT_RETURNTRANSFER => true,
			  CURLOPT_ENCODING => '',
			  CURLOPT_MAXREDIRS => 10,
			  CURLOPT_TIMEOUT => 0,
			  CURLOPT_FOLLOWLOCATION => true,
			  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  CURLOPT_CUSTOMREQUEST => 'POST',
			  CURLOPT_POSTFIELDS =>$arr1,
			  CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer '.$token.'',
				'Content-Type: application/json',
			  ),
			));
			$Validateresponse = curl_exec($curl1);
			$Validateresponse=json_decode($Validateresponse, true);	
			curl_close($curl1);
		
			$responseCode = $Validateresponse['code'];
			
			if($responseCode != 404)
			{
				$valueDenominations=array();
								
				if($Validateresponse['data']['getVouchers']['data'][0])
				{
					$product_name=$Validateresponse['data']['getVouchers']['data'][0]['name'];
					$product_imageUrl=$Validateresponse['data']['getVouchers']['data'][0]['imageUrl'];
					$productId=$Validateresponse['data']['getVouchers']['data'][0]['productId'];
					$valueDenominations=explode(",",$Validateresponse['data']['getVouchers']['data'][0]['valueDenominations']);
				} 
				else 
				{
					$productId=0;
				}
				if(in_array($param['Price'],$valueDenominations))
				{			
					$temp_items_details=true;
				} 
				else 
				{	
					$temp_items_details=false;
				}
				
				if($temp_items_details==true)
				{
					$Purchase_amount = $param['Price']*$param['quantity'];
					
					if($param['productid'] == $productId && $param['productid'] != 0 && $param['Price'] != 0 && $Purchase_amount > 0)
					{
						$Total_points =  $Purchase_amount * $Redemptionratio;
						if($Tier_redemption_ratio > 0)
						{
							$Billing_price_in_points_tier = $Total_points * $Tier_redemption_ratio;
							
							if($Total_points != $Billing_price_in_points_tier)
							{
								$Total_points = $Billing_price_in_points_tier;
								$Redemptionratio = $Tier_redemption_ratio;
							}
						}
						$Current_redeem_points = $Total_points;
						
						$Total_balance = $Available_point_balance;
						
						if($Current_redeem_points<=$Total_balance)
						{
							if($Gift_payment_balance >= $Purchase_amount)
							{
								$postarray= array();  
								$poNumber1=time();
								$poNumber='po'.$poNumber1.'-'.$bill;
								$productId=$voucher_id;
								$quantity=$param['quantity'];
								$denomination=$param['Price'];
								$email=$User_email_id;
								
								$variablesarray["data"]= array('productId'=>$productId,'quantity'=>$quantity,'denomination'=>$denomination,'email'=>$email,'contact'=>$contact1,'tag'=>"",'poNumber'=>$poNumber,'notifyAdminEmail'=>1);
								
								$arr1 = array('query' =>'plumProAPI.mutation.placeOrder', 'tag' =>'plumProAPI', 'variables' =>$variablesarray);
								$postarray = json_encode($arr1);
								
								$data['voucher_response']['code']='';
								
								$curl = curl_init();
								curl_setopt_array($curl, array(
								CURLOPT_URL =>$url,
								  CURLOPT_RETURNTRANSFER => true,
								  CURLOPT_ENCODING => '', 
								  CURLOPT_MAXREDIRS => 10,
								  CURLOPT_TIMEOUT => 0,
								  CURLOPT_FOLLOWLOCATION => true,
								  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
								  CURLOPT_CUSTOMREQUEST => 'POST',
								  CURLOPT_POSTFIELDS =>$postarray,
								  CURLOPT_HTTPHEADER => array(
									'Authorization: Bearer '.$token.'',
									'Content-Type: application/json'
								  ),
								));

								$orderresponse = curl_exec($curl);

								curl_close($curl);
							
								$voucher_codes=array();
								$pin_codes=array();
								$Gift_pointsArray=array();
								$Gift_paymentArray=array();
								
								$voucher_response=json_decode($orderresponse, true);
								
								$errorCode = $voucher_response['code'];
								
								if($errorCode != Null)
								{
									$response["errorcode"] = 2068;
									$response["message"] = "Unable to process $product_name. Please try again";
									$response["code"] = $voucher_response['code'];
									$response["errorId"] = $voucher_response['errorId'];
									$response["errorInfo"] = $voucher_response['errorInfo'];
									$response["error"] = $voucher_response['error'];
									echoRespnse($response);
									exit;
								}
								
								$insertPOData["Company_id"]=$Company_id;
								$insertPOData["Enrollement_id"] = $Enrollement_id;
								$insertPOData["Pid"]=$productId;
								$insertPOData["Product_name"]=$product_name;
								$insertPOData["Total_points"]=$Current_redeem_points;
								$insertPOData["Purchase_amount"]=$Purchase_amount;
								$insertPOData["Quantity"]=$quantity;
								$insertPOData["Bill_no"]=$bill;
								$insertPOData["Card_id"]=$Membership_ID;
								$insertPOData["Response"]=json_encode($orderresponse);
								$insertPOData["Creacted_date"]=$lv_date_time;
								$insertPOData["Status"]=0;
								$insertPOData["PO_number"]=$poNumber; 
								
							
								$resultplaceOrder = $evoucherObj->Insert_eVouchar_placeOrder_response($insertPOData);
								
								$ePartner = $evoucherObj->geteVoucherPartner($Company_id);
								if($ePartner != Null)
								{
									$ePartner_id = $ePartner['Partner_id'];
									$eBranch_code = $ePartner['Branch_code'];
								}
								else
								{
									$ePartner_id = 0;
									$eBranch_code = 0;
								}
								
								$Gift_payment = $voucher_response['data']['placeOrder']['data']['orderTotal'];
								
								$orderStatus = $voucher_response['data']['placeOrder']['data']['orderStatus'];
								
								$deliveryStatus = $voucher_response['data']['placeOrder']['data']['deliveryStatus'];
								
								$voucherquantity = $voucher_response['data']['placeOrder']['data']['quantity'];
								
								$Gift_points = $Gift_payment*$Redemptionratio;
									
								$vouchers = $voucher_response['data']['placeOrder']['data']['vouchers'];
								
								foreach ($vouchers as $voucher) 
								{
									$voucher_codes[] = $voucher['voucherCode'];
									$pin_codes[] = $voucher['pin'];
									$voucher_points = $voucher['amount']*$Redemptionratio;
									$Redeem_amount = $voucher_points/$Redemptionratio;
									
									$insert_data['Company_id']=$Company_id;
									$insert_data['Trans_type']=10;
									$insert_data['Redeem_points']= $voucher_points;
									$insert_data['Redeem_amount']= $Redeem_amount;
									$insert_data['Quantity']=1;
									$insert_data['Trans_date']=$lv_date_time;
									$insert_data['Update_date']=$lv_date_time;
									$insert_data['Remarks']= 'Gift Voucher Redeemed from app through saas api';
									$insert_data['Seller']= $Seller_id;
									$insert_data['Seller_name']= $Seller_name;
									$insert_data['Create_user_id']= $Enrollement_id;
									$insert_data['Enrollement_id']= $Enrollement_id;
									$insert_data['Card_id']= $Membership_ID;
									$insert_data['Item_code']= $param['productid'];
									$insert_data['Item_name']= $product_name;
									$insert_data['Loyalty_pts']= 0.00;
									$insert_data['Online_payment_method']= "Points"; 
									$insert_data['Item_size']= null;
									$insert_data['Voucher_no']= $voucher['voucherCode'];
									$insert_data['Voucher_status']= 296;
									$insert_data['Delivery_method']= 29;
									$insert_data['Cost_payable_partner']= $voucher['amount'];
									$insert_data['Merchandize_Partner_id']= $ePartner_id;
									$insert_data['Merchandize_Partner_branch']= $eBranch_code;
									$insert_data['Bill_no']= $bill;
									$insert_data['Manual_billno']= $poNumber;
									$insert_data['Order_no']= $bill;
									$insert_data['Purchase_amount']= $voucher['amount'];
									$insert_data['Paid_amount']= 0.00;
									$insert_data['Trans_amount']= 0.00;
									$insert_data['Topup_amount']= 0.00;
									$insert_data['Mpesa_Paid_Amount']= 0.00;
									$insert_data['COD_Amount']= 0.00;
									$insert_data['Mpesa_TransID']= null;
									$insert_data['Update_User_id']= 0;
									$insert_data['Transfer_points']= 0.00;
									$insert_data['Coalition_Loyalty_pts']= 0.00;
									$insert_data['Expired_points']= 0;
									$insert_data['Item_sales_tax']= 0.00;
									$insert_data['Payment_type_id']= 4;
									$insert_data['balance_to_pay']= 0.00;
									$insert_data['Shipping_cost']= 0.00;
									$insert_data['Shipping_points']= 0;
									$insert_data['Bill_discount']= 0.00;
									$insert_data['Pos_discount']= 0.00;
									$insert_data['Total_discount']= 0.00;
									$insert_data['Voucher_discount']= 0.00;
									$insert_data['GiftCardNo']= null;
									$insert_data['Channel_id']= 0;
									$insert_data['Item_category_discount']= 0.00;
									$insert_data['BillRefNumber']= 0;
									$insert_data['Table_no']= null;
									$insert_data['Send_miles_flag']= 0;
									$insert_data['Seller_Billing_Bill_no']= 0.00;
									$insert_data['Billing_Bill_flag']= 0;
									$insert_data['Settlement_flag']=0;
									$insert_data['Reference_id']=0;
									$insert_data['Free_item_onquantity_flag']=0;
									$insert_data['Customer_email']=null;
									$insert_data['Customer_name']=null;
									$insert_data['Invoice_no']=null;
									$insert_data['To_Beneficiary_company_name']=null;
									$insert_data['To_Beneficiary_cust_name']=null;
									$insert_data['From_Beneficiary_company_name']=null;
									$insert_data['From_Beneficiary_cust_name']=null;
									$insert_data['Card_id2']=null;
									$insert_data['Delivery_status']="Delivered";
									$insert_data['remark2']=null;
									$insert_data['remark3']=null;
									$insert_data['Flatfile_remarks']=null;
									$insert_data['Credit_Cheque_number']=null;
									$insert_data['Bank_name']=null;
									$insert_data['Branch_name']=null;
									$insert_data['From_Beneficiary_company_id']=0;
									$insert_data['Customer_phone']=0;
									$insert_data['Shipping_partner_id']=0;
									$insert_data['Shipping_payment_flag']=0;
									$insert_data['Payment_to_partner_flag']=0;
									$insert_data['Quantity_balance']=0;
									$insert_data['To_Beneficiary_company_id']=0;
									$insert_data['Enrollement_id2']=0;
									$insert_data['Loyalty_id']=0;
									$insert_data['Source']=0;
									$insert_data['report_status']=0;
									$insert_data['Free_item_quantity']=0;
									$insert_data['purchase_category']=0;
									
									$result = $evoucherObj->Insert_Redeem_Items_at_Transaction($insert_data); 	
									$res = 1;
								}
								if($res == 1)
								{
									$resultplaceOrder = $evoucherObj->update_eVouchar_placeOrder_response($Company_id,$productId,$poNumber);
									
									$Used_Gift_payment=$Gift_payment;
									$Used_Gift_points=$Gift_points;	
								
									$Gift_payment_balance=$Gift_payment_balance-$Used_Gift_payment;
									$Gift_point_balance=$Gift_point_balance-$Used_Gift_points;
									
									$company_giftbalance = $evoucherObj->update_company_giftbalance($Gift_payment_balance,$Gift_point_balance,$Company_id);
								
									$bill_no = $bill + 1;
									$billno_withyear = $str.$bill_no;
									$result4 = $evoucherObj->updatePurchaseBillno($billno_withyear,$Seller_id);
										
									$Enroll_details = $evoucherObj->get_enrollment_details($Enrollement_id);
									$lv_Total_reddems=$Enroll_details['Total_reddems']+$Current_redeem_points;
									$lv_Current_balance=$Enroll_details['Current_balance'];
									$lv_Blocked_points=$Enroll_details['Blocked_points'];
									$lv_Debit_points=$Enroll_details['Debit_points'];
										
									$Calc_Current_balance=$lv_Current_balance-$Current_redeem_points;
									
									$Avialable_balance=$Calc_Current_balance-($lv_Blocked_points+$lv_Debit_points);		
									
									$MemberPara['Total_reddems'] = $lv_Total_reddems;								
									$MemberPara['Current_balance'] = $Calc_Current_balance;
									
									$Update = $evoucherObj->update_member_balance($MemberPara,$Enrollement_id);
								/////////////////////////////////////////////////
									$EmailParam["error"] = false;
									$EmailParam['Voucher_name'] = $product_name;
									$EmailParam['Transaction_date'] = $lv_date_time;
									$EmailParam['Redeem_points'] = $Current_redeem_points;
									$EmailParam['Purchase_amount'] = $Purchase_amount;
									$EmailParam['Quantity'] = $quantity;
									$EmailParam['product_name'] = $product_name;
									$EmailParam['product_image'] = $product_imageUrl;
									$EmailParam['Symbol_of_currency'] = $Symbol_of_currency;
									$EmailParam['Voucher_no'] = $voucher_codes;
									$EmailParam['Pin_codes'] = $pin_codes;
									$EmailParam['Current_balance'] = $Avialable_balance;
									$EmailParam['Notification_type'] ='Evoucher Redeem';
									$EmailParam['Template_type'] = 'Evoucher_redemption';
									$EmailParam['Email_template_id'] =33;
									
									$email = $sendEObj->sendEmail($EmailParam,$Enrollement_id); 
								/////////////////////////////////////////////////
									$response["errorcode"] = 1001;
									$response["message"] = "Successful";
									$response["name"] = $product_name;
									$response["imageurl"] = $product_imageUrl;
									$response["quantity"] = $quantity;
									$response["currency"] = $Symbol_of_currency;
									$response["amount"] = $Purchase_amount;
									$response["points"] = $Current_redeem_points;
									$response["vouchercodes"] = $voucher_codes;
									$response["pincodes"] = $pin_codes;
									$response["pointbalance"] = $Avialable_balance;
									echoRespnse($response);
									exit;
								}
								else    
								{
									$response["errorcode"] = 2068;
									$response["message"] = "Unable to process $product_name. Please try again";
									echoRespnse($response);
									exit;
								} 
							
							}
							else
							{
								$response["errorcode"] = 3102;
								$response["message"] = "Company work in progress... Will be up soon... Sorry for the inconvenience";
								echoRespnse($response);
								exit;
							}
						}
						else
						{
							$response["errorcode"] = 3101;
							$response["message"] = "Insufficient Current Balance";
							$response["pointbalance"] = $Total_balance;
							echoRespnse($response);
							exit;
						} 
					}
				}
				else
				{
					$response["errorcode"] = 3104;
					$response["message"] = "Invalid product price Or product not available of given price";
					echoRespnse($response);
					exit;
				}
			}
			else
			{
				$response["errorcode"] = 3103;
				$response["message"] = "Invalid product id Or product not available";
				echoRespnse($response);
				exit;
			}
		}
		else
		{
			$response["errorcode"] = 2037;
			$response["message"] = "eVoucher module not available";
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
});
function verifyRequiredParams($required_fields,$request_array) {
    $error = false;
    $error_fields = "";
    $request_params = array();
  
    $request_params = $request_array;
  
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
    $app->contentType('application/json');

    echo json_encode($response);
}
$app->run();
?>