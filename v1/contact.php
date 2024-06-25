<?php
require_once '../include/DbHandler.php';
require_once '../include/ContactHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require_once '../include/SendEmailHandler.php';
require '.././libs/Slim/Slim.php';

use lib\Slim\Middleware\SessionCookie;
// error_reporting(E_ALL);
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
			
				$comp = new ContactHandler();
				$result = $comp->getCompanyDetails($api_key);
				
				if ($result != NULL) 
				{
					$_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];			
					$_SESSION["Redemptionratio"] = $result["Redemptionratio"];			
					$_SESSION["phonecode"] = $result["phonecode"];							
					$_SESSION["Company_address"] = $result["Company_address"];		
					$_SESSION["Company_primary_email_id"] = $result["Company_primary_email_id"];		
					$_SESSION["Company_contactus_email_id"] = $result["Company_contactus_email_id"];		
					$_SESSION["Company_primary_phone_no"] = $result["Company_primary_phone_no"];
					$_SESSION["Company_secondary_phone_no"] = $result["Company_secondary_phone_no"];		
					$_SESSION["Website"] = $result["Website"];		
					$_SESSION["Cust_website"] = $result["Cust_website"];		
					$_SESSION["Facebook_link"] = $result["Facebook_link"];		
					$_SESSION["Twitter_link"] = $result["Twitter_link"];		
					$_SESSION["Linkedin_link"] = $result["Linkedin_link"];		
					$_SESSION["Googlplus_link"] = $result["Googlplus_link"];		
					$_SESSION["Social5_link"] = $result["Social5_link"];		
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
$app->post('/getdetails','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$contactObj = new ContactHandler();

	$user = $contactObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
				
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		
		$response["errorcode"] = 1001;
		$response["message"] = "Successful";
		$response["name"] = $_SESSION["company_name"];
		$response["redemptionratio"] = $_SESSION["Redemptionratio"];
		$response["address"] = $_SESSION["Company_address"];
		$response["phonecode"] = $_SESSION["phonecode"];
		$response["phone"] = $_SESSION["Company_secondary_phone_no"];
		$response["email"] = $_SESSION["Company_contactus_email_id"];
		$response["website"] = $_SESSION["Website"];
		$response["facebook"] = $_SESSION["Facebook_link"];
		$response["twitter"] = $_SESSION["Twitter_link"];
		$response["linkedin"] = $_SESSION["Linkedin_link"];
		$response["googlplus"] = $_SESSION["Googlplus_link"];
		$response["social5"] = $_SESSION["Social5_link"];
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
});
$app->post('/feedback','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	
	verifyRequiredParams(array('membershipid','feedback'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['feedback'] = $request_array['feedback'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$contactObj = new ContactHandler();
	$sendEObj = new SendEmailHandler();
	
	$user = $contactObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
				
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		
		$date = new DateTime();
		$lv_date_time = $date->format('Y-m-d H:i:s');
		
		$insertPOData["Company_id"]=$Company_id;
		$insertPOData["Enrollment_id"] = $Enrollement_id;
		$insertPOData["Membership_id"]=$Membership_ID;
		$insertPOData["Header_type"]=1; // feedback
		$insertPOData["Content_description"]=strip_tags($param['feedback']);
		$insertPOData["Create_user_id"]=$Enrollement_id;
		$insertPOData["Creation_date"]=$lv_date_time;
	
		$result = $contactObj->Insert_contact_feedback($insertPOData);
		
		$EmailParam['Notification_description'] = strip_tags($param['feedback']);
		$EmailParam['Notification_type'] ='Feedback';
		$EmailParam['Template_type'] = 'Contactus';
		$EmailParam['Email_template_id'] =30; //Contactus
		
		$email = $sendEObj->sendEmail($EmailParam,$Enrollement_id);
		
		$EmailParam1['Notification_description'] = strip_tags($param['feedback']);
		$EmailParam1['Notification_type'] ='Feedback';
		$EmailParam1['Template_type'] = 'Contactus_feedback';
		$EmailParam1['Email_template_id'] =31; //Contactus_feedback
		
		$email = $sendEObj->sendEmail($EmailParam1,$Enrollement_id);
		
		$response["errorcode"] = 1001;
		$response["message"] = "Message has been Submitted Successfully";
	}
	else
	{
		$response["errorcode"] = 2003;
		$response["message"] = "Invalid or unable to locate membership id";
	}
	echoRespnse($response); 
});
$app->post('/getmenu','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$contactObj = new ContactHandler();

	$user = $contactObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
				
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		
		$company = $contactObj->get_company_details($Company_id);
		if($company['Company_License_type'] == 117)
		{
			$License_type = "Standard";
		}
		else if($company['Company_License_type'] == 120)
		{
			$License_type = "Enhance";
		}
		else if($company['Company_License_type'] == 121)
		{
			$License_type = "Basic";
		}
		$response["errorcode"] = 1001;
		$response["message"] = "Successful";
		$response["name"] = $company["Company_name"];
		$response["license"] = $License_type;
		$response["profile"] = $company["Profile_flag"];
		$response["offer"] = $company["Offer_flag"];
		$response["rewards"] = $company["Redeem_flag"];
		$response["transfer"] = $company["Transfer_flag"];
		$response["promo"] = $company["Promo_code_applicable"];
		$response["auction"] = $company["Auction_bidding_applicable"];
		$response["survey"] = $company["Survey_flag"];
		$response["notification"] = $company["Notification_flag"];
		$response["statement"] = $company["My_statement_flag"];
		$response["discount"] = $company["Discount_flag"];
		$response["voucher"] = $company["Voucher_applicable"];
		$response["contactus"] = $company["Contact_flag"];
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