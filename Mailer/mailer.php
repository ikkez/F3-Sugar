<?php

/**
 * Mailer - A simple SMTP Mail wrapper
 *
 * The contents of this file are subject to the terms of the GNU General
 * Public License Version 3.0. You may not use this file except in
 * compliance with the license. Any of the license terms and conditions
 * can be waived if you get permission from the copyright holder.
 *
 * (c) Christian Knuth, ikkez0n3@gmail.com
 *
 * @date: 13.02.2015
 * @version 0.6.1
 */

class Mailer {

	protected
		$smtp,
		$recipients,
		$message,
		$charset;

	/**
	 * create mailer instance working with a specific charset
	 * usually one of these:
	 *      ISO-8859-1
	 *      ISO-8859-15
	 *      UTF-8
	 * @param string $enforceCharset
	 */
	public function __construct($enforceCharset='ISO-8859-15') {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		$this->charset = $enforceCharset;
		$this->smtp = new \SMTP(
			$f3->get('mailer.smtp.host'),
			$f3->get('mailer.smtp.port'),
			$f3->get('mailer.smtp.scheme'),
			$f3->get('mailer.smtp.user'),
			$f3->get('mailer.smtp.pw'));
		$this->recipients = array();
		if ($f3->exists('mailer.errors_to',$errors_to) && !empty($errors_to))
			$this->smtp->set('Errors-to', '<'.$errors_to.'>');
		if ($f3->exists('mailer.return_to',$return_to) && !empty($return_to))
			$this->smtp->set('Return-Path', '<'.$return_to.'>');
		if ($f3->exists('mailer.from_mail',$from_mail) && !empty($from_mail)) {
			$from_name = !$f3->devoid('mailer.from_name') ? $f3->get('mailer.from_name') : null;
			$this->setFrom($from_mail, $from_name);
		}
	}

	/**
	 * encode special chars if possible
	 * @param $str
	 * @return mixed
	 */
	protected function encode($str) {
		if (empty($str) || $this->charset == 'UTF-8')
			return $str;
		if (extension_loaded('iconv'))
			$out = @iconv("UTF-8", $this->charset."//IGNORE", $str);
		if (!isset($out) || !$out)
			$out = extension_loaded('mbstring')
				? mb_convert_encoding($str,$this->charset,"UTF-8")
				: utf8_decode($str);
		return $out ?: $str;
	}

	/**
	 * build email with title string
	 * @param $email
	 * @param null $title
	 * @return string
	 */
	protected function buildMail($email, $title=null) {
		return ($title?'"'.$title.'" ':'').'<'.$email.'>';
	}

	/**
	 * set encoded header value
	 * @param $key
	 * @param $val
	 */
	public function set($key, $val) {
		$this->smtp->set($key, $this->encode($val));
	}

	/**
	 * set message sender
	 * @param $email
	 * @param null $title
	 */
	public function setFrom($email, $title=null) {
		$this->set('From', $this->buildMail($email,$title));
	}

	/**
	 * add a direct recipient
	 * @param $email
	 * @param null $title
	 */
	public function addTo($email, $title=null) {
		$this->recipients['To'][$email] = $title;
	}

	/**
	 * add a carbon copy recipient
	 * @param $email
	 * @param null $title
	 */
	public function addCc($email, $title=null) {
		$this->recipients['Cc'][$email] = $title;
	}

	/**
	 * add a blind carbon copy recipient
	 * @param $email
	 * @param null $title
	 */
	public function addBcc($email, $title=null) {
		$this->recipients['Bcc'][$email] = $title;
	}

	/**
	 * reset recipients
	 * @param null $key
	 */
	public function reset($key=null) {
		if ($key) {
			$key = ucfirst($key);
			$this->smtp->clear($key);
			if (isset($this->recipients[$key]))
				unset($this->recipients[$key]);
		} else {
			$this->recipients = array();
			$this->smtp->clear('To');
			$this->smtp->clear('Cc');
			$this->smtp->clear('Bcc');
		}
	}

	/**
	 * set message in plain text format
	 * @param $message
	 */
	public function setText($message) {
		$this->message['text'] = $message;
	}

	/**
	 * set message in HTML text format
	 * @param $message
	 */
	public function setHTML($message) {
		$f3 = \Base::instance();
		// we need a clean template instance for extending it one-time
		$tmpl = new \Template();
		// create traceable jump links
		if ($f3->exists('mailer.jumplinks',$jumplink) && $jumplink)
			$tmpl->extend('a', function($node) use($f3, $tmpl) {
				if (isset($node['@attrib'])) {
					$attr = $node['@attrib'];
					unset($node['@attrib']);
				} else
					$attr = array();
				if (isset($attr['href'])) {
					if (!$f3->exists('mailer.jump_route',$ping_route))
						$ping_route = '/mailer-jump';
					$attr['href'] = $f3->get('SCHEME').'://'.$f3->get('HOST').$f3->get('BASE').
						$ping_route.'?target='.urlencode($attr['href']);
				}
				$params = '';
				foreach ($attr as $key => $value)
					$params.=' '.$key.'="'.$value.'"';
				return '<a'.$params.'>'.$tmpl->build($node).'</a>';
			});
		$message = $tmpl->build($tmpl->parse($message));
		$this->message['html'] = $message;
	}

	/**
	 * add a file attachment
	 * @param $path
	 * @param null $alias
	 * @param null $cid
	 */
	public function attachFile($path, $alias=null, $cid=null) {
		$this->smtp->attach($path,$alias,$cid);
	}

	/**
	 * send message
	 * @param $subject
	 * @return bool
	 */
	public function send($subject) {
		foreach ($this->recipients as $key => $rcpts) {
			$mails = array();
			foreach ($rcpts as $mail=>$title)
				$mails[] = $this->buildMail($mail,$title);
			$this->set($key,implode(', ',$mails));
		}
		$this->smtp->set('Subject', $this->encode($subject));
		$body = '';
		$hash=uniqid(NULL,TRUE);
		$eol="\r\n";
		if (isset($this->message['text']) && isset($this->message['html'])) {
			$this->smtp->set('Content-Type', 'multipart/alternative; boundary="'.$hash.'"');
			$body .= '--'.$hash.$eol;
			$body .= 'Content-Type: text/plain; charset='.$this->charset.$eol;
			$body .= $this->message['text'].$eol.$eol;
			$body .= '--'.$hash.$eol;
			$body .= 'Content-Type: text/html; charset='.$this->charset.$eol;
			$body .= $this->message['html'].$eol;
		} elseif (isset($this->message['text'])) {
			$this->smtp->set('Content-Type', 'text/plain; charset='.$this->charset);
			$body = $this->message['text'];
		} elseif (isset($this->message['html'])) {
			$this->smtp->set('Content-Type', 'text/html; charset='.$this->charset);
			$body = $this->message['html'];
		}
		$success = $this->smtp->send($this->encode($body));
		$f3 = \Base::instance();
		if (!$success && $f3->exists('mailer.on.failure',$fail_handler))
			$f3->call($fail_handler,array($this,$this->smtp->log()));
		return $success;
	}

	/**
	 * receive and proceed message ping
	 * @param Base $f3
	 * @param $params
	 */
	public function ping(\Base $f3, $params) {
		$hash = $params['hash'];
		// trigger ping event
		if ($f3->exists('mailer.on.ping',$ping_handler))
			$f3->call($ping_handler,array($hash));
		$img = new \Image();
		// 1x1 transparent 8bit PNG
		$img->load(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMA'.
			'AAAl21bKAAAABGdBTUEAALGPC/xhBQAAAANQTFRFAAAAp3o92gAAAAF0U'.
			'k5TAEDm2GYAAAAKSURBVAjXY2AAAAACAAHiIbwzAAAAAElFTkSuQmCC'));
		$img->render();
	}

	/**
	 * track clicked link and reroute
	 * @param Base $f3
	 */
	public function jump(\Base $f3, $params) {
		$target = $f3->get('GET.target');
		// trigger jump event
		if ($f3->exists('mailer.on.jump',$jump_handler))
			$f3->call($jump_handler,array($target,$params));
		$f3->reroute(urldecode($target));
	}

	/**
	 * init routing
	 */
	static public function initTracking() {
		/** @var \Base $f3 */
		$f3 = \Base::instance();
		if (!$f3->exists('mailer.ping_route',$ping_route))
			$ping_route = '/mailer-ping/@hash.png';
		\Base::instance()->route('GET '.$ping_route,'\Mailer->ping');

		if (!$f3->exists('mailer.jump_route',$jump_route))
			$jump_route = '/mailer-jump';
		\Base::instance()->route('GET '.$jump_route,'\Mailer->jump');
	}

}