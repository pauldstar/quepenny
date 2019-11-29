<?php defined('BASEPATH') OR exit('No direct script access allowed');

class User extends CI_Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->load->model('user_model', '_user');
  }

  public function login()
  {
    $this->load->helper('url');

    $name = $this->input->post('login-name', TRUE);
    $password = $this->input->post('login-password', TRUE);
    isset($name) AND isset($password) OR redirect('login/100');

    $this->load->library('form_validation');

    $user = $this->_user->get_user($name, ['username', 'email'], TRUE);

    $user_exists = isset($user) && password_verify($password, $user->password);

    if (!$user_exists)
    {
      $this->form_validation->
        set_error('login_form', 'Login failed! Please try again...');
      $this->form_validation->save_data();
      redirect('login/100');
    }

    if ($user->email_verified === '0')
    {
      $this->_user->unverified_username($user->username);
      redirect('login/400');
    }

    $this->_user->login($user);

    redirect();
  }

  /**
   * Sign-up and send email verification to user
   *
   * @return void
   */
  public function signup()
  {
    $this->load->helper('url');
    $this->load->library('form_validation');
    $this->load->database();

    $this->form_validation->set_rules(
      'signup-firstname', 'First-Name', 'required'.
      "|regex_match[/^[A-Za-z][A-Za-z]*(?:-[A-Za-z]+)*(?:'[A-Za-z]+)*$/]"
    );
    $this->form_validation->set_rules(
      'signup-lastname', 'Last-Name', 'required'.
      "|regex_match[/^[A-Za-z][A-Za-z]*(?:-[A-Za-z]+)*(?:'[A-Za-z]+)*$/]"
    );
    $this->form_validation->set_rules(
      'signup-password', 'Password', 'required|min_length[8]'
    );
    $this->form_validation->set_rules(
      'signup-email', 'Email', 'required|valid_email|is_unique[user.email]'
    );
    $this->form_validation->set_rules(
      'signup-username', 'Username', 'required|max_length[20]'.
      '|regex_match[/^[A-Za-z][A-Za-z0-9]*(?:_[A-Za-z0-9]+)*$/]'.
      '|is_unique[user.username]'
    );

    if (!$this->form_validation->run())
    {
      $this->form_validation->save_data();
      redirect('login/200');
    }

    $user = $this->_user->create_user();

    if (!$user)
    {
      $this->form_validation->set_error('signup_form',
        'Server error. Sign Up failed! Please try again later.'
      );
      $this->form_validation->save_data();
      redirect('login/200');
    }

    self::send_email_verifier($user);
  }

  /**
   * Verify Email when user follows verification email link
   *
	 * @param string $username
	 * @param string $email_verifier
   * @return void
   */
  public function verify_email($username, $email_verifier)
  {
    $this->load->helper('url');
    $this->load->database();

    $row = $this->_user->get_user($username, 'username');

    isset($row) AND $row->email_verified === '0' OR redirect('login');

    if ($email_verifier !== $row->email_verifier)
    {
      $this->_user->unverified_username($row->username);
      redirect('login/400');
    }

    $this->_user->confirm_email_verification($row->user_id);

    redirect('login/300');
  }

  /**
   * Send email verification to user
   *
	 * @param array $user
   * @return void
   */
  public function send_email_verifier($user)
  {
    $this->load->helper('url');

    $data = is_array($user) ? $user :
      (array) $this->_user->get_user($user, 'username');

    if ($data['email_verified'] === '1') redirect('login/300');

    $this->load->library('email');

    $this->email->subject('Email Verification');
    $this->email->from('info@quepenny.com', 'QuePenny');

    $this->email->to($data['email']);
    $email_view = $this->load->view('template/verify_email', $data, TRUE);
    $this->email->message($email_view);

    $this->email->send();

    $this->_user->unverified_username($data['username']);

    redirect('login/400');
  }

  // TODO: remove test_email() and show_email()
  public function test_email()
  {
    $this->load->library('email');

    $this->email->subject('Email Verification');
    $this->email->from('info@quepenny.com', 'QuePenny');
    $this->email->to('paulogbeiwi@gmail.com');

    $data = (array) $this->_user->get_user(5);
    $email_view = $this->load->view('template/verify_email', $data, TRUE);
    $this->email->message($email_view);

    $this->email->send(FALSE);
    echo $this->email->print_debugger(['headers']);
  }

  public function show_email()
  {
    $data = (array) $this->_user->get_user(5);
    $this->load->view('template/verify_email', $data);
  }

  /**
   * Check if login/signup form input is valid
   * Called mostly by ajax calls
   *
	 * @param string $input_type
   * @return void
   */
  public function is_valid($input_type)
  {
    $input_text = $this->input->post('inputText', TRUE);

    $this->load->library('form_validation');

    $invalid_email = $input_type === 'email' &&
      !$this->form_validation->valid_email($input_text);

    if ($invalid_email)
    {
      echo json_encode(['response' => FALSE]);
      die();
    }

    $this->load->database();

    $is_unique = $this->form_validation->
      is_unique($input_text, "user.{$input_type}");

    echo json_encode(['response' => $is_unique]);
  }

  /**
   * Log user out
   *
   * @return void
   */
  public function logout()
  {
    session_destroy();
    $this->load->helper('url');
    redirect('login');
  }
}
