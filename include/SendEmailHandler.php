<?php
error_reporting(0);
/* Mail Functionality */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

/* Mail Functionality */

class SendEmailHandler {
    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() {

        require_once dirname(__FILE__) . '/DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
		require_once dirname(__FILE__) . '/DbHandler.php';
		$this->dbHobj = new DbHandler();
		require_once dirname(__FILE__) . '/NotificationHandler.php';
		$this->notiHobj = new NotificationHandler();
			
    }
	public function sendEmail($param,$Member_id)
	{  			
		$TemplateDetails=$this->dbHobj->fetchEmailTemplate($param['Email_template_id']);
		$MemberDetails=$this->dbHobj->fetchEnrollmentDetails($Member_id);
		
		while ($Member = $MemberDetails->fetch_assoc()) {
								
			$Customer_id = $Member["Enrollement_id"];
			$First_name = $Member["First_name"];
			$Last_name = $Member["Last_name"];
			$Customer_name = $Member["First_name"].' '.$Member["Last_name"];
			$Current_address = $Member["Current_address"];
			$State = $Member["State"];
			$City = $Member["City"];
			$Country = $Member["Country"];				
			$User_email_id  = $this->phash->string_decrypt($Member["User_email_id"]);
			$User_pwd = $this->phash->string_decrypt($Member["User_pwd"]);
			$Phone_no = $this->phash->string_decrypt($Member["Phone_no"]);
			$pinno = $Member["pinno"];
			$Tier_id = $Member["Tier_id"];
			$Current_balance = $Member["Current_balance"]-($Member["Blocked_points"]+$Member["Debit_points"]);
			$Blocked_points = $Member["Blocked_points"];
			$Debit_points = $Member["Debit_points"];
			$Tier_id = $Member["Tier_id"];
			$Card_id = $Member["Card_id"];
			$total_purchase = $Member["total_purchase"];
			$Total_topup_amt = $Member["Total_topup_amt"];
			$Total_reddems = $Member["Total_reddems"];
			$Fcm_token = $Member["Fcm_token"];
		}
		
		$TierDetails=$this->dbHobj->getTierName($Tier_id);
		$New_Tier_name =$TierDetails['Tier_name'];
		
		$CompanyDetails=$this->dbHobj->getCompanyDetails();
		
		while ($Comp = $CompanyDetails->fetch_assoc()) {
								
			$Company_id = $Comp["Company_id"];
			$Company_name = $Comp["Company_name"];
			$Alise_name = $Comp["Alise_name"];
			$Company_address = $Comp["Company_address"];
			$Company_primary_contact_person = $Comp["Company_primary_contact_person"];
			$Company_primary_email_id = $Comp["Company_primary_email_id"];
			$Company_contactus_email_id = $Comp["Company_contactus_email_id"];
			$Website = $Comp["Website"];
			$Cust_website = $Comp["Cust_website"];
			$Cust_ios_link = $Comp["Cust_ios_link"];
			$Cust_apk_link = $Comp["Cust_apk_link"];
			$Facebook_link = $Comp["Facebook_link"];
			$Twitter_link = $Comp["Twitter_link"];
			$Googlplus_link = $Comp["Googlplus_link"];
			$Linkedin_link = $Comp["Linkedin_link"];
			$Notification_send_to_email = $Comp["Notification_send_to_email"];
			$Company_Currency = $Comp["Currency_name"];
			$Domain_name = $Comp["Domain_name"];
			$Fcm_access_token = $Comp["Fcm_access_token"];
		}

		$SellerDetails=$this->dbHobj->superSellerDetails();
		
		$Super_Seller_id = $SellerDetails['id'];
		$Super_Seller_Name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
		$Seller_timezone_entry =$SellerDetails['timezone_entry'];

		$logtimezone = $SellerDetails['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('d M Y');
		$Transaction_date = $Todays_date;

		ob_start();	
		
		$Email_header_image = $TemplateDetails["Email_header_image"];
		$Template_description = $TemplateDetails["Template_description"];
		$Email_subject = $TemplateDetails["Email_subject"];
		$Body_structure = $TemplateDetails["Body_structure"];
		$Email_body = $TemplateDetails["Email_body"];
		$Footer_notes = $TemplateDetails["Footer_notes"];
		$Email_header = $TemplateDetails["Email_header"];
		$Body_image = $TemplateDetails["Body_image"];
		$Email_font_size = $TemplateDetails["Email_font_size"];
		$Font_family = $TemplateDetails["Font_family"];
		$Email_font_color = $TemplateDetails["Email_font_color"];
		$Email_background_color = $TemplateDetails["Email_background_color"];
		$Unsubscribe_flg = $TemplateDetails["Unsubscribe_flg"];
		
		$Ios_application_link = $TemplateDetails["Ios_application_link"];
		$Header_background_color = $TemplateDetails["Header_background_color"];
		$Footer_background_color = $TemplateDetails["Footer_background_color"];
		$Twitter_share_flag = $TemplateDetails["Twitter_share_flag"];
		$Facebook_share_flag = $TemplateDetails["Facebook_share_flag"];
		$Linkedin_share_flag = $TemplateDetails["Linkedin_share_flag"];
		 
		$Google_share_flag = $TemplateDetails["Google_share_flag"];
		$Google_play_link = $TemplateDetails["Google_play_link"];
	
		if($param['Email_template_id'] == 1 || $param['Email_template_id'] == 16)
		{ 
			$Pwd_set_code = $param['Pwd_set_code'];
			$Base_url = $Cust_website; 
			
			$myData = array('Company_id' => $_SESSION['company_id'], 'Enroll_id' => $Customer_id, 'User_email_id' => $User_email_id,'Pwd_set_code'=>$Pwd_set_code);
			
			$Pwddata = base64_encode(json_encode($myData));
			$Pwddata_URL = $Base_url."/Login/bc1fadea?vvTFsNBjgNhi=" . $Pwddata;
			$Pwdlink = "<a href='" . $Pwddata_URL . "' target='_blank' style='color:#000;'>Click here to Set Password</a>";
		}
		if($param['Email_template_id'] == 33)
		{ 
			$Pin_codes=$param['Pin_codes'];
			$Voucher_no=$param['Voucher_no'];
			$i=1;
			$Voucher_no_text="";
			foreach($Voucher_no as $voch){
				$Voucher_no_text .=$i.") ".$voch."<br>";
				$i++;
			}
			$j=1;
			$Pin_codes_text="";
			foreach($Pin_codes as $pin){
				
				if($pin){
					$Pin_codes_text .=$j.") ".$pin."<br>";
				}else {
					$Pin_codes_text .="-";
				}
				$j++;
			}
		}
		include'Email_templates/email.php';
			
		$body = ob_get_contents();
		ob_end_clean();	
		
		/*************************Email_body Variable Replace Code***************/
			$search_variables = array('$First_name','$Last_name','$Loyalty_program_name','$Membership_id','$Company_name','$User_name','$Password','$Pin_no','$Website','$Outlet_name','$Joining_bonus_points','$Current_balance','$Credit_points','$Purchase_date','$Cancellation_date','$Purchase_amount','$Cancelled_amount','$Debited_points','$Bill_no','$End_date','$Start_date','$Voucher_type','$Revenue_voucher','$Product_voucher','$Customer_name','$Discount_voucher','$Discount_percentage','$Discount_value','$User_email_id','$Pwdlink','$Company_Currency','$Transaction_date','$Google_play_link','$Ios_application_link','$Survey_name','$Survey_reward','$Order_no','$Amount','$Redeem_amount','$Redeem_points','$datatable','$Gift_card_no','$Gift_card_amount','$Paid_amount','$Valid_till','$Pin','$Transfered_points','$Transferred_to','$Promo_code','$Promo_points','$Transferred_from','$Order_amount','$Discount_amount','$Balance_due','$Gained_points','$Current_balance','$Outlet_name','$Brand_name','$Voucher_no','$Voucher_validity','$Description','$Reward_amt','$Reward_percent','$Received_points','$Received_from','$Voucher_name','$Quantity','$product_name','$product_image','$Symbol_of_currency','$Code_pin','$Comapany_Currency','$Notification_description','$Company_primary_contact_person','$Query','$Phone_no','$New_Tier_name');
			
			$inserts_contents = array($First_name,$Last_name,$Company_name,$Card_id,$Company_name,$User_email_id,$User_pwd,$pinno,$Website,$Super_Seller_Name,$param['Joining_bonus_points'],$Current_balance,$param['Credit_points'],$param['Purchase_date'],$param['Cancellation_date'],$param['Purchase_amount'],$param['Cancelled_amount'],$param['Debited_points'],$param['Bill_no'],$param['End_date'],$param['Start_date'],$param['Voucher_type'],$param['Revenue_voucher'],$param['Product_voucher'],$Customer_name,$param['Discount_voucher'],$param['Discount_percentage'],$param['Discount_value'],$User_email_id,$Pwdlink,$Company_Currency,$Transaction_date,$Cust_apk_link,$Cust_ios_link,$param['Survey_name'],$param['Survey_reward'],$param['Order_no'],$param['Amount'],$param['Redeem_amount'],$param['Redeem_points'],$param['datatable'],$param['Gift_card_no'],$param['Gift_card_amount'],$param['Paid_amount'],$param['Valid_till'],$pinno,$param['Transfered_points'],$param['Transferred_to'],$param['Promo_code'],$param['Promo_points'],$param['Transferred_from'],$param['Order_amount'],$param['Discount_amount'],$param['Balance_due'],$param['Gained_points'],$param['Current_balance'],$param['Outlet_name'],$param['Brand_name'],$Voucher_no_text,$param['Voucher_validity'],$param['Description'],$param['Reward_amt'],$param['Reward_percent'],$param['Received_points'],$param['Received_from'],$param['Voucher_name'],$param['Quantity'],$param['product_name'],$param['product_image'],$param['Symbol_of_currency'],$Pin_codes_text,$Company_Currency,$param['Notification_description'],$Company_primary_contact_person,$param['Notification_type'],$Phone_no,$New_Tier_name);
			
			$email_content = str_replace($search_variables,$inserts_contents,$TemplateDetails["Email_body"]);
				
		/*******************Email_body Variable Replace Code****************/
		/*******************Email_subject Variable Replace Code************/
			
			$search_variables_sub = array('$First_name','$Last_name','$Company_name','$End_date','$Start_date','$Voucher_type','$Revenue_voucher','$Product_voucher','$Current_balance','$Membership_id','$Customer_name','$Discount_voucher','$Discount_percentage','$Discount_value','$User_email_id','$Pwdlink','$Company_Currency','$Joining_bonus_points','$Transaction_date','$Google_play_link','$Ios_application_link','$Transferred_from','$Order_no','$Transfered_points','$Transferred_to','$Received_points','$Received_from','$Promo_code','$Promo_points');

			$inserts_contents_sub = array($First_name,$Last_name,$Company_name,$param['End_date'],$param['Start_date'],$param['Voucher_type'],$param['Revenue_voucher'],$param['Product_voucher'],$Current_balance,$Card_id,$Customer_name,$param['Discount_voucher'],$param['Discount_percentage'],$param['Discount_value'],$User_email_id,$Pwdlink,$Company_Currency,$param['Joining_bonus_points'],$param['Transaction_date'],$Cust_apk_link,$Cust_ios_link,$param['Transferred_from'],$param['Order_no'],$param['Transfered_points'],$param['Transferred_to'],$param['Received_points'],$param['Received_from'],$param['Promo_code'],$param['Promo_points']);
		
			$Email_subject = str_replace($search_variables_sub,$inserts_contents_sub,$TemplateDetails["Email_subject"]);
			
		/******Email_subject Variable Replace Code*******Footer_notes Variable Replace Code*****************/
			$search_variables_footer = array('$First_name','$Last_name','$Company_name');//
			$inserts_contents_footer = array($First_name,$Last_name,$Company_name);
			$Footer_notes = str_replace($search_variables_footer,$inserts_contents_footer,$TemplateDetails["Footer_notes"]);
		/***Footer_notes Variable Replace Code******email_header Variable Replace Code*********/
			$search_variables_header = array('$First_name','$Last_name','$Company_name');//
			$inserts_contents_header = array($First_name,$Last_name,$Company_name);
			$email_header = str_replace($search_variables_header,$inserts_contents_header,$TemplateDetails["Email_header"]);
			// $email_header = $TemplateDetails["Email_header"];
		/**************email_header Variable Replace Code***********/
			$search_variables_sub = array('$email_header','$email_content','$email_footer','$Body_image','$Body_structure','$Email_header_image','$Email_font_size','$Font_family','$Email_font_color','$Email_background_color','$Header_background_color','$Footer_background_color','$facebook_link','$twitter_link','$googlplus_link','$linkedin_link');
			
			$inserts_contents_sub = array($email_header,$email_content,$Footer_notes,$TemplateDetails["Body_image"],$TemplateDetails["Body_structure"],$TemplateDetails["Email_header_image"],$TemplateDetails["Email_font_size"],$TemplateDetails["Font_family"],$TemplateDetails["Email_font_color"],$TemplateDetails["Email_background_color"],$TemplateDetails["Header_background_color"],$TemplateDetails["Footer_background_color"],$Facebook_link,$Twitter_link,$Googlplus_link,$Linkedin_link);
			
			$html = str_replace($search_variables_sub,$inserts_contents_sub,$body);
		/*******************Email_subject Variable Replace Code**********************/
		try 
		{
			if($param['Email_template_id'] == 30)
			{
				$User_email_id = $Company_contactus_email_id;
			}
			
			$smtpDetails=$this->dbHobj->getSmtpDetails($Company_id);
			if($smtpDetails != NULL)
			{
				$smtpHost = $smtpDetails["smtp_host"];
				$smtpUsername = $smtpDetails["smtp_user"];
				$smtpPassword = $smtpDetails["smtp_pass"];
				$smtpPort = $smtpDetails["smtp_port"];
			}
			else
			{
				$smtpHost = "Igainspark-com.mail.protection.outlook.com";
				$smtpUsername = "no-reply@igainspark.com";
				$smtpPassword = "Gaf70488";
				$smtpPort = "25";
			}	
				
		
			// $mail->SMTPDebug = SMTP::DEBUG_SERVER;                    
			$mail = new PHPMailer(true);
			// $mail->SMTPDebug = 3; 
			$mail->isSMTP();  
			$mail->Host = $smtpHost;  
			$mail->SMTPAuth = true;                                 
			$mail->Username = $smtpUsername;                    
			$mail->Password = $smtpPassword;                              
			$mail->SMTPSecure = 'tls';         
			$mail->Port = $smtpPort;                                  
			
			$mail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				)
			);
			
			$mail->From = $smtpUsername;
			$mail->FromName = $Company_name;
			$mail->addAddress($User_email_id,  $First_name.' '. $Last_name);    
			$mail->isHTML(true);                           
			$mail->Subject = $Email_subject;
			$mail->Body = $html;
			if($Notification_send_to_email ==1 ){
				$mail->send();
			}
			else if($param['Email_template_id'] == 1 || $param['Email_template_id'] == 16)
			{
				$mail->send();
			}
		}
		catch (Exception $e) 
		{	
			//echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
		}
		$timezone_entry = $Seller_timezone_entry;
		$logtimezone = $timezone_entry;
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$Todays_date_time = $date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');			
		
		$NotiPara['Company_id']=$_SESSION["company_id"];
		$NotiPara['Seller_id']=$Super_Seller_id;
		$NotiPara['Customer_id']=$Customer_id;
		$NotiPara['User_email_id']=$User_email_id;
		$NotiPara['Communication_id']=0;
		$NotiPara['Offer']=$Email_subject;
		$NotiPara['Offer_description']=$html;
		$NotiPara['Open_flag']=0;
		$NotiPara['Date']=$Todays_date_time;
		$NotiPara['Active_flag']=1;	
		
		if($param['Email_template_id'] != 30)
		{
			$insert_id = $this->notiHobj->Insert_notification($NotiPara);
		
			if($Fcm_access_token != Null && $Fcm_token != Null)
			{	
				$url = "https://fcm.googleapis.com/fcm/send";
				$api_key = $Fcm_access_token;
				$To = $Fcm_token;	
				
				$input_data = array("id"=>$insert_id,"redirect_to"=>"ReadNotification");
				$notification_data = array("title"=>$Company_name,"body"=>$Email_subject);

				$input_notification = array("to"=>$Fcm_token,"data"=>$input_data,"notification"=>$notification_data);
				$notifBodyReq = json_encode($input_notification);
				
				$curl = curl_init();
				
				curl_setopt_array($curl, array(
				  CURLOPT_URL => $url,
				  CURLOPT_POST => true,
				  CURLOPT_POSTFIELDS => $notifBodyReq,  
				  CURLOPT_RETURNTRANSFER => true,
				  CURLOPT_HTTPHEADER => array('Authorization:key='.$api_key,'Content-Type: application/json')
				));
				$response =curl_exec($curl);
				$data = json_decode($response,true);
				//echo $data;
				curl_close($curl);
			}
		}
    }
}
?>