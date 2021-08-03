<?php

declare(strict_types = 1);

namespace Drupal\Tests\marvin_acquia\Integration;

/**
 * @group marvin_acquia
 * @group drush-command
 *
 * @covers \Drush\Commands\marvin_acquia\ArtifactBuildAcquiaCommands<extended>
 */
class ArtifactTypesCommandsTest extends CommandsTestCase {

  public function testArtifactTypesJson() {
    $options = $this->getCommonCommandLineOptions();
    $options['format'] = 'json';
    $this->drush('marvin:artifact:types', [], $options);

    $expected = [
      'acquia' => [
        'label' => "Acquia",
        'description' => 'Optimized for Acquia Cloud hosting',
        'id' => 'acquia',
        'weight' => 0,
      ],
      'vanilla' => [
        'label' => "Vanilla",
        'description' => 'Not customized',
        'id' => 'vanilla',
        'weight' => 0,
      ],
    ];

    static::assertSame(
      json_encode($expected, JSON_PRETTY_PRINT) . PHP_EOL,
      $this->getOutputRaw()
    );
  }

  public function testArtifactTypesTable() {
    $options = $this->getCommonCommandLineOptions();
    $options['format'] = 'table';
    $this->drush('marvin:artifact:types', [], $options);

    $expected = implode(PHP_EOL, [
      ' ID      Label   Description                        ',
      ' acquia  Acquia  Optimized for Acquia Cloud hosting ',
      ' vanilla Vanilla Not customized                     ',
      '',
    ]);

    static::assertSame($expected, $this->getOutputRaw());
  }

}
