<?php
class NotificationHandler {

    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() {
        // echo"---in---UserHandler---222--";
        require_once dirname(__FILE__) . '/DbConnect.php';
       
        // opening db connection
        $db = new DbConnect();
		// print_r($db);
        $this->conn = $db->connect();

		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
    }
	public function insertNotification($NotiPara) {
		
        // echo "---insertNotification-----";

		$key = array_keys($NotiPara);
		$val = array_values($NotiPara);
		$sql = "INSERT INTO igain_cust_notification (" . implode(', ', $key) . ") ". "VALUES ('" . implode("', '", $val) . "')";		
        
            //  echo "---insertNotification---sql--".$sql;
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
	public function Insert_notification($TransPara) 
	{
		$stmt = $this->conn->prepare("INSERT INTO igain_cust_notification (Company_id,Seller_id,Customer_id,User_email_id,Communication_id,Offer,Offer_description,Offer_description1,Open_flag,Date,Active_flag) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("sssssssssss",$TransPara['Company_id'],$TransPara['Seller_id'],$TransPara['Customer_id'],$TransPara['User_email_id'],$TransPara['Communication_id'],$TransPara['Offer'],$TransPara['Offer_description'],$TransPara['Offer_description'],$TransPara['Open_flag'],$TransPara['Date'],$TransPara['Active_flag']);			
		$result = $stmt->execute();
		
        $notification_id = $stmt->insert_id;
		$stmt->close();
	
		if($result) {
			return $notification_id;
			
		} else {
			return false;			
		}			  
    }
}
?>