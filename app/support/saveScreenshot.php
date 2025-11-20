<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use PHPMailer\PHPMailer\PHPMailer;
use App\Registries\ContainerRegistry;


$tableName = "support";

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {
	// File upload folder
	$uploadDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . "support";
	if (isset($_POST['image']) && trim((string) $_POST['image']) !== "" && trim((string) $_POST['supportId']) !== "") {
		$supportId = base64_decode((string) $_POST['supportId']);

		MiscUtility::makeDirectory($uploadDir . DIRECTORY_SEPARATOR . $supportId);

		$data = $_POST['image'];
		$fileName = 'screenshot-' . uniqid() . '.png';
		$uploadPath = realpath($uploadDir . DIRECTORY_SEPARATOR . $supportId . DIRECTORY_SEPARATOR . $fileName);

		// remove "data:image/png;base64,"
		$uri = substr((string) $data, strpos((string) $data, ",", 1));
		// save to file
		file_put_contents($uploadPath, base64_decode($uri));

		$fData = ['screenshot_file_name' => $fileName];
		$db->where('support_id', $supportId);
		$db->update($tableName, $fData);
		$response['message'] = _translate("Thank you. Your message has been submitted.");
	} elseif (trim((string) $_POST['supportId']) !== "") {
		$supportId = base64_decode((string) $_POST['supportId']);
		$response['message'] = _translate("Thank you. Your message has been submitted.");
	}

	//Send mail to support
	$supportEmail = $general->getGlobalConfig('support_email');
	if (!empty($supportEmail)) {
		$sQuery = "SELECT * FROM support WHERE support_id = ?";
		$sResult = $db->rawQuery($sQuery, [$supportId]);
		if (isset($sResult[0]['support_id']) && trim((string) $sResult[0]['support_id']) !== "") {
			$feedback = $sResult[0]['feedback'];
			$feedbackUrl = $sResult[0]['feedback_url'];

			//get system config values
			$smtpEmail = $general->getSystemConfig('sup_email');
			$smtpPassword = $general->getSystemConfig('sup_password');

			if (isset($smtpEmail) && trim((string) $smtpEmail) !== "" && trim((string) $smtpPassword) !== "") {
				//Create a new PHPMailer instance
				$mail = new PHPMailer();
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
				$xplodAddress = explode(",", (string) $supportEmail);
				$counter = count($xplodAddress);
				for ($to = 0; $to < $counter; $to++) {
					$mail->addAddress($xplodAddress[$to]);
				}

				if (trim((string) $sResult[0]['upload_file_name']) !== "") {
					$file_to_attach = $uploadDir . DIRECTORY_SEPARATOR . $supportId . DIRECTORY_SEPARATOR . $sResult[0]['upload_file_name'];
					if (file_exists($file_to_attach)) {
						$mail->AddAttachment($file_to_attach);
					}
				}
				if (trim((string) $sResult[0]['screenshot_file_name']) !== "") {
					$uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $supportId . DIRECTORY_SEPARATOR . $sResult[0]['screenshot_file_name'];
					if (file_exists($uploadPath)) {
						$mail->AddAttachment($uploadPath);
					}
				}

				$message = '';
				if (isset($feedback) && trim((string) $feedback) !== "") {
					$feedback = (nl2br((string) $feedback));
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
				$mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
				if ($mail->send()) {
					$db->where('support_id', $supportId);
					$db->update($tableName, ['status' => 'sent']);
					$response['status'] = 1;
					$response['message'] = _translate("Thank you. Your message has been submitted.");
				}
			}
		}
	}
	// Return response
	echo json_encode($response);
} catch (Exception $e) {
	LoggerUtility::log("error", $e->getMessage(), [
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'trace' => $e->getTraceAsString(),
	]);
}
