<?php

declare(strict_types=1);

namespace Drupal\sitewide_alert\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\sitewide_alert\Entity\SitewideAlertInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a Sitewide Alert revision.
 *
 * @ingroup sitewide_alert
 */
class SitewideAlertRevisionDeleteForm extends ConfirmFormBase {

  /**
   * The Sitewide Alert revision.
   *
   * @var \Drupal\sitewide_alert\Entity\SitewideAlertInterface
   */
  protected SitewideAlertInterface $revision;

  /**
   * Constructs a new SitewideAlertRevisionDeleteForm.
   *
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $sitewideAlertStorage
   *   The entity storage.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Drupal's date formatter.
   */
  public function __construct(
    protected RevisionableStorageInterface $sitewideAlertStorage,
    protected Connection $connection,
    protected DateFormatterInterface $dateFormatter,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static(
      $entity_type_manager->getStorage('sitewide_alert'),
      $container->get('database'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sitewide_alert_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('entity.sitewide_alert.version_history', ['sitewide_alert' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $sitewide_alert_revision = NULL): array {
    $this->revision = $this->sitewideAlertStorage->loadRevision($sitewide_alert_revision);
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->sitewideAlertStorage->deleteRevision($this->revision->getRevisionId());

    $this->logger('content')
      ->notice('Sitewide Alert: deleted %title revision %revision.',
        [
          '%title' => $this->revision->label(),
          '%revision' => $this->revision->getRevisionId(),
        ]
      );
    $this->messenger()
      ->addMessage(
        $this->t(
          'Revision from %revision-date of Sitewide Alert %title has been deleted.',
          [
            '%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime()),
            '%title' => $this->revision->label(),
          ]
        )
      );
    $form_state->setRedirect(
      'entity.sitewide_alert.canonical',
       ['sitewide_alert' => $this->revision->id()]
    );
    // @todo change to dynamic query.
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {sitewide_alert_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.sitewide_alert.version_history',
         ['sitewide_alert' => $this->revision->id()]
      );
    }
  }

}
