<?php

namespace Drupal\osu_groups\Plugin\RulesAction;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\rules\Context\ContextDefinition;
use Drupal\rules\Core\Attribute\RulesAction;
use Drupal\rules\Core\RulesActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add nodes to groups.
 */
#[RulesAction(
  id: "rules_group_make_content",
  label: new TranslatableMarkup("Make selected content belongs to selected group"),
  category: new TranslatableMarkup("Group Content"),
  context_definitions: [
    "node" => new ContextDefinition(
      data_type: "entity:node",
      label: new TranslatableMarkup("The node to add to the group."),
      description: new TranslatableMarkup("The node to add to the group."),
      assignment_restriction: "selector"
    ),
    "gid" => new ContextDefinition(
      data_type: "integer",
      label: new TranslatableMarkup("The group to add the node to."),
      required: FALSE,
      description: new TranslatableMarkup("The group to add the node to."),
      default_value: NULL
    ),
  ]
)]
class GroupMakeNodeContent extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * The Logger Factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The Entity Type Manager Interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new instance of the class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * Executes the process of adding a node to a group as a relationship.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to be added as a relationship in the group.
   * @param int $gid
   *   The group ID where the node should be added.
   *
   * @return void
   *   This method does not return a value.
   */
  protected function doExecute(NodeInterface $node, int $gid): void {
    $logger = $this->loggerFactory->get('osu_groups');

    // Log the incoming values.
    /** @var \Drupal\group\Entity\Storage\GroupStorage $groupStorage */
    try {
      $groupStorage = $this->entityTypeManager->getStorage('group');
    }
    catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      $logger->error('Failed to load group storage: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    /** @var \Drupal\group\Entity\Group $group */
    $group = $groupStorage->load($gid);
    $pluginId = "group_node:" . $node->getType();

    if ($group) {
      $existingRelationships = $group->getRelationshipsByEntity($node, $pluginId);
      // Check if the node is already a member of the group.
      if (!empty($existingRelationships)) {
        return;
      }
      $group->addRelationship($node, $pluginId);
    }
  }

}
