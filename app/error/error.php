<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$errorReason ??= _translate('Internal Server Error') . ' - ';
$errorMessage ??= _translate('Sorry, something went wrong. Please try again later.');
$errorInfo ??= [];
$httpCode ??= 500;

// Show shortcuts that only make sense for a signed-in user, and only when the
// user actually holds the privilege. _isAllowed() touches the DB/session, so we
// guard it: never let a permission check blow up the error page itself.
$canSearch = !empty($_SESSION['menuItems']);
$canViewLogs = false;
if (!empty($_SESSION['userId'])) {
    try {
        $canViewLogs = _isAllowed('/admin/monitoring/log-files.php');
    } catch (\Throwable $e) {
        $canViewLogs = false;
    }
}
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
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/sts-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/sts-icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/sts-icons/favicon-16x16.png">
    <link rel="manifest" href="/assets/sts-icons/site.webmanifest">
  <?php } else { ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/lis-icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/lis-icons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/lis-icons/favicon-16x16.png">
    <link rel="manifest" href="/assets/lis-icons/site.webmanifest">
  <?php } ?>

  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/font-awesome.min.css">
  <link rel="stylesheet" href="/assets/css/AdminLTE.min.css">

  <style>
    :root {
      --err-bg-1: #eef2f8;
      --err-bg-2: #e3e9f2;
      --err-ink: #1e293b;
      --err-muted: #64748b;
      --err-border: #e2e8f0;
      --err-primary: #2563eb;
      --err-primary-dark: #1d4ed8;
      --err-danger: #dc2626;
      --err-danger-soft: #fee2e2;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      color: var(--err-ink);
      background: radial-gradient(1200px 600px at 50% -10%, #ffffff 0%, var(--err-bg-1) 45%, var(--err-bg-2) 100%);
      display: flex;
      flex-direction: column;
    }

    .error-shell {
      flex: 1 0 auto;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px 16px;
    }

    .error-card {
      position: relative;
      width: 100%;
      max-width: 720px;
      background: #fff;
      border: 1px solid var(--err-border);
      border-radius: 18px;
      box-shadow: 0 20px 50px -20px rgba(15, 23, 42, 0.35);
      padding: 40px 44px 36px;
      overflow: hidden;
    }

    /* accent bar at the very top of the card */
    .error-card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, var(--err-danger), #f59e0b);
    }

    .logout-btn {
      position: absolute;
      top: 18px;
      right: 18px;
      z-index: 10;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #fff;
      border: 1px solid var(--err-border);
      color: var(--err-muted);
      padding: 7px 14px;
      border-radius: 8px;
      text-decoration: none;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.2s ease;
    }

    .logout-btn:hover {
      color: var(--err-danger);
      border-color: var(--err-danger);
      background: var(--err-danger-soft);
      text-decoration: none;
    }

    .error-hero {
      text-align: center;
      margin-bottom: 26px;
    }

    .error-status {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 16px;
      margin-bottom: 6px;
    }

    .error-status .status-code {
      font-size: 76px;
      font-weight: 800;
      line-height: 1;
      letter-spacing: -2px;
      background: linear-gradient(135deg, var(--err-danger), #f97316);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .error-status .status-icon {
      font-size: 40px;
      color: var(--err-danger);
      opacity: 0.9;
    }

    .error-reason {
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--err-muted);
      margin: 8px 0 14px;
    }

    .error-title {
      font-size: 24px;
      font-weight: 700;
      color: var(--err-ink);
      margin: 0 0 10px;
    }

    .error-message {
      font-size: 15px;
      color: var(--err-muted);
      line-height: 1.5;
      margin: 0 auto;
      max-width: 520px;
    }

    .error-details {
      background: #f8fafc;
      border: 1px solid var(--err-border);
      border-radius: 12px;
      padding: 22px;
      margin-top: 26px;
    }

    .error-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 18px;
    }

    .meta-chip {
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
      background: #fff;
      border: 1px solid var(--err-border);
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 12px;
      font-weight: 600;
      color: var(--err-ink);
      display: inline-flex;
      align-items: center;
      gap: 7px;
      word-break: break-all;
    }

    .meta-chip i {
      color: var(--err-muted);
    }

    .meta-chip .meta-label {
      color: var(--err-muted);
      font-weight: 500;
    }

    .suggested-actions h4 {
      color: var(--err-ink);
      margin: 0 0 12px;
      font-size: 14px;
      font-weight: 700;
    }

    .suggested-actions ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .suggested-actions li {
      background: #fff;
      border: 1px solid var(--err-border);
      border-radius: 8px;
      padding: 11px 14px 11px 38px;
      margin-bottom: 8px;
      font-size: 13px;
      line-height: 1.4;
      color: var(--err-ink);
      position: relative;
      transition: all 0.2s ease;
    }

    .suggested-actions li:hover {
      border-color: var(--err-primary);
      box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12);
    }

    .suggested-actions li:before {
      content: "\2192"; /* → */
      font-weight: 700;
      position: absolute;
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--err-primary);
    }

    .suggested-actions li:last-child {
      margin-bottom: 0;
    }

    .button-container {
      margin-top: 26px;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 10px;
    }

    .action-button {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border-radius: 10px;
      padding: 11px 20px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      border: 1px solid transparent;
      transition: all 0.2s ease;
    }

    .action-button.btn-primary {
      background: var(--err-primary);
      border-color: var(--err-primary);
      color: #fff;
      box-shadow: 0 6px 16px -6px rgba(37, 99, 235, 0.6);
    }

    .action-button.btn-primary:hover {
      background: var(--err-primary-dark);
      border-color: var(--err-primary-dark);
      transform: translateY(-1px);
    }

    .action-button.btn-ghost {
      background: #fff;
      border-color: var(--err-border);
      color: var(--err-ink);
    }

    .action-button.btn-ghost:hover {
      border-color: var(--err-primary);
      color: var(--err-primary);
      transform: translateY(-1px);
    }

    .search-hint {
      margin-top: 16px;
      text-align: center;
      font-size: 12px;
      color: var(--err-muted);
    }

    .search-hint kbd {
      background: #fff;
      border: 1px solid var(--err-border);
      border-bottom-width: 2px;
      border-radius: 5px;
      padding: 2px 7px;
      font-family: 'Monaco', 'Menlo', monospace;
      font-size: 11px;
      color: var(--err-ink);
    }

    .fallback-message {
      text-align: center;
      color: var(--err-muted);
      font-size: 15px;
      line-height: 1.6;
      margin-top: 24px;
    }

    .fallback-link {
      color: var(--err-primary);
      font-weight: 600;
      text-decoration: none;
    }

    .fallback-link:hover {
      text-decoration: underline;
    }

    .error-footer {
      flex-shrink: 0;
      text-align: center;
      padding: 18px 24px;
      font-size: 12px;
      line-height: 1.5;
      color: var(--err-muted);
    }

    @media (max-width: 600px) {
      .error-card {
        padding: 34px 22px 28px;
        border-radius: 14px;
      }

      .logout-btn {
        position: static;
        margin: 0 auto 18px;
        width: fit-content;
      }

      .error-status .status-code {
        font-size: 60px;
      }

      .error-meta {
        flex-direction: column;
        align-items: stretch;
      }

      .button-container .action-button {
        flex: 1 1 100%;
        justify-content: center;
      }
    }
  </style>

</head>

<body class="<?php echo $skin ?? ''; ?>" id="capture">
  <div class="error-shell">
    <div class="error-card">

      <a href="/login/logout.php" class="logout-btn">
        <i class="fa-solid fa-right-from-bracket"></i> <?= _translate('Logout'); ?>
      </a>

      <div class="error-hero">
        <div class="error-status">
          <i class="fa-solid fa-triangle-exclamation status-icon"></i>
          <span class="status-code"><?= htmlspecialchars((string) $httpCode, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="error-reason"><?= htmlspecialchars((string) $errorReason, ENT_QUOTES, 'UTF-8'); ?></div>
        <h1 class="error-title"><?= _translate("An error occurred"); ?></h1>
        <p class="error-message"><?= htmlspecialchars((string) $errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>

      <?php if (!empty($errorInfo)): ?>
        <div class="error-details">

          <?php if (!empty($errorInfo['error_id']) || !empty($errorInfo['timestamp'])): ?>
            <div class="error-meta">
              <?php if (!empty($errorInfo['error_id'])): ?>
                <span class="meta-chip">
                  <i class="fa-solid fa-hashtag"></i>
                  <span class="meta-label"><?= _translate('Error ID'); ?>:</span>
                  <?= htmlspecialchars((string) $errorInfo['error_id'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
              <?php endif; ?>

              <?php if (!empty($errorInfo['timestamp'])): ?>
                <span class="meta-chip">
                  <i class="fa-solid fa-clock"></i>
                  <span class="meta-label"><?= _translate('Time'); ?>:</span>
                  <?= DateUtility::humanReadableDateFormat($errorInfo['timestamp'], true); ?>
                </span>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($errorInfo['suggested_actions'])): ?>
            <div class="suggested-actions">
              <h4><?= _translate('What you can try'); ?></h4>
              <ul>
                <?php foreach ($errorInfo['suggested_actions'] as $action): ?>
                  <li><?= htmlspecialchars((string) $action, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <div class="button-container">
            <?php if (!empty($errorInfo['can_retry']) && $errorInfo['can_retry']): ?>
              <a href="javascript:location.reload();" class="action-button btn-primary">
                <i class="fa-solid fa-rotate-right"></i> <?= _translate('Try Again'); ?>
              </a>
            <?php endif; ?>

            <a href="<?= $_SESSION['landingPage'] ?? "/"; ?>" class="action-button btn-ghost">
              <i class="fa-solid fa-house"></i> <?= _translate('Go to Dashboard'); ?>
            </a>

            <?php if ($canSearch): ?>
              <button type="button" id="spotlightTrigger" class="action-button btn-ghost">
                <i class="fa-solid fa-magnifying-glass"></i> <?= _translate('Search pages'); ?>
              </button>
            <?php endif; ?>

            <?php if ($canViewLogs):
              // Deep-link straight to this error in the log viewer when we have an
              // ID to search on; otherwise just open the viewer.
              $logUrl = '/admin/monitoring/log-files.php';
              if (!empty($errorInfo['error_id'])) {
                  $logUrl .= '?q=' . rawurlencode((string) $errorInfo['error_id']);
              }
            ?>
              <a href="<?= htmlspecialchars($logUrl, ENT_QUOTES, 'UTF-8'); ?>" class="action-button btn-ghost">
                <i class="fa-solid fa-file-lines"></i> <?= _translate('View Logs'); ?>
              </a>
            <?php endif; ?>
          </div>

          <?php if ($canSearch): ?>
            <div class="search-hint">
              <?= sprintf(
                _translate('Tip: press %s to search anywhere.'),
                '<kbd>Ctrl</kbd>&nbsp;+&nbsp;<kbd>K</kbd> <span style="opacity:.6">/</span> <kbd>&#8984;</kbd>&nbsp;+&nbsp;<kbd>K</kbd>'
              ); ?>
            </div>
          <?php endif; ?>

        </div>
      <?php else: ?>
        <div class="fallback-message">
          <p><?= _translate("Please contact the System Admin for further support."); ?></p>
          <p><a href="/" class="fallback-link"><?= _translate("Go to Dashboard"); ?></a></p>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <footer class="error-footer">
    <?= _translate("This project is supported by the U.S. President's Emergency Plan for AIDS Relief (PEPFAR) through the U.S. Centers for Disease Control and Prevention (CDC)."); ?>
  </footer>

  <script type="text/javascript" src="/assets/js/jquery.min.js"></script>
  <?php require_once APPLICATION_PATH . '/_spotlight.php'; ?>

</body>

</html>
