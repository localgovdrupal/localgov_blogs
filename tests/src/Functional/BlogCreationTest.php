<?php

namespace Drupal\Tests\localgov_blogs\Functional;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests LocalGov Blogs creation.
 *
 * @group localgov_page
 */
class BlogCreationTest extends BrowserTestBase {

  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'localgov_blogs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($admin_user);
  }

  /**
   * Test the node create forms.
   */
  public function testBlogChannelSelection() {

    // Check no blog channel message.
    $this->drupalGet('/node/add/localgov_blog_post');
    $this->assertSession()->pageTextContains('Warning message');
    $this->assertSession()->pageTextContains('There are no blogs channels.');
    $this->assertSession()->pageTextContains('Blog channel');

    // Check blog channel automatic selection when one channel.
    $this->drupalGet('/node/add/localgov_blog_channel');
    $channel = $this->drupalCreateNode([
      'type' => 'localgov_blog_channel',
      'title' => 'Channel 1',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $this->drupalGet('/node/add/localgov_blog_post');
    $this->assertSession()->pageTextNotContains('Blog channel');
    $this->submitForm([
      'title[0][value]' => 'Blog post 1',
      'body[0][summary]' => 'Blog post 1 summary',
      'body[0][value]' => 'Blog post 1 body',
    ], 'Save');
    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('title', 'Blog post 1')
      ->execute();
    $post = Node::load(reset($nids));
    $this->assertSame($channel->id(), $post->get('localgov_blog_channel')->target_id);

    // Check channel select for multiple channels.
    $this->drupalCreateNode([
      'type' => 'localgov_blog_channel',
      'title' => 'Channel 2',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $this->drupalGet('/node/add/localgov_blog_post');
    $this->assertSession()->pageTextContains('Blog channel');
    $this->assertSession()->responseContains('Channel 1');
    $this->assertSession()->responseContains('Channel 2');
  }

}
