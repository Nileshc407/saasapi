<?php
require_once '../include/DbHandler.php';
require_once '../include/RedemptionHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require_once '../include/SendEmailHandler.php';
require_once '../include/LogHandler.php';
require '.././libs/Slim/Slim.php';

use lib\Slim\Middleware\SessionCookie;
// session_start();
//error_reporting(0);
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
					$_SESSION["Block_points_flag"] = $result["Company_block_points_flag"];		
					
					$superSeller= $dbHandlerObj->superSellerDetails();
					
					$_SESSION["seller_id"] = $superSeller["id"];
					$_SESSION["seller_name"] = $superSeller["fname"].' '.$superSeller["lname"];
					$_SESSION["country"] = $superSeller["country"];
					$_SESSION["state"] = $superSeller["state"];
					$_SESSION["city"] = $superSeller["city"];
					$_SESSION["topup_Bill_no"] = $superSeller["topup_Bill_no"];
					$_SESSION["timezone_entry"] = $superSeller["timezone_entry"];
					
					// $timezone_entry = $superSeller["timezone_entry"];
					// $logtimezone = $timezone_entry;
					// $timezone = new DateTimeZone($logtimezone);
					$date = new DateTime();
					// $date->setTimezone($timezone);
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
$app->post('/getitems','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	// to get an array so try following..
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['filtervalue'] = strip_tags($request_array['filtervalue']);
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
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
		$Cust_Tier_id = $user['Tier_id'];
		
		if($param['filtervalue'] != Null)
		{
			$filtervalue = $param['filtervalue'];
			$Redemption_Items = $redemptionObj->get_all_items_filter($Company_id,$filtervalue,$Cust_Tier_id);
		}
		else
		{
			$Redemption_Items = $redemptionObj->get_all_items($Company_id,$Cust_Tier_id);
		}
		// var_dump($Redemption_Items); exit;
		if($Redemption_Items != NULL)
		{
			$Itemsdata= array();
			foreach($Redemption_Items as $row)
			{	
				$Items_details = array("code" => $row["Company_merchandize_item_code"], "name" =>$row["Merchandize_item_name"],"description" => $row["Merchandise_item_description"],"price" => $row["Billing_price"],"priceinpoints" => $row["Billing_price_in_points"],"imageurl" => $row["Item_image1"]);
				$Itemsdata[] =$Items_details;
										
			}
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["itemsdata"] = $Itemsdata;
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
	// exit;
});
$app->post('/redeemitems','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	// to get an array so try following..
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	// check for required params
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['itemsdata'] = $request_array['itemsdata'];
	
	
	// $param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$redemptionObj = new RedemptionHandler();
	$sendEObj = new SendEmailHandler();
	$logHObj = new LogHandler();
	
	$user = $redemptionObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$User_email_id = $user['User_email_id'];
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
		
		$param['timezone_entry']=$user['timezone_entry'];
		$logtimezone = $param['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');
		
		$SellerDetails=$redemptionObj->superSellerDetails();   
        $Super_Seller_id = $SellerDetails['id'];
        $Super_Seller_Name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
        $Seller_timezone_entry =$SellerDetails['timezone_entry'];
		$Purchase_Bill_no = $SellerDetails['Purchase_Bill_no'];
		$tp_db = $Purchase_Bill_no;
		$len = strlen($tp_db);
		$str = substr($tp_db,0,5);
		$bill = substr($tp_db,5,$len);
		
		$Delivery_method = 28; //Pick Up
		$Voucher_status = 30; //Issued
			if($param['itemsdata'] != NULL)
			{
				$billing_price_array = array();
				$price_in_points_array = array();
				$Itemsdetails = array();
				foreach($param['itemsdata'] as $row)
				{
					$Item_code=$row['code'];
					$Item_name=$row['name'];
					$Item_description=$row['description'];
					$Item_price=$row['price'];
					$Item_priceinpoints=$row['priceinpoints'];
					$Item_quantity=$row['quantity'];
					
					$ItemDetails = $redemptionObj->Get_item_details($Item_code,$Company_id);
					if($ItemDetails == NULL)
					{
						$response["errorcode"] = 3103;
						$response["message"] = "Invalid item code Or Item not exist.";
						$response["code"] = $Item_code;
						echoRespnse($response); 
						exit;
					}
					else
					{
						$Billing_price = $ItemDetails['Billing_price']*$Item_quantity;
						$item_price_in_points = $ItemDetails['Billing_price_in_points']*$Item_quantity;
						$billing_price_array[] = $Billing_price;
						$price_in_points_array[] = $item_price_in_points;
					}
				}
				
				$Total_items_price = array_sum($billing_price_array);
				$Total_items_price_in_points = array_sum($price_in_points_array);
				$Billing_amount = $Total_items_price;
				$Redeem_points = $Total_items_price_in_points;
				
				if($Available_point_balance < $Redeem_points)
				{
					$response["errorcode"] = 3101;
					$response["message"] = "Insufficient Current Balance";
					$response["currentbalance"] = $Available_point_balance;
					// $response["itemsprice"] = $Total_items_price;
					// $response["itemspriceinpoints"] = number_format($Total_items_price_in_points,2);
					
					echoRespnse($response); 
					exit;
				}
				foreach($param['itemsdata'] as $row)
				{
					$Item_code=$row['code'];
					$Item_quantity=$row['quantity'];
					
					$ItemDetails = $redemptionObj->Get_item_details($Item_code,$Company_id);
					if($ItemDetails != NULL)
					{
						$item_code = $ItemDetails['Company_merchandize_item_code'];
						$item_name = $ItemDetails['Merchandize_item_name'];
						$item_description = $ItemDetails['Merchandise_item_description'];
						$Partner_id = $ItemDetails['Partner_id'];
						$Cost_price = $ItemDetails['Cost_price'];
						$Cost_in_points = $ItemDetails['Cost_in_points'];
						$Billing_price = $ItemDetails['Billing_price'];
						$Billing_price_in_points = $ItemDetails['Billing_price_in_points'];
						$Cost_payable_to_partner = $ItemDetails['Cost_payable_to_partner']; 
						$VAT = $ItemDetails['VAT'];
						
						$ItemBranch = $redemptionObj->Get_item_partner_branch($Item_code,$Company_id,$Partner_id);
						if($ItemBranch !=NULL)
						{
							$Branch_code = $ItemBranch['Branch_code'];
						}
						else
						{
							$Branch_code = 0;
						}
						$characters = 'A123B56C89';
						$string = '';
						$Voucher_no="";
						for ($i = 0; $i < 10; $i++) 
						{
							$Voucher_no .= $characters[mt_rand(0, strlen($characters) - 1)];
						}
						
						$insert_data['Company_id']=$Company_id;
						$insert_data['Trans_type']=10;
						$insert_data['Redeem_points']=$Billing_price_in_points*$Item_quantity;
						$insert_data['Quantity']=$Item_quantity;
						$insert_data['Trans_date']=$lv_date_time;
						$insert_data['Update_date']=$lv_date_time;
						$insert_data['Remarks']= 'Redeem Merchandize Items by api';
						$insert_data['Seller']= $Super_Seller_id;
						$insert_data['Seller_name']= $Super_Seller_Name;
						$insert_data['Create_user_id']= $Enrollement_id;
						$insert_data['Enrollement_id']= $Enrollement_id;
						$insert_data['Card_id']= $Membership_ID;
						$insert_data['Item_code']= $item_code;
						$insert_data['Loyalty_pts']= 0.00;
						$insert_data['Online_payment_method']= "Points";
						$insert_data['Item_size']= null;
						$insert_data['Voucher_no']= $Voucher_no;
						$insert_data['Voucher_status']= $Voucher_status;
						$insert_data['Delivery_method']= $Delivery_method;
						$insert_data['Cost_payable_partner']= $Cost_payable_to_partner*$Item_quantity;
						$insert_data['Merchandize_Partner_id']= $Partner_id;
						$insert_data['Merchandize_Partner_branch']= $Branch_code;
						$insert_data['Bill_no']= $bill;
						$insert_data['Manual_billno']= $bill;
						$insert_data['Order_no']= $bill;
						$insert_data['Purchase_amount']= 0.00;
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
						$insert_data['Redeem_amount']= $Billing_price_in_points*$Item_quantity;
						$insert_data['Payment_type_id']= 0;
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
						$insert_data['Delivery_status']=null;
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

						
						$Insert_Redeem = $redemptionObj->Insert_Redeem_Items_at_Transaction($insert_data);
						
						$Items_details = array("code" => $item_code, "name" =>$item_name,"description" => $item_description,"quantity" => $Item_quantity,"points" => $Billing_price_in_points*$Item_quantity,"deliverymethod" => "Pick Up","voucherno" => $Voucher_no,"status" => "Issued"); 
						$Itemsdetails[] = $Items_details;
					}
				}
				
				if($Insert_Redeem == SUCCESS)
				{		
					$Enroll_details = $redemptionObj->get_enrollment_details($Enrollement_id);
					$Card_id=$Enroll_details['Card_id'];
					$Current_balance=$Enroll_details['Current_balance'];
					$Total_topup_amt=$Enroll_details['Total_topup_amt'];
					$Blocked_points =$Enroll_details['Blocked_points'];
					$Total_reddems =$Enroll_details['Total_reddems'];
					$First_name =$Enroll_details['First_name'];
					$Last_name =$Enroll_details['Last_name'];

					 $Total_Current_Balance = $Current_balance - $Redeem_points;
					 $Total_reddems = $Total_reddems + $Redeem_points;
					
					$MemberPara['Total_reddems'] = $Total_reddems;								
					$MemberPara['Current_balance'] = $Total_Current_Balance;	
					
					$update_balance=$redemptionObj->update_member_balance($MemberPara,$Enrollement_id);
					
					$bill_no = $bill + 1;
					$billno_withyear = $str.$bill_no;
					$BillPara['Purchase_Bill_no'] = $billno_withyear;		
					$result4 = $redemptionObj->updatePurchaseBillno($BillPara,$Super_Seller_id);
					
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
										<b>'.$_SESSION["Company_Currency"].'</b>
									</TH>	
						<TH style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
										<b>Voucher No.</b>
									</TH>																
						<TH style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
						<b>Voucher Status</b>
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
								'.$item["points"].'
							</TD>							
							<TD style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left> 
							'.$item['voucherno'].'
							</TD>
							<TD style="border: #dbdbdb 1px solid;PADDING-BOTTOM: 4px; PADDING-LEFT: 4px; PADDING-RIGHT: 4px; PADDING-TOP: 4px" align=left>
							'.$item['status'].'
							</TD>
						</TR>';
						$i++;
					}
					$html .='</tbody></TABLE></div>';
					
					$EmailParam["error"] = false;
					$EmailParam['Order_no'] = $bill;
					$EmailParam['Amount'] = number_format($Billing_amount, 2);
					$EmailParam['Redeem_points'] = $Redeem_points;
					$EmailParam['datatable'] = $html;
					$EmailParam['Email_template_id'] =3; 
					
					$email = $sendEObj->sendEmail($EmailParam,$Enrollement_id); 
				/*********************Insert Log*************************/
					$log_data['Company_id']=$Company_id;
					$log_data['From_enrollid']=$Enrollement_id;
					$log_data['From_emailid']=$User_email_id;
					$log_data['From_userid']=$User_id;
					$log_data['To_enrollid']=$Enrollement_id;
					$log_data['Transaction_by']=$Member_name;
					$log_data['Transaction_to']= $Member_name;
					$log_data['Transaction_type']= 'Redeemed Merchandise Item';
					$log_data['Transaction_from']= 'Redeem Item API';
					$log_data['Operation_type']= 1;
					$log_data['Operation_value']= 'Bill No-'.$bill.', Points-'.$Redeem_points; 
					$log_data['Date']= $Todays_date;
					
					$Log = $logHObj->insertLog($log_data);
				/**********************Insert Log*************************/	
				}
				if($Insert_Redeem == SUCCESS)
				{
					$response = array();
					$response["errorcode"] = 1001;
					$response["message"] = "Successful";
					
					$APILogParam['Company_id'] =$Company_id;
                    $APILogParam['Trans_type'] = 10;
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
				$response["errorcode"] = 3108;
				$response["message"] = "Items data is blank";
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
$app->post('/pointevaluation','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	$Block_points_flag = $_SESSION["Block_points_flag"];
	
	verifyRequiredParams(array('membershipid','points'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	$param['points'] = $request_array['points'];
		
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
		
		if($Available_point_balance < $param['points'])
		{
			$response["errorcode"] = 3101;
			$response["message"] = "Insufficient Current Balance";
			$response["currentbalance"] = $Available_point_balance;
			
			echoRespnse($response); 
			exit;
		}
		$Equivalent_amount = ($param['points']/$Redemption_ratio); 
		if($Equivalent_amount >= 1)
		{	
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["points"] = $param['points'];
			$response["pointsvalue"] = number_format($Equivalent_amount, 2);
		}
		else
		{
			$response["errorcode"] = 3104;
			$response["message"] = "Invalid points, Please provide valid points";
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
$app->get('/getdiscountvouchers','authenticate', function() use ($app) 
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
				}
				else if($row['Discount_percentage'] > 0)
				{
					$Voucher_Amount = $row['Discount_percentage'];
					$Voucher_Type ="Percentage";  // Percentage Voucher
				}
				
				$Voucher_Details[] = array("code"=>$row['Gift_card_id'],"type"=>$Voucher_Type,"amount"=>number_format($Voucher_Amount,2),"validity"=>$row['Valid_till']);		
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
$app->post('/pointsvalidation','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid','billamount','points'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	$Bill_amount = $request_array['billamount'];
	$Bill_amount = str_replace( ',', '', $Bill_amount);
	$Redeem_points = $request_array['points'];
	$Redeem_points = str_replace( ',', '', $Redeem_points);
	
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
		if($Available_point_balance < $Redeem_points)
		{
			$response["errorcode"] = 3101;
			$response["message"] = "Insufficient Current Balance";
			$response["currentbalance"] = $Available_point_balance;
			echoRespnse($response); 
			exit;
		}
		
		$Reddem_amount = Validate_redeem_points($Redeem_points,$Redemption_ratio,$Bill_amount);
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
			
			if($Points_amount >= 1)
			{	
				$response["errorcode"] = 1001;
				$response["message"] = "Successful";
				$response["billamount"] = number_format($Bill_amount, 2);
				$response["redeempoints"] = $Redeem_points;
				$response["pointsamount"] = number_format($Points_amount, 2);
			}
			else
			{
				$response["errorcode"] = 3104;
				$response["message"] = "Invalid points, Please provide valid points";
			}
			
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
$app->post('/blockpoints','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid','points','outletno','orderno'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	$param['points'] = $request_array['points'];
	$outlet_no = $request_array['outletno'];
	$order_no = $request_array['orderno'];
	
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
		if($Available_point_balance < $param['points'])
		{
			$response["errorcode"] = 3101;
			$response["message"] = "Insufficient Current Balance";
			$response["currentbalance"] = $Available_point_balance;
			
			echoRespnse($response); 
			exit;
		}
		$Outlet_details = $redemptionObj->get_outlet_details($outlet_no,$Company_id);
		
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
			
			$result01 = $redemptionObj->check_block_points_bill_no($order_no,$outlet_no,$Company_id,$lv_date_time);
				
			if($result01 > 0)
			{
				$response["errorcode"] = 2067;
				$response["message"] = "Order no. already exist.";
				echoRespnse($response); 
				exit;
			} 
			else
			{
				$Equivalent_amount = ($param['points']/$Redemption_ratio); 
			
				if($Equivalent_amount >= 1)
				{	
					$insert_data['Company_id']=$Company_id;
					$insert_data['Enrollment_id']=$Enrollement_id;
					$insert_data['Outlet_id']=$outlet_no;
					$insert_data['Order_no']=$order_no;
					$insert_data['Points']= $param['points'];
					$insert_data['Points_value']=$Equivalent_amount;
					$insert_data['Status']= 0;	
					$insert_data['Status_dec']= "Block Points";	
					$insert_data['Create_user_id']=$outlet_no;
	
					$Insert_block = $redemptionObj->Insert_block_points($insert_data);
					
					if($Insert_block == SUCCESS)
					{
						$Total_blaock_Points = $Blocked_points + $param['points'];
					
						$upData['Blocked_points']=$Total_blaock_Points;
						$valData['Enrollement_id']=$Enrollement_id;
						$valData['Company_id']=$Company_id;								
						
						$updateData = $dbHandlerObj->updateData($upData,'igain_enrollment_master',$valData);
						
						$response["errorcode"] = 1001;
						$response["message"] = "Successful";
						$response["membershipid"] = $Membership_ID;
						$response["membername"] = $Member_name;
						$response["orderno"] = $order_no;
						$response["points"] = $param['points'];
						$response["pointsvalue"] = number_format($Equivalent_amount, 2);
						$response["status"] = "Block";
					}
					else
					{
						$response = array();
						$response["errorcode"] = 2068;
						$response["message"] = "Unsuccessful";
						echoRespnse($response); 
						exit;
					}
				}
				else
				{
					$response["errorcode"] = 3104;
					$response["message"] = "Invalid points";
					echoRespnse($response); 
					exit;
				}
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
	}
	echoRespnse($response); 
	// exit;
});
$app->post('/unblockpoints','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Redemption_ratio = $_SESSION["Company_Redemptionratio"];
	
	verifyRequiredParams(array('membershipid','points','outletno','orderno'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	$param['points'] = $request_array['points'];
	$outlet_no = $request_array['outletno'];
	$order_no = $request_array['orderno'];
	
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
		
		$param['timezone_entry']=$user['timezone_entry'];
		$logtimezone = $param['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');
			
		if($Blocked_points < $param['points'])
		{
			$response["errorcode"] = 3102;
			$response["message"] = "Insufficient block points to release!";
			$response["blockpoints"] = $Blocked_points;
			
			echoRespnse($response); 
			exit;
		}
		$Outlet_details = $redemptionObj->get_outlet_details($outlet_no,$Company_id);
		
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
			
			$result02 = $redemptionObj->get_block_points_details($order_no,$outlet_no,$Company_id,$Enrollement_id,$param['points'],$lv_date_time);
				
			if($result02 != Null)
			{
				$redeem_points = str_replace( ',', '', $param['points']);	
				
				$Block_points = $result02['Points'];
					
				$upblockData['Status']=2;
				$upblockData['Status_dec']="Release Points";
				$upblockData['Update_user_id']=$seller_id;
				$upblockData['Update_date']=$lv_date_time;
				
				$valblockData['Company_id']=$Company_id;		
				$valblockData['Enrollment_id']=$Enrollement_id;
				$valblockData['Outlet_id']=$seller_id;
				$valblockData['Order_no']=$order_no;						
				$valblockData['Points']= $redeem_points;						
				
				$updateblockData = $dbHandlerObj->update_block_points_status($upblockData,'igain_block_points',$valblockData);	
				
				$Total_blaock_Points = $Blocked_points - $param['points'];
				
				$upData['Blocked_points']=$Total_blaock_Points;
				$valData['Enrollement_id']=$Enrollement_id;
				$valData['Company_id']=$Company_id;								
				
				$updateData = $dbHandlerObj->updateData($upData,'igain_enrollment_master',$valData);
				
				$Enroll_details = $dbHandlerObj->get_enrollment_details($Enrollement_id,$Company_id);
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
				$response["membershipid"] = $Membership_ID;
				$response["membername"] = $Member_name;
				$response["orderno"] = $order_no;
				$response["points"] = $param['points'];
				$response["status"] = "Released";
				$response["currentbalance"] = $Current_point_balance;
			} 
			else
			{
				$response["errorcode"] = 3105;
				$response["message"] = "Points doesn't match with order no. or points has been already released or used!";
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