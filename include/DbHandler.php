<?php
// error_reporting(E_ALL);
/* Mail Functionality */

/* use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// echo"---in---DbHandler---PHPMailer---";

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php'; */

// echo"---in---DbHandler---PHPMailer-outside class--";

/* Mail Functionality */

class DbHandler {

    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() {

		// echo"---in---DbHandler--inside class--11--";
        require_once dirname(__FILE__) . '/DbConnect.php';
		// echo"---in---DbHandler---111-inside class--22--";
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();

		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
		
			
    }
	
	public function fetchEmailTemplate($Email_template_id) {
		
		/* echo"--Email_template_id-----".$Email_template_id;
		echo"--company_id-----".$_SESSION['company_id']; */

		/* echo "SELECT Template_description,Email_Type,Email_subject,Body_structure,Email_body,Footer_notes,Email_header,Body_image,Email_header_image,Email_font_size,Font_family,Email_font_color,Email_background_color,Unsubscribe_flg,Google_play_link,Header_background_color,Footer_background_color,Ios_application_link,Facebook_share_flag,Twitter_share_flag,Linkedin_share_flag,Google_share_flag from igain_company_email_template_master WHERE Status = 1 AND Email_template_id = ".$Email_template_id." AND Company_id = ".$_SESSION['company_id']." "; */ 
		
		$stmt = $this->conn->prepare("SELECT Template_description,Email_Type,Email_subject,Body_structure,Email_body,Footer_notes,Email_header,Body_image,Email_header_image,Email_font_size,Font_family,Email_font_color,Email_background_color,Unsubscribe_flg,Google_play_link,Header_background_color,Footer_background_color,Ios_application_link,Facebook_share_flag,Twitter_share_flag,Linkedin_share_flag,Google_share_flag from igain_company_email_template_master WHERE Status = 1 AND Template_type_id  = ? AND Company_id = ? ");
        $stmt->bind_param("ss", $Email_template_id,$_SESSION['company_id']);

			$stmt->execute();
			$stmt->store_result();
			// echo"--num_rows-----".$stmt->num_rows;
          if ($stmt->num_rows > 0) {

			$res = array();
            $stmt->bind_result($Template_description,$Email_Type,$Email_subject,$Body_structure,$Email_body,$Footer_notes,$Email_header,$Body_image,$Email_header_image,$Email_font_size,$Font_family,$Email_font_color,$Email_background_color,$Unsubscribe_flg,$Google_play_link,$Header_background_color,$Footer_background_color,$Ios_application_link,$Facebook_share_flag,$Twitter_share_flag,$Linkedin_share_flag,$Google_share_flag);
            
            $stmt->fetch();
            $res["Template_description"] = $Template_description;
            $res["Email_Type"] = $Email_Type;
            $res["Email_subject"] = $Email_subject;
            $res["Body_structure"] = $Body_structure;
            $res["Email_body"] = $Email_body;
            $res["Footer_notes"] = $Footer_notes;
            $res["Email_header"] = $Email_header;
            $res["Body_image"] = $Body_image;
            $res["Email_header_image"] = $Email_header_image;
            $res["Email_font_size"] = $Email_font_size;
            $res["Font_family"] = $Font_family;
            $res["Email_font_color"] = $Email_font_color;
            $res["Email_background_color"] = $Email_background_color;
            $res["Unsubscribe_flg"] = $Unsubscribe_flg;
            $res["Google_play_link"] = $Google_play_link;
            $res["Ios_application_link"] = $Ios_application_link;
            $res["Header_background_color"] = $Header_background_color;
            $res["Footer_background_color"] = $Footer_background_color;
            $res["Facebook_share_flag"] = $Facebook_share_flag;
            $res["Twitter_share_flag"] = $Twitter_share_flag;
            $res["Linkedin_share_flag"] = $Linkedin_share_flag;
            $res["Google_share_flag"] = $Google_share_flag;
			
            $stmt->close();
			
			
            // TODO
            // $user_id = $stmt->get_result()->fetch_assoc();
			
			
			
            // $stmt->close();
            // return $Company_id;
            return $res;

        } else {
            return NULL;
        }
		
    }	
	public function superSellerDetails() {
		//echo "---company_id---------".$_SESSION['company_id'];
		 $stmt = $this->conn->prepare("SELECT Enrollement_id,First_name, Last_name,Country,State,City,Topup_Bill_no,timezone_entry FROM igain_enrollment_master WHERE Company_id = ? AND Super_seller=1 AND Sub_seller_admin=1 AND User_id=2");
        $stmt->bind_param("s", $_SESSION['company_id']);
        if ($stmt->execute()) {
			
            $stmt->bind_result($Enrollement_id, $First_name, $Last_name,$Country,$State,$City,$Topup_Bill_no,$timezone_entry);			
            $stmt->fetch();
            $user = array();
            $user["id"] = $Enrollement_id;
            $user["fname"] = $First_name;
            $user["lname"] = $Last_name;
            $user["country"] = $Country;
            $user["state"] = $State;
            $user["city"] = $City;
            $user["topup_Bill_no"] = $Topup_Bill_no;
            $user["timezone_entry"] = $timezone_entry;
            $stmt->close();
            return $user;
			
        } else {
            return NULL;
        }
    }
	public function updateTopupBillNo($BillPara)
	{
			
		// $next_card_no=$next_card_no1+1;
		$stmt = $this->conn->prepare("UPDATE igain_enrollment_master t set t.Topup_Bill_no = ? WHERE t.Enrollement_id = ? ");
        $stmt->bind_param("si", $BillPara['billno_withyear_ref'], $BillPara['seller_id']);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function updateMemberBalance($MemberPara,$where)
	{
			
		$cols = array(); 
		foreach($MemberPara as $key=>$val) {
			$cols[] = "$key = '$val'";
		}
		
		$sql = "UPDATE igain_enrollment_master SET " . implode(', ', $cols) . " WHERE Enrollement_id=$where";		
		// print_r($sql); 	
		$stmt = $this->conn->prepare($sql);
        // $stmt->bind_param("ssi", $MemberPara['Total_topup'],$MemberPara['Total_topup'], $MemberPara['id']);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
	}
	public function updateMemberProfile($MemberPara,$where)
	{
		if (!$this->isUserExists($MemberPara['User_email_id']))
		{	
			if (!$this->isUserPhoneExists($MemberPara['Phone_no']))
			{	
				$cols = array(); 
				foreach($MemberPara as $key=>$val) {
					$cols[] = "$key = '$val'";
				}
				
				$sql = "UPDATE igain_enrollment_master SET " . implode(', ', $cols) . " WHERE Enrollement_id=$where";		
				// print_r($sql); 	
				$stmt = $this->conn->prepare($sql);
				// $stmt->bind_param("ssi", $MemberPara['Total_topup'],$MemberPara['Total_topup'], $MemberPara['id']);
				$stmt->execute();
				$num_affected_rows = $stmt->affected_rows;
				$stmt->close();
				// return $num_affected_rows > 0;
				//return USER_PROFILE_UPDATED_SUCCESSFULLY;
				if($num_affected_rows > 0)
				 {
					return USER_PROFILE_UPDATED_SUCCESSFULLY;
				 }
				 else
				 {
					 return USER_PROFILE_UPDATED_UNSUCCESSFULLY;
				 }
			}
			else 
			{
				return USER_PHONE_ALREADY_EXISTED;
			}
		}
		else 
		{
            return USER_ALREADY_EXISTED;
        }

	}
	 private function isUserExists($email) {
		// echo "---isUserExists---";
        // $stmt = $this->conn->prepare("SELECT id from users WHERE email = ?");
		$stmt = $this->conn->prepare("SELECT Enrollement_id FROM igain_enrollment_master WHERE User_email_id = ? and Company_id = ? and User_id=1");
        $stmt->bind_param("ss", $email,$_SESSION['company_id']);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }
	private function isUserPhoneExists($phone) 
	{
		$stmt = $this->conn->prepare("SELECT Enrollement_id FROM igain_enrollment_master WHERE Phone_no = ? and Company_id = ? and User_id=1");
        $stmt->bind_param("ss", $phone,$_SESSION['company_id']);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
		// echo "---num_rows---".$num_rows."----<br>";
        $stmt->close();
        return $num_rows > 0;
    }
	public function fetchEnrollmentDetails($Member_id) {
		// echo "---Member_id---------".$Member_id;
		// echo "---company_id---------".$_SESSION['company_id'];

		$stmt = $this->conn->prepare("SELECT * FROM igain_enrollment_master WHERE Company_id = ? AND Enrollement_id =?  AND User_activated=1 ");

		/* echo "SELECT * FROM igain_enrollment_master WHERE Company_id = ".$_SESSION['company_id']." AND Enrollement_id =".$Member_id."  AND User_activated=1 "; */
        $stmt->bind_param("ss", $_SESSION['company_id'],$Member_id);
		if ($stmt->execute()) {

			$res = $stmt->get_result();
			$stmt->close();
			return $res;
			
        } else {

            return NULL;
        }
    }
	public function getCompanyDetails() {
		$stmt = $this->conn->prepare("SELECT * FROM igain_company_master WHERE Company_id = ?");
        $stmt->bind_param("s", $_SESSION['company_id']);
		if ($stmt->execute()) {

			$res = $stmt->get_result();
			$stmt->close();
			return $res;
			
        } else {

            return NULL;
        }
    }
	public function getMemberhobbies($userId) {
		$stmt = $this->conn->prepare("SELECT Hobbies,Hobbie_id FROM igain_hobbies_interest JOIN igain_hobbies_master ON igain_hobbies_master.Id =igain_hobbies_interest.Hobbie_id  WHERE Company_id = ?  AND Enrollement_id =? ");
        $stmt->bind_param("ss", $_SESSION['company_id'],$userId);
        if ($stmt->execute()) {

            $res = $stmt->get_result();
			$stmt->close();
			return $res;
			
        } else {

            return NULL;
        }
    }
	public function checkMemberhobbies($Hobbieid,$userId) {
		$stmt = $this->conn->prepare("SELECT * FROM igain_hobbies_interest WHERE Company_id = ? AND Hobbie_id = ? AND Enrollement_id =? ");
       
       /*  $sql = "SELECT * FROM igain_hobbies_interest WHERE Company_id = ". $_SESSION['company_id']." AND Hobbie_id = ".$Hobbieid." AND Enrollement_id =".$userId." ";		
		print_r($sql); */	

        $stmt->bind_param("sss", $_SESSION['company_id'],$Hobbieid,$userId);
		$stmt->execute();
        $stmt->store_result();
        // echo"--num_rows-----".$stmt->num_rows;
        return $stmt->num_rows;
    }
	public function deleteMemberhobbies($Hobbieid,$userId) {
		$stmt = $this->conn->prepare("DELETE FROM igain_hobbies_interest WHERE Company_id = ? AND Hobbie_id = ? AND Enrollement_id =? ");
       
       $stmt->bind_param("sss", $_SESSION['company_id'],$Hobbieid,$userId);
		$stmt->execute();
        $stmt->store_result();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }
    public function updateMemberhobbies($MemberPara,$where)
	{
			
		$cols = array(); 
		foreach($MemberPara as $key=>$val) {
			$cols[] = "$key = '$val'";
		}
		
		$sql = "UPDATE igain_hobbies_interest SET " . implode(', ', $cols) . " WHERE Hobbie_id=$where";		
		// print_r($sql);		
		$stmt = $this->conn->prepare($sql);
        // $stmt->bind_param("ssi", $MemberPara['Total_topup'],$MemberPara['Total_topup'], $MemberPara['id']);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
	}
    public function insertMemberhobbies($TransPara)
	{
			
		$key = array_keys($TransPara);
		$val = array_values($TransPara);
		$sql = "INSERT INTO igain_hobbies_interest (" . implode(', ', $key) . ") "
			 . "VALUES ('" . implode("', '", $val) . "')";
	 	$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) {
			return SUCCESS;
			
		} else {
			return FAIL;			
		}
	}
    public function insertData($TransPara,$table)
	{
			
		$key = array_keys($TransPara);
		$val = array_values($TransPara);
		$sql = "INSERT INTO ".$table." (" . implode(', ', $key) . ") "
			 . "VALUES ('" . implode("', '", $val) . "')";
	 	$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		// print_r($sql);

		$stmt->close();
		if($result) {
			return SUCCESS;
			
		} else {
			return FAIL;			
		}
	}
    public function updateData($MemberPara,$table,$wherePara)
	{
			
		$cols = array(); 
		foreach($MemberPara as $key=>$val) {
			$cols[] = "$key = '$val'";
		}
		
        $where = array(); 
		foreach($wherePara as $key1=>$val1) {
			$where[] = "$key1 = '$val1'";
		}
        // print_r($where); 
		
		$sql = "UPDATE ". $table." SET " . implode(', ', $cols) . " WHERE ".implode(' AND ', $where)." ";		
		/*  print_r($sql);  */		
		$stmt = $this->conn->prepare($sql);
        // $stmt->bind_param("ssi", $MemberPara['Total_topup'],$MemberPara['Total_topup'], $MemberPara['id']);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
	} 
	public function update_block_points_status($MemberPara,$table,$wherePara)
	{	
		$cols = array(); 
		foreach($MemberPara as $key=>$val) {
			$cols[] = "$key = '$val'";
		}
		
        $where = array(); 
		foreach($wherePara as $key1=>$val1) {
			$where[] = "$key1 = '$val1'";
		}
		
		$sql = "UPDATE ". $table." SET " . implode(', ', $cols) . " WHERE ".implode(' AND ', $where)." ";		
		
		$stmt = $this->conn->prepare($sql);
      
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
	}
	public function get_enrollment_details($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry FROM igain_enrollment_master WHERE Enrollement_id = ? AND Company_id = ?");
        $stmt->bind_param("ss", $Enrollement_id,$Company_id);
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
	public function getSmtpDetails($Company_id) {
		$stmt = $this->conn->prepare("SELECT * FROM igain_smtp_master WHERE company_id = ?");
        $stmt->bind_param("s", $Company_id);
		if ($stmt->execute()) {
			$res = $stmt->get_result()->fetch_assoc();
			
			return $res;
			
        } else {

            return NULL;
        }
    }
	public function updateDeviceToken($MemberPara,$where)
	{		
		$cols = array(); 
		foreach($MemberPara as $key=>$val) {
			$cols[] = "$key = '$val'";
		}
				
		$sql = "UPDATE igain_enrollment_master SET " . implode(', ', $cols) . " WHERE Enrollement_id=$where";		
				 	
		$stmt = $this->conn->prepare($sql);
		
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
		$stmt->close();
			
		if($num_affected_rows > 0)
		{
			return USER_PROFILE_UPDATED_SUCCESSFULLY;
		}
		else
		{
			return USER_PROFILE_UPDATED_UNSUCCESSFULLY;
		}
	}
	public function getTierName($Tier_id) {
		 $stmt = $this->conn->prepare("SELECT Tier_name FROM igain_tier_master WHERE Tier_id = ? AND Company_id=?");
        $stmt->bind_param("ss",$Tier_id,$_SESSION['company_id']);
        if ($stmt->execute()) {
			
            $stmt->bind_result($Tier_name);			
            $stmt->fetch();
            $tier = array();
            $tier["Tier_name"] = $Tier_name;
            $stmt->close();
            return $tier;
			
        } else {
            return NULL;
        }
    }
}
?>