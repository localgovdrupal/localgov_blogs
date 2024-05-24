<?php

namespace Drupal\Tests\localgov_blogs\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Tests user blocks.
 *
 * @group localgov_guides
 */
class PrevNextBlockTest extends BrowserTestBase {

  use NodeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'path',
    'options',
    'localgov_blogs',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with the 'administer blocks' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('localgov_blogs_prev_next_block');
    $this->drupalLogout();
  }

  /**
   * Test the contents list block.
   */
  public function testPrevNextBlock() {

    // create channel
    $channel = $this->createNode([
      'title' => 'Blog Channel',
      'type' => 'localgov_blog_channel',
      'status' => NodeInterface::PUBLISHED,
    ]);

    // create 3 posts.
    $posts = [];
    for ($i = 1; $i <= 3; $i++) {
      $posts[] = $this->createNode([
        'title' => 'Blog post ' . $i,
        'type' => 'localgov_blog_post',
        'localgov_blog_date' => '2024-05-1' . $i,
        'status' => NodeInterface::PUBLISHED,
        'localgov_blog_channel' => ['target_id' => $channel->id()],
      ]);
    }
    $this->drupalGet($posts[0]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Prev');
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($posts[1]->toUrl()->toString());
    $this->drupalGet($posts[1]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Prev');
    $this->assertSession()->responseContains($posts[0]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($posts[2]->toUrl()->toString());
    $this->drupalGet($posts[2]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Prev');
    $this->assertSession()->responseContains($posts[1]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Next');
  }

}
