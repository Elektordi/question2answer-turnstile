<?php

class qa_turnstile_captcha
{
	private $directory;
	private $errorCodeMessages;

	public function load_module($directory, $urltoroot)
	{
		$this->directory = $directory;

		// human-readable error messages (though these are not currently displayed anywhere)
		$this->errorCodeMessages = array(
			'missing-input-secret' => 'The secret parameter is missing.',
			'invalid-input-secret' => 'The secret parameter is invalid or malformed.',
			'missing-input-response' => 'The response parameter is missing.',
			'invalid-input-response' => 'The response parameter is invalid or malformed.',
		);
	}

	public function admin_form()
	{
		$saved = false;

		if (qa_clicked('turnstile_save_button')) {
			qa_opt('turnstile_public_key', qa_post_text('turnstile_public_key_field'));
			qa_opt('turnstile_private_key', qa_post_text('turnstile_private_key_field'));

			$saved = true;
		}

		$pub = trim(qa_opt('turnstile_public_key'));
		$pri = trim(qa_opt('turnstile_private_key'));

		$error = null;
		if (!strlen($pub) || !strlen($pri)) {
			$error = 'To use Turnstile, you must <a href="https://developers.cloudflare.com/turnstile/get-started/" target="_blank">sign up</a> to get these keys.';
		}

		$form = array(
			'ok' => $saved ? 'Turnstile settings saved' : null,

			'fields' => array(
				'public' => array(
					'label' => 'Turnstile Site key:',
					'value' => $pub,
					'tags' => 'name="turnstile_public_key_field"',
				),

				'private' => array(
					'label' => 'Turnstile Secret key:',
					'value' => $pri,
					'tags' => 'name="turnstile_private_key_field"',
					'error' => $error,
				),
			),

			'buttons' => array(
				array(
					'label' => 'Save Changes',
					'tags' => 'name="turnstile_save_button"',
				),
			),
		);

		return $form;
	}

	/**
	 * Only allow turnstile if the keys are set up (new turnstile has no special requirements)
	 */
	public function allow_captcha()
	{
		$pub = trim(qa_opt('turnstile_public_key'));
		$pri = trim(qa_opt('turnstile_private_key'));

		return strlen($pub) && strlen($pri);
	}

	/**
	 * turnstile HTML - we actually return nothing because the new turnstile requires 'explicit rendering'
	 * via JavaScript when we have multiple Captchas per page. It also auto-detects the user's language.
	 */
	public function form_html(&$qa_content, $error)
	{
		$pub = qa_opt('turnstile_public_key');

		// onload handler
		$qa_content['script_lines'][] = array(
			'function turnstile_load(elemId) {',
			'  if (grecaptcha) {',
			'    grecaptcha.render(document.getElementById(elemId), {',
			'      "sitekey": ' . qa_js($pub),
			'    });',
			'  }',
			'}',
			'function turnstile_onload() {',
			'  turnstile_load("qa_captcha_div_1");',
			'}',
		);

		$qa_content['script_src'][] = 'https://challenges.cloudflare.com/turnstile/v0/api.js?compat=recaptcha&onload=turnstile_onload&render=explicit';

		return '';
	}

	/**
	 * Check that the CAPTCHA was entered correctly. Turnstile (in reCAPTCHA compat mode) sets a long string in 'g-recaptcha-response'
	 * when the CAPTCHA is completed; we check that with the Turnstile API.
	 */
	public function validate_post(&$error)
	{
		require_once $this->directory.'recaptchalib_turnstile.php';

		if (ini_get('allow_url_fopen'))
			$recaptcha = new ReCaptcha(qa_opt('turnstile_private_key'));
		else
			$recaptcha = new ReCaptcha(qa_opt('turnstile_private_key'), new ReCaptchaSocketPostRequestMethod());

		$remoteIp = qa_remote_ip_address();
		$userResponse = qa_post_text('g-recaptcha-response');

		$recResponse = $recaptcha->verifyResponse($remoteIp, $userResponse);

		foreach ($recResponse->errorCodes as $code) {
			if (isset($this->errorCodeMessages[$code]))
				$error .= $this->errorCodeMessages[$code] . "\n";
		}

		return $recResponse->success;
	}
}
