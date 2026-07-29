<?php

namespace Drupal\styleguide\Plugin\Styleguide;

use Drupal\comment\Entity\Comment;
use Drupal\styleguide\Plugin\StyleguidePluginBase;

/**
 * Comment Styleguide items implementation.
 *
 * @Plugin(
 *   id = "comment_styleguide",
 *   label = @Translation("Comment Styleguide elements"),
 *   provider = "comment",
 * )
 */
class CommentStyleguide extends StyleguidePluginBase {

  /**
   * {@inheritdoc}
   */
  public function items() {
    $items = [];

    $items['comment'] = [
      'title' => $this->t('Comment'),
      'content' => $this->commentPrepare(),
      'group' => $this->t('Comment'),
    ];

    return $items;
  }

  /**
   * Helper method to prepare a fake comment.
   *
   * @return array
   *   A renderable array.
   */
  private function commentPrepare() {
    $comment = [
      'subject' => $this->generator->words(5),
      'comment_type' => 'comment',
      'cid' => 0,
    ];

    return [
      '#theme' => 'comment',
      '#comment' => Comment::create($comment),
      '#comment_threaded' => TRUE,
      'content' => $this->generator->paragraphs(),
    ];
  }

}
