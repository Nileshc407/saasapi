<?php
class CommOffersHandler {

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

        $today=date('Y-m-d');

		$stmt = $this->conn->prepare("SELECT communication_plan,description,From_date,Till_date,First_name,Last_name FROM igain_seller_communication JOIN igain_enrollment_master ON igain_enrollment_master.Enrollement_id =igain_seller_communication.seller_id  WHERE igain_seller_communication.Company_id = ?  AND activate = 'yes' AND '".$today."' BETWEEN From_date  AND Till_date ");
        
        
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