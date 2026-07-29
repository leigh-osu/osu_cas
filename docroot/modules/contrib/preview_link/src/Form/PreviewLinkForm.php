<?php

declare(strict_types=1);

namespace Drupal\preview_link\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\preview_link\PreviewLinkExpiry;
use Drupal\preview_link\PreviewLinkHostInterface;
use Drupal\preview_link\PreviewLinkStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preview link form.
 *
 * @internal
 *
 * @property \Drupal\preview_link\Entity\PreviewLinkInterface $entity
 */
final class PreviewLinkForm extends ContentEntityForm {

  /**
   * PreviewLinkForm constructor.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    protected DateFormatterInterface $dateFormatter,
    protected PreviewLinkExpiry $linkExpiry,
    MessengerInterface $messenger,
    protected PreviewLinkHostInterface $previewLinkHost,
  ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('preview_link.link_expiry'),
      $container->get('messenger'),
      $container->get('preview_link.host'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'preview_link_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    $host = $this->getHostEntity($route_match);
    $previewLinks = $this->previewLinkHost->getPreviewLinks($host);
    if (count($previewLinks) > 0) {
      return reset($previewLinks);
    }
    else {
      $storage = $this->entityTypeManager->getStorage('preview_link');
      assert($storage instanceof PreviewLinkStorageInterface);
      $previewLink = PreviewLink::create()->addEntity($host);
      $previewLink->save();
      return $previewLink;
    }
  }

  /**
   * Get the entity referencing this Preview Link.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   A route match.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The host entity.
   */
  public function getHostEntity(RouteMatchInterface $routeMatch): EntityInterface {
    return parent::getEntityFromRouteMatch($routeMatch, $routeMatch->getRouteObject()->getOption('preview_link.entity_type_id'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RouteMatchInterface $routeMatch = NULL) {
    if (!isset($routeMatch)) {
      throw new \LogicException('Route match not populated from argument resolver');
    }

    $host = $this->getHostEntity($routeMatch);
    $description = $this->t('Generate a preview link for the <em>@entity_label</em> entity. Preview links will expire @lifetime after they were created.', [
      '@entity_label' => $host->label(),
      '@lifetime' => $this->dateFormatter->formatInterval($this->linkExpiry->getLifetime(), 1),
    ]);

    /** @var \Drupal\preview_link\Entity\PreviewLinkInterface $previewLink */
    $previewLink = $this->getEntity();
    $link = Url::fromRoute('entity.' . $host->getEntityTypeId() . '.preview_link', [
      $host->getEntityTypeId() => $host->id(),
      'preview_token' => $previewLink->getToken(),
    ]);

    $form = parent::buildForm($form, $form_state);
    $remainingSeconds = max(0, ($this->entity->getExpiry()?->getTimestamp() ?? 0) - $this->time->getRequestTime());
    $form['preview_link'] = [
      '#theme' => 'preview_link',
      '#title' => $this->t('Preview link'),
      '#weight' => -9999,
      '#description' => $description,
      '#remaining_lifetime' => $this->dateFormatter->formatInterval($remainingSeconds),
      '#link' => $link
        ->setAbsolute()
        ->toString(),
    ];

    $form['actions']['regenerate_submit'] = $form['actions']['submit'];
    $form['actions']['regenerate_submit']['#value'] = $this->t('Save and regenerate preview link');
    // Shift ::save to after ::regenerateToken.
    $form['actions']['regenerate_submit']['#submit'] = array_diff($form['actions']['regenerate_submit']['#submit'], ['::save']);
    $form['actions']['regenerate_submit']['#submit'][] = '::regenerateToken';
    $form['actions']['regenerate_submit']['#submit'][] = '::save';

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset lifetime'),
      '#submit' => ['::resetLifetime', '::save'],
      '#weight' => 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Preview Link saved.'));
    return $result;
  }

  /**
   * Regenerates preview link token.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function regenerateToken(array &$form, FormStateInterface $form_state): void {
    $this->entity->regenerateToken(TRUE);
    $this->messenger()->addMessage($this->t('The token has been regenerated.'));
  }

  /**
   * Resets the lifetime of the preview link.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetLifetime(array &$form, FormStateInterface $form_state): void {
    $expiry = new \DateTimeImmutable('@' . $this->time->getRequestTime());
    $expiry = $expiry->modify('+' . $this->linkExpiry->getLifetime() . ' seconds');
    $this->entity->setExpiry($expiry);
    $this->messenger()->addMessage($this->t('Preview link will now expire at %time.', [
      '%time' => $this->dateFormatter->format($expiry->getTimestamp()),
    ]));
  }

}
