<?php

declare(strict_types=1);

namespace Drupal\trash\Command;

use Drupal\trash\TrashCliActions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Exports the Trash overview page views via the core `dr` CLI.
 */
#[AsCommand(
  name: 'trash:export-views',
  description: 'Exports the Trash overview page views.',
  aliases: ['tev'],
)]
final class TrashExportViewsCommand extends Command {

  public function __construct(
    protected readonly TrashCliActions $trashCliActions,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('entity_type_id', InputArgument::OPTIONAL, 'The entity type to export the Trash overview page view for.')
      ->addOption('all', NULL, InputOption::VALUE_NONE, 'Export the Trash overview page view for all Trash entity types.')
      ->addOption('overwrite', NULL, InputOption::VALUE_NONE, 'Overwrite any existing views.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $entity_type_ids = $this->trashCliActions->getEnabledEntityTypes();
    if ($entity_type_ids === []) {
      $io->error($this->trashCliActions->noEntityTypesEnabledMessage());
      return Command::FAILURE;
    }

    $all = (bool) $input->getOption('all');
    $entity_type_id = $input->getArgument('entity_type_id');

    if ($all) {
      $confirmation = $this->trashCliActions->exportConfirmationQuestion(TRUE, NULL);
    }
    else {
      if (!$entity_type_id || !in_array($entity_type_id, $entity_type_ids, TRUE)) {
        $entity_type_id = $io->choice($this->trashCliActions->selectExportEntityTypeQuestion(), $this->trashCliActions->getEntityTypeLabels($entity_type_ids));
      }
      $entity_type_ids = [$entity_type_id];
      $confirmation = $this->trashCliActions->exportConfirmationQuestion(FALSE, $entity_type_id);
    }

    if (!$io->confirm($confirmation)) {
      return Command::SUCCESS;
    }

    $this->trashCliActions->exportViews($io, $entity_type_ids, (bool) $input->getOption('overwrite'));
    return Command::SUCCESS;
  }

}
