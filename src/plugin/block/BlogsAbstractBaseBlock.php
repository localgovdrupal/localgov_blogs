<?php

namespace Drupal\localgov_blogs\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Guide contents block.
 *
 * @package Drupal\localgov_blogs\Plugin\Block
 * )
 */
abstract class BlogsAbstractBaseBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Guide overview node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $channel;

  /**
   * Array of guide page nodes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $blogPages;

  /**
   * Guide node being displayed.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current route object.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Initialise new content block instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $route_match
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentRouteMatch $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    if ($this->routeMatch->getParameter('node')) {
      $this->node = $this->routeMatch->getParameter('node');
      if (!$this->node instanceof NodeInterface) {
        $node_storage = $this->entityTypeManager->getStorage('node');
        $this->node = $node_storage->load($this->node);
      }
    }
  }

  /**
   * Set block list of pages and format to display.
   */
  protected function setPages() {
    if (is_null($this->blogPages)) {
      if ($this->node->bundle() == 'localgov_blog_channel') {
        $this->channel = $this->node;
      }
      else {
        $this->channel = $this->node->localgov_blog_channel->entity;
      }

      $this->blogPages = $this->channel->localgov_blog_posts->referencedEntities();
      $this->blogPages = array_filter($this->blogPages, function ($blog_node) {
        return ($blog_node instanceof NodeInterface) && $blog_node->access('view');
      });
      $this->blogPages = array_values($this->blogPages);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($this->node && (
      ($this->node->bundle() == 'localgov_blog_channel' && !empty($this->node->localgov_blog_posts)) ||
      ($this->node->bundle() == 'localgov_blog_post' && !empty($this->node->localgov_blog_channel)&& !empty($this->node->localgov_blog_channel->entity))
    )) {
      return AccessResult::allowed();
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user', 'route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $this->setPages();
    $blog_posts_cache_tags = $this->prepareCacheTags(array_merge([$this->channel], $this->blogPages));
    return Cache::mergeTags(parent::getCacheTags(), $blog_posts_cache_tags);
  }

  /**
   * Prepare cache tags for the given items.
   *
   * @param array $cacheable_items
   *   Array of Drupal\Core\Cache\CacheableDependencyInterface objects.
   */
  protected function prepareCacheTags(array $cacheable_items): array {
    $list_of_tag_collections = array_map(function (CacheableDependencyInterface $cacheable_item): array {
      return $cacheable_item->getCacheTags();
    }, $cacheable_items);

    $merged_tags = array_reduce($list_of_tag_collections, [
      Cache::class,
      'mergeTags',
    ], []);
    return $merged_tags;
  }

}
