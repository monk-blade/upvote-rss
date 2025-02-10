<?php

namespace Auth;

abstract class Auth {

  // Get token
  abstract function getToken();

}

// Authenticate communities
include_once "auth/reddit.php";
