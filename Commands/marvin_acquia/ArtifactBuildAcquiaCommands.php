<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_acquia;

use Drupal\marvin\Robo\VersionNumberTaskLoader;
use Drush\Commands\marvin_product\ArtifactBuildProductCommandsBase;
use Robo\Collection\CollectionBuilder;
use Sweetchuck\Robo\Git\GitTaskLoader;
use Symfony\Component\Console\Input\InputInterface;

class ArtifactBuildAcquiaCommands extends ArtifactBuildProductCommandsBase {

  use VersionNumberTaskLoader;
  use GitTaskLoader;

  /**
   * {@inheritdoc}
   */
  protected $artifactType = 'acquia';

  /**
   * {@inheritdoc}
   */
  protected $drupalRootDir = 'docroot';

  /**
   * @hook on-event marvin:artifact:types
   */
  public function onEventMarvinArtifactTypes(string $projectType): array {
    if (!$this->isApplicable($projectType)) {
      return [];
    }

    return [
      $this->artifactType => [
        'label' => dt('Acquia'),
        'description' => dt('Optimized for Acquia Cloud hosting'),
      ],
    ];
  }

  /**
   * @command marvin:artifact:build:acquia
   * @bootstrap none
   *
   * @todo Validate "version-bump" option.
   * @todo Rename this method.
   */
  public function artifactBuildAcquia(
    array $options = [
      'version-bump' => 'minor',
    ]
  ): CollectionBuilder {
    return $this->delegate($this->artifactType);
  }

  /**
   * @hook on-event marvin:artifact:build
   */
  public function onEventMarvinArtifactBuild(InputInterface $input): array {
    $this->srcDir = '.';
    $this->artifactDir = (string) $this->getConfig()->get('marvin.artifactDir');
    $this->versionPartToBump = $input->getOption('version-bump');

    return $this->getBuildSteps();
  }

  /**
   * @hook on-event marvin:artifact:build:acquia
   */
  public function onEventMarvinArtifactBuildAcquia(InputInterface $input): array {
    $this->srcDir = '.';
    $this->artifactDir = (string) $this->getConfig()->get('marvin.artifactDir');
    $this->versionPartToBump = $input->getOption('version-bump');

    return $this->getBuildSteps();
  }

}
