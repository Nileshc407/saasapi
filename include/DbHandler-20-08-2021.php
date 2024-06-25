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
	public function fetchEnrollmentDetails($Member_id) {
		// echo "---Member_id---------".$Member_id;
		// echo "---company_id---------".$_SESSION['company_id'];

		$stmt = $this->conn->prepare("SELECT * FROM igain_enrollment_master WHERE Company_id = ? AND Enrollement_id =?  AND User_activated=1 ");
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

}
?>