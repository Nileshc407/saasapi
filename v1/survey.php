<?php
require_once '../include/DbHandler.php';
require_once '../include/SurveyHandler.php';
require_once '../include/SendEmailHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/LogHandler.php';
require_once '../include/PassHash.php';
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
			// get the api key
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
			
				$comp = new SurveyHandler();
				$result = $comp->getCompanyDetails($api_key);
				
				if ($result != NULL) 
				{
					$_SESSION["company_id"] = $result["Company_id"];
					$_SESSION["company_name"] = $result["Company_name"];			
					$_SESSION["phonecode"] = $result["phonecode"];					
					$_SESSION["Survey_analysis"] = $result["Survey_analysis"];					
					$_SESSION["Domain_name"] = $result["Domain_name"];					
					$_SESSION["Cust_website"] = $result["Cust_website"];					
					
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
$app->post('/getsurvey','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	// to get an array so try following..
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	$Domain_name = $_SESSION["Domain_name"];
	$Cust_website = $_SESSION["Cust_website"];
	// check for required params
	verifyRequiredParams(array('membershipid'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	// $param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$surveyObj = new SurveyHandler();
	
	$user = $surveyObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		
		$survey = $surveyObj->getSendSurveyDetails($Enrollement_id,$Company_id);
		if($survey != NULL)
		{
			$Base_url = $Cust_website; 
			
			$surveydata= array();
			foreach($survey as $row)
			{
				$surveyResponse = $surveyObj->checkSurveyResponse($row["Survey_id"],$Enrollement_id,$Company_id);
				if($surveyResponse == 0)
				{
					if($row["Survey_type"] == 1)
					{
						$Survey_type = "Feedback Survey";
					}
					else if($row["Survey_type"] == 2)
					{
						$Survey_type = "Service Related Survey";
					}
					else if($row["Survey_type"] == 3)
					{
						$Survey_type = "Product Survey";
					}
					else
					{
						$Survey_type="";
					}
					
					$myData = array('Company_id' => $Company_id, 'Enroll_id' => $Enrollement_id, 'Survey_id' => $row["Survey_id"], 'Card_id' => $Membership_ID);
			
					$Surdata = base64_encode(json_encode($myData));
					$Surdata_URL = $Base_url."/Api/bc1fadea?vvTFsNBjgNhi=" . $Surdata;
					
					//$Surveylink = "<a href='" . $Surdata_URL . "' target='_blank' style='color:#000;'>Click here to Submit Survey</a>";
					
					$Survey_details = array("id" => $row["Survey_id"], "name" =>$row["Survey_name"], "type" =>$Survey_type,"reward" => $row["Survey_reward_points"],"enddate" => $row["End_date"],"surveyurl" => $Surdata_URL,"imageurl" => $row["Survey_image"]);
					$surveydata[] =$Survey_details;
				}						
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["surveydata"] = $surveydata;
		}
		else
		{
			$response["errorcode"] = 3106;
			$response["message"] = "Survey not exist";
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
$app->post('/getsurveyquestion','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	// to get an array so try following..
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	// check for required params
	verifyRequiredParams(array('membershipid','id'),$request_array);
	
	$response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['survey_id'] = $request_array['id'];
	// $param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	
	$surveyObj = new SurveyHandler();
	
	$user = $surveyObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
	if($user != NULL) 
	{
		$Company_id = $user['Company_id'];
		$Enrollement_id = $user['Enrollement_id'];
		$Membership_ID = $user['Card_id'];
		$fname = $user['First_name'];
		$lname = $user['Last_name'];
		$Current_balance = $user['Current_balance'];
		
		$surveyQuestions = $surveyObj->getSurveyQuestions($param['survey_id'],$Enrollement_id,$Company_id);
		if($surveyQuestions != NULL)
		{
			$questionsdata= array();
			foreach($surveyQuestions as $row)
			{
				if($row["Response_type"] == 1)
				{
					$MCQchoiceValues = $surveyObj->getMCQchoiceValues($row["Choice_id"]);
					$mcqchoicedata = array();
					if($MCQchoiceValues != NULL)
					{
						foreach($MCQchoiceValues as $choiceValuesrow)
						{
							$MCQchoice_details = array("id" => $choiceValuesrow["Value_id"],"values" =>$choiceValuesrow["Option_values"]);//"choiceid" => $choiceValuesrow["Choice_id"], 
							
							$mcqchoicedata[] = $MCQchoice_details;
						}
					}
				}
				else
				{
					$mcqchoicedata = Null;
				}
				$Questions_details = array("id" => $row["Question_id"], "question" =>$row["Question"],"responsetype" => $row["Response_type"],"multipleselection" => $row["Multiple_selection"],"mcqchoicedata" => $mcqchoicedata); // "choiceid" => $row["Choice_id"],
										
				$questionsdata[] =$Questions_details;
			}
			
			$response["errorcode"] = 1001;
			$response["message"] = "Successful";
			$response["id"] = $param['survey_id'];
			$response["questionsdata"] = $questionsdata;
		}
		else
		{
			$response["errorcode"] = 3107;
			$response["message"] = "Questions not exist for this survey id";
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
$app->post('/submitresponse','authenticate', function() use ($app) 
{   
	$json=$app->request->getbody();
	// to get an array so try following..
	$request_array=json_decode($json,true);
	$Company_id = $_SESSION["company_id"];
	// check for required params
	verifyRequiredParams(array('membershipid','id'),$request_array);
	
	// $response = array();

	$param['membershipid'] = $request_array['membershipid'];
	$param['survey_id'] = $request_array['id'];
	$param['responsedata'] = $request_array['responsedata'];
	
	// $param['phoneno'] = $_SESSION["phonecode"].''.$request_array['phoneno'];
	$param['phoneno'] = $_SESSION["phonecode"].''.$request_array['membershipid'];
	
	$param['Company_name'] = $_SESSION["company_name"];
	$param['Survey_analysis'] = $_SESSION["Survey_analysis"];
	$param['Loyalty_program_name'] = $_SESSION["company_name"];
		
	// require_once dirname(__FILE__) . '/PassHash.php';
	$phash = new PassHash();
	$dbHandlerObj = new DbHandler();
	$surveyObj = new SurveyHandler();
	$sendEObj = new SendEmailHandler();
	$logHObj = new LogHandler();
	
	$user = $surveyObj->getMemberDetails($param['membershipid'],$param['phoneno'],$Company_id);
		
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
		$Member_name = $fname.' '.$lname;
		
		
		$param['timezone_entry']=$user['timezone_entry'];
		$logtimezone = $param['timezone_entry'];
		$timezone = new DateTimeZone($logtimezone);
		$date = new DateTime();
		$date->setTimezone($timezone);
		$lv_date_time=$date->format('Y-m-d H:i:s');
		$Todays_date = $date->format('Y-m-d');
		
		$SellerDetails=$dbHandlerObj->superSellerDetails();   
        $Super_Seller_id = $SellerDetails['id'];
        $Super_Seller_Name= $SellerDetails['fname'].' '.$SellerDetails['lname'];
        $Seller_timezone_entry =$SellerDetails['timezone_entry'];
		
		// $surveyQuestions = $surveyObj->getSurveyQuestions($param['survey_id'],$Enrollement_id,$Company_id);
		$surveyResponse = $surveyObj->checkSurveyResponse($param['survey_id'],$Enrollement_id,$Company_id);
		if($surveyResponse == 0)
		{
			if($param['responsedata'] != NULL)
			{
				foreach($param['responsedata'] as $row)
				{
					$Question_id=$row['id'];
					$Question=$row['question'];
					$Response_type=$row['responsetype'];
					$Multiple_selection=$row['multipleselection'];
			
					if($Response_type == 2 ) //Text Based Question
					{
						$get_flag=0;
						
						$response = $row['textresponse'];
						$Cust_response = strtolower($response);
						if($param['Survey_analysis'] == 1)
						{
							$get_promoters_dictionary_keywords = $surveyObj->get_nps_promoters_keywords($Company_id);
							if($get_promoters_dictionary_keywords !=Null)
							{
								foreach($get_promoters_dictionary_keywords as $NPS_promo)
								{
									$dictionary_keywords=strtolower($NPS_promo['NPS_dictionary_keywords']);
									$Get_promo_keywords=explode(",",$dictionary_keywords);
									$NPS_type_id=$NPS_promo['NPS_type_id'];
									
									for($i=0;$i<count($Get_promo_keywords); $i++)
									{
										$pos = strpos($Cust_response, $Get_promo_keywords[$i]);
									
										if(is_int($pos) == true)
										{
											$get_flag=1;
											$NPS_type_id=$NPS_promo['NPS_type_id'];
											break;
										}
									}
									
									if($get_flag==1)
									{
										$post_data10['Enrollment_id']=$Enrollement_id;
										$post_data10['Company_id']=$Company_id;
										$post_data10['Survey_id']=$param['survey_id'];
										$post_data10['Question_id']=$Question_id;
										$post_data10['Response1']=$Cust_response;
										$post_data10['NPS_type_id']=$NPS_type_id;
										
										$response_flag=0;
										$insert_response = $surveyObj->insert_survey_response($post_data10);
										
										if($insert_response == SUCCESS)
										{
											$response_flag=1;
										}
										else
										{
											$response_flag=0;
										}
										break;
									}
								}
								if($get_flag==0)
								{
									$NPS_type_id=2;
									$post_data01['Enrollment_id']=$Enrollement_id;
									$post_data01['Company_id']=$Company_id;
									$post_data01['Survey_id']=$param['survey_id'];
									$post_data01['Question_id']=$Question_id;
									$post_data01['Response1']=$Cust_response;
									$post_data01['NPS_type_id']=$NPS_type_id;
									
									$response_flag=0;
									$insert_response = $surveyObj->insert_survey_response($post_data01);
									
									if($insert_response == SUCCESS)
									{
										$response_flag=1;
									}
									else
									{
										$response_flag=0;
									}
								}
							}
							else
							{
								echo "emptey"; exit;
							}
						}
						else
						{
							$post_data0['Enrollment_id']=$Enrollement_id;
							$post_data0['Company_id']=$Company_id;
							$post_data0['Survey_id']=$param['survey_id'];
							$post_data0['Question_id']=$Question_id;
							$post_data0['Response1']=$Cust_response;
							$post_data0['NPS_type_id']=0;
							
							$response_flag=0;
							$insert_response = $surveyObj->insert_survey_response($post_data0);
							
							if($insert_response == SUCCESS)
							{
								$response_flag=1;

							}
							else
							{
								$response_flag=0;
							}
						}
					}
					else if($Response_type == 1 && $Multiple_selection ==1)//Multiple Selection based Question i.e. Check box Based
					{
						foreach($row['mcqchoicedata'] as $mulsel)
						{
							$Value_id = $mulsel['id'];
							$Option_values = $mulsel['values'];
							
							$ChoiceId = $surveyObj->GetMultipleChoiceDetails($Value_id);
							$Mul_Choice_id = $ChoiceId['Choice_id'];
							$Survey_nps_type1 = $ChoiceId['NPS_type_id'];
					
							$Mul_response2=$Value_id;
							$Mul_Choice_id=$Mul_Choice_id;

							if($param['Survey_analysis'] == 1)
							{
								$Survey_nps_type=$Survey_nps_type1;
							}
							
							if($Survey_nps_type ==1 )
							{
								$Promo_nps[]=$Survey_nps_type;
							}
							else if($Survey_nps_type ==2 )
							{
								$Passive_nps[]=$Survey_nps_type;
							}
							else
							{
								$Dectractive_nps[]=$Survey_nps_type;
							}
					//	} foreach close

							if(count($Promo_nps) > 0 || count($Passive_nps) > 0 || count($Dectractive_nps) > 0 )
							{
								if(count($Promo_nps) == count($Dectractive_nps))
								{
									$Survey_nps_type=2;
								}
								else if(count($Promo_nps) > count($Dectractive_nps) && count($Promo_nps) > count($Passive_nps))
								{
									$Survey_nps_type=1;
								}
								else if(count($Passive_nps) >= count($Dectractive_nps))
								{
									$Survey_nps_type=1;
								}
								else
								{
									$Survey_nps_type=3;
								}
							}
							else
							{
								$Survey_nps_type=2;
							}


							if($Survey_nps_type != "")
							{
								$NPS_type_id12 = $Survey_nps_type;
							}
							else
							{
								$NPS_type_id12 =2;
							}
							if($Mul_response2 != "")
							{
								$Mul_response2=$Mul_response2;
							}
							else
							{
								$Mul_response2=0;
							}
							if($Mul_Choice_id != "")
							{
								$Mul_Choice_id=$Mul_Choice_id;
							}
							else
							{
								$Mul_Choice_id=0;
							}
							
							$post_data1['Enrollment_id']=$Enrollement_id;
							$post_data1['Company_id']=$Company_id;
							$post_data1['Survey_id']=$param['survey_id'];
							$post_data1['Question_id']=$Question_id;
							$post_data1['Response2']=$Mul_response2;
							$post_data1['Choice_id']=$Mul_Choice_id;
							$post_data1['NPS_type_id']=$NPS_type_id12;
							
							$response_flag=0;
							$insert_response = $surveyObj->insert_survey_response($post_data1);
							
							if($insert_response == SUCCESS)
							{
								$response_flag=1;
							}
							else
							{
								$response_flag=0;
							}

							unset($Promo_nps);
							unset($Passive_nps);
							unset($Dectractive_nps);
						}
					}
					else if($Response_type == 1 && $Multiple_selection ==0) //MCQ based Question i.e. Radio Based
					{
						foreach($row['mcqchoicedata'] as $mulsel)
						{
							$Value_id = $mulsel['id'];
							$Option_values = $mulsel['values'];
						}
						
						$ChoiceId = $surveyObj->GetMultipleChoiceDetails($Value_id);
						$Mul_Choice_id = $ChoiceId['Choice_id'];
						$Survey_nps_type1 = $ChoiceId['NPS_type_id'];
				
						$Response2=$Value_id;
						$Choice_id=$Mul_Choice_id;
						
						if($param['Survey_analysis'] == 1)
						{
							$Survey_nps_type=$Survey_nps_type1;
						}
						
						if($Survey_nps_type != "")
						{
							$NPS_type_id12 = $Survey_nps_type;
						}
						else
						{
							$NPS_type_id12 = 2;
						}
						if($Response2 != "")
						{
							$Response2=$Response2;
						}
						else
						{
							$Response2=0;
						}
						if($Choice_id != "")
						{
							$Choice_id=$Choice_id;
						}
						else
						{
							$Choice_id=0;
						}
						
						$post_data2['Enrollment_id']=$Enrollement_id;
						$post_data2['Company_id']=$Company_id;
						$post_data2['Survey_id']=$param['survey_id'];
						$post_data2['Question_id']=$Question_id;
						$post_data2['Response2']=$Response2;
						$post_data2['Choice_id']=$Choice_id;
						$post_data2['NPS_type_id']=$NPS_type_id12;
						$response_flag=0;
						
						$insert_response = $surveyObj->insert_survey_response($post_data2);
						
						if($insert_response == SUCCESS)
						{
							$response_flag=1;

						}
						else
						{
							$response_flag=0;
						}
					}
				}
				
				if($response_flag == 1)
				{
					$Survey_details=$surveyObj->get_survey_details($param['survey_id'],$Company_id);
					 $Survey_name=$Survey_details['Survey_name'];
					 $Start_date=$Survey_details['Start_date'];
					 $End_date=$Survey_details['End_date'];
					 $Survey_reward_points=$Survey_details['Survey_reward_points'];
					 $Survey_rewarded=$Survey_details['Survey_rewarded'];
					
					if(($Survey_rewarded == 1) && ( $Todays_date >= $Start_date && $Todays_date <= $End_date ))
					{
						$Enroll_details = $surveyObj->get_enrollment_details($Enrollement_id);
						$Card_id=$Enroll_details['Card_id'];
						$Current_balance=$Enroll_details['Current_balance'];
						$Total_topup_amt=$Enroll_details['Total_topup_amt'];
						$Blocked_points =$Enroll_details['Blocked_points'];
						$First_name =$Enroll_details['First_name'];
						$Last_name =$Enroll_details['Last_name'];

						$rewards_data['Company_id']=$Company_id;
						$rewards_data['Trans_type']=13;
						$rewards_data['Topup_amount']=$Survey_reward_points;
						$rewards_data['Trans_date']=$lv_date_time;
						$rewards_data['Enrollement_id']=$Enrollement_id;
						$rewards_data['Card_id']=$Card_id;
						$rewards_data['Remarks']= 'Survey Reward';
						
						$insert_survey_rewards=$surveyObj->insert_survey_rewards_transaction($rewards_data);
						if($insert_survey_rewards == SUCCESS)
						{
							 $Total_Current_Balance=$Current_balance+$Survey_reward_points;
							 $Total_Topup_Amount=$Total_topup_amt+$Survey_reward_points;
							
							$MemberPara['Total_topup_amt']=$Total_Topup_Amount;								
							$MemberPara['Current_balance']=$Total_Current_Balance;	
							
							$update_balance=$surveyObj->update_member_balance($MemberPara,$Enrollement_id);

							$EmailParam["error"] = false;
							$EmailParam['Survey_name'] = $Survey_name;  
							$EmailParam['Survey_reward']= $Survey_reward_points ;
							$EmailParam['Email_template_id'] =22; 
							
							$email = $sendEObj->sendEmail($EmailParam,$Enrollement_id); 
						}
					}
				/*********************Insert Log*************************/
					$log_data['Company_id']=$Company_id;
					$log_data['From_enrollid']=$Enrollement_id;
					$log_data['From_emailid']=$User_email_id;
					$log_data['From_userid']=$User_id;
					$log_data['To_enrollid']=$Enrollement_id;
					$log_data['Transaction_by']=$Member_name;
					$log_data['Transaction_to']= $Member_name;
					$log_data['Transaction_type']= 'Survey Response';
					$log_data['Transaction_from']= 'Submit Survey Response Api';
					$log_data['Operation_type']= 1;
					$log_data['Operation_value']= 'Survey Name: '.$Survey_name;
					$log_data['Date']= $Todays_date;
					
					$Log = $logHObj->insertLog($log_data);
					// $result_log_table = $surveyObj->Insert_log_table($log_data);
				/**********************Insert Log*************************/					
				}
				if($response_flag == 1)
				{
					$response = array();
					$response["errorcode"] = 1001;
					$response["message"] = "Successful";
					
					$APILogParam['Company_id'] =$Company_id;
                    $APILogParam['Trans_type'] = 13;
                    $APILogParam['Outlet_id'] = $Super_Seller_id;
                    $APILogParam['Bill_no'] = 0;
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
				$response["message"] = "Response data is blank";
			}
		}
		else
		{
			$response["errorcode"] = 3109;
			$response["message"] = "Survey Already Submitted";
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
        $response["errorcode"] = 3121;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse($response);
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