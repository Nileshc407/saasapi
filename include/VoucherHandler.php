<?php
class VoucherHandler
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
	public function Validate_discount_voucher($Card_id,$Company_id,$Discount_voucher_code)
	{
		$today = date("Y-m-d");
		
		$stmt = $this->conn->prepare("SELECT * FROM igain_giftcard_tbl WHERE Card_id = ? AND Company_id = ? AND Gift_card_id = ? AND Valid_till >= ? AND Card_balance > 0");
        $stmt->bind_param("ssss", $Card_id,$Company_id,$Discount_voucher_code,$today);
        if ($stmt->execute()) 
		{
            $voucher = $stmt->get_result()->fetch_assoc();
            return $voucher;
        } 
		else 
		{
            return NULL;
        }			
	}
	public function Get_lowest_sent_vouchers($Enrollement_id,$Company_id,$Voucher_code)
	{
		$today = date("Y-m-d");
	
		// $stmt = $this->conn->prepare("SELECT G.Gift_card_id,G.Card_balance,G.Valid_till,G.Discount_percentage,B.Voucher_code,B.Company_merchandize_item_code, B.Quantity as Voucher_Qty,B.Cost_price as Voucher_Cost_price,of.Offer_description,of.Offer_name FROM igain_giftcard_tbl as G LEFT JOIN igain_company_send_voucher as B ON G.Gift_card_id = B.Voucher_code AND G.Company_id = B.Company_id JOIN igain_offer_master as of ON of.Offer_code = B.Offer_code and of.Company_id = B.Company_id WHERE G.Card_id = ? AND G.Company_id = ? AND G.Gift_card_id = ? AND G.Valid_till >= ? AND G.Payment_Type_id = 997 AND B.Voucher_id = 0 AND B.Offer_code != '' AND G.Card_balance >= 0");
		
		// $stmt = $this->conn->prepare("SELECT Voucher_code,Company_merchandize_item_code,Quantity as Voucher_Qty,Cost_price as Voucher_Cost_price FROM igain_company_send_voucher WHERE Enrollement_id = ? AND Company_id = ? AND Voucher_code = ? AND Valid_till >= ? AND Voucher_id = 0 GROUP BY Voucher_code,Company_merchandize_item_code ORDER BY Voucher_code ASC,Cost_price ASC");
		
		$stmt = $this->conn->prepare("SELECT Voucher_code,Company_merchandize_item_code,Quantity as Voucher_Qty,Cost_price as Voucher_Cost_price FROM igain_company_send_voucher WHERE Enrollement_id = ? AND Company_id = ? AND Voucher_code = ? AND Valid_till >= ? GROUP BY Voucher_code,Company_merchandize_item_code ORDER BY Voucher_code ASC,Cost_price ASC");
		
		//GROUP BY B.Voucher_code,B.Company_merchandize_item_code ORDER BY B.Voucher_code ASC,B.Cost_price ASC 
		//GROUP BY B.Voucher_code,B.Company_merchandize_item_code ORDER BY B.Voucher_code ASC,B.Cost_price ASC 
		
		$stmt->bind_param("ssss",$Enrollement_id,$Company_id,$Voucher_code,$today);
		
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
	public function Get_items($Company_id,$Cust_enrollement_id,$Outlet_no,$ChannelCompanyId) 
	{	
        $stmt = $this->conn->prepare("SELECT * FROM igain_pos_temp_cart WHERE Company_id = ? AND Enrollment_id = ? AND Seller_id = ? AND Channel_id = ?"); 
		
        $stmt->bind_param("ssss", $Company_id,$Cust_enrollement_id,$Outlet_no,$ChannelCompanyId);
		
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
}
?>