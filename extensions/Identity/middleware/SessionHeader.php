<?php

namespace MABI\Identity\Middleware;

use MABI\Middleware;
use MABI\Identity\Session;

include_once __DIR__ . '/../../../Middleware.php';

class SessionHeader extends Middleware {
  /**
   * @var \MABI\Identity\Session
   */
  public $session = NULL;

  /**
   * Call
   *
   * Pulls out a anonymous sent from an http header
   *
   * Perform actions specific to this middleware and optionally
   * call the next downstream middleware.
   */
  public function call() {
    $sessionId = $this->getController()->getApp()->getSlim()->request()->headers('SESSION');

    $foundSession = Session::init($this->getController()->getApp());
    if($foundSession->findById($sessionId)) {
      $this->session = $foundSession;
      $this->getController()->getApp()->getSlim()->request()->session = $this->session;
    }

    if (!empty($this->next)) {
      $this->next->call();
    }
  }

  public function documentMethod(\ReflectionClass $rClass, \ReflectionMethod $rMethod, array &$methodDoc) {
    parent::documentMethod($rClass, $rMethod, $methodDoc);

    $methodDoc['parameters'][] = array(
      'Name' => 'SESSION',
      'Required' => 'N',
      'Type' => 'string',
      'Location' => 'header',
      'Description' => 'A guid that identifies the current logged in session'
    );

    if (!empty($this->next)) {
      $this->next->documentMethod($rClass, $rMethod, $methodDoc);
    }
  }
}