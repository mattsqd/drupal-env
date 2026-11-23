<?php

declare(strict_types=1);

namespace DrupalEnv\Environment\Feature;

use Symfony\Component\Console\Style\SymfonyStyle;

interface SupportsPersonalServices {

  public function personalServices(SymfonyStyle $io): void;

}
