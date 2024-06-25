<?php
class GiftCardHandler
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
        $stmt = $this->conn->prepare("SELECT Company_id,Company_name,phonecode,Redemptionratio,Currency_name,Points_used_gift_card,Minimum_gift_card_amount ,Gift_card_validity_days from igain_company_master JOIN igain_country_master ON igain_country_master.id=igain_company_master.Country WHERE Company_username = ?");
		
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
	public function get_all_items($Company_id) 
	{
		$Todays_date=date("Y-m-d");
        $stmt = $this->conn->prepare("SELECT * FROM igain_company_merchandise_catalogue WHERE Company_id = ? and Valid_from <= ? and Valid_till >= ? and show_item = 1 and Active_flag = 1 and Link_to_Member_Enrollment_flag = 0 and Send_once_year = 0 and Send_other_benefits = 0 and Billing_price != 0 and Billing_price_in_points != 0"); //and Ecommerce_flag = 0
		
        $stmt->bind_param("sss", $Company_id,$Todays_date,$Todays_date);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$items[] = $row;
			}
			return $items;
        }
		else 
		{
            return NULL;
        } 
    }
	public function Get_item_details($ItemCode,$Company_id) 
	{
		$Todays_date=date("Y-m-d");
		
        $stmt = $this->conn->prepare("SELECT * FROM igain_company_merchandise_catalogue WHERE Company_merchandize_item_code = ? and Company_id = ? and Valid_from <= ? and Valid_till >= ? and Active_flag = 1");
        $stmt->bind_param("ssss", $ItemCode,$Company_id,$Todays_date,$Todays_date);
        if ($stmt->execute()) 
		{
            $item_details = $stmt->get_result()->fetch_assoc();
            return $item_details;
        } 
		else 
		{
            return NULL;
        }
    }
	public function Insert_giftcard_purchase_transaction($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
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
	public function Insert_gift_card($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_giftcard_tbl (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
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
	public function updatePurchaseBillno($BillPara,$seller_id)
	{
		$stmt = $this->conn->prepare("UPDATE igain_enrollment_master set Purchase_Bill_no = ? WHERE Enrollement_id = ? ");
        $stmt->bind_param("si", $BillPara['Purchase_Bill_no'], $seller_id);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function Get_discount_vouchers($Card_id,$Company_id) 
	{
		$Todays_date=date("Y-m-d");
		
        $stmt = $this->conn->prepare("SELECT * FROM igain_giftcard_tbl WHERE Company_id = ? and Card_id = ? and Valid_till >= ? and Card_balance > 0 and  Payment_Type_id = 99"); //Payment_Type_id = 99 Discount Vouchers
		
        $stmt->bind_param("sss", $Company_id,$Card_id,$Todays_date);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$vouchers[] = $row;
			}
			return $vouchers;
        }
		else 
		{
            return NULL;
        } 
    }
	public function Get_gift_cards($Card_id,$Company_id) 
	{
		$Todays_date=date("Y-m-d");
		
        $stmt = $this->conn->prepare("SELECT * FROM igain_giftcard_tbl WHERE Company_id = ? and Card_id = ? and Valid_till >= ? and Card_balance > 0 and  Payment_Type_id != 99 and  Payment_Type_id != 997 and  Payment_Type_id != 998"); //Payment_Type_id = 99 Discount Vouchers
		
        $stmt->bind_param("sss", $Company_id,$Card_id,$Todays_date);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$vouchers[] = $row;
			}
			return $vouchers;
        }
		else 
		{
            return NULL;
        } 
    }
	public function get_outlet_details($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Phone_no,Company_id ,timezone_entry,State,City,Zipcode,Country,timezone_entry,Super_seller,Sub_seller_admin,Sub_seller_Enrollement_id,Purchase_Bill_no,Seller_Redemptionratio FROM igain_enrollment_master WHERE Enrollement_id = ? AND Company_id = ? AND User_id = 2 AND User_activated = 1");
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
	public function Validate_gift_card($Company_id,$Giftcard_No,$CardId)
	{
		$today = date("Y-m-d");
		
		$stmt = $this->conn->prepare("SELECT * FROM igain_giftcard_tbl WHERE Company_id = ? AND Gift_card_id = ? AND Valid_till >= ? AND Card_id = ? AND Card_balance > 0 AND Payment_Type_id IN(1,2,3,4,5,6,7)"); 	
		
		$stmt->bind_param("ssss", $Company_id,$Giftcard_No,$today,$CardId);
		
		if ($stmt->execute()) 
		{
            $details = $stmt->get_result()->fetch_assoc();
            return $details;
        } 
		else 
		{
            return NULL;
        }		
	}
}
?>