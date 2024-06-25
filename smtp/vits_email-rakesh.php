<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once "vendor/autoload.php";

//PHPMailer Object
$mail = new PHPMailer(true); //Argument true in constructor enables exceptions
$mail->SMTPDebug = 3;   // Enable verbose debug output

$mail->isSMTP();   // Set mailer to use SMTP

//echo 'Here inside';



/*$mail->SMTPAuth = true;     // Enable SMTP authentication
$mail->Host = 'mail.miraclecartes.com';
$mail->Username = 'no-reply@miraclecartes.com';     
$mail->Password = '8u8IV)?r?V_U';  
$mail->SMTPSecure = 'tls';  // Enable TLS encryption, `ssl` also accepted
$mail->Port = 25; */

$mail->SMTPAuth = true;     // Enable SMTP authentication
$mail->Host = 'mail.vitspassport.com';
$mail->Username = 'noreply@vitspassport.com';     
$mail->Password = 'loyalty@stack2023';  
$mail->SMTPSecure = 'tls';  // Enable TLS encryption, `ssl` also accepted
$mail->Port = 587; 


//$mail->SMTPAuth = true;     // Enable SMTP authentication
/*$mail->Host = 'Igainspark-com.mail.protection.outlook.com';
$mail->Username = 'no-reply@igainspark.com';     
$mail->Password = 'Gaf70488';  
//$mail->SMTPSecure = 'tls';  // Enable TLS encryption, `ssl` also accepted
$mail->Port = 25; */

//to run the outlook smtp we have commented SMTPAuth and SMTPSecure both


//echo 'Here 1';

	
$mail->SMTPOptions = array(					
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					)
				); 

//echo 'Before Here 2';

//$mail->From = 'no-reply@miraclecartes.com';
$mail->From = 'noreply@vitspassport.com';
//$mail->From = 'igainspark@gmail.com';
$mail->FromName = 'VITS Passport Loyalty';
//$mail->addAddress('nileshchoudhari91@gmail.com', 'nilesh choudhari');     // Add a recipient
$mail->addAddress('rakesh.jadhav718@gmail.com', 'rakesh jadhav');     // Add a recipient

$mail->addAddress('rakesh_jadhav@hotmail.com');               // Name is optional
$mail->addAddress('rakesh@miraclecartes.com');               // Name is optional
//$mail->addAddress('amitk@miraclecartes.com');               // Name is optional
//$mail->addReplyTo('nileshc@miraclecartes.com', 'Information');

$mail->WordWrap = 50;                                 // Set word wrap to 50 characters
// $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
// $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
$mail->isHTML(true);                                  // Set email format to HTML

$mail->Subject = 'VITS PassportHere is the subject';
$mail->Body    = "This is the HTML message body <b>in bold!</b><br>";
$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

echo 'Before send function';

if(!$mail->send()) 
{
	echo 'Message could not be sent.';
	echo 'Mailer Error: ' . $mail->ErrorInfo;
} 
else 
{
	echo 'Message has been sent';
}
//echo $mail->print_debugger();
?>
