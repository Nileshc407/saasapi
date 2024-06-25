<?php
class CampaignHandler {

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
	public function getOffers() {

            /* ".date('Y-m-d H:i:s')." */

            $today=date('Y-m-d H:i:s');

		$stmt = $this->conn->prepare("SELECT Loyalty_name,From_date,Till_date,Loyalty_at_transaction,Loyalty_at_value,discount,First_name,Last_name FROM igain_loyalty_master JOIN igain_enrollment_master ON igain_enrollment_master.Enrollement_id =igain_loyalty_master.Seller  WHERE igain_loyalty_master.Company_id = ?  AND Active_flag = 1 AND '".$today."' BETWEEN From_date  AND Till_date ");

        


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

}
?>