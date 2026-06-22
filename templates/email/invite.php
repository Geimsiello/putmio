<?php
/** @var string $appName */
/** @var string $inviteUrl */
/** @var int $expiresHours */
$appName = $appName ?? 'PutMio';
$inviteUrl = $inviteUrl ?? '';
$expiresHours = $expiresHours ?? 72;
$safeApp = putmio_e($appName);
$safeUrl = putmio_e($inviteUrl);
$ctaLabel = putmio_e(putmio_lang('invite_email_cta'));
$headline = putmio_e(putmio_lang('invite_email_headline', ['app' => $appName]));
$intro = putmio_e(putmio_lang('invite_email_intro', ['app' => $appName]));
$expires = putmio_e(putmio_lang('invite_email_expires', ['hours' => (string) $expiresHours]));
$footer = putmio_e(putmio_lang('invite_email_footer', ['app' => $appName]));
$linkHint = putmio_e(putmio_lang('invite_email_link_hint'));
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <title><?= $safeApp ?> — Invito</title>
</head>
<body style="margin:0;padding:0;background-color:#0b1326;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#dae2fd;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#0b1326;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background-color:#171f33;border:1px solid rgba(70,69,84,0.35);border-radius:16px;overflow:hidden;">
          <tr>
            <td style="padding:32px 32px 24px;text-align:center;background:linear-gradient(180deg,#222a3d 0%,#171f33 100%);">
              <div style="display:inline-block;width:40px;height:40px;background-color:#8083ff;border-radius:12px;line-height:40px;font-size:20px;color:#0d0096;">&#9654;</div>
              <div style="margin-top:12px;font-size:24px;font-weight:800;color:#c0c1ff;letter-spacing:-0.02em;"><?= $safeApp ?></div>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <h1 style="margin:0 0 16px;font-size:22px;line-height:30px;font-weight:700;color:#dae2fd;"><?= $headline ?></h1>
              <p style="margin:0 0 24px;font-size:16px;line-height:24px;color:#c7c4d7;"><?= $intro ?></p>
              <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 24px;">
                <tr>
                  <td style="border-radius:8px;background-color:#8083ff;">
                    <a href="<?= $safeUrl ?>" style="display:inline-block;padding:14px 28px;font-size:16px;font-weight:700;color:#0d0096;text-decoration:none;"><?= $ctaLabel ?></a>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 12px;font-size:14px;line-height:20px;color:#908fa0;"><?= $expires ?></p>
              <p style="margin:0;font-size:13px;line-height:20px;color:#908fa0;"><?= $linkHint ?></p>
              <p style="margin:12px 0 0;font-size:12px;line-height:18px;word-break:break-all;color:#c0c1ff;"><a href="<?= $safeUrl ?>" style="color:#c0c1ff;"><?= $safeUrl ?></a></p>
            </td>
          </tr>
          <tr>
            <td style="padding:20px 32px 28px;border-top:1px solid rgba(70,69,84,0.25);font-size:12px;line-height:18px;color:#908fa0;text-align:center;">
              <?= $footer ?>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
