<?php

namespace Drupal\localgov_blogs\EventSubscriber;

use Drupal\localgov_core\Event\PageHeaderDisplayEvent;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters the Local Gov Page header.
 *
 * @package Drupal\localgov_blogs\EventSubscriber
 */
class PageHeaderSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PageHeaderDisplayEvent::EVENT_NAME => ['setPageHeader', 0],
    ];
  }

  /**
   * Hide page header block.
   */
  public function setPageHeader(PageHeaderDisplayEvent $event) {
    if ($event->getEntity() instanceof Node &&
          ($event->getEntity()->bundle() == 'localgov_blog_post')
      ) {
      $event->setVisibility(FALSE);
    }
  }

}
