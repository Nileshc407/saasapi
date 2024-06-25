<?php
class NotifyHandler
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
        $stmt = $this->conn->prepare("SELECT Company_id,Company_name,phonecode,Redemptionratio,Currency_name,Points_used_gift_card,Minimum_gift_card_amount ,Gift_card_validity_days from igain_company_master JOIN igain_country_master ON igain_country_master.id=igain_company_master.Country WHERE Company_id = ?");
		
        $stmt->bind_param("s", $api_key);		
        if ($stmt->execute()) 
		{
			$res = array();
            $stmt->bind_result($Company_id,$Company_name,$phonecode,$Redemptionratio,$Currency_name,$Points_used_gift_card,$Minimum_gift_card_amount,$Gift_card_validity_days);
            $stmt->fetch();
			
            // $stmt->bind_result($id, $task, $status, $created_at);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["Company_id"] = $Company_id;
            $res["Company_name"] = $Company_name;
            $res["phonecode"] = $phonecode;
            $res["Redemptionratio"] = $Redemptionratio;
            $res["Currency_name"] = $Currency_name;
            $res["Points_used_gift_card"] = $Points_used_gift_card;
            $res["Minimum_gift_card_amount"] = $Minimum_gift_card_amount;
            $res["Gift_card_validity_days"] = $Gift_card_validity_days;
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
	
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry FROM igain_enrollment_master WHERE (Card_id = ? OR Phone_no = ? ) AND Company_id = ? AND User_id=1");
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
	public function Get_unread_notifications($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT igain_cust_notification.Id,igain_cust_notification.Seller_id,igain_cust_notification.Customer_id,igain_cust_notification.User_email_id,igain_cust_notification.Communication_id,igain_cust_notification.Offer,igain_cust_notification.Offer_description,igain_cust_notification.Open_flag,igain_cust_notification.Date,igain_enrollment_master.Enrollement_id,igain_enrollment_master.First_name,igain_enrollment_master.Middle_name,igain_enrollment_master.Last_name,igain_company_master.Company_name FROM igain_cust_notification JOIN igain_enrollment_master ON igain_enrollment_master.Enrollement_id = igain_cust_notification.Customer_id JOIN igain_company_master ON igain_company_master.Company_id = igain_cust_notification.Company_id WHERE igain_cust_notification.Customer_id = ? and igain_cust_notification.Company_id = ? and  igain_cust_notification.Open_flag = 0 and  igain_cust_notification.Active_flag = 1 ORDER BY igain_cust_notification.Id DESC"); 
		
        $stmt->bind_param("ss", $Enrollement_id,$Company_id);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$notifications[] = $row;
			}
			return $notifications;
        }
		else 
		{
            return NULL;
        } 
    }
	public function Get_read_notifications($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT igain_cust_notification.Id,igain_cust_notification.Seller_id,igain_cust_notification.Customer_id,igain_cust_notification.User_email_id,igain_cust_notification.Communication_id,igain_cust_notification.Offer,igain_cust_notification.Offer_description,igain_cust_notification.Open_flag,igain_cust_notification.Date,igain_enrollment_master.Enrollement_id,igain_enrollment_master.First_name,igain_enrollment_master.Middle_name,igain_enrollment_master.Last_name,igain_company_master.Company_name FROM igain_cust_notification JOIN igain_enrollment_master ON igain_enrollment_master.Enrollement_id = igain_cust_notification.Customer_id JOIN igain_company_master ON igain_company_master.Company_id = igain_cust_notification.Company_id WHERE igain_cust_notification.Customer_id = ? and igain_cust_notification.Company_id = ? and  igain_cust_notification.Open_flag = 1 and  igain_cust_notification.Active_flag = 1 ORDER BY igain_cust_notification.Id DESC"); 
		
        $stmt->bind_param("ss", $Enrollement_id,$Company_id);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$notifications[] = $row;
			}
			return $notifications;
        }
		else 
		{
            return NULL;
        } 
    }
	public function FetchNotifications($NotifyId,$Company_id,$Enrollement_id) 
	{
        $stmt = $this->conn->prepare("SELECT * FROM igain_cust_notification WHERE Id = ? and Company_id = ? and Customer_id = ?");
        $stmt->bind_param("sss", $NotifyId,$Company_id,$Enrollement_id);
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
	public function Update_Notification($notification_id,$Company_id,$Enrollement_id)
	{
		$Open_flag = 1;
		$stmt = $this->conn->prepare("UPDATE igain_cust_notification set Open_flag = ? WHERE Id = ? and Company_id = ? and Customer_id = ?");
        $stmt->bind_param("siii", $Open_flag, $notification_id,$Company_id,$Enrollement_id);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function Delete_Notification($notification_id,$Company_id,$Enrollement_id)
	{
		$Active_flag = 0;
		$stmt = $this->conn->prepare("UPDATE igain_cust_notification set Active_flag = ? WHERE Id = ? and Company_id = ? and Customer_id = ?");
        $stmt->bind_param("siii", $Active_flag, $notification_id,$Company_id,$Enrollement_id);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
}
?>