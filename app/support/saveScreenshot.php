<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
$tableName = "support";

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

try {
	// File upload folder
	$uploadDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . "support";
	if (isset($_POST['image']) && trim($_POST['image']) != "" && trim($_POST['supportId']) != "") {
		$supportId = base64_decode($_POST['supportId']);

		// if (!file_exists($uploadDir) && !is_dir($uploadDir)) {
		// 	mkdir($uploadDir, 0777, true);
		// }

		if (
			!file_exists($uploadDir . DIRECTORY_SEPARATOR . $supportId)
			&& !is_dir($uploadDir . DIRECTORY_SEPARATOR . $supportId)
		) {
			mkdir($uploadDir . DIRECTORY_SEPARATOR . $supportId, 0777, true);
		}

		$data = $_POST['image'];
		$fileName = 'screenshot-' . uniqid() . '.png';
		$uploadPath = realpath($uploadDir . DIRECTORY_SEPARATOR . $supportId . DIRECTORY_SEPARATOR . $fileName);

		// remove "data:image/png;base64,"
		$uri =  substr($data, strpos($data, ",", 1));
		// save to file
		file_put_contents($uploadPath, base64_decode($uri));

		$fData = array(
			'screenshot_file_name' => $fileName
		);
		$db->where('support_id', $supportId);
		$db->update($tableName, $fData);
		$response['message'] = _("Thank you. Your message has been submitted.");
	} elseif (trim($_POST['supportId']) != "") {
		$supportId = base64_decode($_POST['supportId']);
		$response['message'] = _("Thank you. Your message has been submitted.");
	}

	//Send mail to support
	$supportEmail = $general->getGlobalConfig('support_email');
	if (!empty($supportEmail)) {
		$sQuery = "SELECT * FROM support WHERE support_id = $supportId";
		$sResult = $db->rawQuery($sQuery);
		if (isset($sResult[0]['support_id']) && trim($sResult[0]['support_id']) != "") {
			$feedback = $sResult[0]['feedback'];
			$feedbackUrl = $sResult[0]['feedback_url'];

			//get system config values
			$smtpEmail = $general->getSystemConfig('sup_email');
			$smtpPassword = $general->getSystemConfig('sup_password');

			if (isset($smtpEmail) && trim($smtpEmail) != "" && trim($smtpPassword) != "") {
				//Create a new PHPMailer instance
				$mail = new PHPMailer\PHPMailer\PHPMailer();
				//Tell PHPMailer to use SMTP
				$mail->isSMTP();
				//Enable SMTP debugging
				// 0 = off (for production use)
				// 1 = client messages
				// 2 = client and server messages
				$mail->SMTPDebug = 2;
				//Ask for HTML-friendly debug output
				$mail->Debugoutput = 'html';
				//Set the hostname of the mail server
				$mail->Host = 'smtp.gmail.com';
				//Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
				$mail->Port = 587;
				//Set the encryption system to use - ssl (deprecated) or tls
				$mail->SMTPSecure = 'tls';
				//Whether to use SMTP authentication
				$mail->SMTPAuth = true;
				$mail->SMTPKeepAlive = true;
				//Username to use for SMTP authentication - use full email address for gmail
				$mail->Username = $smtpEmail;
				//Password to use for SMTP authentication
				$mail->Password = $smtpPassword;
				//Set who the message is to be sent from
				$mail->setFrom($smtpEmail);

				$mail->Subject = "Support";

				//Set To EmailId(s)
				$xplodAddress = explode(",", $supportEmail);
				for ($to = 0; $to < count($xplodAddress); $to++) {
					$mail->addAddress($xplodAddress[$to]);
				}

				if (trim($sResult[0]['upload_file_name']) != "") {
					$file_to_attach = $uploadDir . DIRECTORY_SEPARATOR . $supportId . DIRECTORY_SEPARATOR . $sResult[0]['upload_file_name'];
					if (file_exists($file_to_attach)) {
						$mail->AddAttachment($file_to_attach);
					}
				}
				if (trim($sResult[0]['screenshot_file_name']) != "") {
					$uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $supportId . DIRECTORY_SEPARATOR . $sResult[0]['screenshot_file_name'];
					if (file_exists($uploadPath)) {
						$mail->AddAttachment($uploadPath);
					}
				}

				$message = '';
				if (isset($feedback) && trim($feedback) != "") {
					$feedback = (nl2br($feedback));
					$message = "<table cellpadding='0' cellspacing='0' style='width:95%;' border='1'>";
					$message .= "<tr>";
					$message .= "<th style='width:15%;'>Feedback</th>";
					$message .= "<td>" . $feedback . "</td>";
					$message .= "</tr>";
					$message .= "<tr>";
					$message .= "<th>Feedback Url</th>";
					$message .= "<td><a href='" . $feedbackUrl . "'>" . $feedbackUrl . "</a></td>";
					$message .= "</tr>";
					$message .= "</table>";
				}

				$mail->msgHTML($message);
				$mail->SMTPOptions = array(
					'ssl' => array(
						'verify_peer' => false,
						'verify_peer_name' => false,
						'allow_self_signed' => true
					)
				);
				if ($mail->send()) {
					$db->where('support_id', $supportId);
					$db->update($tableName, array('status' => 'sent'));
					$response['status'] = 1;
					$response['message'] = _("Thank you. Your message has been submitted.");
				}
			}
		}
	}
	// Return response
	echo json_encode($response);
} catch (Exception $exc) {
	error_log($exc->getMessage());
	error_log($exc->getTraceAsString());
}
