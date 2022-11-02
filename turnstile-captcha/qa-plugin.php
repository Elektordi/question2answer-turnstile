<?php
if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}


qa_register_plugin_module('captcha', 'qa-turnstile-captcha.php', 'qa_turnstile_captcha', 'Turnstile');
