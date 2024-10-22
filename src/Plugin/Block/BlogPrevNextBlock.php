<?php

namespace Drupal\localgov_blogs\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Next Previous' block.
 *
 * @Block(
 *   id = "localgov_blogs_prev_next_block",
 *   admin_label = @Translation("Blog Previous Next block"),
 *   category = @Translation("Blocks")
 * )
 */
class BlogPrevNextBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Node being displayed.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Creates a PrevNextBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteMatchInterface $route_match,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entityTypeManager;
    if ($this->routeMatch->getParameter('node')) {
      $this->node = $this->routeMatch->getParameter('node');
      if (!$this->node instanceof NodeInterface) {
        $node_storage = $this->entityTypeManager->getStorage('node');
        $this->node = $node_storage->load($this->node);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $node = $this->routeMatch->getParameter('node');
    $previous_url = '';
    $previous_title = '';
    $next_url = '';
    $next_title = '';

    if ($node instanceof NodeInterface && $node->getType() == 'localgov_blog_post') {

      $prev = $this->generatePrevious($node);
      if (!empty($prev)) {
        $previous_title = $prev['title'];
        $previous_url = $prev['url'];
      }

      $next = $this->generateNext($node);
      if (!empty($next)) {
        $next_title = $next['title'];
        $next_url = $next['url'];
      }
    }

    return [
      '#theme' => 'localgov_blogs_prev_next_block',
      '#previous_url' => $previous_url,
      '#previous_title' => $previous_title,
      '#next_url' => $next_url,
      '#next_title' => $next_title,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Get the created time of the current node.
    $node = $this->routeMatch->getParameter('node');
    if (!empty($node) && $node instanceof NodeInterface) {
      // If there is node add its cachetag.
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      // Return default tags instead.
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * Lookup the previous node,youngest node which is still older than the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Show current page node id.
   *
   * @return array
   *   A render array for a previous node.
   */
  private function generatePrevious($node) {
    return $this->generateNextPrevious($node, 'prev');
  }

  /**
   * Lookup the next node,oldest node which is still younger than the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Show current page node id.
   *
   * @return array
   *   A render array for a next node.
   */
  private function generateNext($node) {
    return $this->generateNextPrevious($node, 'next');
  }

  const DIRECTION__NEXT = 'next';

  /**
   * Lookup the next or previous node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Get current page node id.
   * @param string $direction
   *   Default value is "next" and other value come from
   *   generatePrevious() and generatePrevious().
   *
   * @return array
   *   Find the alias of the next node.
   */
  private function generateNextPrevious($node, $direction = self::DIRECTION__NEXT) {
    $comparison_operator = '>=';
    $sort = 'ASC';
    $current_node_id = $node->id();
    $current_node_date = $node->get('localgov_blog_date')->value;
    $current_langcode = $node->get('langcode')->value;
    $current_blog_channel = $node->get('localgov_blog_channel')->target_id;

    if ($direction === 'prev') {
      $comparison_operator = '<=';
      $sort = 'DESC';
    }

    // Lookup 1 node younger (or older) than the current node
    // based upon the `localgov_blog_date` field.
    $storage = $this->entityTypeManager->getStorage('node');
    $query_result = $storage->getQuery();
    $results = $query_result->condition('localgov_blog_date', $current_node_date, $comparison_operator)
      ->condition('type', 'localgov_blog_post')
      ->condition('localgov_blog_channel', $current_blog_channel)
      ->condition('status', 1)
      ->condition('langcode', $current_langcode)
      ->sort('localgov_blog_date', $sort)
      ->sort('created', $sort)
      ->sort('nid', $sort)
      ->accessCheck(TRUE)
      ->execute();

    // Since sometimes the next / prev blog post could be on the same day, and
    // the query will not have sufficent granularity yet, we need to retrive
    // an array of all following / previous including the current post.
    // We can then search in the array and find the next one in the list.
    // @todo possibly remove if localgov_blog_date changed to a datetime field.
    $results = array_values($results);
    $index = array_search($current_node_id, $results, TRUE);
    $result = $results[$index + 1] ?? NULL;

    // If this is not the youngest (or oldest) node.
    if (!empty($result)) {
      $node = $storage->load($result);

      return [
        'title' => $node->get('title')->value,
        'url' => $node->toUrl()->toString(),
      ];
    }
    return '';
  }

}
