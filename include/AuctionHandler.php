<?php
class AuctionHandler {

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
	public function getAuction() {

        $today=date('Y-m-d H:i:s');

		$stmt = $this->conn->prepare("SELECT igain_auction_master.Auction_id,igain_auction_master.Auction_name,igain_auction_master.From_date,igain_auction_master.To_date,igain_auction_master.End_time,igain_auction_master.Prize,igain_auction_master.Min_bid_value,igain_auction_master.Min_increment,igain_auction_master.Prize_image,igain_auction_master.Prize_description,First_name,Last_name FROM igain_auction_master JOIN igain_enrollment_master ON igain_enrollment_master.Enrollement_id =igain_auction_master.Create_user_id WHERE igain_auction_master.Company_id = ?  AND igain_auction_master.Active_flag = 1 AND '".$today."' BETWEEN From_date  AND To_date ");

       /* echo "SELECT igain_auction_master.Auction_name,igain_auction_master.From_date,igain_auction_master.To_date,igain_auction_master.End_time,igain_auction_master.Prize,igain_auction_master.Min_bid_value,igain_auction_master.Min_increment,igain_auction_master.Prize_image,igain_auction_master.Prize_description,igain_auction_winner.Bid_value,First_name,Last_name FROM igain_auction_master LEFT JOIN igain_auction_winner ON igain_auction_winner.Auction_id=igain_auction_master.Auction_id  JOIN igain_enrollment_master ON igain_enrollment_master.Enrollement_id =igain_auction_master.Seller_id  WHERE igain_auction_master.Company_id = ".$_SESSION['company_id']."  AND igain_auction_master.Active_flag = 1 AND '".$today."' BETWEEN From_date  AND To_date";  */
        
        $stmt->bind_param("s", $_SESSION['company_id']);
       
        if ($stmt->execute()) {

            $res = $stmt->get_result();
            // print_r($res);
			$stmt->close();
			return $res;
			
        } else {

            return NULL;
        }
    }   
	
    
    public function Fetch_Auction_Max_Bid_Value($id) {

        $today=date('Y-m-d H:i:s');

		$stmt = $this->conn->prepare("SELECT MAX(Bid_value) as Bid_value FROM igain_auction_winner LEFT JOIN igain_auction_master ON igain_auction_master.Auction_id=igain_auction_winner.Auction_id WHERE  
        igain_auction_winner.Winner_flag = 0 AND igain_auction_winner.Company_id = ? AND igain_auction_winner.Auction_id = ? ");
		
		/* $stmt = $this->conn->prepare("SELECT MAX(Bid_value) as Bid_value ,Min_increment,Min_bid_value,igain_auction_master.Auction_id,igain_auction_master.Prize FROM igain_auction_winner LEFT JOIN igain_auction_master ON igain_auction_master.Auction_id=igain_auction_winner.Auction_id  WHERE  
        igain_auction_winner.Winner_flag=0 AND igain_auction_master.Company_id = ?  AND igain_auction_master.Active_flag = 1 AND igain_auction_master.Auction_id = ? "); */


        $stmt->bind_param("ss", $_SESSION['company_id'],$id);
        $stmt->execute();
        $stmt->store_result();
		// echo "stmt-------".$stmt;
        // echo "---num_rows---".$stmt->num_rows."----<br>";
        if ($stmt->num_rows > 0) {
            
            // $user = $stmt->get_result()->fetch_assoc();
            // $stmt->bind_result($Bid_value,$Min_increment,$Min_bid_value,$Auction_id,$Prize);			
            $stmt->bind_result($Bid_value);			
            $stmt->fetch(); 
            $res = array();
            $res["Bid_value"] = $Bid_value;
            // $res["Min_increment"] = $Min_increment;
            // $res["Min_bid_value"] = $Min_bid_value;
            // $res["id"] = $Auction_id;
            // $res["Prize"] = $Prize;
            $stmt->close(); 
			
            return $res;
        } else {
            return NULL;
        }
    }
	public function Fetch_Auction_Datails($id) 
	{
		$stmt = $this->conn->prepare("SELECT Min_bid_value,Min_increment,Auction_id,Prize FROM igain_auction_master WHERE  
        igain_auction_master.Active_flag = 1 AND igain_auction_master.Company_id = ? AND igain_auction_master.Auction_id = ? ");

        $stmt->bind_param("ss", $_SESSION['company_id'],$id);
        $stmt->execute();
        $stmt->store_result();
	
        if ($stmt->num_rows > 0) {
            
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($Min_bid_value,$Min_increment,$Auction_id,$Prize);			
		
            $stmt->fetch(); 
            $res = array();
            $res["Min_increment"] = $Min_increment;
            $res["Min_bid_value"] = $Min_bid_value;
            $res["id"] = $Auction_id;
            $res["Prize"] = $Prize;
            $stmt->close(); 
			
            return $res;
        } else {
            return NULL;
        }
    }   
    public function Fetch_Auction_previous_Bid_Value($Auction_id) {

        $today=date('Y-m-d H:i:s');
		$stmt = $this->conn->prepare("SELECT Id,Enrollment_id,Bid_value FROM igain_auction_winner  WHERE Company_id = ?  AND Active_flag = 0 AND Winner_flag = 0 AND Auction_id = ? ORDER BY Id DESC limit 1,1");

        /* echo "SELECT Id,Enrollment_id, Bid_value FROM igain_auction_winner  WHERE Company_id =".$_SESSION['company_id']."  AND Active_flag = 0 AND Winner_flag = 0 AND Auction_id =".$Auction_id."  ORDER BY Id DESC limit 1,1 ";  */
       

        $stmt->bind_param("ss", $_SESSION['company_id'],$Auction_id);
        $stmt->execute();
        $stmt->store_result();
        // echo "-------num_rows---".$stmt->num_rows."----<br>";
        if ($stmt->num_rows > 0) {
            
            $stmt->bind_result($Id,$Enrollment_id,$Bid_value);			
            $stmt->fetch();
            $res = array();
            $res["Id"] = $Id;           
            $res["Enrollment_id"] = $Enrollment_id;           
            $res["Bid_value"] = $Bid_value;
            $stmt->close(); 
            return $res;

        } else {

            return NULL;
        }
    }   

}
?>