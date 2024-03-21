<?php

namespace Drupal\localgov_blogs;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides helper to maintain overview page backreferences for blogs.
 */
class ChildParentRelationship implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ChildeParentRelationship constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Check backward references.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Channel node to have child references checked.
   */
  public function channelPagesCheck(NodeInterface $node) {
    $query = $this->entityTypeManager->getStorage('node')
      ->getQuery()
      ->condition('type', 'localgov_blog_post')
      ->condition('localgov_blog_channel', $node->id())
      ->accessCheck(TRUE);
    $actual_children = $query->execute();
    $linked_children = array_column($node->localgov_blog_posts->getValue(), 'target_id');
    $missing_children = array_diff($actual_children, $linked_children);
    $extra_children = array_diff($linked_children, $actual_children);
    foreach ($missing_children as $missing) {
      $node->localgov_blog_posts->appendItem(['target_id' => $missing]);
    }
    foreach ($extra_children as $extra) {
      foreach (array_keys($node->localgov_blog_posts->getValue(), ['target_id' => $extra]) as $offset) {
        $node->localgov_blog_posts->offsetUnset($offset);
      }
    }
  }

  /**
   * Update channel references for a page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The Blog post node.
   */
  public function pageUpdateChannel(NodeInterface $node) {
    if (
      ($previous = $node->original) &&
      ($previous->localgov_blog_channel->target_id != $node->localgov_blog_channel->target_id)
    ) {
      // There is a previous version of this page.
      // The blog channel it is in isn't the same in the previous and new
      // versions.
      if ($old_parent = $node->original->localgov_blog_channel->entity) {
        // Getting the old blog channel we look for all the references to this
        // page.
        foreach (array_keys($old_parent->localgov_blog_posts->getValue(), ['target_id' => $node->id()]) as $offset) {
          // And remove each reference on the old parent to this page.
          $old_parent->localgov_blog_posts->offsetUnset($offset);
        }
        $old_parent->save();
      }
    }
    if ($parent = $node->localgov_blog_channel->entity) {
      // The current version of this page points to a channel.
      if (array_search(['target_id' => $node->id()], $parent->localgov_blog_posts->getValue()) === FALSE) {
        // The channel does not yet point to this page, so we add it.
        $parent->localgov_blog_posts->appendItem(['target_id' => $node->id()]);
        $parent->save();
      }
    }
  }

}
