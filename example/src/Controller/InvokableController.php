<?php

namespace Example\Controller;

use Piri\Attributes\Route;

class InvokableController
{
  #[Route('/invokable', name: 'invokable')]
  public function __invoke()
  {
    return 'This is an invokable controller';
  }
}