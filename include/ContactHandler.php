<?php
class ContactHandler
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
		$api_key = $this->phash->string_decrypt($api_key);		
        $stmt = $this->conn->prepare("SELECT Company_id,Company_name,Redemptionratio,phonecode,Company_address,Company_primary_email_id,Company_contactus_email_id,Company_primary_phone_no,Website,Cust_website,Company_secondary_phone_no,Facebook_link,Twitter_link,Linkedin_link,Googlplus_link,Social5_link from igain_company_master JOIN igain_country_master ON igain_country_master.id=igain_company_master.Country WHERE Company_id = ?");
		
        $stmt->bind_param("s", $api_key);		
        if ($stmt->execute()) 
		{
			$res = array();
            $stmt->bind_result($Company_id,$Company_name,$Redemptionratio,$phonecode,$Company_address,$Company_primary_email_id,$Company_contactus_email_id,$Company_primary_phone_no,$Website,$Cust_website,$Company_secondary_phone_no,$Facebook_link,$Twitter_link,$Linkedin_link,$Googlplus_link,$Social5_link);
            $stmt->fetch();
			
           
            $stmt->fetch();
            $res["Company_id"] = $Company_id;
            $res["Company_name"] = $Company_name;
            $res["Redemptionratio"] = $Redemptionratio;
            $res["phonecode"] = $phonecode;
            $res["Company_address"] = $Company_address;
            $res["Company_primary_email_id"] = $Company_primary_email_id;
			$res["Company_contactus_email_id"] = $Company_contactus_email_id;
			$res["Company_primary_phone_no"] = $Company_primary_phone_no;
			$res["Website"] = $Website;
			$res["Cust_website"] = $Cust_website;
			$res["Company_secondary_phone_no"] = $Company_secondary_phone_no;
			$res["Facebook_link"] = $Facebook_link;
			$res["Twitter_link"] = $Twitter_link;
			$res["Linkedin_link"] = $Linkedin_link;
			$res["Googlplus_link"] = $Googlplus_link;
			$res["Social5_link"] = $Social5_link;
            $stmt->close();
            return $res;
        } 
		else 
		{
            return NULL;
        }
    }
	public function superSellerDetails() 
	{
		 $stmt = $this->conn->prepare("SELECT Enrollement_id,First_name, Last_name,Country,State,City,Topup_Bill_no,Purchase_Bill_no,timezone_entry FROM igain_enrollment_master WHERE Company_id = ? AND Super_seller=1 AND Sub_seller_admin=1 AND User_id=2");
        $stmt->bind_param("s", $_SESSION['company_id']);
        if ($stmt->execute()) {
			
            $stmt->bind_result($Enrollement_id, $First_name, $Last_name,$Country,$State,$City,$Topup_Bill_no,$Purchase_Bill_no,$timezone_entry);			
            $stmt->fetch();
            $user = array();
            $user["id"] = $Enrollement_id;
            $user["fname"] = $First_name;
            $user["lname"] = $Last_name;
            $user["country"] = $Country;
            $user["state"] = $State;
            $user["city"] = $City;
            $user["topup_Bill_no"] = $Topup_Bill_no;
            $user["Purchase_Bill_no"] = $Purchase_Bill_no;
            $user["timezone_entry"] = $timezone_entry;
            $stmt->close();
            return $user;
			
        } else {
            return NULL;
        }
    }
	public function getMemberDetails($membershipid,$phoneno,$Company_id) 
	{
		$phoneno=$this->phash->string_encrypt($phoneno);
	
        $stmt = $this->conn->prepare("SELECT e.Enrollement_id, e.First_name, e.Last_name, e.User_email_id,e.Card_id,e.User_id,e.User_pwd,e.pinno,e.Current_balance,e.Blocked_points,e.Debit_points,e.Phone_no,e.Total_reddems,e.total_purchase,e.Total_topup_amt,e.Company_id ,e.timezone_entry,e.Tier_id,t.Tier_redemption_ratio FROM igain_enrollment_master as e JOIN igain_tier_master as t ON t.Tier_id = e.Tier_id WHERE (e.Card_id = ? OR e.Phone_no = ? ) and e.Company_id = ? and e.User_id=1");
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
	public function Insert_contact_feedback($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_contact_us_tbl (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
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
	public function update_member_balance($MemberPara,$Enrollment_id)
	{
		$stmt = $this->conn->prepare("UPDATE igain_enrollment_master t set t.Current_balance = ?,t.Total_reddems = ? WHERE t.Enrollement_id = ? ");
        $stmt->bind_param("ssi", $MemberPara['Current_balance'],$MemberPara['Total_reddems'],$Enrollment_id);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function get_company_details($Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT * FROM igain_company_master WHERE Company_id=?");
        $stmt->bind_param("s",$Company_id);
        if ($stmt->execute()) 
		{
            $company = $stmt->get_result()->fetch_assoc();
            return $company;
        } 
		else 
		{
            return NULL;
        }
    }
}
?>