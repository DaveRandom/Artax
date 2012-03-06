<?php

$cfg = [];

// application-wide debug output flag
$cfg['debug'] = TRUE;

// don't load HTTP app libs during boot
$cfg['httpBundle'] = FALSE;

// specify namespace paths for class autoloaders
$cfg['namespaces'] = [
  '' => AX_APP_PATH . '/src'
];

// specify event listeners
$cfg['listeners'] = [
  
  ['ax.shutdown', function() {
    echo PHP_EOL . '... ax.shutdown ...' . PHP_EOL;
  }],
  
  ['ax.uncaught_exception', function(\Exception $e) {
    $handler = $this->depProvider->make('controllers.ExHandler');
    $handler->setException($e)->exec()->getResponse()->output();
    throw new artax\exceptions\ScriptHaltException;
  }],
  
  ['ax.boot_complete', function() {
    echo PHP_EOL . '... ax.boot_complete ...' . PHP_EOL . PHP_EOL;
    $this->notify('app.questions');
  }],
  ['app.questions', function() {
    echo 'app.questions: What is your name?' . PHP_EOL;
    $this->notify('app.quest');
  }],
  ['app.questions', function() {
    echo 'app.questions: What is your quest?' . PHP_EOL;
    $this->notify('app.color');
  }],
  ['app.questions', function() {
    echo 'app.questions: What is your favorite color?' . PHP_EOL;
    $this->notify('app.swallow');
    return FALSE;
  }],
  ['app.questions', function() {
    echo 'app.questions: What is the airspeed velocity of an unladen swallow?' . PHP_EOL;
  }]
];