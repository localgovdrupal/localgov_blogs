<?php

namespace Drupal\localgov_blogs\Plugin\Block;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'BlogsPrevNextBlock' block.
 *
 * @Block(
 *  id = "localgov_blogs_prev_next_block",
 *  admin_label = @Translation("Blogs prev next block"),
 * )
 */
class BlogsPrevNextBlock extends BlogsAbstractBaseBlock {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $this->setPages();
    $previous_url = '';
    $previous_title = '';
    $next_url = '';
    $next_title = '';

    if ($this->node->bundle() == 'localgov_blog_post') {
      $page_delta = array_search($this->node, $this->blogPages, TRUE);
      if (!empty($this->blogPages[$page_delta - 1])) {
        $previous_url = $this->blogPages[$page_delta - 1]->toUrl();
        $previous_title = $this->blogPages[$page_delta - 1]->title->value;
      }
      if (!empty($this->blogPages[$page_delta + 1])) {
        $next_url = $this->blogPages[$page_delta + 1]->toUrl();
        $next_title = $this->blogPages[$page_delta + 1]->title->value;
      }
    }

    $build = [];
    $build[] = [
      '#theme' => 'blogs_prev_next_block',
      '#previous_url' => $previous_url,
      '#previous_title' => $previous_title,
      '#next_url' => $next_url,
      '#next_title' => $next_title,
      '#show_title' => $this->configuration['show_title'],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['show_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show blog title'),
      '#default_value' => $this->configuration['show_title'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);

    $values = $form_state->getValues();
    $this->configuration['show_title'] = $values['show_title'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'show_title' => FALSE,
    ];
  }

}
