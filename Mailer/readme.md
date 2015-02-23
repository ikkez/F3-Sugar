# Mailer

This is a little mail plugin that contains:

  - SMTP plugin wrapper
  - easily send plain text, html or both text & html mixed content mails
  - convenient methods to add one or multiple recipients 
  - encode special chars for mails with ISO charset
  - ping and jump methods for tracking read and click events in your html mails

Configurable via config file:

```ini
[mailer]
; smtp config
smtp.host = smtp.domain.com
smtp.port = 25
smtp.user = info@domain.com
smtp.pw = 123456789!
; scheme could be SSL or TLS
smtp.scheme =

; optional mail settings
from_mail = info@domain.com
from_name = Mario Bros.
errors_to = errors@domain.com
return_to = bounce@domain.com
on.failure = \Controller\Mail::logError
on.ping = \Controller\Mail::traceMail
on.jump = \Controller\Mail::traceClick
; automatically create jump links in all <a> tags
jumplinks = true
```

To initialize the tracking routes, call this before `$f3->run()`:

```php
$f3->config('mailer_config.ini');
// ...
Mailer::initTracking();
// ...
$f3->run();
```

A little sample looks like this:

```php
function send_test($email, $title=null) {
	$mail = new \Mailer();
	$mail->addTo($email, $title);
	$mail->setText('This is a Test.');
	$mail->setHTML('This is a <b>Test</b>.');
	$mail->send('Test Mail Subject');
}
```

To add the ping tracking image, put this in your html mail body:

```html
<img src="http://mydomain.com/mailer-ping/AH2cjDWb.png" />
```

The file name should be a unique hash you can use to identify the recipient who read your mail.

The tracking methods could look like this:

```php
static public function logError($mailer, $log) {
	$logger = new \Log('logs/smtp_'.date('Y_m_d').'.log');
	$logger->write($log);
}

static public function traceMail($hash) {
	// your mail $hash is being read
}

static public function traceClick($target) {
	// someone clicked $target link
}
```