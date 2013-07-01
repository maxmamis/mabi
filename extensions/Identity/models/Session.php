<?php

namespace MABI\Identity;

include_once __DIR__ . '/../../../Model.php';

use MABI\Model;

class Session extends Model {
  /**
   * @var \DateTime
   * @field internal
   */
  public $created;

  /**
   * @field internal
   * @var \DateTime
   */
  public $lastAccessed;

  /**
   * @var string
   * @field owner
   */
  public $user;

  /**
   * @var string
   * @field external
   */
  public $email;

  /**
   * @var string
   * @field external
   */
  public $password;
}
