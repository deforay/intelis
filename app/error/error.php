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
    .content-wrapper small {
      font-size: 1.4em !important;
      color: #555;
    }

    .content-wrapper a {
      font-size: 0.9em !important;
      text-decoration: underline;
    }

    .error-details {
      background: #f8f9fa;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      padding: 25px;
      margin: 25px 0;
      text-align: left;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .error-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 15px;
    }

    .error-id-section {
      flex: 1;
      min-width: 200px;
      min-height: 70px;
    }

    .error-time-section {
      flex: 1;
      text-align: right;
      min-width: 200px;
      min-height: 70px;
    }

    .error-id,
    .error-time {
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
      color:rgb(192, 21, 24);
      padding: 8px 12px;
      border-radius: 4px;
      display: inline-block;
      margin: 5px 0;
      font-size: 0.95em;
      font-weight: 600;
      border: 1px solid rgb(253, 227, 232);
      word-break: break-all;
    }

    .suggested-actions {
      text-align: left;
      margin: 25px auto;
      max-width: 600px;
    }

    .suggested-actions h4 {
      color: #495057;
      margin-bottom: 15px;
      font-size: 1.1em;
      font-weight: 600;
    }

    .suggested-actions ul {
      list-style-type: none;
      padding: 0;
    }

    .suggested-actions li {
      padding: 10px 0;
      border-bottom: 1px solid #e9ecef;
      font-size: 1em;
      line-height: 1.4;
    }

    .suggested-actions li:last-child {
      border-bottom: none;
    }

    .suggested-actions li:before {
      content: "→ ";
      color: #007bff;
      font-weight: bold;
      margin-right: 8px;
    }

    .button-container {
      margin-top: 25px;
      text-align: center;
    }

    .retry-button {
      background: #007bff;
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      margin: 8px 8px;
      text-decoration: none !important;
      display: inline-block;
      font-weight: 500;
      transition: all 0.2s ease;
      box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
    }

    .retry-button:hover {
      background: #0056b3;
      color: white;
      text-decoration: none;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
    }

    .retry-button i {
      margin-right: 6px;
    }

    /* Enhanced error title styling */
    .error-title {
      color: #dc3545;
      text-align: center;
      font-size: 6em;
      font-weight: 500;
      margin-bottom: 20px;
      text-shadow: 0 2px 4px rgba(220, 53, 69, 0.1);
    }

    .error-code-line {
      color: #555;
      font-weight: bold;
      font-size: 1.4em;
      margin-bottom: 10px;
    }

    .error-message-line {
      color: #555;
      font-weight: bold;
      font-size: 1.4em;
      margin-bottom: 25px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .error-header {
        flex-direction: column;
        text-align: center;
      }

      .error-time-section {
        text-align: center;
      }

      .content-wrapper {
        padding: 0 25px !important;
      }

      .error-title {
        font-size: 4em !important;
      }

      .button-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
      }

      .retry-button {
        width: 200px;
      }
    }
  </style>

</head>

<body class="<?php echo $skin ?? ''; ?>" id="capture">
  <div class="wrapper">
    <div class="content-wrapper" style="margin:0;min-height:700px !important;padding:0 50px;">
      <section class="">
        <div class="row">
          <div class="col-xs-12" style="text-align: center;">
            <br><br><br><br>

            <h2 class="error-title">
              <span class="fa fa-exclamation-triangle"></span>
              <?= _translate("An error occurred"); ?>
            </h2>

            <h3 class="error-code-line">
              <?= _translate("Error Code") . " : " . ($httpCode ?? '500') . " - " . $errorReason; ?>
            </h3>

            <h3 class="error-message-line">
              <?= htmlspecialchars((string) $errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </h3>

            <?php if (!empty($errorInfo)) : ?>
              <div class="error-details">

                <div class="error-header">
                  <?php if (!empty($errorInfo['error_id'])) : ?>
                    <div class="error-id-section">
                      <span class="error-id"><?= _translate('Error ID'); ?> : <?= htmlspecialchars($errorInfo['error_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($errorInfo['timestamp'])) : ?>
                    <div class="error-time-section">
                      <span class="error-time"><?= _translate('Time'); ?> : <?= DateUtility::humanReadableDateFormat($errorInfo['timestamp'], true); ?></span>
                    </div>
                  <?php endif; ?>
                </div>

                <?php if (!empty($errorInfo['suggested_actions'])) : ?>
                  <div class="suggested-actions">
                    <h4><?= _translate('What you can try'); ?>:</h4>
                    <ul>
                      <?php foreach ($errorInfo['suggested_actions'] as $action) : ?>
                        <li><?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>

                <div class="button-container">
                  <?php if (!empty($errorInfo['can_retry']) && $errorInfo['can_retry']) : ?>
                    <a href="javascript:location.reload();" class="retry-button">
                      <i class="fa fa-refresh"></i> <?= _translate('Try Again'); ?>
                    </a>
                  <?php endif; ?>

                  <a href="<?= $_SESSION['landingPage'] ?? "/"; ?>" class="retry-button">
                    <i class="fa fa-home"></i> <?= _translate('Go to Dashboard'); ?>
                  </a>
                </div>

              </div>
            <?php else : ?>
              <!-- Fallback for when errorInfo is not available -->
              <small>
                <?= _translate("Please contact the System Admin for further support."); ?>
              </small>
              <br><br>
              <small>
                <a href="/"><?= _translate("Go to Dashboard"); ?></a>
              </small>
            <?php endif; ?>

          </div>
        </div>
      </section>
    </div>
  </div>

  <footer class="main-footer" style="margin:0;min-height:100px !important;text-align:center;">
    <small>
      <?= _translate("This project is supported by the U.S. President's Emergency Plan for AIDS Relief (PEPFAR) through the U.S. Centers for Disease Control and Prevention (CDC)."); ?>
    </small>
  </footer>

  <script>
    // Auto-hide sensitive information after some time (optional security measure)
    setTimeout(function() {
      const errorId = document.querySelector('.error-id');
      if (errorId && window.location.protocol === 'https:') {
        // Only in production/secure environments
        // errorId.style.display = 'none';
      }
    }, 300000); // 5 minutes
  </script>
</body>

</html>
