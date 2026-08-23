<?php

namespace Drupal\osu_tours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides a block that displays role-based tour links.
 *
 * @Block(
 *   id = "role_based_tours_block",
 *   admin_label = @Translation("Role-Based Tours Block"),
 *   category = @Translation("OSU Tours")
 * )
 */
class RoleBasedToursBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $current_user = \Drupal::currentUser();
    $user_roles = array_map('strtolower', $current_user->getRoles());

    // Define the tour data grouped by category.
    $tour_data = [
      'People/Users' => [
        ['title' => 'Users – People Page Tour', 'path' => '/admin/people', 'roles' => ['manage_users', 'architect']],
        ['title' => 'Users – Add User', 'path' => '/admin/people/create', 'roles' => ['manage_users', 'architect']],
        ['title' => 'Users – Bulk Add CAS Users Page', 'path' => '/admin/people/create/cas-bulk', 'roles' => ['manage_users', 'architect']],
      ],
      'Content' => [
        ['title' => 'Edit Functions Tour', 'path' => '/node/add/page', 'roles' => ['content_authors', 'group_content_author', 'manage_content', 'architect']],
        ['title' => 'Content Overview', 'path' => '/admin/content', 'roles' => ['content_authors', 'manage_content', 'architect']],
        ['title' => 'Media', 'path' => '/admin/content/media', 'roles' => ['content_authors', 'manage_content', 'architect']],
        ['title' => 'Layout Builder', 'path' => '/node/86/layout', 'roles' => ['content_authors', 'group_content_author', 'manage_content', 'architect']],
      ],
      'Menus' => [
        ['title' => 'Menus – Menu overview page', 'path' => '/admin/structure/menu', 'roles' => ['manage_menus', 'architect']],
        ['title' => 'Menus – Edit menu', 'path' => '/admin/structure/menu/manage/main', 'roles' => ['manage_menus', 'architect']],
        ['title' => 'Menus – Edit link', 'path' => '/admin/structure/menu/item/91/edit', 'roles' => ['manage_menus', 'architect']],
      ],
      'Structure' => [
        ['title' => 'Block Layout Tour', 'path' => '/admin/structure/block', 'roles' => ['manage_blocks', 'architect']],
        ['title' => 'Content Type Creation', 'path' => '/admin/structure/types/add', 'roles' => ['manage_content', 'architect']],
        ['title' => 'Group Overview Page', 'path' => '/admin/group', 'roles' => ['architect']],
        ['title' => 'Parent Site Configuration', 'path' => '/admin/appearance/settings/madrone', 'roles' => ['manage_site_configuration', 'architect']],
      ],
      'Aliases & Redirects' => [
        ['title' => 'Redirects – Overview Page', 'path' => '/admin/config/search/redirect', 'roles' => ['content_authors', 'manage_content', 'manage_site_configuration', 'architect']],
        ['title' => 'Redirects – Create', 'path' => '/admin/config/search/redirect/add', 'roles' => ['content_authors', 'manage_content', 'manage_site_configuration', 'architect']],
        ['title' => 'Aliases – Overview page', 'path' => '/admin/config/search/path', 'roles' => ['content_authors', 'group_content_author', 'manage_content', 'manage_site_configuration', 'architect']],
      ],
    ];

    $grouped_links = [];

    foreach ($tour_data as $category => $tours) {
      foreach ($tours as $tour) {
        if (array_intersect($user_roles, array_map('strtolower', $tour['roles']))) {
          $grouped_links[$category][] = [
            'title' => $tour['title'],
            'url' => \Drupal\Core\Url::fromUserInput($tour['path'])->toString(),
          ];
        }
      }
    }

    return [
      '#theme' => 'role_based_tours_block',
      '#links' => $grouped_links,
      '#cache' => [
        'contexts' => ['user.roles'],
      ],
    ];
  }

}
