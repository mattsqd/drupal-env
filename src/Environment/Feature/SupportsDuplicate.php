<?php

declare(strict_types=1);

namespace DrupalEnv\Environment\Feature;

use Symfony\Component\Console\Style\SymfonyStyle;

interface SupportsDuplicate {

  public function duplicate(SymfonyStyle $io): void;

}
