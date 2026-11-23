<?php

declare(strict_types=1);

namespace DrupalEnv\Environment\Feature;

use Symfony\Component\Console\Style\SymfonyStyle;

interface SupportsXdebugAlwaysConnect {

  public function xdebugToggleAlwaysConnect(SymfonyStyle $io): void;

}
