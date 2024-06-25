<?php
class SurveyHandler
{
    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() 
	{
        require_once dirname(__FILE__) . '/DbConnect.php';
       
        // opening db connection
        $db = new DbConnect();
		// print_r($db);
        $this->conn = $db->connect();

		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
    }
	 public function getCompanyDetails($api_key) 
	 {
		// $api_key1 = $this->phash->string_encrypt($api_key);	
		$api_key = $this->phash->string_decrypt($api_key);		
        $stmt = $this->conn->prepare("SELECT Company_id,Company_name,phonecode,Survey_analysis,Domain_name,Cust_website from igain_company_master JOIN igain_country_master ON igain_country_master.id=igain_company_master.Country WHERE Company_id = ?");
		
        $stmt->bind_param("s", $api_key);		
        if ($stmt->execute()) 
		{
			$res = array();
            $stmt->bind_result($Company_id,$Company_name,$phonecode,$Survey_analysis,$Domain_name,$Cust_website);
            $stmt->fetch();
			
            // $stmt->bind_result($id, $task, $status, $created_at);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["Company_id"] = $Company_id;
            $res["Company_name"] = $Company_name;
            $res["phonecode"] = $phonecode;
            $res["Survey_analysis"] = $Survey_analysis;
            $res["Domain_name"] = $Domain_name;
            $res["Cust_website"] = $Cust_website;
            $stmt->close();
            return $res;
        } 
		else 
		{
            return NULL;
        }
    }
	public function getMemberDetails($membershipid,$phoneno,$Company_id) 
	{
		$phoneno=$this->phash->string_encrypt($phoneno);
	
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry FROM igain_enrollment_master WHERE (Card_id = ? OR Phone_no = ? ) and Company_id = ? and User_id=1");
        $stmt->bind_param("sss", $membershipid,$phoneno,$Company_id);
        if ($stmt->execute()) 
		{
            $user = $stmt->get_result()->fetch_assoc();
            return $user;
        } 
		else 
		{
            return NULL;
        }
    }
	public function get_enrollment_details($Enrollement_id) 
	{
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry FROM igain_enrollment_master WHERE Enrollement_id = ?");
        $stmt->bind_param("s", $Enrollement_id);
        if ($stmt->execute()) 
		{
            $user = $stmt->get_result()->fetch_assoc();
            return $user;
        } 
		else 
		{
            return NULL;
        }
    }
	public function getSendSurveyDetails($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT * FROM igain_survey_send JOIN igain_survey_structure_master ON igain_survey_structure_master.Survey_id = igain_survey_send.Survey_id WHERE igain_survey_send.Enrollment_id = ? and igain_survey_send.Company_id = ? and igain_survey_structure_master.Send_flag = 1");
		
        $stmt->bind_param("ss", $Enrollement_id,$Company_id);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$survey[] = $row;
			}
			return $survey;
        }
		else 
		{
            return NULL;
        } 
    }
	public function checkSurveyResponse($Survey_id,$Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT * FROM igain_response_master WHERE Survey_id = ? and Company_id = ? and Enrollment_id = ?");
		
        $stmt->bind_param("sss", $Survey_id,$Company_id,$Enrollement_id);
		
        $stmt->execute();
	
		$stmt->store_result();
		$num_rows = $stmt->num_rows; 
		// echo $num_rows; exit;
		return $num_rows;
	}
	public function getSurveyQuestions($Survey_id,$Enrollement_id,$Company_id) 
	{ 
        $stmt = $this->conn->prepare("SELECT igain_questionaire_master.Question,igain_questionaire_master.Question_id,igain_questionaire_master.Response_type,igain_questionaire_master.Choice_id,igain_questionaire_master.Multiple_selection,igain_survey_structure_master.Survey_name,igain_survey_structure_master.Company_id,igain_survey_structure_master.Survey_id FROM igain_survey_send JOIN igain_questionaire_master ON igain_questionaire_master.Survey_id = igain_survey_send.Survey_id JOIN igain_survey_structure_master ON igain_survey_structure_master.Survey_id = igain_questionaire_master.Survey_id WHERE igain_survey_send.Enrollment_id = ? and igain_survey_send.Survey_id = ? and igain_survey_send.Company_id = ?");
		
        $stmt->bind_param("sss", $Enrollement_id,$Survey_id,$Company_id);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$surveyquestions[] = $row;
			}
			return $surveyquestions;
        }
		else 
		{
            return NULL;
        } 
    }
	public function getMCQchoiceValues($Choice_id) 
	{ 
        $stmt = $this->conn->prepare("SELECT Value_id,Choice_id,Option_values FROM igain_multiple_choice_values WHERE Choice_id = ?");
		
        $stmt->bind_param("s", $Choice_id);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$questionchoice[] = $row;
			}
			return $questionchoice;
        }
		else 
		{
            return NULL;
        } 
    }
	public function get_nps_promoters_keywords($Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT * FROM igain_nps_master WHERE NPS_Company_id = ?");
		
        $stmt->bind_param("s",$Company_id);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$survey[] = $row;
			}
			return $survey;
        }
		else 
		{
            return NULL;
        } 
    }
	public function GetMultipleChoiceDetails($Value_id) 
	{
        $stmt = $this->conn->prepare("SELECT * FROM igain_multiple_choice_values WHERE Value_id = ?");
        $stmt->bind_param("s", $Value_id);
        if ($stmt->execute()) 
		{
            $multiple_choice = $stmt->get_result()->fetch_assoc();
            return $multiple_choice;
        } 
		else 
		{
            return NULL;
        }
    }
	public function get_survey_details($Survey_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT * FROM igain_survey_structure_master WHERE Survey_id = ? and Company_id = ?");
        $stmt->bind_param("ss", $Survey_id,$Company_id);
        if ($stmt->execute()) 
		{
            $user = $stmt->get_result()->fetch_assoc();
            return $user;
        } 
		else 
		{
            return NULL;
        }
    }
	public function insert_survey_response($ResponsePara) 
	{
		$key = array_keys($ResponsePara);
		$val = array_values($ResponsePara);
		$sql = "INSERT INTO igain_response_master (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) 
		{
			return SUCCESS;
		} 
		else
		{
			return FAIL;			
		}
	}
	public function insert_survey_rewards_transaction($rewards_data) 
	{
		$key = array_keys($rewards_data);
		$val = array_values($rewards_data);
		$sql = "INSERT INTO igain_transaction (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) 
		{
			return SUCCESS;
		} 
		else
		{
			return FAIL;			
		}
	}
	public function Insert_log_table($log_data) 
	{
		$key = array_keys($log_data);
		$val = array_values($log_data);
		$sql = "INSERT INTO igain_log_tbl (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		if($result) 
		{
			return SUCCESS;
		} 
		else
		{
			return FAIL;			
		}
	}
	public function update_member_balance($MemberPara,$Enrollment_id)
	{
		$stmt = $this->conn->prepare("UPDATE igain_enrollment_master t set t.Current_balance = ?,t.Total_topup_amt = ? WHERE t.Enrollement_id = ? ");
        $stmt->bind_param("ssi", $MemberPara['Current_balance'],$MemberPara['Total_topup_amt'],$Enrollment_id);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
    public function getUserByEmail($email)
	{	
		$email = $this->phash->string_encrypt($email);	
		// SELECT password_hash FROM igain_enrollment_master WHERE User_email_id,User_pwd = ?,?
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno FROM igain_enrollment_master WHERE User_email_id = ? and Company_id = ? and User_id=1");
        $stmt->bind_param("ss", $email,$_SESSION['company_id']);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($Enrollement_id, $First_name, $Last_name, $User_email_id,$Card_id,$User_pwd,$pinno);			
            $stmt->fetch();
            $user = array();
            $user["id"] = $Enrollement_id;
            $user["fname"] = $First_name;
            $user["lname"] = $Last_name;
            $user["Membership_ID"] = $Card_id;
            $user["Password"] = $this->phash->string_decrypt($User_pwd);
            $user["Pin"] = $pinno;
            $user["email"] = $this->phash->string_decrypt($User_email_id);
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }
}
?>