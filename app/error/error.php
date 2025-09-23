<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$errorReason ??= _translate('Internal Server Error') . ' - ';
$errorMessage ??= _translate('Sorry, something went wrong. Please try again later.');
$errorInfo ??= [];
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['APP_LOCALE'] ?? 'en_US'; ?>">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title><?= _translate('ERROR'); ?> | <?= $general->isSTSInstance() ? 'STS' : 'LIS'; ?></title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <meta name="viewport" content="width=1024">

  <?php if (!empty($_SESSION['instance']['type']) && $general->isSTSInstance()) { ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/vlsts-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/vlsts-icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/vlsts-icons/favicon-16x16.png">
    <link rel="manifest" href="/assets/vlsts-icons/site.webmanifest">
  <?php } else { ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/vlsm-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/vlsm-icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/vlsm-icons/favicon-16x16.png">
    <link rel="manifest" href="/assets/vlsm-icons/site.webmanifest">
  <?php } ?>

  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/font-awesome.min.css">
  <link rel="stylesheet" href="/assets/css/AdminLTE.min.css">

  <style>
    body {
      background-color: #f4f6f9;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      color: #495057;
      min-height: 100vh;
    }

    .error-container {
      min-height: 100vh;
      display: table;
      width: 100%;
      background: #f4f6f9;
    }

    .error-content {
      display: table-cell;
      vertical-align: middle;
      text-align: center;
      padding: 10px;
    }

    .error-card {
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      padding: 20px 25px;
      margin: 0 auto;
      max-width: 700px;
      position: relative;
    }

    .logout-btn {
      position: absolute;
      top: 20px;
      right: 20px;
      background-color: #dc3545;
      border: 1px solid #dc3545;
      color: white;
      padding: 8px 16px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .logout-btn:hover {
      background-color: #c82333;
      border-color: #bd2130;
      color: white;
      text-decoration: none;
      transform: translateY(-1px);
    }

    .error-icon-wrapper {
      margin-bottom: 20px;
    }

    .error-icon {
      font-size: 48px;
      color: #dc3545;
      margin-bottom: 12px;
      opacity: 0.9;
    }

    .error-title {
      font-size: 22px;
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 15px;
      line-height: 1.3;
    }

    .error-code {
      font-size: 14px;
      color: #6c757d;
      margin-bottom: 6px;
      font-weight: 500;
    }

    .error-message {
      font-size: 14px;
      color: #495057;
      margin-bottom: 25px;
      line-height: 1.4;
    }

    .error-details {
      background: #f8f9fa;
      border: 1px solid #e9ecef;
      border-radius: 6px;
      padding: 20px;
      margin: 20px 0;
      text-align: left;
    }

    .error-meta {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 15px;
      flex-wrap: wrap;
      gap: 15px;
    }

    .error-id-section,
    .error-time-section {
      flex: 1;
      min-width: 200px;
    }

    .error-time-section {
      text-align: right;
    }

    .error-id,
    .error-time {
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
      background: #fff;
      color: #d73527;
      padding: 6px 10px;
      border-radius: 4px;
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      border: 1px solid #f8d7da;
      word-break: break-all;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .suggested-actions {
      margin: 15px 0;
    }

    .suggested-actions h4 {
      color: #495057;
      margin-bottom: 12px;
      font-size: 15px;
      font-weight: 600;
      text-align: center;
    }

    .suggested-actions ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .suggested-actions li {
      background: white;
      border: 1px solid #e9ecef;
      border-radius: 4px;
      padding: 10px 15px;
      margin-bottom: 6px;
      font-size: 13px;
      line-height: 1.3;
      position: relative;
      padding-left: 35px;
      transition: all 0.2s ease;
    }

    .suggested-actions li:hover {
      border-color: #007bff;
      box-shadow: 0 1px 4px rgba(0, 123, 255, 0.1);
    }

    .suggested-actions li:before {
      content: "→";
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #007bff;
      font-weight: bold;
      font-size: 14px;
    }

    .suggested-actions li:last-child {
      margin-bottom: 0;
    }

    .button-container {
      margin-top: 20px;
      text-align: center;
    }

    .action-button {
      background: #007bff;
      border: 1px solid #007bff;
      color: white;
      padding: 10px 20px;
      border-radius: 4px;
      text-decoration: none;
      display: inline-block;
      margin: 4px 6px;
      font-weight: 500;
      font-size: 13px;
      transition: all 0.3s ease;
      box-shadow: 0 2px 6px rgba(0, 123, 255, 0.2);
    }

    .action-button:hover {
      background: #0056b3;
      border-color: #004085;
      color: white;
      text-decoration: none;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
    }

    .action-button.btn-secondary {
      background: #6c757d;
      border-color: #6c757d;
      box-shadow: 0 2px 6px rgba(108, 117, 125, 0.2);
    }

    .action-button.btn-secondary:hover {
      background: #545b62;
      border-color: #4e555b;
      box-shadow: 0 3px 8px rgba(108, 117, 125, 0.3);
    }

    .action-button i {
      margin-right: 6px;
    }

    .fallback-message {
      color: #6c757d;
      font-size: 16px;
      line-height: 1.6;
    }

    .fallback-link {
      color: #007bff;
      text-decoration: underline;
      font-weight: 500;
    }

    .fallback-link:hover {
      color: #0056b3;
      text-decoration: none;
    }

    .footer {
      background: #2c3e50;
      color: #bdc3c7;
      text-align: center;
      padding: 20px;
      font-size: 12px;
      line-height: 1.4;
      margin-top: 5px;
    }

    /* Responsive design */
    @media (max-width: 768px) {
      .error-card {
        margin: 15px;
        padding: 25px 20px;
        border-radius: 6px;
      }

      .logout-btn {
        position: static;
        display: block;
        width: fit-content;
        margin: 0 auto 20px auto;
      }

      .error-icon {
        font-size: 40px;
      }

      .error-title {
        font-size: 20px;
      }

      .error-meta {
        flex-direction: column;
        gap: 10px;
      }

      .error-time-section {
        text-align: left;
      }

      .button-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
      }

      .action-button {
        width: 180px;
        margin: 3px 0;
      }

      .suggested-actions li {
        padding-left: 30px;
      }
    }

    @media (max-width: 480px) {
      .error-card {
        margin: 10px;
        padding: 20px 15px;
      }

      .error-title {
        font-size: 18px;
      }

      .error-code,
      .error-message {
        font-size: 13px;
      }
    }
  </style>

</head>

<body class="<?php echo $skin ?? ''; ?>" id="capture">
  <div class="error-container">
    <div class="error-content">
      <div class="error-card">
        
        <a href="/login/logout.php" class="logout-btn">
          <i class="fa fa-sign-out"></i> <?= _translate('Logout'); ?>
        </a>

        <div class="error-icon-wrapper">
          <div class="error-icon">
            <i class="fa fa-exclamation-triangle"></i>
          </div>
          <h1 class="error-title"><?= _translate("An error occurred"); ?></h1>
        </div>

        <div class="error-code">
          <?= _translate("Error Code") . " : " . ($httpCode ?? '500') . " - " . $errorReason; ?>
        </div>

        <div class="error-message">
          <?= htmlspecialchars((string) $errorMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <?php if (!empty($errorInfo)) : ?>
          <div class="error-details">

            <?php if (!empty($errorInfo['error_id']) || !empty($errorInfo['timestamp'])) : ?>
              <div class="error-meta">
                <?php if (!empty($errorInfo['error_id'])) : ?>
                  <div class="error-id-section">
                    <div class="error-id">
                      <?= _translate('Error ID'); ?>: <?= htmlspecialchars($errorInfo['error_id'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($errorInfo['timestamp'])) : ?>
                  <div class="error-time-section">
                    <div class="error-time">
                      <?= _translate('Time'); ?>: <?= DateUtility::humanReadableDateFormat($errorInfo['timestamp'], true); ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($errorInfo['suggested_actions'])) : ?>
              <div class="suggested-actions">
                <h4><?= _translate('What you can try'); ?></h4>
                <ul>
                  <?php foreach ($errorInfo['suggested_actions'] as $action) : ?>
                    <li><?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>

            <div class="button-container">
              <?php if (!empty($errorInfo['can_retry']) && $errorInfo['can_retry']) : ?>
                <a href="javascript:location.reload();" class="action-button">
                  <i class="fa fa-refresh"></i> <?= _translate('Try Again'); ?>
                </a>
              <?php endif; ?>

              <a href="<?= $_SESSION['landingPage'] ?? "/"; ?>" class="action-button btn-secondary">
                <i class="fa fa-home"></i> <?= _translate('Go to Dashboard'); ?>
              </a>
            </div>

          </div>
        <?php else : ?>
          <div class="fallback-message">
            <p><?= _translate("Please contact the System Admin for further support."); ?></p>
            <p><a href="/" class="fallback-link"><?= _translate("Go to Dashboard"); ?></a></p>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <footer class="footer">
    <?= _translate("This project is supported by the U.S. President's Emergency Plan for AIDS Relief (PEPFAR) through the U.S. Centers for Disease Control and Prevention (CDC)."); ?>
  </footer>

</body>

</html>