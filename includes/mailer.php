<?php
/**
 * TempMail Pro - SMTP Mailer (native PHP sockets, no Composer)
 */
class Mailer {
    private $host,$port,$secure,$user,$pass,$fromName,$fromEmail,$sock;

    public function __construct($host=null,$port=null,$secure=null,$user=null,$pass=null,$fromName=null,$fromEmail=null) {
        $this->host      = $host      ?? SMTP_HOST;
        $this->port      = (int)($port ?? SMTP_PORT);
        $this->secure    = $secure    ?? SMTP_SECURE;
        $this->user      = $user      ?? SMTP_USER;
        $this->pass      = $pass      ?? SMTP_PASS;
        $this->fromName  = $fromName  ?? SMTP_FROM_NAME;
        $this->fromEmail = $fromEmail ?? SMTP_FROM_EMAIL;
    }

    private function connect() {
        if (empty($this->host)) throw new Exception("SMTP_HOST is not configured in .env");
        if (empty($this->user)) throw new Exception("SMTP_USER is not configured in .env");

        $addr = ($this->secure === 'ssl') ? 'ssl://'.$this->host : $this->host;
        $ctx  = stream_context_create(['ssl'=>[
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);
        $this->sock = @stream_socket_client($addr.':'.$this->port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) throw new Exception("SMTP connect failed ($addr:{$this->port}): $errstr ($errno)");
        stream_set_timeout($this->sock, 15);
        $this->read(); // 220

        $this->cmd("EHLO localhost");
        if ($this->secure === 'tls') {
            $this->cmd("STARTTLS");
            stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
            $this->cmd("EHLO localhost");
        }
        $r = $this->cmd("AUTH LOGIN");
        if (strpos($r,'334') === false) throw new Exception("AUTH LOGIN not supported. Response: $r");
        $this->cmd(base64_encode($this->user));
        $r = $this->cmd(base64_encode($this->pass));
        if (strpos($r,'235') === false) throw new Exception("SMTP authentication failed. Check SMTP_USER/SMTP_PASS in .env. Response: $r");
    }

    private function cmd($c) {
        fwrite($this->sock, $c."\r\n");
        return $this->read();
    }

    private function read() {
        $r = '';
        while ($l = fgets($this->sock, 512)) {
            $r .= $l;
            if (isset($l[3]) && $l[3] === ' ') break;
        }
        return $r;
    }

    private function disconnect() {
        if ($this->sock) { @fwrite($this->sock,"QUIT\r\n"); @fclose($this->sock); $this->sock=null; }
    }

    public function send($to, $subject, $html, $toName='') {
        $this->connect();
        $r = $this->cmd("MAIL FROM:<{$this->fromEmail}>");
        if (strpos($r,'250')===false) throw new Exception("MAIL FROM rejected: $r");
        $r = $this->cmd("RCPT TO:<$to>");
        if (strpos($r,'250')===false && strpos($r,'251')===false) throw new Exception("RCPT TO rejected: $r");
        $this->cmd("DATA");

        $b    = md5(uniqid());
        $name = $toName ?: $to;
        $plain= strip_tags(str_replace(['<br>','<br/>','<br />','</p>'],"\n",$html));
        $hdr  = "MIME-Version: 1.0\r\n"
              . "Content-Type: multipart/alternative; boundary=\"$b\"\r\n"
              . "From: {$this->fromName} <{$this->fromEmail}>\r\n"
              . "To: $name <$to>\r\n"
              . "Subject: =?UTF-8?B?".base64_encode($subject)."?=\r\n"
              . "Date: ".date('r')."\r\n"
              . "Message-ID: <".uniqid()."@".parse_url(APP_URL,PHP_URL_HOST).">\r\n"
              . "X-Mailer: TempMailPro/2.0\r\n";

        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($plain))."\r\n"
              . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html))."\r\n"
              . "--$b--";

        fwrite($this->sock, $hdr."\r\n".$body."\r\n.\r\n");
        $r = $this->read();
        $this->disconnect();
        if (strpos($r,'250')===false) throw new Exception("Message not accepted: $r");
        return true;
    }

    public function sendOTP($to, $otp, $type='verify') {
        $app = APP_NAME; $exp = OTP_EXPIRY;
        $titles = ['verify'=>'Email Verification','reset'=>'Password Reset','login'=>'Login Code'];
        $title  = $titles[$type] ?? 'OTP Code';
        $color  = $type==='reset'?'#dc2626':'#2563eb';
        $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
<tr><td align="center">
<table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
<tr><td style="background:linear-gradient(135deg,{$color},#1e40af);padding:36px;text-align:center;">
  <h1 style="color:#fff;margin:0;font-size:26px;font-weight:900;letter-spacing:1px;">$app</h1>
  <p style="color:rgba(255,255,255,.8);margin:8px 0 0;font-size:15px;">$title</p>
</td></tr>
<tr><td style="padding:40px 36px;text-align:center;">
  <p style="color:#374151;font-size:16px;margin:0 0 28px;">Your verification code is:</p>
  <div style="background:#f0f4ff;border:2px dashed #3b82f6;border-radius:14px;padding:28px;display:inline-block;margin-bottom:24px;">
    <span style="font-size:46px;font-weight:900;letter-spacing:12px;color:#1d4ed8;font-family:monospace;">$otp</span>
  </div>
  <p style="color:#6b7280;font-size:14px;margin:0;">Expires in <strong>$exp minutes</strong>.</p>
  <p style="color:#ef4444;font-size:13px;margin:10px 0 0;">⚠ Never share this code with anyone.</p>
</td></tr>
<tr><td style="background:#f9fafb;padding:20px;text-align:center;border-top:1px solid #e5e7eb;">
  <p style="color:#9ca3af;font-size:12px;margin:0;">If you didn't request this, please ignore. &copy; $app</p>
</td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
        return $this->send($to,"$app – $title: $otp",$html);
    }

    public static function test($host,$port,$secure,$user,$pass) {
        try {
            $m = new self($host,(int)$port,$secure,$user,$pass,'Test',$user);
            $m->connect();
            $m->disconnect();
            return ['success'=>true,'message'=>'SMTP connection & authentication successful!'];
        } catch(Exception $e) {
            return ['success'=>false,'message'=>$e->getMessage()];
        }
    }
}
