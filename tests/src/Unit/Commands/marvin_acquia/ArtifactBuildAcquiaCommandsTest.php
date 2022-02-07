<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_acquia\Unit\Commands\marvin_acquia;

use Drush\Commands\marvin_acquia\ArtifactBuildAcquiaCommands;

/**
 * @covers \Drush\Commands\marvin_acquia\ArtifactBuildAcquiaCommands
 */
class ArtifactBuildAcquiaCommandsTest extends TestBase {

  public function testOnEventMarvinArtifactTypes(): void {
    $command = new ArtifactBuildAcquiaCommands();
    static::assertSame(
      [],
      $command->onEventMarvinArtifactTypes('library'),
    );

    static::assertSame(
      [
        'acquia' => [
          'label' => 'Acquia',
          'description' => 'Optimized for Acquia Cloud hosting',
        ],
      ],
      $command->onEventMarvinArtifactTypes('product'),
    );
  }

}
