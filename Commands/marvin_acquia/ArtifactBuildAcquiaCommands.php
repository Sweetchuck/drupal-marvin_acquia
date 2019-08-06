<?php

declare(strict_types = 1);

namespace Drush\Commands\marvin_acquia;

use Drupal\marvin\Robo\VersionNumberTaskLoader;
use Drush\Commands\marvin_product\ArtifactBuildProductCommandsBase;
use Robo\Collection\CollectionBuilder;
use Robo\State\Data as RoboStateData;
use Sweetchuck\Robo\Git\GitComboTaskLoader;
use Sweetchuck\Robo\Git\GitTaskLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Finder\Finder;

class ArtifactBuildAcquiaCommands extends ArtifactBuildProductCommandsBase {

  use VersionNumberTaskLoader;
  use GitTaskLoader;
  use GitComboTaskLoader;

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

  /**
   * {@inheritdoc}
   */
  protected function getBuildSteps(): array {
    $steps = parent::getBuildSteps();

    $weight = $steps['prepareDirectory.marvin']['weight'] ?? 0;
    $steps['prepareDirectory.marvin_acquia'] = [
      'task' => $this->getTaskGitCloneAndClean(),
      'weight' => $weight + 1,
    ];

    $weight = $steps['copyFilesCollect.marvin']['weight'] ?? 0;
    $steps['copyFilesCollect.marvin_acquia'] = [
      'task' => $this->getTaskCollectFilesAcquia(),
      'weight' => $weight + 1,
    ];

    return $steps;
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskGitCloneAndClean() {
    $config = $this->getConfig();
    $projectId = (string) $config->get('marvin.acquia.projectId');
    if (!$projectId) {
      return function (): int {
        $logger = $this->getLogger();
        $logContext = [
          'taskName' => 'Git clone and clean',
        ];

        $logger->notice(
          '{taskName} - skipped because of the lack of marvin.acquia.projectId',
          $logContext
        );

        return 0;
      };
    }

    $remoteName = (string) $config->get('marvin.acquia.remoteName');
    $remoteUrl = (string) $config->get('marvin.acquia.remoteUrl');
    $remoteBranch = (string) $config->get('marvin.acquia.remoteBranch');
    $localBranch = (string) $config->get('marvin.acquia.localBranch');

    return $this
      ->taskGitCloneAndClean()
      ->setSrcDir($this->srcDir)
      ->setRemoteName($remoteName)
      ->setRemoteUrl($remoteUrl)
      ->setRemoteBranch($remoteBranch)
      ->setLocalBranch($localBranch)
      ->deferTaskConfiguration('setWorkingDirectory', 'buildDir');
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskCollectFilesAcquia() {
    return function (RoboStateData $data): int {
      if ($this->fs->exists("{$this->srcDir}/hooks/")) {
        $artifactDir = $this->getConfig()->get('marvin.artifactDir', 'artifacts');
        $artifactDirSafe = preg_quote($artifactDir, '@');

        /** @var \Drupal\marvin\ComposerInfo $composerInfo */
        $composerInfo = $data['composerInfo'];
        $docroot = $composerInfo->getDrupalRootDir();
        $docrootSafe = preg_quote($docroot, '@');

        $data['files'][] = (new Finder())
          ->in($this->srcDir)
          ->path('hooks')
          ->notPath("@^{$artifactDirSafe}@")
          ->notPath("@^{$docrootSafe}/sites/simpletest/@")
          ->ignoreDotFiles(FALSE)
          ->files();
      }

      return 0;
    };
  }

}
