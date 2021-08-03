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
   * {@inheritDoc}
   */
  protected $artifactType = 'acquia';

  /**
   * {@inheritDoc}
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
  protected function getInitialStateData(): array {
    $data = parent::getInitialStateData();
    $data['srcDir'] = $this->srcDir;

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function getBuildSteps(): array {
    $steps = parent::getBuildSteps();

    $weight = $steps['prepareDirectory.marvin']['weight'] ?? 0;
    $steps += $this->getBuildStepsGitCloneAndClean($weight);

    $weight = $steps['copyFilesCollect.marvin']['weight'] ?? 0;
    $steps['copyFilesCollect.marvin_acquia'] = [
      'task' => $this->getTaskCollectFilesAcquia(),
      'weight' => $weight + 1,
    ];

    return $steps;
  }

  /**
   * @todo Native Robo tasks.
   *
   * @return array[]
   */
  protected function getBuildStepsGitCloneAndClean(int $baseWeight): array {
    $config = $this->getConfig();
    $projectId = (string) $config->get('marvin.acquia.projectId');
    if (!$projectId) {
      return [
        'gitCloneAndClean.ignore.marvin_acquia' => [
          'task' => $this->getTaskMissingAcquiaProjectIdMessage('Git clone and clean'),
          'weight' => $baseWeight + 1,
        ],
      ];
    }

    $configNamePrefix = 'marvin.acquia.artifact';

    $cloneOptions = (array) $config->get("$configNamePrefix.gitCloneAndClean");

    return [
      'gitCloneAndClean.clone.marvin_acquia' => [
        'weight' => $baseWeight + 1,
        'task' => $this->getTaskGitCloneAndCleanClone($cloneOptions),
      ],
      'gitCloneAndClean.collectGitConfigNames.marvin_acquia' => [
        'weight' => $baseWeight + 2,
        'task' => $this->getTaskGitCloneAndCleanCollectGitConfigNames(),
      ],
      'gitCloneAndClean.copyGitConfig.marvin_acquia' => [
        'weight' => $baseWeight + 3,
        'task' => $this->getTaskGitCloneAndCleanCopyGitConfig(),
      ],
    ];
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskGitCloneAndCleanClone(array $options) {
    return $this
      ->taskGitCloneAndClean()
      ->setRemoteName($options['remoteName'] ?? '')
      ->setRemoteUrl($options['remoteUrl'] ?? '')
      ->setRemoteBranch($options['remoteBranch'] ?? '')
      ->setLocalBranch($options['localBranch'] ?? '')
      ->deferTaskConfiguration('setSrcDir', 'srcDir')
      ->deferTaskConfiguration('setWorkingDirectory', 'buildDir');
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskGitCloneAndCleanCollectGitConfigNames() {
    return function (RoboStateData $data): int {
      $gitConfigNamesToCopy = array_keys(
        $this->getConfig()->get('marvin.acquia.artifact.gitConfigNamesToCopy'),
        TRUE,
        TRUE
      );

      $data['gitConfigCopyItems'] = [];
      foreach ($gitConfigNamesToCopy as $name) {
        $data['gitConfigCopyItems'][$name] = [
          'name' => $name,
          'srcDir' => $this->srcDir,
          'dstDir' => $data['buildDir'],
        ];
      }

      return 0;
    };
  }

  /**
   * @return \Closure|\Robo\Contract\TaskInterface
   */
  protected function getTaskGitCloneAndCleanCopyGitConfig() {
    return $this
      ->taskForEach()
      ->deferTaskConfiguration('setIterable', 'gitConfigCopyItems')
      ->withBuilder(function (CollectionBuilder $builder, string $name, array $dirs) {
        $builder
          ->addTask(
            $this
              ->taskGitConfigGet()
              ->setWorkingDirectory($dirs['srcDir'])
              ->setSource('local')
              ->setName($name)
              ->setStopOnFail(FALSE)
          )
          ->addCode(function (RoboStateData $data) use ($name): int {
            $value = $data["git.config.$name"] ?? NULL;

            if ($value === NULL) {
              $data['gitConfigSetCommand'] = sprintf(
                'git config --unset %s || true',
                escapeshellarg($name)
              );

              return 0;
            }

            $data['gitConfigSetCommand'] = sprintf(
              'git config %s %s',
              escapeshellarg($name),
              escapeshellarg($value)
            );

            return 0;
          })
          ->addTask(
            $this
              ->taskExecStack()
              ->dir($dirs['dstDir'])
              ->deferTaskConfiguration('exec', 'gitConfigSetCommand')
          );
      });
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

  protected function getTaskMissingAcquiaProjectIdMessage(string $taskName) {
    return function () use ($taskName): int {
      $logger = $this->getLogger();

      $logger->warning(
        '{taskName} - skipped because of the lack of marvin.acquia.projectId',
        [
          'taskName' => $taskName,
        ]
      );

      return 0;
    };
  }

}
