<?php
class OffersHandler 
{
    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() 
	{
        require_once dirname(__FILE__) . '/DbConnect.php';
        $db = new DbConnect();
        $this->conn = $db->connect();

		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();		
    }
	public function getOffers() 
	{
        $today=date('Y-m-d H:i:s');

		$stmt = $this->conn->prepare("SELECT Offer_name,From_date,Till_date,Buy_item,Free_item,First_name,Last_name FROM igain_offer_master JOIN igain_enrollment_master ON igain_enrollment_master.Enrollement_id =igain_offer_master.Seller_id  WHERE igain_offer_master.Company_id = ?  AND Active_flag = 1 AND '".$today."' BETWEEN From_date  AND Till_date ");
        
        $stmt->bind_param("s", $_SESSION['company_id']);
       
        if ($stmt->execute()) {

            $res = $stmt->get_result();
           
			$stmt->close();
			return $res;
			
        } else {

            return NULL;
        }
    }
	public function getOffersImages() 
	{
		$stmt = $this->conn->prepare("SELECT Sequence,Spl_Image FROM igain_brand_offer_images WHERE Company_id = ?");
        
        $stmt->bind_param("s", $_SESSION['company_id']);
       
        if ($stmt->execute()) {

            $result = $stmt->get_result();
			while($row = $result->fetch_assoc())
			{
				$res[] = $row;
			}
			return $res;
        } else {

            return NULL;
        }
    }   
}
?>