<?php
class RedemptionHandler
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
        $stmt = $this->conn->prepare("SELECT Company_id,Company_name,phonecode,Redemptionratio,Currency_name,Block_points_flag,Symbol_of_currency from igain_company_master JOIN igain_country_master ON igain_country_master.id=igain_company_master.Country WHERE Company_id = ?");
		
        $stmt->bind_param("s", $api_key);		
        if ($stmt->execute()) 
		{
			$res = array();
            $stmt->bind_result($Company_id,$Company_name,$phonecode,$Redemptionratio,$Currency_name,$Block_points_flag,$Symbol_of_currency);
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
			$res["Company_block_points_flag"] = $Block_points_flag;
			$res["Symbol_of_currency"] = $Symbol_of_currency;
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
	
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry,Tier_id FROM igain_enrollment_master WHERE (Card_id = ? OR Phone_no = ? ) and Company_id = ? and User_id=1");
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
	public function get_all_items($Company_id,$Cust_Tier_id) 
	{
		$Todays_date=date("Y-m-d");
        $stmt = $this->conn->prepare("SELECT a.* FROM igain_company_merchandise_catalogue as a JOIN igain_merchandize_item_tier_child as b ON b.Merchandize_item_code = a.Company_merchandize_item_code WHERE a.Company_id = ? and a.Valid_from <= ? and a.Valid_till >= ? and b.Tier_id = ? and a.show_item = 1 and a.Active_flag = 1 and a.Billing_price != 0 and a.Billing_price_in_points != 0 and a.Ecommerce_flag = 0"); 
		
        $stmt->bind_param("ssss", $Company_id,$Todays_date,$Todays_date,$Cust_Tier_id);
	
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
	public function get_all_items_filter($Company_id,$filtervalue,$Cust_Tier_id) 
	{
		$Todays_date=date("Y-m-d");
        $stmt = $this->conn->prepare("SELECT i.* FROM igain_company_merchandise_catalogue as i JOIN igain_merchandize_category as c ON c.Merchandize_category_id = i.Merchandize_category_id JOIN igain_merchandize_item_tier_child as b ON b.Merchandize_item_code = i.Company_merchandize_item_code WHERE i.Company_id = ? and i.Valid_from <= ? and i.Valid_till >= ? and b.Tier_id = ? and i.show_item = 1 and i.Active_flag = 1 and i.Billing_price != 0 and i.Billing_price_in_points != 0 and i.Ecommerce_flag = 0 and (c.Merchandize_category_name LIKE '%".$filtervalue."%' OR i.Merchandize_item_name LIKE '%".$filtervalue."%')"); 
		
        $stmt->bind_param("ssss", $Company_id,$Todays_date,$Todays_date,$Cust_Tier_id);
		
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
	public function Get_item_partner_branch($ItemCode,$Company_id,$Partner_id) 
	{
        $stmt = $this->conn->prepare("SELECT Branch_code FROM igain_merchandize_item_child WHERE Merchandize_item_code = ? and Partner_id = ? and Company_id = ?");
        $stmt->bind_param("sss", $ItemCode,$Partner_id,$Company_id);
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
	public function Insert_Redeem_Items_at_Transaction($insert_data) 
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
		
        $stmt = $this->conn->prepare("SELECT * FROM igain_giftcard_tbl WHERE Company_id = ? and Card_id = ? and Valid_till >= 
? and Update_user_id = 0 and  Payment_Type_id IN(99,997,998)"); //Payment_Type_id = 99 Discount Vouchers
		
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
	public function Get_voucher_id($Voucher_code,$Company_id,$Enrollement_id) 
	{
        $stmt = $this->conn->prepare("SELECT Voucher_id,Offer_code FROM igain_company_send_voucher WHERE Voucher_code = ? and Company_id = ? and Enrollement_id = ?");
        $stmt->bind_param("sss", $Voucher_code,$Company_id,$Enrollement_id);
        if ($stmt->execute()) 
		{
            $voucher_details = $stmt->get_result()->fetch_assoc();
            return $voucher_details;
        } 
		else 
		{
            return NULL;
        }
    }
	public function Get_voucher_details($Voucher_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT Voucher_code,Voucher_name,Voucher_description,Item_image1 FROM igain_company_voucher_catalogue WHERE Voucher_id = ? and Company_id = ?");
        $stmt->bind_param("ss", $Voucher_id,$Company_id);
        if ($stmt->execute()) 
		{
            $voucherDetails = $stmt->get_result()->fetch_assoc();
            return $voucherDetails;
        } 
		else 
		{
            return NULL;
        }
    }
	public function Get_voucher_details2($Offer_code,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT Offer_name,Offer_description FROM igain_offer_master WHERE Offer_code = ? and Company_id = ?");
        $stmt->bind_param("ss", $Offer_code,$Company_id);
        if ($stmt->execute()) 
		{
            $voucherDetails = $stmt->get_result()->fetch_assoc();
            return $voucherDetails;
        } 
		else 
		{
            return NULL;
        }
    }
	public function get_member_product_vouchers($Card_id,$Company_id,$Item_code) 
	{
		$Todays_date=date("Y-m-d");
		
		$stmt = $this->conn->prepare("SELECT igain_giftcard_tbl.Gift_card_id,igain_giftcard_tbl.Card_value,igain_giftcard_tbl.Card_balance,igain_giftcard_tbl.Valid_till,igain_giftcard_tbl.Discount_percentage from igain_giftcard_tbl JOIN igain_company_send_voucher ON igain_company_send_voucher.Voucher_code=igain_giftcard_tbl.Gift_card_id WHERE igain_giftcard_tbl.Card_id = ? and igain_giftcard_tbl.Company_id =? and igain_company_send_voucher.Company_merchandize_item_code =? and igain_giftcard_tbl.Valid_till >=? and igain_giftcard_tbl.Payment_Type_id = 997");
		  
        $stmt->bind_param("ssss", $Card_id,$Company_id,$Item_code,$Todays_date);
		
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
	public function get_member_product_inprecentage_vouchers($Card_id,$Company_id,$item_code,$Quantity)
	{
		$today = date("Y-m-d");
		
		$stmt = $this->conn->prepare("SELECT G.Gift_card_id,G.Card_balance,G.Valid_till,G.Discount_percentage,B.Company_merchandize_item_code,B.Quantity,B.Cost_price as Reduce_product_amt FROM igain_giftcard_tbl as G JOIN igain_company_send_voucher as B ON G.Gift_card_id = B.Voucher_code WHERE G.Card_id = ? AND G.Company_id = ? AND G.Valid_till >= ? AND B.Company_merchandize_item_code = ? AND B.Quantity <= ? AND G.Payment_Type_id = 997 AND G.Card_balance >= 0");
		
		$stmt->bind_param("sssss",$Card_id,$Company_id,$today,$item_code,$Quantity);
		
		if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$items_vouchers[] = $row;
			}
			return $items_vouchers;
        }
		else 
		{
            return NULL;
        } 
	}
	public function check_block_points_bill_no($order_no,$outlet_no,$Company_id,$Bill_date_time)
	{
		$start_date=date("Y-m-d 00:00:00", strtotime($Bill_date_time)); 
		$end_date=date("Y-m-d 23:59:59", strtotime($Bill_date_time));
		
		$stmt = $this->conn->prepare("SELECT Order_no FROM igain_block_points WHERE Order_no = ? AND Outlet_id = ? AND Company_id = ? AND Creation_date BETWEEN '".$start_date."' AND '".$end_date."' AND Status=0");
		
		$stmt->bind_param("sss", $order_no,$outlet_no,$Company_id);

		$stmt->execute();
	
		$stmt->store_result();
		
		$num_rows = $stmt->num_rows; 
		return $num_rows;
	}
	public function get_outlet_details($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Phone_no,Company_id ,timezone_entry,State,City,Zipcode,Country,timezone_entry,Super_seller,Sub_seller_admin,Sub_seller_Enrollement_id,Purchase_Bill_no,Topup_Bill_no,Seller_Redemptionratio FROM igain_enrollment_master WHERE Enrollement_id = ? AND Company_id = ? AND User_id = 2 AND User_activated = 1");
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
	public function Insert_block_points($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_block_points (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
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
	public function get_block_points_details($order_no,$outlet_no,$Company_id,$Enrollement_id,$points,$lv_date_time) 
	{
		$start_date=date("Y-m-d 00:00:00", strtotime($lv_date_time)); 
		$end_date=date("Y-m-d 23:59:59", strtotime($lv_date_time));
		
		//AND Creation_date BETWEEN '".$start_date."' AND '".$end_date."'
		
        $stmt = $this->conn->prepare("SELECT Enrollment_id,Outlet_id,Order_no,Points,Points_value,Status,Status_dec,Company_id,Creation_date FROM igain_block_points WHERE Enrollment_id = ? AND Company_id = ? AND Outlet_id = ? AND Order_no = ? AND Points = ? AND Status = 0");
        $stmt->bind_param("sssss", $Enrollement_id,$Company_id,$outlet_no,$order_no,$points);
        if ($stmt->execute()) 
		{
            $block_points = $stmt->get_result()->fetch_assoc();
            return $block_points;
        } 
		else 
		{
            return NULL;
        }
    }
}
?>